<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../session_check.php';

if (!has_role('admin')) {
    redirect('../dashboard.php');
}

$forecast_days = isset($_GET['forecast']) ? intval($_GET['forecast']) : 30;
$historical_days = isset($_GET['historical']) ? intval($_GET['historical']) : 60;

// Get historical sales data
$stmt = $conn->prepare(
    "SELECT DATE(created_at) as date, SUM(total_amount) as total, COUNT(*) as orders
    FROM sales
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY date ASC"
);
$stmt->bind_param("i", $historical_days);
$stmt->execute();
$historical = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_sales = array_sum(array_column($historical, 'total'));
$total_orders = array_sum(array_column($historical, 'orders'));
$avg_daily_sales = count($historical) > 0 ? $total_sales / count($historical) : 0;

// Simple linear forecast
$forecast_data = [];
$growth_rate = 0;

if (count($historical) > 1) {
    $first_week = array_slice($historical, 0, 7);
    $last_week = array_slice($historical, -7);

    $first_week_avg = count($first_week) ? array_sum(array_column($first_week, 'total')) / count($first_week) : 0;
    $last_week_avg = count($last_week) ? array_sum(array_column($last_week, 'total')) / count($last_week) : 0;

    if ($first_week_avg > 0) {
        $growth_rate = (($last_week_avg - $first_week_avg) / $first_week_avg) * 100;
    }

    for ($i = 1; $i <= $forecast_days; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $day_name = date('l', strtotime($date));
        $projected = $avg_daily_sales * (1 + ($growth_rate / 100) * ($i / 30));
        $expected_orders = round($projected / ($avg_daily_sales > 0 ? $avg_daily_sales / ($total_orders / max(1, count($historical))) : 1));

        $forecast_data[] = [
            'date' => date('M d, Y', strtotime($date)),
            'day' => $day_name,
            'projected_sales' => round($projected, 2),
            'expected_orders' => $expected_orders,
            'confidence' => max(50, 95 - ($i * 0.5))
        ];
    }
}

// Output printable HTML for browser print-to-PDF
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Sales Forecast Report</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;color:#222;padding:20px}
h1{font-size:20px}
table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{border:1px solid #ddd;padding:8px;text-align:left}
th{background:#f5f5f5}
.stats{display:flex;gap:20px;margin-top:10px}
.stat{padding:10px;border:1px solid #eee;border-radius:6px}
</style>
</head>
<body>
<h1>Sales Forecast Report (Next <?php echo intval($forecast_days); ?> days)</h1>
<p>Based on last <?php echo intval($historical_days); ?> days of data. Generated: <?php echo date('Y-m-d H:i:s'); ?></p>

<div class="stats">
    <div class="stat"><strong>Projected Sales:</strong><br>₱<?php echo number_format(array_sum(array_column($forecast_data, 'projected_sales')),2); ?></div>
    <div class="stat"><strong>Avg Daily Sales:</strong><br>₱<?php echo number_format($avg_daily_sales,2); ?></div>
    <div class="stat"><strong>Growth Rate:</strong><br><?php echo round($growth_rate,1); ?>%</div>
    <div class="stat"><strong>Confidence:</strong><br><?php echo count($historical) > 30 ? '85%' : (count($historical) > 14 ? '70%' : '50%'); ?></div>
</div>

<h2 style="margin-top:18px">Detailed Forecast</h2>
<table>
<thead>
<tr><th>Date</th><th>Day</th><th>Projected Sales</th><th>Expected Orders</th><th>Confidence</th></tr>
</thead>
<tbody>
<?php if (empty($forecast_data)): ?>
    <tr><td colspan="5" style="text-align:center;padding:20px">Not enough historical data to generate forecast.</td></tr>
<?php else: ?>
    <?php foreach ($forecast_data as $row): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['date']); ?></td>
            <td><?php echo htmlspecialchars($row['day']); ?></td>
            <td>₱<?php echo number_format($row['projected_sales'],2); ?></td>
            <td><?php echo intval($row['expected_orders']); ?></td>
            <td><?php echo intval($row['confidence']); ?>%</td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>

<script>window.onload=function(){window.print();}</script>
</body>
</html>
