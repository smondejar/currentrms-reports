<?php
/**
 * API: Debug - Shows actual API response structure
 * Use this to understand what fields CurrentRMS returns
 */

ob_start();
require_once __DIR__ . '/../includes/bootstrap.php';
ob_end_clean();

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get API client
$api = getApiClient();
if (!$api) {
    http_response_code(500);
    echo json_encode(['error' => 'API not configured']);
    exit;
}

$endpoint = $_GET['endpoint'] ?? 'opportunities';
$limit = (int) ($_GET['limit'] ?? 1);

$debug = [
    'endpoint' => $endpoint,
    'timestamp' => date('Y-m-d H:i:s'),
];

try {
    $response = $api->get($endpoint, ['per_page' => $limit]);

    $debug['raw_response_keys'] = array_keys($response);

    // Get the data array (usually same as endpoint name)
    $dataKey = $endpoint;
    $data = $response[$dataKey] ?? $response[array_keys($response)[0]] ?? [];

    $debug['data_key'] = $dataKey;
    $debug['record_count'] = count($data);

    if (!empty($data)) {
        $firstRecord = $data[0];
        $debug['first_record'] = $firstRecord;
        $debug['field_names'] = array_keys($firstRecord);

        // Check for nested objects
        $nestedObjects = [];
        foreach ($firstRecord as $key => $value) {
            if (is_array($value)) {
                $nestedObjects[$key] = [
                    'type' => isset($value[0]) ? 'array' : 'object',
                    'keys' => is_array($value) ? array_keys($value) : 'scalar',
                    'sample' => $value
                ];
            }
        }
        $debug['nested_objects'] = $nestedObjects;

        // Look for total/revenue related fields
        $moneyFields = [];
        foreach ($firstRecord as $key => $value) {
            if (stripos($key, 'total') !== false ||
                stripos($key, 'charge') !== false ||
                stripos($key, 'revenue') !== false ||
                stripos($key, 'price') !== false ||
                stripos($key, 'budget') !== false ||
                stripos($key, 'amount') !== false) {
                $moneyFields[$key] = $value;
            }
        }
        $debug['money_related_fields'] = $moneyFields;
    }

    // Also get meta info
    $debug['meta'] = $response['meta'] ?? null;

} catch (Exception $e) {
    $debug['error'] = $e->getMessage();
}

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
