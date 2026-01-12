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
    'products' => ['count' => 0, 'error' => null],
];

$recentInvoices = [];
$upcomingEvents = [];
$revenueData = ['labels' => [], 'values' => []];
$opportunityData = ['labels' => [], 'values' => []];

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
        // Get products count
        $prodResponse = $api->get('products', [
            'per_page' => 1,
            'filtermode' => 'active'
        ]);
        $stats['products']['count'] = $prodResponse['meta']['total_row_count'] ?? 0;
    } catch (Exception $e) {
        $stats['products']['error'] = $e->getMessage();
    }

    try {
        // Get recent invoices for the table
        $recentInvResponse = $api->get('invoices', [
            'per_page' => 5,
            'q[s]' => 'created_at desc'
        ]);
        $recentInvoices = $recentInvResponse['invoices'] ?? [];

        // Calculate monthly revenue from recent invoices
        $monthlyRevenue = 0;
        $currentMonth = date('Y-m');
        foreach ($recentInvResponse['invoices'] ?? [] as $inv) {
            $invMonth = substr($inv['invoice_date'] ?? '', 0, 7);
            if ($invMonth === $currentMonth) {
                $monthlyRevenue += floatval($inv['total'] ?? 0);
            }
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
        // Get opportunity status breakdown for pie chart
        $statusCounts = [];
        $allOppResponse = $api->get('opportunities', [
            'per_page' => 100,
            'filtermode' => 'active'
        ]);
        foreach ($allOppResponse['opportunities'] ?? [] as $opp) {
            $status = $opp['status'] ?? 'Unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        $opportunityData['labels'] = array_keys($statusCounts);
        $opportunityData['values'] = array_values($statusCounts);
    } catch (Exception $e) {
        // Silently fail for chart
    }

    try {
        // Get revenue by month for bar chart
        $monthlyData = [];
        $invForChart = $api->get('invoices', [
            'per_page' => 100,
            'q[s]' => 'invoice_date desc'
        ]);
        foreach ($invForChart['invoices'] ?? [] as $inv) {
            $month = date('M Y', strtotime($inv['invoice_date'] ?? 'now'));
            $monthlyData[$month] = ($monthlyData[$month] ?? 0) + floatval($inv['total'] ?? 0);
        }
        // Get last 6 months
        $monthlyData = array_slice(array_reverse($monthlyData), 0, 6);
        $revenueData['labels'] = array_keys($monthlyData);
        $revenueData['values'] = array_values($monthlyData);
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

                    <div class="stat-card">
                        <div class="stat-icon info">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Products in Stock</div>
                            <div class="stat-value">
                                <?php if ($stats['products']['error']): ?>
                                    <span class="text-danger" style="font-size: 14px;">Error</span>
                                <?php else: ?>
                                    <?php echo number_format($stats['products']['count']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid" id="dashboard-grid">
                    <!-- Revenue Chart -->
                    <div class="widget" style="grid-column: span 6;">
                        <div class="widget-header">
                            <span class="widget-title">Revenue by Month</span>
                        </div>
                        <div class="widget-body">
                            <div class="chart-container">
                                <?php if (empty($revenueData['values'])): ?>
                                    <div class="empty-state" style="padding: 40px 20px;">
                                        <p class="text-muted">No revenue data available</p>
                                    </div>
                                <?php else: ?>
                                    <canvas id="chart-revenue"></canvas>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Opportunities Chart -->
                    <div class="widget" style="grid-column: span 6;">
                        <div class="widget-header">
                            <span class="widget-title">Opportunities by Status</span>
                        </div>
                        <div class="widget-body">
                            <div class="chart-container">
                                <?php if (empty($opportunityData['values'])): ?>
                                    <div class="empty-state" style="padding: 40px 20px;">
                                        <p class="text-muted">No opportunity data available</p>
                                    </div>
                                <?php else: ?>
                                    <canvas id="chart-opportunities"></canvas>
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
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentInvoices as $invoice): ?>
                                                <tr>
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
                                                        <span class="badge <?php echo $badgeClass; ?>">
                                                            <?php echo ucfirst($state); ?>
                                                        </span>
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
            // Revenue Chart
            const revenueCtx = document.getElementById('chart-revenue');
            if (revenueCtx) {
                new Chart(revenueCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($revenueData['labels']); ?>,
                        datasets: [{
                            label: 'Revenue',
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

            <?php if (!empty($opportunityData['values'])): ?>
            // Opportunities Chart
            const oppCtx = document.getElementById('chart-opportunities');
            if (oppCtx) {
                new Chart(oppCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($opportunityData['labels']); ?>,
                        datasets: [{
                            data: <?php echo json_encode($opportunityData['values']); ?>,
                            backgroundColor: ['#10b981', '#667eea', '#f59e0b', '#9ca3af', '#ef4444', '#8b5cf6'],
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>
