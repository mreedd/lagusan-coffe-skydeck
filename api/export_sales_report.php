<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in() || !has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$period = $_GET['period'] ?? 'daily';
$format = $_GET['format'] ?? 'csv';

// Determine date range based on period
switch ($period) {
    case 'daily':
        $days = 7;
        $date_format = 'M d';
        break;
    case 'weekly':
        $days = 28;
        $date_format = 'M d';
        break;
    case 'monthly':
        $days = 365;
        $date_format = 'M Y';
        break;
    default:
        $days = 7;
        $date_format = 'M d';
}

// Get sales data
$sales_data = [];
$total_sales = 0;
$total_orders = 0;

for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_label = date($date_format, strtotime($date));
    
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total,
            COUNT(*) as orders
        FROM sales 
        WHERE DATE(created_at) = ? AND status = 'completed'
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $sales_data[] = [
        'date' => $date_label,
        'sales' => (float)$result['total'],
        'orders' => (int)$result['orders']
    ];
    
    $total_sales += (float)$result['total'];
    $total_orders += (int)$result['orders'];
}

$avg_order_value = $total_orders > 0 ? $total_sales / $total_orders : 0;

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['Sales Report - ' . ucfirst($period), '', '']);
    fputcsv($output, ['Generated on', date('Y-m-d H:i:s'), '']);
    fputcsv($output, ['', '', '']);
    
    // Summary
    fputcsv($output, ['Summary', '', '']);
    fputcsv($output, ['Total Sales', '₱' . number_format($total_sales, 2), '']);
    fputcsv($output, ['Total Orders', $total_orders, '']);
    fputcsv($output, ['Average Order Value', '₱' . number_format($avg_order_value, 2), '']);
    fputcsv($output, ['', '', '']);
    
    // Detailed data
    fputcsv($output, ['Date', 'Sales Amount', 'Number of Orders']);
    foreach ($sales_data as $row) {
        fputcsv($output, [
            $row['date'],
            '₱' . number_format($row['sales'], 2),
            $row['orders']
        ]);
    }
    
    fclose($output);
}
else if ($format === 'pdf') {
    // Render a printable HTML view which user can save as PDF via browser Print
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Sales Report</title>';
    echo '<style>body{font-family:Arial,Helvetica,sans-serif;padding:20px;color:#222}h1{font-size:20px}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f5f5}</style>';
    echo '</head><body>';
    echo '<h1>Sales Report - ' . ucfirst(htmlspecialchars($period)) . '</h1>';
    echo '<p>Generated on: ' . date('Y-m-d H:i:s') . '</p>';

    echo '<h2>Summary</h2>';
    echo '<table><tr><th>Metric</th><th>Value</th></tr>';
    echo '<tr><td>Total Sales</td><td>₱' . number_format($total_sales,2) . '</td></tr>';
    echo '<tr><td>Total Orders</td><td>' . intval($total_orders) . '</td></tr>';
    echo '<tr><td>Average Order Value</td><td>₱' . number_format($avg_order_value,2) . '</td></tr>';
    echo '</table>';

    echo '<h2>Detailed Data</h2>';
    echo '<table><thead><tr><th>Date</th><th>Sales Amount</th><th>Number of Orders</th></tr></thead><tbody>';
    foreach ($sales_data as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['date']) . '</td>';
        echo '<td>₱' . number_format($row['sales'],2) . '</td>';
        echo '<td>' . intval($row['orders']) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<script>window.onload=function(){window.print();}</script>';
    echo '</body></html>';
    exit;
}
?>
