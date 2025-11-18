<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    ]
], JSON_PRETTY_PRINT);
