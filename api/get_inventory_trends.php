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

// Get top 10 most used inventory items in last 30 days
$stmt = $conn->query("
    SELECT 
        i.item_name,
        i.unit,
        COALESCE(SUM(il.quantity), 0) as total_used
    FROM inventory i
    LEFT JOIN inventory_log il ON i.id = il.inventory_id 
        AND il.action = 'reduce' 
        AND il.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY i.id
    ORDER BY total_used DESC
    LIMIT 10
");

$chart_labels = [];
$chart_values = [];

while ($row = $stmt->fetch_assoc()) {
    $chart_labels[] = $row['item_name'];
    $chart_values[] = (float)$row['total_used'];
}

// Get detailed inventory movement data
$stmt = $conn->query("
    SELECT 
        i.id,
        i.item_name,
        i.quantity as current_stock,
        i.unit,
        i.reorder_level,
        COALESCE(SUM(CASE WHEN il.action = 'reduce' AND il.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN il.quantity ELSE 0 END), 0) as used_30_days
    FROM inventory i
    LEFT JOIN inventory_log il ON i.id = il.inventory_id
    GROUP BY i.id
    ORDER BY used_30_days DESC
");

$details = [];

while ($row = $stmt->fetch_assoc()) {
    $used_30_days = (float)$row['used_30_days'];
    $avg_daily_usage = $used_30_days / 30;
    
    // Calculate days until reorder level
    $days_until_reorder = 0;
    if ($avg_daily_usage > 0) {
        $stock_above_reorder = $row['current_stock'] - $row['reorder_level'];
        $days_until_reorder = max(0, $stock_above_reorder / $avg_daily_usage);
    } else {
        $days_until_reorder = 999; // No usage, so plenty of time
    }
    
    $details[] = [
        'item_name' => $row['item_name'],
        'current_stock' => round($row['current_stock'], 2),
        'unit' => $row['unit'],
        'used_30_days' => round($used_30_days, 2),
        'avg_daily_usage' => round($avg_daily_usage, 2),
        'days_until_reorder' => round($days_until_reorder, 0)
    ];
}

echo json_encode([
    'success' => true,
    'chart' => [
        'labels' => $chart_labels,
        'values' => $chart_values
    ],
    'details' => $details
]);
?>
