<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config/conexion.php';

try {
    $pdo = getPDO();
    
    // Crear contraseña encriptada
    $password = 'test123';
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Verificar si el usuario ya existe
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ?");
    $stmt->execute(['testuser']);
    
    if ($stmt->fetch()) {
        // Actualizar contraseña y asegurar que esté activo
        $stmt = $pdo->prepare("UPDATE usuarios SET pwd = ?, estado = 'Activo' WHERE usuario = ?");
        $stmt->execute([$hashedPassword, 'testuser']);
        $message = "Usuario 'testuser' actualizado y activado";
    } else {
        // Primero verificar si existe una persona con ese DNI, si no, crearla
        $stmt = $pdo->prepare("SELECT dni FROM personas WHERE dni = ?");
        $stmt->execute(['10000003']);
        
        if (!$stmt->fetch()) {
            // Crear persona
            $stmt = $pdo->prepare("INSERT INTO personas (dni, nombres, apellido_paterno, apellido_materno, telefono, direccion) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['10000003', 'Usuario', 'Test', 'Prueba', '999999999', 'Dirección Test']);
        }
        
        // Crear nuevo usuario
        $stmt = $pdo->prepare("INSERT INTO usuarios (id_rol, dni_persona, usuario, pwd, estado, fecha_creacion) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([1, '10000003', 'testuser', $hashedPassword, 'Activo']);
        $message = "Usuario 'testuser' creado exitosamente";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'credentials' => [
            'usuario' => 'testuser',
            'password' => 'test123'
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
