<?php
/**
 * API: Get Dynamic Field Values from CurrentRMS
 * Fetches actual values for filter dropdowns (customers, venues, owners, etc.)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$api = getApiClient();
if (!$api) {
    http_response_code(500);
    echo json_encode(['error' => 'API not configured']);
    exit;
}

$field = $_GET['field'] ?? null;
$module = $_GET['module'] ?? null;
$search = $_GET['search'] ?? '';

if (!$field) {
    http_response_code(400);
    echo json_encode(['error' => 'Field parameter is required']);
    exit;
}

try {
    $values = [];
    $cacheKey = "field_values_{$field}_{$module}_" . md5($search);

    // Define field mappings to CurrentRMS endpoints
    $fieldMappings = [
        // Members/Customers
        'member_name' => [
            'endpoint' => 'members',
            'label_field' => 'name',
            'value_field' => 'name',
            'params' => ['per_page' => 50, 'q[s]' => 'name asc']
        ],
        'customer' => [
            'endpoint' => 'members',
            'label_field' => 'name',
            'value_field' => 'name',
            'params' => ['per_page' => 50, 'q[member_type_eq]' => 'Contact', 'q[s]' => 'name asc']
        ],

        // Users/Owners
        'owner_name' => [
            'endpoint' => 'users',
            'label_field' => 'name',
            'value_field' => 'name',
            'params' => ['per_page' => 50, 'q[s]' => 'name asc']
        ],

        // Venues
        'venue_name' => [
            'endpoint' => 'members',
            'label_field' => 'name',
            'value_field' => 'name',
            'params' => ['per_page' => 50, 'q[member_type_eq]' => 'Venue', 'q[s]' => 'name asc']
        ],

        // Product Groups
        'product_group_name' => [
            'endpoint' => 'product_groups',
            'label_field' => 'name',
            'value_field' => 'name',
            'params' => ['per_page' => 100, 'q[s]' => 'name asc']
        ],

        // Stores/Warehouses
        'store_name' => [
            'endpoint' => 'stores',
            'label_field' => 'name',
            'value_field' => 'name',
            'params' => ['per_page' => 50, 'q[s]' => 'name asc']
        ],

        // Projects
        'project_name' => [
            'endpoint' => 'projects',
            'label_field' => 'name',
            'value_field' => 'name',
            'params' => ['per_page' => 50, 'q[s]' => 'name asc']
        ],

        // Cities (from members addresses)
        'address_city' => [
            'endpoint' => 'members',
            'label_field' => 'primary_address.city',
            'value_field' => 'primary_address.city',
            'params' => ['per_page' => 100],
            'extract_unique' => true
        ],
        'venue_city' => [
            'endpoint' => 'members',
            'label_field' => 'primary_address.city',
            'value_field' => 'primary_address.city',
            'params' => ['per_page' => 100, 'q[member_type_eq]' => 'Venue'],
            'extract_unique' => true
        ],

        // Countries
        'address_country_name' => [
            'endpoint' => 'countries',
            'label_field' => 'name',
            'value_field' => 'name',
            'params' => ['per_page' => 250]
        ],

        // Categories (custom field - may vary)
        'category' => [
            'static' => true,
            'values' => [] // Will be populated from projects if available
        ],
    ];

    // Check if we have a mapping for this field
    if (!isset($fieldMappings[$field])) {
        echo json_encode(['values' => [], 'message' => 'No dynamic values for this field']);
        exit;
    }

    $mapping = $fieldMappings[$field];

    // Handle static values
    if (!empty($mapping['static'])) {
        echo json_encode(['values' => $mapping['values']]);
        exit;
    }

    // Add search filter if provided
    $params = $mapping['params'];
    if ($search && isset($mapping['label_field'])) {
        $searchField = explode('.', $mapping['label_field'])[0];
        $params["q[{$searchField}_cont]"] = $search;
    }

    // Fetch data from CurrentRMS
    $response = $api->get($mapping['endpoint'], $params);
    $dataKey = $mapping['endpoint'];
    $items = $response[$dataKey] ?? [];

    // Extract values
    $seen = [];
    foreach ($items as $item) {
        $label = getNestedValue($item, $mapping['label_field']);
        $value = getNestedValue($item, $mapping['value_field']);

        if ($label && $value && !isset($seen[$value])) {
            $seen[$value] = true;
            $values[] = [
                'label' => $label,
                'value' => $value
            ];
        }
    }

    // For extract_unique fields, we need to deduplicate
    if (!empty($mapping['extract_unique'])) {
        $uniqueValues = [];
        foreach ($values as $v) {
            if (!empty($v['value']) && !in_array($v['value'], $uniqueValues)) {
                $uniqueValues[] = $v['value'];
            }
        }
        sort($uniqueValues);
        $values = array_map(fn($v) => ['label' => $v, 'value' => $v], $uniqueValues);
    }

    // Sort alphabetically by label
    usort($values, fn($a, $b) => strcasecmp($a['label'], $b['label']));

    echo json_encode([
        'success' => true,
        'field' => $field,
        'values' => $values,
        'count' => count($values)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch values: ' . $e->getMessage()]);
}

/**
 * Get nested value from array using dot notation
 */
function getNestedValue($array, $path) {
    $keys = explode('.', $path);
    $value = $array;

    foreach ($keys as $key) {
        if (is_array($value) && isset($value[$key])) {
            $value = $value[$key];
        } else {
            return null;
        }
    }

    return $value;
}
