<?php
/**
 * Report Builder Page
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireAuth();
Auth::requirePermission(Permissions::VIEW_REPORTS);

$pageTitle = 'Report Builder';
$selectedModule = $_GET['module'] ?? null;

// Get API client and report builder
$api = getApiClient();
$reportBuilder = $api ? new ReportBuilder($api) : null;
$modules = $reportBuilder ? $reportBuilder->getModules() : [];

// Load saved reports for the current user
$savedReports = ReportManager::getForUser(Auth::id());
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
                <?php if (!$api): ?>
                    <div class="alert alert-danger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M15 9l-6 6M9 9l6 6"/>
                        </svg>
                        <div>
                            <strong>API Not Configured</strong>
                            <p>Please configure your CurrentRMS API credentials in <a href="settings.php">Settings</a> to use the report builder.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="report-builder" id="report-builder">
                        <!-- Module Selection Sidebar -->
                        <div class="report-sidebar">
                            <div class="card-header">
                                <h3 class="card-title">Select Module</h3>
                            </div>
                            <div class="module-list">
                                <?php foreach ($modules as $key => $module): ?>
                                    <div class="module-item <?php echo $selectedModule === $key ? 'selected' : ''; ?>"
                                         data-module="<?php echo e($key); ?>">
                                        <div class="module-icon">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <?php
                                                $icons = [
                                                    'products' => '<path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>',
                                                    'members' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>',
                                                    'opportunities' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
                                                    'invoices' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/>',
                                                    'projects' => '<path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>',
                                                    'purchase_orders' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/>',
                                                    'stock_levels' => '<path d="M12.89 1.45l8 4A2 2 0 0122 7.24v9.53a2 2 0 01-1.11 1.79l-8 4a2 2 0 01-1.79 0l-8-4a2 2 0 01-1.1-1.8V7.24a2 2 0 011.11-1.79l8-4a2 2 0 011.78 0z"/>',
                                                    'quarantines' => '<path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
                                                ];
                                                echo $icons[$key] ?? '<circle cx="12" cy="12" r="10"/>';
                                                ?>
                                            </svg>
                                        </div>
                                        <div class="module-name"><?php echo e($module['name']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Saved Reports -->
                            <div class="card-header" style="border-top: 1px solid var(--gray-200);">
                                <h3 class="card-title">Saved Reports</h3>
                            </div>
                            <div style="padding: 16px; max-height: 300px; overflow-y: auto;">
                                <?php if (empty($savedReports)): ?>
                                    <p class="text-muted" style="font-size: 13px;">No saved reports yet.</p>
                                <?php else: ?>
                                    <?php foreach (array_slice($savedReports, 0, 10) as $report): ?>
                                        <a href="report-view.php?id=<?php echo $report['id']; ?>" class="module-item" style="padding: 8px;">
                                            <div class="module-name" style="font-size: 13px;">
                                                <?php echo e($report['name']); ?>
                                                <div class="text-muted" style="font-size: 11px;"><?php echo ucfirst($report['module']); ?></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Report Configuration & Preview -->
                        <div class="report-preview">
                            <div class="card-header">
                                <h3 class="card-title" id="preview-title">
                                    <?php if ($selectedModule && isset($modules[$selectedModule])): ?>
                                        <?php echo e($modules[$selectedModule]['name']); ?> Report
                                    <?php else: ?>
                                        Select a module to begin
                                    <?php endif; ?>
                                </h3>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn btn-secondary btn-sm" id="preview-report">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        Preview
                                    </button>
                                    <div class="dropdown">
                                        <button class="btn btn-secondary btn-sm dropdown-toggle">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                                            </svg>
                                            Export
                                        </button>
                                        <div class="dropdown-menu">
                                            <div class="dropdown-item" data-export="csv">Export CSV</div>
                                            <div class="dropdown-item" data-export="xlsx">Export Excel</div>
                                            <div class="dropdown-item" data-export="json">Export JSON</div>
                                            <div class="dropdown-divider"></div>
                                            <div class="dropdown-item" data-export="print">Print Report</div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-sm" id="save-report" data-modal="save-modal">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                                            <path d="M17 21v-8H7v8M7 3v5h8"/>
                                        </svg>
                                        Save
                                    </button>
                                </div>
                            </div>

                            <!-- Configuration Tabs -->
                            <div class="tabs" data-tab-group="report-config">
                                <div class="tab active" data-tab="columns">Columns</div>
                                <div class="tab" data-tab="filters">Filters</div>
                                <div class="tab" data-tab="sorting">Sorting</div>
                            </div>

                            <!-- Columns Tab -->
                            <div class="card-body" data-tab-group="report-config" data-tab="columns">
                                <div class="form-group">
                                    <label class="form-label">Select Columns to Include</label>
                                    <div id="column-list" class="checkbox-group" style="max-height: 200px; overflow-y: auto;">
                                        <?php if ($selectedModule && isset($modules[$selectedModule])): ?>
                                            <?php foreach ($modules[$selectedModule]['columns'] as $key => $col): ?>
                                                <label class="checkbox-item">
                                                    <input type="checkbox" class="checkbox-input column-checkbox" value="<?php echo e($key); ?>" checked>
                                                    <span><?php echo e($col['label']); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted">Select a module to see available columns.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Filters Tab -->
                            <div class="card-body hidden" data-tab-group="report-config" data-tab="filters">
                                <div class="form-group">
                                    <label class="form-label">Filter Conditions</label>
                                    <div id="filter-list" style="display: flex; flex-direction: column; gap: 12px;">
                                        <!-- Filters will be added here -->
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-filter">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 5v14M5 12h14"/>
                                        </svg>
                                        Add Filter
                                    </button>
                                </div>
                            </div>

                            <!-- Sorting Tab -->
                            <div class="card-body hidden" data-tab-group="report-config" data-tab="sorting">
                                <div class="form-group">
                                    <label class="form-label">Sort By</label>
                                    <div style="display: flex; gap: 12px;">
                                        <select class="form-control" id="sort-field" style="flex: 1;">
                                            <option value="">Select field...</option>
                                            <?php if ($selectedModule && isset($modules[$selectedModule])): ?>
                                                <?php foreach ($modules[$selectedModule]['columns'] as $key => $col): ?>
                                                    <option value="<?php echo e($key); ?>"><?php echo e($col['label']); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <select class="form-control" id="sort-direction" style="width: 150px;">
                                            <option value="asc">Ascending</option>
                                            <option value="desc">Descending</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Area -->
                            <div class="card-body" style="border-top: 1px solid var(--gray-200); min-height: 400px;">
                                <div id="report-preview">
                                    <div class="empty-state">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                            <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <h3 class="empty-state-title">Ready to Build</h3>
                                        <p class="empty-state-desc">Select a module, configure your columns and filters, then click Preview to see your report.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Save Report Modal -->
    <div class="modal-overlay" id="save-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Save Report</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="save-report-form" method="POST" action="api/reports/save.php">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="module" id="save-module" value="<?php echo e($selectedModule); ?>">
                    <input type="hidden" name="columns" id="save-columns" value="">
                    <input type="hidden" name="filters" id="save-filters" value="">
                    <input type="hidden" name="sorting" id="save-sorting" value="">

                    <div class="form-group">
                        <label class="form-label">Report Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., Monthly Revenue Report">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description (Optional)</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Brief description of this report..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-item">
                            <input type="checkbox" name="is_public" value="1" class="checkbox-input">
                            <span>Make this report visible to all users</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Report</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Set currency symbol from config
        window.APP_CURRENCY = '<?php echo e(config('app.currency_symbol') ?? '£'); ?>';
    </script>
    <script src="assets/js/app.js"></script>
    <script>
        // Initialize report builder
        document.addEventListener('DOMContentLoaded', function() {
            ReportBuilder.init();

            <?php if ($selectedModule): ?>
            ReportBuilder.selectModule('<?php echo e($selectedModule); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>
