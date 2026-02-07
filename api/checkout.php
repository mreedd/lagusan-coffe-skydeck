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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['items']) || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$customer_name = $data['customer_name'] ?? null;
$payment_method = $data['payment_method'] ?? 'cash';
// Validate payment method against allowed enum values
$allowed_payment_methods = ['cash', 'card', 'gcash', 'paymaya'];
if (!in_array($payment_method, $allowed_payment_methods, true)) {
    $payment_method = 'cash'; // Default to cash if invalid
}

$amount_paid = $data['amount_paid'];
$items = $data['items'];

$discount_type = $data['discount_type'] ?? null;
$discount_percentage = $data['discount_percentage'] ?? 0;
$discount_amount = $data['discount_amount'] ?? 0;
$discount_id_number = $data['discount_id_number'] ?? null;

// Calculate total
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$total_amount = $subtotal - $discount_amount;

$change_amount = $amount_paid - $total_amount;

// Generate order number
$order_number = generate_order_number();

// Start transaction
$conn->begin_transaction();

try {
    $cashier_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

    // Verify cashier exists to avoid FK constraint failures
    if (empty($cashier_id)) {
        throw new Exception('Invalid session: cashier not identified. Please login again.');
    }

    $uq = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $uq->bind_param('i', $cashier_id);
    $uq->execute();
    $ur = $uq->get_result();
    if (!$ur || $ur->num_rows === 0) {
        throw new Exception('Invalid cashier account. Please login with a valid user.');
    }

    $stmt = $conn->prepare("INSERT INTO sales (order_number, cashier_id, customer_name, subtotal, discount_type, discount_percentage, discount_amount, discount_id_number, total_amount, payment_method, amount_paid, change_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("sisdsddssddd", $order_number, $cashier_id, $customer_name, $subtotal, $discount_type, $discount_percentage, $discount_amount, $discount_id_number, $total_amount, $payment_method, $amount_paid, $change_amount);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert sale: ' . $stmt->error);
    }
    
    $sale_id = $conn->insert_id;
    
    $debug_info = [];
    
    // Insert sale items
    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($items as $item) {
        $subtotal = $item['price'] * $item['quantity'];

        // If this cart item comes from an inventory item (no product), find or create a product linked to that inventory
        $product_id = $item['id'];
        if (!empty($item['inventoryId'])) {
            $inventory_id = intval($item['inventoryId']);
            // Try to find existing product linked to this inventory
            $pq = $conn->prepare("SELECT p.id FROM products p JOIN product_ingredients pi ON p.id = pi.product_id WHERE pi.inventory_id = ? LIMIT 1");
            if ($pq) {
                $pq->bind_param('i', $inventory_id);
                $pq->execute();
                $pr = $pq->get_result();
                if ($pr && $pr->num_rows > 0) {
                    $product_row = $pr->fetch_assoc();
                    $product_id = $product_row['id'];
                }
            }

            // If not found, create a lightweight product record and link it to the inventory
            if (empty($product_id)) {
                $create_p = $conn->prepare("INSERT INTO products (name, category, price, status, created_at) VALUES (?, ?, ?, 'available', NOW())");
                $cat = 'uncategorized';
                $pname = $item['name'];
                $price = $item['price'] ?? 0.00;
                $create_p->bind_param('ssd', $pname, $cat, $price);
                if (!$create_p->execute()) {
                    throw new Exception('Failed to create product for inventory item: ' . $create_p->error);
                }
                $product_id = $conn->insert_id;

                // Link product_ingredients (quantity_used = 1 by default)
                $link = $conn->prepare("INSERT INTO product_ingredients (product_id, inventory_id, quantity_used) VALUES (?, ?, 1)");
                $link->bind_param('ii', $product_id, $inventory_id);
                $link->execute();
            }
        }

        $stmt->bind_param("iisidd", $sale_id, $product_id, $item['name'], $item['quantity'], $item['price'], $subtotal);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed (sale_items): ' . $stmt->error);
        }
    }
    
    // Commit transaction (sale created with status 'pending')
    $conn->commit();

    echo json_encode([
        'success' => true,
        'order_number' => $order_number,
        'payment_method' => $payment_method,
        'message' => 'Sale recorded as pending. Inventory will be deducted when the order is marked complete.'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log('Checkout error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error processing sale: ' . $e->getMessage()
    ]);
}

function deductInventoryForProduct($conn, $product_id, $quantity_sold, $user_id) {
    $debug = [
        'ingredients_found' => 0,
        'deductions' => []
    ];
    
    // Get all ingredients for this product
    $stmt = $conn->prepare("SELECT inventory_id, quantity_used FROM product_ingredients WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $debug['ingredients_found'] = $result->num_rows;
    
    if ($result->num_rows === 0) {
        $debug['message'] = 'No ingredients linked to this product';
        return $debug;
    }
    
    while ($ingredient = $result->fetch_assoc()) {
        $inventory_id = $ingredient['inventory_id'];
        $quantity_per_product = $ingredient['quantity_used'];
        $total_quantity_to_deduct = $quantity_per_product * $quantity_sold;

        // Get current inventory quantity and unit before deduction
        $check_stmt = $conn->prepare("SELECT item_name, quantity, unit FROM inventory WHERE id = ?");
        $check_stmt->bind_param("i", $inventory_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $inventory_item = $check_result->fetch_assoc();
        $quantity_before = $inventory_item['quantity'];
        $unit = $inventory_item['unit'];

        // Convert deduction quantity to base unit for consistent calculation
        $total_quantity_to_deduct_base = convert_to_base_unit($total_quantity_to_deduct, $unit);
        $quantity_before_base = convert_to_base_unit($quantity_before, $unit);

        // Calculate new quantity in base unit
        $quantity_after_base = $quantity_before_base - $total_quantity_to_deduct_base;

        // Convert back to display unit for storage
        $quantity_after = convert_from_base_unit($quantity_after_base, $unit);

        // Deduct from inventory
        $update_stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
        $update_stmt->bind_param("di", $quantity_after, $inventory_id);
        $update_stmt->execute();

        // Log the inventory change (log in base units for consistency)
        $log_stmt = $conn->prepare("INSERT INTO inventory_log (inventory_id, action, quantity, notes, user_id) VALUES (?, 'reduce', ?, ?, ?)");
        $notes = "Auto-deducted from sale (Product ID: $product_id, Qty: $quantity_sold)";
        $log_stmt->bind_param("idsi", $inventory_id, $total_quantity_to_deduct, $notes, $user_id);
        $log_stmt->execute();

        $debug['deductions'][] = [
            'inventory_id' => $inventory_id,
            'item_name' => $inventory_item['item_name'],
            'quantity_before' => $quantity_before,
            'quantity_deducted' => $total_quantity_to_deduct,
            'quantity_after' => $quantity_after
        ];
    }
    
    return $debug;
}
?>
