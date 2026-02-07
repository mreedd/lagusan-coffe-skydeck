<?php
session_start();
require_once 'config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Inventory Deduction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Inventory Deduction Test</h2>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5>Products and Their Ingredients</h5>
            </div>
            <div class="card-body">
                <?php
                $products_query = "SELECT * FROM products WHERE status = 'available'";
                $products_result = $conn->query($products_query);
                
                if ($products_result->num_rows > 0) {
                    while ($product = $products_result->fetch_assoc()) {
                        echo "<div class='mb-4 p-3 border rounded'>";
                        echo "<h6>{$product['name']} (ID: {$product['id']})</h6>";
                        
                        // Get ingredients for this product
                        $ingredients_query = "SELECT pi.*, i.item_name, i.quantity as current_qty, i.unit 
                                            FROM product_ingredients pi
                                            JOIN inventory i ON pi.inventory_id = i.id
                                            WHERE pi.product_id = ?";
                        $stmt = $conn->prepare($ingredients_query);
                        $stmt->bind_param("i", $product['id']);
                        $stmt->execute();
                        $ingredients_result = $stmt->get_result();
                        
                        if ($ingredients_result->num_rows > 0) {
                            echo "<table class='table table-sm mt-2'>";
                            echo "<thead><tr><th>Ingredient</th><th>Uses per Product</th><th>Current Stock</th></tr></thead>";
                            echo "<tbody>";
                            while ($ingredient = $ingredients_result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>{$ingredient['item_name']}</td>";
                                echo "<td>{$ingredient['quantity_used']} {$ingredient['unit']}</td>";
                                echo "<td>{$ingredient['current_qty']} {$ingredient['unit']}</td>";
                                echo "</tr>";
                            }
                            echo "</tbody></table>";
                        } else {
                            echo "<p class='text-danger mb-0'>⚠️ No ingredients linked! This product won't deduct inventory.</p>";
                        }
                        
                        echo "</div>";
                    }
                } else {
                    echo "<p>No products found.</p>";
                }
                ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5>Recent Inventory Logs</h5>
            </div>
            <div class="card-body">
                <?php
                $logs_query = "SELECT il.*, i.item_name, u.username 
                              FROM inventory_log il
                              JOIN inventory i ON il.inventory_id = i.id
                              LEFT JOIN users u ON il.user_id = u.id
                              WHERE il.action = 'reduce'
                              ORDER BY il.created_at DESC
                              LIMIT 10";
                $logs_result = $conn->query($logs_query);
                
                if ($logs_result->num_rows > 0) {
                    echo "<table class='table table-sm'>";
                    echo "<thead><tr><th>Item</th><th>Quantity</th><th>Notes</th><th>User</th><th>Date</th></tr></thead>";
                    echo "<tbody>";
                    while ($log = $logs_result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>{$log['item_name']}</td>";
                        echo "<td>-{$log['quantity']}</td>";
                        echo "<td>{$log['notes']}</td>";
                        echo "<td>{$log['username']}</td>";
                        echo "<td>" . date('M d, Y H:i', strtotime($log['created_at'])) . "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                } else {
                    echo "<p>No inventory deductions logged yet.</p>";
                }
                ?>
            </div>
        </div>
        
        <a href="products.php" class="btn btn-primary mt-3">Go to Products to Add Ingredients</a>
        <a href="pos.php" class="btn btn-success mt-3">Go to POS</a>
    </div>
</body>
</html>
