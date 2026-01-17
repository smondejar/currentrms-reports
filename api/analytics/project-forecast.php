<?php
/**
 * Project Forecast API
 * Returns future project charges grouped by category based on the date range duration
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

    // Get date range from request to calculate the forecast period
    $fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-90 days'));
    $toDate = $_GET['to'] ?? date('Y-m-d');

    // Calculate days in the range to project forward the same duration
    $fromTs = strtotime($fromDate);
    $toTs = strtotime($toDate);
    $daysDiff = max(1, floor(($toTs - $fromTs) / 86400));

    // Forecast period: from today to today + daysDiff
    $forecastFrom = date('Y-m-d');
    $forecastTo = date('Y-m-d', strtotime("+{$daysDiff} days"));

    // Fetch future opportunities
    $opportunities = $api->fetchAll('opportunities', [
        'per_page' => 100,
        'q[starts_at_gteq]' => $forecastFrom,
        'q[starts_at_lteq]' => $forecastTo . ' 23:59:59',
    ], 50);

    // Group by Category custom field (Business vs Consumer)
    $categoryData = [
        'Business' => ['name' => 'Business', 'count' => 0, 'charges' => 0],
        'Consumer' => ['name' => 'Consumer', 'count' => 0, 'charges' => 0],
    ];
    $totalCharges = 0;
    $totalProjects = 0;

    foreach ($opportunities as $opp) {
        // Get category from custom fields - look for "Category" field
        $category = null;

        if (isset($opp['custom_fields']) && is_array($opp['custom_fields'])) {
            foreach ($opp['custom_fields'] as $field) {
                $fieldName = strtolower($field['name'] ?? '');
                if ($fieldName === 'category' || $fieldName === 'categories') {
                    $value = $field['value'] ?? '';
                    // Map to Business or Consumer based on value
                    $valueLower = strtolower($value);
                    if (strpos($valueLower, 'business') !== false ||
                        strpos($valueLower, 'conf') !== false ||
                        strpos($valueLower, 'assoc') !== false ||
                        strpos($valueLower, 'corporate') !== false ||
                        strpos($valueLower, 'exhib') !== false) {
                        $category = 'Business';
                    } elseif (strpos($valueLower, 'consumer') !== false ||
                              strpos($valueLower, 'entert') !== false) {
                        $category = 'Consumer';
                    } else {
                        $category = $value ?: null;
                    }
                    break;
                }
            }
        }

        // Skip if no category found
        if (!$category) {
            continue;
        }

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
            'forecast_from' => $forecastFrom,
            'forecast_to' => $forecastTo,
            'days_ahead' => $daysDiff,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
