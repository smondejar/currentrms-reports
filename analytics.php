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
        /* Report Widget Modal */
        .report-widget-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .report-widget-modal.active {
            display: flex;
        }
        .report-widget-modal-content {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
        }
        .report-widget-modal h3 {
            margin: 0 0 20px 0;
            font-size: 18px;
        }
        .report-widget-modal .form-group {
            margin-bottom: 16px;
        }
        .report-widget-modal label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .report-widget-modal select,
        .report-widget-modal input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-size: 14px;
            background: var(--gray-50);
        }
        .report-widget-modal .btn-row {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        [data-theme="dark"] .report-widget-modal-content {
            background: #1f2937;
        }
        /* Report widget card */
        .report-widget-card {
            position: relative;
        }
        .report-widget-card .widget-actions {
            position: absolute;
            top: 12px;
            right: 12px;
            display: flex;
            gap: 8px;
        }
        .report-widget-card .widget-actions button {
            background: var(--gray-100);
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .report-widget-card .widget-actions button:hover {
            background: var(--gray-200);
        }
        .widget-stat-value {
            font-size: 36px;
            font-weight: bold;
            color: var(--primary);
            margin: 20px 0;
        }
        .widget-stat-label {
            color: var(--gray-500);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-overlay-text">Fetching Analytics Data</div>
        <div class="loading-overlay-subtext">Please wait while we load your data from CurrentRMS...</div>
    </div>

    <!-- Report Widget Modal -->
    <div class="report-widget-modal" id="report-widget-modal">
        <div class="report-widget-modal-content">
            <h3>Add Report Widget</h3>
            <form id="report-widget-form">
                <div class="form-group">
                    <label for="widget-report-id">Select Report</label>
                    <select id="widget-report-id" required>
                        <option value="">Loading reports...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="widget-type">Widget Type</label>
                    <select id="widget-type" required>
                        <option value="stat_card">Stat Card (Single Value)</option>
                        <option value="chart_bar">Bar Chart</option>
                        <option value="chart_line">Line Chart</option>
                        <option value="chart_pie">Pie Chart</option>
                        <option value="chart_doughnut">Doughnut Chart</option>
                        <option value="table">Data Table</option>
                    </select>
                </div>
                <div class="form-group" id="widget-aggregate-field-group" style="display: none;">
                    <label for="widget-aggregate-field">Value Field (for aggregation)</label>
                    <input type="text" id="widget-aggregate-field" placeholder="e.g., rental_charge_total">
                    <small class="text-muted">Field to aggregate (sum, count, etc.)</small>
                </div>
                <div class="form-group" id="widget-group-by-group" style="display: none;">
                    <label for="widget-group-by">Group By Field</label>
                    <input type="text" id="widget-group-by" placeholder="e.g., status_name">
                    <small class="text-muted">Field to group data by</small>
                </div>
                <div class="form-group" id="widget-aggregate-func-group" style="display: none;">
                    <label for="widget-aggregate-func">Aggregation Function</label>
                    <select id="widget-aggregate-func">
                        <option value="sum">Sum</option>
                        <option value="count">Count</option>
                        <option value="avg">Average</option>
                        <option value="min">Minimum</option>
                        <option value="max">Maximum</option>
                    </select>
                </div>
                <div class="form-group" id="widget-limit-group" style="display: none;">
                    <label for="widget-limit">Limit Results</label>
                    <input type="number" id="widget-limit" value="10" min="1" max="100">
                </div>
                <div class="btn-row">
                    <button type="button" class="btn btn-secondary" onclick="closeReportWidgetModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Widget</button>
                </div>
            </form>
        </div>
    </div>

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
                                    <input type="checkbox" id="widget-total_charges" checked onchange="toggleWidget('total_charges')">
                                    <label for="widget-total_charges">Total Charges</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-rental_charges" checked onchange="toggleWidget('rental_charges')">
                                    <label for="widget-rental_charges">Rental Charges</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-service_charges" checked onchange="toggleWidget('service_charges')">
                                    <label for="widget-service_charges">Service Charges</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-opportunities" checked onchange="toggleWidget('opportunities')">
                                    <label for="widget-opportunities">Active Opportunities</label>
                                </div>
                            </div>
                            <div class="widget-panel-section">
                                <h4>Charts</h4>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-charges_trend" checked onchange="toggleWidget('charges_trend')">
                                    <label for="widget-charges_trend">Charges Trend</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-opp_status" checked onchange="toggleWidget('opp_status')">
                                    <label for="widget-opp_status">Opportunities by Status</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-top_products" checked onchange="toggleWidget('top_products')">
                                    <label for="widget-top_products">Top Products by Charges</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-customer_segments" checked onchange="toggleWidget('customer_segments')">
                                    <label for="widget-customer_segments">Customer Segments</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-projects_by_category" checked onchange="toggleWidget('projects_by_category')">
                                    <label for="widget-projects_by_category">Projects by Category</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-charges_by_category" checked onchange="toggleWidget('charges_by_category')">
                                    <label for="widget-charges_by_category">Charges by Category</label>
                                </div>
                                <div class="widget-option">
                                    <input type="checkbox" id="widget-opportunity_types" checked onchange="toggleWidget('opportunity_types')">
                                    <label for="widget-opportunity_types">Opportunity Types</label>
                                </div>
                            </div>
                            <div class="widget-panel-section" id="saved-reports-section">
                                <h4>Saved Reports</h4>
                                <div id="saved-reports-list">
                                    <div class="text-muted" style="font-size: 12px;">Loading reports...</div>
                                </div>
                            </div>
                            <div class="widget-panel-section">
                                <button class="btn btn-sm btn-primary" style="width: 100%;" onclick="addReportWidget()">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line x1="12" y1="8" x2="12" y2="16"/>
                                        <line x1="8" y1="12" x2="16" y2="12"/>
                                    </svg>
                                    Add Report Widget
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="stat-cards" id="kpi-cards">
                    <div class="stat-card" data-widget="total_charges">
                        <div class="stat-icon primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Charges</div>
                            <div class="stat-value" id="kpi-total-charges">
                                <div class="spinner" style="width: 20px; height: 20px;"></div>
                            </div>
                            <div class="stat-change" id="kpi-total-charges-change"></div>
                        </div>
                    </div>

                    <div class="stat-card" data-widget="rental_charges">
                        <div class="stat-icon success">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                <path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Rental Charges</div>
                            <div class="stat-value" id="kpi-rental-charges">
                                <div class="spinner" style="width: 20px; height: 20px;"></div>
                            </div>
                            <div class="stat-change" id="kpi-rental-charges-change"></div>
                        </div>
                    </div>

                    <div class="stat-card" data-widget="service_charges">
                        <div class="stat-icon warning">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Service Charges</div>
                            <div class="stat-value" id="kpi-service-charges">
                                <div class="spinner" style="width: 20px; height: 20px;"></div>
                            </div>
                            <div class="stat-change" id="kpi-service-charges-change"></div>
                        </div>
                    </div>

                    <div class="stat-card" data-widget="opportunities">
                        <div class="stat-icon info">
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
                </div>

                <!-- Charts Row 1 -->
                <div class="chart-row">
                    <div class="card" data-widget="charges_trend">
                        <div class="card-header">
                            <h3 class="card-title">Charges Trend</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-charges-trend"></canvas>
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
                    <div class="card" data-widget="projects_by_category">
                        <div class="card-header">
                            <h3 class="card-title">Projects by Category</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-project-categories"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card" data-widget="charges_by_category">
                        <div class="card-header">
                            <h3 class="card-title">Charges by Project Category</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="chart-category-charges"></canvas>
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

                <!-- Report Widgets Container -->
                <div id="report-widgets-container" class="chart-row" style="display: none;">
                    <!-- Dynamic report widgets will be added here -->
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

        function showLoading(show = true) {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) {
                if (show) {
                    overlay.classList.remove('hidden');
                } else {
                    overlay.classList.add('hidden');
                }
            }
        }

        async function loadAnalytics() {
            const days = document.getElementById('date-range')?.value || 30;
            showLoading(true);

            try {
                const response = await fetch(`api/analytics.php?days=${days}`);

                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const text = await response.text();
                console.log('Analytics raw response:', text.substring(0, 500));

                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError, 'Response:', text.substring(0, 200));
                    throw new Error('Invalid JSON response from server');
                }

                if (data.error) {
                    App.showNotification(data.error, 'error');
                    showLoading(false);
                    return;
                }

                updateKPIs(data.kpis || {});
                updateCharts(data.charts || {});
                updateTimeline(data.timeline || []);

            } catch (error) {
                console.error('Failed to load analytics:', error);
                App.showNotification('Failed to load analytics: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        }

        function updateKPIs(kpis) {
            // Total Charges
            if (kpis.total_charges) {
                document.getElementById('kpi-total-charges').textContent = currencySymbol + parseFloat(kpis.total_charges.value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                updateChange('kpi-total-charges-change', kpis.total_charges.change);
            }

            // Rental Charges
            if (kpis.rental_charges) {
                document.getElementById('kpi-rental-charges').textContent = currencySymbol + parseFloat(kpis.rental_charges.value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                updateChange('kpi-rental-charges-change', kpis.rental_charges.change);
            }

            // Service Charges
            if (kpis.service_charges) {
                document.getElementById('kpi-service-charges').textContent = currencySymbol + parseFloat(kpis.service_charges.value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                updateChange('kpi-service-charges-change', kpis.service_charges.change);
            }

            // Opportunities
            if (kpis.opportunities) {
                document.getElementById('kpi-opportunities').textContent = parseInt(kpis.opportunities.value || 0).toLocaleString();
                updateChange('kpi-opp-change', kpis.opportunities.change);
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

            // Charges Trend
            if (chartsData.charges_trend) {
                const ctx = document.getElementById('chart-charges-trend');
                if (ctx) {
                    charts.charges = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartsData.charges_trend.labels,
                            datasets: [{
                                label: 'Charges',
                                data: chartsData.charges_trend.values,
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

            // Project Categories (uses projects_by_category from API)
            if (chartsData.projects_by_category && chartsData.projects_by_category.labels.length > 0) {
                const ctx = document.getElementById('chart-project-categories');
                if (ctx) {
                    charts.projectCategories = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: chartsData.projects_by_category.labels,
                            datasets: [{
                                data: chartsData.projects_by_category.values,
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

            // Charges by Category (uses charges_by_category from API)
            if (chartsData.charges_by_category && chartsData.charges_by_category.labels.length > 0) {
                const ctx = document.getElementById('chart-category-charges');
                if (ctx) {
                    charts.categoryCharges = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: chartsData.charges_by_category.labels,
                            datasets: [{
                                label: 'Charges',
                                data: chartsData.charges_by_category.values,
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
                const ctx = document.getElementById('chart-category-charges');
                if (ctx) {
                    ctx.parentElement.innerHTML = '<p class="text-muted text-center" style="padding: 100px 20px;">No category charges data available</p>';
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

        // Report Widget Functions
        let savedReports = [];
        let reportWidgets = [];
        let reportWidgetCharts = {};

        // Load saved reports for widget selection
        async function loadSavedReports() {
            try {
                const response = await fetch('api/dashboard/list-reports.php');
                const data = await response.json();

                if (data.error) {
                    document.getElementById('saved-reports-list').innerHTML =
                        '<div class="text-muted" style="font-size: 12px;">Failed to load reports</div>';
                    return;
                }

                savedReports = data.reports || [];

                // Update the saved reports list in widget panel
                const listContainer = document.getElementById('saved-reports-list');
                if (savedReports.length === 0) {
                    listContainer.innerHTML = '<div class="text-muted" style="font-size: 12px;">No saved reports yet</div>';
                } else {
                    listContainer.innerHTML = savedReports.slice(0, 5).map(report => `
                        <div class="widget-option" style="font-size: 13px;">
                            <span style="flex: 1;">${escapeHtml(report.name)}</span>
                            <button type="button" class="btn btn-xs" onclick="quickAddReportWidget(${report.id})" title="Add as widget">+</button>
                        </div>
                    `).join('');
                }

                // Update the modal select
                const select = document.getElementById('widget-report-id');
                select.innerHTML = '<option value="">Select a report...</option>' +
                    savedReports.map(r => `<option value="${r.id}" data-module="${r.module}">${escapeHtml(r.name)} (${r.module})</option>`).join('');

            } catch (error) {
                console.error('Failed to load saved reports:', error);
                document.getElementById('saved-reports-list').innerHTML =
                    '<div class="text-muted" style="font-size: 12px;">Failed to load reports</div>';
            }
        }

        // Load saved widgets from localStorage
        function loadReportWidgets() {
            const saved = localStorage.getItem('reportWidgets');
            if (saved) {
                try {
                    reportWidgets = JSON.parse(saved);
                    renderReportWidgets();
                } catch (e) {
                    reportWidgets = [];
                }
            }
        }

        function saveReportWidgets() {
            localStorage.setItem('reportWidgets', JSON.stringify(reportWidgets));
        }

        // Quick add a report widget with default settings
        function quickAddReportWidget(reportId) {
            const report = savedReports.find(r => r.id === reportId);
            if (!report) return;

            const widget = {
                id: 'rw_' + Date.now(),
                reportId: reportId,
                reportName: report.name,
                type: 'stat_card',
                aggregateField: '',
                groupBy: '',
                aggregateFunc: 'count',
                limit: 10
            };

            reportWidgets.push(widget);
            saveReportWidgets();
            renderReportWidgets();
            loadReportWidgetData(widget);
            App.showNotification('Widget added', 'success');
        }

        // Open modal to add report widget
        function addReportWidget() {
            document.getElementById('widget-panel').classList.remove('active');
            document.getElementById('report-widget-modal').classList.add('active');
            updateModalFields();
        }

        function closeReportWidgetModal() {
            document.getElementById('report-widget-modal').classList.remove('active');
            document.getElementById('report-widget-form').reset();
        }

        // Show/hide fields based on widget type
        function updateModalFields() {
            const type = document.getElementById('widget-type').value;
            const isChart = type.startsWith('chart_');
            const isStatCard = type === 'stat_card';
            const isTable = type === 'table';

            document.getElementById('widget-aggregate-field-group').style.display = (isChart || isStatCard) ? 'block' : 'none';
            document.getElementById('widget-group-by-group').style.display = isChart ? 'block' : 'none';
            document.getElementById('widget-aggregate-func-group').style.display = (isChart || isStatCard) ? 'block' : 'none';
            document.getElementById('widget-limit-group').style.display = (isChart || isTable) ? 'block' : 'none';
        }

        // Handle widget type change
        document.getElementById('widget-type')?.addEventListener('change', updateModalFields);

        // Handle form submission
        document.getElementById('report-widget-form')?.addEventListener('submit', function(e) {
            e.preventDefault();

            const reportId = parseInt(document.getElementById('widget-report-id').value);
            const report = savedReports.find(r => r.id === reportId);
            if (!report) {
                App.showNotification('Please select a report', 'error');
                return;
            }

            const widget = {
                id: 'rw_' + Date.now(),
                reportId: reportId,
                reportName: report.name,
                type: document.getElementById('widget-type').value,
                aggregateField: document.getElementById('widget-aggregate-field').value,
                groupBy: document.getElementById('widget-group-by').value,
                aggregateFunc: document.getElementById('widget-aggregate-func').value,
                limit: parseInt(document.getElementById('widget-limit').value) || 10
            };

            reportWidgets.push(widget);
            saveReportWidgets();
            closeReportWidgetModal();
            renderReportWidgets();
            loadReportWidgetData(widget);
            App.showNotification('Widget added', 'success');
        });

        // Render all report widgets
        function renderReportWidgets() {
            const container = document.getElementById('report-widgets-container');
            if (!container) return;

            if (reportWidgets.length === 0) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'grid';
            container.innerHTML = reportWidgets.map(widget => {
                const isChart = widget.type.startsWith('chart_');
                const isTable = widget.type === 'table';
                const isStatCard = widget.type === 'stat_card';

                return `
                    <div class="card report-widget-card" id="widget-${widget.id}" data-widget-id="${widget.id}">
                        <div class="widget-actions">
                            <button type="button" class="widget-refresh-btn" data-widget-id="${widget.id}" title="Refresh">↻</button>
                            <button type="button" class="widget-remove-btn" data-widget-id="${widget.id}" title="Remove">×</button>
                        </div>
                        <div class="card-header">
                            <h3 class="card-title">${escapeHtml(widget.reportName)}</h3>
                        </div>
                        <div class="card-body">
                            ${isStatCard ? `
                                <div class="widget-stat-value" id="stat-${widget.id}">
                                    <div class="spinner" style="width: 20px; height: 20px;"></div>
                                </div>
                                <div class="widget-stat-label" id="stat-label-${widget.id}">Loading...</div>
                            ` : ''}
                            ${isChart ? `
                                <div class="chart-container" style="height: 300px;">
                                    <canvas id="chart-${widget.id}"></canvas>
                                </div>
                            ` : ''}
                            ${isTable ? `
                                <div class="table-responsive" id="table-${widget.id}" style="max-height: 300px; overflow-y: auto;">
                                    <div class="text-center"><div class="spinner"></div></div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');

            // Attach event listeners using event delegation
            container.querySelectorAll('.widget-refresh-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const widgetId = this.getAttribute('data-widget-id');
                    refreshReportWidget(widgetId);
                });
            });

            container.querySelectorAll('.widget-remove-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const widgetId = this.getAttribute('data-widget-id');
                    removeReportWidget(widgetId);
                });
            });

            // Load data for all widgets
            reportWidgets.forEach(loadReportWidgetData);
        }

        // Load data for a single widget
        async function loadReportWidgetData(widget) {
            try {
                const params = new URLSearchParams({
                    report_id: widget.reportId,
                    type: widget.type,
                    limit: widget.limit
                });

                if (widget.aggregateField) {
                    params.append('aggregate_field', widget.aggregateField);
                }
                if (widget.groupBy) {
                    params.append('group_by', widget.groupBy);
                }
                if (widget.aggregateFunc) {
                    params.append('aggregate_func', widget.aggregateFunc);
                }

                const response = await fetch(`api/dashboard/report-widget.php?${params}`);
                const data = await response.json();

                if (data.error) {
                    console.error('Widget error:', data.error);
                    showWidgetError(widget.id, data.error);
                    return;
                }

                renderWidgetData(widget, data);
            } catch (error) {
                console.error('Failed to load widget data:', error);
                showWidgetError(widget.id, error.message);
            }
        }

        function showWidgetError(widgetId, message) {
            const card = document.getElementById('widget-' + widgetId);
            if (card) {
                const body = card.querySelector('.card-body');
                if (body) {
                    body.innerHTML = `<div class="text-muted text-center" style="padding: 40px 20px;">Error: ${escapeHtml(message)}</div>`;
                }
            }
        }

        function renderWidgetData(widget, data) {
            if (widget.type === 'stat_card') {
                const valueEl = document.getElementById('stat-' + widget.id);
                const labelEl = document.getElementById('stat-label-' + widget.id);
                if (valueEl) {
                    const value = data.value ?? data.count ?? 0;
                    const isMonetary = widget.aggregateField &&
                        (widget.aggregateField.includes('charge') || widget.aggregateField.includes('price') || widget.aggregateField.includes('cost'));
                    valueEl.textContent = isMonetary
                        ? currencySymbol + parseFloat(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})
                        : parseFloat(value).toLocaleString();
                }
                if (labelEl) {
                    labelEl.textContent = widget.aggregateFunc === 'count'
                        ? `${data.count} records`
                        : `${widget.aggregateFunc} of ${widget.aggregateField || 'records'}`;
                }
            } else if (widget.type.startsWith('chart_')) {
                renderWidgetChart(widget, data);
            } else if (widget.type === 'table') {
                renderWidgetTable(widget, data);
            }
        }

        function renderWidgetChart(widget, data) {
            const ctx = document.getElementById('chart-' + widget.id);
            if (!ctx || !data.chart) return;

            // Destroy existing chart if any
            if (reportWidgetCharts[widget.id]) {
                reportWidgetCharts[widget.id].destroy();
            }

            const chartType = widget.type.replace('chart_', '');
            const chartColors = ['#667eea', '#764ba2', '#10b981', '#f59e0b', '#3b82f6', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

            const config = {
                type: chartType,
                data: {
                    labels: data.chart.labels,
                    datasets: [{
                        label: widget.reportName,
                        data: data.chart.values,
                        backgroundColor: chartType === 'line' ? 'rgba(102, 126, 234, 0.1)' : chartColors,
                        borderColor: chartType === 'line' ? '#667eea' : undefined,
                        fill: chartType === 'line',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: chartType === 'pie' || chartType === 'doughnut', position: 'bottom' }
                    }
                }
            };

            if (chartType === 'bar') {
                config.options.indexAxis = 'y';
            }

            reportWidgetCharts[widget.id] = new Chart(ctx, config);
        }

        function renderWidgetTable(widget, data) {
            const container = document.getElementById('table-' + widget.id);
            if (!container || !data.data) return;

            if (data.data.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">No data available</p>';
                return;
            }

            const columns = data.columns || Object.keys(data.data[0] || {});
            const displayCols = columns.slice(0, 5); // Limit columns for widget

            let html = '<table class="table table-sm"><thead><tr>';
            displayCols.forEach(col => {
                html += `<th>${escapeHtml(col)}</th>`;
            });
            html += '</tr></thead><tbody>';

            data.data.forEach(row => {
                html += '<tr>';
                displayCols.forEach(col => {
                    const value = row[col] ?? '';
                    html += `<td>${escapeHtml(String(value))}</td>`;
                });
                html += '</tr>';
            });

            html += '</tbody></table>';
            if (data.total > data.data.length) {
                html += `<div class="text-muted text-center" style="font-size: 12px;">Showing ${data.data.length} of ${data.total}</div>`;
            }

            container.innerHTML = html;
        }

        function refreshReportWidget(widgetId) {
            const widget = reportWidgets.find(w => w.id === widgetId);
            if (widget) {
                loadReportWidgetData(widget);
            }
        }

        function removeReportWidget(widgetId) {
            if (!confirm('Remove this widget?')) return;

            // Destroy chart if exists
            if (reportWidgetCharts[widgetId]) {
                reportWidgetCharts[widgetId].destroy();
                delete reportWidgetCharts[widgetId];
            }

            reportWidgets = reportWidgets.filter(w => w.id !== widgetId);
            saveReportWidgets();
            renderReportWidgets();
        }

        // Close modal when clicking outside
        document.getElementById('report-widget-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeReportWidgetModal();
            }
        });

        // Initialize report widgets on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSavedReports();
            loadReportWidgets();
        });
    </script>
</body>
</html>
