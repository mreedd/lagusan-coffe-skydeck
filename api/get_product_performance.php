<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get top selling products (last 30 days)
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

// Get slow moving products (last 30 days)
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

// Get chart data for all products
$stmt = $conn->query("
    SELECT 
        p.name,
        COALESCE(SUM(si.quantity), 0) as quantity_sold
    FROM products p
    LEFT JOIN sale_items si ON p.id = si.product_id
    LEFT JOIN sales s ON si.sale_id = s.id 
        AND s.status = 'completed' 
        AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE p.status = 'available'
    GROUP BY p.id
    ORDER BY quantity_sold DESC
    LIMIT 15
");

$chart_labels = [];
$chart_values = [];

while ($row = $stmt->fetch_assoc()) {
    $chart_labels[] = $row['name'];
    $chart_values[] = (int)$row['quantity_sold'];
}

echo json_encode([
    'success' => true,
    'top_selling' => $top_selling,
    'slow_moving' => $slow_moving,
    'chart' => [
        'labels' => $chart_labels,
        'values' => $chart_values
    ]
]);
?>
