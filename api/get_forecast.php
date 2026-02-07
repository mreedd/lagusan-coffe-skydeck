<?php
require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../session_check.php';

header('Content-Type: application/json');

// Check if user is admin
if (!has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$forecast_days = $_GET['forecast'] ?? 30;
$historical_days = $_GET['historical'] ?? 60;

// Get historical sales data
$stmt = $conn->prepare("
    SELECT DATE(created_at) as date, SUM(total_amount) as total, COUNT(*) as orders
    FROM sales 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->bind_param("i", $historical_days);
$stmt->execute();
$historical = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_sales = array_sum(array_column($historical, 'total'));
$total_orders = array_sum(array_column($historical, 'orders'));
$avg_daily_sales = count($historical) > 0 ? $total_sales / count($historical) : 0;

// Simple linear regression for forecast
$forecast_data = [];
$growth_rate = 0;

if (count($historical) > 1) {
    // Calculate growth rate
    $first_week = array_slice($historical, 0, 7);
    $last_week = array_slice($historical, -7);
    
    $first_week_avg = array_sum(array_column($first_week, 'total')) / count($first_week);
    $last_week_avg = array_sum(array_column($last_week, 'total')) / count($last_week);
    
    if ($first_week_avg > 0) {
        $growth_rate = (($last_week_avg - $first_week_avg) / $first_week_avg) * 100;
    }
    
    // Generate forecast
    for ($i = 1; $i <= $forecast_days; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $day_name = date('l', strtotime($date));
        
        // Apply growth rate to average
        $projected = $avg_daily_sales * (1 + ($growth_rate / 100) * ($i / 30));
        $expected_orders = round($projected / ($avg_daily_sales > 0 ? $avg_daily_sales / ($total_orders / count($historical)) : 1));
        
        $forecast_data[] = [
            'date' => date('M d, Y', strtotime($date)),
            'day' => $day_name,
            'projected_sales' => round($projected, 2),
            'expected_orders' => $expected_orders,
            'confidence' => max(50, 95 - ($i * 0.5)) // Confidence decreases over time
        ];
    }
}

// Prepare chart data
$chart_labels = array_merge(
    array_map(function($item) { return date('M d', strtotime($item['date'])); }, $historical),
    array_map(function($item) { return date('M d', strtotime($item['date'])); }, array_slice($forecast_data, 0, 14))
);

$chart_historical = array_merge(
    array_column($historical, 'total'),
    array_fill(0, min(14, count($forecast_data)), null)
);

$chart_forecast = array_merge(
    array_fill(0, count($historical), null),
    array_column(array_slice($forecast_data, 0, 14), 'projected_sales')
);

echo json_encode([
    'success' => true,
    'stats' => [
        'projected_sales' => array_sum(array_column($forecast_data, 'projected_sales')),
        'avg_daily_sales' => round($avg_daily_sales, 2),
        'growth_rate' => round($growth_rate, 1),
        'confidence' => count($historical) > 30 ? 85 : (count($historical) > 14 ? 70 : 50)
    ],
    'chart' => [
        'labels' => $chart_labels,
        'historical' => $chart_historical,
        'forecast' => $chart_forecast
    ],
    'forecast' => $forecast_data
]);
