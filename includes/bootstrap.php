<?php
/**
 * Application Bootstrap
 * Include this file at the beginning of every page
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Load configuration
$dbConfig = require BASE_PATH . '/config/database.php';
$apiConfig = require BASE_PATH . '/config/api.php';
$appConfig = require BASE_PATH . '/config/app.php';

// Set timezone
date_default_timezone_set($appConfig['timezone']);

// Autoload classes
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/includes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Initialize database
Database::init($dbConfig);

// Initialize authentication
Auth::init();

// Create API client (only if configured)
$apiClient = null;
if (!empty($apiConfig['subdomain']) && !empty($apiConfig['api_token'])) {
    $apiClient = new CurrentRMSClient($apiConfig);
}

// Helper functions

/**
 * Get application config
 */
function config(string $key = null)
{
    global $appConfig, $dbConfig, $apiConfig;

    $configs = [
        'app' => $appConfig,
        'database' => $dbConfig,
        'api' => $apiConfig,
    ];

    if ($key === null) {
        return $configs;
    }

    $parts = explode('.', $key);
    $value = $configs;

    foreach ($parts as $part) {
        if (!isset($value[$part])) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
}

/**
 * Escape HTML
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 */
function formatCurrency($value, string $symbol = '$'): string
{
    return $symbol . number_format((float) $value, 2);
}

/**
 * Format date
 */
function formatDate($date, string $format = 'M j, Y'): string
{
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($date, string $format = 'M j, Y g:i A'): string
{
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * Get current URL
 */
function currentUrl(): string
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Redirect to URL
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Get flash message
 */
function flash(string $key, string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

/**
 * Check if request is AJAX
 */
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get request input
 */
function input(string $key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/**
 * Validate CSRF token
 */
function validateCsrf(): bool
{
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Generate CSRF token
 */
function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF input field
 */
function csrfField(): string
{
    return '<input type="hidden" name="_token" value="' . csrfToken() . '">';
}

/**
 * Check if API is configured
 */
function isApiConfigured(): bool
{
    global $apiClient;
    return $apiClient !== null;
}

/**
 * Get API client
 */
function getApiClient(): ?CurrentRMSClient
{
    global $apiClient;
    return $apiClient;
}

/**
 * Log message
 */
function logMessage(string $message, string $level = 'info'): void
{
    global $appConfig;
    $logFile = $appConfig['logs_path'] . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
