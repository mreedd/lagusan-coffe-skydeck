<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

if (!has_role('admin')) {
    redirect('dashboard.php');
}

$page_title = 'Reports';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Reports & Analytics</h1>
        <p>Sales reports, inventory trends, and product performance</p>
    </div>
    
    <!-- Sales Reports Section -->
    <div class="report-section">
        <div class="section-header">
            <h2>Sales Reports</h2>
            <div class="header-controls">
                <div class="period-selector">
                    <button class="period-btn active" data-period="daily" onclick="changePeriod('daily')">Daily</button>
                    <button class="period-btn" data-period="weekly" onclick="changePeriod('weekly')">Weekly</button>
                    <button class="period-btn" data-period="monthly" onclick="changePeriod('monthly')">Monthly</button>
                    <button class="period-btn" data-period="custom" onclick="showCustomDatePicker()">Custom Range</button>
                </div>
                <div class="date-range-picker" id="dateRangePicker" style="display: none;">
                    <input type="date" id="startDate" placeholder="Start Date">
                    <span>to</span>
                    <input type="date" id="endDate" placeholder="End Date">
                    <button class="btn btn-primary" onclick="applyCustomDateRange()">Apply</button>
                    <button class="btn btn-secondary" onclick="cancelCustomDateRange()">Cancel</button>
                </div>
                <div class="export-buttons">
                    <button class="export-btn export-csv" onclick="exportSalesReport('csv')" title="Export as CSV">
                        <span>📥 CSV</span>
                    </button>
                    <button class="export-btn export-pdf" onclick="exportSalesReport('pdf')" title="Export as PDF">
                        <span>📄 PDF</span>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h3 id="totalSales">₱0.00</h3>
                    <p>Total Sales</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-info">
                    <h3 id="totalOrders">0</h3>
                    <p>Total Orders</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-info">
                    <h3 id="avgOrderValue">₱0.00</h3>
                    <p>Avg Order Value</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-info">
                    <h3 id="growthRate">0%</h3>
                    <p>Growth Rate</p>
                </div>
            </div>
        </div>
        
        <div class="chart-card">
            <h3>Sales Trend</h3>
            <canvas id="salesChart"></canvas>
        </div>
    </div>
    
    <!-- Inventory Usage Trends Section -->
    <div class="report-section">
        <div class="section-header">
            <h2>Inventory Usage Trends</h2>
             <!-- Inventory Usage Trends Section 
              <div class="export-buttons">
                <button class="export-btn export-csv" onclick="exportInventoryReport('csv')" title="Export as CSV">
                    <span>📥 CSV</span>
                </button>
            </div>
             -->
        </div>
        
        <div class="chart-card">
            <h3>Top 10 Most Used Ingredients</h3>
            <canvas id="inventoryChart"></canvas>
        </div>
        
        <div class="table-card">
            <h3>Inventory Movement Details</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Current Stock</th>
                        <th>Used (Last 30 Days)</th>
                        <th>Avg Daily Usage</th>
                        <th>Days Until Reorder</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Top Selling & Slow Movers Section -->
    <div class="report-section">
        <div class="section-header">
            <h2>Product Performance</h2>
            <div class="export-buttons">
                <button class="export-btn export-csv" onclick="exportProductPerformance('csv')" title="Export as CSV">
                    <span>📥 CSV</span>
                </button>
            </div>
        </div>

        <div class="performance-grid">
            <div class="performance-card">
                <h3>🏆 Top Selling Products</h3>
                <div id="topSellingList" class="product-list">
                    <p style="text-align: center; padding: 20px; color: #999;">Loading...</p>
                </div>
            </div>

            <div class="performance-card">
                <h3>🐌 Slow Moving Products</h3>
                <div id="slowMovingList" class="product-list">
                    <p style="text-align: center; padding: 20px; color: #999;">Loading...</p>
                </div>
            </div>
        </div>

        <div class="chart-card">
            <h3>Product Sales Comparison</h3>
            <canvas id="productsChart"></canvas>
        </div>
    </div>

    <!-- Financial Overview Section -->
    <div class="report-section">
        <div class="section-header">
            <h2>Financial Overview</h2>
            <div class="header-controls">
                <button class="btn btn-primary" onclick="refreshFinancialOverview()">
                    <span>🔄</span> Refresh Data
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h3 id="totalInventoryCost">₱0.00</h3>
                    <p>Total Inventory Cost</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-info">
                    <h3 id="totalSalesRevenue">₱0.00</h3>
                    <p>Total Sales Revenue</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" id="profitLossIcon">📊</div>
                <div class="stat-info">
                    <h3 id="profitLossAmount">₱0.00</h3>
                    <p id="profitLossLabel">Profit/Loss</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-info">
                    <h3 id="profitMargin">0.00%</h3>
                    <p>Profit Margin</p>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.report-section {
    background: white;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    flex-wrap: wrap;
    gap: 15px;
}

.section-header h2 {
    margin: 0;
    color: #333;
    font-size: 22px;
}

