<?php
require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../session_check.php';

header('Content-Type: application/json');

$order_id = $_GET['id'] ?? 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

if (empty($order['payment_method'])) {
    $order['payment_method'] = 'cash';
}

// Get order items. Use LEFT JOIN so items remain visible even if the product record was removed.
$stmt = $conn->prepare("SELECT si.*, COALESCE(p.name, si.product_name) as product_name
                        FROM sale_items si
                        LEFT JOIN products p ON si.product_id = p.id
                        WHERE si.sale_id = ?");
if (!$stmt) {
    error_log('Failed to prepare get_order_details items query: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'order' => $order,
    'items' => $items
]);
?>
