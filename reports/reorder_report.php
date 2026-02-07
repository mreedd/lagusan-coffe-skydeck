<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../includes/db_connect.php';

if (!has_role('admin') && !has_role('staff')) {
    redirect('../index.php');
}

// Get reorder list
$stmt = $conn->query("
    SELECT r.*, i.item_name, i.quantity, i.unit, i.category 
    FROM reorder_list r 
    JOIN inventory i ON r.inventory_id = i.id 
    WHERE r.status = 'pending'
    ORDER BY r.priority DESC, r.created_at DESC
");
$items = $stmt->fetch_all(MYSQLI_ASSOC);

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="reorder_report_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Add headers
fputcsv($output, ['Item Name', 'Category', 'Current Stock', 'Suggested Order Quantity', 'Unit', 'Priority', 'Date Added']);

// Add data
foreach ($items as $item) {
    fputcsv($output, [
        $item['item_name'],
        $item['category'],
        $item['quantity'],
        $item['suggested_quantity'],
        $item['unit'],
        ucfirst($item['priority']),
        date('Y-m-d', strtotime($item['created_at']))
    ]);
}

fclose($output);
exit;