.header-controls {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.period-selector {
    display: flex;
    gap: 10px;
}

.period-btn {
    padding: 8px 20px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}

.period-btn:hover {
    background: #f5f5f5;
}

/* Tablet styles: improve controls and touch sizes */
@media (min-width: 768px) and (max-width: 1024px) {
    .report-section {
        padding: 28px;
        border-radius: 10px;
    }

    .section-header {
        gap: 18px;
    }

    .period-btn {
        padding: 12px 24px;
        font-size: 15px;
        border-radius: 8px;
    }

    .header-controls {
        gap: 16px;
    }

    .stat-card .stat-icon {
        font-size: 36px;
    }

    .stat-card .stat-info h3 {
        font-size: 20px;
    }

    .chart-container {
        height: 360px;
    }
}

.period-btn.active {
    background: #96715e;
    color: white;
    border-color: #96715e;
}

/* Added export button styles */
.export-buttons {
    display: flex;
    gap: 10px;
}

.export-btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.export-btn:hover {
    background: #f5f5f5;
    border-color: #96715e;
}

.export-csv {
    color: #2c5f2d;
}

.export-pdf {
    color: #c41e3a;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    padding: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 1px solid #e0e0e0;
}

.stat-icon {
    font-size: 36px;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-info h3 {
    margin: 0 0 5px 0;
    font-size: 28px;
    color: #2c5f2d;
}

.stat-info p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.chart-card {
    background: #fafafa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.chart-card h3 {
    margin: 0 0 20px 0;
    color: #333;
    font-size: 18px;
}

.chart-card canvas {
    max-height: 350px;
}

.table-card {
    background: #fafafa;
    padding: 20px;
    border-radius: 8px;
}

.table-card h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 18px;
}

.performance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.performance-card {
    background: #fafafa;
    padding: 20px;
    border-radius: 8px;
}

.performance-card h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 18px;
}

.product-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.product-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: white;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
}

.product-name {
    font-weight: 500;
    color: #333;
}

.product-stats {
    display: flex;
    gap: 15px;
    font-size: 14px;
    color: #666;
}

.badge-success {
    background: #d4edda;
    color: #155724;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.date-range-picker {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.date-range-picker input[type="date"] {
    padding: 6px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.date-range-picker span {
    color: #666;
    font-weight: 500;
}

.date-range-picker .btn {
    padding: 6px 16px;
    font-size: 14px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentPeriod = 'daily';
let currentStartDate = null;
let currentEndDate = null;
let salesChart, inventoryChart, productsChart;

function changePeriod(period) {
    currentPeriod = period;

    // Update button states
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.period === period) {
            btn.classList.add('active');
        }
    });

    // Hide custom date picker if switching to predefined periods
    if (period !== 'custom') {
        document.getElementById('dateRangePicker').style.display = 'none';
        // Clear custom date parameters
        currentStartDate = null;
        currentEndDate = null;
    }

    loadSalesReport();
    loadFinancialOverview();
}

function loadSalesReport() {
    let url = `<?php echo SITE_URL; ?>/api/get_sales_report.php?period=${currentPeriod}`;
    if (currentStartDate && currentEndDate) {
        url = `<?php echo SITE_URL; ?>/api/get_sales_report.php?start_date=${currentStartDate}&end_date=${currentEndDate}`;
    }

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update stats
                document.getElementById('totalSales').textContent = formatCurrency(data.stats.total_sales);
                document.getElementById('totalOrders').textContent = data.stats.total_orders;
                document.getElementById('avgOrderValue').textContent = formatCurrency(data.stats.avg_order_value);
                document.getElementById('growthRate').textContent = data.stats.growth_rate + '%';

                // Update chart
                updateSalesChart(data.chart);
            }
        });
}

function updateSalesChart(chartData) {
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    if (salesChart) {
        salesChart.destroy();
    }
    
    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Sales',
                data: chartData.values,
                borderColor: '#96715e',
                backgroundColor: 'rgba(150, 113, 94, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
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

function loadInventoryTrends() {
    fetch('<?php echo SITE_URL; ?>/api/get_inventory_trends.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateInventoryChart(data.chart);
                updateInventoryTable(data.details);
            }
        });
}

function updateInventoryChart(chartData) {
    const ctx = document.getElementById('inventoryChart').getContext('2d');
    
    if (inventoryChart) {
        inventoryChart.destroy();
    }
    
    inventoryChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Quantity Used',
                data: chartData.values,
                backgroundColor: '#96715e'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function updateInventoryTable(details) {
    const tbody = document.getElementById('inventoryTableBody');
    
    if (details.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No inventory data available</td></tr>';
        return;
    }
    
    tbody.innerHTML = details.map(item => {
        let statusBadge = '';
        if (item.days_until_reorder <= 3) {
            statusBadge = '<span class="badge-danger">Critical</span>';
        } else if (item.days_until_reorder <= 7) {
            statusBadge = '<span class="badge-warning">Low</span>';
        } else {
            statusBadge = '<span class="badge-success">Good</span>';
        }
        
        return `
            <tr>
                <td>${item.item_name}</td>
                <td>${item.current_stock} ${item.unit}</td>
                <td>${item.used_30_days} ${item.unit}</td>
                <td>${item.avg_daily_usage} ${item.unit}</td>
                <td>${item.days_until_reorder} days</td>
                <td>${statusBadge}</td>
            </tr>
        `;
    }).join('');
}

function loadProductPerformance() {
    fetch('<?php echo SITE_URL; ?>/api/get_product_performance.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateTopSelling(data.top_selling);
                updateSlowMoving(data.slow_moving);
                updateProductsChart(data.chart);
            }
        });
}

