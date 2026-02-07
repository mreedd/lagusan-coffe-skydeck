<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

// Check if user has access (admin or cashier)
if (!has_role('admin') && !has_role('cashier')) {
    redirect('inventory.php');
}

$page_title = 'Current Orders';

// Get filter parameters and validate
$status_filter = $_GET['status'] ?? 'all'; // Default to all for order history
$date_filter = (has_role('cashier') && !has_role('admin')) ? date('Y-m-d') : ($_GET['date'] ?? '');

// Whitelist allowed status values
$allowed_status = ['all', 'completed', 'pending', 'cancelled', 'refunded'];
if (!in_array($status_filter, $allowed_status, true)) {
    $status_filter = 'pending';
}

// Validate date (YYYY-MM-DD) only if provided
if ($date_filter !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $date_filter);
    if (!$dt || $dt->format('Y-m-d') !== $date_filter) {
        $date_filter = '';
    }
}

// Prepare statement dynamically depending on filters
try {
    // First, verify the sales table has required columns
    $check_columns = $conn->query("SHOW COLUMNS FROM sales LIKE 'payment_method'");
    if ($check_columns->num_rows === 0) {
        echo "<div style='background: #fff3cd; padding: 20px; margin: 20px; border-radius: 8px; border-left: 4px solid #ffc107;'>";
        echo "<h3 style='color: #856404; margin-top: 0;'>⚠️ Database Structure Issue</h3>";
        echo "<p style='color: #856404;'>The sales table is missing required columns (payment_method, discount fields, etc.).</p>";
        echo "<p style='color: #856404;'><strong>To fix this:</strong></p>";
        echo "<ol style='color: #856404;'>";
        echo "<li>Open <code>db_check_sales.php</code> in your browser to see what's missing</li>";
        echo "<li>Run the SQL script: <code>scripts/fix_sales_table.sql</code></li>";
        echo "</ol>";
        echo "<p><a href='db_check_sales.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;'>Check Database Structure</a></p>";
        echo "</div>";
        include 'includes/footer.php';
        exit;
    }

    $sql = "SELECT s.id, s.order_number, s.customer_name, s.total_amount,
                   COALESCE(s.subtotal, s.total_amount) as subtotal,
                   COALESCE(s.discount_amount, 0) as discount_amount,
                   COALESCE(s.discount_percentage, 0) as discount_percentage,
                   s.discount_type,
                   COALESCE(NULLIF(s.payment_method, ''), 'cash') as payment_method,
                   s.status, s.created_at, u.username as cashier_name,
                   COUNT(si.id) as total_items
            FROM sales s
            LEFT JOIN users u ON s.cashier_id = u.id
            LEFT JOIN sale_items si ON s.id = si.sale_id";

    $where_conditions = [];
    $params = [];
    $types = '';

    // For cashiers, only show their own orders
    if (has_role('cashier') && !has_role('admin')) {
        $where_conditions[] = "s.cashier_id = ?";
        $params[] = $_SESSION['user_id'];
        $types .= 'i';
    }

    if ($status_filter !== 'all') {
        $where_conditions[] = "s.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }

    if ($date_filter !== '') {
        $where_conditions[] = "DATE(s.created_at) = ?";
        $params[] = $date_filter;
        $types .= 's';
    }

    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }

    $sql .= " GROUP BY s.id ORDER BY s.created_at DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log('DB prepare failed in current_orders.php: ' . $conn->error);
        echo "<div class='alert alert-error'>A database error occurred. Please contact the administrator.</div>";
        include 'includes/footer.php';
        exit;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $orders = $stmt->get_result();

} catch (mysqli_sql_exception $e) {
    error_log('Database error in current_orders.php: ' . $e->getMessage());
    echo "<div class='alert alert-error'>A database error occurred. Please contact the administrator.</div>";
    include 'includes/footer.php';
    exit;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Order History</h1>
        <p>View and manage all orders</p>
    </div>

    <div class="filters-bar">
        <form method="GET" class="filters-form">
            <?php if (has_role('admin')): ?>
            <div class="filter-group">
                <label>Date:</label>
                <input type="date" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
            </div>
            <?php endif; ?>

            <div class="filter-group">
                <label>Status:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Orders</option>
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
            </div>

            <button type="button" onclick="window.location.href='current_orders.php'" class="btn-secondary">Clear Filters</button>
        </form>
    </div>

    <div class="orders-table">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Subtotal</th>
                    <th>Discount</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Cashier</th>
                    <th>Date & Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($orders->num_rows === 0): ?>
                <tr>
                    <td colspan="11" style="text-align: center; padding: 40px; color: #999;">
                        No orders found<?php echo ($status_filter !== 'all' || $date_filter !== '') ? ' for the selected filters' : ''; ?>.
                    </td>
                </tr>
                <?php else: ?>
                <?php while ($order = $orders->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo safe_html($order['order_number']); ?></strong></td>
                    <td><?php echo safe_html($order['customer_name'] ?: 'Walk-in Customer'); ?></td>
                    <td><?php echo $order['total_items']; ?> items</td>
                    <td><?php echo format_currency($order['subtotal'] ?? $order['total_amount']); ?></td>
                    <td>
                        <?php if (!empty($order['discount_type'])): ?>
                            <span style="color: #28a745; font-size: 12px;">
                                <?php
                                    $discount_label = '';
                                    if ($order['discount_type'] === 'senior') $discount_label = 'Senior';
                                    elseif ($order['discount_type'] === 'pwd') $discount_label = 'PWD';
                                    elseif ($order['discount_type'] === 'vat_exempt') $discount_label = 'VAT Exempt';
                                    echo $discount_label . ' (' . $order['discount_percentage'] . '%)';
                                ?>
                                <br>-<?php echo format_currency($order['discount_amount']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #999;">None</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo format_currency($order['total_amount']); ?></strong></td>
                    <!-- Ensure payment_method displays correctly with proper formatting -->
                    <td>
                        <span style="background: #e8f4f8; padding: 4px 8px; border-radius: 4px; font-weight: 500;">
                            <?php echo strtoupper($order['payment_method'] ?? 'CASH'); ?>
                        </span>
                    </td>
                    <td><?php echo safe_html($order['cashier_name'] ?? 'N/A'); ?></td>
                    <td><?php echo format_datetime($order['created_at']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </td>
                    <td>
                        <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" class="btn-icon" title="View Details">
                            👁️
                        </button>
                        <?php if ($order['status'] === 'pending'): ?>
                        <button onclick="completeOrder(<?php echo $order['id']; ?>)" class="btn-icon" title="Complete Order" style="color: #28a745;">
                            ✅
                        </button>
                        <button onclick="addToOrder(<?php echo $order['id']; ?>)" class="btn-icon" title="Add Items" style="color: #007bff;">
                            ➕
                        </button>
                        <?php endif; ?>
                        <?php if (has_role('admin') || has_role('cashier')): ?>
                        <button onclick="cancelOrder(<?php echo $order['id']; ?>)" class="btn-icon" title="Cancel Order" style="color: #dc3545;">
                            ❌
                        </button>
                        <?php endif; ?>
                        <?php if (has_role('admin') || has_role('cashier')): ?>
                        <button onclick="printReceipt(<?php echo $order['id']; ?>)" class="btn-icon" title="Print Receipt">
                            🖨️
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Order Details Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Order Details</h2>
            <div class="modal-header-actions">
                <button class="scroll-btn" onclick="scrollModal('up')" title="Scroll Up">↑</button>
                <button class="scroll-btn" onclick="scrollModal('down')" title="Scroll Down">↓</button>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="orderDetailsContent" style="max-height: 400px; overflow-y: auto;">
            <!-- Order details will be loaded here -->
        </div>
    </div>
</div>

<!-- Password Confirmation Modal -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirm Cancellation</h2>
            <span class="close" onclick="closePasswordModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Please enter the admin password to confirm order cancellation:</p>
            <form id="passwordForm">
                <div class="form-group">
                    <label for="confirmPassword">Password:</label>
                    <input type="password" id="confirmPassword" name="password" required>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closePasswordModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.filters-bar {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filters-form {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 14px;
    font-weight: 500;
    color: #666;
}

.filter-group input,
.filter-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.orders-table {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.badge-completed {
    background: #d4edda;
    color: #155724;
}

.badge-pending {
    background: #fff3cd;
    color: #856404;
}

.badge-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.badge-refunded {
    background: #e2e3e5;
    color: #383d41;
}

.btn-icon {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    padding: 4px 8px;
}

.btn-icon:hover {
    opacity: 0.7;
}

/* Modal header actions */
.modal-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.scroll-btn {
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 30px;
    height: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #666;
    transition: all 0.3s;
}

.scroll-btn:hover {
    background: #e0e0e0;
    color: #333;
}

.scroll-btn:active {
    background: #d0d0d0;
}

/* Password Modal Styles */
#passwordModal .modal-content {
    max-width: 400px;
}

#passwordModal .modal-body {
    padding: 20px;
}

#passwordModal .form-group {
    margin-bottom: 20px;
}

#passwordModal label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

#passwordModal input[type="password"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-primary {
    background: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.btn-primary:hover {
    background: #0056b3;
}
</style>

<script>
function viewOrderDetails(orderId) {
    console.log('[v0] Fetching order details for ID:', orderId);

    fetch(`<?php echo SITE_URL; ?>/api/get_order_details.php?id=${orderId}`)
        .then(res => res.json())
        .then(data => {
            console.log('[v0] Order details response:', data);

            if (data.success) {
                const order = data.order;
                const items = data.items;

                let itemsHtml = items.map(item => `
                    <tr>
                        <td>${item.product_name}</td>
                        <td>${item.quantity}</td>
                        <td>${formatCurrency(item.price)}</td>
                        <td>${formatCurrency(item.subtotal)}</td>
                    </tr>
                `).join('');

                document.getElementById('orderDetailsContent').innerHTML = `
                    <div class="order-info">
                        <p><strong>Order Number:</strong> ${order.order_number}</p>
                        <p><strong>Customer:</strong> ${order.customer_name || 'Walk-in Customer'}</p>
                        <p><strong>Date:</strong> ${order.created_at}</p>
                        <!-- Ensure payment_method displays with proper formatting -->
                        <p><strong>Payment Method:</strong> <span style="background: #e8f4f8; padding: 4px 8px; border-radius: 4px; font-weight: 500;">${(order.payment_method || 'CASH').toUpperCase()}</span></p>
                        <p><strong>Status:</strong> <span class="badge badge-${order.status}">${order.status}</span></p>
                    </div>
                    <h3>Order Items</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                                <td><strong>${formatCurrency(order.total_amount)}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                `;

                document.getElementById('orderModal').style.display = 'block';
            } else {
                console.error('[v0] Failed to load order details:', data.message);
                alert('Failed to load order details: ' + data.message);
            }
        })
        .catch(error => {
            console.error('[v0] Error fetching order details:', error);
            alert('Error loading order details. Check console for details.');
        });
}

function closeModal() {
    document.getElementById('orderModal').style.display = 'none';
}

function scrollModal(direction) {
    const modalBody = document.getElementById('orderDetailsContent');
    const scrollAmount = 100; // pixels to scroll

    if (direction === 'up') {
        modalBody.scrollTop -= scrollAmount;
    } else if (direction === 'down') {
        modalBody.scrollTop += scrollAmount;
    }
}

function printReceipt(orderId) {
    window.open(`<?php echo SITE_URL; ?>/reports/receipt.php?id=${orderId}`, '_blank');
}

function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2);
}

function completeOrder(orderId) {
    if (!confirm('Are you sure you want to complete this order? This will deduct inventory and mark the order as completed.')) {
        return;
    }

    console.log('[v0] Completing order ID:', orderId);

    fetch(`<?php echo SITE_URL; ?>/api/complete_order.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ order_id: orderId })
    })
    .then(res => res.json())
    .then(data => {
        console.log('[v0] Complete order response:', data);

        if (data.success) {
            alert('Order completed successfully!');
            window.location.href = 'current_orders.php'; // Redirect to show current orders
        } else {
            alert('Failed to complete order: ' + data.message);
        }
    })
    .catch(error => {
        console.error('[v0] Error completing order:', error);
        alert('Error completing order. Check console for details.');
    });
}

function addToOrder(orderId) {
    // Redirect to POS with order ID to add items
    window.location.href = `pos.php?add_to_order=${orderId}`;
}

const userRole = '<?php echo $_SESSION['role'] ?? ''; ?>';
console.log('User role detected:', userRole);

let currentOrderId = null;

function cancelOrder(orderId) {
    currentOrderId = orderId;
    console.log('Cancel order called for ID:', orderId, 'User role:', userRole);

    // Only require password confirmation for cashiers
    if (userRole === 'cashier') {
        console.log('Showing password modal for cashier');
        document.getElementById('passwordModal').style.display = 'block';
        document.getElementById('confirmPassword').focus();
    } else {
        console.log('Showing confirmation dialog for admin');
        // For admin, proceed directly with confirmation
        if (confirm('Are you sure you want to cancel this order? This will restore the inventory and mark the order as cancelled.')) {
            proceedWithCancellation(orderId);
        }
    }
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    document.getElementById('passwordForm').reset();
    currentOrderId = null;
}

// Handle password form submission
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const password = document.getElementById('confirmPassword').value;

    if (!password) {
        alert('Please enter your password.');
        return;
    }

    proceedWithCancellation(currentOrderId, password);
});

function proceedWithCancellation(orderId, password = null) {
    console.log('[v0] Cancelling order ID:', orderId, 'Password provided:', password ? 'yes' : 'no');

    const requestData = { order_id: orderId };
    if (password !== null) {
        requestData.password = password;
    }

    fetch(`<?php echo SITE_URL; ?>/api/cancel_order.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestData)
    })
    .then(res => res.json())
    .then(data => {
        console.log('[v0] Cancel order response:', data);

        if (data.success) {
            alert('Order cancelled successfully!');
            closePasswordModal();
            location.reload(); // Refresh the page to show updated status
        } else {
            alert('Failed to cancel order: ' + data.message);
        }
    })
    .catch(error => {
        console.error('[v0] Error cancelling order:', error);
        alert('Error cancelling order. Check console for details.');
    });
}
</script>

<?php include 'includes/footer.php'; ?>
