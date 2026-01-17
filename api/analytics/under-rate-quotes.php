<?php
/**
 * Under-Rate Quotes Report API
 * Returns opportunities where items have discounts applied
 * Uses CurrentRMS's built-in discount_percent field on opportunity items
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

    // Fetch opportunities with items and owner expanded
    // Filter by starts_at to get opportunities in our date range (past and future)
    // Use filtermode=all to get ALL opportunities, then filter out dead in PHP
    $baseParams = [
        'per_page' => 100,
        'filtermode' => 'all',
        'q[starts_at_gteq]' => $startDate,
        'q[starts_at_lteq]' => $endDate . ' 23:59:59',
    ];
    $queryString = http_build_query($baseParams)
        . '&include[]=opportunity_items&include[]=owner';

    $opportunities = $api->fetchAllWithQuery('opportunities', $queryString, 50);

    $underRateItems = [];
    $ownerSummary = [];
    $skippedStatuses = [];

    // Analyze items for discounts using CurrentRMS's discount_percent field
    foreach ($opportunities as $opp) {
        // Get opportunity status - skip dead/cancelled/closed lost
        $state = $opp['state'] ?? null;
        $statusName = strtolower($opp['status_name'] ?? $opp['status'] ?? '');

        // Skip dead, cancelled, or closed lost opportunities
        if ($state === 4 || $state === 'dead' ||
            strpos($statusName, 'dead') !== false ||
            strpos($statusName, 'cancel') !== false ||
            strpos($statusName, 'closed lost') !== false) {
            $skippedStatuses[$statusName] = ($skippedStatuses[$statusName] ?? 0) + 1;
            continue;
        }

        $items = $opp['opportunity_items'] ?? [];

        // Get owner from the 'owner' field (already expanded object in CurrentRMS)
        $ownerObj = $opp['owner'] ?? null;
        if (is_array($ownerObj) && isset($ownerObj['name'])) {
            $owner = $ownerObj['name'];
            $ownerId = $ownerObj['id'] ?? 0;
        } else {
            // Fallback to owned_by ID
            $ownerId = $opp['owned_by'] ?? 0;
            $owner = $ownerId ? "Owner #$ownerId" : 'Unknown Owner';
        }
        $oppStatus = $opp['status_name'] ?? $opp['status'] ?? $opp['state'] ?? 'Unknown';
        $startsAt = $opp['starts_at'] ?? null;
        $customer = $opp['member']['name'] ?? ($opp['billing_address']['name'] ?? ($opp['subject'] ?? 'Unknown Customer'));

        foreach ($items as $item) {
            // Get discount percentage directly from the item
            $discountPercent = floatval($item['discount_percent'] ?? 0);

            // Skip items with no discount or below threshold
            if ($discountPercent < $minDiscount) {
                continue;
            }

            // Skip group items (quantity = 0, they're just containers)
            $quantity = floatval($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            // Get price info
            $price = floatval($item['price'] ?? 0);
            $chargeTotal = floatval($item['charge_total'] ?? 0);

            // Calculate lost revenue: price * quantity * (discount_percent / 100)
            // Or: original charge - actual charge
            $originalCharge = $price * $quantity;
            $lostRevenue = $originalCharge - $chargeTotal;
            if ($lostRevenue < 0) {
                $lostRevenue = $originalCharge * ($discountPercent / 100);
            }

            $itemName = $item['name'] ?? 'Unknown Item';

            $underRateItem = [
                'opportunity_id' => $opp['id'],
                'opportunity_subject' => $opp['subject'] ?? 'Opportunity #' . $opp['id'],
                'opportunity_status' => $oppStatus,
                'starts_at' => $startsAt,
                'owner' => $owner,
                'owner_id' => $ownerId,
                'customer' => $customer,
                'item_id' => $item['id'] ?? null,
                'item_name' => $itemName,
                'quantity' => $quantity,
                'price' => $price,
                'charge_total' => $chargeTotal,
                'discount_percent' => round($discountPercent, 1),
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
            'today' => date('Y-m-d'),
            'raw_past_days_input' => $_GET['past_days'] ?? 'not set',
            'raw_future_days_input' => $_GET['future_days'] ?? 'not set',
            'past_days_used' => $pastDays,
            'future_days_used' => $futureDays,
            'calculated_start_date' => $startDate,
            'calculated_end_date' => $endDate,
            'total_opportunities_fetched' => count($opportunities),
            'opportunities_with_discounted_items' => count($underRateItems),
            'skipped_by_status' => $skippedStatuses,
            'query_params' => $queryString,
            'sample_opp_dates' => array_slice(array_map(function($o) {
                return [
                    'id' => $o['id'],
                    'starts_at' => $o['starts_at'] ?? 'null',
                    'status' => $o['status_name'] ?? $o['status'] ?? 'unknown'
                ];
            }, $opportunities), 0, 5),
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
