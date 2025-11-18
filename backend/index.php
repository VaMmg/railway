<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Check if debug mode is requested
if (isset($_GET['debug'])) {
    echo json_encode([
        'debug' => true,
        'environment_variables' => [
            'DB_HOST' => getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'NOT SET',
            'DB_PORT' => getenv('DB_PORT') ?: $_ENV['DB_PORT'] ?? 'NOT SET',
            'DB_NAME' => getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'NOT SET',
            'DB_USER' => getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'NOT SET',
            'DB_PASSWORD' => getenv('DB_PASSWORD') ? '***SET***' : 'NOT SET',
        ],
        'php_version' => PHP_VERSION,
        'loaded_extensions' => get_loaded_extensions()
    ], JSON_PRETTY_PRINT);
    exit;
}

// Health check endpoint
echo json_encode([
    'success' => true,
    'message' => 'Sistema de Créditos API - Backend funcionando correctamente',
    'version' => '1.0.0',
    'php_version' => PHP_VERSION,
    'timestamp' => date('Y-m-d H:i:s'),
    'endpoints' => [
        'auth' => '/api/auth.php',
        'usuarios' => '/api/usuarios.php',
        'personas' => '/api/personas.php',
        'creditos' => '/api/creditos.php',
        'pagos' => '/api/pagos.php',
        'cajas' => '/api/cajas.php',
        'reportes' => '/api/reportes.php'
    ],
    'debug_url' => '/?debug=1'
], JSON_PRETTY_PRINT);
