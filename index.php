<?php
/**
 * Dashboard / Home Page
 */

// Check installation FIRST before loading bootstrap
if (!file_exists(__DIR__ . '/.installed')) {
    header('Location: install/');
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';

// Require authentication
Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'Dashboard';

// Get dashboard data
$dashboard = Dashboard::getForUser(Auth::id());

// Check if API is configured
$apiConfigured = isApiConfigured();
$api = getApiClient();

// Fetch live stats if API is configured
$stats = [
    'opportunities' => ['count' => 0, 'error' => null],
    'revenue' => ['value' => 0, 'error' => null],
    'invoices' => ['count' => 0, 'error' => null],
];

$recentInvoices = [];
$upcomingEvents = [];
$revenueData = ['labels' => [], 'values' => []];

if ($api) {
    try {
        // Get active opportunities count
        $oppResponse = $api->get('opportunities', [
            'per_page' => 1,
            'filtermode' => 'active'
        ]);
        $stats['opportunities']['count'] = $oppResponse['meta']['total_row_count'] ?? 0;
    } catch (Exception $e) {
        $stats['opportunities']['error'] = $e->getMessage();
    }

    try {
        // Get pending invoices count
        $invResponse = $api->get('invoices', [
            'per_page' => 1,
            'q[state_eq]' => 'sent'
        ]);
        $stats['invoices']['count'] = $invResponse['meta']['total_row_count'] ?? 0;
    } catch (Exception $e) {
        $stats['invoices']['error'] = $e->getMessage();
    }

    try {
        // Get recent invoices for the table
        $recentInvResponse = $api->get('invoices', [
            'per_page' => 5,
            'q[s]' => 'created_at desc'
        ]);
        $recentInvoices = $recentInvResponse['invoices'] ?? [];
    } catch (Exception $e) {
        // Silently fail for invoices table
    }

    try {
        // Calculate monthly revenue from opportunities (more reliable than invoices)
        $currentMonthStart = date('Y-m-01');
        $currentMonthEnd = date('Y-m-t');
        $monthlyOpps = $api->get('opportunities', [
            'per_page' => 100,
            'q[starts_at_gteq]' => $currentMonthStart,
            'q[starts_at_lteq]' => $currentMonthEnd,
        ]);

        $monthlyRevenue = 0;
        foreach ($monthlyOpps['opportunities'] ?? [] as $opp) {
            // Try multiple field paths for total value
            $total = 0;
            if (isset($opp['totals']['charge_total'])) {
                $total = floatval($opp['totals']['charge_total']);
            } elseif (isset($opp['totals']['grand_total'])) {
                $total = floatval($opp['totals']['grand_total']);
            } elseif (isset($opp['charge_total'])) {
                $total = floatval($opp['charge_total']);
            } elseif (isset($opp['grand_total'])) {
                $total = floatval($opp['grand_total']);
            } elseif (isset($opp['rental_charge_total'])) {
                $total = floatval($opp['rental_charge_total']);
            }
            $monthlyRevenue += $total;
        }
        $stats['revenue']['value'] = $monthlyRevenue;
    } catch (Exception $e) {
        $stats['revenue']['error'] = $e->getMessage();
    }

    try {
        // Get upcoming opportunities for timeline
        $today = date('Y-m-d');
        $upcomingResponse = $api->get('opportunities', [
            'per_page' => 5,
            'q[starts_at_gteq]' => $today,
            'q[s]' => 'starts_at asc',
            'filtermode' => 'active'
        ]);
        $upcomingEvents = $upcomingResponse['opportunities'] ?? [];
    } catch (Exception $e) {
        // Silently fail for timeline
    }

    try {
        // Get revenue by month for bar chart (from opportunities - more reliable)
        $monthlyData = [];
        $oppsForChart = $api->get('opportunities', [
            'per_page' => 200,
            'q[starts_at_gteq]' => date('Y-m-d', strtotime('-6 months')),
            'q[s]' => 'starts_at asc'
        ]);
        foreach ($oppsForChart['opportunities'] ?? [] as $opp) {
            $date = $opp['starts_at'] ?? $opp['created_at'] ?? null;
            if ($date) {
                $month = date('M Y', strtotime($date));
                // Try multiple field paths for total value
                $total = 0;
                if (isset($opp['totals']['charge_total'])) {
                    $total = floatval($opp['totals']['charge_total']);
                } elseif (isset($opp['totals']['grand_total'])) {
                    $total = floatval($opp['totals']['grand_total']);
                } elseif (isset($opp['charge_total'])) {
                    $total = floatval($opp['charge_total']);
                } elseif (isset($opp['grand_total'])) {
                    $total = floatval($opp['grand_total']);
                } elseif (isset($opp['rental_charge_total'])) {
                    $total = floatval($opp['rental_charge_total']);
                }
                $monthlyData[$month] = ($monthlyData[$month] ?? 0) + $total;
            }
        }
        // Ensure we have last 6 months in order
        $orderedMonths = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('M Y', strtotime("-{$i} months"));
            $orderedMonths[$month] = $monthlyData[$month] ?? 0;
        }
        $revenueData['labels'] = array_keys($orderedMonths);
        $revenueData['values'] = array_values($orderedMonths);
    } catch (Exception $e) {
        // Silently fail for chart
    }
}
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
</head>
<body>
    <div class="app-layout">
        <?php include 'includes/partials/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/partials/header.php'; ?>

            <div class="content">
                <?php if (!$apiConfigured): ?>
                    <div class="alert alert-warning">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <strong>API Not Configured</strong>
                            <p>Please configure your CurrentRMS API credentials in <a href="settings.php">Settings</a> to fetch live data.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="stat-cards">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 6v6l4 2"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Active Opportunities</div>
                            <div class="stat-value">
                                <?php if ($stats['opportunities']['error']): ?>
                                    <span class="text-danger" style="font-size: 14px;">Error</span>
                                <?php else: ?>
                                    <?php echo number_format($stats['opportunities']['count']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Monthly Revenue</div>
                            <div class="stat-value">
                                <?php if ($stats['revenue']['error']): ?>
                                    <span class="text-danger" style="font-size: 14px;">Error</span>
                                <?php else: ?>
                                    <?php echo formatCurrency($stats['revenue']['value']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Pending Invoices</div>
                            <div class="stat-value">
                                <?php if ($stats['invoices']['error']): ?>
                                    <span class="text-danger" style="font-size: 14px;">Error</span>
                                <?php else: ?>
                                    <?php echo number_format($stats['invoices']['count']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid" id="dashboard-grid">
                    <!-- Charges by Month Chart -->
                    <div class="widget" style="grid-column: span 12;">
                        <div class="widget-header">
                            <span class="widget-title">Charges by Month</span>
                        </div>
                        <div class="widget-body">
                            <div class="chart-container">
                                <?php if (empty($revenueData['values'])): ?>
                                    <div class="empty-state" style="padding: 40px 20px;">
                                        <p class="text-muted">No charge data available</p>
                                    </div>
                                <?php else: ?>
                                    <canvas id="chart-charges"></canvas>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="widget" style="grid-column: span 6;">
                        <div class="widget-header">
                            <span class="widget-title">Upcoming Events</span>
                        </div>
                        <div class="widget-body">
                            <?php if (empty($upcomingEvents)): ?>
                                <div class="empty-state" style="padding: 40px 20px;">
                                    <p class="text-muted">No upcoming events</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($upcomingEvents as $event): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-date">
                                                <?php echo formatDate($event['starts_at'] ?? '', 'M j, Y'); ?>
                                            </div>
                                            <div class="timeline-title">
                                                <?php echo e($event['subject'] ?? 'Untitled'); ?>
                                            </div>
                                            <?php if (!empty($event['member']['name'])): ?>
                                                <div class="timeline-desc">
                                                    <?php echo e($event['member']['name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Invoices -->
                    <div class="widget" style="grid-column: span 6;">
                        <div class="widget-header">
                            <span class="widget-title">Recent Invoices</span>
                            <a href="reports.php?module=invoices" class="btn btn-sm btn-secondary">View All</a>
                        </div>
                        <div class="widget-body">
                            <?php if (empty($recentInvoices)): ?>
                                <div class="empty-state" style="padding: 40px 20px;">
                                    <p class="text-muted">No invoices found</p>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th>Customer</th>
                                                <th class="text-right">Amount</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentInvoices as $invoice): ?>
                                                <tr data-invoice-id="<?php echo $invoice['id']; ?>">
                                                    <td><?php echo e($invoice['number'] ?? $invoice['id']); ?></td>
                                                    <td><?php echo e($invoice['member']['name'] ?? 'N/A'); ?></td>
                                                    <td class="text-right"><?php echo formatCurrency($invoice['total'] ?? 0); ?></td>
                                                    <td>
                                                        <?php
                                                        $state = $invoice['state'] ?? 'draft';
                                                        $badgeClass = match($state) {
                                                            'paid' => 'badge-success',
                                                            'sent', 'approved' => 'badge-warning',
                                                            'void' => 'badge-danger',
                                                            default => 'badge-gray'
                                                        };
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?>" id="invoice-status-<?php echo $invoice['id']; ?>">
                                                            <?php echo ucfirst($state); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" style="display: flex; gap: 4px;">
                                                            <?php if ($state === 'draft'): ?>
                                                                <button class="btn btn-sm btn-primary" onclick="issueInvoice(<?php echo $invoice['id']; ?>)" title="Issue Invoice">
                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                                                                    </svg>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($state === 'sent' || $state === 'approved'): ?>
                                                                <button class="btn btn-sm btn-success" onclick="markInvoicePaid(<?php echo $invoice['id']; ?>)" title="Mark as Paid">
                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <path d="M20 6L9 17l-5-5"/>
                                                                    </svg>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($state === 'paid'): ?>
                                                                <span class="text-muted" style="font-size: 11px;">Completed</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Report Widgets Section -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Report Widgets</h3>
                        <button class="btn btn-sm btn-primary" onclick="showAddWidgetModal()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            Add Widget
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="report-widgets-container" class="dashboard-grid" style="grid-template-columns: repeat(12, 1fr);">
                            <p class="text-muted" id="no-widgets-msg">No report widgets added yet. Click "Add Widget" to add a report-based widget to your dashboard.</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Reports -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Recent Reports</h3>
                        <a href="reports.php" class="btn btn-sm btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            New Report
                        </a>
                    </div>
                    <div class="card-body">
                        <?php
                        $recentReports = ReportManager::getRecent(Auth::id(), 5);
                        if (empty($recentReports)):
                        ?>
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h3 class="empty-state-title">No Reports Yet</h3>
                                <p class="empty-state-desc">Create your first report to start analyzing your CurrentRMS data.</p>
                                <a href="reports.php" class="btn btn-primary">Create Report</a>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Module</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentReports as $report): ?>
                                            <tr>
                                                <td>
                                                    <a href="report-view.php?id=<?php echo $report['id']; ?>">
                                                        <?php echo e($report['name']); ?>
                                                    </a>
                                                </td>
                                                <td><span class="badge badge-gray"><?php echo e(ucfirst($report['module'])); ?></span></td>
                                                <td><?php echo formatDate($report['created_at']); ?></td>
                                                <td>
                                                    <a href="report-view.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

    <!-- Add Widget Modal -->
    <div class="modal-overlay" id="add-widget-modal" style="display: none;">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">Add Report Widget</h3>
                <button class="modal-close" onclick="hideAddWidgetModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Report</label>
                    <select id="widget-report-select" class="form-control" onchange="onReportSelected()">
                        <option value="">-- Select a report --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Widget Type</label>
                    <select id="widget-type-select" class="form-control">
                        <option value="table">Table</option>
                        <option value="chart_bar">Bar Chart</option>
                        <option value="chart_line">Line Chart</option>
                        <option value="chart_pie">Pie Chart</option>
                        <option value="stat_card">Stat Card</option>
                    </select>
                </div>
                <div id="widget-chart-options" style="display: none;">
                    <div class="form-group">
                        <label>Group By Field</label>
                        <select id="widget-group-by" class="form-control">
                            <option value="">-- Select field --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Aggregate Field (for values)</label>
                        <select id="widget-aggregate-field" class="form-control">
                            <option value="">Count (rows)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Aggregate Function</label>
                        <select id="widget-aggregate-func" class="form-control">
                            <option value="count">Count</option>
                            <option value="sum">Sum</option>
                            <option value="avg">Average</option>
                            <option value="min">Minimum</option>
                            <option value="max">Maximum</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Limit (rows/items)</label>
                    <input type="number" id="widget-limit" class="form-control" value="10" min="1" max="100">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="hideAddWidgetModal()">Cancel</button>
                <button class="btn btn-primary" onclick="addReportWidget()">Add Widget</button>
            </div>
        </div>
    </div>
        </div>
    </div>
</div>

    <script>
        // Set currency symbol from config
        window.APP_CURRENCY = '<?php echo e(config('app.currency_symbol') ?? '£'); ?>';
    </script>
    <script src="assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currencySymbol = window.APP_CURRENCY || '£';

            <?php if (!empty($revenueData['values'])): ?>
            // Charges by Month Chart
            const chargesCtx = document.getElementById('chart-charges');
            if (chargesCtx) {
                new Chart(chargesCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($revenueData['labels']); ?>,
                        datasets: [{
                            label: 'Charges',
                            data: <?php echo json_encode($revenueData['values']); ?>,
                            backgroundColor: 'rgba(102, 126, 234, 0.8)',
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
            <?php endif; ?>
        });

        // Get CSRF token helper
        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            const token = meta ? meta.getAttribute('content') : '';
            console.log('CSRF Token from meta:', token ? token.substring(0, 10) + '...' : 'EMPTY');
            return token;
        }

        // Invoice Actions
        async function issueInvoice(invoiceId) {
            if (!confirm('Issue this invoice? This will send it to the customer.')) return;

            const csrfToken = getCsrfToken();
            if (!csrfToken) {
                alert('Error: CSRF token not found. Please refresh the page.');
                return;
            }

            try {
                const response = await fetch('api/invoices/action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ invoice_id: invoiceId, action: 'issue' })
                });

                const result = await response.json();
                if (result.success) {
                    // Update the status badge
                    const badge = document.getElementById('invoice-status-' + invoiceId);
                    if (badge) {
                        badge.textContent = 'Sent';
                        badge.className = 'badge badge-warning';
                    }
                    // Reload page to update buttons
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Failed to issue invoice') + (result.hint ? ' (' + result.hint + ')' : ''));
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }

        async function markInvoicePaid(invoiceId) {
            if (!confirm('Mark this invoice as paid?')) return;

            const csrfToken = getCsrfToken();
            if (!csrfToken) {
                alert('Error: CSRF token not found. Please refresh the page.');
                return;
            }

            try {
                const response = await fetch('api/invoices/action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ invoice_id: invoiceId, action: 'mark_paid' })
                });

                const result = await response.json();
                if (result.success) {
                    // Update the status badge
                    const badge = document.getElementById('invoice-status-' + invoiceId);
                    if (badge) {
                        badge.textContent = 'Paid';
                        badge.className = 'badge badge-success';
                    }
                    // Reload page to update buttons
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Failed to mark invoice as paid') + (result.hint ? ' (' + result.hint + ')' : ''));
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }

        // Report Widget Functions
        let availableReports = [];
        let savedWidgets = JSON.parse(localStorage.getItem('dashboard_report_widgets') || '[]');
        let widgetIdCounter = savedWidgets.length > 0 ? Math.max(...savedWidgets.map(w => w.id || 0)) + 1 : 1;

        // Load saved widgets on page load and setup event handlers
        document.addEventListener('DOMContentLoaded', function() {
            loadSavedWidgets();

            // Setup widget type change handler once
            const widgetTypeSelect = document.getElementById('widget-type-select');
            if (widgetTypeSelect) {
                widgetTypeSelect.addEventListener('change', function() {
                    const chartTypes = ['chart_bar', 'chart_line', 'chart_pie', 'stat_card'];
                    document.getElementById('widget-chart-options').style.display =
                        chartTypes.includes(this.value) ? 'block' : 'none';
                });
            }
        });

        async function showAddWidgetModal() {
            const modal = document.getElementById('add-widget-modal');
            if (!modal) {
                console.error('Add widget modal not found');
                alert('Error: Widget modal not found');
                return;
            }
            modal.style.display = 'flex';

            // Reset form
            document.getElementById('widget-report-select').value = '';
            document.getElementById('widget-type-select').value = 'table';
            document.getElementById('widget-chart-options').style.display = 'none';
            document.getElementById('widget-group-by').innerHTML = '<option value="">-- Select field --</option>';
            document.getElementById('widget-aggregate-field').innerHTML = '<option value="">Count (rows)</option>';
            document.getElementById('widget-limit').value = '10';

            // Load available reports
            try {
                console.log('Fetching reports from api/dashboard/list-reports.php...');
                const response = await fetch('api/dashboard/list-reports.php', {
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const text = await response.text();
                console.log('Raw response:', text.substring(0, 200));

                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    alert('Error: Server returned invalid JSON. Check console for details.');
                    return;
                }

                if (data.success) {
                    availableReports = data.reports || [];
                    const select = document.getElementById('widget-report-select');
                    select.innerHTML = '<option value="">-- Select a report --</option>';

                    if (availableReports.length === 0) {
                        select.innerHTML = '<option value="">No reports available - create a report first</option>';
                    } else {
                        availableReports.forEach(report => {
                            const option = document.createElement('option');
                            option.value = report.id;
                            option.textContent = `${report.name} (${report.module})`;
                            option.dataset.module = report.module;
                            option.dataset.config = JSON.stringify(report.config || {});
                            select.appendChild(option);
                        });
                    }
                    console.log('Loaded', availableReports.length, 'reports');
                } else {
                    console.error('API error:', data.error);
                    alert('Error loading reports: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error('Failed to load reports:', e);
                alert('Error loading reports: ' + e.message);
            }
        }

        function hideAddWidgetModal() {
            document.getElementById('add-widget-modal').style.display = 'none';
        }

        async function onReportSelected() {
            const select = document.getElementById('widget-report-select');
            const reportId = select.value;
            if (!reportId) return;

            const option = select.options[select.selectedIndex];
            const module = option.dataset.module;

            // Load module columns for group by / aggregate options
            try {
                const response = await fetch(`api/modules.php?module=${module}&action=columns`);
                const data = await response.json();
                if (data.columns) {
                    const groupBySelect = document.getElementById('widget-group-by');
                    const aggregateSelect = document.getElementById('widget-aggregate-field');

                    groupBySelect.innerHTML = '<option value="">-- Select field --</option>';
                    aggregateSelect.innerHTML = '<option value="">Count (rows)</option>';

                    data.columns.forEach(col => {
                        const opt1 = document.createElement('option');
                        opt1.value = col.key;
                        opt1.textContent = col.label;
                        groupBySelect.appendChild(opt1);

                        if (col.type === 'number' || col.type === 'currency') {
                            const opt2 = document.createElement('option');
                            opt2.value = col.key;
                            opt2.textContent = col.label;
                            aggregateSelect.appendChild(opt2);
                        }
                    });
                }
            } catch (e) {
                console.error('Failed to load columns:', e);
            }
        }

        async function addReportWidget() {
            const reportId = document.getElementById('widget-report-select').value;
            const widgetType = document.getElementById('widget-type-select').value;
            const groupBy = document.getElementById('widget-group-by').value;
            const aggregateField = document.getElementById('widget-aggregate-field').value;
            const aggregateFunc = document.getElementById('widget-aggregate-func').value;
            const limit = document.getElementById('widget-limit').value;

            if (!reportId) {
                alert('Please select a report');
                return;
            }

            const report = availableReports.find(r => r.id == reportId);
            if (!report) return;

            const widget = {
                id: widgetIdCounter++,
                reportId: reportId,
                reportName: report.name,
                module: report.module,
                type: widgetType,
                groupBy: groupBy,
                aggregateField: aggregateField,
                aggregateFunc: aggregateFunc,
                limit: limit
            };

            savedWidgets.push(widget);
            localStorage.setItem('dashboard_report_widgets', JSON.stringify(savedWidgets));

            hideAddWidgetModal();
            renderWidget(widget);
        }

        function loadSavedWidgets() {
            if (savedWidgets.length > 0) {
                document.getElementById('no-widgets-msg').style.display = 'none';
                savedWidgets.forEach(widget => renderWidget(widget));
            }
        }

        async function renderWidget(widget) {
            document.getElementById('no-widgets-msg').style.display = 'none';
            const container = document.getElementById('report-widgets-container');

            const widgetEl = document.createElement('div');
            widgetEl.className = 'widget';
            widgetEl.style.gridColumn = 'span 6';
            widgetEl.id = `widget-${widget.id}`;
            widgetEl.innerHTML = `
                <div class="widget-header">
                    <span class="widget-title">${escapeHtml(widget.reportName)}</span>
                    <button class="btn btn-sm btn-secondary" onclick="removeWidget(${widget.id})" title="Remove">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="widget-body">
                    <div class="loading-placeholder"><div class="spinner"></div></div>
                </div>
            `;
            container.appendChild(widgetEl);

            // Fetch widget data
            try {
                const params = new URLSearchParams({
                    report_id: widget.reportId,
                    type: widget.type,
                    limit: widget.limit
                });
                if (widget.groupBy) params.append('group_by', widget.groupBy);
                if (widget.aggregateField) params.append('aggregate_field', widget.aggregateField);
                if (widget.aggregateFunc) params.append('aggregate_func', widget.aggregateFunc);

                console.log('Fetching widget data:', `api/dashboard/report-widget.php?${params}`);
                const response = await fetch(`api/dashboard/report-widget.php?${params}`, {
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const text = await response.text();
                console.log('Widget response:', text.substring(0, 200));

                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }

                const body = widgetEl.querySelector('.widget-body');
                if (data.error) {
                    body.innerHTML = `<p class="text-danger">${escapeHtml(data.error)}</p>`;
                    return;
                }

                // Render based on widget type
                if (widget.type === 'stat_card') {
                    body.innerHTML = `<div class="stat-value" style="font-size: 2rem; text-align: center; padding: 20px;">${formatValue(data.value, widget.aggregateField)}</div>`;
                } else if (widget.type.startsWith('chart_')) {
                    if (data.chart && data.chart.labels) {
                        const chartId = `chart-widget-${widget.id}`;
                        body.innerHTML = `<div class="chart-container"><canvas id="${chartId}"></canvas></div>`;
                        const chartType = widget.type.replace('chart_', '');
                        new Chart(document.getElementById(chartId), {
                            type: chartType === 'pie' ? 'pie' : chartType,
                            data: {
                                labels: data.chart.labels,
                                datasets: [{
                                    label: widget.reportName,
                                    data: data.chart.values,
                                    backgroundColor: chartType === 'pie' ?
                                        ['#10b981', '#667eea', '#f59e0b', '#9ca3af', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899'] :
                                        'rgba(102, 126, 234, 0.8)',
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: chartType === 'pie', position: 'bottom' } }
                            }
                        });
                    } else {
                        body.innerHTML = '<p class="text-muted">No data available</p>';
                    }
                } else {
                    // Table
                    if (data.data && data.data.length > 0) {
                        const columns = data.columns || Object.keys(data.data[0]);
                        let html = '<div class="table-container"><table class="table"><thead><tr>';
                        columns.forEach(col => html += `<th>${escapeHtml(col)}</th>`);
                        html += '</tr></thead><tbody>';
                        data.data.forEach(row => {
                            html += '<tr>';
                            columns.forEach(col => html += `<td>${escapeHtml(String(row[col] ?? ''))}</td>`);
                            html += '</tr>';
                        });
                        html += '</tbody></table></div>';
                        body.innerHTML = html;
                    } else {
                        body.innerHTML = '<p class="text-muted">No data available</p>';
                    }
                }
            } catch (e) {
                const body = widgetEl.querySelector('.widget-body');
                body.innerHTML = `<p class="text-danger">Error loading widget: ${escapeHtml(e.message)}</p>`;
            }
        }

        function removeWidget(widgetId) {
            if (!confirm('Remove this widget?')) return;
            savedWidgets = savedWidgets.filter(w => w.id !== widgetId);
            localStorage.setItem('dashboard_report_widgets', JSON.stringify(savedWidgets));
            const el = document.getElementById(`widget-${widgetId}`);
            if (el) el.remove();
            if (savedWidgets.length === 0) {
                document.getElementById('no-widgets-msg').style.display = 'block';
            }
        }

        function formatValue(value, field) {
            if (value === null || value === undefined) return '-';
            if (typeof value === 'number') {
                const currencySymbol = window.APP_CURRENCY || '£';
                if (field && (field.includes('total') || field.includes('charge') || field.includes('price') || field.includes('cost') || field.includes('revenue'))) {
                    return currencySymbol + value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
                return value.toLocaleString();
            }
            return String(value);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
