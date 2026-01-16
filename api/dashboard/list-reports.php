<?php
/**
 * API: List Reports for Widget Selection
 * Returns reports available for dashboard widgets
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $userId = Auth::user()['id'];
    $isAdmin = Auth::can(Permissions::MANAGE_USERS);

    $reports = ReportManager::getForWidgets($userId, $isAdmin);

    // Group by module
    $grouped = [];
    foreach ($reports as $report) {
        $module = $report['module'];
        if (!isset($grouped[$module])) {
            $grouped[$module] = [];
        }
        $grouped[$module][] = $report;
    }

    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'grouped' => $grouped,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load reports: ' . $e->getMessage()]);
}
