<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

// Check if user is staff
if (!has_role('staff')) {
    redirect('index.php');
}

$page_title = 'Staff Dashboard';

// Get low stock items count
$stmt = $conn->query("SELECT COUNT(*) as low_stock FROM inventory WHERE quantity <= reorder_level");
$low_stock = $stmt->fetch_assoc()['low_stock'];

// Get total inventory items
$stmt = $conn->query("SELECT COUNT(*) as total_items FROM inventory");
$total_items = $stmt->fetch_assoc()['total_items'];

// Get pending reorders
$stmt = $conn->query("SELECT COUNT(*) as pending_reorders FROM reorder_list WHERE status = 'pending'");
$pending_reorders = $stmt->fetch_assoc()['pending_reorders'];

// Get today's wastage count
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as today_wastage FROM wastage_log WHERE DATE(recorded_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_wastage = $stmt->get_result()->fetch_assoc()['today_wastage'];

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Staff Dashboard</h1>
        <p>Inventory monitoring and management</p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-info">
                <h3><?php echo $total_items; ?></h3>
                <p>Total Inventory Items</p>
            </div>
        </div>
        
        <div class="stat-card alert">
            <div class="stat-icon">⚠️</div>
            <div class="stat-info">
                <h3><?php echo $low_stock; ?></h3>
                <p>Low Stock Alerts</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🔄</div>
            <div class="stat-info">
                <h3><?php echo $pending_reorders; ?></h3>
                <p>Pending Reorders</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🗑️</div>
            <div class="stat-info">
                <h3><?php echo $today_wastage; ?></h3>
                <p>Today's Wastage</p>
            </div>
        </div>
    </div>
    
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="action-buttons">
            <a href="inventory.php" class="btn btn-primary">
                <span>📦</span> View Inventory
            </a>
            <a href="inventory.php?tab=low-stock" class="btn btn-warning">
                <span>⚠️</span> Low Stock Alerts
            </a>
            <a href="inventory.php?tab=reorder" class="btn btn-info">
                <span>🔄</span> Reorder List
            </a>
            <a href="inventory.php?tab=wastage" class="btn btn-secondary">
                <span>🗑️</span> Record Wastage
            </a>
        </div>
    </div>
    
    <div class="recent-alerts">
        <h3>Recent Low Stock Alerts</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Current Stock</th>
                    <th>Reorder Level</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->query("SELECT * FROM inventory WHERE quantity <= reorder_level ORDER BY quantity ASC LIMIT 10");
                if ($stmt->num_rows === 0):
                ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                        No low stock alerts. All inventory levels are good!
                    </td>
                </tr>
                <?php else: ?>
                <?php while ($item = $stmt->fetch_assoc()): ?>
                <tr>
                    <td><?php echo safe_html($item['item_name']); ?></td>
                    <td class="text-danger"><strong><?php echo $item['quantity']; ?></strong></td>
                    <td><?php echo $item['reorder_level']; ?></td>
                    <td><?php echo safe_html($item['unit']); ?></td>
                    <td>
                        <?php if ($item['quantity'] == 0): ?>
                            <span class="badge badge-danger">Out of Stock</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Low Stock</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="addToReorder(<?php echo $item['id']; ?>)">
                            Add to Reorder
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
function addToReorder(inventoryId) {
    if (!confirm('Add this item to reorder list?')) return;
    
    fetch('<?php echo SITE_URL; ?>/api/add_to_reorder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ inventory_id: inventoryId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Item added to reorder list');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
