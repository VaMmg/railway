<?php
// Endpoint de perfil simplificado para debugging
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Respuesta simple de éxito para testing
    echo json_encode([
        'success' => true,
        'message' => 'Perfil actualizado exitosamente (versión simple)',
        'data' => [
            'id_usuario' => '3',
            'usuario' => 'admin',
            'id_rol' => '1',
            'nombres' => $input['nombres'] ?? 'Test',
            'apellido_paterno' => $input['apellido_paterno'] ?? 'User',
            'apellido_materno' => $input['apellido_materno'] ?? '',
            'dni' => '12345678',
            'nombre_rol' => 'Administrador',
            'nombre_completo' => ($input['nombres'] ?? 'Test') . ' ' . ($input['apellido_paterno'] ?? 'User') . ' ' . ($input['apellido_materno'] ?? '')
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método no soportado']);
?>