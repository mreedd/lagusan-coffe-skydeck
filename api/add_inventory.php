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

$item_name = trim($_POST['item_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$quantity = floatval($_POST['quantity'] ?? 0);
$unit = trim($_POST['unit'] ?? '');
$reorder_level = floatval($_POST['reorder_level'] ?? 0);
$cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);

if (empty($item_name) || empty($category) || empty($unit)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO inventory (item_name, category, quantity, unit, reorder_level, cost_per_unit) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssdsdd", $item_name, $category, $quantity, $unit, $reorder_level, $cost_per_unit);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Inventory item added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add inventory item']);
}
