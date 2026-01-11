<?php
/**
 * API: Test CurrentRMS Connection
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get credentials from request or config
$subdomain = $_POST['subdomain'] ?? $_GET['subdomain'] ?? null;
$apiToken = $_POST['api_token'] ?? $_GET['api_token'] ?? null;

// Use config if not provided
if (!$subdomain || !$apiToken) {
    $apiConfig = require BASE_PATH . '/config/api.php';
    $subdomain = $subdomain ?: ($apiConfig['subdomain'] ?? '');
    $apiToken = $apiToken ?: ($apiConfig['api_token'] ?? '');
}

if (empty($subdomain) || empty($apiToken)) {
    echo json_encode([
        'success' => false,
        'error' => 'API credentials not configured. Please enter your subdomain and API token.'
    ]);
    exit;
}

// Test the connection
try {
    $client = new CurrentRMSClient([
        'subdomain' => $subdomain,
        'api_token' => $apiToken,
        'base_url' => 'https://api.current-rms.com/api/v1',
        'timeout' => 10,
        'verify_ssl' => true,
    ]);

    // Try to fetch stores (minimal data)
    $response = $client->get('stores', ['per_page' => 1]);

    $storeCount = $response['meta']['total_row_count'] ?? 0;
    $storeName = $response['stores'][0]['name'] ?? 'Unknown';

    echo json_encode([
        'success' => true,
        'message' => "Connected successfully! Found {$storeCount} store(s).",
        'data' => [
            'store_count' => $storeCount,
            'first_store' => $storeName,
        ]
    ]);

} catch (Exception $e) {
    $errorMessage = $e->getMessage();

    // Provide helpful error messages
    if (strpos($errorMessage, '401') !== false) {
        $errorMessage = 'Invalid API token. Please check your credentials.';
    } elseif (strpos($errorMessage, '404') !== false) {
        $errorMessage = 'Subdomain not found. Please check your subdomain.';
    } elseif (strpos($errorMessage, 'cURL') !== false) {
        $errorMessage = 'Network error. Please check your internet connection.';
    }

    echo json_encode([
        'success' => false,
        'error' => $errorMessage
    ]);
}
