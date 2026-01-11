<?php
/**
 * API: Export Report Data
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    die('Unauthorized');
}

// Check permission
if (!Auth::can(Permissions::EXPORT_REPORTS)) {
    http_response_code(403);
    die('Permission denied');
}

// Get API client
$api = getApiClient();
if (!$api) {
    http_response_code(500);
    die('API not configured');
}

// Get request data
$module = $_GET['module'] ?? null;
$columns = isset($_GET['columns']) ? explode(',', $_GET['columns']) : [];
$filters = isset($_GET['filters']) ? json_decode($_GET['filters'], true) : [];
$format = $_GET['format'] ?? 'csv';
$reportName = $_GET['name'] ?? 'Report';

if (!$module) {
    http_response_code(400);
    die('Module is required');
}

try {
    $builder = new ReportBuilder($api);
    $builder->setModule($module);

    if (!empty($columns)) {
        $builder->setColumns($columns);
    }

    if (!empty($filters)) {
        $builder->setFilters($filters);
    }

    // Get all data for export
    $data = $builder->fetchAll(config('app.max_export_rows'));

    // Get module config for column labels
    $moduleConfig = $builder->getModule($module);
    $columnConfig = $moduleConfig['columns'];
    $exportColumns = !empty($columns) ? $columns : array_keys($columnConfig);

    // Generate filename
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $reportName) . '_' . date('Y-m-d');

    switch ($format) {
        case 'csv':
            $content = Exporter::toCSV($data, $exportColumns, $columnConfig);
            Exporter::downloadHeaders($filename . '.csv', 'text/csv; charset=UTF-8');
            break;

        case 'xlsx':
        case 'excel':
            $content = Exporter::toExcelXML($data, $exportColumns, $columnConfig, $reportName);
            Exporter::downloadHeaders($filename . '.xls', 'application/vnd.ms-excel');
            break;

        case 'json':
            $content = Exporter::toJSON($data, $exportColumns);
            Exporter::downloadHeaders($filename . '.json', 'application/json');
            break;

        case 'html':
        case 'print':
            $content = Exporter::toHTML($data, $exportColumns, $columnConfig, $reportName);
            header('Content-Type: text/html; charset=UTF-8');
            break;

        case 'pdf':
            try {
                $html = Exporter::toHTML($data, $exportColumns, $columnConfig, $reportName);
                $content = Exporter::toPDF($html);
                Exporter::downloadHeaders($filename . '.pdf', 'application/pdf');
            } catch (Exception $e) {
                // Fallback to HTML for print
                $content = Exporter::toHTML($data, $exportColumns, $columnConfig, $reportName);
                header('Content-Type: text/html; charset=UTF-8');
            }
            break;

        default:
            http_response_code(400);
            die('Invalid format');
    }

    echo $content;

} catch (Exception $e) {
    http_response_code(500);
    die('Export failed: ' . $e->getMessage());
}
