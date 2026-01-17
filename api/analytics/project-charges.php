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

    // Fetch opportunities with linked project (project has the category custom field)
    $queryString = http_build_query([
        'per_page' => 100,
        'q[starts_at_gteq]' => $fromDate,
        'q[starts_at_lteq]' => $toDate . ' 23:59:59',
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

        // Get charges from opportunity
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

    // Debug: show opportunity structure to find project link
    $debugInfo = [];
    foreach (array_slice($opportunities, 0, 3) as $opp) {
        $debugInfo[] = [
            'opp_id' => $opp['id'] ?? null,
            'subject' => $opp['subject'] ?? null,
            'charge_total' => $opp['charge_total'] ?? 'NOT SET',
            'project_id' => $opp['project_id'] ?? 'NOT SET',
            'project' => isset($opp['project']) ? 'SET' : 'NOT SET',
            'all_keys' => array_keys($opp),
        ];
    }

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
        'debug' => $debugInfo,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
