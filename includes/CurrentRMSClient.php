<?php
/**
 * CurrentRMS API Client
 * Handles all communication with the CurrentRMS API
 */

class CurrentRMSClient
{
    private string $subdomain;
    private string $apiToken;
    private string $baseUrl;
    private int $timeout;
    private bool $verifySSL;

    public function __construct(array $config)
    {
        $this->subdomain = $config['subdomain'];
        $this->apiToken = $config['api_token'];
        $this->baseUrl = $config['base_url'];
        $this->timeout = $config['timeout'] ?? 30;
        $this->verifySSL = $config['verify_ssl'] ?? true;
    }

    /**
     * Make a GET request to the API
     */
    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * Make a POST request to the API
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, [], $data);
    }

    /**
     * Make a PUT request to the API
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, [], $data);
    }

    /**
     * Make a DELETE request to the API
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Execute the HTTP request
     */
    private function request(string $method, string $endpoint, array $params = [], array $data = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_HTTPHEADER => [
                'X-SUBDOMAIN: ' . $this->subdomain,
                'X-AUTH-TOKEN: ' . $this->apiToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['errors'][0]['detail'] ?? $decoded['error'] ?? 'Unknown API error';
            throw new Exception("API Error ({$httpCode}): " . $errorMessage);
        }

        return $decoded ?? [];
    }

    /**
     * Build query string with predicates for filtering
     * Predicates: eq, not_eq, cont, not_cont, start, end, lt, lteq, gt, gteq, null, not_null, in, not_in
     */
    public function buildQuery(array $filters): array
    {
        $query = [];

        foreach ($filters as $filter) {
            $field = $filter['field'];
            $predicate = $filter['predicate'] ?? 'eq';
            $value = $filter['value'];

            $key = "q[{$field}_{$predicate}]";
            $query[$key] = $value;
        }

        return $query;
    }

    /**
     * Fetch all pages of results
     */
    public function fetchAll(string $endpoint, array $params = [], int $maxPages = 100): array
    {
        $allResults = [];
        $page = 1;
        $params['per_page'] = $params['per_page'] ?? 100;

        do {
            $params['page'] = $page;
            $response = $this->get($endpoint, $params);

            // Get the data key (varies by endpoint)
            $dataKey = $this->getDataKey($endpoint);
            $results = $response[$dataKey] ?? [];

            $allResults = array_merge($allResults, $results);

            $meta = $response['meta'] ?? [];
            $totalPages = $meta['total_pages'] ?? 1;
            $page++;

        } while ($page <= $totalPages && $page <= $maxPages);

        return $allResults;
    }

    /**
     * Fetch all pages with a raw query string (for multiple same-name params like include[])
     */
    public function fetchAllWithQuery(string $endpoint, string $queryString, int $maxPages = 100): array
    {
        $allResults = [];
        $page = 1;

        do {
            $pageQuery = $queryString . '&page=' . $page;
            $response = $this->getWithQuery($endpoint, $pageQuery);

            $dataKey = $this->getDataKey($endpoint);
            $results = $response[$dataKey] ?? [];

            $allResults = array_merge($allResults, $results);

            $meta = $response['meta'] ?? [];
            $totalPages = $meta['total_pages'] ?? 1;
            $page++;

        } while ($page <= $totalPages && $page <= $maxPages);

        return $allResults;
    }

    /**
     * Make a GET request with a raw query string
     */
    public function getWithQuery(string $endpoint, string $queryString): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/') . '?' . $queryString;

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_HTTPHEADER => [
                'X-SUBDOMAIN: ' . $this->subdomain,
                'X-AUTH-TOKEN: ' . $this->apiToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['errors'][0]['detail'] ?? $decoded['error'] ?? 'Unknown API error';
            throw new Exception("API Error ({$httpCode}): " . $errorMessage);
        }

        return $decoded ?? [];
    }

    /**
     * Get the data key for an endpoint response
     */
    private function getDataKey(string $endpoint): string
    {
        $endpoint = trim($endpoint, '/');
        $parts = explode('/', $endpoint);
        $resource = $parts[0];

        $keyMap = [
            'products' => 'products',
            'members' => 'members',
            'opportunities' => 'opportunities',
            'invoices' => 'invoices',
            'projects' => 'projects',
            'purchase_orders' => 'purchase_orders',
            'stock_levels' => 'stock_levels',
            'stores' => 'stores',
            'categories' => 'categories',
            'custom_fields' => 'custom_fields',
            'tax_classes' => 'tax_classes',
            'payment_methods' => 'payment_methods',
            'opportunity_items' => 'opportunity_items',
            'invoice_items' => 'invoice_items',
            'quarantines' => 'quarantines',
            'vehicles' => 'vehicles',
            'venues' => 'venues',
        ];

        return $keyMap[$resource] ?? $resource;
    }

    // Module-specific methods

    public function getProducts(array $params = []): array
    {
        return $this->get('products', $params);
    }

    public function getProduct(int $id): array
    {
        return $this->get("products/{$id}");
    }

    public function getMembers(array $params = []): array
    {
        return $this->get('members', $params);
    }

    public function getMember(int $id): array
    {
        return $this->get("members/{$id}");
    }

    public function getOpportunities(array $params = []): array
    {
        return $this->get('opportunities', $params);
    }

    public function getOpportunity(int $id): array
    {
        return $this->get("opportunities/{$id}");
    }

    public function getInvoices(array $params = []): array
    {
        return $this->get('invoices', $params);
    }

    public function getInvoice(int $id): array
    {
        return $this->get("invoices/{$id}");
    }

    public function getProjects(array $params = []): array
    {
        return $this->get('projects', $params);
    }

    public function getProject(int $id): array
    {
        return $this->get("projects/{$id}");
    }

    public function getPurchaseOrders(array $params = []): array
    {
        return $this->get('purchase_orders', $params);
    }

    public function getStockLevels(array $params = []): array
    {
        return $this->get('stock_levels', $params);
    }

    public function getStores(array $params = []): array
    {
        return $this->get('stores', $params);
    }

    public function getCategories(array $params = []): array
    {
        return $this->get('categories', $params);
    }

    public function getCustomFields(array $params = []): array
    {
        return $this->get('custom_fields', $params);
    }

    public function getTaxClasses(array $params = []): array
    {
        return $this->get('tax_classes', $params);
    }

    public function getPaymentMethods(array $params = []): array
    {
        return $this->get('payment_methods', $params);
    }

    public function getVenues(array $params = []): array
    {
        return $this->get('venues', $params);
    }

    public function getVehicles(array $params = []): array
    {
        return $this->get('vehicles', $params);
    }

    public function getQuarantines(array $params = []): array
    {
        return $this->get('quarantines', $params);
    }

    /**
     * Test API connection
     */
    public function testConnection(): bool
    {
        try {
            $this->get('stores', ['per_page' => 1]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
