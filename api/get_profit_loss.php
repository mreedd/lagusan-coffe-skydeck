<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!has_role('admin') && !has_role('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get date parameters
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$period = $_GET['period'] ?? null;

// Calculate total inventory cost (always current)
$inventory_stmt = $conn->query("SELECT SUM(quantity * cost_per_unit) as total_cost FROM inventory WHERE cost_per_unit > 0");
$inventory_result = $inventory_stmt->fetch_assoc();
$total_inventory_cost = $inventory_result['total_cost'] ?? 0.00;

// Calculate total sales revenue (only completed sales)
$sales_query = "SELECT SUM(total_amount) as total_revenue FROM sales WHERE status = 'completed'";
$sales_params = [];
$sales_types = "";

if ($start_date && $end_date) {
    // Custom date range
    $sales_query .= " AND DATE(created_at) BETWEEN ? AND ?";
    $sales_params = [$start_date, $end_date];
    $sales_types = "ss";
} elseif ($period) {
    // Predefined periods
    switch ($period) {
        case 'daily':
            $days = 7;
            break;
        case 'weekly':
            $days = 28;
            break;
        case 'monthly':
            $days = 365;
            break;
        default:
            $days = 7;
    }
    $start_date = date('Y-m-d', strtotime("-$days days"));
    $end_date = date('Y-m-d');
    $sales_query .= " AND DATE(created_at) BETWEEN ? AND ?";
    $sales_params = [$start_date, $end_date];
    $sales_types = "ss";
}

$sales_stmt = $conn->prepare($sales_query);
if (!empty($sales_params)) {
    $sales_stmt->bind_param($sales_types, ...$sales_params);
}
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result()->fetch_assoc();
$total_sales_revenue = $sales_result['total_revenue'] ?? 0.00;

// Calculate profit/loss
$profit_loss = $total_sales_revenue - $total_inventory_cost;

echo json_encode([
    'success' => true,
    'total_inventory_cost' => round($total_inventory_cost, 2),
    'total_sales_revenue' => round($total_sales_revenue, 2),
    'profit_loss' => round($profit_loss, 2),
    'is_profit' => $profit_loss >= 0,
    'profit_margin' => $total_sales_revenue > 0 ? round(($profit_loss / $total_sales_revenue) * 100, 2) : 0
]);
?>
