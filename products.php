<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

// Check if user has access (admin only)
if (!has_role('admin')) {
    redirect('dashboard.php');
}

$page_title = 'Products Management';

// Check if coming from menu suggestions
$from_suggestion = isset($_GET['from_suggestion']) && $_GET['from_suggestion'] == '1';
$suggestion_data = [];
if ($from_suggestion) {
    $suggestion_data = [
        'name' => $_GET['name'] ?? '',
        'description' => $_GET['description'] ?? '',
        'price' => $_GET['price'] ?? '',
        'category' => $_GET['category'] ?? ''
    ];
}

$products_query = null;
// Show all products to admins (including disabled), otherwise only available products
if (has_role('admin')) {
    $products_query = $conn->query("SELECT * FROM products ORDER BY category, name");
} else {
    $products_query = $conn->query("SELECT * FROM products WHERE status = 'available' ORDER BY category, name");
}
$products_by_category = [];
while ($row = $products_query->fetch_assoc()) {
    $products_by_category[$row['category']][] = $row;
}

// Get categories for filter
$categories_query = $conn->query("SELECT DISTINCT category FROM products ORDER BY category");
$categories = [];
while ($row = $categories_query->fetch_assoc()) {
    $categories[] = $row['category'];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Products Management</h1>
        <p>Manage menu items and products</p>
    </div>
    
    <div class="actions-bar">
        <button class="btn btn-primary" onclick="openAddModal()">+ Add New Product</button>
        <?php if ($from_suggestion): ?>
            <div class="suggestion-notice">
                <p>💡 Creating product from menu suggestion: <strong><?php echo safe_html($suggestion_data['name']); ?></strong></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="products-container">
        <?php if (empty($products_by_category)): ?>
            <div class="empty-state">
                <h3>No Products Yet</h3>
                <p>Add your first product to get started with the POS system.</p>
                <button class="btn btn-primary" onclick="openAddModal()">Add Product</button>
            </div>
        <?php else: ?>
            <?php foreach ($products_by_category as $category => $products): ?>
                <div class="category-section">
                    <h2 class="category-header"><?php echo safe_html($category); ?></h2>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-item">
                                <!-- Images removed from product list to simplify UI -->
                                <div class="product-details">
                                    <h3><?php echo safe_html($product['name']); ?></h3>
                                    <p class="product-category"><?php echo safe_html($product['category']); ?></p>
                                    <p class="product-price"><?php echo format_currency($product['price']); ?></p>
                                    <span class="badge badge-<?php echo $product['status']; ?>">
                                        <?php echo ucfirst($product['status']); ?>
                                    </span>
                                </div>
                                <div class="product-actions">
                                    <button class="btn-icon" onclick="manageIngredients(<?php echo $product['id']; ?>)" title="Manage Ingredients">🧪</button>
                                    <button class="btn-icon" onclick="editProduct(<?php echo $product['id']; ?>)" title="Edit">✏️</button>
                                    <button class="btn-icon" onclick="toggleStatus(<?php echo $product['id']; ?>, '<?php echo $product['status']; ?>')"
                                            title="Toggle Status">
                                        <?php echo $product['status'] === 'available' ? '🔴' : '🟢'; ?>
                                    </button>
                                    <button class="btn-icon" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="Delete">🗑️</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Add/Edit Product Modal -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Product</h3>
            <div class="modal-header-actions">
                <button class="scroll-btn" onclick="scrollModal('up')" title="Scroll Up">↑</button>
                <button class="scroll-btn" onclick="scrollModal('down')" title="Scroll Down">↓</button>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="modalBody" style="max-height: 400px; overflow-y: auto;">
            <form id="productForm">
                <input type="hidden" id="productId" name="id">
                <input type="hidden" id="existingImage" name="existing_image">
                
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" id="productName" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Category *</label>
                    <input list="categoriesList" id="productCategory" name="category" class="form-control" required placeholder="Select or type a category">
                    <datalist id="categoriesList">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo safe_html($cat); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Price *</label>
                        <input type="number" id="productPrice" name="price" class="form-control" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Cost (Optional)</label>
                        <input type="number" id="productCost" name="cost" class="form-control" 
                               step="0.01" min="0">
                    </div>
                </div>
                
                <!-- Description removed per request -->
                
                <div class="form-group">
                    <label>Status</label>
                    <select id="productStatus" name="status" class="form-control">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>
                
                <!-- Product image upload removed per request -->
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveProduct()">Save Product</button>
        </div>
    </div>
</div>

<!-- Ingredients Modal -->
<div id="ingredientsModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="ingredientsModalTitle">Manage Ingredients</h3>
            <div class="modal-header-actions">
                <button class="scroll-btn" onclick="scrollIngredientsModal('up')" title="Scroll Up">↑</button>
                <button class="scroll-btn" onclick="scrollIngredientsModal('down')" title="Scroll Down">↓</button>
                <span class="close" onclick="closeIngredientsModal()">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="ingredientsModalBody" style="max-height: 400px; overflow-y: auto;">
            <input type="hidden" id="ingredientsProductId">
            
            <div class="ingredients-section">
                <h4>Current Ingredients</h4>
                <div id="currentIngredients" class="ingredients-list">
                    <p class="text-muted">Loading...</p>
                </div>
            </div>
            
            <div class="add-ingredient-section">
                <h4>Add Ingredient</h4>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Inventory Item</label>
                        <select id="inventorySelect" class="form-control">
                            <option value="">Select ingredient...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity Used</label>
                        <input type="number" id="quantityUsed" class="form-control"
                               step="0.01" min="0.01" placeholder="e.g., 100 (in grams/ml)">
                        <small class="text-muted">Enter quantity in base units (grams for weight, ml for volume, pieces for count)</small>
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <select id="ingredientUnit" class="form-control">
                            <option value="g">g</option>
                            <option value="ml">ml</option>
                            <option value="oz">oz</option>
                            <option value="pcs">pcs</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" onclick="addIngredient()">Add</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeIngredientsModal()">Close</button>
        </div>
    </div>
</div>

<style>
.actions-bar {
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.suggestion-notice {
    background: #e8f5e8;
    border: 1px solid #4caf50;
    border-radius: 8px;
    padding: 12px 16px;
    margin: 0;
}

.suggestion-notice p {
    margin: 0;
    color: #2e7d32;
    font-weight: 500;
}

.products-container {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.category-section {
    margin-bottom: 30px;
}

.category-header {
    font-size: 24px;
    font-weight: bold;
    color: #2c5f2d;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #2c5f2d;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.product-item {
    background: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Product images intentionally removed from list view */

.product-details h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.product-category {
    color: #666;
    font-size: 14px;
    margin: 5px 0;
}

.product-price {
    font-size: 20px;
    font-weight: bold;
    color: #2c5f2d;
    margin: 5px 0;
}

.product-actions {
    display: flex;
    gap: 10px;
    margin-top: auto;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}
/* Tablet styles: improve touch targets and spacing without altering desktop layout */
@media (min-width: 768px) and (max-width: 1024px) {
    .products-container {
        gap: 28px;
    }

    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 22px;
    }

    .product-item {
        padding: 20px;
        border-radius: 10px;
    }

    .product-details h3 {
        font-size: 20px;
    }

    .product-price {
        font-size: 22px;
    }

    .product-actions {
        gap: 12px;
    }

    .product-actions .btn,
    .product-actions button {
        padding: 12px 16px;
        font-size: 14px;
        border-radius: 8px;
    }

    .badge {
        padding: 6px 14px;
        font-size: 13px;
    }
}

/* Mobile fallback: keep readable but compact */
@media (max-width: 767px) {
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 12px;
    }

    .product-item {
        padding: 12px;
    }
}

.badge-available {
    background: #d4edda;
    color: #155724;
}

.badge-unavailable {
    background: #f8d7da;
    color: #721c24;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 8px;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #333;
}

.empty-state p {
    color: #666;
    margin-bottom: 20px;
}

.modal-large {
    max-width: 700px;
}

.ingredients-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #ddd;
}

.ingredients-section h4,
.add-ingredient-section h4 {
    margin-bottom: 15px;
    color: #333;
    font-size: 16px;
}

.ingredients-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ingredient-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
}

