<?php
/**
 * CurrentRMS Report Builder - Application Configuration
 */

return [
    'name' => 'CurrentRMS Report Builder',
    'version' => '1.0.0',
    'debug' => getenv('APP_DEBUG') ?: false,
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    'locale' => getenv('APP_LOCALE') ?: 'en',
    'secret_key' => getenv('APP_SECRET') ?: 'change-this-secret-key-in-production',
    'session_lifetime' => 120, // minutes
    'items_per_page' => 25,
    'max_export_rows' => 10000,
    'currency_symbol' => '£', // Currency symbol: £, $, €, etc.
    'upload_path' => __DIR__ . '/../storage/uploads/',
    'cache_path' => __DIR__ . '/../storage/cache/',
    'logs_path' => __DIR__ . '/../storage/logs/',
];
