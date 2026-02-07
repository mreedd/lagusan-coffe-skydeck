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

// Get inventory data
$stmt = $conn->query("
    SELECT 
        i.item_name,
        i.quantity as current_stock,
        i.unit,
        COALESCE(SUM(CASE WHEN il.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN il.quantity_used ELSE 0 END), 0) as used_30_days
    FROM inventory i
    LEFT JOIN inventory_log il ON i.id = il.inventory_id
    GROUP BY i.id
    ORDER BY i.item_name
");

$inventory_data = [];
while ($row = $stmt->fetch_assoc()) {
    $avg_daily = $row['used_30_days'] / 30;
    $days_until_reorder = $avg_daily > 0 ? floor($row['current_stock'] / $avg_daily) : 999;
    
    $inventory_data[] = [
        'item_name' => $row['item_name'],
        'current_stock' => $row['current_stock'],
        'unit' => $row['unit'],
        'used_30_days' => $row['used_30_days'],
        'avg_daily_usage' => round($avg_daily, 2),
        'days_until_reorder' => $days_until_reorder
    ];
}

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['Inventory Usage Report', '', '', '', '', '']);
    fputcsv($output, ['Generated on', date('Y-m-d H:i:s'), '', '', '', '']);
    fputcsv($output, ['', '', '', '', '', '']);
    
    // Detailed data
    fputcsv($output, ['Item Name', 'Current Stock', 'Unit', 'Used (Last 30 Days)', 'Avg Daily Usage', 'Days Until Reorder']);
    foreach ($inventory_data as $row) {
        fputcsv($output, [
            $row['item_name'],
            $row['current_stock'],
            $row['unit'],
            $row['used_30_days'],
            $row['avg_daily_usage'],
            $row['days_until_reorder']
        ]);
    }
    
    fclose($output);
}
?>
