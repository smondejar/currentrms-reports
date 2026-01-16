<?php
/**
 * Under-Rate Quotes Report API
 * Returns opportunities where items are quoted below standard rates
 * Looks at ALL opportunity statuses and includes future opportunities
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
    // past_days: how many days back to look (default 90)
    // future_days: how many days forward to look (default 365)
    $pastDays = intval($_GET['past_days'] ?? $_GET['days'] ?? 90);
    $pastDays = max(0, min(365, $pastDays));

    $futureDays = intval($_GET['future_days'] ?? 365);
    $futureDays = max(0, min(730, $futureDays));

    $startDate = date('Y-m-d', strtotime("-{$pastDays} days"));
    $endDate = date('Y-m-d', strtotime("+{$futureDays} days"));

    // Minimum discount percentage to report (default 5%)
    $minDiscount = floatval($_GET['min_discount'] ?? 5);

    // Build a product rate cache
    $productRates = [];

    // Fetch ALL opportunities with items - no status filter
    // Filter by starts_at to get opportunities in our date range (past and future)
    $opportunities = $api->fetchAll('opportunities', [
        'per_page' => 100,
        'q[starts_at_gteq]' => $startDate,
        'q[starts_at_lteq]' => $endDate . ' 23:59:59',
        'include[]' => 'opportunity_items',
    ], 50); // Increased page limit to fetch more opportunities

    $underRateItems = [];
    $ownerSummary = [];
    $productIdsToFetch = [];

    // First pass: collect all product IDs we need to look up
    foreach ($opportunities as $opp) {
        $items = $opp['opportunity_items'] ?? [];
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            if ($productId && !isset($productRates[$productId])) {
                $productIdsToFetch[$productId] = true;
            }
        }
    }

    // Fetch product rates in batches
    $productIds = array_keys($productIdsToFetch);
    foreach (array_chunk($productIds, 50) as $batch) {
        foreach ($batch as $productId) {
            try {
                $productResponse = $api->get("products/{$productId}");
                $product = $productResponse['product'] ?? $productResponse;

                // Get standard rental rate from product
                $standardRate = 0;
                if (isset($product['rates']) && is_array($product['rates']) && count($product['rates']) > 0) {
                    $standardRate = floatval($product['rates'][0]['price'] ?? 0);
                } elseif (isset($product['rental_price'])) {
                    $standardRate = floatval($product['rental_price']);
                } elseif (isset($product['rate'])) {
                    $standardRate = floatval($product['rate']);
                }

                $productRates[$productId] = [
                    'name' => $product['name'] ?? 'Unknown Product',
                    'standard_rate' => $standardRate,
                    'product_group' => $product['product_group']['name'] ?? ($product['product_group_name'] ?? 'Uncategorized'),
                ];
            } catch (Exception $e) {
                // Product might be deleted, skip it
                $productRates[$productId] = null;
            }
        }
    }

    // Second pass: analyze items for under-rate quotes
    foreach ($opportunities as $opp) {
        $items = $opp['opportunity_items'] ?? [];
        $owner = $opp['owner']['name'] ?? ($opp['owner_name'] ?? 'Unknown Owner');
        $ownerId = $opp['owner_id'] ?? $opp['owner']['id'] ?? 0;
        $oppStatus = $opp['status'] ?? $opp['state'] ?? 'Unknown';
        $startsAt = $opp['starts_at'] ?? null;

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId || !isset($productRates[$productId]) || $productRates[$productId] === null) {
                continue;
            }

            $productInfo = $productRates[$productId];
            $standardRate = $productInfo['standard_rate'];

            // Skip items with no standard rate defined
            if ($standardRate <= 0) {
                continue;
            }

            // Get quoted rate - check multiple possible fields
            $quotedRate = floatval($item['rate'] ?? $item['price'] ?? $item['charge'] ?? 0);
            $quantity = intval($item['quantity'] ?? 1);

            // Calculate discount percentage
            if ($quotedRate < $standardRate) {
                $discountAmount = $standardRate - $quotedRate;
                $discountPercent = ($discountAmount / $standardRate) * 100;

                // Only include if discount exceeds minimum threshold
                if ($discountPercent >= $minDiscount) {
                    $lostRevenue = $discountAmount * $quantity;

                    $underRateItem = [
                        'opportunity_id' => $opp['id'],
                        'opportunity_subject' => $opp['subject'] ?? 'Opportunity #' . $opp['id'],
                        'opportunity_status' => $oppStatus,
                        'starts_at' => $startsAt,
                        'owner' => $owner,
                        'owner_id' => $ownerId,
                        'customer' => $opp['member']['name'] ?? ($opp['billing_address']['name'] ?? 'Unknown Customer'),
                        'product_id' => $productId,
                        'product_name' => $productInfo['name'],
                        'product_group' => $productInfo['product_group'],
                        'quantity' => $quantity,
                        'standard_rate' => $standardRate,
                        'quoted_rate' => $quotedRate,
                        'discount_percent' => round($discountPercent, 1),
                        'discount_amount' => round($discountAmount, 2),
                        'lost_revenue' => round($lostRevenue, 2),
                        'created_at' => $opp['created_at'] ?? null,
                    ];

                    $underRateItems[] = $underRateItem;

                    // Update owner summary
                    if (!isset($ownerSummary[$ownerId])) {
                        $ownerSummary[$ownerId] = [
                            'owner' => $owner,
                            'total_items' => 0,
                            'total_lost_revenue' => 0,
                            'avg_discount' => 0,
                            'discounts' => [],
                        ];
                    }
                    $ownerSummary[$ownerId]['total_items']++;
                    $ownerSummary[$ownerId]['total_lost_revenue'] += $lostRevenue;
                    $ownerSummary[$ownerId]['discounts'][] = $discountPercent;
                }
            }
        }
    }

    // Calculate average discount per owner
    foreach ($ownerSummary as $ownerId => &$summary) {
        if (count($summary['discounts']) > 0) {
            $summary['avg_discount'] = round(array_sum($summary['discounts']) / count($summary['discounts']), 1);
        }
        $summary['total_lost_revenue'] = round($summary['total_lost_revenue'], 2);
        unset($summary['discounts']); // Remove raw data
    }

    // Sort items by discount percentage (highest first)
    usort($underRateItems, function($a, $b) {
        return $b['discount_percent'] <=> $a['discount_percent'];
    });

    // Sort owners by lost revenue (highest first)
    uasort($ownerSummary, function($a, $b) {
        return $b['total_lost_revenue'] <=> $a['total_lost_revenue'];
    });

    // Calculate totals
    $totalLostRevenue = array_sum(array_column($underRateItems, 'lost_revenue'));
    $totalItems = count($underRateItems);
    $avgDiscount = $totalItems > 0
        ? round(array_sum(array_column($underRateItems, 'discount_percent')) / $totalItems, 1)
        : 0;

    echo json_encode([
        'success' => true,
        'data' => [
            'items' => array_slice($underRateItems, 0, 100), // Limit to top 100
            'owner_summary' => array_values($ownerSummary),
            'totals' => [
                'total_items' => $totalItems,
                'total_lost_revenue' => round($totalLostRevenue, 2),
                'avg_discount' => $avgDiscount,
                'unique_owners' => count($ownerSummary),
            ],
        ],
        'filters' => [
            'past_days' => $pastDays,
            'future_days' => $futureDays,
            'min_discount' => $minDiscount,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ],
        'debug' => [
            'total_opportunities_fetched' => count($opportunities),
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
