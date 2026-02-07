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

// Check if user has permission (admin or cashier)
if (!has_role('admin') && !has_role('cashier')) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$order_id = intval($data['order_id']);

// Only verify password for cashiers who are not admins
if (has_role('cashier') && !has_role('admin')) {
    if (!isset($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Admin password required for cashiers to cancel orders']);
        exit;
    }

    $password = $data['password'];

    // Verify against admin password (get first admin user)
    $stmt = $conn->prepare("SELECT password FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No admin user found']);
        exit;
    }
    $admin = $result->fetch_assoc();
    if (!password_verify($password, $admin['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid admin password']);
        exit;
    }
}

// Start transaction
$conn->begin_transaction();

try {
    // Check if order exists and is not already cancelled
    $stmt = $conn->prepare("SELECT status FROM sales WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Order not found');
    }
    $order = $result->fetch_assoc();
    if ($order['status'] === 'cancelled') {
        throw new Exception('Order is already cancelled');
    }

    // Update order status to cancelled
    $stmt = $conn->prepare("UPDATE sales SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to cancel order: ' . $stmt->error);
    }

    // Restore inventory for each sale item
    $stmt = $conn->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $sale_items = $stmt->get_result();

    while ($item = $sale_items->fetch_assoc()) {
        $product_id = $item['product_id'];
        $quantity_sold = $item['quantity'];

        // Get ingredients for this product
        $ing_stmt = $conn->prepare("SELECT inventory_id, quantity_used FROM product_ingredients WHERE product_id = ?");
        $ing_stmt->bind_param("i", $product_id);
        $ing_stmt->execute();
        $ingredients = $ing_stmt->get_result();

        while ($ingredient = $ingredients->fetch_assoc()) {
            $inventory_id = $ingredient['inventory_id'];
            $quantity_per_product = $ingredient['quantity_used'];
            $total_quantity_to_restore = $quantity_per_product * $quantity_sold;

            // Restore inventory quantity
            $update_stmt = $conn->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
            $update_stmt->bind_param("di", $total_quantity_to_restore, $inventory_id);
            $update_stmt->execute();

            // Log the inventory restoration
            $log_stmt = $conn->prepare("INSERT INTO inventory_log (inventory_id, action, quantity, notes, user_id) VALUES (?, 'add', ?, ?, ?)");
            $notes = "Restored from cancelled order (Order ID: $order_id, Product ID: $product_id, Qty: $quantity_sold)";
            $log_stmt->bind_param("idsi", $inventory_id, $total_quantity_to_restore, $notes, $_SESSION['user_id']);
            $log_stmt->execute();
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully'
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log('Cancel order error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error cancelling order: ' . $e->getMessage()
    ]);
}
?>
