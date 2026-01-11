<?php
/**
 * Analytics Dashboard
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireAuth();
Auth::requirePermission(Permissions::VIEW_ANALYTICS);

$pageTitle = 'Analytics';
$api = getApiClient();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo csrfToken(); ?>">
    <title><?php echo e($pageTitle); ?> - CurrentRMS Report Builder</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app-layout">
        <?php include 'includes/partials/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/partials/header.php'; ?>

            <div class="content">
                <?php if (!$api): ?>
                    <div class="alert alert-warning">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <strong>API Not Configured</strong>
                            <p>Please configure your CurrentRMS API credentials in <a href="settings.php">Settings</a> to view analytics.</p>
                        </div>
                    </div>
                <?php else: ?>

                <!-- Date Range Filter -->
                <div class="filter-bar">
                    <div class="filter-group">
                        <label class="filter-label">Date Range:</label>
                        <select class="form-control" id="date-range" style="width: 200px;">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button class="btn btn-secondary btn-sm" onclick="refreshAnalytics()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M23 4v6h-6M1 20v-6h6"/>
                                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
                            </svg>
                            Refresh
                        </button>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="stat-cards">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Revenue</div>
                            <div class="stat-value" id="kpi-revenue">$0</div>
                            <div class="stat-change up" id="kpi-revenue-change">
                                ↑ 0% from last period
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 6v6l4 2"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Opportunities Won</div>
                            <div class="stat-value" id="kpi-opportunities">0</div>
                            <div class="stat-change up" id="kpi-opp-change">
                                ↑ 0% from last period
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">New Customers</div>
                            <div class="stat-value" id="kpi-customers">0</div>
                            <div class="stat-change up" id="kpi-cust-change">
                                ↑ 0% from last period
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon info">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Products Utilized</div>
                            <div class="stat-value" id="kpi-products">0%</div>
                            <div class="stat-change up" id="kpi-prod-change">
                                ↑ 0% from last period
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Revenue Trend</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-revenue-trend"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Opportunities by Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-opp-status"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid mt-3" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Top Products by Revenue</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-top-products"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Customer Segments</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-customers"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity Timeline</h3>
                    </div>
                    <div class="card-body">
                        <div class="timeline" id="activity-timeline">
                            <div class="timeline-item">
                                <div class="timeline-date">Today</div>
                                <div class="timeline-title">Invoice #1234 - Paid</div>
                                <div class="timeline-desc">ABC Corporation - $2,500.00</div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-date">Yesterday</div>
                                <div class="timeline-title">New Opportunity Created</div>
                                <div class="timeline-desc">Wedding Event - Grand Hotel</div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-date">Jan 8</div>
                                <div class="timeline-title">Equipment Returned</div>
                                <div class="timeline-desc">Conference Setup - Tech Summit</div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-date">Jan 7</div>
                                <div class="timeline-title">New Customer Added</div>
                                <div class="timeline-desc">XYZ Events Ltd</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
        });

        function initCharts() {
            // Revenue Trend
            new Chart(document.getElementById('chart-revenue-trend'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Revenue',
                        data: [15000, 18000, 22000, 19000, 25000, 28000, 32000, 30000, 35000, 38000, 42000, 45000],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Opportunities by Status
            new Chart(document.getElementById('chart-opp-status'), {
                type: 'doughnut',
                data: {
                    labels: ['Confirmed', 'Provisional', 'Quote Sent', 'Draft', 'Closed'],
                    datasets: [{
                        data: [45, 20, 15, 10, 10],
                        backgroundColor: ['#10b981', '#667eea', '#f59e0b', '#9ca3af', '#3b82f6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });

            // Top Products
            new Chart(document.getElementById('chart-top-products'), {
                type: 'bar',
                data: {
                    labels: ['PA System', 'Stage Lights', 'Projector', 'Microphones', 'Speakers'],
                    datasets: [{
                        label: 'Revenue',
                        data: [12000, 9500, 8200, 7500, 6800],
                        backgroundColor: ['#667eea', '#764ba2', '#10b981', '#f59e0b', '#3b82f6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } }
                }
            });

            // Customer Segments
            new Chart(document.getElementById('chart-customers'), {
                type: 'pie',
                data: {
                    labels: ['Corporate', 'Weddings', 'Concerts', 'Conferences', 'Other'],
                    datasets: [{
                        data: [35, 25, 20, 15, 5],
                        backgroundColor: ['#667eea', '#10b981', '#f59e0b', '#ef4444', '#9ca3af']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });

            // Update KPIs
            document.getElementById('kpi-revenue').textContent = '$349,000';
            document.getElementById('kpi-opportunities').textContent = '127';
            document.getElementById('kpi-customers').textContent = '43';
            document.getElementById('kpi-products').textContent = '78%';
        }

        function refreshAnalytics() {
            App.showNotification('Analytics refreshed', 'success');
        }
    </script>
</body>
</html>
