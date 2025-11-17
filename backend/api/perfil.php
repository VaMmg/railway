<?php
// BACKEND/api/perfil.php
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Manejar preflight requests (CORS)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($method) {
    case 'GET':
        handleGetProfile();
        break;
    case 'PUT':
        handleUpdateProfile();
        break;
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no soportado'
        ]);
        exit;
}

function handleGetProfile() {
    $user = requireAuthProfile(); // Solo necesita estar autenticado
    $pdo = getPDO();
    
    try {
        $sql = "
            SELECT u.id_usuario, u.usuario, u.id_rol, u.estado, u.fecha_creacion, u.ultimo_acceso,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
                   r.nombre_rol
            FROM usuarios u
            INNER JOIN personas p ON u.dni_persona = p.dni
            LEFT JOIN rol r ON u.id_rol = r.id_rol
            WHERE u.id_usuario = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $user['id_usuario']]);
        $profile = $stmt->fetch();
        
        if ($profile) {
            // No incluir información sensible en la respuesta
            unset($profile['pwd']);
            jsonResponse(true, 'Perfil obtenido', $profile);
        } else {
            jsonResponse(false, 'Perfil no encontrado', null, 404);
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al obtener perfil: ' . $e->getMessage(), null, 500);
    }
}

function handleUpdateProfile() {
    global $input;
    $currentUser = requireAuthProfile(); // Cualquier usuario autenticado puede editar su perfil
    $pdo = getPDO();
    
    // Validar que existan los campos requeridos
    if (!isset($input['nombres']) || !isset($input['apellido_paterno']) || !isset($input['usuario'])) {
        jsonResponse(false, 'Campos requeridos: nombres, apellido_paterno, usuario', null, 400);
    }
    
    // Los usuarios solo pueden editar su propio perfil
    // Los administradores pueden editar cualquier perfil si se proporciona user_id
    $userIdToEdit = $currentUser['id_usuario'];
    
    // Si es administrador y se proporciona user_id, puede editar otros perfiles
    if ($currentUser['id_rol'] == 1 && isset($input['user_id'])) {
        $userIdToEdit = (int)$input['user_id'];
    }
    
    // Obtener información del usuario a editar
    $auth = new AuthSystem();
    $userToEdit = $auth->getUserInfo($userIdToEdit);
    
    if (!$userToEdit) {
        jsonResponse(false, 'Usuario a editar no encontrado', null, 404);
    }
    
    // Validar que el usuario tenga dni_persona
    if (!isset($userToEdit['dni_persona']) || empty($userToEdit['dni_persona'])) {
        jsonResponse(false, 'Error: DNI de persona no disponible para el usuario a editar', null, 500);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Actualizar tabla personas
        $updatePersonSql = "
            UPDATE personas 
            SET nombres = :nombres, 
                apellido_paterno = :apellido_paterno, 
                apellido_materno = :apellido_materno
            WHERE dni = :dni
        ";
        $updatePersonStmt = $pdo->prepare($updatePersonSql);
        $updatePersonStmt->execute([
            ':nombres' => $input['nombres'],
            ':apellido_paterno' => $input['apellido_paterno'],
            ':apellido_materno' => $input['apellido_materno'] ?? '',
            ':dni' => $userToEdit['dni_persona']
        ]);
        
        // Verificar si el nuevo nombre de usuario ya existe (excepto para el usuario actual)
        $checkUserSql = "SELECT id_usuario FROM usuarios WHERE usuario = :usuario AND id_usuario != :id";
        $checkUserStmt = $pdo->prepare($checkUserSql);
        $checkUserStmt->execute([':usuario' => $input['usuario'], ':id' => $userToEdit['id_usuario']]);
        
        if ($checkUserStmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(false, 'El nombre de usuario ya existe', null, 400);
        }
        
        // Preparar actualización de usuarios
        $updateUserFields = ['usuario = :usuario'];
        $userParams = [
            ':usuario' => $input['usuario'],
            ':id' => $userToEdit['id_usuario']
        ];
        
        // Si se proporciona nueva contraseña, incluirla
        if (isset($input['password']) && !empty(trim($input['password']))) {
            $updateUserFields[] = 'pwd = :pwd';
            $userParams[':pwd'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        
        // Actualizar tabla usuarios
        $updateUserSql = "UPDATE usuarios SET " . implode(', ', $updateUserFields) . " WHERE id_usuario = :id";
        $updateUserStmt = $pdo->prepare($updateUserSql);
        $updateUserStmt->execute($userParams);
        
        $pdo->commit();
        
        // Obtener datos actualizados para devolver
        $getUpdatedSql = "
            SELECT u.id_usuario, u.usuario, u.id_rol, u.estado, u.fecha_creacion, u.ultimo_acceso,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
                   r.nombre_rol
            FROM usuarios u
            INNER JOIN personas p ON u.dni_persona = p.dni
            LEFT JOIN rol r ON u.id_rol = r.id_rol
            WHERE u.id_usuario = :id
        ";
        $getUpdatedStmt = $pdo->prepare($getUpdatedSql);
        $getUpdatedStmt->execute([':id' => $userToEdit['id_usuario']]);
        $updatedProfile = $getUpdatedStmt->fetch();
        
        // Agregar nombre completo
        $updatedProfile['nombre_completo'] = trim($updatedProfile['nombres'] . ' ' . $updatedProfile['apellido_paterno'] . ' ' . $updatedProfile['apellido_materno']);
        
        jsonResponse(true, 'Perfil actualizado exitosamente', $updatedProfile);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al actualizar perfil: ' . $e->getMessage(), null, 500);
    }
}

?>
