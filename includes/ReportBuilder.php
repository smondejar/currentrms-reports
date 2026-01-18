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
                    'description' => ['label' => 'Description', 'type' => 'string'],
                    'product_group_name' => ['label' => 'Group', 'type' => 'string'],
                    'type' => ['label' => 'Type', 'type' => 'string'],
                    'item_type' => ['label' => 'Item Type', 'type' => 'string'],
                    'rental_rate' => ['label' => 'Rental Rate', 'type' => 'currency'],
                    'daily_rate' => ['label' => 'Daily Rate', 'type' => 'currency'],
                    'weekly_rate' => ['label' => 'Weekly Rate', 'type' => 'currency'],
                    'sale_price' => ['label' => 'Sale Price', 'type' => 'currency'],
                    'cost_price' => ['label' => 'Cost Price', 'type' => 'currency'],
                    'replacement_charge' => ['label' => 'Replacement Value', 'type' => 'currency'],
                    'weight' => ['label' => 'Weight', 'type' => 'number'],
                    'barcode' => ['label' => 'Barcode', 'type' => 'string'],
                    'sku' => ['label' => 'SKU', 'type' => 'string'],
                    'quantity_owned' => ['label' => 'Qty Owned', 'type' => 'number'],
                    'quantity_available' => ['label' => 'Qty Available', 'type' => 'number'],
                    'quantity_booked' => ['label' => 'Qty Booked', 'type' => 'number'],
                    'quantity_sub_rent' => ['label' => 'Qty Sub Rent', 'type' => 'number'],
                    'quantity_quarantined' => ['label' => 'Qty Quarantined', 'type' => 'number'],
                    'active' => ['label' => 'Active', 'type' => 'boolean'],
                    'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                    'updated_at' => ['label' => 'Updated', 'type' => 'datetime'],
                ],
                'filters' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start', 'end', 'not_eq', 'not_cont']],
                    'description' => ['label' => 'Description', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start']],
                    'product_group_name' => ['label' => 'Group', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                    'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['Product', 'Accessory', 'Consumable', 'Service'], 'predicates' => ['eq', 'not_eq']],
                    'item_type' => ['label' => 'Item Type', 'type' => 'select', 'options' => ['Product', 'Service', 'TextItem'], 'predicates' => ['eq', 'not_eq']],
                    'rental_rate' => ['label' => 'Rental Rate', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq', 'not_eq']],
                    'sale_price' => ['label' => 'Sale Price', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'replacement_charge' => ['label' => 'Replacement Value', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'quantity_owned' => ['label' => 'Qty Owned', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'quantity_available' => ['label' => 'Qty Available', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'barcode' => ['label' => 'Barcode', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start']],
                    'active' => ['label' => 'Active', 'type' => 'select', 'options' => ['true', 'false'], 'predicates' => ['eq']],
                    'created_at' => ['label' => 'Created Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'updated_at' => ['label' => 'Updated Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
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
                    'number' => ['label' => 'Number', 'type' => 'string'],
                    'subject' => ['label' => 'Subject', 'type' => 'string'],
                    'description' => ['label' => 'Description', 'type' => 'string'],
                    'member_name' => ['label' => 'Customer', 'type' => 'string'],
                    'owner_name' => ['label' => 'Owner', 'type' => 'string'],
                    'status' => ['label' => 'Status', 'type' => 'string'],
                    'state' => ['label' => 'State', 'type' => 'string'],
                    'starts_at' => ['label' => 'Starts', 'type' => 'datetime'],
                    'ends_at' => ['label' => 'Ends', 'type' => 'datetime'],
                    'duration_days' => ['label' => 'Duration (Days)', 'type' => 'number'],
                    'rental_charge_total' => ['label' => 'Rental Charges', 'type' => 'currency'],
                    'service_charge_total' => ['label' => 'Service Charges', 'type' => 'currency'],
                    'sale_charge_total' => ['label' => 'Sale Charges', 'type' => 'currency'],
                    'charge_total' => ['label' => 'Total Charges', 'type' => 'currency'],
                    'discount_total' => ['label' => 'Discount Total', 'type' => 'currency'],
                    'tax_total' => ['label' => 'Tax Total', 'type' => 'currency'],
                    'grand_total' => ['label' => 'Grand Total', 'type' => 'currency'],
                    'cost_total' => ['label' => 'Cost Total', 'type' => 'currency'],
                    'profit' => ['label' => 'Profit', 'type' => 'currency'],
                    'profit_margin' => ['label' => 'Profit Margin %', 'type' => 'number'],
                    'venue_name' => ['label' => 'Venue', 'type' => 'string'],
                    'venue_city' => ['label' => 'Venue City', 'type' => 'string'],
                    'project_name' => ['label' => 'Project', 'type' => 'string'],
                    'items_count' => ['label' => 'Items Count', 'type' => 'number'],
                    'invoiced_total' => ['label' => 'Invoiced Total', 'type' => 'currency'],
                    'paid_total' => ['label' => 'Paid Total', 'type' => 'currency'],
                    'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                    'updated_at' => ['label' => 'Updated', 'type' => 'datetime'],
                ],
                'filters' => [
                    'subject' => ['label' => 'Subject', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start', 'not_cont']],
                    'number' => ['label' => 'Number', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start']],
                    'member_name' => ['label' => 'Customer', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start']],
                    'owner_name' => ['label' => 'Owner', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['Draft', 'Provisional', 'Quote', 'Confirmed', 'Reserved', 'Order', 'Checked Out', 'Closed', 'Dead', 'Cancelled'], 'predicates' => ['eq', 'not_eq']],
                    'state' => ['label' => 'State', 'type' => 'select', 'options' => ['draft', 'quote_sent', 'order_confirmed', 'active', 'closed', 'dead', 'cancelled'], 'predicates' => ['eq', 'not_eq']],
                    'starts_at' => ['label' => 'Start Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'ends_at' => ['label' => 'End Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'charge_total' => ['label' => 'Charge Total', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'grand_total' => ['label' => 'Grand Total', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'rental_charge_total' => ['label' => 'Rental Total', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'service_charge_total' => ['label' => 'Service Total', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'venue_name' => ['label' => 'Venue', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                    'project_name' => ['label' => 'Project', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                    'created_at' => ['label' => 'Created Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'updated_at' => ['label' => 'Updated Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                ],
            ],
            'invoices' => [
                'name' => 'Invoices',
                'icon' => 'file-text',
                'endpoint' => 'invoices',
                'columns' => [
                    'id' => ['label' => 'ID', 'type' => 'number'],
                    'number' => ['label' => 'Invoice #', 'type' => 'string'],
                    'reference' => ['label' => 'Reference', 'type' => 'string'],
                    'member_name' => ['label' => 'Customer', 'type' => 'string'],
                    'member_email' => ['label' => 'Customer Email', 'type' => 'string'],
                    'opportunity_subject' => ['label' => 'Opportunity', 'type' => 'string'],
                    'status' => ['label' => 'Status', 'type' => 'string'],
                    'state' => ['label' => 'State', 'type' => 'string'],
                    'invoice_date' => ['label' => 'Invoice Date', 'type' => 'date'],
                    'due_date' => ['label' => 'Due Date', 'type' => 'date'],
                    'sent_at' => ['label' => 'Sent Date', 'type' => 'datetime'],
                    'paid_at' => ['label' => 'Paid Date', 'type' => 'datetime'],
                    'subtotal' => ['label' => 'Subtotal', 'type' => 'currency'],
                    'discount_total' => ['label' => 'Discount', 'type' => 'currency'],
                    'tax_total' => ['label' => 'Tax', 'type' => 'currency'],
                    'total' => ['label' => 'Total', 'type' => 'currency'],
                    'amount_paid' => ['label' => 'Paid', 'type' => 'currency'],
                    'balance' => ['label' => 'Balance', 'type' => 'currency'],
                    'days_overdue' => ['label' => 'Days Overdue', 'type' => 'number'],
                    'notes' => ['label' => 'Notes', 'type' => 'string'],
                    'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                    'updated_at' => ['label' => 'Updated', 'type' => 'datetime'],
                ],
                'filters' => [
                    'number' => ['label' => 'Invoice Number', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start']],
                    'reference' => ['label' => 'Reference', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                    'member_name' => ['label' => 'Customer', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['Draft', 'Approved', 'Sent', 'Paid', 'Part Paid', 'Void'], 'predicates' => ['eq', 'not_eq']],
                    'state' => ['label' => 'State', 'type' => 'select', 'options' => ['draft', 'approved', 'sent', 'paid', 'part_paid', 'void'], 'predicates' => ['eq', 'not_eq']],
                    'invoice_date' => ['label' => 'Invoice Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'due_date' => ['label' => 'Due Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'sent_at' => ['label' => 'Sent Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq', 'null', 'not_null']],
                    'paid_at' => ['label' => 'Paid Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq', 'null', 'not_null']],
                    'total' => ['label' => 'Total', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'balance' => ['label' => 'Balance', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'created_at' => ['label' => 'Created Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                ],
            ],
            'projects' => [
                'name' => 'Projects',
                'icon' => 'folder',
                'endpoint' => 'projects',
                'columns' => [
                    'id' => ['label' => 'ID', 'type' => 'number'],
                    'name' => ['label' => 'Name', 'type' => 'string'],
                    'description' => ['label' => 'Description', 'type' => 'string'],
                    'member_name' => ['label' => 'Client', 'type' => 'string'],
                    'owner_name' => ['label' => 'Owner', 'type' => 'string'],
                    'status' => ['label' => 'Status', 'type' => 'string'],
                    'category' => ['label' => 'Category', 'type' => 'string'],
                    'starts_at' => ['label' => 'Start Date', 'type' => 'datetime'],
                    'ends_at' => ['label' => 'End Date', 'type' => 'datetime'],
                    'budget' => ['label' => 'Budget', 'type' => 'currency'],
                    'total_charges' => ['label' => 'Total Charges', 'type' => 'currency'],
                    'rental_charges' => ['label' => 'Rental Charges', 'type' => 'currency'],
                    'service_charges' => ['label' => 'Service Charges', 'type' => 'currency'],
                    'sale_charges' => ['label' => 'Sale Charges', 'type' => 'currency'],
                    'cost_total' => ['label' => 'Cost Total', 'type' => 'currency'],
                    'profit' => ['label' => 'Profit', 'type' => 'currency'],
                    'profit_margin' => ['label' => 'Profit Margin %', 'type' => 'number'],
                    'invoiced_total' => ['label' => 'Invoiced Total', 'type' => 'currency'],
                    'paid_total' => ['label' => 'Paid Total', 'type' => 'currency'],
                    'opportunities_count' => ['label' => 'Opportunities', 'type' => 'number'],
                    'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                    'updated_at' => ['label' => 'Updated', 'type' => 'datetime'],
                ],
                'filters' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start', 'not_cont']],
                    'description' => ['label' => 'Description', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                    'member_name' => ['label' => 'Client', 'type' => 'text', 'predicates' => ['cont', 'eq', 'start']],
                    'owner_name' => ['label' => 'Owner', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['Active', 'Completed', 'On Hold', 'Cancelled'], 'predicates' => ['eq', 'not_eq']],
                    'category' => ['label' => 'Category', 'type' => 'text', 'predicates' => ['cont', 'eq']],
                    'starts_at' => ['label' => 'Start Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'ends_at' => ['label' => 'End Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'total_charges' => ['label' => 'Total Charges', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'budget' => ['label' => 'Budget', 'type' => 'number', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
                    'created_at' => ['label' => 'Created Date', 'type' => 'date', 'predicates' => ['eq', 'lt', 'gt', 'lteq', 'gteq']],
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

        // Include related data for specific modules
        if ($this->currentModule === 'projects') {
            $params['include[]'] = 'opportunities';
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

        // Calculate total and pages
        $totalCount = $meta['total_row_count'] ?? count($data);
        $currentPerPage = $meta['per_page'] ?? $this->perPage;
        $totalPages = $meta['total_pages'] ?? ($totalCount > 0 ? (int) ceil($totalCount / $currentPerPage) : 1);

        return [
            'data' => $data,
            'meta' => [
                'total' => $totalCount,
                'page' => $meta['page'] ?? $this->page,
                'per_page' => $currentPerPage,
                'total_pages' => $totalPages,
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

        // Include related data for specific modules
        if ($this->currentModule === 'projects') {
            $params['include[]'] = 'opportunities';
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

        // Special handling for projects: calculate charges from opportunities
        if ($module === 'projects' && isset($row['opportunities']) && is_array($row['opportunities'])) {
            $rentalCharges = 0;
            $serviceCharges = 0;
            foreach ($row['opportunities'] as $opp) {
                $rentalCharges += floatval($opp['rental_charge_total'] ?? 0);
                $serviceCharges += floatval($opp['service_charge_total'] ?? 0);
            }
            $flattened['rental_charges'] = $rentalCharges;
            $flattened['service_charges'] = $serviceCharges;
            $flattened['total_charges'] = $rentalCharges + $serviceCharges;
            $flattened['opportunities_count'] = count($row['opportunities']);
        }

        // Special handling for projects: get budget from custom_fields
        if ($module === 'projects' && isset($row['custom_fields'])) {
            if (isset($row['custom_fields']['budget']) && !isset($flattened['budget'])) {
                $flattened['budget'] = floatval($row['custom_fields']['budget']);
            }
            if (isset($row['custom_fields']['category']) && !isset($flattened['category'])) {
                $cat = $row['custom_fields']['category'];
                $flattened['category'] = is_array($cat) ? ($cat['name'] ?? json_encode($cat)) : $cat;
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
                'member_name' => [['member', 'name'], ['billing_address', 'name'], ['subject', 'name'], ['contact_name']],
                'number' => [['number'], ['invoice_number'], ['reference']],
                'invoice_date' => [['invoice_date'], ['date'], ['created_at']],
                'due_date' => [['due_date'], ['payment_due_date'], ['payment_terms_due_date']],
                'subtotal' => [['totals', 'subtotal'], ['subtotal'], ['net_total'], ['charge_total']],
                'tax_total' => [['totals', 'tax_total'], ['tax_total'], ['vat_total'], ['tax']],
                'total' => [['totals', 'total'], ['total'], ['gross_total'], ['grand_total'], ['totals', 'grand_total']],
                'amount_paid' => [['amount_paid'], ['paid_total'], ['payments_total'], ['paid'], ['total_paid']],
                'balance' => [['balance'], ['outstanding'], ['amount_due'], ['balance_due'], ['outstanding_amount']],
                'status' => [['status'], ['state'], ['invoice_status']],
                'state' => [['state'], ['status'], ['invoice_state']],
            ],
            'projects' => [
                'member_name' => [['member', 'name'], ['client_name'], ['customer_name'], ['owner', 'name'], ['contact_name']],
                'budget' => [['budget'], ['budget_total'], ['estimated_budget'], ['totals', 'budget'], ['project_budget']],
                'revenue' => [['revenue'], ['revenue_total'], ['total_revenue'], ['charge_total'], ['totals', 'charge_total'], ['totals', 'grand_total'], ['actual_revenue'], ['calculated_revenue'], ['opportunity_totals', 'charge_total'], ['opportunity_totals', 'grand_total'], ['invoiced_total']],
                'status' => [['status'], ['state'], ['project_status'], ['current_status']],
                'starts_at' => [['starts_at'], ['start_date'], ['begin_date']],
                'ends_at' => [['ends_at'], ['end_date'], ['finish_date']],
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
