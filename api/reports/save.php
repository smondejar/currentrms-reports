<?php
/**
 * API: Save Report
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
if (!Auth::can(Permissions::CREATE_REPORTS)) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// Validate CSRF
if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$name = trim($input['name'] ?? '');
$module = $input['module'] ?? null;
$columns = $input['columns'] ?? [];
$filters = $input['filters'] ?? [];
$sorting = $input['sorting'] ?? null;
$description = $input['description'] ?? '';
$isPublic = !empty($input['is_public']);

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Report name is required';
}

if (empty($module)) {
    $errors[] = 'Module is required';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => implode(', ', $errors)]);
    exit;
}

try {
    // Parse columns and filters if they're strings
    if (is_string($columns)) {
        $columns = !empty($columns) ? explode(',', $columns) : [];
    }

    if (is_string($filters)) {
        $filters = !empty($filters) ? json_decode($filters, true) : [];
    }

    if (is_string($sorting)) {
        $sorting = !empty($sorting) ? json_decode($sorting, true) : null;
    }

    $reportId = ReportManager::save([
        'user_id' => Auth::id(),
        'name' => $name,
        'description' => $description,
        'module' => $module,
        'columns' => $columns,
        'filters' => $filters,
        'sorting' => $sorting,
        'is_public' => $isPublic ? 1 : 0,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Report saved successfully',
        'report_id' => $reportId,
        'redirect' => 'report-view.php?id=' . $reportId,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save report: ' . $e->getMessage()]);
}
