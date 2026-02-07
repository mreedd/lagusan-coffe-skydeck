<?php
session_start();
require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$order_id = $data['order_id'];
$customer_name = $data['customer_name'] ?? '';
$payment_method = $data['payment_method'] ?? 'cash';
$discount_type = $data['discount_type'] ?? null;
$discount_percentage = $data['discount_percentage'] ?? 0;
$discount_amount = $data['discount_amount'] ?? 0;
$discount_id_number = $data['discount_id_number'] ?? null;
$amount_paid = $data['amount_paid'] ?? 0;
$items = $data['items'] ?? [];

$user_id = $_SESSION['user_id'];

$conn->begin_transaction();

try {
    // Get existing order
    $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Order not found or not pending');
    }

    $existing_order = $result->fetch_assoc();

    // Calculate new subtotal
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }

    $total = $subtotal - $discount_amount;

    // Update order
    $stmt = $conn->prepare("UPDATE sales SET
        customer_name = ?,
        subtotal = ?,
        discount_type = ?,
        discount_percentage = ?,
        discount_amount = ?,
        discount_id_number = ?,
        total_amount = ?,
        payment_method = ?,
        amount_paid = ?,
        updated_at = NOW()
        WHERE id = ?");

    $stmt->bind_param("sdssdssdii",
        $customer_name,
        $subtotal,
        $discount_type,
        $discount_percentage,
        $discount_amount,
        $discount_id_number,
        $total,
        $payment_method,
        $amount_paid,
        $order_id
    );
    $stmt->execute();

    // Delete existing sale items
    $stmt = $conn->prepare("DELETE FROM sale_items WHERE sale_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    // Insert new sale items
    foreach ($items as $item) {
        $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        $subtotal_item = $item['price'] * $item['quantity'];
        $stmt->bind_param("iisidd", $order_id, $item['id'], $item['name'], $item['quantity'], $item['price'], $subtotal_item);
        $stmt->execute();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order updated successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
