<?php
/**
 * Invoice Actions API
 * Handle issuing invoices and marking them as paid
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

// Require authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate CSRF token
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);
$invoiceId = $input['invoice_id'] ?? null;
$action = $input['action'] ?? null;

if (!$invoiceId || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing invoice_id or action']);
    exit;
}

// Get API client
$api = getApiClient();
if (!$api) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API not configured']);
    exit;
}

try {
    switch ($action) {
        case 'issue':
            // Issue/send the invoice
            // CurrentRMS uses PUT to update invoice state
            $response = $api->put("invoices/{$invoiceId}", [
                'invoice' => [
                    'state' => 'sent'
                ]
            ]);
            echo json_encode([
                'success' => true,
                'message' => 'Invoice issued successfully',
                'invoice' => $response['invoice'] ?? null
            ]);
            break;

        case 'mark_paid':
            // Mark invoice as paid
            $response = $api->put("invoices/{$invoiceId}", [
                'invoice' => [
                    'state' => 'paid'
                ]
            ]);
            echo json_encode([
                'success' => true,
                'message' => 'Invoice marked as paid',
                'invoice' => $response['invoice'] ?? null
            ]);
            break;

        case 'void':
            // Void the invoice
            $response = $api->put("invoices/{$invoiceId}", [
                'invoice' => [
                    'state' => 'void'
                ]
            ]);
            echo json_encode([
                'success' => true,
                'message' => 'Invoice voided',
                'invoice' => $response['invoice'] ?? null
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
