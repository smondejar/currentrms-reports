<?php
/**
 * API: Analytics Data
 * Fetches live analytics data from CurrentRMS
 */

// Clean output buffer to ensure pure JSON response
ob_start();

require_once __DIR__ . '/../includes/bootstrap.php';

// Clear any output from bootstrap
ob_end_clean();
ob_start();

header('Content-Type: application/json');

// Increase error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check authentication
if (!Auth::check()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check permission
if (!Auth::can(Permissions::VIEW_ANALYTICS)) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// Get API client
$api = getApiClient();
if (!$api) {
    ob_end_clean();
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

// Initialize analytics data
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
        'projects_by_category' => ['labels' => [], 'values' => []],
        'revenue_by_category' => ['labels' => [], 'values' => []],
    ],
    'timeline' => [],
    'debug' => [],
];

// CurrentRMS field paths for totals (top-level fields based on actual API response)
$oppTotalPaths = [
    'charge_total',           // Primary field at top level
    'rental_charge_total',    // Rental charges
    'sale_charge_total',      // Sale charges
    'service_charge_total',   // Service charges
    'charge_including_tax_total',
    'invoice_charge_total',
];

// =====================
// KPI: Total Revenue
// =====================
// First try with date filter, then without if no results
$oppsForRevenue = safeApiCall($api, 'opportunities', [
    'per_page' => 200,
    'q[starts_at_gteq]' => $startDate,
    'q[starts_at_lteq]' => $endDate,
]);

// If no opportunities in date range, try without date filter
if (!$oppsForRevenue || empty($oppsForRevenue['opportunities'])) {
    $oppsForRevenue = safeApiCall($api, 'opportunities', [
        'per_page' => 200,
    ]);
    $analytics['debug']['date_filter_bypassed'] = true;
}

if ($oppsForRevenue) {
    $totalRevenue = 0;
    $oppCount = count($oppsForRevenue['opportunities'] ?? []);

    // Debug: capture first opportunity structure
    if (!empty($oppsForRevenue['opportunities'])) {
        $firstOpp = $oppsForRevenue['opportunities'][0];
        $moneyFields = [];
        foreach ($firstOpp as $key => $value) {
            if (!is_array($value) && (
                stripos($key, 'total') !== false ||
                stripos($key, 'charge') !== false ||
                stripos($key, 'revenue') !== false ||
                stripos($key, 'amount') !== false
            )) {
                $moneyFields[$key] = $value;
            }
        }
        $analytics['debug']['first_opp_money_fields'] = $moneyFields;
        $analytics['debug']['first_opp_all_keys'] = array_keys($firstOpp);
    }

    foreach ($oppsForRevenue['opportunities'] ?? [] as $opp) {
        $total = getFieldValue($opp, $oppTotalPaths, 0);
        $totalRevenue += floatval($total);
    }

    $analytics['debug']['opp_count'] = $oppCount;
    $analytics['debug']['revenue_calculated'] = $totalRevenue;
    $analytics['kpis']['revenue']['value'] = $totalRevenue;
    $analytics['kpis']['opportunities']['value'] = $oppCount;

    // Previous period for comparison
    $prevOpps = safeApiCall($api, 'opportunities', [
        'per_page' => 100,
        'q[starts_at_gteq]' => $previousStartDate,
        'q[starts_at_lteq]' => $previousEndDate,
    ]);

    if ($prevOpps) {
        $prevRevenue = 0;
        foreach ($prevOpps['opportunities'] ?? [] as $opp) {
            $prevRevenue += floatval(getFieldValue($opp, $oppTotalPaths, 0));
        }

        if ($prevRevenue > 0) {
            $analytics['kpis']['revenue']['change'] = round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1);
        }

        $prevCount = count($prevOpps['opportunities'] ?? []);
        if ($prevCount > 0) {
            $analytics['kpis']['opportunities']['change'] = round((($oppCount - $prevCount) / $prevCount) * 100, 1);
        }
    }
}

// =====================
// KPI: Active Projects
// =====================
// Include opportunities in project fetch if supported
$projectsResponse = safeApiCall($api, 'projects', [
    'per_page' => 100,
    'include[]' => 'opportunities',
]);

// If include didn't work, try without
if (!$projectsResponse || empty($projectsResponse['projects'])) {
    $projectsResponse = safeApiCall($api, 'projects', [
        'per_page' => 100,
    ]);
}

