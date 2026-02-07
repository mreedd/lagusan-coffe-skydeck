<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in() || !has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$format = $_GET['format'] ?? 'csv';

// Get top selling products
$stmt = $conn->query("
    SELECT 
        p.id,
        p.name,
        SUM(si.quantity) as quantity_sold,
        SUM(si.quantity * si.price) as revenue
    FROM products p
    LEFT JOIN sale_items si ON p.id = si.product_id
    LEFT JOIN sales s ON si.sale_id = s.id
    WHERE s.status = 'completed' 
        AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.id
    ORDER BY quantity_sold DESC
    LIMIT 10
");

$top_selling = [];
while ($row = $stmt->fetch_assoc()) {
    $top_selling[] = [
        'name' => $row['name'],
        'quantity_sold' => (int)$row['quantity_sold'],
        'revenue' => (float)$row['revenue']
    ];
}

// Get slow moving products
$stmt = $conn->query("
    SELECT 
        p.id,
        p.name,
        COALESCE(SUM(si.quantity), 0) as quantity_sold
    FROM products p
    LEFT JOIN sale_items si ON p.id = si.product_id
    LEFT JOIN sales s ON si.sale_id = s.id 
        AND s.status = 'completed' 
        AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE p.status = 'available'
    GROUP BY p.id
    ORDER BY quantity_sold ASC
    LIMIT 10
");

$slow_moving = [];
while ($row = $stmt->fetch_assoc()) {
    $slow_moving[] = [
        'name' => $row['name'],
        'quantity_sold' => (int)$row['quantity_sold']
    ];
}

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="product_performance_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['Product Performance Report', '', '']);
    fputcsv($output, ['Generated on', date('Y-m-d H:i:s'), '']);
    fputcsv($output, ['Period', 'Last 30 Days', '']);
    fputcsv($output, ['', '', '']);
    
    // Top Selling
    fputcsv($output, ['Top Selling Products', '', '']);
    fputcsv($output, ['Product Name', 'Quantity Sold', 'Revenue']);
    foreach ($top_selling as $row) {
        fputcsv($output, [
            $row['name'],
            $row['quantity_sold'],
            '₱' . number_format($row['revenue'], 2)
        ]);
    }
    
    fputcsv($output, ['', '', '']);
    
    // Slow Moving
    fputcsv($output, ['Slow Moving Products', '', '']);
    fputcsv($output, ['Product Name', 'Quantity Sold', '']);
    foreach ($slow_moving as $row) {
        fputcsv($output, [
            $row['name'],
            $row['quantity_sold'],
            ''
        ]);
    }
    
    fclose($output);
}
?>
