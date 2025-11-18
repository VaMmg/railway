<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config/conexion.php';

try {
    $pdo = getPDO();
    $dbType = getCurrentDbType();
    
    // Probar una consulta simple
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM usuarios");
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Conexión a base de datos exitosa',
        'database_type' => $dbType,
        'usuarios_count' => $result['count'],
        'php_version' => PHP_VERSION,
        'pdo_drivers' => PDO::getAvailableDrivers()
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a base de datos',
        'error' => $e->getMessage(),
        'php_version' => PHP_VERSION,
        'pdo_drivers' => PDO::getAvailableDrivers()
    ], JSON_PRETTY_PRINT);
}
