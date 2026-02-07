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
$status = $data['status'] ?? '';

if ($reorder_id <= 0 || !in_array($status, ['pending', 'ordered', 'completed'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$stmt = $conn->prepare("UPDATE reorder_list SET status = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("si", $status, $reorder_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}
