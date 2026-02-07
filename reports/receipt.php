<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../includes/db_connect.php';

// Check if user has access (admin or cashier)
if (!has_role('admin') && !has_role('cashier')) {
    redirect('../dashboard.php');
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    die('Invalid order ID');
}

// Get order details
$stmt = $conn->prepare("
    SELECT s.*, u.username as cashier_name
    FROM sales s
    LEFT JOIN users u ON s.cashier_id = u.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die('Order not found');
}

// Get order items
$stmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Coffee shop details
$shop_name = 'Lagusan Coffee Skydeck';
$shop_address = 'Lot 4B Block 112 Lee Drive Villa Vienna, Brgy. Greater Lagro., Quezon City, Philippines';
$shop_email = 'lagusancoffee@gmail.com';
$shop_hours = 'Monday to Sunday 2:00 PM to 2:00 AM';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo safe_html($order['order_number']); ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 10px;
            max-width: 300px;
            margin: 0 auto;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .shop-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .shop-details {
            font-size: 10px;
            margin-bottom: 5px;
        }

        .receipt-title {
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
            text-align: center;
        }

        .order-info {
            margin-bottom: 10px;
        }

        .order-info div {
            margin-bottom: 3px;
        }

        .items-table {
            width: 100%;
            margin-bottom: 10px;
        }

        .items-table th,
        .items-table td {
            text-align: left;
            padding: 2px 0;
        }

        .items-table .qty {
            text-align: center;
            width: 30px;
        }

        .items-table .price {
            text-align: right;
            width: 60px;
        }

        .total-line {
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
            font-weight: bold;
        }

        .footer {
            text-align: center;
            border-top: 1px dashed #000;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 10px;
        }

        @media print {
            body {
                margin: 0;
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-header">
        <div class="shop-name"><?php echo safe_html($shop_name); ?></div>
        <div class="shop-details"><?php echo safe_html($shop_address); ?></div>
        <div class="shop-details"><?php echo safe_html($shop_email); ?></div>
        <div class="shop-details"><?php echo safe_html($shop_hours); ?></div>
    </div>

    <div class="receipt-title">RECEIPT</div>

    <div class="order-info">
        <div><strong>Order #:</strong> <?php echo safe_html($order['order_number']); ?></div>
        <div><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></div>
        <div><strong>Cashier:</strong> <?php echo safe_html($order['cashier_name'] ?? 'N/A'); ?></div>
        <div><strong>Customer:</strong> <?php echo safe_html($order['customer_name'] ?: 'Walk-in Customer'); ?></div>
        <div><strong>Payment:</strong> <?php echo strtoupper($order['payment_method'] ?? 'CASH'); ?></div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="qty">Qty</th>
                <th class="price">Price</th>
                <th class="price">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo safe_html($item['product_name']); ?></td>
                <td class="qty"><?php echo $item['quantity']; ?></td>
                <td class="price"><?php echo number_format($item['price'], 2); ?></td>
                <td class="price"><?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-line">
        <div><strong>Subtotal: ₱<?php echo number_format($order['subtotal'] ?? $order['total_amount'], 2); ?></strong></div>
        <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
        <div><strong>Discount (<?php echo $order['discount_percentage']; ?>%): -₱<?php echo number_format($order['discount_amount'], 2); ?></strong></div>
        <?php endif; ?>
        <div><strong>Total: ₱<?php echo number_format($order['total_amount'], 2); ?></strong></div>
        <?php if (($order['payment_method'] ?? 'cash') === 'cash'): ?>
        <div><strong>Amount Paid: ₱<?php echo number_format($order['amount_paid'], 2); ?></strong></div>
        <div><strong>Change: ₱<?php echo number_format($order['change_amount'] ?? 0, 2); ?></strong></div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <div>Thank you for your business!</div>
        <div>Please come again.</div>
    </div>

    <script>
        // Auto-print when page loads
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
