<?php
/**
 * Report Builder Core Class
 * Handles report creation, filtering, and data aggregation
 */

class ReportBuilder
{
    private CurrentRMSClient $api;
    private array $modules = [];
    private string $currentModule = '';
    private array $filters = [];
    private array $columns = [];
    private array $sorting = [];
    private int $page = 1;
    private int $perPage = 25;

    public function __construct(CurrentRMSClient $api)
    {
        $this->api = $api;
        $this->initModules();
    }

    /**
     * Initialize available modules and their configurations
     */
    private function initModules(): void
    {
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
            'purchase_orders' => [
                'name' => 'Purchase Orders',
                'icon' => 'shopping-cart',
                'endpoint' => 'purchase_orders',
                'columns' => [
                    'id' => ['label' => 'ID', 'type' => 'number'],
                    'number' => ['label' => 'PO Number', 'type' => 'string'],
                    'supplier_name' => ['label' => 'Supplier', 'type' => 'string'],
                    'status' => ['label' => 'Status', 'type' => 'string'],
                    'order_date' => ['label' => 'Order Date', 'type' => 'date'],
                    'expected_date' => ['label' => 'Expected Date', 'type' => 'date'],
                    'subtotal' => ['label' => 'Subtotal', 'type' => 'currency'],
                    'tax_total' => ['label' => 'Tax', 'type' => 'currency'],
                    'total' => ['label' => 'Total', 'type' => 'currency'],
                    'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                ],
                'filters' => [
                    'number' => ['label' => 'PO Number', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['Draft', 'Sent', 'Received', 'Cancelled'], 'predicates' => ['eq']],
                    'order_date' => ['label' => 'Order Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
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

    /**
     * Get all available modules
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Get a specific module configuration
     */
    public function getModule(string $module): ?array
    {
        return $this->modules[$module] ?? null;
    }

    /**
     * Set the current module
     */
    public function setModule(string $module): self
    {
        if (!isset($this->modules[$module])) {
            throw new InvalidArgumentException("Invalid module: {$module}");
        }
        $this->currentModule = $module;
        return $this;
    }

    /**
     * Set columns to include in report
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Add a filter
     */
    public function addFilter(string $field, string $predicate, $value): self
    {
        $this->filters[] = [
            'field' => $field,
            'predicate' => $predicate,
            'value' => $value,
        ];
        return $this;
    }

    /**
     * Set filters
     */
    public function setFilters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * Set sorting
     */
    public function setSorting(string $field, string $direction = 'asc'): self
    {
        $this->sorting = ['field' => $field, 'direction' => $direction];
        return $this;
    }

    /**
     * Set pagination
     */
    public function setPagination(int $page, int $perPage = 25): self
    {
        $this->page = $page;
        $this->perPage = $perPage;
        return $this;
    }

    /**
     * Execute the report query
     */
    public function execute(): array
    {
        if (empty($this->currentModule)) {
            throw new RuntimeException('No module selected');
        }

        $module = $this->modules[$this->currentModule];
        $params = [
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];

        // Add filters as query predicates
        foreach ($this->filters as $filter) {
            $key = "q[{$filter['field']}_{$filter['predicate']}]";
            $params[$key] = $filter['value'];
        }

        // Add sorting
        if (!empty($this->sorting)) {
            $params['q[s]'] = $this->sorting['field'] . ' ' . $this->sorting['direction'];
        }

        $response = $this->api->get($module['endpoint'], $params);

        $dataKey = $module['endpoint'];
        $rawData = $response[$dataKey] ?? [];
        $meta = $response['meta'] ?? [];

        // Transform data to flatten nested objects from CurrentRMS API
        $data = array_map(function ($row) {
            return $this->flattenRow($row, $this->currentModule);
        }, $rawData);

        // Filter columns if specified
        if (!empty($this->columns)) {
            $data = array_map(function ($row) {
                $filtered = [];
                foreach ($this->columns as $col) {
                    $filtered[$col] = $row[$col] ?? null;
                }
                return $filtered;
            }, $data);
        }

        return [
            'data' => $data,
            'meta' => [
                'total' => $meta['total_row_count'] ?? count($data),
                'page' => $meta['page'] ?? $this->page,
                'per_page' => $meta['per_page'] ?? $this->perPage,
                'total_pages' => $meta['total_pages'] ?? 1,
            ],
            'module' => $this->currentModule,
            'columns' => $this->columns ?: array_keys($module['columns']),
            'column_config' => $module['columns'],
        ];
    }

    /**
     * Fetch all data (for export)
     */
    public function fetchAll(int $maxRows = 10000): array
    {
        if (empty($this->currentModule)) {
            throw new RuntimeException('No module selected');
        }

        $module = $this->modules[$this->currentModule];
        $params = ['per_page' => 100];

        // Add filters
        foreach ($this->filters as $filter) {
            $key = "q[{$filter['field']}_{$filter['predicate']}]";
            $params[$key] = $filter['value'];
        }

        // Add sorting
        if (!empty($this->sorting)) {
            $params['q[s]'] = $this->sorting['field'] . ' ' . $this->sorting['direction'];
        }

        $rawData = $this->api->fetchAll($module['endpoint'], $params, ceil($maxRows / 100));

        // Transform data to flatten nested objects
        $data = array_map(function ($row) {
            return $this->flattenRow($row, $this->currentModule);
        }, $rawData);

        // Filter columns if specified
        if (!empty($this->columns)) {
            $data = array_map(function ($row) {
                $filtered = [];
                foreach ($this->columns as $col) {
                    $filtered[$col] = $row[$col] ?? null;
                }
                return $filtered;
            }, $data);
        }

        return array_slice($data, 0, $maxRows);
    }

    /**
     * Get aggregated data for charts
     */
    public function aggregate(string $groupBy, string $aggregateField, string $function = 'sum'): array
    {
        $data = $this->fetchAll(5000);

        $groups = [];
        foreach ($data as $row) {
            $key = $row[$groupBy] ?? 'Unknown';
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $row[$aggregateField] ?? 0;
        }

        $result = [];
        foreach ($groups as $key => $values) {
            switch ($function) {
                case 'sum':
                    $result[$key] = array_sum($values);
                    break;
                case 'avg':
                    $result[$key] = count($values) > 0 ? array_sum($values) / count($values) : 0;
                    break;
                case 'count':
                    $result[$key] = count($values);
                    break;
                case 'min':
                    $result[$key] = min($values);
                    break;
                case 'max':
                    $result[$key] = max($values);
                    break;
            }
        }

        return $result;
    }

    /**
     * Reset the builder state
     */
    public function reset(): self
    {
        $this->currentModule = '';
        $this->filters = [];
        $this->columns = [];
        $this->sorting = [];
        $this->page = 1;
        $this->perPage = 25;
        return $this;
    }

    /**
     * Flatten nested objects from CurrentRMS API response to match column definitions
     */
    private function flattenRow(array $row, string $module): array
    {
        $flattened = [];

        // Start with direct copies of simple scalar fields
        foreach ($row as $key => $value) {
            if (!is_array($value) && !is_object($value)) {
                $flattened[$key] = $value;
            }
        }

        // Module-specific field mappings from CurrentRMS API nested structure
        // Note: CurrentRMS API returns monetary values as strings with decimal places
        $fieldMappings = [
            'products' => [
                'product_group_name' => [['product_group', 'name'], ['product_group_name']],
                'rental_rate' => [['rates', 0, 'price'], ['rental_price'], ['rate']],
                'sale_price' => [['sale_price'], ['sale_charge']],
                'replacement_charge' => [['replacement_charge'], ['replacement_value']],
                'quantity_owned' => [['stock_level', 'quantity_owned'], ['quantity_owned'], ['stock_method_quantity']],
                'quantity_available' => [['stock_level', 'quantity_available'], ['quantity_available']],
            ],
            'members' => [
                'company' => [['organisation', 'name'], ['company_name']],
                'email' => [['primary_email', 'address'], ['email']],
                'phone' => [['primary_telephone', 'number'], ['telephone'], ['phone']],
                'address_street' => [['primary_address', 'street'], ['street']],
                'address_city' => [['primary_address', 'city'], ['city']],
                'address_postcode' => [['primary_address', 'postcode'], ['postcode']],
                'address_country_name' => [['primary_address', 'country', 'name'], ['country']],
                'balance' => [['account_balance'], ['balance'], ['outstanding_balance']],
            ],
            'opportunities' => [
                'member_name' => [['member', 'name'], ['billing_address', 'name'], ['customer_name']],
                'venue_name' => [['venue', 'name'], ['destination', 'name'], ['delivery_address', 'name']],
                'charge_total' => [['totals', 'charge_total'], ['charge_total'], ['rental_charge_total']],
                'tax_total' => [['totals', 'tax_total'], ['tax_total']],
                'grand_total' => [['totals', 'grand_total'], ['grand_total'], ['total']],
                'rental_revenue' => [['rental_charge_total'], ['charge_total']],
            ],
            'invoices' => [
                'member_name' => [['member', 'name'], ['billing_address', 'name']],
                'number' => [['number'], ['invoice_number']],
                'invoice_date' => [['invoice_date'], ['date']],
                'due_date' => [['due_date'], ['payment_due_date']],
                'subtotal' => [['totals', 'subtotal'], ['subtotal'], ['net_total']],
                'tax_total' => [['totals', 'tax_total'], ['tax_total'], ['vat_total']],
                'total' => [['totals', 'total'], ['total'], ['gross_total']],
                'amount_paid' => [['amount_paid'], ['paid_total'], ['payments_total']],
                'balance' => [['balance'], ['outstanding'], ['amount_due']],
            ],
            'projects' => [
                'member_name' => [['member', 'name'], ['client_name']],
                'budget' => [['budget'], ['budget_total']],
                'revenue' => [['revenue'], ['revenue_total'], ['total_revenue']],
            ],
            'purchase_orders' => [
                'supplier_name' => [['supplier', 'name'], ['vendor', 'name']],
                'number' => [['number'], ['po_number']],
                'order_date' => [['order_date'], ['date']],
                'expected_date' => [['expected_date'], ['delivery_date']],
                'subtotal' => [['totals', 'subtotal'], ['subtotal']],
                'tax_total' => [['totals', 'tax_total'], ['tax_total']],
                'total' => [['totals', 'total'], ['total']],
            ],
            'stock_levels' => [
                'product_name' => [['product', 'name'], ['item_name']],
                'store_name' => [['store', 'name'], ['location_name']],
                'quantity' => [['quantity_owned'], ['quantity']],
                'quantity_available' => [['quantity_available'], ['available']],
                'quantity_booked' => [['quantity_booked'], ['booked']],
                'quantity_sub_rent' => [['quantity_sub_rented'], ['sub_rented']],
                'quantity_quarantined' => [['quantity_quarantined'], ['quarantined']],
            ],
            'quarantines' => [
                'item_name' => [['item', 'name'], ['product', 'name'], ['product_name']],
                'store_name' => [['store', 'name'], ['location_name']],
                'quantity' => [['quantity']],
                'reason' => [['reason'], ['notes']],
            ],
        ];

        // Apply module-specific mappings with fallback paths
        $mappings = $fieldMappings[$module] ?? [];
        foreach ($mappings as $targetField => $pathOptions) {
            // Skip if already have a non-null value
            if (isset($flattened[$targetField]) && $flattened[$targetField] !== null && $flattened[$targetField] !== '') {
                continue;
            }

            // Try each path option until we find a value
            foreach ($pathOptions as $sourcePath) {
                $value = $this->getNestedValue($row, $sourcePath);
                if ($value !== null && $value !== '') {
                    $flattened[$targetField] = $value;
                    break;
                }
            }

            // If still no value, check direct field as last resort
            if (!isset($flattened[$targetField]) || $flattened[$targetField] === null) {
                $flattened[$targetField] = $row[$targetField] ?? null;
            }
        }

        // Ensure numeric values for currency/number fields are properly typed
        $numericFields = [
            'rental_rate', 'sale_price', 'replacement_charge', 'weight',
            'quantity', 'quantity_owned', 'quantity_available', 'quantity_booked',
            'quantity_sub_rent', 'quantity_quarantined', 'balance', 'budget', 'revenue',
            'charge_total', 'tax_total', 'grand_total', 'subtotal', 'total', 'amount_paid',
            'rental_revenue', 'net_total', 'gross_total', 'vat_total'
        ];
        foreach ($numericFields as $field) {
            if (isset($flattened[$field]) && $flattened[$field] !== null) {
                // Convert to float, handle string numbers
                $val = $flattened[$field];
                if (is_string($val)) {
                    $val = str_replace([',', '£', '$', '€'], '', $val);
                }
                $flattened[$field] = is_numeric($val) ? (float) $val : 0;
            }
        }

        return $flattened;
    }

    /**
     * Get a nested value from an array using a path array
     */
    private function getNestedValue(array $data, array $path)
    {
        $current = $data;
        foreach ($path as $key) {
            if (is_array($current) && isset($current[$key])) {
                $current = $current[$key];
            } else {
                return null;
            }
        }
        return $current;
    }
}
