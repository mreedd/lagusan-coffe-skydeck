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

// Calculate total inventory cost: SUM(quantity * cost_per_unit)
$stmt = $conn->query("SELECT SUM(quantity * cost_per_unit) as total_cost FROM inventory WHERE cost_per_unit > 0");

if ($stmt) {
    $result = $stmt->fetch_assoc();
    $total_cost = $result['total_cost'] ?? 0.00;

    echo json_encode([
        'success' => true,
        'total_cost' => round($total_cost, 2)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to calculate inventory cost']);
}
?>
