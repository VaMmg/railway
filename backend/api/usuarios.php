<?php
// BACKEND/api/usuarios.php
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'PUT':
        handlePut();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        jsonResponse(false, 'Método no soportado', null, 405);
}

function handleGet() {
    $user = requireGerente(); // Gerentes y administradores pueden ver usuarios
    $pdo = getPDO();
    
    if (isset($_GET['id'])) {
        // Obtener usuario específico
        $id = $_GET['id'];
        $sql = "
            SELECT u.id_usuario, u.usuario, u.id_rol, u.estado, u.fecha_creacion, u.ultimo_acceso,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
                   r.nombre_rol, r.descripcion as rol_descripcion
            FROM usuarios u
            INNER JOIN personas p ON u.dni_persona = p.dni
            LEFT JOIN rol r ON u.id_rol = r.id_rol
            WHERE u.id_usuario = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            jsonResponse(true, 'Usuario encontrado', $usuario);
        } else {
            jsonResponse(false, 'Usuario no encontrado', null, 404);
        }
    } else {
        // Obtener todos los usuarios con paginación
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $sql = "
            SELECT u.id_usuario, u.usuario, u.dni_persona, u.id_rol, u.estado, u.fecha_creacion, u.ultimo_acceso,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
                   CONCAT(p.nombres, ' ', p.apellido_paterno, ' ', IFNULL(p.apellido_materno, '')) as nombre_completo,
                   r.nombre_rol, r.nombre_rol as rol
            FROM usuarios u
            INNER JOIN personas p ON u.dni_persona = p.dni
            LEFT JOIN rol r ON u.id_rol = r.id_rol
            ORDER BY u.fecha_creacion DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $usuarios = $stmt->fetchAll();
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM usuarios";
        $countStmt = $pdo->query($countSql);
        $total = $countStmt->fetch()['total'];
        
        jsonResponse(true, 'Usuarios obtenidos', [
            'usuarios' => $usuarios,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
}

function handlePost() {
    global $input;
    $user = requireAdmin(); // Solo administradores pueden crear usuarios
    $pdo = getPDO();
    
    // Validar datos requeridos para usuario
    $required = ['dni_persona', 'usuario', 'pwd', 'rol'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            jsonResponse(false, "Campo requerido: $field", null, 400);
        }
    }
    
    // Validar datos requeridos para persona
    $requiredPersona = ['nombres', 'apellido_paterno'];
    foreach ($requiredPersona as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            jsonResponse(false, "Campo requerido para persona: $field", null, 400);
        }
    }
    
    // Convertir nombre de rol a id_rol
    $rolMap = [
        'Administrador' => 1,
        'Gerente' => 2,
        'Trabajador' => 3
    ];
    
    if (!isset($rolMap[$input['rol']])) {
        jsonResponse(false, 'Rol inválido', null, 400);
    }
    
    $id_rol = $rolMap[$input['rol']];
    $estado = $input['estado'] ?? 'Activo';
    $dni = $input['dni_persona'];
    
    try {
        $pdo->beginTransaction();
        
        // Verificar si el usuario ya existe
        $checkUserSql = "SELECT usuario FROM usuarios WHERE usuario = :usuario";
        $checkUserStmt = $pdo->prepare($checkUserSql);
        $checkUserStmt->execute([':usuario' => $input['usuario']]);
        
        if ($checkUserStmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(false, 'El nombre de usuario ya existe', null, 400);
        }
        
        // Verificar si ya existe un usuario con ese DNI
        $checkDniUserSql = "SELECT id_usuario FROM usuarios WHERE dni_persona = :dni";
        $checkDniUserStmt = $pdo->prepare($checkDniUserSql);
        $checkDniUserStmt->execute([':dni' => $dni]);
        
        if ($checkDniUserStmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(false, 'Ya existe un usuario con ese DNI de persona', null, 400);
        }
        
        // Verificar si la persona ya existe
        $checkPersonSql = "SELECT dni FROM personas WHERE dni = :dni";
        $checkPersonStmt = $pdo->prepare($checkPersonSql);
        $checkPersonStmt->execute([':dni' => $dni]);
        $personaExiste = $checkPersonStmt->fetch();
        
        // Si la persona NO existe, crearla
        if (!$personaExiste) {
            // Preparar datos de persona
            $personaData = [
                'dni' => $dni,
                'nombres' => $input['nombres'],
                'apellido_paterno' => $input['apellido_paterno'],
                'apellido_materno' => $input['apellido_materno'] ?? '',
                'sexo' => $input['sexo'] ?? 'M',
                'fecha_nacimiento' => $input['fecha_nacimiento'] ?? null,
                'nacionalidad' => $input['nacionalidad'] ?? 'Peruana',
                'estado_civil' => $input['estado_civil'] ?? 'Soltero',
                'nivel_educativo' => $input['nivel_educativo'] ?? null,
                'fecha_registro' => date('Y-m-d H:i:s')
            ];
            
            // Normalizar valores opcionales
            if (isset($personaData['fecha_nacimiento']) && $personaData['fecha_nacimiento'] === '') {
                $personaData['fecha_nacimiento'] = null;
            }
            
            // PASO 1: Crear persona (sin IDs de tablas relacionadas)
            $fields = array_keys($personaData);
            $placeholders = ':' . implode(', :', $fields);
            $sqlPersona = "INSERT INTO personas (" . implode(', ', $fields) . ") VALUES ($placeholders)";
            $stmtPersona = $pdo->prepare($sqlPersona);
            $stmtPersona->execute($personaData);
            
            // PASO 2: Crear registros relacionados si existen
            $id_correo = null;
            $id_contacto = null;
            $id_direccion = null;
            
            // Crear registro en tabla correos si existe el campo correo
            if (isset($input['correo']) && !empty($input['correo'])) {
                $sqlCorreo = "INSERT INTO correos (dni_persona, cuantas_correos, correo1) VALUES (:dni, 1, :correo)";
                $stmtCorreo = $pdo->prepare($sqlCorreo);
                $stmtCorreo->execute([':dni' => $dni, ':correo' => $input['correo']]);
                $id_correo = $pdo->lastInsertId();
            }
            
            // Crear registro en tabla contacto si existe el campo telefono
            if (isset($input['telefono']) && !empty($input['telefono'])) {
                $sqlContacto = "INSERT INTO contacto (dni_persona, cantidad_numeros, numero1) VALUES (:dni, 1, :telefono)";
                $stmtContacto = $pdo->prepare($sqlContacto);
                $stmtContacto->execute([':dni' => $dni, ':telefono' => $input['telefono']]);
                $id_contacto = $pdo->lastInsertId();
            }
            
            // Crear registro en tabla direccion si existe el campo direccion
            if (isset($input['direccion']) && !empty($input['direccion'])) {
                $sqlDireccion = "INSERT INTO direccion (dni_persona, cuantas_direcciones, direccion1) VALUES (:dni, 1, :direccion)";
                $stmtDireccion = $pdo->prepare($sqlDireccion);
                $stmtDireccion->execute([':dni' => $dni, ':direccion' => $input['direccion']]);
                $id_direccion = $pdo->lastInsertId();
            }
            
            // PASO 3: Actualizar persona con los IDs de las tablas relacionadas
            $updateParts = [];
            $updateParams = [':dni' => $dni];
            
            if ($id_correo) {
                $updateParts[] = "id_correo = :id_correo";
                $updateParams[':id_correo'] = $id_correo;
            }
            if ($id_contacto) {
                $updateParts[] = "id_contacto = :id_contacto";
                $updateParams[':id_contacto'] = $id_contacto;
            }
            if ($id_direccion) {
                $updateParts[] = "id_direccion = :id_direccion";
                $updateParams[':id_direccion'] = $id_direccion;
            }
            
            if (!empty($updateParts)) {
                $sqlUpdate = "UPDATE personas SET " . implode(', ', $updateParts) . " WHERE dni = :dni";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute($updateParams);
            }
        }
        
        // PASO 4: Crear usuario (ahora la persona definitivamente existe)
        $userSql = "
            INSERT INTO usuarios (id_rol, dni_persona, usuario, pwd, estado, fecha_creacion)
            VALUES (:id_rol, :dni_persona, :usuario, :pwd, :estado, NOW())
        ";
        $userStmt = $pdo->prepare($userSql);
        $userStmt->execute([
            ':id_rol' => $id_rol,
            ':dni_persona' => $dni,
            ':usuario' => $input['usuario'],
            ':pwd' => password_hash($input['pwd'], PASSWORD_DEFAULT),
            ':estado' => $estado
        ]);
        
        $usuarioId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        $mensaje = $personaExiste ? 
            'Usuario creado exitosamente (persona ya existía)' : 
            'Usuario y persona creados exitosamente';
            
        jsonResponse(true, $mensaje, ['id_usuario' => $usuarioId, 'dni_persona' => $dni], 201);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (strpos($e->getMessage(), 'duplicate key') !== false) {
            jsonResponse(false, 'El usuario o DNI ya existe', null, 400);
        }
        jsonResponse(false, 'Error al crear usuario: ' . $e->getMessage(), null, 500);
    }
}