.ingredient-info {
    flex: 1;
}

.ingredient-name {
    font-weight: 500;
    color: #333;
    margin-bottom: 4px;
}

.ingredient-quantity {
    font-size: 14px;
    color: #666;
}

.ingredient-actions {
    display: flex;
    gap: 5px;
}

.add-ingredient-section {
    margin-top: 20px;
}

.text-muted {
    color: #999;
    font-style: italic;
}

/* Image preview removed from modal */

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
</style>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Product';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    // Pre-fill form if coming from suggestion
    <?php if ($from_suggestion): ?>
        document.getElementById('productName').value = '<?php echo addslashes($suggestion_data['name']); ?>';
        document.getElementById('productPrice').value = '<?php echo $suggestion_data['price']; ?>';
        document.getElementById('productCategory').value = '<?php echo addslashes($suggestion_data['category']); ?>';
    <?php endif; ?>

    document.getElementById('productModal').style.display = 'block';
}

// image preview removed (image upload disabled)

function editProduct(id) {
    fetch(`<?php echo SITE_URL; ?>/api/get_product.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const product = data.product;
                document.getElementById('modalTitle').textContent = 'Edit Product';
                document.getElementById('productId').value = product.id;
                document.getElementById('productName').value = product.name;
                document.getElementById('productCategory').value = product.category;
                document.getElementById('productPrice').value = product.price;
                document.getElementById('productCost').value = product.cost || '';
                document.getElementById('productStatus').value = product.status;
                document.getElementById('existingImage').value = product.image || '';
                
                document.getElementById('productModal').style.display = 'block';
            }
        });
}

function saveProduct() {
    const formData = new FormData(document.getElementById('productForm'));
    
    fetch('<?php echo SITE_URL; ?>/api/save_product.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Product saved successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('[v0] Error saving product:', error);
        alert('Error saving product. Please try again.');
    });
}

function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus === 'available' ? 'unavailable' : 'available';
    
    fetch('<?php echo SITE_URL; ?>/api/toggle_product_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status: newStatus })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    
    fetch('<?php echo SITE_URL; ?>/api/delete_product.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Product deleted successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function closeModal() {
    document.getElementById('productModal').style.display = 'none';
}

function scrollModal(direction) {
    const modalBody = document.getElementById('productModalBody');
    const scrollAmount = 100; // pixels to scroll

    if (direction === 'up') {
        modalBody.scrollTop -= scrollAmount;
    } else if (direction === 'down') {
        modalBody.scrollTop += scrollAmount;
    }
}

function manageIngredients(productId) {
    document.getElementById('ingredientsProductId').value = productId;
    document.getElementById('ingredientsModal').style.display = 'block';
    
    // Load product name
    fetch(`<?php echo SITE_URL; ?>/api/get_product.php?id=${productId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('ingredientsModalTitle').textContent = 
                    `Manage Ingredients - ${data.product.name}`;
            }
        });
    
    // Load current ingredients
    loadIngredients(productId);
    
    // Load inventory items for dropdown
    loadInventoryItems();
}

