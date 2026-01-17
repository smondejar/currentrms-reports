<?php
/**
 * Project Charges by Category API
 * Returns project charges grouped by category custom field for the given date range
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

    // Get date range from request
    $fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-90 days'));
    $toDate = $_GET['to'] ?? date('Y-m-d');

    // Fetch opportunities within the date range (past dates = completed/historical projects)
    $opportunities = $api->fetchAll('opportunities', [
        'per_page' => 100,
        'q[starts_at_gteq]' => $fromDate,
        'q[starts_at_lteq]' => $toDate . ' 23:59:59',
    ], 50);

    // Group by category custom field
    $categoryData = [];
    $totalCharges = 0;
    $totalProjects = 0;

    foreach ($opportunities as $opp) {
        // Get category from custom fields or a relevant field
        $category = 'Uncategorized';

        // Check for custom fields that might contain category
        if (isset($opp['custom_fields']) && is_array($opp['custom_fields'])) {
            foreach ($opp['custom_fields'] as $field) {
                $fieldName = strtolower($field['name'] ?? '');
                if (strpos($fieldName, 'category') !== false || strpos($fieldName, 'type') !== false) {
                    $category = $field['value'] ?? 'Uncategorized';
                    break;
                }
            }
        }

        // Fallback to opportunity type or status
        if ($category === 'Uncategorized') {
            $category = $opp['opportunity_type_name'] ?? $opp['status_name'] ?? 'Uncategorized';
        }

        // Get charges
        $charges = floatval($opp['charge_total'] ?? $opp['total'] ?? 0);

        if (!isset($categoryData[$category])) {
            $categoryData[$category] = [
                'name' => $category,
                'count' => 0,
                'charges' => 0,
            ];
        }

        $categoryData[$category]['count']++;
        $categoryData[$category]['charges'] += $charges;
        $totalCharges += $charges;
        $totalProjects++;
    }

    // Sort by charges descending
    usort($categoryData, function($a, $b) {
        return $b['charges'] <=> $a['charges'];
    });

    echo json_encode([
        'success' => true,
        'data' => [
            'categories' => array_values($categoryData),
            'total_projects' => $totalProjects,
            'total_charges' => round($totalCharges, 2),
        ],
        'filters' => [
            'from' => $fromDate,
            'to' => $toDate,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
