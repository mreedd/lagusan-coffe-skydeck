<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!has_role('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? '';

if ($type !== 'low_stock') {
    echo json_encode(['success' => false, 'message' => 'Invalid notification type']);
    exit;
}

// Get low stock items
$stmt = $conn->query("SELECT item_name, quantity, reorder_level FROM inventory WHERE quantity <= reorder_level");
$low_stock_items = $stmt->fetch_all(MYSQLI_ASSOC);

if (empty($low_stock_items)) {
    echo json_encode(['success' => false, 'message' => 'No low stock items to report']);
    exit;
}

// Create notification record
$user_id = $_SESSION['user_id'];
$message = "Low stock alert: " . count($low_stock_items) . " items need attention";
$stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, data) VALUES (?, 'low_stock', ?, ?)");
$data_json = json_encode($low_stock_items);
$admin_id = 1; // Assuming admin user ID is 1
$stmt->bind_param("iss", $admin_id, $message, $data_json);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Admin notified successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to notify admin']);
}
