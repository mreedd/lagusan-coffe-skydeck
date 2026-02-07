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
$reorder_id = intval($data['reorder_id'] ?? 0);

if ($reorder_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reorder ID']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM reorder_list WHERE id = ?");
$stmt->bind_param("i", $reorder_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Item removed from reorder list']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
}
