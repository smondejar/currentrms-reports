<?php
/**
 * API: Get Module Configuration
 * Returns static module configuration (columns, filters) - does NOT require CurrentRMS API
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Module configuration is static - we don't need the API client for this
// Create a mock API client or use null
$api = getApiClient();

// If no API client, create ReportBuilder without it for static config access
if ($api) {
    $builder = new ReportBuilder($api);
} else {
    // Create a minimal mock for getting static config
    $builder = new class {
        private array $modules;

        public function __construct() {
            $this->initModules();
        }

        private function initModules(): void {
            $this->modules = [
                'products' => [
                    'name' => 'Products',
                    'icon' => 'box',
                    'endpoint' => 'products',
                    'columns' => [
                        'id' => ['label' => 'ID', 'type' => 'number'],
                        'name' => ['label' => 'Name', 'type' => 'string'],
                        'product_group_name' => ['label' => 'Group', 'type' => 'string'],
                        'type' => ['label' => 'Type', 'type' => 'string'],
                        'rental_rate' => ['label' => 'Rental Rate', 'type' => 'currency'],
                        'sale_price' => ['label' => 'Sale Price', 'type' => 'currency'],
                        'replacement_charge' => ['label' => 'Replacement Value', 'type' => 'currency'],
                        'weight' => ['label' => 'Weight', 'type' => 'number'],
                        'barcode' => ['label' => 'Barcode', 'type' => 'string'],
                        'quantity_owned' => ['label' => 'Qty Owned', 'type' => 'number'],
                        'quantity_available' => ['label' => 'Qty Available', 'type' => 'number'],
                        'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                        'updated_at' => ['label' => 'Updated', 'type' => 'datetime'],
                    ],
                    'filters' => [
                        'name' => ['label' => 'Name', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start', 'end']],
                        'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['Product', 'Accessory', 'Consumable', 'Service'], 'predicates' => ['eq']],
                        'rental_rate' => ['label' => 'Rental Rate', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                        'quantity_owned' => ['label' => 'Qty Owned', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt']],
                        'created_at' => ['label' => 'Created Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    ],
                ],
                'members' => [
                    'name' => 'Contacts & Organizations',
                    'icon' => 'users',
                    'endpoint' => 'members',
                    'columns' => [
                        'id' => ['label' => 'ID', 'type' => 'number'],
                        'name' => ['label' => 'Name', 'type' => 'string'],
                        'type' => ['label' => 'Type', 'type' => 'string'],
                        'company' => ['label' => 'Company', 'type' => 'string'],
                        'email' => ['label' => 'Email', 'type' => 'email'],
                        'phone' => ['label' => 'Phone', 'type' => 'string'],
                        'address_street' => ['label' => 'Street', 'type' => 'string'],
                        'address_city' => ['label' => 'City', 'type' => 'string'],
                        'address_postcode' => ['label' => 'Postcode', 'type' => 'string'],
                        'address_country_name' => ['label' => 'Country', 'type' => 'string'],
                        'balance' => ['label' => 'Balance', 'type' => 'currency'],
                        'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                    ],
                    'filters' => [
                        'name' => ['label' => 'Name', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start']],
                        'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['Contact', 'Organisation', 'User', 'Venue', 'Vehicle'], 'predicates' => ['eq']],
                        'email' => ['label' => 'Email', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                        'address_city' => ['label' => 'City', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                        'created_at' => ['label' => 'Created Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    ],
                ],
                'opportunities' => [
                    'name' => 'Opportunities',
                    'icon' => 'target',
                    'endpoint' => 'opportunities',
                    'columns' => [
                        'id' => ['label' => 'ID', 'type' => 'number'],
                        'subject' => ['label' => 'Subject', 'type' => 'string'],
                        'member_name' => ['label' => 'Customer', 'type' => 'string'],
                        'status' => ['label' => 'Status', 'type' => 'string'],
                        'state' => ['label' => 'State', 'type' => 'string'],
                        'starts_at' => ['label' => 'Starts', 'type' => 'datetime'],
                        'ends_at' => ['label' => 'Ends', 'type' => 'datetime'],
                        'charge_total' => ['label' => 'Charge Total', 'type' => 'currency'],
                        'tax_total' => ['label' => 'Tax Total', 'type' => 'currency'],
                        'grand_total' => ['label' => 'Grand Total', 'type' => 'currency'],
                        'venue_name' => ['label' => 'Venue', 'type' => 'string'],
                        'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                    ],
                    'filters' => [
                        'subject' => ['label' => 'Subject', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                        'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['Provisional', 'Confirmed', 'Reserved', 'Checked Out', 'Closed'], 'predicates' => ['eq']],
                        'state' => ['label' => 'State', 'type' => 'select', 'options' => ['draft', 'quote_sent', 'order_confirmed', 'active', 'closed'], 'predicates' => ['eq']],
                        'starts_at' => ['label' => 'Start Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                        'ends_at' => ['label' => 'End Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                        'grand_total' => ['label' => 'Grand Total', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    ],
                ],
                'invoices' => [
                    'name' => 'Invoices',
                    'icon' => 'file-text',
                    'endpoint' => 'invoices',
                    'columns' => [
                        'id' => ['label' => 'ID', 'type' => 'number'],
                        'number' => ['label' => 'Invoice #', 'type' => 'string'],
                        'member_name' => ['label' => 'Customer', 'type' => 'string'],
                        'status' => ['label' => 'Status', 'type' => 'string'],
                        'state' => ['label' => 'State', 'type' => 'string'],
                        'invoice_date' => ['label' => 'Invoice Date', 'type' => 'date'],
                        'due_date' => ['label' => 'Due Date', 'type' => 'date'],
                        'subtotal' => ['label' => 'Subtotal', 'type' => 'currency'],
                        'tax_total' => ['label' => 'Tax', 'type' => 'currency'],
                        'total' => ['label' => 'Total', 'type' => 'currency'],
                        'amount_paid' => ['label' => 'Paid', 'type' => 'currency'],
                        'balance' => ['label' => 'Balance', 'type' => 'currency'],
                        'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                    ],
                    'filters' => [
                        'number' => ['label' => 'Invoice Number', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                        'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['Draft', 'Approved', 'Sent', 'Void'], 'predicates' => ['eq']],
                        'state' => ['label' => 'State', 'type' => 'select', 'options' => ['draft', 'approved', 'sent', 'paid', 'part_paid', 'void'], 'predicates' => ['eq']],
                        'invoice_date' => ['label' => 'Invoice Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                        'due_date' => ['label' => 'Due Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                        'total' => ['label' => 'Total', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    ],
                ],
                'projects' => [
                    'name' => 'Projects',
                    'icon' => 'folder',
                    'endpoint' => 'projects',
                    'columns' => [
                        'id' => ['label' => 'ID', 'type' => 'number'],
                        'name' => ['label' => 'Name', 'type' => 'string'],
                        'member_name' => ['label' => 'Client', 'type' => 'string'],
                        'status' => ['label' => 'Status', 'type' => 'string'],
                        'starts_at' => ['label' => 'Start Date', 'type' => 'datetime'],
                        'ends_at' => ['label' => 'End Date', 'type' => 'datetime'],
                        'budget' => ['label' => 'Budget', 'type' => 'currency'],
                        'revenue' => ['label' => 'Revenue', 'type' => 'currency'],
                        'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                    ],
                    'filters' => [
                        'name' => ['label' => 'Name', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                        'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['Active', 'Completed', 'On Hold', 'Cancelled'], 'predicates' => ['eq']],
                        'starts_at' => ['label' => 'Start Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    ],
                ],
                'stock_levels' => [
                    'name' => 'Stock Levels',
                    'icon' => 'package',
                    'endpoint' => 'stock_levels',
                    'columns' => [
                        'id' => ['label' => 'ID', 'type' => 'number'],
                        'product_name' => ['label' => 'Product', 'type' => 'string'],
                        'store_name' => ['label' => 'Store', 'type' => 'string'],
                        'quantity' => ['label' => 'Quantity', 'type' => 'number'],
                        'quantity_available' => ['label' => 'Available', 'type' => 'number'],
                        'quantity_booked' => ['label' => 'Booked', 'type' => 'number'],
                        'quantity_sub_rent' => ['label' => 'Sub Rent', 'type' => 'number'],
                        'quantity_quarantined' => ['label' => 'Quarantined', 'type' => 'number'],
                        'updated_at' => ['label' => 'Updated', 'type' => 'datetime'],
                    ],
                    'filters' => [
                        'product_name' => ['label' => 'Product', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                        'quantity' => ['label' => 'Quantity', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                        'quantity_available' => ['label' => 'Available', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    ],
                ],
                'quarantines' => [
                    'name' => 'Quarantines',
                    'icon' => 'alert-triangle',
                    'endpoint' => 'quarantines',
                    'columns' => [
                        'id' => ['label' => 'ID', 'type' => 'number'],
                        'item_name' => ['label' => 'Item', 'type' => 'string'],
                        'store_name' => ['label' => 'Store', 'type' => 'string'],
                        'quantity' => ['label' => 'Quantity', 'type' => 'number'],
                        'reason' => ['label' => 'Reason', 'type' => 'string'],
                        'status' => ['label' => 'Status', 'type' => 'string'],
                        'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                    ],
                    'filters' => [
                        'item_name' => ['label' => 'Item', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                        'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['Open', 'Resolved'], 'predicates' => ['eq']],
                    ],
                ],
            ];
        }

        public function getModules(): array {
            return $this->modules;
        }

        public function getModule(string $module): ?array {
            return $this->modules[$module] ?? null;
        }
    };
}

$module = $_GET['module'] ?? null;
$action = $_GET['action'] ?? 'list';

if ($action === 'list' || !$module) {
    // Return all modules
    $modules = $builder->getModules();

    $result = [];
    foreach ($modules as $key => $config) {
        $result[] = [
            'key' => $key,
            'name' => $config['name'],
            'icon' => $config['icon'],
        ];
    }

    echo json_encode(['modules' => $result]);
    exit;
}

// Get specific module details
$moduleConfig = $builder->getModule($module);

if (!$moduleConfig) {
    http_response_code(404);
    echo json_encode(['error' => 'Module not found']);
    exit;
}

switch ($action) {
    case 'columns':
        $columns = [];
        foreach ($moduleConfig['columns'] as $key => $config) {
            $columns[] = [
                'key' => $key,
                'label' => $config['label'],
                'type' => $config['type'],
            ];
        }
        echo json_encode(['columns' => $columns]);
        break;

    case 'filters':
        $filters = [];
        foreach ($moduleConfig['filters'] as $key => $config) {
            $filters[] = [
                'key' => $key,
                'label' => $config['label'],
                'type' => $config['type'],
                'predicates' => $config['predicates'],
                'options' => $config['options'] ?? null,
            ];
        }
        echo json_encode(['filters' => $filters]);
        break;

    case 'details':
    default:
        echo json_encode([
            'module' => $module,
            'name' => $moduleConfig['name'],
            'columns' => array_map(function ($key, $config) {
                return ['key' => $key, 'label' => $config['label'], 'type' => $config['type']];
            }, array_keys($moduleConfig['columns']), $moduleConfig['columns']),
            'filters' => array_map(function ($key, $config) {
                return [
                    'key' => $key,
                    'label' => $config['label'],
                    'type' => $config['type'],
                    'predicates' => $config['predicates'],
                    'options' => $config['options'] ?? null,
                ];
            }, array_keys($moduleConfig['filters']), $moduleConfig['filters']),
        ]);
        break;
}
