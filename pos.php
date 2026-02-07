<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

// Check if user has access to POS
if (!has_role('admin') && !has_role('cashier')) {
    redirect('dashboard.php');
}

$page_title = 'Point of Sale';

// Get all available products
$products_query = $conn->query("SELECT * FROM products WHERE status = 'available' ORDER BY category, name");
$products = [];
while ($row = $products_query->fetch_assoc()) {
    $products[] = $row;
}

// Calculate stock left for each product based on ingredients and inventory
foreach ($products as &$product) {
    $product_id = (int) $product['id'];
    $stmt = $conn->prepare("SELECT pi.quantity_used, i.quantity AS inv_quantity, i.unit
                             FROM product_ingredients pi
                             JOIN inventory i ON pi.inventory_id = i.id
                             WHERE pi.product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $res = $stmt->get_result();

    // If no ingredients are defined, we cannot determine stock from inventory
    if ($res->num_rows === 0) {
        $product['stock_left'] = null; // N/A
        continue;
    }

    $possible_counts = [];
    while ($ing = $res->fetch_assoc()) {
        $qty_used = (float) $ing['quantity_used'];
        $inv_qty = (float) $ing['inv_quantity'];

        if ($qty_used <= 0) {
            // If recipe claims zero of an ingredient, ignore it (prevent divide by zero)
            continue;
        }

        // Number of products that can be made from this ingredient
        $count = floor($inv_qty / $qty_used);
        // If inventory has zero, count will be 0
        $possible_counts[] = max(0, (int) $count);
    }

    if (empty($possible_counts)) {
        $product['stock_left'] = 0;
    } else {
        $product['stock_left'] = min($possible_counts);
    }
}
unset($product);

// Get categories
$categories_query = $conn->query("SELECT DISTINCT category FROM products WHERE status = 'available' ORDER BY category");
$categories = [];
while ($row = $categories_query->fetch_assoc()) {
    $categories[] = $row['category'];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Point of Sale</h1>
        <p>Process customer orders</p>
    </div>
    
    <div class="pos-container">
        <!-- Products Section -->
        <div class="pos-products">
            <!-- Search Bar -->
            <div class="pos-search">
                <input type="text" id="searchProduct" placeholder="Search products..." class="form-control">
            </div>
            
            <!-- Category Buttons -->
            <div class="pos-categories">
                <button class="category-btn active" data-category="all">All Items</button>
                <?php foreach ($categories as $category): ?>
                    <button class="category-btn" data-category="<?php echo safe_html($category); ?>">
                        <?php echo safe_html($category); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Products Grid (Text-only tiles) -->
            <div class="products-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <p>No products available. Please add products first.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                            $stock = array_key_exists('stock_left', $product) ? $product['stock_left'] : null;
                            $stock_attr = is_null($stock) ? 'na' : (int)$stock;
                            $out_of_stock = ($stock === 0);
                        ?>
                        <div class="product-tile<?php echo $out_of_stock ? ' out-of-stock' : ''; ?>" 
                             data-category="<?php echo safe_html($product['category'] ?? ''); ?>" 
                             data-id="<?php echo $product['id']; ?>"
                             data-name="<?php echo safe_html($product['name']); ?>"
                             data-price="<?php echo $product['price']; ?>"
                             data-stock="<?php echo $stock_attr; ?>">
                            <div class="tile-name"><?php echo safe_html($product['name']); ?></div>
                            <div class="tile-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div class="tile-stock" style="font-size:11px;margin-top:6px;color:#555;">
                                <?php if (is_null($stock)): ?>
                                    Stock: N/A
                                <?php else: ?>
                                    Stock Left: <?php echo (int)$stock; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($out_of_stock): ?>
                                <div class="tile-out" style="position:absolute;inset:8px;display:flex;align-items:flex-start;justify-content:flex-end;pointer-events:none;">
                                    <span style="background:#dc3545;color:white;padding:4px 6px;border-radius:4px;font-size:11px;">Out of stock</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Order Bill Section -->
        <div class="pos-bill">
            <div class="bill-header">
                <h3>New Order Bill</h3>
                <span class="bill-date" id="billDate"></span>
            </div>

            <div class="bill-content">
                <!-- Left Side: Cart Items -->
                <div class="bill-left">
                    <div class="bill-items" id="cartItems">
                        <div class="empty-bill">
                            <p>No items added</p>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Financial Details -->
                <div class="bill-right">
                    <!-- Discount Section -->
                    <div class="discount-section">
                        <label>Apply Discount (Optional)</label>
                        <div class="discount-quick-buttons">
                            <button type="button" class="discount-quick-btn" data-discount="senior" onclick="applyQuickDiscount('senior', 'Senior Citizen (20%)')">
                                Senior (20%)
                            </button>
                            <button type="button" class="discount-quick-btn" data-discount="pwd" onclick="applyQuickDiscount('pwd', 'PWD (20%)')">
                                PWD (20%)
                            </button>
                            
                        </div>
                    </div>

                    <div id="discountOptions" style="display: none; margin-left: 0; padding: 12px; background: #e8f5e9; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid #4caf50;">
                        <div class="discount-applied-info">
                            <span id="discountAppliedLabel" style="font-weight: 600; color: #2e7d32;"></span>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="cancelDiscount()" style="float: right; font-size: 12px; padding: 4px 8px;">Cancel</button>
                        </div>
                        <div class="form-group" id="idNumberGroup" style="display: none; margin-top: 10px;">
                            <label>ID Number (for verification)</label>
                            <input type="text" id="idNumber" class="form-control" placeholder="Enter ID number">
                        </div>
                    </div>

                    <div class="payment-methods">
                        <label>Payment Method</label>
                        <div class="payment-options">
                            <button class="payment-btn active" data-method="cash" title="Cash">
                                <span class="payment-icon"></span>
                                <span>Cash</span>
                            </button>
                             <!--<button class="payment-btn" data-method="card" title="Debit Card">
                                <span class="payment-icon">💳</span>
                                <span>Card</span>
                            </button> -->

                            <button class="payment-btn" data-method="gcash" title="GCash">
                                <span class="payment-icon"></span>
                                <span>GCash</span>
                            </button>
                        </div>
                        <!-- GCash reference moved here so cashier selects payment method first -->
                        <div class="form-group" id="gcashRefGroup" style="display: none; margin-top:10px;">
                            <label>GCash Reference Number <small style="color:#666; font-weight:500;">(required for GCash payments)</small></label>
                            <input type="text" id="gcashRef" class="form-control" placeholder="Enter GCash reference number">
                        </div>
                    </div>

                    <!-- Added payment input section with change calculation -->
                    <div class="payment-input-section">
                        <label>Cash Received</label>
                        <div class="payment-input-group">
                            <span class="currency-symbol">₱</span>
                            <input type="number" id="paymentAmount" class="payment-input" placeholder="0.00" min="0" step="0.01" onchange="calculateChange()" oninput="calculateChange()">
                        </div>
                        <div class="change-display" id="changeDisplay" style="display: none;">
                            <div class="change-row">
                                <span>Change</span>
                                <span id="changeAmount" class="change-amount">₱0.00</span>
                            </div>
                        </div>
                        <div class="payment-status" id="paymentStatus"></div>
                    </div>

                    <!-- Bill Summary -->
                    <div class="bill-summary">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="subtotal">₱0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Tax 12% (VAT included)</span>
                            <span id="tax">₱0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="total">₱0.00</span>
                        </div>
                        <div class="summary-row tendered-row" id="tenderedRow" style="display: none;">
                            <span>Paid Amount</span>
                            <span id="tenderedAmount">₱0.00</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bill-actions">
                        <button class="btn btn-secondary btn-block" id="clearCart">Clear</button>
                        <button class="btn btn-primary btn-block" id="checkoutBtn">Place Order</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Checkout Modal -->
<div id="checkoutModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Order</h3>
            <div class="modal-header-actions">
                
                <span class="close">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="checkoutModalBody" style="max-height: 400px; overflow-y: auto;">
            <div class="form-group">
                <label>Customer Name (Optional)</label>
                <input type="text" id="customerName" class="form-control" placeholder="Walk-in customer">
            </div>
            
          
            
            <div class="checkout-summary">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="checkoutSubtotal">₱0.00</span>
                </div>
                <div class="summary-row" id="discountRow" style="display: none; color: #4caf50;">
                    <span>Discount:</span>
                    <span id="checkoutDiscount">-₱0.00</span>
                </div>
                <div class="summary-row total">
                    <span>Total Amount:</span>
                    <span id="checkoutTotal">₱0.00</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelCheckout">Cancel</button>
            <button class="btn btn-primary" id="confirmCheckout">Place Order</button>
        </div>
    </div>
</div>

<style>
/* POS Container */
.pos-container {
    display: grid;
    /* Use flexible columns so the bill section can expand without causing horizontal scroll
       First column (products) and second column (bill) share available space equally.
       This replaces the fixed 550px width which could force horizontal scrolling. */
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

/* Products Section */
.pos-products {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.pos-search {
    display: flex;
}

.pos-search input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

/* Category Buttons */
.pos-categories {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.category-btn {
    padding: 10px 16px;
    border: 2px solid #ddd;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}

.category-btn:hover {
    border-color: #8B4513;
    color: #8B4513;
}

.category-btn.active {
    background: #8B4513;
    color: white;
    border-color: #8B4513;
}

/* Products Grid */
.products-grid {
    display: grid;
    /* Use a fixed column width so tiles are equal-sized squares.
       auto-fill will create as many tiles per row as fit. */
    grid-template-columns: repeat(auto-fill, 140px);
    gap: 8px;
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 6px;
    max-height: 600px;
    justify-content: start; /* keep tiles left-aligned */
}

/* Product Tiles (Text-only) */
.product-tile {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    padding: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: center;
    /* Fixed square tiles */
    width: 140px;
    height: 140px;
    box-sizing: border-box;
}

.product-tile:hover {
    border-color: #8B4513;
    box-shadow: 0 4px 12px rgba(139, 69, 19, 0.15);
    transform: translateY(-2px);
}

.product-tile.out-of-stock {
    opacity: 0.6;
    border-color: #ddd;
    cursor: default;
}

.tile-name {
    font-size: 11px;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
    line-height: 1.2;
}

.tile-price {
    font-size: 14px;
    font-weight: 700;
    color: #8B4513;
}

/* Bill Section */
.pos-bill {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    height: fit-content;
    position: sticky;
    top: 20px;
    max-height: 150vh;
    overflow-y: auto;
    /* Ensure the bill uses full width of its grid column and doesn't impose its own max-width */
    width: 100%;
    max-width: none;
}

.bill-content {
    display: flex;
    gap: 20px;
    flex: 1;
}

.bill-left {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.bill-right {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.bill-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.bill-header h3 {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.bill-date {
    font-size: 12px;
    color: #999;
}

/* Bill Items */
.bill-items {
    margin-bottom: 15px;
}

.empty-bill {
    text-align: center;
    padding: 30px 10px;
    color: #999;
    font-size: 13px;
}

.bill-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 6px;
    margin-bottom: 8px;
    font-size: 13px;
}

.bill-item-info {
    flex: 1;
}

.bill-item-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.bill-item-price {
    font-size: 12px;
    color: #666;
}

.bill-item-controls {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0 8px;
}

.qty-btn {
    width: 24px;
    height: 24px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    color: #666;
}

.qty-btn:hover {
    background: #f0f0f0;
}


.qty-display {
    width: 30px;
    max-width: 80px;
    padding: 6px 6px;
    text-align: center;
    font-weight: 600;
    font-size: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    box-sizing: border-box;
    appearance: textfield;
    -moz-appearance: textfield; /* Firefox */
}

/* Remove spinner arrows on number inputs so users can type quantities */
.qty-display::-webkit-outer-spin-button,
.qty-display::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.bill-item-remove {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    font-size: 16px;
    padding: 0;
}

.bill-item-remove:hover {
    opacity: 0.7;
}

/* Bill Summary */
.bill-summary {
    background: #f9f9f9;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 8px;
    color: #666;
}

.summary-row.total {
    font-size: 15px;
    font-weight: 700;
    color: #333;
    border-top: 2px solid #ddd;
    padding-top: 8px;
    margin-top: 8px;
}

/* Payment Methods */
.payment-methods {
    margin-bottom: 15px;
}

.payment-methods label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    margin-bottom: 8px;
}

.payment-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.payment-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 10px;
    border: 2px solid #ddd;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 500;
    transition: all 0.2s;
}

.payment-btn:hover {
    border-color: #8B4513;
}

.payment-btn.active {
    background: #8B4513;
    color: white;
    border-color: #8B4513;
}

.payment-icon {
    font-size: 20px;
}

/* Action Buttons */
.bill-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.btn-block {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: #8B4513;
    color: white;
}

.btn-primary:hover {
    background: #6b3410;
}

.btn-secondary {
    background: #e0e0e0;
    color: #333;
}

.btn-secondary:hover {
    background: #d0d0d0;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close {
    font-size: 28px;
    font-weight: bold;
    color: #999;
    cursor: pointer;
}

.close:hover {
    color: #333;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    font-size: 13px;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
}

/* Added payment input section styles */
.payment-input-section {
    background: #f0f8ff;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    border: 2px solid #e3f2fd;
}

.payment-input-section label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #1976d2;
    margin-bottom: 8px;
}

.payment-input-group {
    display: flex;
    align-items: center;
    background: white;
    border: 2px solid #1976d2;
    border-radius: 6px;
    overflow: hidden;
}

.currency-symbol {
    padding: 10px 12px;
    font-weight: 600;
    color: #1976d2;
    background: #f0f8ff;
}

.payment-input {
    flex: 1;
    border: none;
    padding: 10px 12px;
    font-size: 16px;
    font-weight: 600;
    outline: none;
}

.payment-input::placeholder {
    color: #ccc;
}

.change-display {
    margin-top: 10px;
    padding: 10px;
    background: white;
    border-radius: 4px;
    border-left: 4px solid #4caf50;
}

.change-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #333;
}

.change-amount {
    font-weight: 700;
    color: #4caf50;
    font-size: 16px;
}

.payment-status {
    margin-top: 8px;
    padding: 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
}

.payment-status.insufficient {
    background: #ffebee;
    color: #c62828;
}

.payment-status.sufficient {
    background: #e8f5e9;
    color: #2e7d32;
}

/* Added discount quick buttons styles */
.discount-section {
    background: #f9f9f9;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.discount-section label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    margin-bottom: 8px;
}

.discount-quick-buttons {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.discount-quick-btn {
    padding: 10px 12px;
    border: 2px solid #e0e0e0;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #333;
    transition: all 0.2s;
    text-align: left;
}

.discount-quick-btn:hover {
    border-color: #4caf50;
    background: #f1f8e9;
}

.discount-quick-btn.active {
    background: #4caf50;
    color: white;
    border-color: #4caf50;
}

.discount-applied-info {
    font-size: 13px;
}

/* Responsive */
/* Tablet: between 768px and 1024px - preserve two-column layout but enlarge touch targets */
@media (min-width: 768px) and (max-width: 1024px) {
    .pos-container {
        grid-template-columns: 1.4fr 0.6fr; /* give more space to products area on tablets */
        gap: 16px;
    }

    .pos-bill {
        position: sticky;
        top: 20px;
    }

    .bill-content {
        flex-direction: column;
        gap: 12px;
    }

    .bill-left,
    .bill-right {
        flex: none;
    }

    .products-grid {
        /* Slightly larger tiles for tablet touch use */
        grid-template-columns: repeat(auto-fill, 160px);
        gap: 12px;
        justify-content: start;
        max-height: 72vh;
    }

    .product-tile {
        width: 160px;
        height: 160px;
        padding: 10px;
    }

    .tile-name {
        font-size: 13px;
    }

    .tile-price {
        font-size: 16px;
    }

    .category-btn {
        padding: 12px 18px;
        font-size: 14px;
    }

    .payment-btn {
        padding: 14px;
        font-size: 13px;
    }

    .payment-icon {
        font-size: 22px;
    }

    .btn-block {
        padding: 16px;
        font-size: 15px;
    }

    .qty-btn {
        width: 36px;
        height: 36px;
        font-size: 16px;
    }

    .qty-display {
        width: 44px;
        padding: 8px 6px;
        font-size: 14px;
    }
}

/* Mobile: below 768px */
@media (max-width: 767px) {
    .pos-container {
        grid-template-columns: 1fr;
    }

    .pos-bill {
        position: static;
    }

    .bill-content {
        flex-direction: column;
        gap: 15px;
    }

    .bill-left,
    .bill-right {
        flex: none;
    }

    .products-grid {
        /* Smaller tiles for narrow screens */
        grid-template-columns: repeat(auto-fill, 100px);
        justify-content: start;
    }

    .payment-options {
        grid-template-columns: repeat(2, 1fr);
    }

    .bill-content {
        gap: 10px;
    }
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
</style>

<script>
// POS System
let cart = [];
let currentCategory = 'all';
let currentPaymentMethod = 'cash';
let currentOrderId = null;
// VAT rate (12% VAT, price is VAT-inclusive)
const VAT_RATE = 0.12;

// Set current date
document.getElementById('billDate').textContent = new Date().toLocaleDateString('en-US', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric'
});

// Check if adding to existing order
const urlParams = new URLSearchParams(window.location.search);
const addToOrderId = urlParams.get('add_to_order');
if (addToOrderId) {
    loadExistingOrder(addToOrderId);
}

// Product tile click handler
document.querySelectorAll('.product-tile').forEach(tile => {
    tile.addEventListener('click', function() {
        // Prevent adding if out of stock (dataset.stock: 'na' means N/A)
        const stockVal = this.dataset.stock;
        if (typeof stockVal !== 'undefined' && stockVal !== 'na') {
            const stockNum = parseInt(stockVal, 10);
            if (!isNaN(stockNum) && stockNum <= 0) {
                alert('This item is currently out of stock');
                return;
            }
        }

        const product = {
            id: this.dataset.id,
            name: this.dataset.name,
            price: parseFloat(this.dataset.price)
        };
        addToCart(product);
    });
});

// Add to cart
function addToCart(product) {
    const existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        existingItem.quantity++;
    } else {
        cart.push({
            ...product,
            quantity: 1
        });
    }
    
    updateCart();
}

// Update cart display
function updateCart() {
    const cartItems = document.getElementById('cartItems');
    
    if (cart.length === 0) {
        cartItems.innerHTML = '<div class="empty-bill"><p>No items added</p></div>';
        document.getElementById('subtotal').textContent = '₱0.00';
        document.getElementById('tax').textContent = '₱0.00';
        document.getElementById('total').textContent = '₱0.00';
        return;
    }
    
    let html = '';
    let gross = 0; // gross = sum of prices (VAT-inclusive)

    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity; // VAT-inclusive
        gross += itemTotal;
        
        html += `
            <div class="bill-item">
                <div class="bill-item-info">
                    <div class="bill-item-name">${item.name}</div>
                    <div class="bill-item-price">₱${item.price.toFixed(2)}</div>
                </div>
                <div class="bill-item-controls">
                    <button class="qty-btn" onclick="decreaseQty(${index})">−</button>
                    <input type="number" class="qty-display" value="${item.quantity}" min="1" step="1" inputmode="numeric" pattern="\\d*" onchange="updateQty(${index}, this.value)">
                    <button class="qty-btn" onclick="increaseQty(${index})">+</button>
                </div>
                <button class="bill-item-remove" onclick="removeItem(${index})">×</button>
            </div>
        `;
    });
    
    cartItems.innerHTML = html;

    // VAT-inclusive math: gross is price already including VAT.
    // Apply any selected discount (senior/PWD 20% or VAT-exempt ~12%)
    let discountAmount = 0;
    if (selectedDiscount === 'senior' || selectedDiscount === 'pwd') {
        discountAmount = gross * 0.20;
    } else if (selectedDiscount === 'vat_exempt') {
        discountAmount = gross * 0.12;
    }

    const total = gross - discountAmount; // VAT-inclusive total after discount

    // net (subtotal before VAT) = total / (1 + VAT_RATE)
    const net = total / (1 + VAT_RATE);
    const vat = total - net;

    document.getElementById('subtotal').textContent = '₱' + net.toFixed(2);
    document.getElementById('tax').textContent = '₱' + vat.toFixed(2);
    document.getElementById('total').textContent = '₱' + total.toFixed(2);

    // Scroll to top of bill to show added items
    const bill = document.querySelector('.pos-bill');
    bill.scrollTop = 0;
}

// Increase quantity
function increaseQty(index) {
    cart[index].quantity++;
    updateCart();
}

// Decrease quantity
function decreaseQty(index) {
    if (cart[index].quantity > 1) {
        cart[index].quantity--;
    } else {
        cart.splice(index, 1);
    }
    updateCart();
}

// Update quantity directly
function updateQty(index, newQty) {
    const qty = parseInt(newQty);
    if (qty > 0) {
        cart[index].quantity = qty;
    } else {
        cart.splice(index, 1);
    }
    updateCart();
}

// Remove item
function removeItem(index) {
    cart.splice(index, 1);
    updateCart();
}

// Load existing order for adding items
function loadExistingOrder(orderId) {
    fetch(`<?php echo SITE_URL; ?>/api/get_order_details.php?id=${orderId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentOrderId = orderId;
                document.getElementById('billDate').textContent = 'Order #' + data.order.order_number;

                // Load existing items into cart
                cart = data.items.map(item => ({
                    id: item.product_id,
                    name: item.product_name,
                    price: parseFloat(item.price),
                    quantity: parseInt(item.quantity)
                }));

                updateCart();

                // Update checkout button text
                document.getElementById('checkoutBtn').textContent = 'Update Order';
            } else {
                alert('Failed to load order: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading order:', error);
            alert('Error loading order details');
        });
}

// Clear cart
document.getElementById('clearCart').addEventListener('click', function() {
    if (currentOrderId) {
        if (confirm('This will clear all items from the current order. Continue?')) {
            cart = [];
            updateCart();
        }
    } else {
        if (confirm('Clear all items from cart?')) {
            cart = [];
            updateCart();
        }
    }
});

// Category filter
document.querySelectorAll('.category-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        currentCategory = this.dataset.category;
        filterProducts();
    });
});

// Payment method selection
document.querySelectorAll('.payment-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.payment-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentPaymentMethod = this.dataset.method;
        updateGcashFieldVisibility();
    });
});

// Show or hide GCash reference input based on selected payment method
function updateGcashFieldVisibility() {
    const gcashGroup = document.getElementById('gcashRefGroup');
    const gcashInput = document.getElementById('gcashRef');
    if (!gcashGroup || !gcashInput) return;

    if (currentPaymentMethod === 'gcash') {
        gcashGroup.style.display = 'block';
        gcashInput.setAttribute('required', 'required');
        // focus the input when user selects GCash
        setTimeout(() => gcashInput.focus(), 50);
    } else {
        gcashGroup.style.display = 'none';
        gcashInput.removeAttribute('required');
        gcashInput.value = '';
    }
}

// Search products
document.getElementById('searchProduct').addEventListener('input', function() {
    filterProducts();
});

// Filter products
function filterProducts() {
    const searchTerm = document.getElementById('searchProduct').value.toLowerCase();
    const tiles = document.querySelectorAll('.product-tile');
    
    tiles.forEach(tile => {
        const name = tile.dataset.name.toLowerCase();
        const category = tile.dataset.category;
        
        const matchesSearch = name.includes(searchTerm);
        const matchesCategory = currentCategory === 'all' || category === currentCategory;
        
        if (matchesSearch && matchesCategory) {
            tile.style.display = 'block';
        } else {
            tile.style.display = 'none';
        }
    });
}

function calculateChange() {
    const totalAmount = parseFloat(document.getElementById('total').textContent.replace('₱', '')) || 0;
    const paymentAmount = parseFloat(document.getElementById('paymentAmount').value) || 0;
    const changeDisplay = document.getElementById('changeDisplay');
    const paymentStatus = document.getElementById('paymentStatus');
    const tenderedRow = document.getElementById('tenderedRow');
    const tenderedAmount = document.getElementById('tenderedAmount');

    if (paymentAmount > 0) {
        const change = paymentAmount - totalAmount;
        document.getElementById('changeAmount').textContent = '₱' + Math.max(0, change).toFixed(2);
        changeDisplay.style.display = 'block';
        tenderedRow.style.display = 'flex';
        tenderedAmount.textContent = '₱' + paymentAmount.toFixed(2);

        if (paymentAmount >= totalAmount) {
            paymentStatus.className = 'payment-status sufficient';
            paymentStatus.textContent = 'Payment sufficient';
        } else {
            paymentStatus.className = 'payment-status insufficient';
            paymentStatus.textContent = '✗ Insufficient payment (₱' + (totalAmount - paymentAmount).toFixed(2) + ' short)';
        }
    } else {
        changeDisplay.style.display = 'none';
        tenderedRow.style.display = 'none';
        paymentStatus.textContent = '';
    }
}

let selectedDiscount = null;

function applyQuickDiscount(discountType, discountLabel) {
    selectedDiscount = discountType;

    // Update button states
    document.querySelectorAll('.discount-quick-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-discount="${discountType}"]`).classList.add('active');

    // Show discount info
    const discountOptions = document.getElementById('discountOptions');
    const idNumberGroup = document.getElementById('idNumberGroup');
    const discountAppliedLabel = document.getElementById('discountAppliedLabel');

    discountOptions.style.display = 'block';
    discountAppliedLabel.textContent = '✓ ' + discountLabel + ' applied';

    if (discountType === 'senior' || discountType === 'pwd') {
        idNumberGroup.style.display = 'block';
    } else {
        idNumberGroup.style.display = 'none';
        document.getElementById('idNumber').value = '';
    }

    updateCheckoutSummary();
}

function cancelDiscount() {
    selectedDiscount = null;

    // Update button states
    document.querySelectorAll('.discount-quick-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Hide discount info
    const discountOptions = document.getElementById('discountOptions');
    const idNumberGroup = document.getElementById('idNumberGroup');

    discountOptions.style.display = 'none';
    idNumberGroup.style.display = 'none';
    document.getElementById('idNumber').value = '';

    updateCheckoutSummary();
}

// Checkout modal
const modal = document.getElementById('checkoutModal');
const checkoutBtn = document.getElementById('checkoutBtn');
const closeModal = document.querySelector('.close');
const cancelCheckout = document.getElementById('cancelCheckout');

checkoutBtn.addEventListener('click', function() {
    if (cart.length === 0) {
        alert('Cart is empty!');
        return;
    }

    selectedDiscount = null;
    document.querySelectorAll('.discount-quick-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById('discountOptions').style.display = 'none';
    document.getElementById('idNumberGroup').style.display = 'none';
    document.getElementById('idNumber').value = '';
    document.getElementById('customerName').value = '';
    cancelDiscount();

    updateCheckoutSummary();
    updateGcashFieldVisibility();
    modal.style.display = 'block';
});

closeModal.addEventListener('click', function() {
    modal.style.display = 'none';
});

cancelCheckout.addEventListener('click', function() {
    modal.style.display = 'none';
});

function updateCheckoutSummary() {
    // gross is VAT-inclusive total before discounts
    const gross = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    let discountAmount = 0;

    if (selectedDiscount === 'senior' || selectedDiscount === 'pwd') {
        discountAmount = gross * 0.20;
    } else if (selectedDiscount === 'vat_exempt') {
        // VAT exempt treated as removing VAT portion (approx). We'll approximate as 12% of gross
        discountAmount = gross * 0.12;
    }

    const total = gross - discountAmount;

    // net (subtotal before VAT) after discounts
    const netAfterDiscount = total / (1 + VAT_RATE);
    const vatAfterDiscount = total - netAfterDiscount;

    document.getElementById('checkoutSubtotal').textContent = '₱' + netAfterDiscount.toFixed(2);

    if (discountAmount > 0) {
        document.getElementById('discountRow').style.display = 'flex';
        document.getElementById('checkoutDiscount').textContent = '-₱' + discountAmount.toFixed(2);
    } else {
        document.getElementById('discountRow').style.display = 'none';
    }

    document.getElementById('checkoutTotal').textContent = '₱' + total.toFixed(2);
}

document.getElementById('confirmCheckout').addEventListener('click', function() {
    const customerName = document.getElementById('customerName').value;
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    let discountAmount = 0;
    let discountPercentage = 0;

    if (selectedDiscount === 'senior' || selectedDiscount === 'pwd') {
        discountPercentage = 20;
        discountAmount = subtotal * 0.20;

        const idNumber = document.getElementById('idNumber').value.trim();
        if (!idNumber) {
            alert('Please enter ID number for discount verification');
            return;
        }
    } else if (selectedDiscount === 'vat_exempt') {
        discountPercentage = 12;
        discountAmount = subtotal * 0.12;
    }

    const total = subtotal - discountAmount;
    let amount_paid = total;

    // If GCash is selected, require a valid GCash reference number
    if (currentPaymentMethod === 'gcash') {
        const gcashRefInput = document.getElementById('gcashRef');
        const gcashRef = gcashRefInput ? gcashRefInput.value.trim() : '';
        // Basic validation: at least 6 alphanumeric characters (allow hyphens)
        const validRef = /^[A-Za-z0-9-]{6,}$/.test(gcashRef);
        if (!gcashRef) {
            alert('Please enter the GCash reference number before completing the payment.');
            return;
        }
        if (!validRef) {
            alert('Invalid GCash reference number. It must be at least 6 alphanumeric characters.');
            return;
        }
        // For GCash we assume payment is made externally and amount_paid equals total
        amount_paid = total;
    }

    if (currentPaymentMethod === 'cash') {
        amount_paid = parseFloat(document.getElementById('paymentAmount').value) || 0;
        if (amount_paid < total) {
            alert('Insufficient payment amount');
            return;
        }
    }

    const orderData = {
        order_id: currentOrderId, // Include order ID if updating existing order
        customer_name: customerName,
        payment_method: currentPaymentMethod,
        discount_type: selectedDiscount,
        discount_percentage: discountPercentage,
        discount_amount: discountAmount,
        gcash_reference: (currentPaymentMethod === 'gcash') ? (document.getElementById('gcashRef').value.trim() || null) : null,
        discount_id_number: document.getElementById('idNumber').value || null,
        amount_paid: amount_paid,
        items: cart
    };

    const apiEndpoint = currentOrderId ? '<?php echo SITE_URL; ?>/api/update_order.php' : '<?php echo SITE_URL; ?>/api/checkout.php';

    fetch(apiEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(orderData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = currentOrderId ? 'Order updated successfully!' : 'Sale completed successfully!';
            const orderNum = currentOrderId ? '' : '\nOrder #: ' + data.order_number;
            alert(message + orderNum);

            cart = [];
            updateCart();
            modal.style.display = 'none';
            document.getElementById('paymentAmount').value = '';
            calculateChange();

            document.getElementById('customerName').value = '';
            selectedDiscount = null;
            document.getElementById('idNumber').value = '';
            document.getElementById('paymentAmount').value = '';
            calculateChange();

            // Reset to new order mode
            currentOrderId = null;
            document.getElementById('billDate').textContent = new Date().toLocaleDateString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            document.getElementById('checkoutBtn').textContent = 'Place Order';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
});

function scrollCheckoutModal(direction) {
    const modalBody = document.getElementById('checkoutModalBody');
    const scrollAmount = 100; // pixels to scroll

    if (direction === 'up') {
        modalBody.scrollTop -= scrollAmount;
    } else if (direction === 'down') {
        modalBody.scrollTop += scrollAmount;
    }
}

window.addEventListener('click', function(event) {
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
