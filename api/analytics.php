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

// Field paths for invoice total - try multiple possibilities
$invoiceTotalPaths = [
    ['totals', 'total'],
    ['totals', 'grand_total'],
    'total',
    'grand_total',
    'gross_total',
    ['totals', 'gross_total'],
];

// KPI: Total Revenue (from invoices in date range)
try {
    $invoices = $api->get('invoices', [
        'per_page' => 100,
        'q[invoice_date_gteq]' => $startDate,
        'q[invoice_date_lteq]' => $endDate,
    ]);

    $totalRevenue = 0;
    foreach ($invoices['invoices'] ?? [] as $inv) {
        $total = getFieldValue($inv, $invoiceTotalPaths, 0);
        $totalRevenue += floatval($total);
    }

    // If no invoice revenue, try opportunity revenue instead
    if ($totalRevenue == 0) {
        $opps = $api->get('opportunities', [
            'per_page' => 100,
            'q[starts_at_gteq]' => $startDate,
            'q[starts_at_lteq]' => $endDate,
        ]);

        $oppTotalPaths = [
            ['totals', 'charge_total'],
            ['totals', 'grand_total'],
            'charge_total',
            'grand_total',
            'rental_charge_total',
        ];

        foreach ($opps['opportunities'] ?? [] as $opp) {
            $total = getFieldValue($opp, $oppTotalPaths, 0);
            $totalRevenue += floatval($total);
        }
    }

    // Previous period revenue
    $prevInvoices = $api->get('invoices', [
        'per_page' => 100,
        'q[invoice_date_gteq]' => $previousStartDate,
        'q[invoice_date_lteq]' => $previousEndDate,
    ]);

    $prevRevenue = 0;
    foreach ($prevInvoices['invoices'] ?? [] as $inv) {
        $total = getFieldValue($inv, $invoiceTotalPaths, 0);
        $prevRevenue += floatval($total);
    }

    $revenueChange = $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;

    $analytics['kpis']['revenue'] = [
        'value' => $totalRevenue,
        'change' => $revenueChange,
        'label' => 'Total Revenue',
    ];
} catch (Exception $e) {
    $analytics['kpis']['revenue'] = ['value' => 0, 'change' => 0, 'label' => 'Total Revenue'];
}

// KPI: Opportunities (active in date range)
try {
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
} catch (Exception $e) {
    $analytics['kpis']['opportunities'] = ['value' => 0, 'change' => 0, 'label' => 'Active Opportunities'];
}

// KPI: New Customers (members created in date range)
try {
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
} catch (Exception $e) {
    $analytics['kpis']['customers'] = ['value' => 0, 'change' => 0, 'label' => 'New Customers'];
}

// KPI: Product Utilisation (from products with stock method)
try {
    $products = $api->get('products', ['per_page' => 100]);
    $totalOwned = 0;
    $totalBooked = 0;

    $stockPaths = [
        'owned' => [['stock_level', 'quantity_owned'], 'quantity_owned', 'stock_method_quantity', ['stock', 'owned']],
        'booked' => [['stock_level', 'quantity_booked'], 'quantity_booked', ['stock', 'booked']],
    ];

    foreach ($products['products'] ?? [] as $product) {
        $owned = floatval(getFieldValue($product, $stockPaths['owned'], 0));
        $booked = floatval(getFieldValue($product, $stockPaths['booked'], 0));
        $totalOwned += $owned;
        $totalBooked += $booked;
    }

    // If no data from products, try stock_levels endpoint
    if ($totalOwned == 0) {
        $stockLevels = $api->get('stock_levels', ['per_page' => 100]);
        foreach ($stockLevels['stock_levels'] ?? [] as $level) {
            $owned = floatval(getFieldValue($level, [
                'quantity_owned', 'quantity', 'stock_quantity', 'owned'
            ], 0));
            $booked = floatval(getFieldValue($level, [
                'quantity_booked', 'booked', 'quantity_allocated'
            ], 0));
            $totalOwned += $owned;
            $totalBooked += $booked;
        }
    }

    $utilisation = $totalOwned > 0 ? round(($totalBooked / $totalOwned) * 100, 1) : 0;

    $analytics['kpis']['utilisation'] = [
        'value' => $utilisation,
        'change' => 0,
        'label' => 'Product Utilisation',
        'format' => 'percent',
    ];
} catch (Exception $e) {
    $analytics['kpis']['utilisation'] = ['value' => 0, 'change' => 0, 'label' => 'Product Utilisation'];
}

// Chart: Revenue Trend (monthly) - use opportunities for more reliable data
try {
    $revenueByMonth = [];

    // Get opportunities from last 12 months
    $allOpps = $api->get('opportunities', [
        'per_page' => 200,
        'q[starts_at_gteq]' => date('Y-m-d', strtotime('-12 months')),
        'q[s]' => 'starts_at asc',
    ]);

    $oppTotalPaths = [
        ['totals', 'charge_total'],
        ['totals', 'grand_total'],
        'charge_total',
        'grand_total',
        'rental_charge_total',
    ];

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
} catch (Exception $e) {
    $analytics['charts']['revenue_trend'] = ['labels' => [], 'values' => []];
}

