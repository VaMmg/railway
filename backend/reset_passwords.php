<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config/conexion.php';

try {
    $pdo = getPDO();
    
    // Nuevas contraseñas
    $adminPassword = 'admin123';
    $gerentePassword = 'gerente123';
    
    // Encriptar contraseñas
    $adminHash = password_hash($adminPassword, PASSWORD_BCRYPT);
    $gerenteHash = password_hash($gerentePassword, PASSWORD_BCRYPT);
    
    // Actualizar contraseña de admin
    $stmt = $pdo->prepare("UPDATE usuarios SET pwd = ?, estado = 'Activo' WHERE usuario = 'admin'");
    $stmt->execute([$adminHash]);
    $adminUpdated = $stmt->rowCount() > 0;
    
    // Actualizar contraseña de gerente
    $stmt = $pdo->prepare("UPDATE usuarios SET pwd = ?, estado = 'Activo' WHERE usuario = 'gerente'");
    $stmt->execute([$gerenteHash]);
    $gerenteUpdated = $stmt->rowCount() > 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Contraseñas actualizadas exitosamente',
        'usuarios_actualizados' => [
            'admin' => [
                'actualizado' => $adminUpdated,
                'usuario' => 'admin',
                'password' => 'admin123'
            ],
            'gerente' => [
                'actualizado' => $gerenteUpdated,
                'usuario' => 'gerente',
                'password' => 'gerente123'
            ]
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