if ($projectsResponse) {
    $projectCount = count($projectsResponse['projects'] ?? []);
    $analytics['kpis']['projects']['value'] = $projectCount;

    // Debug project structure
    if (!empty($projectsResponse['projects'])) {
        $firstProject = $projectsResponse['projects'][0];
        $analytics['debug']['first_project_keys'] = array_keys($firstProject);
        $analytics['debug']['first_project_has_opps'] = isset($firstProject['opportunities']);
        $analytics['debug']['first_project_custom_fields'] = $firstProject['custom_fields'] ?? 'NOT_SET';
        $analytics['debug']['first_project_name'] = $firstProject['name'] ?? 'NOT_SET';
    }
}

// =====================
// KPI: Product Utilisation
// =====================
$stockResponse = safeApiCall($api, 'stock_levels', ['per_page' => 100]);
if ($stockResponse) {
    $totalStock = 0;
    $bookedStock = 0;
    foreach ($stockResponse['stock_levels'] ?? [] as $stock) {
        $qty = floatval($stock['quantity_owned'] ?? $stock['quantity'] ?? 0);
        $booked = floatval($stock['quantity_booked'] ?? 0);
        $totalStock += $qty;
        $bookedStock += $booked;
    }

    if ($totalStock > 0) {
        $analytics['kpis']['utilisation']['value'] = round(($bookedStock / $totalStock) * 100, 1);
    }
}

// =====================
// Chart: Revenue Trend
// =====================
if ($oppsForRevenue) {
    $revenueByPeriod = [];
    foreach ($oppsForRevenue['opportunities'] ?? [] as $opp) {
        $date = $opp['starts_at'] ?? $opp['created_at'] ?? null;
        if ($date) {
            $period = ($days <= 30) ? date('M d', strtotime($date)) : date('M Y', strtotime($date));
            $total = getFieldValue($opp, $oppTotalPaths, 0);
            $revenueByPeriod[$period] = ($revenueByPeriod[$period] ?? 0) + floatval($total);
        }
    }

    uksort($revenueByPeriod, function($a, $b) {
        return strtotime($a) - strtotime($b);
    });

    $analytics['charts']['revenue_trend'] = [
        'labels' => array_keys($revenueByPeriod),
        'values' => array_values($revenueByPeriod),
    ];
}

// =====================
// Chart: Opportunities by Status
// =====================
if ($oppsForRevenue) {
    $byStatus = [];
    foreach ($oppsForRevenue['opportunities'] ?? [] as $opp) {
        $status = $opp['status'] ?? $opp['state'] ?? 'Unknown';
        $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
    }

    $analytics['charts']['opp_status'] = [
        'labels' => array_keys($byStatus),
        'values' => array_values($byStatus),
    ];
}

// =====================
// Chart: Customer Segments
// =====================
if ($oppsForRevenue) {
    $customerRevenue = [];
    foreach ($oppsForRevenue['opportunities'] ?? [] as $opp) {
        $name = $opp['member']['name'] ?? $opp['billing_address']['name'] ?? $opp['subject'] ?? 'Unknown';
        $total = getFieldValue($opp, $oppTotalPaths, 0);
        $customerRevenue[$name] = ($customerRevenue[$name] ?? 0) + floatval($total);
    }

    arsort($customerRevenue);
    $topCustomers = array_slice($customerRevenue, 0, 8, true);

    $analytics['charts']['customer_segments'] = [
        'labels' => array_keys($topCustomers),
        'values' => array_values($topCustomers),
    ];
}

// =====================
// Chart: Top Products by Revenue
// =====================
// Try to get opportunity_items - first with date filter, then without
$opportunityItems = safeApiCall($api, 'opportunity_items', [
    'per_page' => 200,
]);

if ($opportunityItems) {
    $productRevenue = [];
    $analytics['debug']['opportunity_items_count'] = count($opportunityItems['opportunity_items'] ?? []);

    // Debug first item structure
    if (!empty($opportunityItems['opportunity_items'])) {
        $firstItem = $opportunityItems['opportunity_items'][0];
        $analytics['debug']['first_item_keys'] = array_keys($firstItem);
        $analytics['debug']['first_item_charge'] = $firstItem['charge_total'] ?? $firstItem['total'] ?? 'NOT_FOUND';
        $analytics['debug']['first_item_product'] = $firstItem['product']['name'] ?? $firstItem['name'] ?? 'NOT_FOUND';
    }

    foreach ($opportunityItems['opportunity_items'] ?? [] as $item) {
        $productName = $item['product']['name'] ?? $item['name'] ?? $item['item_name'] ?? 'Unknown Product';
        // Try multiple field names for the total
        $itemTotal = floatval(
            $item['charge_total'] ??
            $item['total'] ??
            $item['price_total'] ??
            $item['row_total'] ??
            $item['subtotal'] ??
            ($item['quantity'] ?? 0) * ($item['price'] ?? 0)
        );
        if ($productName !== 'Unknown Product') {
            $productRevenue[$productName] = ($productRevenue[$productName] ?? 0) + $itemTotal;
        }
    }

    arsort($productRevenue);
    $topProducts = array_slice($productRevenue, 0, 10, true);

    $analytics['charts']['top_products'] = [
        'labels' => array_keys($topProducts),
        'values' => array_values($topProducts),
    ];
    $analytics['debug']['top_products_count'] = count($productRevenue);
}

