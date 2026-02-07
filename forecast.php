<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

// Check if user is admin
if (!has_role('admin')) {
    redirect('dashboard.php');
}

$page_title = 'Sales Forecast';

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<style>
.forecast-controls {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    gap: 20px;
    align-items: flex-end;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.control-group label {
    font-size: 14px;
    font-weight: 500;
    color: #666;
}

.control-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.forecast-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.chart-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: relative;
    height: 400px;
}

/* Added canvas sizing to prevent Chart.js rendering issues */
.chart-container canvas {
    max-height: 350px;
}

.forecast-table {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<style>
/* Tablet styles: improve forecast controls and touch sizes */
@media (min-width: 768px) and (max-width: 1024px) {
    .forecast-controls {
        gap: 24px;
        padding: 22px;
        align-items: center;
    }

    .control-group select {
        padding: 10px 14px;
        font-size: 15px;
    }

    .btn-secondary {
        padding: 12px 18px;
        font-size: 15px;
        border-radius: 6px;
    }

    .forecast-stats .stat-card .stat-icon {
        font-size: 32px;
    }

    .chart-container {
        height: 380px;
    }
}
</style>
</style>

<main class="main-content">
    <div class="page-header">
        <h1>Sales Forecasting</h1>
        <p>Predict future sales trends based on historical data</p>
    </div>
    
    <div class="forecast-controls">
        <div class="control-group">
            <label>Forecast Period:</label>
            <select id="forecastPeriod" onchange="updateForecast()">
                <option value="7">Next 7 Days</option>
                <option value="14">Next 14 Days</option>
                <option value="30" selected>Next 30 Days</option>
                <option value="90">Next 90 Days</option>
            </select>
        </div>
        
        <div class="control-group">
            <label>Based On:</label>
            <select id="historicalPeriod" onchange="updateForecast()">
                <option value="30">Last 30 Days</option>
                <option value="60" selected>Last 60 Days</option>
                <option value="90">Last 90 Days</option>
            </select>
        </div>
        
        <button onclick="exportForecast()" class="btn-secondary">Export Report</button>
    </div>
    
    <div class="forecast-stats">
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-info">
                <h3 id="projectedSales">₱0.00</h3>
                <p>Projected Sales</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-info">
                <h3 id="avgDailySales">₱0.00</h3>
                <p>Avg Daily Sales</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📉</div>
            <div class="stat-info">
                <h3 id="growthRate">0%</h3>
                <p>Growth Rate</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-info">
                <h3 id="confidence">0%</h3>
                <p>Confidence Level</p>
            </div>
        </div>
    </div>
    
    <div class="chart-container">
        <h3>Sales Forecast Chart</h3>
        <!-- Added wrapper div for proper Chart.js rendering -->
        <div style="position: relative; height: 350px;">
            <canvas id="forecastChart"></canvas>
        </div>
    </div>
    
    <div class="forecast-table">
        <h3>Detailed Forecast</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Projected Sales</th>
                    <th>Expected Orders</th>
                    <th>Confidence</th>
                </tr>
            </thead>
            <tbody id="forecastTableBody">
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px;">
                        Loading forecast data...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</main>


<script>
let forecastChart;

function updateForecast() {
    const forecastPeriod = document.getElementById('forecastPeriod').value;
    const historicalPeriod = document.getElementById('historicalPeriod').value;
    
    fetch(`<?php echo SITE_URL; ?>/api/get_forecast.php?forecast=${forecastPeriod}&historical=${historicalPeriod}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update stats
                document.getElementById('projectedSales').textContent = formatCurrency(data.stats.projected_sales);
                document.getElementById('avgDailySales').textContent = formatCurrency(data.stats.avg_daily_sales);
                document.getElementById('growthRate').textContent = data.stats.growth_rate + '%';
                document.getElementById('confidence').textContent = data.stats.confidence + '%';
                
                // Update chart
                updateChart(data.chart);
                
                // Update table
                updateTable(data.forecast);
            } else {
                console.error('Forecast error:', data.message);
            }
        })
        .catch(err => console.error('Fetch error:', err));
}

function updateChart(chartData) {
    const ctx = document.getElementById('forecastChart').getContext('2d');
    
    if (forecastChart) {
        forecastChart.destroy();
    }
    
    forecastChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Historical Sales',
                    data: chartData.historical,
                    borderColor: '#96715e',
                    backgroundColor: 'rgba(150, 113, 94, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 3,
                    pointBackgroundColor: '#96715e'
                },
                {
                    label: 'Forecasted Sales',
                    data: chartData.forecast,
                    borderColor: '#d4a574',
                    backgroundColor: 'rgba(212, 165, 116, 0.1)',
                    borderDash: [5, 5],
                    tension: 0.4,
                    fill: true,
                    pointRadius: 3,
                    pointBackgroundColor: '#d4a574'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toFixed(0);
                        }
                    }
                }
            }
        }
    });
}

function updateTable(forecastData) {
    const tbody = document.getElementById('forecastTableBody');
    
    if (forecastData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px;">Not enough historical data to generate forecast.</td></tr>';
        return;
    }
    
    tbody.innerHTML = forecastData.map(item => `
        <tr>
            <td>${item.date}</td>
            <td>${item.day}</td>
            <td><strong>${formatCurrency(item.projected_sales)}</strong></td>
            <td>${item.expected_orders}</td>
            <td>
                <div class="confidence-bar">
                    <div class="confidence-fill" style="width: ${item.confidence}%"></div>
                    <span>${item.confidence}%</span>
                </div>
            </td>
        </tr>
    `).join('');
}

function exportForecast() {
    const forecastPeriod = document.getElementById('forecastPeriod').value;
    const historicalPeriod = document.getElementById('historicalPeriod').value;
    window.open(`<?php echo SITE_URL; ?>/reports/forecast_export.php?forecast=${forecastPeriod}&historical=${historicalPeriod}`, '_blank');
}

function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    updateForecast();
});
</script>

<?php include 'includes/footer.php'; ?>