// Chart: Opportunities by Status
try {
    $oppByStatus = [];
    $allOpportunities = $api->get('opportunities', [
        'per_page' => 200,
        'filtermode' => 'active',
    ]);

    foreach ($allOpportunities['opportunities'] ?? [] as $opp) {
        $status = $opp['status'] ?? $opp['state'] ?? 'Unknown';
        $oppByStatus[$status] = ($oppByStatus[$status] ?? 0) + 1;
    }

    $analytics['charts']['opp_status'] = [
        'labels' => array_keys($oppByStatus),
        'values' => array_values($oppByStatus),
    ];
} catch (Exception $e) {
    $analytics['charts']['opp_status'] = ['labels' => [], 'values' => []];
}

// Chart: Top Products by Revenue (from opportunities)
try {
    $productRevenue = [];

    // Get opportunities with items
    $oppsWithItems = $api->get('opportunities', [
        'per_page' => 50,
        'include[]' => 'opportunity_items',
    ]);

    $itemTotalPaths = [
        'charge_total',
        'total',
        ['totals', 'charge_total'],
        'rental_charge_total',
        'price',
    ];

    foreach ($oppsWithItems['opportunities'] ?? [] as $opp) {
        foreach ($opp['opportunity_items'] ?? [] as $item) {
            $productName = getFieldValue($item, [
                ['product', 'name'],
                'product_name',
                'name',
                'description',
            ], 'Unknown');
            $revenue = floatval(getFieldValue($item, $itemTotalPaths, 0));
            if ($revenue > 0) {
                $productRevenue[$productName] = ($productRevenue[$productName] ?? 0) + $revenue;
            }
        }
    }

    // If no items found, try opportunity_items endpoint directly
    if (empty($productRevenue)) {
        $oppItems = $api->get('opportunity_items', [
            'per_page' => 100,
        ]);

        foreach ($oppItems['opportunity_items'] ?? [] as $item) {
            $productName = getFieldValue($item, [
                ['product', 'name'],
                'product_name',
                'name',
                'description',
            ], 'Unknown');
            $revenue = floatval(getFieldValue($item, $itemTotalPaths, 0));
            if ($revenue > 0) {
                $productRevenue[$productName] = ($productRevenue[$productName] ?? 0) + $revenue;
            }
        }
    }

    // Sort and get top 10
    arsort($productRevenue);
    $topProducts = array_slice($productRevenue, 0, 10, true);

    $analytics['charts']['top_products'] = [
        'labels' => array_keys($topProducts),
        'values' => array_values($topProducts),
    ];
} catch (Exception $e) {
    $analytics['charts']['top_products'] = ['labels' => [], 'values' => []];
}

// Chart: Customer Segments (by member type)
try {
    $memberTypes = [];
    $allMembers = $api->get('members', [
        'per_page' => 100,
    ]);

    foreach ($allMembers['members'] ?? [] as $member) {
        $type = $member['member_type_name'] ?? $member['type'] ?? $member['member_type'] ?? 'Other';
        $memberTypes[$type] = ($memberTypes[$type] ?? 0) + 1;
    }

    $analytics['charts']['customer_segments'] = [
        'labels' => array_keys($memberTypes),
        'values' => array_values($memberTypes),
    ];
} catch (Exception $e) {
    $analytics['charts']['customer_segments'] = ['labels' => [], 'values' => []];
}

// Chart: Projects by Category (custom field)
try {
    $projectCategories = [];
    $projects = $api->get('projects', [
        'per_page' => 100,
    ]);

    foreach ($projects['projects'] ?? [] as $project) {
        // Try to get category from custom fields or direct field
        $category = getFieldValue($project, [
            ['custom_fields', 'category'],
            ['custom_fields', 'Category'],
            ['custom_fields', 'categories'],
            ['custom_fields', 'Categories'],
            'category',
            'project_type',
            'type',
        ], 'Uncategorized');

        // Also check for custom field values in different formats
        if (isset($project['custom_field_values']) && is_array($project['custom_field_values'])) {
            foreach ($project['custom_field_values'] as $cfv) {
                $fieldName = strtolower($cfv['custom_field_name'] ?? $cfv['name'] ?? '');
                if (in_array($fieldName, ['category', 'categories', 'project category', 'project type'])) {
                    $category = $cfv['value'] ?? $cfv['text_value'] ?? $category;
                    break;
                }
            }
        }

        $projectCategories[$category] = ($projectCategories[$category] ?? 0) + 1;
    }

    $analytics['charts']['project_categories'] = [
        'labels' => array_keys($projectCategories),
        'values' => array_values($projectCategories),
    ];
} catch (Exception $e) {
    $analytics['charts']['project_categories'] = ['labels' => [], 'values' => []];
}

