<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config/conexion.php';

try {
    $pdo = getPDO();
    
    // Verificar usuario
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
    $stmt->execute(['testuser']);
    $usuario = $stmt->fetch();
    
    // Verificar persona
    $persona = null;
    if ($usuario) {
        $stmt = $pdo->prepare("SELECT * FROM personas WHERE dni = ?");
        $stmt->execute([$usuario['dni_persona']]);
        $persona = $stmt->fetch();
    }
    
    // Verificar con el JOIN que usa login
    $stmt = $pdo->prepare("
        SELECT u.*, p.nombres, p.apellido_paterno 
        FROM usuarios u
        INNER JOIN personas p ON u.dni_persona = p.dni
        WHERE u.usuario = ?
    ");
    $stmt->execute(['testuser']);
    $usuarioConJoin = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'usuario' => $usuario,
        'persona' => $persona,
        'usuario_con_join' => $usuarioConJoin,
        'problema' => !$usuarioConJoin ? 'El INNER JOIN falla - persona no existe o DNI no coincide' : 'Todo OK'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
