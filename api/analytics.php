<?php
/**
 * API: Analytics Data
 * Fetches live analytics data from CurrentRMS
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check permission
if (!Auth::can(Permissions::VIEW_ANALYTICS)) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// Get API client
$api = getApiClient();
if (!$api) {
    http_response_code(500);
    echo json_encode(['error' => 'API not configured']);
    exit;
}

// Get date range
$days = (int) ($_GET['days'] ?? 30);
$startDate = date('Y-m-d', strtotime("-{$days} days"));
$endDate = date('Y-m-d');
$previousStartDate = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));
$previousEndDate = date('Y-m-d', strtotime("-{$days} days"));

$analytics = [
    'kpis' => [],
    'charts' => [],
    'timeline' => [],
];

try {
    // KPI: Total Revenue (from invoices in date range)
    $invoices = $api->get('invoices', [
        'per_page' => 100,
        'q[invoice_date_gteq]' => $startDate,
        'q[invoice_date_lteq]' => $endDate,
    ]);

    $totalRevenue = 0;
    foreach ($invoices['invoices'] ?? [] as $inv) {
        $totalRevenue += floatval($inv['total'] ?? 0);
    }

    // Previous period revenue for comparison
    $prevInvoices = $api->get('invoices', [
        'per_page' => 100,
        'q[invoice_date_gteq]' => $previousStartDate,
        'q[invoice_date_lteq]' => $previousEndDate,
    ]);

    $prevRevenue = 0;
    foreach ($prevInvoices['invoices'] ?? [] as $inv) {
        $prevRevenue += floatval($inv['total'] ?? 0);
    }

    $revenueChange = $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;

    $analytics['kpis']['revenue'] = [
        'value' => $totalRevenue,
        'change' => $revenueChange,
        'label' => 'Total Revenue',
    ];

    // KPI: Opportunities (closed/won in date range)
    $opportunities = $api->get('opportunities', [
        'per_page' => 100,
        'q[created_at_gteq]' => $startDate,
        'q[created_at_lteq]' => $endDate,
        'filtermode' => 'active',
    ]);

    $oppCount = $opportunities['meta']['total_row_count'] ?? count($opportunities['opportunities'] ?? []);

    $prevOpportunities = $api->get('opportunities', [
        'per_page' => 1,
        'q[created_at_gteq]' => $previousStartDate,
        'q[created_at_lteq]' => $previousEndDate,
        'filtermode' => 'active',
    ]);

    $prevOppCount = $prevOpportunities['meta']['total_row_count'] ?? 0;
    $oppChange = $prevOppCount > 0 ? round((($oppCount - $prevOppCount) / $prevOppCount) * 100, 1) : 0;

    $analytics['kpis']['opportunities'] = [
        'value' => $oppCount,
        'change' => $oppChange,
        'label' => 'Active Opportunities',
    ];

    // KPI: New Customers (members created in date range)
    $members = $api->get('members', [
        'per_page' => 1,
        'q[created_at_gteq]' => $startDate,
        'q[created_at_lteq]' => $endDate,
    ]);

    $newCustomers = $members['meta']['total_row_count'] ?? 0;

    $prevMembers = $api->get('members', [
        'per_page' => 1,
        'q[created_at_gteq]' => $previousStartDate,
        'q[created_at_lteq]' => $previousEndDate,
    ]);

    $prevCustomers = $prevMembers['meta']['total_row_count'] ?? 0;
    $custChange = $prevCustomers > 0 ? round((($newCustomers - $prevCustomers) / $prevCustomers) * 100, 1) : 0;

    $analytics['kpis']['customers'] = [
        'value' => $newCustomers,
        'change' => $custChange,
        'label' => 'New Customers',
    ];

    // KPI: Product Utilisation
    $stockLevels = $api->get('stock_levels', ['per_page' => 100]);
    $totalOwned = 0;
    $totalBooked = 0;
    foreach ($stockLevels['stock_levels'] ?? [] as $level) {
        // Try different possible field names for stock quantity
        $owned = floatval($level['quantity_owned'] ?? $level['quantity'] ?? $level['stock_quantity'] ?? 0);
        $booked = floatval($level['quantity_booked'] ?? $level['booked'] ?? 0);
        $totalOwned += $owned;
        $totalBooked += $booked;
    }
    $utilisation = $totalOwned > 0 ? round(($totalBooked / $totalOwned) * 100, 1) : 0;

    $analytics['kpis']['utilisation'] = [
        'value' => $utilisation,
        'change' => 0,
        'label' => 'Product Utilisation',
        'format' => 'percent',
    ];

    // Chart: Revenue Trend (monthly)
    $revenueByMonth = [];
    $allInvoices = $api->get('invoices', [
        'per_page' => 100,
        'q[invoice_date_gteq]' => date('Y-m-d', strtotime('-12 months')),
        'q[s]' => 'invoice_date asc',
    ]);

    foreach ($allInvoices['invoices'] ?? [] as $inv) {
        $month = date('M Y', strtotime($inv['invoice_date'] ?? 'now'));
        $revenueByMonth[$month] = ($revenueByMonth[$month] ?? 0) + floatval($inv['total'] ?? 0);
    }

    // Get last 12 months
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('M Y', strtotime("-{$i} months"));
        $months[$month] = $revenueByMonth[$month] ?? 0;
    }

    $analytics['charts']['revenue_trend'] = [
        'labels' => array_keys($months),
        'values' => array_values($months),
    ];

    // Chart: Opportunities by Status
    $oppByStatus = [];
    $allOpportunities = $api->get('opportunities', [
        'per_page' => 100,
        'filtermode' => 'active',
    ]);

    foreach ($allOpportunities['opportunities'] ?? [] as $opp) {
        $status = $opp['status'] ?? 'Unknown';
        $oppByStatus[$status] = ($oppByStatus[$status] ?? 0) + 1;
    }

    $analytics['charts']['opp_status'] = [
        'labels' => array_keys($oppByStatus),
        'values' => array_values($oppByStatus),
    ];

    // Chart: Top Products by Revenue (from opportunity items)
    $productRevenue = [];
    $oppItems = $api->get('opportunity_items', [
        'per_page' => 100,
        'q[s]' => 'charge_total desc',
    ]);

    foreach ($oppItems['opportunity_items'] ?? [] as $item) {
        $productName = $item['product']['name'] ?? $item['name'] ?? 'Unknown';
        $revenue = floatval($item['charge_total'] ?? $item['total'] ?? 0);
        $productRevenue[$productName] = ($productRevenue[$productName] ?? 0) + $revenue;
    }

    // Sort and get top 10
    arsort($productRevenue);
    $topProducts = array_slice($productRevenue, 0, 10, true);

    $analytics['charts']['top_products'] = [
        'labels' => array_keys($topProducts),
        'values' => array_values($topProducts),
    ];

    // Chart: Customer Segments (by member type or tags)
    $memberTypes = [];
    $allMembers = $api->get('members', [
        'per_page' => 100,
    ]);

    foreach ($allMembers['members'] ?? [] as $member) {
        $type = $member['member_type_name'] ?? $member['type'] ?? 'Other';
        $memberTypes[$type] = ($memberTypes[$type] ?? 0) + 1;
    }

    $analytics['charts']['customer_segments'] = [
        'labels' => array_keys($memberTypes),
        'values' => array_values($memberTypes),
    ];

    // Timeline: Recent Activity
    $timeline = [];

    // Recent invoices
    $recentInvoices = $api->get('invoices', [
        'per_page' => 5,
        'q[s]' => 'created_at desc',
    ]);

    foreach ($recentInvoices['invoices'] ?? [] as $inv) {
        // Get member name from nested object or direct field
        $memberName = 'Unknown';
        if (isset($inv['member']['name'])) {
            $memberName = $inv['member']['name'];
        } elseif (isset($inv['member_name'])) {
            $memberName = $inv['member_name'];
        } elseif (isset($inv['billing_address']['name'])) {
            $memberName = $inv['billing_address']['name'];
        }

        $timeline[] = [
            'date' => $inv['created_at'] ?? $inv['invoice_date'] ?? date('Y-m-d'),
            'title' => 'Invoice #' . ($inv['number'] ?? $inv['id'] ?? '?') . ' - ' . ucfirst($inv['state'] ?? 'created'),
            'description' => $memberName . ' - ' . formatCurrency($inv['total'] ?? 0),
            'type' => 'invoice',
        ];
    }

    // Recent opportunities
    $recentOpps = $api->get('opportunities', [
        'per_page' => 5,
        'q[s]' => 'created_at desc',
    ]);

    foreach ($recentOpps['opportunities'] ?? [] as $opp) {
        // Get member name from nested object or direct field
        $memberName = 'Unknown';
        if (isset($opp['member']['name'])) {
            $memberName = $opp['member']['name'];
        } elseif (isset($opp['member_name'])) {
            $memberName = $opp['member_name'];
        } elseif (isset($opp['billing_address']['name'])) {
            $memberName = $opp['billing_address']['name'];
        }

        $timeline[] = [
            'date' => $opp['created_at'] ?? date('Y-m-d'),
            'title' => 'Opportunity: ' . ($opp['subject'] ?? 'Untitled'),
            'description' => $memberName . ' - ' . ucfirst($opp['status'] ?? 'draft'),
            'type' => 'opportunity',
        ];
    }

    // Sort timeline by date
    usort($timeline, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    $analytics['timeline'] = array_slice($timeline, 0, 10);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode($analytics);
