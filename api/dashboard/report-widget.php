<?php
/**
 * API: Dashboard Report Widget
 * Fetches saved report data formatted for dashboard widgets
 */

// Start output buffering to catch any errors
ob_start();

// Suppress display errors for JSON output
$displayErrors = ini_get('display_errors');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/bootstrap.php';

// Clear any buffered output (warnings, etc.)
ob_end_clean();

header('Content-Type: application/json');

// Restore display errors setting
ini_set('display_errors', $displayErrors);

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get parameters
$reportId = (int) ($_GET['report_id'] ?? 0);
$widgetType = $_GET['type'] ?? 'table'; // table, chart_bar, chart_line, chart_pie, stat_card
$limit = min((int) ($_GET['limit'] ?? 10), 100);
$aggregateField = $_GET['aggregate_field'] ?? null;
$groupByField = $_GET['group_by'] ?? null;
$aggregateFunc = $_GET['aggregate_func'] ?? 'sum'; // sum, count, avg, min, max

if (!$reportId) {
    http_response_code(400);
    echo json_encode(['error' => 'Report ID is required']);
    exit;
}

try {
    $report = ReportManager::get($reportId);

    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit;
    }

    // Check access
    $userId = Auth::user()['id'];
    $isAdmin = Auth::can(Permissions::MANAGE_USERS);
    if (!ReportManager::canAccess($reportId, $userId, $isAdmin)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Get API client
    $api = getApiClient();
    if (!$api) {
        http_response_code(500);
        echo json_encode(['error' => 'API not configured']);
        exit;
    }

    // Build report
    $builder = new ReportBuilder($api);
    $builder->setModule($report['module']);

    // Apply saved config (already decoded by ReportManager::get)
    $config = $report['config'] ?? [];

    if (!empty($config['columns'])) {
        $builder->setColumns($config['columns']);
    }

    if (!empty($config['filters'])) {
        foreach ($config['filters'] as $filter) {
            $builder->addFilter($filter['field'], $filter['predicate'], $filter['value']);
        }
    }

    if (!empty($config['sorting'])) {
        $builder->setSorting($config['sorting']['field'], $config['sorting']['direction'] ?? 'asc');
    }

    // Fetch data based on widget type
    $result = [
        'report' => [
            'id' => $report['id'],
            'name' => $report['name'],
            'module' => $report['module'],
        ],
        'widget_type' => $widgetType,
    ];

    // Buffer any output during data fetching
    ob_start();

    if ($widgetType === 'stat_card') {
        // For stat cards, we need to aggregate a single value
        $data = $builder->fetchAll(1000);
        ob_end_clean(); // Clear any warnings

        if ($aggregateField && !empty($data)) {
            $values = array_filter(array_column($data, $aggregateField), function($v) {
                return is_numeric($v);
            });

            switch ($aggregateFunc) {
                case 'sum':
                    $result['value'] = array_sum($values);
                    break;
                case 'count':
                    $result['value'] = count($data);
                    break;
                case 'avg':
                    $result['value'] = count($values) > 0 ? array_sum($values) / count($values) : 0;
                    break;
                case 'min':
                    $result['value'] = count($values) > 0 ? min($values) : 0;
                    break;
                case 'max':
                    $result['value'] = count($values) > 0 ? max($values) : 0;
                    break;
                default:
                    $result['value'] = array_sum($values);
            }
        } else {
            $result['value'] = count($data);
        }
        $result['count'] = count($data);

    } elseif (in_array($widgetType, ['chart_bar', 'chart_line', 'chart_pie', 'chart_doughnut'])) {
        // For charts, we need to group and aggregate
        $data = $builder->fetchAll(1000);
        ob_end_clean(); // Clear any warnings

        if ($groupByField && !empty($data)) {
            $grouped = [];
            foreach ($data as $row) {
                $key = $row[$groupByField] ?? 'Unknown';
                if (is_array($key)) {
                    $key = json_encode($key);
                }
                $key = (string) $key;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [];
                }
                $grouped[$key][] = $row;
            }

            $labels = [];
            $values = [];

            foreach ($grouped as $label => $rows) {
                $labels[] = $label;

                if ($aggregateField) {
                    $fieldValues = array_filter(array_column($rows, $aggregateField), 'is_numeric');
                    switch ($aggregateFunc) {
                        case 'sum':
                            $values[] = array_sum($fieldValues);
                            break;
                        case 'avg':
                            $values[] = count($fieldValues) > 0 ? array_sum($fieldValues) / count($fieldValues) : 0;
                            break;
                        case 'count':
                        default:
                            $values[] = count($rows);
                            break;
                    }
                } else {
                    $values[] = count($rows);
                }
            }

            // Sort by value descending and limit
            array_multisort($values, SORT_DESC, $labels);
            $labels = array_slice($labels, 0, $limit);
            $values = array_slice($values, 0, $limit);

            $result['chart'] = [
                'labels' => $labels,
                'values' => $values,
            ];
        } else {
            $result['chart'] = ['labels' => [], 'values' => []];
        }

    } else {
        // Table widget - just return rows
        $builder->setPage(1)->setPerPage($limit);
        $reportData = $builder->execute();
        ob_end_clean(); // Clear any warnings

        $result['data'] = $reportData['data'];
        $result['columns'] = $config['columns'] ?? [];
        $result['total'] = $reportData['total'] ?? count($reportData['data']);
    }

    // Update run count
    ReportManager::incrementRunCount($reportId);

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load report: ' . $e->getMessage()]);
}
