<?php
session_start();
require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? 'sales';
$period = $_GET['period'] ?? 'week';

if ($type === 'top_products') {
    // Get top selling products
    $stmt = $conn->query("
        SELECT p.name, SUM(si.quantity) as total_qty 
        FROM sale_items si 
        JOIN products p ON si.product_id = p.id 
        JOIN sales s ON si.sale_id = s.id 
        WHERE s.status = 'completed' 
        GROUP BY p.id 
        ORDER BY total_qty DESC 
        LIMIT 5
    ");
    
    $labels = [];
    $values = [];
    
    while ($row = $stmt->fetch_assoc()) {
        $labels[] = $row['name'];
        $values[] = (int)$row['total_qty'];
    }
    
    echo json_encode(['labels' => $labels, 'values' => $values]);
} else {
    // Get sales data for the period
    $days = $period === 'week' ? 7 : 30;
    $labels = [];
    $values = [];
    
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime($date));
        
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) = ? AND status = 'completed'");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $values[] = (float)$stmt->get_result()->fetch_assoc()['total'];
    }
    
    echo json_encode(['labels' => $labels, 'values' => $values]);
}
?>
