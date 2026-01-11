<?php
/**
 * View/Run Saved Report
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireAuth();
Auth::requirePermission(Permissions::VIEW_REPORTS);

$reportId = (int) ($_GET['id'] ?? 0);
$report = ReportManager::get($reportId);

if (!$report) {
    flash('error', 'Report not found.');
    header('Location: reports.php');
    exit;
}

// Check access
if (!ReportManager::canAccess($reportId, Auth::id(), Auth::isAdmin())) {
    http_response_code(403);
    die('Access denied');
}

$pageTitle = $report['name'];
$api = getApiClient();
$data = [];
$meta = [];
$error = null;

// Execute report if API is configured
if ($api) {
    try {
        $builder = new ReportBuilder($api);
        $builder->setModule($report['module']);

        $config = $report['config'];

        if (!empty($config['columns'])) {
            $builder->setColumns($config['columns']);
        }

        if (!empty($config['filters'])) {
            $builder->setFilters($config['filters']);
        }

        if (!empty($config['sorting'])) {
            $builder->setSorting($config['sorting']['field'], $config['sorting']['direction'] ?? 'asc');
        }

        $page = (int) ($_GET['page'] ?? 1);
        $builder->setPagination($page, 25);

        $result = $builder->execute();
        $data = $result['data'];
        $meta = $result['meta'];
        $columns = $result['columns'];
        $columnConfig = $result['column_config'];

        // Update run count
        $prefix = Database::getPrefix();
        Database::query("UPDATE {$prefix}reports SET run_count = run_count + 1, last_run = NOW() WHERE id = ?", [$reportId]);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$moduleConfig = $api ? (new ReportBuilder($api))->getModule($report['module']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo csrfToken(); ?>">
    <title><?php echo e($pageTitle); ?> - CurrentRMS Report Builder</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include 'includes/partials/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/partials/header.php'; ?>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><?php echo e($report['name']); ?></h3>
                            <?php if ($report['description']): ?>
                                <p class="text-muted" style="margin-top: 4px; font-size: 13px;"><?php echo e($report['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <div class="dropdown">
                                <button class="btn btn-secondary btn-sm dropdown-toggle">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                                    </svg>
                                    Export
                                </button>
                                <div class="dropdown-menu">
                                    <a href="api/reports/export.php?module=<?php echo e($report['module']); ?>&columns=<?php echo e(implode(',', $config['columns'] ?? [])); ?>&filters=<?php echo urlencode(json_encode($config['filters'] ?? [])); ?>&format=csv&name=<?php echo urlencode($report['name']); ?>" class="dropdown-item">Export CSV</a>
                                    <a href="api/reports/export.php?module=<?php echo e($report['module']); ?>&columns=<?php echo e(implode(',', $config['columns'] ?? [])); ?>&filters=<?php echo urlencode(json_encode($config['filters'] ?? [])); ?>&format=xlsx&name=<?php echo urlencode($report['name']); ?>" class="dropdown-item">Export Excel</a>
                                    <a href="api/reports/export.php?module=<?php echo e($report['module']); ?>&columns=<?php echo e(implode(',', $config['columns'] ?? [])); ?>&filters=<?php echo urlencode(json_encode($config['filters'] ?? [])); ?>&format=json&name=<?php echo urlencode($report['name']); ?>" class="dropdown-item">Export JSON</a>
                                    <div class="dropdown-divider"></div>
                                    <a href="api/reports/export.php?module=<?php echo e($report['module']); ?>&columns=<?php echo e(implode(',', $config['columns'] ?? [])); ?>&filters=<?php echo urlencode(json_encode($config['filters'] ?? [])); ?>&format=print&name=<?php echo urlencode($report['name']); ?>" class="dropdown-item" target="_blank">Print Report</a>
                                </div>
                            </div>
                            <?php if (ReportManager::canEdit($reportId, Auth::id(), Auth::isAdmin())): ?>
                                <a href="reports.php?module=<?php echo e($report['module']); ?>&load=<?php echo $reportId; ?>" class="btn btn-secondary btn-sm">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                    Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Report Info -->
                    <div class="filter-bar">
                        <div class="filter-group">
                            <span class="filter-label">Module:</span>
                            <span class="badge badge-primary"><?php echo e($moduleConfig['name'] ?? ucfirst($report['module'])); ?></span>
                        </div>
                        <?php if (!empty($config['filters'])): ?>
                            <div class="filter-group">
                                <span class="filter-label">Filters:</span>
                                <?php foreach ($config['filters'] as $filter): ?>
                                    <span class="filter-tag">
                                        <?php echo e($filter['field']); ?> <?php echo e($filter['predicate']); ?> <?php echo e($filter['value']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="filter-group" style="margin-left: auto;">
                            <span class="text-muted" style="font-size: 13px;">
                                <?php echo e($meta['total'] ?? 0); ?> records found
                            </span>
                        </div>
                    </div>

                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo e($error); ?></div>
                        <?php elseif (!$api): ?>
                            <div class="alert alert-warning">API not configured. Please configure your CurrentRMS API in Settings.</div>
                        <?php elseif (empty($data)): ?>
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h3 class="empty-state-title">No Data Found</h3>
                                <p class="empty-state-desc">No records match the current filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <?php foreach ($columns as $col): ?>
                                                <th><?php echo e($columnConfig[$col]['label'] ?? $col); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data as $row): ?>
                                            <tr>
                                                <?php foreach ($columns as $col): ?>
                                                    <?php
                                                    $value = $row[$col] ?? '';
                                                    $type = $columnConfig[$col]['type'] ?? 'string';
                                                    $formatted = $value;

                                                    if ($type === 'currency' && is_numeric($value)) {
                                                        $formatted = formatCurrency($value);
                                                    } elseif ($type === 'date' && $value) {
                                                        $formatted = formatDate($value);
                                                    } elseif ($type === 'datetime' && $value) {
                                                        $formatted = formatDateTime($value);
                                                    }
                                                    ?>
                                                    <td class="<?php echo in_array($type, ['currency', 'number']) ? 'text-right' : ''; ?>">
                                                        <?php echo e($formatted); ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($meta['total_pages'] > 1): ?>
                                <div class="pagination">
                                    <?php if ($meta['page'] > 1): ?>
                                        <a href="?id=<?php echo $reportId; ?>&page=<?php echo $meta['page'] - 1; ?>" class="pagination-btn">Previous</a>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $meta['page'] - 2); $i <= min($meta['total_pages'], $meta['page'] + 2); $i++): ?>
                                        <a href="?id=<?php echo $reportId; ?>&page=<?php echo $i; ?>"
                                           class="pagination-btn <?php echo $i === $meta['page'] ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($meta['page'] < $meta['total_pages']): ?>
                                        <a href="?id=<?php echo $reportId; ?>&page=<?php echo $meta['page'] + 1; ?>" class="pagination-btn">Next</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
