<?php
/**
 * Top Products by Charges API
 * Returns top products ranked by total charges within the date range
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

    // Build query string for multiple includes
    $baseParams = [
        'per_page' => 100,
        'q[starts_at_gteq]' => $fromDate,
        'q[starts_at_lteq]' => $toDate . ' 23:59:59',
    ];
    $queryString = http_build_query($baseParams) . '&include[]=opportunity_items';

    // Fetch opportunities with items
    $opportunities = $api->fetchAllWithQuery('opportunities', $queryString, 50);

    // Aggregate charges by product
    $productCharges = [];

    foreach ($opportunities as $opp) {
        $items = $opp['opportunity_items'] ?? [];

        foreach ($items as $item) {
            $productName = $item['name'] ?? $item['product_name'] ?? 'Unknown Product';
            $productId = $item['product_id'] ?? 0;

            // Skip group headers (quantity = 0)
            $quantity = floatval($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            // Get charges (not revenue - use charge_total which is what was charged)
            $charges = floatval($item['charge_total'] ?? $item['total'] ?? 0);

            $key = $productId ?: $productName;

            if (!isset($productCharges[$key])) {
                $productCharges[$key] = [
                    'name' => $productName,
                    'product_id' => $productId,
                    'charges' => 0,
                    'count' => 0,
                ];
            }

            $productCharges[$key]['charges'] += $charges;
            $productCharges[$key]['count']++;
        }
    }

    // Sort by charges descending
    usort($productCharges, function($a, $b) {
        return $b['charges'] <=> $a['charges'];
    });

    // Limit results
    $topProducts = array_slice($productCharges, 0, $limit);

    // Round charges
    foreach ($topProducts as &$product) {
        $product['charges'] = round($product['charges'], 2);
    }

    echo json_encode([
        'success' => true,
        'data' => $topProducts,
        'filters' => [
            'from' => $fromDate,
            'to' => $toDate,
            'limit' => $limit,
        ],
        'meta' => [
            'total_products' => count($productCharges),
            'opportunities_analyzed' => count($opportunities),
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