// =====================
// Chart: Projects by Category & Revenue by Category
// =====================
if ($projectsResponse) {
    $categoryCount = [];
    $categoryRevenue = [];
    $projectOpportunityMap = [];

    // Build map of project_id -> opportunities from oppsForRevenue if projects don't have embedded opportunities
    if ($oppsForRevenue) {
        foreach ($oppsForRevenue['opportunities'] ?? [] as $opp) {
            $projectId = $opp['project_id'] ?? $opp['project']['id'] ?? null;
            if ($projectId) {
                if (!isset($projectOpportunityMap[$projectId])) {
                    $projectOpportunityMap[$projectId] = [];
                }
                $projectOpportunityMap[$projectId][] = $opp;
            }
        }
    }
    $analytics['debug']['project_opp_map_count'] = count($projectOpportunityMap);

    foreach ($projectsResponse['projects'] ?? [] as $project) {
        $projectId = $project['id'] ?? null;

        // Get category from multiple possible sources
        $category = null;
        if (!empty($project['custom_fields']['category'])) {
            $category = $project['custom_fields']['category'];
        } elseif (!empty($project['category'])) {
            $category = $project['category'];
        } elseif (!empty($project['project_type'])) {
            $category = $project['project_type'];
        } elseif (!empty($project['type'])) {
            $category = $project['type'];
        }

        // If still null, use status or default
        if (empty($category)) {
            $category = $project['status'] ?? 'Uncategorized';
        }

        // Count projects per category
        $categoryCount[$category] = ($categoryCount[$category] ?? 0) + 1;

        // Calculate project revenue
        $projectRev = 0;

        // First try embedded opportunities
        if (!empty($project['opportunities'])) {
            foreach ($project['opportunities'] as $opp) {
                $projectRev += floatval($opp['charge_total'] ?? 0);
            }
        }
        // If no embedded opportunities, try from our map
        elseif ($projectId && isset($projectOpportunityMap[$projectId])) {
            foreach ($projectOpportunityMap[$projectId] as $opp) {
                $projectRev += floatval($opp['charge_total'] ?? 0);
            }
        }

        // Sum revenue per category
        $categoryRevenue[$category] = ($categoryRevenue[$category] ?? 0) + $projectRev;
    }

    // Sort by count for projects by category
    arsort($categoryCount);
    $analytics['charts']['projects_by_category'] = [
        'labels' => array_keys($categoryCount),
        'values' => array_values($categoryCount),
    ];

    // Sort by revenue for revenue by category
    arsort($categoryRevenue);
    $analytics['charts']['revenue_by_category'] = [
        'labels' => array_keys($categoryRevenue),
        'values' => array_values($categoryRevenue),
    ];

    $analytics['debug']['project_categories'] = $categoryCount;
    $analytics['debug']['category_revenue'] = $categoryRevenue;
}

// =====================
// Timeline: Recent Activity
// =====================
if ($oppsForRevenue) {
    $timeline = [];
    foreach (array_slice($oppsForRevenue['opportunities'] ?? [], 0, 10) as $opp) {
        $memberName = $opp['member']['name'] ?? $opp['billing_address']['name'] ?? 'Unknown';
        $timeline[] = [
            'date' => $opp['created_at'] ?? $opp['starts_at'] ?? date('Y-m-d'),
            'title' => 'Opportunity: ' . ($opp['subject'] ?? 'Untitled'),
            'description' => $memberName . ' - ' . ucfirst($opp['status'] ?? 'pending'),
        ];
    }

    usort($timeline, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    $analytics['timeline'] = $timeline;
}

// Available widgets
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
        ['id' => 'customer_segments', 'label' => 'Customer Segments', 'type' => 'pie'],
        ['id' => 'top_products', 'label' => 'Top Products by Revenue', 'type' => 'bar'],
        ['id' => 'projects_by_category', 'label' => 'Projects by Category', 'type' => 'doughnut'],
        ['id' => 'revenue_by_category', 'label' => 'Revenue by Category', 'type' => 'bar'],
    ],
];

// Clear any error output and send clean JSON
ob_end_clean();
echo json_encode($analytics, JSON_UNESCAPED_UNICODE);
