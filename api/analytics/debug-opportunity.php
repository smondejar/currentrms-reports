<?php
/**
 * Debug endpoint to check opportunity data structure
 */

ob_start();
require_once __DIR__ . '/../../includes/bootstrap.php';
ob_end_clean();

header('Content-Type: application/json');

try {
    Auth::requireAuth();

    $api = getApiClient();
    if (!$api) {
        throw new Exception('API client not configured');
    }

    $oppId = intval($_GET['id'] ?? 1394);

    // Fetch the specific opportunity with items and owner
    $oppResponse = $api->get("opportunities/{$oppId}", [
        'include[]' => 'opportunity_items',
    ]);

    // Also try fetching with owner include separately to see what we get
    $oppWithOwner = $api->get("opportunities/{$oppId}");

    $opp = $oppResponse['opportunity'] ?? $oppResponse;

    // Get items
    $items = $opp['opportunity_items'] ?? [];

    // Debug info for each item
    $itemsDebug = [];
    foreach ($items as $item) {
        $productId = $item['product_id'] ?? null;
        $productInfo = null;

        if ($productId) {
            try {
                $productResponse = $api->get("products/{$productId}");
                $product = $productResponse['product'] ?? $productResponse;

                // Get standard rate
                $standardRate = 0;
                if (isset($product['rates']) && is_array($product['rates']) && count($product['rates']) > 0) {
                    $standardRate = floatval($product['rates'][0]['price'] ?? 0);
                } elseif (isset($product['rental_price'])) {
                    $standardRate = floatval($product['rental_price']);
                } elseif (isset($product['rate'])) {
                    $standardRate = floatval($product['rate']);
                }

                $productInfo = [
                    'name' => $product['name'] ?? 'Unknown',
                    'standard_rate' => $standardRate,
                    'rates_array' => $product['rates'] ?? null,
                    'rental_price' => $product['rental_price'] ?? null,
                    'rate' => $product['rate'] ?? null,
                ];
            } catch (Exception $e) {
                $productInfo = ['error' => $e->getMessage()];
            }
        }

        $itemsDebug[] = [
            'item_id' => $item['id'] ?? null,
            'product_id' => $productId,
            'item_name' => $item['name'] ?? $item['product_name'] ?? null,
            'quantity' => $item['quantity'] ?? null,
            // All possible rate/price fields from the item
            'item_rate' => $item['rate'] ?? null,
            'item_price' => $item['price'] ?? null,
            'item_charge' => $item['charge'] ?? null,
            'item_charge_total' => $item['charge_total'] ?? null,
            'item_rental_charge' => $item['rental_charge'] ?? null,
            'item_unit_price' => $item['unit_price'] ?? null,
            'item_discount' => $item['discount'] ?? null,
            'item_discount_percent' => $item['discount_percent'] ?? null,
            // Full item for inspection
            'raw_item_keys' => array_keys($item),
            'product_info' => $productInfo,
        ];
    }

    // Get the opportunity without items to see all base fields
    $oppBase = $oppWithOwner['opportunity'] ?? $oppWithOwner;

    // Find all fields that might contain owner/user info
    $ownerRelatedFields = [];
    foreach ($oppBase as $key => $value) {
        if (stripos($key, 'owner') !== false ||
            stripos($key, 'user') !== false ||
            stripos($key, 'by') !== false ||
            stripos($key, 'member') !== false ||
            stripos($key, 'staff') !== false ||
            stripos($key, 'assign') !== false ||
            stripos($key, 'name') !== false) {
            $ownerRelatedFields[$key] = $value;
        }
    }

    echo json_encode([
        'success' => true,
        'opportunity' => [
            'id' => $opp['id'] ?? null,
            'subject' => $opp['subject'] ?? null,
            'status' => $opp['status'] ?? null,
            'status_name' => $opp['status_name'] ?? null,
            'state' => $opp['state'] ?? null,
            'starts_at' => $opp['starts_at'] ?? null,
            'items_count' => count($items),
        ],
        'owner_related_fields' => $ownerRelatedFields,
        'all_opportunity_keys' => array_keys($oppBase),
        'items' => $itemsDebug,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