function updateTopSelling(products) {
    const container = document.getElementById('topSellingList');
    
    if (products.length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 20px; color: #999;">No sales data available</p>';
        return;
    }
    
    container.innerHTML = products.map((product, index) => `
        <div class="product-item">
            <div>
                <div class="product-name">${index + 1}. ${product.name}</div>
                <div class="product-stats">
                    <span>${product.quantity_sold} sold</span>
                    <span>${formatCurrency(product.revenue)} revenue</span>
                </div>
            </div>
        </div>
    `).join('');
}

function updateSlowMoving(products) {
    const container = document.getElementById('slowMovingList');
    
    if (products.length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 20px; color: #999;">All products are selling well!</p>';
        return;
    }
    
    container.innerHTML = products.map((product, index) => `
        <div class="product-item">
            <div>
                <div class="product-name">${index + 1}. ${product.name}</div>
                <div class="product-stats">
                    <span>${product.quantity_sold} sold</span>
                    <span>Last 30 days</span>
                </div>
            </div>
        </div>
    `).join('');
}

function updateProductsChart(chartData) {
    const ctx = document.getElementById('productsChart').getContext('2d');
    
    if (productsChart) {
        productsChart.destroy();
    }
    
    productsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Quantity Sold',
                data: chartData.values,
                backgroundColor: '#96715e'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2);
}

function exportSalesReport(format) {
    const url = `<?php echo SITE_URL; ?>/api/export_sales_report.php?period=${currentPeriod}&format=${format}`;
    window.location.href = url;
}

function exportInventoryReport(format) {
    const url = `<?php echo SITE_URL; ?>/api/export_inventory_report.php?format=${format}`;
    window.location.href = url;
}

function exportProductPerformance(format) {
    const url = `<?php echo SITE_URL; ?>/api/export_product_performance.php?format=${format}`;
    window.location.href = url;
}

function loadFinancialOverview() {
    let url = '<?php echo SITE_URL; ?>/api/get_profit_loss.php';
    if (currentStartDate && currentEndDate) {
        url += `?start_date=${currentStartDate}&end_date=${currentEndDate}`;
    } else if (currentPeriod) {
        url += `?period=${currentPeriod}`;
    }

    // Load inventory cost
    fetch('<?php echo SITE_URL; ?>/api/get_inventory_cost.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalInventoryCost').textContent = formatCurrency(data.total_cost);
            }
        });

    // Load profit/loss data
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalSalesRevenue').textContent = formatCurrency(data.total_sales_revenue);
                document.getElementById('profitLossAmount').textContent = formatCurrency(Math.abs(data.profit_loss));
                document.getElementById('profitMargin').textContent = data.profit_margin + '%';

                const profitLossLabel = document.getElementById('profitLossLabel');
                const profitLossIcon = document.getElementById('profitLossIcon');
                const profitLossAmount = document.getElementById('profitLossAmount');

                if (data.is_profit) {
                    profitLossLabel.textContent = 'Profit';
                    profitLossIcon.textContent = '📈';
                    profitLossAmount.style.color = '#28a745';
                } else {
                    profitLossLabel.textContent = 'Loss';
                    profitLossIcon.textContent = '📉';
                    profitLossAmount.style.color = '#dc3545';
                }
            }
        });
}

function showCustomDatePicker() {
    const picker = document.getElementById('dateRangePicker');
    picker.style.display = picker.style.display === 'none' ? 'flex' : 'none';

    // Update button states
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.period === 'custom') {
            btn.classList.add('active');
        }
    });
}

function applyCustomDateRange() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    if (!startDate || !endDate) {
        alert('Please select both start and end dates.');
        return;
    }

    if (new Date(startDate) > new Date(endDate)) {
        alert('Start date cannot be after end date.');
        return;
    }

    currentStartDate = startDate;
    currentEndDate = endDate;
    currentPeriod = null; // Clear predefined period

    // Hide date picker
    document.getElementById('dateRangePicker').style.display = 'none';

    // Load reports with custom date range
    loadSalesReport();
    loadFinancialOverview();
}

function cancelCustomDateRange() {
    document.getElementById('dateRangePicker').style.display = 'none';
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';

    // Reset to daily if no custom range
    if (!currentStartDate) {
        changePeriod('daily');
    }
}

function refreshFinancialOverview() {
    loadFinancialOverview();
}

// Load all reports on page load
loadSalesReport();
loadInventoryTrends();
loadProductPerformance();
loadFinancialOverview();
</script>

<?php include 'includes/footer.php'; ?>
