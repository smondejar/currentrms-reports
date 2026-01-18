<?php
/**
 * API: Duplicate Report
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate CSRF token
$csrfToken = '';
if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
} else {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (is_array($headers)) {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'x-csrf-token') {
                $csrfToken = $value;
                break;
            }
        }
    }
}

if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);
$reportId = (int) ($input['id'] ?? 0);

if (!$reportId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Report ID is required']);
    exit;
}

try {
    $userId = Auth::id();
    $isAdmin = Auth::can(Permissions::MANAGE_USERS);

    // Check if user can access (view) this report
    if (!ReportManager::canAccess($reportId, $userId, $isAdmin)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to duplicate this report']);
        exit;
    }

    // Duplicate the report
    $newReportId = ReportManager::duplicate($reportId, $userId);

    echo json_encode([
        'success' => true,
        'message' => 'Report duplicated successfully',
        'report_id' => $newReportId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to duplicate report: ' . $e->getMessage()]);
}
