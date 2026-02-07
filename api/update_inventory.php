<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!has_role('admin') && !has_role('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$inventory_id = intval($_POST['inventory_id'] ?? 0);
$action = $_POST['action'] ?? '';
$quantity = floatval($_POST['quantity'] ?? 0);
$input_unit = trim($_POST['input_unit'] ?? ''); // unit user entered for this update
$notes = trim($_POST['notes'] ?? '');

if ($inventory_id <= 0 || empty($action) || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Get current quantity and unit
$stmt = $conn->prepare("SELECT quantity, unit FROM inventory WHERE id = ?");
$stmt->bind_param("i", $inventory_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
    exit;
}

// Convert input quantity (which may be in a different unit) to the inventory unit first
if ($input_unit) {
    // convert user's input quantity from input_unit -> inventory unit
    $quantity_converted_to_inv_unit = convert_unit($quantity, $input_unit, $item['unit']);
} else {
    // If no input unit provided, assume the input is already in the inventory's unit
    $quantity_converted_to_inv_unit = $quantity;
}

// Convert to base unit for consistent storage
$quantity_in_base = convert_to_base_unit($quantity_converted_to_inv_unit, $item['unit']);
$current_quantity_in_base = convert_to_base_unit($item['quantity'], $item['unit']);

$new_quantity_in_base = 0;
if ($action === 'add') {
    $new_quantity_in_base = $current_quantity_in_base + $quantity_in_base;
} elseif ($action === 'set') {
    $new_quantity_in_base = $quantity_in_base;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Convert back to display unit for storage
$new_quantity = convert_from_base_unit($new_quantity_in_base, $item['unit']);

$stmt = $conn->prepare("UPDATE inventory SET quantity = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("di", $new_quantity, $inventory_id);

if ($stmt->execute()) {
    // Log the update
    $user_id = $_SESSION['user_id'];
    // Log the input quantity and the unit used for this update (if provided)
    $log_stmt = $conn->prepare("INSERT INTO inventory_log (inventory_id, action, quantity, notes, user_id) VALUES (?, ?, ?, ?, ?)");
    $log_stmt->bind_param("isdsi", $inventory_id, $action, $quantity, $notes, $user_id);
    $log_stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Stock updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update stock']);
}
