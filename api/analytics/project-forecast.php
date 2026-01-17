<?php
/**
 * Project Forecast API
 * Returns future project charges grouped by category
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
    $daysAhead = max(7, min(365, $daysAhead));

    // Forecast period: from today to today + daysAhead
    $forecastFrom = date('Y-m-d');
    $forecastTo = date('Y-m-d', strtotime("+{$daysAhead} days"));

    // Step 1: Fetch future opportunities (has charge_total and project_id)
    $opportunities = $api->fetchAll('opportunities', [
        'per_page' => 100,
        'q[starts_at_gteq]' => $forecastFrom,
        'q[starts_at_lteq]' => $forecastTo . ' 23:59:59',
    ], 50);

    // Collect unique project IDs and aggregate charges
    $oppByProjectId = [];
    foreach ($opportunities as $opp) {
        $projectId = $opp['project_id'] ?? null;
        if ($projectId) {
            if (!isset($oppByProjectId[$projectId])) {
                $oppByProjectId[$projectId] = $opp;
            } else {
                $oppByProjectId[$projectId]['charge_total'] =
                    floatval($oppByProjectId[$projectId]['charge_total'] ?? 0) +
                    floatval($opp['charge_total'] ?? 0);
            }
        }
    }

    // Step 2: Fetch projects with custom_fields
    $projectsData = [];
    if (!empty($oppByProjectId)) {
        $projectQueryString = 'per_page=100&include[]=custom_fields';
        $allProjects = $api->fetchAllWithQuery('projects', $projectQueryString, 50);

        foreach ($allProjects as $proj) {
            $projectsData[$proj['id']] = $proj;
        }
    }

    // Category custom field IDs
    $BUSINESS_ID = 1000074;
    $CONSUMER_ID = 1000075;

    // Group by category
    $categoryData = [
        'Business' => ['name' => 'Business', 'count' => 0, 'charges' => 0],
        'Consumer' => ['name' => 'Consumer', 'count' => 0, 'charges' => 0],
    ];
    $totalCharges = 0;
    $totalProjects = 0;

    foreach ($oppByProjectId as $projectId => $opp) {
        $project = $projectsData[$projectId] ?? null;
        if (!$project) continue;

        $category = null;

        if (isset($project['custom_fields']['category']) && is_array($project['custom_fields']['category'])) {
            $categoryIds = $project['custom_fields']['category'];
            if (in_array($BUSINESS_ID, $categoryIds)) {
                $category = 'Business';
            } elseif (in_array($CONSUMER_ID, $categoryIds)) {
                $category = 'Consumer';
            }
        }

        if (!$category) continue;

        $charges = floatval($opp['charge_total'] ?? 0);

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
