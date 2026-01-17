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

    // Fetch future opportunities with linked project (project has the category custom field)
    $queryString = http_build_query([
        'per_page' => 100,
        'q[starts_at_gteq]' => $forecastFrom,
        'q[starts_at_lteq]' => $forecastTo . ' 23:59:59',
    ]) . '&include[]=project&include[]=project.custom_fields';

    $opportunities = $api->fetchAllWithQuery('opportunities', $queryString, 50);

    // Category custom field IDs (on project)
    // 1000074 = Business - Conf, assoc, corporate, exhib
    // 1000075 = Consumer - Entert, consumer, exhib
    $BUSINESS_ID = 1000074;
    $CONSUMER_ID = 1000075;

    // Group by "category" custom field (Business vs Consumer)
    $categoryData = [
        'Business' => ['name' => 'Business', 'count' => 0, 'charges' => 0],
        'Consumer' => ['name' => 'Consumer', 'count' => 0, 'charges' => 0],
    ];
    $totalCharges = 0;
    $totalProjects = 0;

    foreach ($opportunities as $opp) {
        $category = null;

        // Get category from linked project's custom_fields
        $project = $opp['project'] ?? null;
        if ($project && isset($project['custom_fields']['category']) && is_array($project['custom_fields']['category'])) {
            $categoryIds = $project['custom_fields']['category'];
            if (in_array($BUSINESS_ID, $categoryIds)) {
                $category = 'Business';
            } elseif (in_array($CONSUMER_ID, $categoryIds)) {
                $category = 'Consumer';
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
