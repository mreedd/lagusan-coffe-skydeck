<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

// Check if user has access (admin or staff)
if (!has_role('admin') && !has_role('staff')) {
    redirect('index.php');
}

$page_title = 'Inventory Management';
$active_tab = $_GET['tab'] ?? 'all';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Inventory Management</h1>
        <p>Monitor stock levels and manage inventory</p>
    </div>
    
    <div class="tabs">
        <button class="tab-btn <?php echo $active_tab === 'all' ? 'active' : ''; ?>" onclick="switchTab('all')">
            All Inventory
        </button>
        <button class="tab-btn <?php echo $active_tab === 'low-stock' ? 'active' : ''; ?>" onclick="switchTab('low-stock')">
            Low Stock Alerts
        </button>
        <button class="tab-btn <?php echo $active_tab === 'reorder' ? 'active' : ''; ?>" onclick="switchTab('reorder')">
            Reorder List
        </button>
        <button class="tab-btn <?php echo $active_tab === 'wastage' ? 'active' : ''; ?>" onclick="switchTab('wastage')">
            Wastage Log
        </button>
        <button class="tab-btn <?php echo $active_tab === 'reports' ? 'active' : ''; ?>" onclick="switchTab('reports')">
            Reports
        </button>
    </div>
    
     All Inventory Tab 
    <div id="tab-all" class="tab-content <?php echo $active_tab === 'all' ? 'active' : ''; ?>">
        <div class="toolbar">
            <input type="text" id="searchInventory" placeholder="Search inventory..." class="search-input">
            <button class="btn btn-primary" onclick="openAddInventoryModal()">
                <span>➕</span> Add New Item
            </button>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Reorder Level</th>
                    <th>Cost per Unit</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="inventoryTableBody">
                <?php
                $stmt = $conn->query("SELECT * FROM inventory ORDER BY item_name ASC");
                if ($stmt->num_rows === 0):
                ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                        No inventory items yet. Click "Add New Item" to start.
                    </td>
                </tr>
                <?php else: ?>
                <?php while ($item = $stmt->fetch_assoc()): ?>
                <tr>
                    <td><?php echo safe_html($item['item_name']); ?></td>
                    <td><?php echo safe_html($item['category']); ?></td>
                    <td>
                        <?php if ($item['quantity'] <= $item['reorder_level']): ?>
                            <span class="text-danger"><strong><?php echo $item['quantity']; ?></strong></span>
                        <?php else: ?>
                            <?php echo $item['quantity']; ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo safe_html($item['unit']); ?></td>
                    <td><?php echo $item['reorder_level']; ?></td>
                    <td><?php echo $item['cost_per_unit'] > 0 ? '₱' . number_format($item['cost_per_unit'], 2) : '-'; ?></td>
                    <td><?php echo format_datetime($item['updated_at']); ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" onclick="toggleDropdown(<?php echo $item['id']; ?>)">
                                <span>⚙️</span> Actions
                            </button>
                            <div id="dropdown-<?php echo $item['id']; ?>" class="dropdown-menu">
                                <button class="dropdown-item" onclick="openEditModal(<?php echo $item['id']; ?>)">Edit</button>
                                <button class="dropdown-item" onclick="openUpdateModal(<?php echo $item['id']; ?>)">Update Stock</button>
                                <button class="dropdown-item" onclick="openWastageModal(<?php echo $item['id']; ?>)">Record Wastage</button>
                                <button class="dropdown-item text-danger" onclick="deleteInventoryItem(<?php echo $item['id']; ?>)">Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    
    <div id="tab-low-stock" class="tab-content <?php echo $active_tab === 'low-stock' ? 'active' : ''; ?>">
        <div class="alert-box">
            <h3>⚠️ Low Stock Alerts</h3>
            <p>Items below reorder level that need attention</p>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Current Stock</th>
                    <th>Reorder Level</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->query("SELECT * FROM inventory WHERE quantity <= reorder_level ORDER BY quantity ASC");
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
                        <button class="btn btn-sm btn-info" onclick="openUpdateModal(<?php echo $item['id']; ?>)">
                            Update Stock
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="action-buttons" style="margin-top: 20px;">
            <button class="btn btn-success" onclick="generateLowStockReport()">
                <span>📄</span> Generate Report
            </button>
            <button class="btn btn-primary" onclick="notifyAdmin()">
                <span>📧</span> Notify Admin
            </button>
        </div>
    </div>
    

    <div id="tab-reorder" class="tab-content <?php echo $active_tab === 'reorder' ? 'active' : ''; ?>">
        <div class="toolbar">
            <h3>Reorder List</h3>
            <button class="btn btn-success" onclick="generateReorderReport()">
                <span>📄</span> Generate Reorder Report
            </button>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Current Stock</th>
                    <th>Suggested Quantity</th>
                    <th>Unit</th>
                    <th>Priority</th>
                    <th>Added Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->query("
                    SELECT r.*, i.item_name, i.quantity, i.unit 
                    FROM reorder_list r 
                    JOIN inventory i ON r.inventory_id = i.id 
                    ORDER BY r.priority DESC, r.created_at DESC
                ");
                if ($stmt->num_rows === 0):
                ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                        No items in reorder list. Add items from low stock alerts.
                    </td>
                </tr>
                <?php else: ?>
                <?php while ($item = $stmt->fetch_assoc()): ?>
                <tr>
                    <td><?php echo safe_html($item['item_name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo $item['suggested_quantity']; ?></td>
                    <td><?php echo safe_html($item['unit']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $item['priority']; ?>">
                            <?php echo ucfirst($item['priority']); ?>
                        </span>
                    </td>
                    <td><?php echo format_datetime($item['created_at']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $item['status']; ?>">
                            <?php echo ucfirst($item['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($item['status'] === 'pending'): ?>
                        <button class="btn btn-sm btn-success" onclick="markAsOrdered(<?php echo $item['id']; ?>)">
                            Mark Ordered
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="removeFromReorder(<?php echo $item['id']; ?>)">
                            Remove
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
   
    <div id="tab-wastage" class="tab-content <?php echo $active_tab === 'wastage' ? 'active' : ''; ?>">
        <div class="toolbar">
            <h3>Wastage & Expired Items Log</h3>
            <button class="btn btn-primary" onclick="openRecordWastageModal()">
                <span>➕</span> Record Wastage
            </button>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Reason</th>
                    <th>Recorded By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->query("
                    SELECT w.*, i.item_name, i.unit, u.username 
                    FROM wastage_log w 
                    JOIN inventory i ON w.inventory_id = i.id 
                    JOIN users u ON w.recorded_by = u.id 
                    ORDER BY w.recorded_at DESC 
                    LIMIT 50
                ");
                if ($stmt->num_rows === 0):
                ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                        No wastage records yet.
                    </td>
                </tr>
                <?php else: ?>
                <?php while ($item = $stmt->fetch_assoc()): ?>
                <tr>
                    <td><?php echo safe_html($item['item_name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo safe_html($item['unit']); ?></td>
                    <td><?php echo safe_html($item['reason']); ?></td>
                    <td><?php echo safe_html($item['username']); ?></td>
                    <td><?php echo format_datetime($item['recorded_at']); ?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Reports Tab -->
    <div id="tab-reports" class="tab-content <?php echo $active_tab === 'reports' ? 'active' : ''; ?>">
        <div class="reports-section">
            <h3>Financial Overview</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3 id="totalInventoryCost">₱0.00</h3>
                        <p>Total Inventory Cost</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-info">
                        <h3 id="totalSalesRevenue">₱0.00</h3>
                        <p>Total Sales Revenue</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" id="profitLossIcon">📊</div>
                    <div class="stat-info">
                        <h3 id="profitLossAmount">₱0.00</h3>
                        <p id="profitLossLabel">Profit/Loss</p>
                    </div>
                </div>
            </div>

            <div class="report-actions">
                <button class="btn btn-primary" onclick="refreshReports()">
                    <span>🔄</span> Refresh Data
                </button>
            </div>
        </div>
    </div>
</main>

 Add Inventory Modal
<div id="addInventoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Inventory Item</h2>
            <div class="modal-header-actions">
                <button class="scroll-btn" onclick="scrollModal('addInventoryModal', 'up')" title="Scroll Up">↑</button>
                <button class="scroll-btn" onclick="scrollModal('addInventoryModal', 'down')" title="Scroll Down">↓</button>
                <span class="close" onclick="closeModal('addInventoryModal')">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="addInventoryModalBody" style="max-height: 400px; overflow-y: auto;">
            <form id="addInventoryForm">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" name="item_name" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" required>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" step="0.01" required>
                <small class="text-muted">Enter quantity in the selected unit</small>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <select id="add_unit_select" name="unit_select" required>
                    <option value="kg">kg</option>
                    <option value="g">g</option>
                    <option value="l">L</option>
                    <option value="ml">ml</option>
                    <option value="oz">oz</option>
                    <option value="pcs">pcs</option>
                    <option value="other">Other</option>
                </select>
                <input type="text" id="add_unit_other" placeholder="Enter custom unit" style="display:none;margin-top:8px;" />
                <input type="hidden" name="unit" id="add_unit_hidden">
            </div>
            <div class="form-group">
                <label>Reorder Level</label>
                <input type="number" name="reorder_level" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Cost per Unit (₱)</label>
                <input type="number" name="cost_per_unit" step="0.01" min="0" placeholder="0.00">
            </div>
            <button type="submit" class="btn btn-primary">Add Item</button>
            </form>
        </div>
    </div>
</div>

 Edit Inventory Modal
<div id="editInventoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Inventory Item</h2>
            <div class="modal-header-actions">
                <button class="scroll-btn" onclick="scrollModal('editInventoryModal', 'up')" title="Scroll Up">↑</button>
                <button class="scroll-btn" onclick="scrollModal('editInventoryModal', 'down')" title="Scroll Down">↓</button>
                <span class="close" onclick="closeModal('editInventoryModal')">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="editInventoryModalBody" style="max-height: 400px; overflow-y: auto;">
            <form id="editInventoryForm">
            <input type="hidden" name="inventory_id" id="edit_inventory_id">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" name="item_name" id="edit_item_name" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" id="edit_category" required>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <select id="edit_unit_select" name="edit_unit_select" required>
                    <option value="kg">kg</option>
                    <option value="g">g</option>
                    <option value="l">L</option>
                    <option value="ml">ml</option>
                    <option value="oz">oz</option>
                    <option value="pcs">pcs</option>
                    <option value="other">Other</option>
                </select>
                <input type="text" id="edit_unit_other" placeholder="Enter custom unit" style="display:none;margin-top:8px;" />
                <input type="hidden" name="unit" id="edit_unit_hidden">
            </div>
            <div class="form-group">
                <label>Reorder Level</label>
                <input type="number" name="reorder_level" id="edit_reorder_level" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" id="edit_quantity" step="0.01" required>
                <small class="text-muted">Enter quantity in the selected unit</small>
            </div>
            <div class="form-group">
                <label>Cost per Unit (₱)</label>
                <input type="number" name="cost_per_unit" id="edit_cost_per_unit" step="0.01" min="0" placeholder="0.00">
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</div>

 Update Stock Modal
<div id="updateStockModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update Stock</h2>
            <div class="modal-header-actions">
                <button class="scroll-btn" onclick="scrollModal('updateStockModal', 'up')" title="Scroll Up">↑</button>
                <button class="scroll-btn" onclick="scrollModal('updateStockModal', 'down')" title="Scroll Down">↓</button>
                <span class="close" onclick="closeModal('updateStockModal')">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="updateStockModalBody" style="max-height: 400px; overflow-y: auto;">
            <form id="updateStockForm">
            <input type="hidden" name="inventory_id" id="update_inventory_id">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" id="update_item_name" readonly>
            </div>
            <div class="form-group">
                <label>Action</label>
                <select name="action" id="update_action" required>
                    <option value="add">Add Stock (Delivery)</option>
                    <option value="set">Set Stock Level</option>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" step="0.01" required>
                <small class="text-muted">Enter quantity in the selected unit</small>
            </div>
            <div class="form-group">
                <label>Unit (for this update)</label>
                <select id="update_unit_select" name="update_unit_select">
                    <option value="kg">kg</option>
                    <option value="g">grams</option>
                    <option value="l">liters</option>
                    <option value="ml">ml</option>
                    <option value="oz">oz</option>
                    <option value="pcs">pcs</option>
                    <option value="other">Other</option>
                </select>
                <input type="text" id="update_unit_other" placeholder="Enter custom unit" style="display:none;margin-top:8px;" />
                <input type="hidden" name="input_unit" id="update_unit_hidden">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update Stock</button>
            </form>
        </div>
    </div>
</div>

 Record Wastage Modal
<div id="recordWastageModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Record Wastage/Expired Items</h2>
            <div class="modal-header-actions">
                <button class="scroll-btn" onclick="scrollModal('recordWastageModal', 'up')" title="Scroll Up">↑</button>
                <button class="scroll-btn" onclick="scrollModal('recordWastageModal', 'down')" title="Scroll Down">↓</button>
                <span class="close" onclick="closeModal('recordWastageModal')">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="recordWastageModalBody" style="max-height: 400px; overflow-y: auto;">
            <form id="recordWastageForm">
            <div class="form-group">
                <label>Select Item</label>
                <select name="inventory_id" id="wastage_inventory_select" required>
                    <option value="">-- Select Item --</option>
                    <?php
                    $stmt = $conn->query("SELECT id, item_name, quantity, unit FROM inventory ORDER BY item_name");
                    while ($item = $stmt->fetch_assoc()):
                    ?>
                    <option value="<?php echo $item['id']; ?>" data-unit="<?php echo $item['unit']; ?>" data-quantity="<?php echo $item['quantity']; ?>">
                        <?php echo safe_html($item['item_name']); ?> (<?php echo $item['quantity']; ?> <?php echo safe_html($item['unit']); ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" step="0.01" required>
                <small class="text-muted">Enter quantity in the unit you select below</small>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <select id="wastage_unit_select" name="unit">
                    <option value="g">g</option>
                    <option value="ml">ml</option>
                    <option value="oz">oz</option>
                    <option value="pcs">pcs</option>
                    <option value="other">Other</option>
                </select>
                <input type="text" id="wastage_unit_other" placeholder="Enter custom unit" style="display:none;margin-top:8px;" />
            </div>
            </div>
            <div class="form-group">
                <label>Reason</label>
                <select name="reason" required>
                    <option value="expired">Expired</option>
                    <option value="damaged">Damaged</option>
                    <option value="spoiled">Spoiled</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Record Wastage</button>
            </form>
        </div>
    </div>
</div>

<script>
const userRole = '<?php echo $_SESSION['role'] ?? ''; ?>';
console.log('User role detected:', userRole);

let currentInventoryId = null;

function switchTab(tab) {
    window.location.href = '?tab=' + tab;
}

function openAddInventoryModal() {
    document.getElementById('addInventoryModal').style.display = 'block';
}

function openEditModal(inventoryId) {
    fetch(`<?php echo SITE_URL; ?>/api/get_inventory_item.php?id=${inventoryId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_inventory_id').value = data.item.id;
                document.getElementById('edit_item_name').value = data.item.item_name;
                document.getElementById('edit_category').value = data.item.category;
                // Set unit select and hidden
                const unit = data.item.unit || '';
                const select = document.getElementById('edit_unit_select');
                const other = document.getElementById('edit_unit_other');
                const hidden = document.getElementById('edit_unit_hidden');
                let matched = false;
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value.toLowerCase() === unit.toLowerCase()) {
                        select.selectedIndex = i;
                        matched = true;
                        other.style.display = 'none';
                        break;
                    }
                }
                if (!matched) {
                    // select Other and show custom value
                    for (let i = 0; i < select.options.length; i++) {
                        if (select.options[i].value === 'other') select.selectedIndex = i;
                    }
                    other.style.display = '';
                    other.value = unit;
                }
                hidden.value = unit;
                document.getElementById('edit_reorder_level').value = data.item.reorder_level;
                document.getElementById('edit_quantity').value = data.item.quantity;
                document.getElementById('edit_cost_per_unit').value = data.item.cost_per_unit;
                document.getElementById('editInventoryModal').style.display = 'block';
            }
        });
}

function openUpdateModal(inventoryId) {
    fetch(`<?php echo SITE_URL; ?>/api/get_inventory_item.php?id=${inventoryId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('update_inventory_id').value = data.item.id;
                document.getElementById('update_item_name').value = data.item.item_name;
                // Set default update unit select to inventory unit
                const invUnit = data.item.unit || '';
                const select = document.getElementById('update_unit_select');
                const other = document.getElementById('update_unit_other');
                const hidden = document.getElementById('update_unit_hidden');
                let matched = false;
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value.toLowerCase() === invUnit.toLowerCase()) {
                        select.selectedIndex = i;
                        matched = true;
                        other.style.display = 'none';
                        break;
                    }
                }
                if (!matched) {
                    for (let i = 0; i < select.options.length; i++) {
                        if (select.options[i].value === 'other') select.selectedIndex = i;
                    }
                    other.style.display = '';
                    other.value = invUnit;
                }
                hidden.value = invUnit;
                document.getElementById('updateStockModal').style.display = 'block';
            }
        });
}

function openWastageModal(inventoryId) {
    // Open the modal first
    document.getElementById('recordWastageModal').style.display = 'block';
    
    // Set the selected inventory item in the dropdown
    const selectElement = document.querySelector('#recordWastageModal [name="inventory_id"]');
    if (selectElement) {
        selectElement.value = inventoryId;
        // Set default unit select based on option data-unit
        const option = selectElement.options[selectElement.selectedIndex];
        const unit = option ? option.getAttribute('data-unit') : null;
        const wastageUnit = document.getElementById('wastage_unit_select');
        const wastageOther = document.getElementById('wastage_unit_other');
        if (unit && wastageUnit) {
            let matched = false;
            for (let i = 0; i < wastageUnit.options.length; i++) {
                if (wastageUnit.options[i].value.toLowerCase() === unit.toLowerCase()) {
                    wastageUnit.selectedIndex = i;
                    wastageOther.style.display = 'none';
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                for (let i = 0; i < wastageUnit.options.length; i++) {
                    if (wastageUnit.options[i].value === 'other') wastageUnit.selectedIndex = i;
                }
                wastageOther.style.display = '';
                wastageOther.value = unit;
            }
        }
    }
}

function openRecordWastageModal() {
    document.getElementById('recordWastageModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function scrollModal(modalId, direction) {
    const modalBody = document.getElementById(modalId + 'Body');
    const scrollAmount = 100; // pixels to scroll

    if (direction === 'up') {
        modalBody.scrollTop -= scrollAmount;
    } else if (direction === 'down') {
        modalBody.scrollTop += scrollAmount;
    }
}

// Add Inventory Form
document.getElementById('addInventoryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // Ensure unit hidden field is set based on select or other input
    const unitSelect = document.getElementById('add_unit_select');
    const unitOther = document.getElementById('add_unit_other');
    const unitHidden = document.getElementById('add_unit_hidden');
    if (unitSelect.value === 'other') {
        unitHidden.value = unitOther.value.trim();
    } else {
        unitHidden.value = unitSelect.value;
    }

    const formData = new FormData(this);

    fetch('<?php echo SITE_URL; ?>/api/add_inventory.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Inventory item added successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Edit Inventory Form
document.getElementById('editInventoryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // Set hidden unit based on select or other
    const unitSelect = document.getElementById('edit_unit_select');
    const unitOther = document.getElementById('edit_unit_other');
    const unitHidden = document.getElementById('edit_unit_hidden');
    if (unitSelect.value === 'other') {
        unitHidden.value = unitOther.value.trim();
    } else {
        unitHidden.value = unitSelect.value;
    }

    const formData = new FormData(this);

    fetch('<?php echo SITE_URL; ?>/api/edit_inventory.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Inventory item updated successfully');
            // Refresh reports if on reports tab
            if (window.location.search.includes('tab=reports')) {
                refreshReports();
            }
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Update Stock Form
document.getElementById('updateStockForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // Ensure input unit is set from select/other
    const unitSelect = document.getElementById('update_unit_select');
    const unitOther = document.getElementById('update_unit_other');
    const unitHidden = document.getElementById('update_unit_hidden');
    if (unitSelect.value === 'other') {
        unitHidden.value = unitOther.value.trim();
    } else {
        unitHidden.value = unitSelect.value;
    }

    const formData = new FormData(this);

    fetch('<?php echo SITE_URL; ?>/api/update_inventory.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Stock updated successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Record Wastage Form
document.getElementById('recordWastageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // If "Other" unit selected, copy to unit field
    const wastageUnitSelect = document.getElementById('wastage_unit_select');
    const wastageUnitOther = document.getElementById('wastage_unit_other');
    if (wastageUnitSelect && wastageUnitSelect.value === 'other') {
        // ensure the input named 'unit' is set to the custom value
        const existing = this.querySelector('[name="unit"]');
        if (existing) existing.value = wastageUnitOther.value.trim();
    }

    const formData = new FormData(this);
    
    fetch('<?php echo SITE_URL; ?>/api/record_wastage.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Wastage recorded successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

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

function markAsOrdered(reorderId) {
    if (!confirm('Mark this item as ordered?')) return;
    
    fetch('<?php echo SITE_URL; ?>/api/update_reorder_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reorder_id: reorderId, status: 'ordered' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Item marked as ordered');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function removeFromReorder(reorderId) {
    if (!confirm('Remove this item from reorder list?')) return;
    
    fetch('<?php echo SITE_URL; ?>/api/remove_from_reorder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reorder_id: reorderId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Item removed from reorder list');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function generateLowStockReport() {
    window.open('<?php echo SITE_URL; ?>/reports/low_stock_report.php', '_blank');
}

function generateReorderReport() {
    window.open('<?php echo SITE_URL; ?>/reports/reorder_report.php', '_blank');
}

function deleteInventoryItem(inventoryId) {
    currentInventoryId = inventoryId;
    console.log('Delete inventory called for ID:', inventoryId, 'User role:', userRole);

    // Only require password confirmation for staff
    if (userRole === 'staff') {
        console.log('Showing password modal for staff');
        document.getElementById('deletePasswordModal').style.display = 'block';
        document.getElementById('deleteConfirmPassword').focus();
    } else {
        console.log('Showing confirmation dialog for admin');
        // For admin, proceed directly with confirmation
        if (confirm('Are you sure you want to delete this inventory item? This action cannot be undone.')) {
            proceedWithDeletion(inventoryId);
        }
    }
}

function closeDeletePasswordModal() {
    document.getElementById('deletePasswordModal').style.display = 'none';
    document.getElementById('deletePasswordForm').reset();
    currentInventoryId = null;
}

// Handle password form submission
document.getElementById('deletePasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const password = document.getElementById('deleteConfirmPassword').value;

    if (!password) {
        alert('Please enter your password.');
        return;
    }

    proceedWithDeletion(currentInventoryId, password);
});

function proceedWithDeletion(inventoryId, password = null) {
    console.log('[v0] Deleting inventory ID:', inventoryId, 'Password provided:', password ? 'yes' : 'no');

    const requestData = { inventory_id: inventoryId };
    if (password !== null) {
        requestData.password = password;
    }

    fetch('<?php echo SITE_URL; ?>/api/delete_inventory.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Inventory item deleted successfully');
            closeDeletePasswordModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('[v0] Error deleting inventory:', error);
        alert('Error deleting inventory item. Check console for details.');
    });
}

function notifyAdmin() {
    fetch('<?php echo SITE_URL; ?>/api/notify_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'low_stock' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Admin notified successfully');
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// Search functionality
document.getElementById('searchInventory')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#inventoryTableBody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
    // Close dropdowns when clicking outside
    if (!event.target.matches('.dropdown-toggle')) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
}

// Unit select helpers: show/hide 'Other' inputs and keep hidden unit fields updated
function setupUnitHelpers() {
    // Add Inventory
    const addSelect = document.getElementById('add_unit_select');
    const addOther = document.getElementById('add_unit_other');
    const addHidden = document.getElementById('add_unit_hidden');
    if (addSelect) {
        addSelect.addEventListener('change', function() {
            if (this.value === 'other') {
                addOther.style.display = '';
                addOther.focus();
            } else {
                addOther.style.display = 'none';
                addOther.value = '';
            }
            addHidden.value = (this.value === 'other') ? addOther.value.trim() : this.value;
        });
        addOther.addEventListener('input', function() {
            addHidden.value = this.value.trim();
        });
    }

    // Edit Inventory
    const editSelect = document.getElementById('edit_unit_select');
    const editOther = document.getElementById('edit_unit_other');
    const editHidden = document.getElementById('edit_unit_hidden');
    if (editSelect) {
        editSelect.addEventListener('change', function() {
            if (this.value === 'other') {
                editOther.style.display = '';
                editOther.focus();
            } else {
                editOther.style.display = 'none';
                editOther.value = '';
            }
            editHidden.value = (this.value === 'other') ? editOther.value.trim() : this.value;
        });
        editOther.addEventListener('input', function() {
            editHidden.value = this.value.trim();
        });
    }

    // Update Stock
    const updateSelect = document.getElementById('update_unit_select');
    const updateOther = document.getElementById('update_unit_other');
    const updateHidden = document.getElementById('update_unit_hidden');
    if (updateSelect) {
        updateSelect.addEventListener('change', function() {
            if (this.value === 'other') {
                updateOther.style.display = '';
                updateOther.focus();
            } else {
                updateOther.style.display = 'none';
                updateOther.value = '';
            }
            updateHidden.value = (this.value === 'other') ? updateOther.value.trim() : this.value;
        });
        updateOther.addEventListener('input', function() {
            updateHidden.value = this.value.trim();
        });
    }

    // Wastage
    const wastageSelect = document.getElementById('wastage_unit_select');
    const wastageOther = document.getElementById('wastage_unit_other');
    if (wastageSelect) {
        wastageSelect.addEventListener('change', function() {
            if (this.value === 'other') {
                wastageOther.style.display = '';
                wastageOther.focus();
            } else {
                wastageOther.style.display = 'none';
                wastageOther.value = '';
            }
        });
    }
}

// Initialize unit helpers on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    setupUnitHelpers();
});

function toggleDropdown(inventoryId) {
    const dropdown = document.getElementById('dropdown-' + inventoryId);
    const isVisible = dropdown.classList.contains('show');

    // Close all dropdowns first
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('show');
    });

    // Toggle the clicked dropdown
    if (!isVisible) {
        dropdown.classList.add('show');
    }
}

function refreshReports() {
    // Load inventory cost
    fetch('<?php echo SITE_URL; ?>/api/get_inventory_cost.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalInventoryCost').textContent = formatCurrency(data.total_cost);
            }
        });

    // Load profit/loss data
    fetch('<?php echo SITE_URL; ?>/api/get_profit_loss.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalSalesRevenue').textContent = formatCurrency(data.total_sales_revenue);
                document.getElementById('profitLossAmount').textContent = formatCurrency(Math.abs(data.profit_loss));

                const profitLossLabel = document.getElementById('profitLossLabel');
                const profitLossIcon = document.getElementById('profitLossIcon');
                const profitLossAmount = document.getElementById('profitLossAmount');

                if (data.is_profit) {
                    profitLossLabel.textContent = 'Profit';
                    profitLossIcon.textContent = '📈';
                    profitLossAmount.style.color = '#28a745';
                } else {
                    profitLossLabel.textContent = 'Loss';
                    profitLossIcon.textContent = '📉';
                    profitLossAmount.style.color = '#dc3545';
                }
            }
        });
}

function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2);
}

// Load reports data on page load if reports tab is active
<?php if ($active_tab === 'reports'): ?>
document.addEventListener('DOMContentLoaded', function() {
    refreshReports();
});
<?php endif; ?>
</script>

<!-- Password Confirmation Modal for Inventory Deletion -->
<div id="deletePasswordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirm Deletion</h2>
            <span class="close" onclick="closeDeletePasswordModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Please enter your password to confirm inventory item deletion:</p>
            <form id="deletePasswordForm">
                <div class="form-group">
                    <label for="deleteConfirmPassword">Password:</label>
                    <input type="password" id="deleteConfirmPassword" name="password" required>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeDeletePasswordModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Confirm Deletion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Password Modal Styles */
#deletePasswordModal .modal-content {
    max-width: 400px;
}

#deletePasswordModal .modal-body {
    padding: 20px;
}

#deletePasswordModal .form-group {
    margin-bottom: 20px;
}

#deletePasswordModal label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

#deletePasswordModal input[type="password"] {
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

/* Tablet adjustments: larger touch targets and spacing for modal and controls */
@media (min-width: 768px) and (max-width: 1024px) {
    #deletePasswordModal .modal-content {
        max-width: 520px;
    }

    #deletePasswordModal input[type="password"] {
        padding: 12px;
        font-size: 16px;
    }

    .modal-actions .btn-secondary,
    .modal-actions .btn-primary,
    .modal-actions button {
        padding: 12px 22px;
        font-size: 15px;
        border-radius: 6px;
    }

    /* Global form controls on inventory page */
    .form-control,
    input[type="text"],
    input[type="number"],
    select {
        padding: 12px;
        font-size: 15px;
    }

    .btn-primary,
    .btn-secondary {
        padding: 14px 18px;
        font-size: 15px;
        border-radius: 6px;
    }
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

<?php include 'includes/footer.php'; ?>
