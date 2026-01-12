<?php
/**
 * API: Customer Revenue Report
 * Calculates total revenue per customer from opportunities
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
    // Get members (customers)
    $membersResponse = $api->get('members', [
        'per_page' => 200,
        'q[s]' => 'name asc'
    ]);

    $customers = [];
    foreach ($membersResponse['members'] ?? [] as $member) {
        $customers[$member['id']] = [
            'id' => $member['id'],
            'name' => $member['name'] ?? 'Unknown',
            'company' => $member['organisation']['name'] ?? $member['company_name'] ?? '',
            'email' => $member['primary_email']['address'] ?? $member['email'] ?? '',
            'type' => $member['type'] ?? 'Contact',
            'total_revenue' => 0,
            'opportunities_count' => 0,
            'invoices_count' => 0,
            'average_order_value' => 0,
        ];
    }

    // Get opportunities within date range
    $opportunities = $api->get('opportunities', [
        'per_page' => 200,
        'q[starts_at_gteq]' => $dateFrom,
        'q[starts_at_lteq]' => $dateTo,
    ]);

    // Process each opportunity
    foreach ($opportunities['opportunities'] ?? [] as $opp) {
        $memberId = $opp['member_id'] ?? $opp['member']['id'] ?? null;
        if ($memberId && isset($customers[$memberId])) {
            // Get revenue from opportunity
            $revenue = floatval(
                $opp['totals']['grand_total'] ??
                $opp['grand_total'] ??
                $opp['totals']['charge_total'] ??
                $opp['charge_total'] ?? 0
            );

            $customers[$memberId]['total_revenue'] += $revenue;
            $customers[$memberId]['opportunities_count']++;
        }
    }

    // Get invoices for invoice count
    try {
        $invoices = $api->get('invoices', [
            'per_page' => 200,
            'q[invoice_date_gteq]' => $dateFrom,
            'q[invoice_date_lteq]' => $dateTo,
        ]);

        foreach ($invoices['invoices'] ?? [] as $inv) {
            $memberId = $inv['member_id'] ?? $inv['member']['id'] ?? null;
            if ($memberId && isset($customers[$memberId])) {
                $customers[$memberId]['invoices_count']++;
            }
        }
    } catch (Exception $e) {
        // Invoices API may fail, continue without it
    }

    // Calculate average order value and filter out zero revenue customers
    $result = [];
    foreach ($customers as $customer) {
        if ($customer['opportunities_count'] > 0) {
            $customer['average_order_value'] = $customer['total_revenue'] / $customer['opportunities_count'];
        }
        // Include all customers, even with zero revenue for complete list
        $result[] = $customer;
    }

    // Sort by total_revenue descending
    usort($result, function($a, $b) {
        return $b['total_revenue'] <=> $a['total_revenue'];
    });

    // Paginate
    $total = count($result);
    $offset = ($page - 1) * $perPage;
    $paginatedData = array_slice($result, $offset, $perPage);

    echo json_encode([
        'data' => array_values($paginatedData),
        'meta' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ],
        'columns' => ['id', 'name', 'company', 'email', 'type', 'total_revenue', 'opportunities_count', 'invoices_count', 'average_order_value'],
        'column_config' => [
            'id' => ['label' => 'ID', 'type' => 'number'],
            'name' => ['label' => 'Customer', 'type' => 'string'],
            'company' => ['label' => 'Company', 'type' => 'string'],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'type' => ['label' => 'Type', 'type' => 'string'],
            'total_revenue' => ['label' => 'Total Revenue', 'type' => 'currency'],
            'opportunities_count' => ['label' => 'Orders', 'type' => 'number'],
            'invoices_count' => ['label' => 'Invoices', 'type' => 'number'],
            'average_order_value' => ['label' => 'Avg Order Value', 'type' => 'currency'],
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch data: ' . $e->getMessage()]);
}
