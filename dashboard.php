<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

// Check if user is admin
if (!has_role('admin')) {
    redirect('pos.php');
}

$page_title = 'Dashboard';

// Get today's sales
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as today_sales FROM sales WHERE DATE(created_at) = ? AND status = 'completed'");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_sales = $stmt->get_result()->fetch_assoc()['today_sales'];

// Get total orders today
$stmt = $conn->prepare("SELECT COUNT(*) as today_orders FROM sales WHERE DATE(created_at) = ? AND status = 'completed'");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_orders = $stmt->get_result()->fetch_assoc()['today_orders'];

// Get low stock items
$stmt = $conn->query("SELECT COUNT(*) as low_stock FROM inventory WHERE quantity <= reorder_level");
$low_stock = $stmt->fetch_assoc()['low_stock'];

// Get total products
$stmt = $conn->query("SELECT COUNT(*) as total_products FROM products WHERE status = 'available'");
$total_products = $stmt->fetch_assoc()['total_products'];

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Sales overview and analytics</p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-info">
                <h3><?php echo format_currency($today_sales); ?></h3>
                <p>Today's Sales</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div class="stat-info">
                <h3><?php echo $today_orders; ?></h3>
                <p>Orders Today</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">⚠️</div>
            <div class="stat-info">
                <h3><?php echo $low_stock; ?></h3>
                <p>Low Stock Items</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🍽️</div>
            <div class="stat-info">
                <h3><?php echo $total_products; ?></h3>
                <p>Available Products</p>
            </div>
        </div>
    </div>
    
    <div class="charts-grid">
        <div class="chart-card">
            <h3>Sales This Week</h3>
            <div class="chart-container"><canvas id="salesChart"></canvas></div>
        </div>
        
        <div class="chart-card">
            <h3>Top Selling Products</h3>
            <div class="chart-container"><canvas id="productsChart"></canvas></div>
        </div>
    </div>
    
    <div class="recent-orders">
        <h3>Recent Orders</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->query("SELECT *, COALESCE(NULLIF(payment_method, ''), 'cash') as payment_method FROM sales ORDER BY created_at DESC LIMIT 10");
                if ($stmt->num_rows === 0):
                ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                        No orders yet. Start selling to see orders here.
                    </td>
                </tr>
                <?php else: ?>
                <?php while ($order = $stmt->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $order['order_number']; ?></td>
                    <td><?php echo $order['customer_name'] ?: 'Walk-in'; ?></td>
                    <td><?php echo format_currency($order['total_amount']); ?></td>
                    <td><?php echo strtoupper($order['payment_method']); ?></td>
                    <td><?php echo format_datetime($order['created_at']); ?></td>
                    <td><span class="badge badge-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<style>
/* Tablet adjustments for dashboard (768px - 1024px) */
@media (min-width: 768px) and (max-width: 1024px) {
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 18px;
    }

    .stat-card {
        padding: 18px;
        border-radius: 10px;
    }

    .stat-icon {
        font-size: 36px;
    }

    .stat-info h3 {
        font-size: 22px;
    }

    .charts-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    .chart-container {
        height: 360px;
    }
}
</style>

<script>
// Sales Chart
fetch('<?php echo SITE_URL; ?>/api/get_sales_data.php?period=week')
    .then(res => res.json())
    .then(data => {
        new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Sales',
                    data: data.values,
                    borderColor: '#96715e',
                    backgroundColor: 'rgba(150, 113, 94, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    });

// Products Chart
fetch('<?php echo SITE_URL; ?>/api/get_sales_data.php?type=top_products')
    .then(res => res.json())
    .then(data => {
        new Chart(document.getElementById('productsChart'), {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Quantity Sold',
                    data: data.values,
                    backgroundColor: '#96715e'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
