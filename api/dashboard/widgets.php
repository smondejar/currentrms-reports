<?php
/**
 * API: Dashboard Widgets
 * Returns user's dashboard widget configuration
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
    $userId = Auth::id();

    // Get user's dashboard configuration
    $dashboard = Dashboard::getForUser($userId);

    // Return widgets array (empty if no dashboard configured)
    $widgets = [];
    if ($dashboard && !empty($dashboard['config'])) {
        $config = is_array($dashboard['config']) ? $dashboard['config'] : json_decode($dashboard['config'], true);
        $widgets = $config['widgets'] ?? [];
    }

    echo json_encode([
        'success' => true,
        'widgets' => $widgets
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load widgets: ' . $e->getMessage()]);
}
