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

    // Get days ahead from request (default 90 days)
    $daysAhead = intval($_GET['days'] ?? 90);
    $daysAhead = max(7, min(365, $daysAhead)); // Clamp between 7 and 365

    // Forecast period: from today to today + daysAhead
    $forecastFrom = date('Y-m-d');
    $forecastTo = date('Y-m-d', strtotime("+{$daysAhead} days"));

    // Fetch future projects with custom_fields included
    $queryString = http_build_query([
        'per_page' => 100,
        'q[starts_at_gteq]' => $forecastFrom,
        'q[starts_at_lteq]' => $forecastTo . ' 23:59:59',
    ]) . '&include[]=custom_fields';

    $projects = $api->fetchAllWithQuery('projects', $queryString, 50);

    // Group by "Event category" custom field (Business vs Consumer)
    $categoryData = [
        'Business' => ['name' => 'Business - Conf, assoc, corporate, exhib', 'count' => 0, 'charges' => 0],
        'Consumer' => ['name' => 'Consumer - Entert, consumer, exhib', 'count' => 0, 'charges' => 0],
    ];
    $totalCharges = 0;
    $totalProjects = 0;

    foreach ($projects as $proj) {
        // Get category from custom fields - look for "Event category" field
        $category = null;

        if (isset($proj['custom_fields']) && is_array($proj['custom_fields'])) {
            foreach ($proj['custom_fields'] as $field) {
                $fieldName = strtolower(trim($field['name'] ?? ''));
                // Match "event category" field
                if ($fieldName === 'event category' || $fieldName === 'event_category' ||
                    $fieldName === 'category' || $fieldName === 'categories') {
                    $value = trim($field['value'] ?? '');
                    $valueLower = strtolower($value);
                    // Match by checking if value starts with Business or Consumer
                    if (strpos($valueLower, 'business') === 0) {
                        $category = 'Business';
                    } elseif (strpos($valueLower, 'consumer') === 0) {
                        $category = 'Consumer';
                    }
                    break;
                }
            }
        }

        // Skip if no category found
        if (!$category) {
            continue;
        }

        $charges = floatval($proj['charge_total'] ?? $proj['total'] ?? 0);

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
            'days_ahead' => $daysAhead,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
