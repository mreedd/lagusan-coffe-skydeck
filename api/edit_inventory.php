<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../includes/db_connect.php';

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
$item_name = trim($_POST['item_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$unit = trim($_POST['unit'] ?? '');
$quantity = floatval($_POST['quantity'] ?? 0);
$reorder_level = floatval($_POST['reorder_level'] ?? 0);
$cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);

if ($inventory_id <= 0 || empty($item_name) || empty($category) || empty($unit) || $reorder_level < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Check if item exists
$stmt = $conn->prepare("SELECT id FROM inventory WHERE id = ?");
$stmt->bind_param("i", $inventory_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
    exit;
}

$stmt = $conn->prepare("UPDATE inventory SET item_name = ?, category = ?, unit = ?, quantity = ?, reorder_level = ?, cost_per_unit = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("sssdidi", $item_name, $category, $unit, $quantity, $reorder_level, $cost_per_unit, $inventory_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Inventory item updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update inventory item']);
}
