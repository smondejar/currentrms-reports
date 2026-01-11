<?php
/**
 * API: Preview Report Data
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check permission
if (!Auth::can(Permissions::VIEW_REPORTS)) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// Get API client
$api = getApiClient();
if (!$api) {
    http_response_code(500);
    echo json_encode(['error' => 'API not configured']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$module = $input['module'] ?? null;
$columns = $input['columns'] ?? [];
$filters = $input['filters'] ?? [];
$sorting = $input['sorting'] ?? null;
$page = (int) ($input['page'] ?? 1);
$perPage = (int) ($input['per_page'] ?? 25);

if (!$module) {
    http_response_code(400);
    echo json_encode(['error' => 'Module is required']);
    exit;
}

try {
    $builder = new ReportBuilder($api);
    $builder->setModule($module);

    if (!empty($columns)) {
        $builder->setColumns(is_array($columns) ? $columns : explode(',', $columns));
    }

    if (!empty($filters)) {
        $builder->setFilters(is_array($filters) ? $filters : json_decode($filters, true));
    }

    if (!empty($sorting)) {
        $sortData = is_array($sorting) ? $sorting : json_decode($sorting, true);
        if ($sortData && isset($sortData['field'])) {
            $builder->setSorting($sortData['field'], $sortData['direction'] ?? 'asc');
        }
    }

    $builder->setPagination($page, $perPage);

    $result = $builder->execute();

    echo json_encode([
        'success' => true,
        'data' => $result['data'],
        'meta' => $result['meta'],
        'columns' => $result['columns'],
        'column_config' => $result['column_config'],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
