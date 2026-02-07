<?php
session_start();
require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$product_id = $data['product_id'] ?? null;
$inventory_id = $data['inventory_id'] ?? null;
$quantity_used = $data['quantity_used'] ?? null;
$input_unit = $data['input_unit'] ?? null; // e.g., 'g', 'ml', 'oz', 'pcs'

if (!$product_id || !$inventory_id || !$quantity_used) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Get inventory item unit
$unit_stmt = $conn->prepare("SELECT unit FROM inventory WHERE id = ?");
$unit_stmt->bind_param("i", $inventory_id);
$unit_stmt->execute();
$unit_result = $unit_stmt->get_result();
$inventory_item = $unit_result->fetch_assoc();

if (!$inventory_item) {
    echo json_encode(['success' => false, 'message' => 'Inventory item not found']);
    exit;
}

$unit = $inventory_item['unit'];

// Determine assumed input unit based on inventory unit.
// UI currently asks users to enter quantities in base units (g for weight, ml for volume, pcs for count).
// We'll map the inventory unit to the expected input base unit and convert accordingly so
// `quantity_used` stored in `product_ingredients` is always in the same unit as the inventory item.
$inventory_unit = strtolower($unit);
$assumed_input_unit = 'g';
if (in_array($inventory_unit, ['g', 'kg', 'mg'])) {
    $assumed_input_unit = 'g';
} elseif (in_array($inventory_unit, ['ml', 'l', 'cl'])) {
    $assumed_input_unit = 'ml';
} elseif ($inventory_unit === 'pcs' || $inventory_unit === 'pieces') {
    $assumed_input_unit = 'pcs';
} else {
    // Fallback: treat as base unit (no conversion)
    $assumed_input_unit = $inventory_unit;
}

// Determine which input unit to use: prefer explicit input_unit from the UI if provided
$from_unit = $input_unit ? strtolower($input_unit) : $assumed_input_unit;

// Use helper convert_unit() to convert from the given input unit to the inventory unit.
$converted_quantity = (float) convert_unit((float) $quantity_used, $from_unit, $inventory_unit);

// Ensure reasonable precision
$converted_quantity = round($converted_quantity, 4);

// Check if ingredient already exists for this product
$check = $conn->prepare("SELECT id FROM product_ingredients WHERE product_id = ? AND inventory_id = ?");
$check->bind_param("ii", $product_id, $inventory_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This ingredient is already added to this product']);
    exit;
}

// Check inventory availability (do not deduct here; deduction happens on order completion).
$inv_stmt = $conn->prepare("SELECT quantity FROM inventory WHERE id = ?");
$inv_stmt->bind_param("i", $inventory_id);
$inv_stmt->execute();
$inv_res = $inv_stmt->get_result();
$inv_row = $inv_res->fetch_assoc();
if ($inv_row && isset($inv_row['quantity'])) {
    $available = (float) $inv_row['quantity'];
    if ($converted_quantity > $available) {
        echo json_encode(['success' => false, 'message' => 'Insufficient inventory available for this ingredient']);
        exit;
    }
}

$stmt = $conn->prepare("INSERT INTO product_ingredients (product_id, inventory_id, quantity_used) VALUES (?, ?, ?)");
$stmt->bind_param("iid", $product_id, $inventory_id, $converted_quantity);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Ingredient added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error adding ingredient']);
}
?>
