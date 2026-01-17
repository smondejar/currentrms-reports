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

    // Fetch projects within the date range with custom_fields included
    $queryString = http_build_query([
        'per_page' => 100,
        'q[starts_at_gteq]' => $fromDate,
        'q[starts_at_lteq]' => $toDate . ' 23:59:59',
    ]) . '&include[]=custom_fields';

    $projects = $api->fetchAllWithQuery('projects', $queryString, 50);

    // Category custom field IDs
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

    foreach ($projects as $proj) {
        $category = null;

        // Check custom_fields for category array
        if (isset($proj['custom_fields']['category']) && is_array($proj['custom_fields']['category'])) {
            $categoryIds = $proj['custom_fields']['category'];
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

        // Get charges
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

    // Debug: show first project's custom_fields structure
    $debugInfo = null;
    if (!empty($projects)) {
        $firstProj = $projects[0];
        $debugInfo = [
            'project_id' => $firstProj['id'] ?? null,
            'custom_fields_keys' => isset($firstProj['custom_fields']) ? array_keys($firstProj['custom_fields']) : 'NOT SET',
            'custom_fields_raw' => $firstProj['custom_fields'] ?? 'NOT SET',
            'total_projects_fetched' => count($projects),
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
