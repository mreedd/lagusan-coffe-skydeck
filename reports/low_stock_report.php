<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../includes/db_connect.php';

if (!has_role('admin') && !has_role('staff')) {
    redirect('../index.php');
}

// Get low stock items
$stmt = $conn->query("SELECT * FROM inventory WHERE quantity <= reorder_level ORDER BY quantity ASC");
$items = $stmt->fetch_all(MYSQLI_ASSOC);

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="low_stock_report_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Add headers
fputcsv($output, ['Item Name', 'Category', 'Current Stock', 'Reorder Level', 'Unit', 'Status']);

// Add data
foreach ($items as $item) {
    $status = $item['quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
    fputcsv($output, [
        $item['item_name'],
        $item['category'],
        $item['quantity'],
        $item['reorder_level'],
        $item['unit'],
        $status
    ]);
}

fclose($output);
exit;
