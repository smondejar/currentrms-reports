<?php
/**
 * Debug Custom Fields - Shows raw custom field data from projects
 */

ob_start();
require_once __DIR__ . '/../../includes/bootstrap.php';
ob_end_clean();

header('Content-Type: application/json');

try {
    Auth::requireAuth();
    Auth::requirePermission(Permissions::VIEW_ANALYTICS);

    $api = getApiClient();
    if (!$api) {
        throw new Exception('API client not configured');
    }

    // Fetch projects with custom_fields included
    $queryString = 'per_page=3&include[]=custom_fields';

    $projects = $api->fetchAllWithQuery('projects', $queryString, 1);

    $debug = [];
    foreach ($projects as $proj) {
        // Show first project completely
        if (count($debug) === 0) {
            $debug[] = [
                'FULL_RAW_DATA' => $proj,
            ];
        }

        $item = [
            'id' => $proj['id'] ?? null,
            'name' => $proj['name'] ?? null,
            'subject' => $proj['subject'] ?? null,
            'status' => $proj['status_name'] ?? $proj['status'] ?? null,
            'charge_total' => $proj['charge_total'] ?? null,
            'total' => $proj['total'] ?? null,
            'custom_fields' => $proj['custom_fields'] ?? 'NOT SET',
            'custom_field_values' => $proj['custom_field_values'] ?? 'NOT SET',
        ];
        $debug[] = $item;
    }

    echo json_encode([
        'success' => true,
        'endpoint' => 'projects',
        'count' => count($projects),
        'query' => $queryString,
        'data' => $debug,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
