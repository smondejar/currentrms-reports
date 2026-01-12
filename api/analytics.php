<?php
/**
 * API: Analytics Data
 * Fetches live analytics data from CurrentRMS
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Increase error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

// Helper function to get value from multiple possible paths
function getFieldValue($item, $paths, $default = 0) {
    foreach ($paths as $path) {
        if (is_array($path)) {
            $value = $item;
            foreach ($path as $key) {
                if (is_array($value) && isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    $value = null;
                    break;
                }
            }
            if ($value !== null && $value !== '') {
                return $value;
            }
        } else {
            if (isset($item[$path]) && $item[$path] !== null && $item[$path] !== '') {
                return $item[$path];
            }
        }
    }
    return $default;
}

// Safe API call wrapper
function safeApiCall($api, $endpoint, $params = []) {
    try {
        $result = $api->get($endpoint, $params);
        return $result;
    } catch (Exception $e) {
        error_log("Analytics API call failed for {$endpoint}: " . $e->getMessage());
        return null;
    }
}

// Get date range
$days = (int) ($_GET['days'] ?? 30);
$startDate = date('Y-m-d', strtotime("-{$days} days"));
$endDate = date('Y-m-d');
$previousStartDate = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));
$previousEndDate = date('Y-m-d', strtotime("-{$days} days"));

// Initialize with default values
$analytics = [
    'kpis' => [
        'revenue' => ['value' => 0, 'change' => 0, 'label' => 'Total Revenue'],
        'opportunities' => ['value' => 0, 'change' => 0, 'label' => 'Active Opportunities'],
        'projects' => ['value' => 0, 'change' => 0, 'label' => 'Active Projects'],
        'utilisation' => ['value' => 0, 'change' => 0, 'label' => 'Product Utilisation', 'format' => 'percent'],
    ],
    'charts' => [
        'revenue_trend' => ['labels' => [], 'values' => []],
        'opp_status' => ['labels' => [], 'values' => []],
        'top_products' => ['labels' => [], 'values' => []],
        'customer_segments' => ['labels' => [], 'values' => []],
        'project_categories' => ['labels' => [], 'values' => []],
        'category_revenue' => ['labels' => [], 'values' => []],
        'opportunity_types' => ['labels' => [], 'values' => []],
    ],
    'timeline' => [],
];

// Field paths for totals
$invoiceTotalPaths = [
    ['totals', 'total'],
    ['totals', 'grand_total'],
    'total',
    'grand_total',
    'gross_total',
];

$oppTotalPaths = [
    ['totals', 'charge_total'],
    ['totals', 'grand_total'],
    'charge_total',
    'grand_total',
    'rental_charge_total',
];

// KPI: Total Revenue (from opportunities - more reliable than invoices)
$oppsForRevenue = safeApiCall($api, 'opportunities', [
    'per_page' => 100,
    'q[starts_at_gteq]' => $startDate,
    'q[starts_at_lteq]' => $endDate,
]);

if ($oppsForRevenue) {
    $totalRevenue = 0;
    foreach ($oppsForRevenue['opportunities'] ?? [] as $opp) {
        $total = getFieldValue($opp, $oppTotalPaths, 0);
        $totalRevenue += floatval($total);
    }

    // Get previous period
    $prevOppsForRevenue = safeApiCall($api, 'opportunities', [
        'per_page' => 100,
        'q[starts_at_gteq]' => $previousStartDate,
        'q[starts_at_lteq]' => $previousEndDate,
    ]);

    $prevRevenue = 0;
    if ($prevOppsForRevenue) {
        foreach ($prevOppsForRevenue['opportunities'] ?? [] as $opp) {
            $total = getFieldValue($opp, $oppTotalPaths, 0);
            $prevRevenue += floatval($total);
        }
    }

    $revenueChange = $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;

    $analytics['kpis']['revenue'] = [
        'value' => $totalRevenue,
        'change' => $revenueChange,
        'label' => 'Total Revenue',
    ];
}

// KPI: Active Opportunities
$activeOpps = safeApiCall($api, 'opportunities', [
    'per_page' => 1,
    'filtermode' => 'active',
]);

if ($activeOpps) {
    $oppCount = $activeOpps['meta']['total_row_count'] ?? count($activeOpps['opportunities'] ?? []);

    $analytics['kpis']['opportunities'] = [
        'value' => $oppCount,
        'change' => 0,
        'label' => 'Active Opportunities',
    ];
}

// KPI: Active Projects (replacing New Customers)
$activeProjects = safeApiCall($api, 'projects', [
    'per_page' => 1,
    'filtermode' => 'active',
]);

if ($activeProjects) {
    $projectCount = $activeProjects['meta']['total_row_count'] ?? count($activeProjects['projects'] ?? []);

    $analytics['kpis']['projects'] = [
        'value' => $projectCount,
        'change' => 0,
        'label' => 'Active Projects',
    ];
}

// KPI: Product Utilisation (simplified - just count products)
$products = safeApiCall($api, 'products', ['per_page' => 100]);

if ($products) {
    $totalOwned = 0;
    $totalBooked = 0;

    foreach ($products['products'] ?? [] as $product) {
        $owned = floatval(getFieldValue($product, [
            ['stock_level', 'quantity_owned'],
            'quantity_owned',
            'stock_method_quantity'
        ], 0));
        $booked = floatval(getFieldValue($product, [
            ['stock_level', 'quantity_booked'],
            'quantity_booked'
        ], 0));
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
}

// Chart: Revenue Trend (monthly from opportunities)
$allOpps = safeApiCall($api, 'opportunities', [
    'per_page' => 200,
    'q[starts_at_gteq]' => date('Y-m-d', strtotime('-12 months')),
    'q[s]' => 'starts_at asc',
]);

if ($allOpps) {
    $revenueByMonth = [];

    foreach ($allOpps['opportunities'] ?? [] as $opp) {
        $date = $opp['starts_at'] ?? $opp['created_at'] ?? null;
        if ($date) {
            $month = date('M Y', strtotime($date));
            $total = floatval(getFieldValue($opp, $oppTotalPaths, 0));
            $revenueByMonth[$month] = ($revenueByMonth[$month] ?? 0) + $total;
        }
    }

    // Get last 12 months in order
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('M Y', strtotime("-{$i} months"));
        $months[$month] = $revenueByMonth[$month] ?? 0;
    }

    $analytics['charts']['revenue_trend'] = [
        'labels' => array_keys($months),
        'values' => array_values($months),
    ];
}

// Chart: Opportunities by Status
$allActiveOpps = safeApiCall($api, 'opportunities', [
    'per_page' => 200,
    'filtermode' => 'active',
]);

if ($allActiveOpps) {
    $oppByStatus = [];

    foreach ($allActiveOpps['opportunities'] ?? [] as $opp) {
        $status = $opp['status'] ?? $opp['state'] ?? 'Unknown';
        $oppByStatus[$status] = ($oppByStatus[$status] ?? 0) + 1;
    }

    $analytics['charts']['opp_status'] = [
        'labels' => array_keys($oppByStatus),
        'values' => array_values($oppByStatus),
    ];
}

// Chart: Top Products by Revenue
if ($allOpps) {
    $productRevenue = [];

    foreach ($allOpps['opportunities'] ?? [] as $opp) {
        foreach ($opp['opportunity_items'] ?? [] as $item) {
            $productName = getFieldValue($item, [
                ['product', 'name'],
                'product_name',
                'name',
            ], 'Unknown');
            $revenue = floatval(getFieldValue($item, [
                'charge_total',
                'total',
                'price',
            ], 0));
            if ($revenue > 0 && $productName !== 'Unknown') {
                $productRevenue[$productName] = ($productRevenue[$productName] ?? 0) + $revenue;
            }
        }
    }

    if (!empty($productRevenue)) {
        arsort($productRevenue);
        $topProducts = array_slice($productRevenue, 0, 10, true);

        $analytics['charts']['top_products'] = [
            'labels' => array_keys($topProducts),
            'values' => array_values($topProducts),
        ];
    }
}

// Chart: Customer Segments
$allMembers = safeApiCall($api, 'members', ['per_page' => 100]);

if ($allMembers) {
    $memberTypes = [];

    foreach ($allMembers['members'] ?? [] as $member) {
        $type = $member['member_type_name'] ?? $member['type'] ?? 'Other';
        $memberTypes[$type] = ($memberTypes[$type] ?? 0) + 1;
    }

    $analytics['charts']['customer_segments'] = [
        'labels' => array_keys($memberTypes),
        'values' => array_values($memberTypes),
    ];
}

// Chart: Projects by Category
$allProjects = safeApiCall($api, 'projects', ['per_page' => 100]);

if ($allProjects) {
    $projectCategories = [];
    $categoryRevenue = [];

    foreach ($allProjects['projects'] ?? [] as $project) {
        // Try to get category from custom fields
        $category = 'Uncategorized';

        if (isset($project['custom_field_values']) && is_array($project['custom_field_values'])) {
            foreach ($project['custom_field_values'] as $cfv) {
                $fieldName = strtolower($cfv['custom_field_name'] ?? $cfv['name'] ?? '');
                if (in_array($fieldName, ['category', 'categories', 'project category', 'project type'])) {
                    $category = $cfv['value'] ?? $cfv['text_value'] ?? $category;
                    break;
                }
            }
        }

        // Fallback to direct fields
        if ($category === 'Uncategorized') {
            $category = getFieldValue($project, [
                ['custom_fields', 'category'],
                'category',
                'project_type',
            ], 'Uncategorized');
        }

        $projectCategories[$category] = ($projectCategories[$category] ?? 0) + 1;

        // Revenue by category
        $revenue = floatval(getFieldValue($project, [
            'revenue',
            'revenue_total',
            ['totals', 'charge_total'],
            'charge_total',
        ], 0));
        $categoryRevenue[$category] = ($categoryRevenue[$category] ?? 0) + $revenue;
    }

    $analytics['charts']['project_categories'] = [
        'labels' => array_keys($projectCategories),
        'values' => array_values($projectCategories),
    ];

    if (array_sum($categoryRevenue) > 0) {
        arsort($categoryRevenue);
        $analytics['charts']['category_revenue'] = [
            'labels' => array_keys($categoryRevenue),
            'values' => array_values($categoryRevenue),
        ];
    }
}

// Chart: Opportunity Types
if ($allActiveOpps) {
    $oppTypes = [];

    foreach ($allActiveOpps['opportunities'] ?? [] as $opp) {
        $type = 'Other';

        // Check custom field values
        if (isset($opp['custom_field_values']) && is_array($opp['custom_field_values'])) {
            foreach ($opp['custom_field_values'] as $cfv) {
                $fieldName = strtolower($cfv['custom_field_name'] ?? $cfv['name'] ?? '');
                if (in_array($fieldName, ['category', 'categories', 'type', 'opportunity type', 'booking type'])) {
                    $type = $cfv['value'] ?? $cfv['text_value'] ?? $type;
                    break;
                }
            }
        }

        $oppTypes[$type] = ($oppTypes[$type] ?? 0) + 1;
    }

    $analytics['charts']['opportunity_types'] = [
        'labels' => array_keys($oppTypes),
        'values' => array_values($oppTypes),
    ];
}

// Timeline: Recent Activity (simplified)
$recentOpps = safeApiCall($api, 'opportunities', [
    'per_page' => 10,
    'q[s]' => 'created_at desc',
]);

if ($recentOpps) {
    $timeline = [];

    foreach ($recentOpps['opportunities'] ?? [] as $opp) {
        $memberName = getFieldValue($opp, [
            ['member', 'name'],
            'member_name',
            ['billing_address', 'name'],
        ], 'Unknown');

        $timeline[] = [
            'date' => $opp['created_at'] ?? date('Y-m-d'),
            'title' => 'Opportunity: ' . ($opp['subject'] ?? 'Untitled'),
            'description' => $memberName . ' - ' . ucfirst($opp['status'] ?? 'draft'),
            'type' => 'opportunity',
        ];
    }

    $analytics['timeline'] = $timeline;
}

// Available widgets for customization
$analytics['available_widgets'] = [
    'kpis' => [
        ['id' => 'revenue', 'label' => 'Total Revenue', 'type' => 'stat'],
        ['id' => 'opportunities', 'label' => 'Active Opportunities', 'type' => 'stat'],
        ['id' => 'projects', 'label' => 'Active Projects', 'type' => 'stat'],
        ['id' => 'utilisation', 'label' => 'Product Utilisation', 'type' => 'stat'],
    ],
    'charts' => [
        ['id' => 'revenue_trend', 'label' => 'Revenue Trend', 'type' => 'line'],
        ['id' => 'opp_status', 'label' => 'Opportunities by Status', 'type' => 'doughnut'],
        ['id' => 'top_products', 'label' => 'Top Products by Revenue', 'type' => 'bar'],
        ['id' => 'customer_segments', 'label' => 'Customer Segments', 'type' => 'pie'],
        ['id' => 'project_categories', 'label' => 'Projects by Category', 'type' => 'pie'],
        ['id' => 'category_revenue', 'label' => 'Revenue by Category', 'type' => 'bar'],
        ['id' => 'opportunity_types', 'label' => 'Opportunity Types', 'type' => 'doughnut'],
    ],
];

echo json_encode($analytics);
