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
    <script>
        // Load theme immediately to prevent flash of wrong theme
        (function() {
            var theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        .widget-config-btn {
            position: relative;
        }
        .widget-panel {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 16px;
            min-width: 280px;
            z-index: 100;
            box-shadow: var(--shadow-lg);
            max-height: 400px;
            overflow-y: auto;
        }
        .widget-panel.active {
            display: block;
        }
        .widget-panel h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .widget-panel-section {
            margin-bottom: 16px;
        }
        .widget-option {
            display: flex;
            align-items: center;
            padding: 8px 0;
            gap: 10px;
        }
        .widget-option label {
            flex: 1;
            cursor: pointer;
        }
        .chart-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        [data-theme="dark"] .widget-panel {
            background: #1f2937;
            border-color: #374151;
        }
    </style>
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

                <!-- Date Range Filter & Widget Config -->
                <div class="filter-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div style="display: flex; gap: 16px; align-items: center;">
                        <div class="filter-group">
                            <label class="filter-label">Date Range:</label>
                            <select class="form-control" id="date-range" style="width: 200px;">
                                <option value="7">Last 7 Days</option>
                                <option value="30" selected>Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                                <option value="365">Last Year</option>
                            </select>
                        </div>
                        <button class="btn btn-secondary btn-sm" onclick="loadAnalytics()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M23 4v6h-6M1 20v-6h6"/>
                                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
                            </svg>
                            Refresh
                        </button>
                    </div>
                    <div class="widget-config-btn">
                        <button class="btn btn-secondary btn-sm" id="widget-config-toggle">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                            </svg>
                            Customize Widgets
                        </button>
                        <div class="widget-panel" id="widget-panel">
                            <div class="widget-panel-section">
                                <h4>KPI Cards</h4>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-revenue" checked onchange="toggleWidget('revenue')">
                                    <label for="widget-revenue">Total Revenue</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-opportunities" checked onchange="toggleWidget('opportunities')">
                                    <label for="widget-opportunities">Active Opportunities</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-projects" checked onchange="toggleWidget('projects')">
                                    <label for="widget-projects">Active Projects</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-utilisation" checked onchange="toggleWidget('utilisation')">
                                    <label for="widget-utilisation">Product Utilisation</label>
                                </div>
                            </div>
                            <div class="widget-panel-section">
                                <h4>Charts</h4>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-revenue_trend" checked onchange="toggleWidget('revenue_trend')">
                                    <label for="widget-revenue_trend">Revenue Trend</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-opp_status" checked onchange="toggleWidget('opp_status')">
                                    <label for="widget-opp_status">Opportunities by Status</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-top_products" checked onchange="toggleWidget('top_products')">
                                    <label for="widget-top_products">Top Products by Revenue</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-customer_segments" checked onchange="toggleWidget('customer_segments')">
                                    <label for="widget-customer_segments">Customer Segments</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-project_categories" checked onchange="toggleWidget('project_categories')">
                                    <label for="widget-project_categories">Projects by Category</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-category_revenue" checked onchange="toggleWidget('category_revenue')">
                                    <label for="widget-category_revenue">Revenue by Category</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-opportunity_types" checked onchange="toggleWidget('opportunity_types')">
                                    <label for="widget-opportunity_types">Opportunity Types</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="stat-cards" id="kpi-cards">
                    <div class="stat-card" data-widget="revenue">
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

                    <div class="stat-card" data-widget="opportunities">
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

                    <div class="stat-card" data-widget="projects">
                        <div class="stat-icon warning">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Active Projects</div>
                            <div class="stat-value" id="kpi-projects">
                                <div class="spinner" style="width: 20px; height: 20px;"></div>
                            </div>
                            <div class="stat-change" id="kpi-proj-change"></div>
                        </div>
                    </div>

                    <div class="stat-card" data-widget="utilisation">
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

                <!-- Charts Row 1 -->
                <div class="chart-row">
                    <div class="card" data-widget="revenue_trend">
                        <div class="card-header">
                            <h3 class="card-title">Revenue Trend</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-revenue-trend"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card" data-widget="opp_status">
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

                <!-- Charts Row 2 -->
                <div class="chart-row">
                    <div class="card" data-widget="top_products">
                        <div class="card-header">
                            <h3 class="card-title">Top Products by Revenue</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-top-products"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card" data-widget="customer_segments">
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

                <!-- Charts Row 3 - Project Categories -->
                <div class="chart-row">
                    <div class="card" data-widget="project_categories">
                        <div class="card-header">
                            <h3 class="card-title">Projects by Category</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-project-categories"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card" data-widget="category_revenue">
                        <div class="card-header">
                            <h3 class="card-title">Revenue by Project Category</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-category-revenue"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 4 - Opportunity Types -->
                <div class="chart-row">
                    <div class="card" data-widget="opportunity_types">
                        <div class="card-header">
                            <h3 class="card-title">Opportunity Types / Categories</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-opportunity-types"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
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
        let widgetPreferences = {};

        // Load widget preferences from localStorage
        function loadWidgetPreferences() {
            const saved = localStorage.getItem('analyticsWidgets');
            if (saved) {
                try {
                    widgetPreferences = JSON.parse(saved);
                } catch (e) {
                    widgetPreferences = {};
                }
            }

            // Apply saved preferences
            for (const [widgetId, visible] of Object.entries(widgetPreferences)) {
                const checkbox = document.getElementById('widget-' + widgetId);
                if (checkbox) {
                    checkbox.checked = visible;
                }
                const widget = document.querySelector('[data-widget="' + widgetId + '"]');
                if (widget) {
                    widget.style.display = visible ? '' : 'none';
                }
            }
        }

        function saveWidgetPreferences() {
            localStorage.setItem('analyticsWidgets', JSON.stringify(widgetPreferences));
        }

        function toggleWidget(widgetId) {
            const checkbox = document.getElementById('widget-' + widgetId);
            const visible = checkbox ? checkbox.checked : true;
            widgetPreferences[widgetId] = visible;

            const widgets = document.querySelectorAll('[data-widget="' + widgetId + '"]');
            widgets.forEach(widget => {
                widget.style.display = visible ? '' : 'none';
            });

            saveWidgetPreferences();
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadWidgetPreferences();
            loadAnalytics();

            // Reload when date range changes
            document.getElementById('date-range')?.addEventListener('change', loadAnalytics);

            // Widget panel toggle
            document.getElementById('widget-config-toggle')?.addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('widget-panel')?.classList.toggle('active');
            });

            // Close widget panel when clicking outside
            document.addEventListener('click', function(e) {
                const panel = document.getElementById('widget-panel');
                const toggle = document.getElementById('widget-config-toggle');
                if (panel && !panel.contains(e.target) && !toggle.contains(e.target)) {
                    panel.classList.remove('active');
                }
            });
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

            // Projects
            if (kpis.projects) {
                document.getElementById('kpi-projects').textContent = parseInt(kpis.projects.value || 0).toLocaleString();
                updateChange('kpi-proj-change', kpis.projects.change);
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

            const chartColors = ['#667eea', '#764ba2', '#10b981', '#f59e0b', '#3b82f6', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

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
                                backgroundColor: chartColors
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
                                backgroundColor: chartColors
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
            } else {
                // Show "No data" message for top products
                const ctx = document.getElementById('chart-top-products');
                if (ctx) {
                    ctx.parentElement.innerHTML = '<p class="text-muted text-center" style="padding: 100px 20px;">No product revenue data available yet</p>';
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
                                backgroundColor: chartColors
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

            // Project Categories
            if (chartsData.project_categories && chartsData.project_categories.labels.length > 0) {
                const ctx = document.getElementById('chart-project-categories');
                if (ctx) {
                    charts.projectCategories = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: chartsData.project_categories.labels,
                            datasets: [{
                                data: chartsData.project_categories.values,
                                backgroundColor: chartColors
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            } else {
                const ctx = document.getElementById('chart-project-categories');
                if (ctx) {
                    ctx.parentElement.innerHTML = '<p class="text-muted text-center" style="padding: 100px 20px;">No project category data available</p>';
                }
            }

            // Category Revenue
            if (chartsData.category_revenue && chartsData.category_revenue.labels.length > 0) {
                const ctx = document.getElementById('chart-category-revenue');
                if (ctx) {
                    charts.categoryRevenue = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: chartsData.category_revenue.labels,
                            datasets: [{
                                label: 'Revenue',
                                data: chartsData.category_revenue.values,
                                backgroundColor: chartColors
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: {
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
            } else {
                const ctx = document.getElementById('chart-category-revenue');
                if (ctx) {
                    ctx.parentElement.innerHTML = '<p class="text-muted text-center" style="padding: 100px 20px;">No category revenue data available</p>';
                }
            }

            // Opportunity Types
            if (chartsData.opportunity_types && chartsData.opportunity_types.labels.length > 0) {
                const ctx = document.getElementById('chart-opportunity-types');
                if (ctx) {
                    charts.opportunityTypes = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: chartsData.opportunity_types.labels,
                            datasets: [{
                                data: chartsData.opportunity_types.values,
                                backgroundColor: chartColors
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            } else {
                const ctx = document.getElementById('chart-opportunity-types');
                if (ctx) {
                    ctx.parentElement.innerHTML = '<p class="text-muted text-center" style="padding: 100px 20px;">No opportunity type data available</p>';
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
