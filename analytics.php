<?php
/**
 * Analytics Dashboard
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireAuth();
Auth::requirePermission(Permissions::VIEW_ANALYTICS);

$pageTitle = 'Analytics';
$api = getApiClient();
$currencySymbol = config('app.currency_symbol') ?? '£';
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
                        <button class="btn btn-secondary btn-sm" onclick="loadAnalytics()">
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
                            <div class="stat-value" id="kpi-revenue">
                                <div class="spinner" style="width: 20px; height: 20px;"></div>
                            </div>
                            <div class="stat-change" id="kpi-revenue-change"></div>
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
                            <div class="stat-label">Active Opportunities</div>
                            <div class="stat-value" id="kpi-opportunities">
                                <div class="spinner" style="width: 20px; height: 20px;"></div>
                            </div>
                            <div class="stat-change" id="kpi-opp-change"></div>
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
                            <div class="stat-value" id="kpi-customers">
                                <div class="spinner" style="width: 20px; height: 20px;"></div>
                            </div>
                            <div class="stat-change" id="kpi-cust-change"></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon info">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Product Utilisation</div>
                            <div class="stat-value" id="kpi-utilisation">
                                <div class="spinner" style="width: 20px; height: 20px;"></div>
                            </div>
                            <div class="stat-change" id="kpi-util-change"></div>
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
                            <div class="text-center text-muted">
                                <div class="spinner"></div>
                                <p>Loading activity...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Set currency symbol from config
        window.APP_CURRENCY = '<?php echo e($currencySymbol); ?>';
    </script>
    <script src="assets/js/app.js"></script>
    <script>
        const currencySymbol = window.APP_CURRENCY || '£';
        let charts = {};

        document.addEventListener('DOMContentLoaded', function() {
            loadAnalytics();

            // Reload when date range changes
            document.getElementById('date-range')?.addEventListener('change', loadAnalytics);
        });

        async function loadAnalytics() {
            const days = document.getElementById('date-range')?.value || 30;

            try {
                const response = await fetch(`api/analytics.php?days=${days}`);
                const data = await response.json();

                if (data.error) {
                    App.showNotification(data.error, 'error');
                    return;
                }

                updateKPIs(data.kpis);
                updateCharts(data.charts);
                updateTimeline(data.timeline);

            } catch (error) {
                console.error('Failed to load analytics:', error);
                App.showNotification('Failed to load analytics data', 'error');
            }
        }

        function updateKPIs(kpis) {
            // Revenue
            if (kpis.revenue) {
                document.getElementById('kpi-revenue').textContent = currencySymbol + parseFloat(kpis.revenue.value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                updateChange('kpi-revenue-change', kpis.revenue.change);
            }

            // Opportunities
            if (kpis.opportunities) {
                document.getElementById('kpi-opportunities').textContent = parseInt(kpis.opportunities.value || 0).toLocaleString();
                updateChange('kpi-opp-change', kpis.opportunities.change);
            }

            // Customers
            if (kpis.customers) {
                document.getElementById('kpi-customers').textContent = parseInt(kpis.customers.value || 0).toLocaleString();
                updateChange('kpi-cust-change', kpis.customers.change);
            }

            // Utilisation
            if (kpis.utilisation) {
                document.getElementById('kpi-utilisation').textContent = parseFloat(kpis.utilisation.value || 0).toFixed(1) + '%';
                updateChange('kpi-util-change', kpis.utilisation.change);
            }
        }

        function updateChange(elementId, change) {
            const el = document.getElementById(elementId);
            if (!el) return;

            const changeVal = parseFloat(change) || 0;
            const isUp = changeVal >= 0;
            el.className = 'stat-change ' + (isUp ? 'up' : 'down');
            el.textContent = (isUp ? '↑' : '↓') + ' ' + Math.abs(changeVal) + '% from last period';
        }

        function updateCharts(chartsData) {
            // Destroy existing charts
            Object.values(charts).forEach(chart => chart.destroy());
            charts = {};

            // Revenue Trend
            if (chartsData.revenue_trend) {
                const ctx = document.getElementById('chart-revenue-trend');
                if (ctx) {
                    charts.revenue = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartsData.revenue_trend.labels,
                            datasets: [{
                                label: 'Revenue',
                                data: chartsData.revenue_trend.values,
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
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return currencySymbol + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // Opportunities by Status
            if (chartsData.opp_status && chartsData.opp_status.labels.length > 0) {
                const ctx = document.getElementById('chart-opp-status');
                if (ctx) {
                    charts.oppStatus = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: chartsData.opp_status.labels,
                            datasets: [{
                                data: chartsData.opp_status.values,
                                backgroundColor: ['#10b981', '#667eea', '#f59e0b', '#9ca3af', '#3b82f6', '#ef4444', '#8b5cf6']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            }

            // Top Products
            if (chartsData.top_products && chartsData.top_products.labels.length > 0) {
                const ctx = document.getElementById('chart-top-products');
                if (ctx) {
                    charts.topProducts = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: chartsData.top_products.labels.slice(0, 10),
                            datasets: [{
                                label: 'Revenue',
                                data: chartsData.top_products.values.slice(0, 10),
                                backgroundColor: ['#667eea', '#764ba2', '#10b981', '#f59e0b', '#3b82f6', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: { legend: { display: false } },
                            scales: {
                                x: {
                                    ticks: {
                                        callback: function(value) {
                                            return currencySymbol + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // Customer Segments
            if (chartsData.customer_segments && chartsData.customer_segments.labels.length > 0) {
                const ctx = document.getElementById('chart-customers');
                if (ctx) {
                    charts.customers = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: chartsData.customer_segments.labels,
                            datasets: [{
                                data: chartsData.customer_segments.values,
                                backgroundColor: ['#667eea', '#10b981', '#f59e0b', '#ef4444', '#9ca3af', '#8b5cf6', '#3b82f6']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            }
        }

        function updateTimeline(timeline) {
            const container = document.getElementById('activity-timeline');
            if (!container) return;

            if (!timeline || timeline.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">No recent activity</p>';
                return;
            }

            container.innerHTML = timeline.map(item => {
                const date = new Date(item.date);
                const formattedDate = date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });

                return `
                    <div class="timeline-item">
                        <div class="timeline-date">${formattedDate}</div>
                        <div class="timeline-title">${escapeHtml(item.title)}</div>
                        <div class="timeline-desc">${escapeHtml(item.description)}</div>
                    </div>
                `;
            }).join('');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