// Chart: Revenue by Project Category
try {
    $categoryRevenue = [];
    $projects = $api->get('projects', [
        'per_page' => 100,
        'include[]' => 'opportunities',
    ]);

    $revenuePaths = [
        'revenue',
        'revenue_total',
        ['totals', 'charge_total'],
        'charge_total',
        'budget',
    ];

    foreach ($projects['projects'] ?? [] as $project) {
        $category = getFieldValue($project, [
            ['custom_fields', 'category'],
            ['custom_fields', 'Category'],
            'category',
            'project_type',
        ], 'Uncategorized');

        // Check custom field values
        if (isset($project['custom_field_values']) && is_array($project['custom_field_values'])) {
            foreach ($project['custom_field_values'] as $cfv) {
                $fieldName = strtolower($cfv['custom_field_name'] ?? $cfv['name'] ?? '');
                if (in_array($fieldName, ['category', 'categories'])) {
                    $category = $cfv['value'] ?? $cfv['text_value'] ?? $category;
                    break;
                }
            }
        }

        $revenue = floatval(getFieldValue($project, $revenuePaths, 0));
        $categoryRevenue[$category] = ($categoryRevenue[$category] ?? 0) + $revenue;
    }

    arsort($categoryRevenue);

    $analytics['charts']['category_revenue'] = [
        'labels' => array_keys($categoryRevenue),
        'values' => array_values($categoryRevenue),
    ];
} catch (Exception $e) {
    $analytics['charts']['category_revenue'] = ['labels' => [], 'values' => []];
}

// Chart: Opportunity Types/Categories
try {
    $oppTypes = [];
    $oppsWithTypes = $api->get('opportunities', [
        'per_page' => 100,
    ]);

    foreach ($oppsWithTypes['opportunities'] ?? [] as $opp) {
        // Try custom fields first
        $type = getFieldValue($opp, [
            ['custom_fields', 'category'],
            ['custom_fields', 'type'],
            ['custom_fields', 'opportunity_type'],
            'opportunity_type',
            'booking_type',
        ], null);

        // Check custom field values
        if ($type === null && isset($opp['custom_field_values']) && is_array($opp['custom_field_values'])) {
            foreach ($opp['custom_field_values'] as $cfv) {
                $fieldName = strtolower($cfv['custom_field_name'] ?? $cfv['name'] ?? '');
                if (in_array($fieldName, ['category', 'categories', 'type', 'opportunity type', 'booking type'])) {
                    $type = $cfv['value'] ?? $cfv['text_value'] ?? null;
                    break;
                }
            }
        }

        if ($type === null) {
            $type = 'Other';
        }

        $oppTypes[$type] = ($oppTypes[$type] ?? 0) + 1;
    }

    $analytics['charts']['opportunity_types'] = [
        'labels' => array_keys($oppTypes),
        'values' => array_values($oppTypes),
    ];
} catch (Exception $e) {
    $analytics['charts']['opportunity_types'] = ['labels' => [], 'values' => []];
}

// Timeline: Recent Activity
try {
    $timeline = [];

    // Recent invoices
    $recentInvoices = $api->get('invoices', [
        'per_page' => 5,
        'q[s]' => 'created_at desc',
    ]);

    foreach ($recentInvoices['invoices'] ?? [] as $inv) {
        $memberName = getFieldValue($inv, [
            ['member', 'name'],
            'member_name',
            ['billing_address', 'name'],
        ], 'Unknown');

        $total = getFieldValue($inv, $invoiceTotalPaths, 0);

        $timeline[] = [
            'date' => $inv['created_at'] ?? $inv['invoice_date'] ?? date('Y-m-d'),
            'title' => 'Invoice #' . ($inv['number'] ?? $inv['id'] ?? '?') . ' - ' . ucfirst($inv['state'] ?? 'created'),
            'description' => $memberName . ' - ' . formatCurrency($total),
            'type' => 'invoice',
        ];
    }

    // Recent opportunities
    $recentOpps = $api->get('opportunities', [
        'per_page' => 5,
        'q[s]' => 'created_at desc',
    ]);

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

    // Sort timeline by date
    usort($timeline, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    $analytics['timeline'] = array_slice($timeline, 0, 10);
} catch (Exception $e) {
    $analytics['timeline'] = [];
}

// Available widgets for customization
$analytics['available_widgets'] = [
    'kpis' => [
        ['id' => 'revenue', 'label' => 'Total Revenue', 'type' => 'stat'],
        ['id' => 'opportunities', 'label' => 'Active Opportunities', 'type' => 'stat'],
        ['id' => 'customers', 'label' => 'New Customers', 'type' => 'stat'],
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
