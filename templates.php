<?php
/**
 * Report Templates Page
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireAuth();
Auth::requirePermission(Permissions::VIEW_REPORTS);

$pageTitle = 'Report Templates';
$api = getApiClient();

// Get all templates grouped by category
$templates = ReportTemplates::getAll();
$categories = ReportTemplates::getCategories();

// Check if running a specific template
$runTemplate = $_GET['run'] ?? null;
$templateData = $runTemplate ? ReportTemplates::get($runTemplate) : null;

$currencySymbol = config('app.currency_symbol') ?? '£';

// SVG icons for templates
$icons = [
    'trending-up' => '<path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
    'bar-chart-2' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
    'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
    'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'alert-circle' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    'git-branch' => '<line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 01-9 9"/>',
    'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    'package' => '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
    'alert-triangle' => '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    'x-circle' => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
    'shopping-cart' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/>',
    'folder' => '<path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>',
];

// Category colors
$categoryColors = [
    'Revenue' => 'primary',
    'Finance' => 'success',
    'Sales' => 'warning',
    'Inventory' => 'info',
    'Operations' => 'gray',
    'Procurement' => 'danger',
];
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
                <?php if (!$api): ?>
                    <div class="alert alert-warning">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <strong>API Not Configured</strong>
                            <p>Please configure your CurrentRMS API credentials in <a href="settings.php">Settings</a> to use report templates.</p>
                        </div>
                    </div>
                <?php elseif ($templateData): ?>
                    <!-- Running a specific template -->
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title"><?php echo e($templateData['name']); ?></h3>
                                <p class="text-muted" style="margin: 4px 0 0 0; font-size: 13px;"><?php echo e($templateData['description']); ?></p>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <a href="templates.php" class="btn btn-secondary btn-sm">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                                    </svg>
                                    Back to Templates
                                </a>
                                <div class="dropdown">
                                    <button class="btn btn-secondary btn-sm dropdown-toggle">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                                        </svg>
                                        Export
                                    </button>
                                    <div class="dropdown-menu">
                                        <div class="dropdown-item" onclick="exportTemplate('csv')">Export CSV</div>
                                        <div class="dropdown-item" onclick="exportTemplate('xlsx')">Export Excel</div>
                                        <div class="dropdown-item" onclick="exportTemplate('json')">Export JSON</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body" id="template-results">
                            <div class="loading">
                                <div class="spinner"></div>
                                <p>Loading report data...</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Template Gallery -->
                    <div class="card-header" style="background: none; padding: 0 0 24px 0; border: none;">
                        <div>
                            <h2 style="margin: 0;">Report Templates</h2>
                            <p class="text-muted" style="margin: 4px 0 0 0;">Pre-built reports for common business insights. Click any template to run it instantly.</p>
                        </div>
                        <a href="reports.php" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            Custom Report
                        </a>
                    </div>

                    <?php foreach ($categories as $category): ?>
                        <div class="mb-4">
                            <h3 style="margin-bottom: 16px; font-size: 16px; font-weight: 600; color: var(--gray-700);">
                                <?php echo e($category); ?>
                            </h3>
                            <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
                                <?php foreach ($templates as $key => $template): ?>
                                    <?php if ($template['category'] === $category): ?>
                                        <a href="templates.php?run=<?php echo e($key); ?>" class="card" style="text-decoration: none; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='none'; this.style.boxShadow='none'">
                                            <div class="card-body" style="padding: 20px;">
                                                <div style="display: flex; align-items: flex-start; gap: 16px;">
                                                    <div class="stat-icon <?php echo $categoryColors[$category] ?? 'primary'; ?>" style="flex-shrink: 0; width: 48px; height: 48px;">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 24px; height: 24px;">
                                                            <?php echo $icons[$template['icon']] ?? '<circle cx="12" cy="12" r="10"/>'; ?>
                                                        </svg>
                                                    </div>
                                                    <div style="flex: 1; min-width: 0;">
                                                        <h4 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 600; color: var(--gray-900);">
                                                            <?php echo e($template['name']); ?>
                                                        </h4>
                                                        <p style="margin: 0; font-size: 13px; color: var(--gray-600); line-height: 1.4;">
                                                            <?php echo e($template['description']); ?>
                                                        </p>
                                                        <div style="margin-top: 12px;">
                                                            <span class="badge badge-gray"><?php echo e(ucfirst($template['module'])); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        window.APP_CURRENCY = '<?php echo e($currencySymbol); ?>';
    </script>
    <script src="assets/js/app.js"></script>
    <?php if ($runTemplate && $templateData): ?>
    <script>
        const templateKey = '<?php echo e($runTemplate); ?>';
        const currencySymbol = window.APP_CURRENCY || '£';
        let currentPage = 1;
        let perPage = 25;
        let totalPages = 1;

        document.addEventListener('DOMContentLoaded', function() {
            loadTemplateData();
        });

        async function loadTemplateData(page = 1) {
            currentPage = page;
            const container = document.getElementById('template-results');

            try {
                const response = await fetch(`api/templates.php?action=run&key=${templateKey}&page=${page}&per_page=${perPage}`);
                const data = await response.json();

                if (data.error) {
                    container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                    return;
                }

                if (!data.data || data.data.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="empty-state-title">No Data Found</h3>
                            <p class="empty-state-desc">This report returned no results. Try adjusting the filters or check if the data exists in CurrentRMS.</p>
                        </div>
                    `;
                    return;
                }

                totalPages = data.meta?.total_pages || 1;
                renderTable(data, container);

            } catch (error) {
                container.innerHTML = `<div class="alert alert-danger">Failed to load report: ${error.message}</div>`;
            }
        }

        function renderTable(data, container) {
            const columns = data.columns || Object.keys(data.data[0]);
            const columnConfig = data.column_config || {};

            let html = '<div class="table-container"><table class="table"><thead><tr>';
            columns.forEach(col => {
                html += `<th>${columnConfig[col]?.label || col}</th>`;
            });
            html += '</tr></thead><tbody>';

            data.data.forEach(row => {
                html += '<tr>';
                columns.forEach(col => {
                    const value = row[col] ?? '';
                    const type = columnConfig[col]?.type || 'string';
                    html += `<td>${formatValue(value, type)}</td>`;
                });
                html += '</tr>';
            });

            html += '</tbody></table></div>';

            // Pagination
            if (data.meta) {
                const startRecord = ((currentPage - 1) * perPage) + 1;
                const endRecord = Math.min(currentPage * perPage, data.meta.total);

                html += `
                    <div class="pagination-controls" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-top: 1px solid var(--gray-200); margin-top: 16px;">
                        <span class="text-muted">
                            Showing ${startRecord}-${endRecord} of ${data.meta.total} records
                        </span>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button class="btn btn-sm btn-secondary" onclick="loadTemplateData(1)" ${currentPage <= 1 ? 'disabled' : ''}>
                                &laquo; First
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="loadTemplateData(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}>
                                &lsaquo; Prev
                            </button>
                            <span style="padding: 0 12px;">
                                Page ${currentPage} of ${totalPages}
                            </span>
                            <button class="btn btn-sm btn-secondary" onclick="loadTemplateData(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}>
                                Next &rsaquo;
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="loadTemplateData(${totalPages})" ${currentPage >= totalPages ? 'disabled' : ''}>
                                Last &raquo;
                            </button>
                            <select class="form-control" style="width: 80px; margin-left: 16px;" onchange="perPage = parseInt(this.value); loadTemplateData(1);">
                                <option value="10" ${perPage === 10 ? 'selected' : ''}>10</option>
                                <option value="25" ${perPage === 25 ? 'selected' : ''}>25</option>
                                <option value="50" ${perPage === 50 ? 'selected' : ''}>50</option>
                                <option value="100" ${perPage === 100 ? 'selected' : ''}>100</option>
                            </select>
                        </div>
                    </div>
                `;
            }

            container.innerHTML = html;
        }

        function formatValue(value, type) {
            if (value === null || value === undefined || value === '') return '';
            if (typeof value === 'object') return '';

            switch (type) {
                case 'currency':
                    const numVal = parseFloat(value);
                    if (isNaN(numVal)) return '';
                    return currencySymbol + numVal.toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                case 'number':
                    const num = parseFloat(value);
                    if (isNaN(num)) return '';
                    return num.toLocaleString();
                case 'date':
                    if (!value) return '';
                    const dateVal = new Date(value);
                    if (isNaN(dateVal.getTime())) return String(value);
                    return dateVal.toLocaleDateString();
                case 'datetime':
                    if (!value) return '';
                    const dtVal = new Date(value);
                    if (isNaN(dtVal.getTime())) return String(value);
                    return dtVal.toLocaleString();
                case 'boolean':
                    return value ? 'Yes' : 'No';
                default:
                    return String(value).substring(0, 100);
            }
        }

        function exportTemplate(format) {
            const params = new URLSearchParams({
                module: '<?php echo e($templateData['module']); ?>',
                columns: <?php echo json_encode($templateData['columns']); ?>.join(','),
                filters: JSON.stringify(<?php echo json_encode($templateData['filters']); ?>),
                format: format,
            });

            window.location.href = `api/reports/export.php?${params.toString()}`;
        }
    </script>
    <?php endif; ?>
</body>
</html>
