<?php
/**
 * API: Get Module Configuration
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$api = getApiClient();
if (!$api) {
    http_response_code(500);
    echo json_encode(['error' => 'API not configured']);
    exit;
}

$builder = new ReportBuilder($api);
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
