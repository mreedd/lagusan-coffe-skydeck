<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!has_role('admin') && !has_role('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$inventory_id = intval($data['inventory_id'] ?? 0);

if ($inventory_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid inventory ID']);
    exit;
}

// Check if already in reorder list
$stmt = $conn->prepare("SELECT id FROM reorder_list WHERE inventory_id = ? AND status = 'pending'");
$stmt->bind_param("i", $inventory_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Item already in reorder list']);
    exit;
}

// Get inventory details
$stmt = $conn->prepare("SELECT quantity, reorder_level FROM inventory WHERE id = ?");
$stmt->bind_param("i", $inventory_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
    exit;
}

// Calculate suggested quantity (2x reorder level - current quantity)
$suggested_quantity = max(($item['reorder_level'] * 2) - $item['quantity'], $item['reorder_level']);

// Determine priority
$priority = 'medium';
if ($item['quantity'] == 0) {
    $priority = 'high';
} elseif ($item['quantity'] < $item['reorder_level'] / 2) {
    $priority = 'high';
}

$stmt = $conn->prepare("INSERT INTO reorder_list (inventory_id, suggested_quantity, priority) VALUES (?, ?, ?)");
$stmt->bind_param("ids", $inventory_id, $suggested_quantity, $priority);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Item added to reorder list']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add to reorder list']);
}
