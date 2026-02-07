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
$user_id = $_SESSION['user_id'];

$conn->begin_transaction();

try {
    // Get order details
    $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Order not found or not pending');
    }

    $order = $result->fetch_assoc();

    // Get order items
    $stmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items_result = $stmt->get_result();

    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }

    // Deduct inventory for each item
    foreach ($items as $item) {
        $product_id = $item['product_id'];
        $quantity_sold = $item['quantity'];

        // Get all ingredients for this product
        $stmt = $conn->prepare("SELECT inventory_id, quantity_used FROM product_ingredients WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // No ingredients linked, skip inventory deduction
            continue;
        }

        while ($ingredient = $result->fetch_assoc()) {
            $inventory_id = $ingredient['inventory_id'];
            $quantity_per_product = $ingredient['quantity_used'];
            $total_quantity_to_deduct = $quantity_per_product * $quantity_sold;

            // Get current inventory quantity before deduction
            $check_stmt = $conn->prepare("SELECT item_name, quantity, unit FROM inventory WHERE id = ?");
            $check_stmt->bind_param("i", $inventory_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $inventory_item = $check_result->fetch_assoc();
            $quantity_before = $inventory_item['quantity'];

            // Check if there's enough inventory
            if ($quantity_before < $total_quantity_to_deduct) {
                throw new Exception("Insufficient inventory for {$inventory_item['item_name']}. Required: {$total_quantity_to_deduct} {$inventory_item['unit']}, Available: {$quantity_before} {$inventory_item['unit']}");
            }

            // Deduct from inventory
            $update_stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $update_stmt->bind_param("di", $total_quantity_to_deduct, $inventory_id);
            $update_stmt->execute();

            // Log the inventory change
            $log_stmt = $conn->prepare("INSERT INTO inventory_log (inventory_id, action, quantity, notes, user_id) VALUES (?, 'reduce', ?, ?, ?)");
            $notes = "Auto-deducted from order completion (Order: {$order['order_number']}, Product ID: $product_id, Qty: $quantity_sold)";
            $log_stmt->bind_param("idsi", $inventory_id, $total_quantity_to_deduct, $notes, $user_id);
            $log_stmt->execute();
        }
    }

    // Update order status to completed
    $stmt = $conn->prepare("UPDATE sales SET status = 'completed', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order completed successfully',
        'order_number' => $order['order_number']
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
