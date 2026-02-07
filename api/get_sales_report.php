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

$period = $_GET['period'] ?? 'daily';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Determine date range and labels based on period or custom dates
if ($start_date && $end_date) {
    // Custom date range
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days = $interval->days + 1;

    // Generate labels for custom range
    $labels = [];
    $current = clone $start;
    while ($current <= $end) {
        $labels[] = $current->format('M d');
        $current->modify('+1 day');
    }

    $date_format = 'M d';
    $compare_days = $days * 2; // Compare with previous equivalent period
} else {
    // Predefined periods
    switch ($period) {
        case 'daily':
            $days = 7;
            $date_format = 'M d';
            $compare_days = 14; // Compare with previous week
            break;
        case 'weekly':
            $days = 28; // 4 weeks
            $date_format = 'M d';
            $compare_days = 56; // Compare with previous 4 weeks
            break;
        case 'monthly':
            $days = 365; // 12 months
            $date_format = 'M Y';
            $compare_days = 730; // Compare with previous year
            break;
        default:
            $days = 7;
            $date_format = 'M d';
            $compare_days = 14;
    }
}

// Get current period data
if (!$start_date || !$end_date) {
    // Predefined periods - generate labels and data
    $labels = [];
    $values = [];
    $total_sales = 0;
    $total_orders = 0;

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date($date_format, strtotime($date));

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

        $values[] = (float)$result['total'];
        $total_sales += (float)$result['total'];
        $total_orders += (int)$result['orders'];
    }
} else {
    // Custom date range - get data for the range
    $values = [];
    $total_sales = 0;
    $total_orders = 0;

    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(total_amount), 0) as total,
            COUNT(*) as orders
        FROM sales
        WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $values[] = (float)$result['total'];
    $total_sales = (float)$result['total'];
    $total_orders = (int)$result['orders'];
}

// Calculate average order value
$avg_order_value = $total_orders > 0 ? $total_sales / $total_orders : 0;

// Get previous period data for growth calculation
$prev_start = date('Y-m-d', strtotime("-$compare_days days"));
$prev_end = date('Y-m-d', strtotime("-$days days"));

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as prev_total
    FROM sales 
    WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'
");
$stmt->bind_param("ss", $prev_start, $prev_end);
$stmt->execute();
$prev_total = (float)$stmt->get_result()->fetch_assoc()['prev_total'];

// Calculate growth rate
$growth_rate = 0;
if ($prev_total > 0) {
    $growth_rate = (($total_sales - $prev_total) / $prev_total) * 100;
}

echo json_encode([
    'success' => true,
    'stats' => [
        'total_sales' => $total_sales,
        'total_orders' => $total_orders,
        'avg_order_value' => $avg_order_value,
        'growth_rate' => round($growth_rate, 1)
    ],
    'chart' => [
        'labels' => $labels,
        'values' => $values
    ]
]);
?>
