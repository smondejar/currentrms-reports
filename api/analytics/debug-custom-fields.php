<?php
/**
 * Debug Custom Fields - Shows raw custom field data from opportunities
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

    // Try fetching with include[] for custom_fields
    $queryString = 'per_page=3&include[]=custom_fields&q[starts_at_gteq]=' . date('Y-m-d', strtotime('-90 days'));

    $opportunities = $api->fetchAllWithQuery('opportunities', $queryString, 1);

    $debug = [];
    foreach ($opportunities as $opp) {
        // Show first opportunity completely
        if (count($debug) === 0) {
            $debug[] = [
                'FULL_RAW_DATA' => $opp,
            ];
        }

        $item = [
            'id' => $opp['id'] ?? null,
            'subject' => $opp['subject'] ?? null,
            'status' => $opp['status_name'] ?? $opp['status'] ?? null,
            'charge_total' => $opp['charge_total'] ?? null,
            'total' => $opp['total'] ?? null,
            'custom_fields' => $opp['custom_fields'] ?? 'NOT SET',
            'custom_field_values' => $opp['custom_field_values'] ?? 'NOT SET',
            'opportunity_custom_fields' => $opp['opportunity_custom_fields'] ?? 'NOT SET',
        ];
        $debug[] = $item;
    }

    echo json_encode([
        'success' => true,
        'count' => count($opportunities),
        'query' => $queryString,
        'opportunities' => $debug,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
