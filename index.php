<?php
/**
 * Dashboard / Home Page
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Check installation
if (!file_exists(__DIR__ . '/.installed')) {
    header('Location: install/');
    exit;
}

// Require authentication
Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'Dashboard';

// Get dashboard data
$dashboard = Dashboard::getForUser(Auth::id());

// Check if API is configured
$apiConfigured = isApiConfigured();
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
                            <div class="stat-value" id="stat-opportunities">--</div>
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
                            <div class="stat-value" id="stat-revenue">--</div>
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
                            <div class="stat-value" id="stat-invoices">--</div>
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
                            <div class="stat-value" id="stat-products">--</div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid" id="dashboard-grid">
                    <!-- Widgets will be loaded dynamically -->
                    <div class="widget" style="grid-column: span 6;">
                        <div class="widget-header">
                            <span class="widget-title">Revenue by Month</span>
                        </div>
                        <div class="widget-body">
                            <div class="chart-container">
                                <canvas id="chart-revenue"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="widget" style="grid-column: span 6;">
                        <div class="widget-header">
                            <span class="widget-title">Opportunities by Status</span>
                        </div>
                        <div class="widget-body">
                            <div class="chart-container">
                                <canvas id="chart-opportunities"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="widget" style="grid-column: span 6;">
                        <div class="widget-header">
                            <span class="widget-title">Upcoming Events</span>
                        </div>
                        <div class="widget-body">
                            <div class="timeline" id="timeline-events">
                                <div class="text-muted text-center">Loading...</div>
                            </div>
                        </div>
                    </div>

                    <div class="widget" style="grid-column: span 6;">
                        <div class="widget-header">
                            <span class="widget-title">Recent Invoices</span>
                            <a href="reports.php?module=invoices" class="btn btn-sm btn-secondary">View All</a>
                        </div>
                        <div class="widget-body">
                            <div class="table-container" id="recent-invoices">
                                <div class="text-muted text-center">Loading...</div>
                            </div>
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

    <script src="assets/js/app.js"></script>
    <script>
        // Initialize demo charts
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue Chart
            const revenueCtx = document.getElementById('chart-revenue');
            if (revenueCtx) {
                new Chart(revenueCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Revenue',
                            data: [12000, 19000, 15000, 25000, 22000, 30000],
                            backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // Opportunities Chart
            const oppCtx = document.getElementById('chart-opportunities');
            if (oppCtx) {
                new Chart(oppCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Confirmed', 'Provisional', 'Quote Sent', 'Draft'],
                        datasets: [{
                            data: [45, 25, 20, 10],
                            backgroundColor: ['#10b981', '#667eea', '#f59e0b', '#9ca3af'],
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }

            // Load timeline
            document.getElementById('timeline-events').innerHTML = `
                <div class="timeline-item">
                    <div class="timeline-date">Tomorrow</div>
                    <div class="timeline-title">Equipment pickup - ABC Corp</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-date">Jan 15</div>
                    <div class="timeline-title">Wedding Setup - Grand Hotel</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-date">Jan 18</div>
                    <div class="timeline-title">Conference Equipment - Tech Inc</div>
                </div>
            `;

            // Load recent invoices
            document.getElementById('recent-invoices').innerHTML = `
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <tr><td>INV-001</td><td>ABC Corp</td><td>$2,500.00</td><td><span class="badge badge-success">Paid</span></td></tr>
                        <tr><td>INV-002</td><td>XYZ Ltd</td><td>$1,850.00</td><td><span class="badge badge-warning">Pending</span></td></tr>
                        <tr><td>INV-003</td><td>Tech Inc</td><td>$3,200.00</td><td><span class="badge badge-danger">Overdue</span></td></tr>
                    </tbody>
                </table>
            `;
        });
    </script>
</body>
</html>
