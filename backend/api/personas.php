<?php
// BACKEND/api/personas.php - API Auto-generada
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
    $pdo = getPDO();

    // Búsqueda por q (permite Gerente/Trabajador/Admin)
    if (isset($_GET['q'])) {
        $user = requireTrabajador();
        $q = trim($_GET['q']);
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // Buscar por DNI o nombres/apellidos
        $sql = "SELECT p.dni, p.nombres, p.apellido_paterno, p.apellido_materno, u.id_rol AS rol_usuario
                FROM personas p
                LEFT JOIN usuarios u ON p.dni = u.dni_persona
                WHERE p.dni LIKE :q OR p.nombres LIKE :q OR p.apellido_paterno LIKE :q OR p.apellido_materno LIKE :q
                ORDER BY p.apellido_paterno, p.apellido_materno, p.nombres
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $like = "%$q%";
        $stmt->bindValue(':q', $like, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        jsonResponse(true, 'Resultados de búsqueda', [
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($items)
            ]
        ]);
        return;
    }

    // Obtener uno por ID (solo Admin)
    if (isset($_GET['id'])) {
        $user = requireAdmin();
        $id = $_GET['id'];
        $sql = "SELECT p.*, 
                       co.correo1 as correo, 
                       ct.numero1 as telefono, 
                       d.direccion1 as direccion
                FROM personas p
                LEFT JOIN correos co ON p.id_correo = co.id_correo
                LEFT JOIN contacto ct ON p.id_contacto = ct.id_contacto
                LEFT JOIN direccion d ON p.id_direccion = d.id_direccion
                WHERE p.dni = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();
        
        if ($item) {
            jsonResponse(true, 'Registro encontrado', $item);
        } else {
            jsonResponse(false, 'Registro no encontrado', null, 404);
        }
        return;
    }

    // Listado general (permite trabajadores, gerentes y admin)
    $user = requireTrabajador();
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = ($page - 1) * $limit;
    
        $sql = "SELECT p.*, 
                       co.correo1 as correo, 
                       ct.numero1 as telefono, 
                       d.direccion1 as direccion,
                       u.id_rol AS rol_usuario
                FROM personas p
                LEFT JOIN correos co ON p.id_correo = co.id_correo
                LEFT JOIN contacto ct ON p.id_contacto = ct.id_contacto
                LEFT JOIN direccion d ON p.id_direccion = d.id_direccion
                LEFT JOIN usuarios u ON p.dni = u.dni_persona
                ORDER BY p.dni DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    $countSql = "SELECT COUNT(*) as total FROM personas";
    $countStmt = $pdo->query($countSql);
    $total = $countStmt->fetch()['total'];
    
    jsonResponse(true, 'Registros obtenidos', [
        'data' => $items,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handlePost() {
    global $input;
    $user = requireTrabajador();
    $pdo = getPDO();
    
    $required = ['dni', 'nombres', 'apellido_paterno'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            jsonResponse(false, "Campo requerido: $field", null, 400);
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        $dni = $input['dni'];
        
        // Evitar duplicados
        $existsStmt = $pdo->prepare("SELECT dni FROM personas WHERE dni = :dni");
        $existsStmt->execute([':dni' => $dni]);
        if ($existsStmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(false, 'La persona ya está registrada', null, 409);
        }
        
        // Preparar datos de persona (excluir correo, telefono, direccion)
        $personaData = $input;
        unset($personaData['correo']);
        unset($personaData['telefono']);
        unset($personaData['direccion']);

        // Asegurar valores por defecto en campos opcionales
        $personaData = array_merge([
            'apellido_materno' => $personaData['apellido_materno'] ?? '',
            'nivel_educativo' => $personaData['nivel_educativo'] ?? null
        ], $personaData);

        // Campos permitidos en tabla personas (sin los IDs de tablas relacionadas por ahora)
        $allowedFields = [
            'dni',
            'nombres',
            'apellido_paterno',
            'apellido_materno',
            'sexo',
            'fecha_nacimiento',
            'nacionalidad',
            'estado_civil',
            'nivel_educativo',
            'fecha_registro'
        ];
        $personaData = array_intersect_key($personaData, array_flip($allowedFields));

        // Normalizar valores opcionales
        if (isset($personaData['fecha_nacimiento']) && $personaData['fecha_nacimiento'] === '') {
            $personaData['fecha_nacimiento'] = null;
        }
        if (!isset($personaData['sexo']) || empty($personaData['sexo'])) {
            $personaData['sexo'] = 'M';
        }
        if (!isset($personaData['nacionalidad']) || empty($personaData['nacionalidad'])) {
            $personaData['nacionalidad'] = 'Peruana';
        }
        if (!isset($personaData['estado_civil']) || empty($personaData['estado_civil'])) {
            $personaData['estado_civil'] = 'Soltero';
        }
        if (!isset($personaData['fecha_registro']) || empty($personaData['fecha_registro'])) {
            $personaData['fecha_registro'] = date('Y-m-d H:i:s');
        }
        
        // PRIMERO: Crear persona (sin IDs de tablas relacionadas)
        $fields = array_keys($personaData);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO personas (" . implode(', ', $fields) . ") VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($personaData);
        
        // SEGUNDO: Ahora que la persona existe, crear registros relacionados
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
        
        // TERCERO: Actualizar persona con los IDs de las tablas relacionadas
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
        
        $pdo->commit();
        jsonResponse(true, 'Persona creada exitosamente', ['dni' => $dni], 201);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al crear registro: ' . $e->getMessage(), null, 500);
    }
}

function handlePut() {
    global $input;
    $user = requireAdmin();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID requerido', null, 400);
    }
    
    $dni = $_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        // Obtener IDs actuales de la persona
        $sqlPersona = "SELECT id_correo, id_contacto, id_direccion FROM personas WHERE dni = :dni";
        $stmtPersona = $pdo->prepare($sqlPersona);
        $stmtPersona->execute([':dni' => $dni]);
        $persona = $stmtPersona->fetch();
        
        // Actualizar o crear correo
        if (isset($input['correo'])) {
            if ($persona['id_correo']) {
                $sqlCorreo = "UPDATE correos SET correo1 = :correo WHERE id_correo = :id";
                $stmtCorreo = $pdo->prepare($sqlCorreo);
                $stmtCorreo->execute([':correo' => $input['correo'], ':id' => $persona['id_correo']]);
            } else if (!empty($input['correo'])) {
                $sqlCorreo = "INSERT INTO correos (dni_persona, cuantas_correos, correo1) VALUES (:dni, 1, :correo)";
                $stmtCorreo = $pdo->prepare($sqlCorreo);
                $stmtCorreo->execute([':dni' => $dni, ':correo' => $input['correo']]);
                $input['id_correo'] = $pdo->lastInsertId();
            }
        }
        
        // Actualizar o crear contacto
        if (isset($input['telefono'])) {
            if ($persona['id_contacto']) {
                $sqlContacto = "UPDATE contacto SET numero1 = :telefono WHERE id_contacto = :id";
                $stmtContacto = $pdo->prepare($sqlContacto);
                $stmtContacto->execute([':telefono' => $input['telefono'], ':id' => $persona['id_contacto']]);
            } else if (!empty($input['telefono'])) {
                $sqlContacto = "INSERT INTO contacto (dni_persona, cantidad_numeros, numero1) VALUES (:dni, 1, :telefono)";
                $stmtContacto = $pdo->prepare($sqlContacto);
                $stmtContacto->execute([':dni' => $dni, ':telefono' => $input['telefono']]);
                $input['id_contacto'] = $pdo->lastInsertId();
            }
        }
        
        // Actualizar o crear direccion
        if (isset($input['direccion'])) {
            if ($persona['id_direccion']) {
                $sqlDireccion = "UPDATE direccion SET direccion1 = :direccion WHERE id_direccion = :id";
                $stmtDireccion = $pdo->prepare($sqlDireccion);
                $stmtDireccion->execute([':direccion' => $input['direccion'], ':id' => $persona['id_direccion']]);
            } else if (!empty($input['direccion'])) {
                $sqlDireccion = "INSERT INTO direccion (dni_persona, cuantas_direcciones, direccion1) VALUES (:dni, 1, :direccion)";
                $stmtDireccion = $pdo->prepare($sqlDireccion);
                $stmtDireccion->execute([':dni' => $dni, ':direccion' => $input['direccion']]);
                $input['id_direccion'] = $pdo->lastInsertId();
            }
        }
        
        // Preparar datos de persona (excluir correo, telefono, direccion)
        $personaData = $input;
        unset($personaData['correo']);
        unset($personaData['telefono']);
        unset($personaData['direccion']);
        
        // Actualizar persona
        $updateFields = [];
        $params = [':id' => $dni];
        
        foreach ($personaData as $field => $value) {
            $updateFields[] = "$field = :$field";
            $params[":$field"] = $value;
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE personas SET " . implode(', ', $updateFields) . " WHERE dni = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        $pdo->commit();
        jsonResponse(true, 'Registro actualizado exitosamente');
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al actualizar registro: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete() {
    $user = requireAdmin();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    try {
        $sql = "DELETE FROM personas WHERE dni = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(true, 'Registro eliminado exitosamente');
        } else {
            jsonResponse(false, 'Registro no encontrado', null, 404);
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al eliminar registro: ' . $e->getMessage(), null, 500);
    }
}
?>