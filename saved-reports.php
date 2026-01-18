<?php
/**
 * Saved Reports Management Page
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireAuth();
Auth::requirePermission(Permissions::VIEW_REPORTS);

$pageTitle = 'Saved Reports';
$user = Auth::user();
$isAdmin = Auth::can(Permissions::MANAGE_USERS);

// Get all reports for the current user (and public ones)
$reports = ReportManager::getForUser(Auth::id());

// Separate user's own reports and shared reports
$myReports = array_filter($reports, fn($r) => $r['user_id'] == Auth::id());
$sharedReports = array_filter($reports, fn($r) => $r['user_id'] != Auth::id() && $r['is_public']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo csrfToken(); ?>">
    <title><?php echo e($pageTitle); ?> - CurrentRMS Report Builder</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
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
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">My Reports</h3>
                        <a href="reports.php" class="btn btn-primary btn-sm">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            New Report
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($myReports)): ?>
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h3 class="empty-state-title">No Saved Reports</h3>
                                <p class="empty-state-desc">You haven't saved any reports yet. Create a new report to get started.</p>
                                <a href="reports.php" class="btn btn-primary">Create Report</a>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Module</th>
                                            <th>Description</th>
                                            <th>Visibility</th>
                                            <th>Created</th>
                                            <th>Last Updated</th>
                                            <th style="width: 180px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myReports as $report): ?>
                                            <tr data-report-id="<?php echo $report['id']; ?>">
                                                <td>
                                                    <a href="report-view.php?id=<?php echo $report['id']; ?>" class="font-weight-bold">
                                                        <?php echo e($report['name']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge badge-gray"><?php echo e(ucfirst($report['module'])); ?></span>
                                                </td>
                                                <td class="text-muted">
                                                    <?php echo e($report['description'] ? substr($report['description'], 0, 50) . (strlen($report['description']) > 50 ? '...' : '') : '-'); ?>
                                                </td>
                                                <td>
                                                    <?php if ($report['is_public']): ?>
                                                        <span class="badge badge-success">Public</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-gray">Private</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($report['created_at']); ?></td>
                                                <td><?php echo formatDate($report['updated_at']); ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 4px;">
                                                        <a href="report-view.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-secondary" title="View">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                                <circle cx="12" cy="12" r="3"/>
                                                            </svg>
                                                        </a>
                                                        <a href="reports.php?edit=<?php echo $report['id']; ?>" class="btn btn-sm btn-secondary" title="Edit">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                                <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                            </svg>
                                                        </a>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteReport(<?php echo $report['id']; ?>, '<?php echo e(addslashes($report['name'])); ?>')" title="Delete">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                                            </svg>
                                                        </button>
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

                <?php if (!empty($sharedReports)): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Shared Reports</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Module</th>
                                        <th>Description</th>
                                        <th>Owner</th>
                                        <th>Created</th>
                                        <th style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sharedReports as $report): ?>
                                        <tr>
                                            <td>
                                                <a href="report-view.php?id=<?php echo $report['id']; ?>" class="font-weight-bold">
                                                    <?php echo e($report['name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge badge-gray"><?php echo e(ucfirst($report['module'])); ?></span>
                                            </td>
                                            <td class="text-muted">
                                                <?php echo e($report['description'] ? substr($report['description'], 0, 50) . (strlen($report['description']) > 50 ? '...' : '') : '-'); ?>
                                            </td>
                                            <td><?php echo e($report['user_name'] ?? 'Unknown'); ?></td>
                                            <td><?php echo formatDate($report['created_at']); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 4px;">
                                                    <a href="report-view.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-secondary" title="View">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                            <circle cx="12" cy="12" r="3"/>
                                                        </svg>
                                                    </a>
                                                    <button class="btn btn-sm btn-secondary" onclick="duplicateReport(<?php echo $report['id']; ?>)" title="Duplicate to My Reports">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                                            <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        async function deleteReport(id, name) {
            if (!confirm(`Are you sure you want to delete the report "${name}"? This action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch('api/reports/delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ id: id })
                });

                const result = await response.json();
                if (result.success) {
                    // Remove row from table
                    const row = document.querySelector(`tr[data-report-id="${id}"]`);
                    if (row) {
                        row.remove();
                    }
                    App.showNotification('Report deleted successfully', 'success');
                } else {
                    alert('Error: ' + (result.error || 'Failed to delete report'));
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }

        async function duplicateReport(id) {
            try {
                const response = await fetch('api/reports/duplicate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ id: id })
                });

                const result = await response.json();
                if (result.success) {
                    App.showNotification('Report duplicated successfully', 'success');
                    // Reload page to show the new report
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Failed to duplicate report'));
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }
    </script>
</body>
</html>
