<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

if (!has_role('admin')) {
    redirect('index.php');
}

$page_title = 'Ingredient Check';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Ingredient Configuration Check</h1>
        <p>Verify which products have ingredients linked</p>
    </div>
    
    <div class="card">
        <h3>Products and Their Ingredients</h3>
        
        <?php
        $products = $conn->query("SELECT * FROM products ORDER BY name");
        
        while ($product = $products->fetch_assoc()):
            $product_id = $product['id'];
            
            // Get ingredients for this product
            $ingredients_query = $conn->prepare("
                SELECT pi.*, i.item_name, i.quantity as stock_quantity, i.unit 
                FROM product_ingredients pi
                JOIN inventory i ON pi.inventory_id = i.id
                WHERE pi.product_id = ?
            ");
            $ingredients_query->bind_param("i", $product_id);
            $ingredients_query->execute();
            $ingredients = $ingredients_query->get_result();
        ?>
        
        <div style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px;">
            <h4 style="margin: 0 0 10px 0;"><?php echo safe_html($product['name']); ?></h4>
            
            <?php if ($ingredients->num_rows === 0): ?>
                <p style="color: #e74c3c; font-weight: bold;">
                    ⚠️ NO INGREDIENTS LINKED - This product will NOT deduct inventory when sold!
                </p>
                <p style="margin: 5px 0;">
                    <a href="products.php" style="color: #3498db;">Go to Products page and click the 🧪 icon to add ingredients</a>
                </p>
            <?php else: ?>
                <p style="color: #27ae60; font-weight: bold;">✓ Ingredients configured:</p>
                <table style="width: 100%; margin-top: 10px;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; text-align: left;">Ingredient</th>
                            <th style="padding: 8px; text-align: left;">Used Per Item</th>
                            <th style="padding: 8px; text-align: left;">Current Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ing = $ingredients->fetch_assoc()): ?>
                        <tr>
                            <td style="padding: 8px;"><?php echo safe_html($ing['item_name']); ?></td>
                            <td style="padding: 8px;"><?php echo $ing['quantity_used']; ?> <?php echo safe_html($ing['unit']); ?></td>
                            <td style="padding: 8px;"><?php echo $ing['stock_quantity']; ?> <?php echo safe_html($ing['unit']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <?php endwhile; ?>
    </div>
    
    <div class="card" style="margin-top: 20px;">
        <h3>Recent Inventory Deductions</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Inventory Item</th>
                    <th>Quantity Deducted</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $logs = $conn->query("
                    SELECT il.*, i.item_name, i.unit
                    FROM inventory_log il
                    JOIN inventory i ON il.inventory_id = i.id
                    WHERE il.action = 'reduce' AND il.notes LIKE '%Auto-deducted%'
                    ORDER BY il.created_at DESC
                    LIMIT 20
                ");
                
                if ($logs->num_rows === 0):
                ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px; color: #999;">
                        No automatic deductions yet. Complete a sale to see deductions here.
                    </td>
                </tr>
                <?php else: ?>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                        <td><?php echo safe_html($log['item_name']); ?></td>
                        <td><?php echo $log['quantity']; ?> <?php echo safe_html($log['unit']); ?></td>
                        <td><?php echo safe_html($log['notes']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
