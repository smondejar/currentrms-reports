<?php
/**
 * Top Products by Utilisation API
 * Returns top products ranked by how many times they've been added to opportunities
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

    // Get parameters
    $fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-90 days'));
    $toDate = $_GET['to'] ?? date('Y-m-d');
    $limit = intval($_GET['limit'] ?? 20);
    $limit = min(100, max(1, $limit));

    // Type filter: 'product' or 'service' (default: 'product')
    // In CurrentRMS: item_type 1 = Rental, 2 = Sale, 3 = Service, 4 = Accessory
    $typeFilter = strtolower($_GET['type'] ?? 'product');
    $serviceTypes = [3]; // Service item types
    $productTypes = [1, 2, 4]; // Rental, Sale, Accessory

    // Build query string - get ALL opportunities in date range
    // Use filtermode=all to get all opportunities, then filter out dead in PHP
    $baseParams = [
        'per_page' => 100,
        'filtermode' => 'all',
        'q[starts_at_gteq]' => $fromDate,
        'q[starts_at_lteq]' => $toDate . ' 23:59:59',
    ];
    $queryString = http_build_query($baseParams) . '&include[]=opportunity_items';

    // Fetch opportunities with items
    $opportunities = $api->fetchAllWithQuery('opportunities', $queryString, 50);

    // Debug: log what we're querying
    $debugQuery = $queryString;
    $skippedStatuses = [];

    // Count product usage
    $productUsage = [];

    foreach ($opportunities as $opp) {
        // Skip dead/cancelled/closed lost opportunities
        $state = $opp['state'] ?? null;
        $statusName = strtolower($opp['status_name'] ?? $opp['status'] ?? '');

        if ($state === 4 || $state === 'dead' ||
            strpos($statusName, 'dead') !== false ||
            strpos($statusName, 'cancel') !== false ||
            strpos($statusName, 'closed lost') !== false) {
            $skippedStatuses[$statusName] = ($skippedStatuses[$statusName] ?? 0) + 1;
            continue;
        }

        $items = $opp['opportunity_items'] ?? [];

        foreach ($items as $item) {
            $productName = $item['name'] ?? $item['product_name'] ?? 'Unknown Product';
            $productId = $item['product_id'] ?? 0;
            $itemType = $item['item_type'] ?? $item['product_type'] ?? null;

            // Skip group headers (quantity = 0)
            $quantity = floatval($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            // Filter by type (product vs service)
            if ($typeFilter === 'service') {
                // Only include services
                if (!in_array($itemType, $serviceTypes)) {
                    continue;
                }
            } else {
                // Only include products (rental, sale, accessory) - exclude services
                if (in_array($itemType, $serviceTypes)) {
                    continue;
                }
            }

            $key = $productId ?: $productName;

            if (!isset($productUsage[$key])) {
                $productUsage[$key] = [
                    'name' => $productName,
                    'product_id' => $productId,
                    'count' => 0,  // Times added to opportunities
                    'total_quantity' => 0,  // Total quantity across all opportunities
                ];
            }

            // Count each time the product appears in an opportunity
            $productUsage[$key]['count']++;
            $productUsage[$key]['total_quantity'] += $quantity;
        }
    }

    // Sort by count descending (most frequently used)
    usort($productUsage, function($a, $b) {
        return $b['count'] <=> $a['count'];
    });

    // Limit results
    $topProducts = array_slice($productUsage, 0, $limit);

    // Count total items processed
    $totalItemsProcessed = 0;
    foreach ($opportunities as $opp) {
        $totalItemsProcessed += count($opp['opportunity_items'] ?? []);
    }

    // Sample debug data
    $debugSamples = [];
    foreach (array_slice($opportunities, 0, 3) as $opp) {
        $items = $opp['opportunity_items'] ?? [];
        $debugSamples[] = [
            'opp_id' => $opp['id'],
            'subject' => $opp['subject'] ?? null,
            'starts_at' => $opp['starts_at'] ?? null,
            'status' => $opp['status_name'] ?? $opp['status'] ?? null,
            'item_count' => count($items),
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $topProducts,
        'filters' => [
            'from' => $fromDate,
            'to' => $toDate,
            'limit' => $limit,
            'type' => $typeFilter,
        ],
        'meta' => [
            'total_products' => count($productUsage),
            'opportunities_analyzed' => count($opportunities),
        ],
        'debug' => [
            'query_used' => $debugQuery,
            'opportunities_fetched' => count($opportunities),
            'skipped_by_status' => $skippedStatuses,
            'total_items_in_opportunities' => $totalItemsProcessed,
            'unique_products_found' => count($productUsage),
            'date_range' => $fromDate . ' to ' . $toDate,
            'samples' => $debugSamples,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
