<?php
/**
 * API: Product Utilisation Report
 * Calculates how many times each product has been added to opportunities
 */

ob_start();
require_once __DIR__ . '/../includes/bootstrap.php';
ob_end_clean();

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get API client
$api = getApiClient();
if (!$api) {
    http_response_code(500);
    echo json_encode(['error' => 'API not configured']);
    exit;
}

$page = (int) ($_GET['page'] ?? 1);
$perPage = (int) ($_GET['per_page'] ?? 25);
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-365 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

try {
    // Get all products first
    $productsResponse = $api->get('products', [
        'per_page' => 100,
        'q[s]' => 'name asc'
    ]);

    $products = [];
    foreach ($productsResponse['products'] ?? [] as $product) {
        $products[$product['id']] = [
            'id' => $product['id'],
            'name' => $product['name'] ?? 'Unknown',
            'product_group_name' => $product['product_group']['name'] ?? $product['product_group_name'] ?? '',
            'type' => $product['type'] ?? 'Product',
            'rental_rate' => floatval($product['rates'][0]['price'] ?? $product['rental_price'] ?? 0),
            'times_quoted' => 0,
            'total_quantity_quoted' => 0,
            'total_revenue' => 0,
            'opportunities_count' => 0,
        ];
    }

    // Get opportunities within date range
    $opportunities = $api->get('opportunities', [
        'per_page' => 100,
        'q[starts_at_gteq]' => $dateFrom,
        'q[starts_at_lteq]' => $dateTo,
    ]);

    $opportunityIds = array_column($opportunities['opportunities'] ?? [], 'id');

    // Process each opportunity to get items
    foreach ($opportunityIds as $oppId) {
        try {
            $oppDetails = $api->get("opportunities/{$oppId}");
            $opp = $oppDetails['opportunity'] ?? [];

            // Get opportunity items
            $items = $opp['opportunity_items'] ?? [];
            if (empty($items)) {
                // Try fetching items separately
                $itemsResponse = $api->get("opportunities/{$oppId}/opportunity_items", ['per_page' => 100]);
                $items = $itemsResponse['opportunity_items'] ?? [];
            }

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? $item['product']['id'] ?? null;
                if ($productId && isset($products[$productId])) {
                    $products[$productId]['times_quoted']++;
                    $products[$productId]['total_quantity_quoted'] += floatval($item['quantity'] ?? 1);
                    $products[$productId]['total_revenue'] += floatval($item['charge_total'] ?? $item['total'] ?? 0);

                    // Track unique opportunities
                    if (!isset($products[$productId]['opp_ids'])) {
                        $products[$productId]['opp_ids'] = [];
                    }
                    $products[$productId]['opp_ids'][$oppId] = true;
                }
            }
        } catch (Exception $e) {
            // Skip failed opportunities
            continue;
        }
    }

    // Calculate opportunities count and clean up
    foreach ($products as &$product) {
        $product['opportunities_count'] = count($product['opp_ids'] ?? []);
        unset($product['opp_ids']);
    }
    unset($product);

    // Sort by times_quoted descending
    usort($products, function($a, $b) {
        return $b['times_quoted'] - $a['times_quoted'];
    });

    // Paginate
    $total = count($products);
    $offset = ($page - 1) * $perPage;
    $paginatedData = array_slice($products, $offset, $perPage);

    echo json_encode([
        'data' => array_values($paginatedData),
        'meta' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ],
        'columns' => ['id', 'name', 'product_group_name', 'type', 'rental_rate', 'times_quoted', 'total_quantity_quoted', 'total_revenue', 'opportunities_count'],
        'column_config' => [
            'id' => ['label' => 'ID', 'type' => 'number'],
            'name' => ['label' => 'Product', 'type' => 'string'],
            'product_group_name' => ['label' => 'Group', 'type' => 'string'],
            'type' => ['label' => 'Type', 'type' => 'string'],
            'rental_rate' => ['label' => 'Rental Rate', 'type' => 'currency'],
            'times_quoted' => ['label' => 'Times Quoted', 'type' => 'number'],
            'total_quantity_quoted' => ['label' => 'Total Qty Quoted', 'type' => 'number'],
            'total_revenue' => ['label' => 'Total Revenue', 'type' => 'currency'],
            'opportunities_count' => ['label' => 'Unique Opportunities', 'type' => 'number'],
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch data: ' . $e->getMessage()]);
}
