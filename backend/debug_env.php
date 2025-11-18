<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'environment_variables' => [
        'DB_HOST' => getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'NOT SET',
        'DB_PORT' => getenv('DB_PORT') ?: $_ENV['DB_PORT'] ?? 'NOT SET',
        'DB_NAME' => getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'NOT SET',
        'DB_USER' => getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'NOT SET',
        'DB_PASSWORD' => getenv('DB_PASSWORD') ? '***SET***' : 'NOT SET',
    ],
    'all_env' => array_filter($_ENV, function($key) {
        return strpos($key, 'DB_') === 0;
    }, ARRAY_FILTER_USE_KEY),
    'php_version' => PHP_VERSION,
    'loaded_extensions' => get_loaded_extensions()
], JSON_PRETTY_PRINT);
