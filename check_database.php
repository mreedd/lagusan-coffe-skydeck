<?php
require_once 'config.php';
require_once 'includes/db_connect.php';

// This script checks the database structure and displays information
// Access it directly in your browser to see the database status

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Structure Check</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #4CAF50; color: white; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Database Structure Check</h1>
    
    <div class="section">
        <h2>Connection Status</h2>
        <?php if ($conn): ?>
            <p class="success">✓ Database connection successful</p>
            <p>Database: <strong><?php echo DB_NAME; ?></strong></p>
        <?php else: ?>
            <p class="error">✗ Database connection failed</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Sales Table Structure</h2>
        <?php
        $result = $conn->query("DESCRIBE sales");
        if ($result && $result->num_rows > 0):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Type</th>
                        <th>Null</th>
                        <th>Key</th>
                        <th>Default</th>
                        <th>Extra</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $has_payment_method = false;
                    while ($row = $result->fetch_assoc()): 
                        if ($row['Field'] === 'payment_method') {
                            $has_payment_method = true;
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo $row['Field']; ?></strong></td>
                        <td><?php echo $row['Type']; ?></td>
                        <td><?php echo $row['Null']; ?></td>
                        <td><?php echo $row['Key']; ?></td>
                        <td><?php echo $row['Default'] ?? 'NULL'; ?></td>
                        <td><?php echo $row['Extra']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <?php if ($has_payment_method): ?>
                <p class="success">✓ payment_method column exists</p>
            <?php else: ?>
                <p class="error">✗ payment_method column is MISSING!</p>
                <p class="warning">Run the migration script: scripts/add_payment_method_column.sql</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="error">✗ Sales table does not exist!</p>
            <p class="warning">Run the schema script: scripts/create_sales_table.sql</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Sales Data Count</h2>
        <?php
        $result = $conn->query("SELECT COUNT(*) as total FROM sales");
        if ($result) {
            $row = $result->fetch_assoc();
            $total = $row['total'];
            if ($total > 0) {
                echo "<p class='success'>✓ Found {$total} sales records</p>";
                
                // Check payment methods
                $pm_result = $conn->query("SELECT payment_method, COUNT(*) as count FROM sales GROUP BY payment_method");
                if ($pm_result && $pm_result->num_rows > 0) {
                    echo "<h3>Payment Methods Distribution:</h3>";
                    echo "<table><thead><tr><th>Payment Method</th><th>Count</th></tr></thead><tbody>";
                    while ($pm_row = $pm_result->fetch_assoc()) {
                        $pm = $pm_row['payment_method'] ?: '(empty/null)';
                        echo "<tr><td>{$pm}</td><td>{$pm_row['count']}</td></tr>";
                    }
                    echo "</tbody></table>";
                }
            } else {
                echo "<p class='warning'>⚠ No sales records found in database</p>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>Recent Sales (Last 10)</h2>
        <?php
        $result = $conn->query("SELECT id, order_number, customer_name, total_amount, payment_method, status, created_at FROM sales ORDER BY created_at DESC LIMIT 10");
        if ($result && $result->num_rows > 0):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo $row['order_number']; ?></td>
                        <td><?php echo $row['customer_name'] ?: 'Walk-in'; ?></td>
                        <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                        <td><?php echo strtoupper($row['payment_method'] ?: 'N/A'); ?></td>
                        <td><?php echo $row['status']; ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="warning">No sales records to display</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>All Tables in Database</h2>
        <?php
        $result = $conn->query("SHOW TABLES");
        if ($result):
        ?>
            <ul>
                <?php while ($row = $result->fetch_array()): ?>
                    <li><?php echo $row[0]; ?></li>
                <?php endwhile; ?>
            </ul>
        <?php endif; ?>
    </div>

</body>
</html>