function loadIngredients(productId) {
    fetch(`<?php echo SITE_URL; ?>/api/get_product_ingredients.php?product_id=${productId}`)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('currentIngredients');
            
            if (data.success && data.ingredients.length > 0) {
                container.innerHTML = data.ingredients.map(ing => `
                    <div class="ingredient-item">
                        <div class="ingredient-info">
                            <div class="ingredient-name">${ing.item_name}</div>
                            <div class="ingredient-quantity">${ing.quantity_used} ${ing.unit}</div>
                        </div>
                        <div class="ingredient-actions">
                            <button class="btn-icon btn-sm" onclick="removeIngredient(${ing.id})" title="Remove">🗑️</button>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted">No ingredients added yet. Add ingredients below.</p>';
            }
        });
}

function loadInventoryItems() {
    fetch('<?php echo SITE_URL; ?>/api/get_inventory_items.php')
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById('inventorySelect');
            select.innerHTML = '<option value="">Select ingredient...</option>';
            
            if (data.success) {
                data.items.forEach(item => {
                    const altDisplay = formatInventoryDisplay(item.quantity, item.unit);
                    select.innerHTML += `<option value="${item.id}" data-unit="${item.unit}" data-quantity="${item.quantity}">
                        ${item.item_name} (${altDisplay} available)
                    </option>`;
                });
            }
        });
}

// When inventory item is selected, set sensible default unit for input
document.addEventListener('change', function (e) {
    if (e.target && e.target.id === 'inventorySelect') {
        const opt = e.target.options[e.target.selectedIndex];
        const invUnit = opt ? opt.getAttribute('data-unit') : null;
        const unitSelect = document.getElementById('ingredientUnit');
        if (!invUnit) return;

        const weightUnits = ['g','kg','mg'];
        const volumeUnits = ['ml','l','cl'];
        const inv = invUnit.toLowerCase();

        if (weightUnits.includes(inv)) {
            unitSelect.value = 'g';
        } else if (volumeUnits.includes(inv)) {
            unitSelect.value = 'ml';
        } else if (inv === 'pcs' || inv === 'pieces') {
            unitSelect.value = 'pcs';
        } else {
            // default
            unitSelect.value = 'g';
        }
        // Also update displayed availability in selected input unit
        updateAvailableInInputUnit();
    }
});

function formatInventoryDisplay(qty, unit) {
    // Show base unit plus a friendly alternate (larger or smaller unit)
    const u = (unit || '').toLowerCase();
    const q = parseFloat(qty);

    if (!isFinite(q)) return `${qty} ${unit}`;

    if (u === 'l') {
        const ml = +(q * 1000).toFixed(2);
        return `${q} L (${ml} ml)`;
    }
    if (u === 'kg') {
        const g = +(q * 1000).toFixed(2);
        return `${q} kg (${g} g)`;
    }
    if (u === 'g') {
        const kg = +(q / 1000).toFixed(3);
        return `${q} g (${kg} kg)`;
    }
    if (u === 'ml') {
        const l = +(q / 1000).toFixed(3);
        return `${q} ml (${l} L)`;
    }
    if (u === 'pcs' || u === 'pieces') {
        return `${q} pcs`;
    }
    // Fallback
    return `${q} ${unit}`;
}

function convertClientUnit(quantity, fromUnit, toUnit) {
    const q = parseFloat(quantity);
    if (!isFinite(q)) return 0;
    const f = (fromUnit || '').toLowerCase();
    const t = (toUnit || '').toLowerCase();
    if (f === t) return +q;

    // Weight conversions
    const ozToG = 28.349523125;
    const flOzToMl = 29.5735295625;

    // Convert from -> base (g for weight, ml for volume)
    let base;
    if (['kg','g','mg','oz'].includes(f)) {
        switch (f) {
            case 'kg': base = q * 1000; break;
            case 'mg': base = q / 1000; break;
            case 'oz': base = q * ozToG; break;
            default: base = q; break; // g
        }
        // base is grams
        if (['kg','g','mg','oz'].includes(t)) {
            switch (t) {
                case 'kg': return +(base / 1000).toFixed(4);
                case 'mg': return +(base * 1000).toFixed(2);
                case 'oz': return +(base / ozToG).toFixed(4);
                default: return +base.toFixed(4); // g
            }
        }
    }

    if (['l','ml','cl','oz'].includes(f)) {
        switch (f) {
            case 'l': base = q * 1000; break;
            case 'cl': base = q * 10; break;
            case 'oz': base = q * flOzToMl; break;
            default: base = q; break; // ml
        }
        // base is ml
        if (['l','ml','cl','oz'].includes(t)) {
            switch (t) {
                case 'l': return +(base / 1000).toFixed(4);
                case 'cl': return +(base / 10).toFixed(4);
                case 'oz': return +(base / flOzToMl).toFixed(4);
                default: return +base.toFixed(4); // ml
            }
        }
    }

    // Count
    if (['pcs','pieces'].includes(f) && ['pcs','pieces'].includes(t)) return +q;

    // Fallback: no conversion
    return +q;
}

function updateAvailableInInputUnit() {
    const invSelect = document.getElementById('inventorySelect');
    const unitSelect = document.getElementById('ingredientUnit');
    const availSpanId = 'availableInInputUnit';
    let span = document.getElementById(availSpanId);
    if (!span) {
        const parent = document.getElementById('ingredientsModalBody');
        span = document.createElement('div');
        span.id = availSpanId;
        span.style.marginTop = '8px';
        span.className = 'text-muted';
        parent.appendChild(span);
    }

    const opt = invSelect.options[invSelect.selectedIndex];
    if (!opt || !opt.value) {
        span.textContent = '';
        return;
    }

    const invUnit = opt.getAttribute('data-unit');
    const invQty = parseFloat(opt.getAttribute('data-quantity')) || 0;
    const inputUnit = unitSelect.value;

    const converted = convertClientUnit(invQty, invUnit, inputUnit);
    span.textContent = `Available: ${converted} ${inputUnit} (${invQty} ${invUnit} in stock)`;
}

function addIngredient() {
    const productId = document.getElementById('ingredientsProductId').value;
    const inventoryId = document.getElementById('inventorySelect').value;
    const quantityUsed = document.getElementById('quantityUsed').value;
    const inputUnit = document.getElementById('ingredientUnit').value;
    
    if (!inventoryId || !quantityUsed) {
        alert('Please select an ingredient and enter quantity');
        return;
    }
    
    fetch('<?php echo SITE_URL; ?>/api/add_product_ingredient.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            product_id: productId,
            inventory_id: inventoryId,
            quantity_used: quantityUsed,
            input_unit: inputUnit
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('inventorySelect').value = '';
            document.getElementById('quantityUsed').value = '';
            loadIngredients(productId);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function removeIngredient(id) {
    if (!confirm('Remove this ingredient?')) return;
    
    const productId = document.getElementById('ingredientsProductId').value;
    
    fetch('<?php echo SITE_URL; ?>/api/remove_product_ingredient.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadIngredients(productId);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function closeIngredientsModal() {
    document.getElementById('ingredientsModal').style.display = 'none';
}

function scrollIngredientsModal(direction) {
    const modalBody = document.getElementById('ingredientsModalBody');
    const scrollAmount = 100; // pixels to scroll

    if (direction === 'up') {
        modalBody.scrollTop -= scrollAmount;
    } else if (direction === 'down') {
        modalBody.scrollTop += scrollAmount;
    }
}
</script>

<?php include 'includes/footer.php'; ?>
