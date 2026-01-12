<?php
/**
 * API: Report Templates
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check permission
if (!Auth::can(Permissions::VIEW_REPORTS)) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $templates = ReportTemplates::getAll();
        $categories = ReportTemplates::getCategories();
        echo json_encode([
            'templates' => $templates,
            'categories' => $categories,
        ]);
        break;

    case 'get':
        $key = $_GET['key'] ?? null;
        if (!$key) {
            http_response_code(400);
            echo json_encode(['error' => 'Template key is required']);
            exit;
        }

        $template = ReportTemplates::get($key);
        if (!$template) {
            http_response_code(404);
            echo json_encode(['error' => 'Template not found']);
            exit;
        }

        echo json_encode(['template' => $template, 'key' => $key]);
        break;

    case 'run':
        $key = $_GET['key'] ?? $_POST['key'] ?? null;
        if (!$key) {
            http_response_code(400);
            echo json_encode(['error' => 'Template key is required']);
            exit;
        }

        $template = ReportTemplates::get($key);
        if (!$template) {
            http_response_code(404);
            echo json_encode(['error' => 'Template not found']);
            exit;
        }

        // Get API client
        $api = getApiClient();
        if (!$api) {
            http_response_code(500);
            echo json_encode(['error' => 'API not configured']);
            exit;
        }

        $page = (int) ($_GET['page'] ?? $_POST['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? $_POST['per_page'] ?? 25);

        try {
            // Check if this is a custom module
            if (strpos($template['module'], 'custom:') === 0) {
                // Handle custom modules by redirecting to their API
                $customModule = substr($template['module'], 7); // Remove 'custom:' prefix
                $customApiFile = __DIR__ . '/' . $customModule . '.php';

                if (!file_exists($customApiFile)) {
                    throw new Exception("Custom module API not found: {$customModule}");
                }

                // Include the custom API and let it handle the response
                $_GET['page'] = $page;
                $_GET['per_page'] = $perPage;

                // Capture output from the custom API
                ob_start();
                include $customApiFile;
                $output = ob_get_clean();

                // Parse and re-output with template info
                $customData = json_decode($output, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Custom module returned invalid JSON");
                }

                echo json_encode([
                    'success' => true,
                    'template' => $template,
                    'data' => $customData['data'] ?? [],
                    'meta' => $customData['meta'] ?? [],
                    'columns' => $customData['columns'] ?? $template['columns'],
                    'column_config' => $customData['column_config'] ?? [],
                ]);
                exit;
            }

            // Standard module handling
            $builder = new ReportBuilder($api);
            $builder->setModule($template['module']);
            $builder->setColumns($template['columns']);
            $builder->setFilters($template['filters']);

            if (!empty($template['sorting'])) {
                $builder->setSorting($template['sorting']['field'], $template['sorting']['direction']);
            }

            $builder->setPagination($page, $perPage);

            $result = $builder->execute();

            echo json_encode([
                'success' => true,
                'template' => $template,
                'data' => $result['data'],
                'meta' => $result['meta'],
                'columns' => $result['columns'],
                'column_config' => $result['column_config'],
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