function handlePut() {
    global $input;
    $user = requireAdmin(); // Solo administradores pueden editar usuarios
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de usuario requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    try {
        // Acción especial: restablecer contraseña
        if (isset($input['action']) && $input['action'] === 'reset_password') {
            // Obtener la fecha de nacimiento de la persona asociada al usuario
            $getUserSql = "
                SELECT u.dni_persona, p.fecha_nacimiento, p.nombres, p.apellido_paterno 
                FROM usuarios u 
                INNER JOIN personas p ON u.dni_persona = p.dni 
                WHERE u.id_usuario = :id
            ";
            $getUserStmt = $pdo->prepare($getUserSql);
            $getUserStmt->execute([':id' => $id]);
            $userData = $getUserStmt->fetch();
            
            if (!$userData) {
                jsonResponse(false, 'Usuario no encontrado', null, 404);
            }
            
            if (!$userData['fecha_nacimiento']) {
                jsonResponse(false, 'No se puede restablecer la contraseña: la persona no tiene fecha de nacimiento registrada', null, 400);
            }
            
            // Generar nueva contraseña con fecha de nacimiento (AAAAMMDD)
            $fechaNac = new DateTime($userData['fecha_nacimiento']);
            $newPassword = $fechaNac->format('Ymd'); // AAAAMMDD
            
            // Actualizar contraseña
            $updatePwdSql = "UPDATE usuarios SET pwd = :pwd WHERE id_usuario = :id";
            $updatePwdStmt = $pdo->prepare($updatePwdSql);
            $updatePwdStmt->execute([
                ':pwd' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':id' => $id
            ]);
            
            jsonResponse(true, 'Contraseña restablecida exitosamente', [
                'nueva_password' => $newPassword,
                'usuario' => $userData['nombres'] . ' ' . $userData['apellido_paterno']
            ]);
            return;
        }
        
        // Actualización normal de usuario
        $updateFields = [];
        $params = [':id' => $id];
        
        if (isset($input['estado'])) {
            $updateFields[] = "estado = :estado";
            $params[':estado'] = $input['estado'];
        }
        if (isset($input['id_rol'])) {
            if (!in_array($input['id_rol'], [ROLE_ADMIN, ROLE_GERENTE, ROLE_TRABAJADOR])) {
                jsonResponse(false, 'Rol inválido', null, 400);
            }
            $updateFields[] = "id_rol = :id_rol";
            $params[':id_rol'] = $input['id_rol'];
        }
        if (isset($input['password']) && !empty($input['password'])) {
            $updateFields[] = "pwd = :pwd";
            $params[':pwd'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        if (isset($input['usuario']) && !empty($input['usuario'])) {
            // Verificar que el nuevo nombre de usuario no exista (excepto para el usuario actual)
            $checkUserSql = "SELECT id_usuario FROM usuarios WHERE usuario = :usuario AND id_usuario != :id";
            $checkUserStmt = $pdo->prepare($checkUserSql);
            $checkUserStmt->execute([':usuario' => $input['usuario'], ':id' => $id]);
            
            if ($checkUserStmt->fetch()) {
                jsonResponse(false, 'El nombre de usuario ya existe', null, 400);
            }
            
            $updateFields[] = "usuario = :usuario";
            $params[':usuario'] = $input['usuario'];
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id_usuario = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        jsonResponse(true, 'Usuario actualizado exitosamente');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al actualizar usuario: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete() {
    $user = requireAdmin(); // Solo administradores pueden eliminar usuarios
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de usuario requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    // No permitir que el admin se elimine a sí mismo
    if ($id == $user['id_usuario']) {
        jsonResponse(false, 'No puedes eliminar tu propia cuenta', null, 400);
    }
    
    try {
        // Soft delete - cambiar estado a Inactivo
        $sql = "UPDATE usuarios SET estado = 'Inactivo' WHERE id_usuario = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(true, 'Usuario desactivado exitosamente');
        } else {
            jsonResponse(false, 'Usuario no encontrado', null, 404);
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al eliminar usuario: ' . $e->getMessage(), null, 500);
    }
}
?>