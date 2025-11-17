<?php
// BACKEND/api/login.php
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        handleLogin();
        break;
    case 'GET':
        // Verificar token actual
        handleVerifyToken();
        break;
    default:
        jsonResponse(false, 'Método no soportado', null, 405);
}

function handleLogin() {
    global $input;
    $pdo = getPDO();
    
    // Validar datos requeridos
    if (!isset($input['usuario']) || !isset($input['password'])) {
        jsonResponse(false, 'Usuario y contraseña requeridos', null, 400);
    }
    
    $usuario = $input['usuario'];
    $password = $input['password'];
    
    try {
        // Buscar usuario
        $sql = "
            SELECT u.id_usuario, u.usuario, u.pwd, u.id_rol, u.estado,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
                   r.nombre_rol
            FROM usuarios u
            INNER JOIN personas p ON u.dni_persona = p.dni
            LEFT JOIN rol r ON u.id_rol = r.id_rol
            WHERE u.usuario = :usuario AND u.estado = 'Activo'
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario' => $usuario]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Registrar intento fallido
            $auth = new AuthSystem();
            $auth->logAccess(null, false);
            jsonResponse(false, 'Usuario no encontrado o inactivo', null, 401);
        }
        
        // Verificar contraseña (soporta password_hash y SHA2 hex de 64 chars)
        $storedPwd = $user['pwd'];
        $isValid = false;

        // Formatos comunes de password_hash (bcrypt/argon)
        if (preg_match('/^\$2y\$|^\$2a\$|^\$argon2/i', $storedPwd)) {
            $isValid = password_verify($password, $storedPwd);
        } elseif (strlen($storedPwd) === 64 && ctype_xdigit($storedPwd)) {
            // Compatibilidad con contraseñas almacenadas con SHA2('pwd',256)
            $isValid = hash_equals($storedPwd, hash('sha256', $password));
        } else {
            // Intento por defecto
            $isValid = password_verify($password, $storedPwd);
        }

        if (!$isValid) {
            // Registrar intento fallido
            $auth = new AuthSystem();
            $auth->logAccess($user['id_usuario'], false);
            jsonResponse(false, 'Contraseña incorrecta', null, 401);
        }
        
        // Generar token JWT
        $payload = [
            'id' => $user['id_usuario'],
            'id_usuario' => $user['id_usuario'],
            'usuario' => $user['usuario'],
            'rol' => $user['id_rol'],
            'id_rol' => $user['id_rol'],
            'nombre_rol' => $user['nombre_rol'],
            'nombre_completo' => trim($user['nombres'] . ' ' . $user['apellido_paterno'] . ' ' . $user['apellido_materno'])
        ];
        
        $token = generateJWT($payload);
        
        // Actualizar último acceso
        $updateSql = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([':id' => $user['id_usuario']]);
        
        // Registrar acceso exitoso
        $auth = new AuthSystem();
        $auth->logAccess($user['id_usuario'], true);
        
        // Respuesta exitosa
        jsonResponse(true, 'Inicio de sesión exitoso', [
            'token' => $token,
            'user' => [
                'id' => (int)$user['id_usuario'],
                'usuario' => $user['usuario'],
                'nombres' => $user['nombres'],
                'apellido_paterno' => $user['apellido_paterno'],
                'apellido_materno' => $user['apellido_materno'],
                'dni' => $user['dni'],
                'id_rol' => (int)$user['id_rol'], // Convertir a entero
                'nombre_rol' => $user['nombre_rol'],
                'nombre_completo' => $payload['nombre_completo']
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error en el servidor: ' . $e->getMessage(), null, 500);
    }
}

function handleVerifyToken() {
    $token = getBearerToken();
    
    if (!$token) {
        jsonResponse(false, 'Token no proporcionado', null, 401);
    }
    
    $payload = verifyJWT($token);
    
    if (!$payload) {
        jsonResponse(false, 'Token inválido o expirado', null, 401);
    }
    
    // Obtener información completa del usuario
    $auth = new AuthSystem();
    $userInfo = $auth->getUserInfo($payload['id']);
    
    if (!$userInfo) {
        jsonResponse(false, 'Usuario no encontrado', null, 401);
    }
    
    jsonResponse(true, 'Token válido', [
        'user' => [
            'id' => (int)$userInfo['id_usuario'],
            'usuario' => $userInfo['usuario'],
            'nombres' => $userInfo['nombres'],
            'apellido_paterno' => $userInfo['apellido_paterno'],
            'apellido_materno' => $userInfo['apellido_materno'],
            'dni' => $userInfo['dni'],
            'id_rol' => (int)$userInfo['id_rol'], // Convertir a entero
            'nombre_rol' => $userInfo['nombre_rol'],
            'nombre_completo' => trim($userInfo['nombres'] . ' ' . $userInfo['apellido_paterno'] . ' ' . $userInfo['apellido_materno'])
        ]
    ]);
}

// Endpoint para logout (invalida el token en el cliente)
if ($method === 'DELETE') {
    jsonResponse(true, 'Sesión cerrada exitosamente');
}
?>