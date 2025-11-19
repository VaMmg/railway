<?php
// BACKEND/api/cajas.php
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
    $user = requireTrabajador();
    $pdo = getPDO();
    
    // Obtener estado de la caja principal
    if (isset($_GET['action']) && $_GET['action'] === 'caja_principal') {
        try {
            $sql = "
                SELECT c.*
                FROM cajas c
                WHERE DATE(c.fecha_creacion) = CURRENT_DATE
                ORDER BY c.hora_apertura DESC
                LIMIT 1
            ";
            $stmt = $pdo->query($sql);
            $cajaPrincipal = $stmt->fetch();
            
            jsonResponse(true, 'Estado de caja principal', [
                'caja_principal' => $cajaPrincipal,
                'esta_abierta' => $cajaPrincipal && $cajaPrincipal['estado_caja'] === 'Abierta'
            ]);
        } catch (PDOException $e) {
            jsonResponse(false, 'Error al obtener caja principal: ' . $e->getMessage(), null, 500);
        }
        return;
    }
    
    // Obtener caja del trabajador actual
    if (isset($_GET['action']) && $_GET['action'] === 'mi_caja') {
        try {
            $sql = "
                SELECT c.*
                FROM cajas_usuario c
                WHERE c.id_usuario = :user_id
                ORDER BY c.fecha_creacion_caja DESC, c.hora_apertura DESC
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => $user['id_usuario']]);
            $caja = $stmt->fetch();
            
            if ($caja) {
                jsonResponse(true, 'Caja encontrada', ['caja' => $caja]);
            } else {
                jsonResponse(true, 'No tienes caja asignada', ['caja' => null]);
            }
        } catch (PDOException $e) {
            jsonResponse(false, 'Error al obtener caja: ' . $e->getMessage(), null, 500);
        }
        return;
    }
    
    if (isset($_GET['id'])) {
        // Obtener caja específica
        $id = $_GET['id'];
        $sql = "
            SELECT c.*, u.usuario, p.nombres, p.apellido_paterno
            FROM cajas_usuario c
            INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
            INNER JOIN personas p ON u.dni_persona = p.dni
            WHERE c.id_cajas_usuario = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $caja = $stmt->fetch();
        
        if ($caja) {
            jsonResponse(true, 'Caja encontrada', $caja);
        } else {
            jsonResponse(false, 'Caja no encontrada', null, 404);
        }
    } else {
        // Obtener todas las cajas (para gerentes)
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = ($page - 1) * $limit;
        
        $where = "WHERE 1=1";
        $params = [];
        
        // Filtro por usuario (para trabajadores solo sus cajas)
        if ($user['id_rol'] == ROLE_TRABAJADOR) {
            $where .= " AND c.id_usuario = :user_id";
            $params[':user_id'] = $user['id_usuario'];
        }
        
        // Mostrar solo la última caja de cada trabajador
        $sql = "
            SELECT c.id_cajas_usuario, c.hora_apertura, c.saldo_actual, c.estado_caja,
                   c.hora_cierre, c.fecha_creacion_caja, c.limite_credito, c.habilitada_por_gerente,
                   c.comentario, c.saldo_final,
                   u.usuario, p.nombres, p.apellido_paterno, u.id_rol
            FROM cajas_usuario c
            INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
            INNER JOIN personas p ON u.dni_persona = p.dni
            INNER JOIN (
                SELECT id_usuario, MAX(id_cajas_usuario) as ultima_caja
                FROM cajas_usuario
                GROUP BY id_usuario
            ) ultima ON c.id_usuario = ultima.id_usuario AND c.id_cajas_usuario = ultima.ultima_caja
            $where
            ORDER BY c.hora_apertura DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $cajas = $stmt->fetchAll();
        
        // Convertir habilitada_por_gerente a booleano
        foreach ($cajas as &$caja) {
            $caja['habilitada_por_gerente'] = (bool)$caja['habilitada_por_gerente'];
        }
        
        // Contar total
        $countSql = "
            SELECT COUNT(*) as total 
            FROM cajas_usuario c 
            INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
            INNER JOIN (
                SELECT id_usuario, MAX(id_cajas_usuario) as ultima_caja
                FROM cajas_usuario
                GROUP BY id_usuario
            ) ultima ON c.id_usuario = ultima.id_usuario AND c.id_cajas_usuario = ultima.ultima_caja
            $where
        ";
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetch()['total'];
        
        jsonResponse(true, 'Cajas obtenidas', [
            'cajas' => $cajas,
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
    $user = requireTrabajador();
    $pdo = getPDO();
    
    $action = $input['action'] ?? 'abrir';
    
    if ($action === 'abrir_caja_principal') {
        // Solo gerentes pueden abrir la caja principal
        $roleName = strtolower($user['nombre_rol'] ?? '');
        $esGerente = strpos($roleName, 'gerente') !== false;
        $esAdmin = strpos($roleName, 'administrador') !== false;
        
        if (!$esGerente && !$esAdmin) {
            jsonResponse(false, 'Solo gerentes pueden abrir la caja principal', null, 403);
        }
        
        // Verificar si ya hay una caja principal abierta hoy
        $checkSql = "SELECT id_caja FROM cajas WHERE DATE(fecha_creacion) = CURRENT_DATE AND estado_caja = 'Abierta'";
        $checkStmt = $pdo->query($checkSql);
        if ($checkStmt->fetch()) {
            jsonResponse(false, 'Ya existe una caja principal abierta hoy', null, 400);
        }
        
        $saldoInicial = $input['saldo_inicial'] ?? 5000;
        
        try {
            $sql = "
                INSERT INTO cajas (id_usuario_gerente, hora_apertura, saldo_actual, estado_caja, fecha_creacion)
                VALUES (:id_usuario, NOW(), :saldo_inicial, 'Abierta', NOW())
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $user['id_usuario'],
                ':saldo_inicial' => $saldoInicial
            ]);
            
            jsonResponse(true, 'Caja principal abierta exitosamente', ['id_caja' => $pdo->lastInsertId()], 201);
            
        } catch (PDOException $e) {
            jsonResponse(false, 'Error al abrir caja principal: ' . $e->getMessage(), null, 500);
        }
        
    } elseif ($action === 'cerrar_caja_principal') {
        // Solo gerentes pueden cerrar la caja principal
        $roleName = strtolower($user['nombre_rol'] ?? '');
        $esGerente = strpos($roleName, 'gerente') !== false;
        $esAdmin = strpos($roleName, 'administrador') !== false;
        
        if (!$esGerente && !$esAdmin) {
            jsonResponse(false, 'Solo gerentes pueden cerrar la caja principal', null, 403);
        }
        
        try {
            // VALIDACIÓN: Gerente NO puede cerrar la caja principal si hay cajas de trabajadores abiertas
            $checkSql = "
                SELECT COUNT(*) as count, 
                       GROUP_CONCAT(CONCAT(p.nombres, ' ', p.apellido_paterno) SEPARATOR ', ') as trabajadores
                FROM cajas_usuario cu
                INNER JOIN usuarios u ON cu.id_usuario = u.id_usuario
                INNER JOIN personas p ON u.dni_persona = p.dni
                WHERE cu.estado_caja = 'Abierta' 
                AND DATE(cu.fecha_creacion_caja) = CURRENT_DATE
            ";
            $checkStmt = $pdo->query($checkSql);
            $result = $checkStmt->fetch();
            
            if ($result['count'] > 0) {
                $mensaje = "No puedes cerrar la caja principal mientras haya cajas de trabajadores abiertas. ";
                $mensaje .= "Trabajadores con cajas abiertas: " . $result['trabajadores'];
                jsonResponse(false, $mensaje, null, 400);
            }
            
            $sql = "
                UPDATE cajas 
                SET estado_caja = 'Cerrada', hora_cierre = NOW(), saldo_final = saldo_actual
                WHERE DATE(fecha_creacion) = CURRENT_DATE AND estado_caja = 'Abierta'
            ";
            $pdo->query($sql);
            
            jsonResponse(true, 'Caja principal cerrada exitosamente');
            
        } catch (PDOException $e) {
            jsonResponse(false, 'Error al cerrar caja principal: ' . $e->getMessage(), null, 500);
        }
        
    } elseif ($action === 'abrir') {
        // Asignar caja a un trabajador (solo gerentes)
        $roleName = strtolower($user['nombre_rol'] ?? '');
        $esGerente = strpos($roleName, 'gerente') !== false;
        $esAdmin = strpos($roleName, 'administrador') !== false;
        
        if (!$esGerente && !$esAdmin) {
            jsonResponse(false, 'Solo gerentes pueden asignar cajas', null, 403);
        }
        
        $idUsuarioAsignado = $input['id_usuario'] ?? null;
        $saldoInicial = $input['saldo_inicial'] ?? 0;
        $limiteCredito = $input['limite_credito'] ?? 5000;
        
        if (!$idUsuarioAsignado) {
            jsonResponse(false, 'ID de usuario requerido', null, 400);
        }
        
        try {
            $sql = "
                INSERT INTO cajas_usuario (id_usuario, limite_credito, saldo_actual, estado_caja, 
                                          habilitada_por_gerente, fecha_creacion_caja, hora_apertura)
                VALUES (:id_usuario, :limite_credito, :saldo_inicial, 'Cerrada', 1, CURDATE(), NOW())
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $idUsuarioAsignado,
                ':limite_credito' => $limiteCredito,
                ':saldo_inicial' => $saldoInicial
            ]);
            
            $cajaId = $pdo->lastInsertId();
            
            jsonResponse(true, 'Caja asignada exitosamente', ['id_caja' => $cajaId], 201);
            
        } catch (PDOException $e) {
            jsonResponse(false, 'Error al asignar caja: ' . $e->getMessage(), null, 500);
        }
        
    } elseif ($action === 'habilitar') {
        // Habilitar/deshabilitar caja (solo gerentes)
        $roleName = strtolower($user['nombre_rol'] ?? '');
        $esGerente = strpos($roleName, 'gerente') !== false;
        $esAdmin = strpos($roleName, 'administrador') !== false;
        
        if (!$esGerente && !$esAdmin) {
            jsonResponse(false, 'Solo gerentes pueden habilitar cajas', null, 403);
        }
        
        $idCaja = $input['id_caja'] ?? null;
        $habilitar = $input['habilitar'] ?? true;
        
        if (!$idCaja) {
            jsonResponse(false, 'ID de caja requerido', null, 400);
        }
        
        try {
            $sql = "UPDATE cajas_usuario SET habilitada_por_gerente = :habilitar WHERE id_cajas_usuario = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':habilitar' => $habilitar ? 1 : 0,
                ':id' => $idCaja
            ]);
            
            jsonResponse(true, $habilitar ? 'Caja habilitada exitosamente' : 'Caja deshabilitada exitosamente');
            
        } catch (PDOException $e) {
            jsonResponse(false, 'Error al cambiar habilitación: ' . $e->getMessage(), null, 500);
        }
        
    } elseif ($action === 'cerrar') {
        // Cerrar caja (trabajador cierra su propia caja)
        $idCaja = $input['id_caja'] ?? null;
        $comentario = $input['comentario'] ?? '';
        
        if (!$idCaja) {
            jsonResponse(false, 'ID de caja requerido', null, 400);
        }
        
        try {
            // Verificar que la caja pertenezca al usuario
            $checkSql = "SELECT id_usuario, estado_caja, saldo_actual FROM cajas_usuario WHERE id_cajas_usuario = :id";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':id' => $idCaja]);
            $caja = $checkStmt->fetch();
            
            if (!$caja) {
                jsonResponse(false, 'Caja no encontrada', null, 404);
            }
            
            if ($caja['id_usuario'] != $user['id_usuario']) {
                jsonResponse(false, 'No tienes permisos para cerrar esta caja', null, 403);
            }
            
            if ($caja['estado_caja'] === 'Cerrada') {
                jsonResponse(false, 'La caja ya está cerrada', null, 400);
            }
            
            // Cerrar caja
            $updateSql = "
                UPDATE cajas_usuario 
                SET estado_caja = 'Cerrada',
                    hora_cierre = NOW(),
                    saldo_final = saldo_actual,
                    comentario = CONCAT(COALESCE(comentario, ''), ' ', :comentario)
                WHERE id_cajas_usuario = :id
            ";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':id' => $idCaja,
                ':comentario' => $comentario
            ]);
            
            jsonResponse(true, 'Caja cerrada exitosamente');
            
        } catch (PDOException $e) {
            jsonResponse(false, 'Error al cerrar caja: ' . $e->getMessage(), null, 500);
        }
        
    } else {
        jsonResponse(false, 'Acción no válida', null, 400);
    }
}

function handlePut() {
    global $input;
    $user = requireTrabajador();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de caja requerido', null, 400);
    }
    
    $id = $_GET['id'];
    $action = $input['action'] ?? '';
    
    // Actualizar estado de la caja (trabajador activa su caja)
    if ($action === 'actualizar_estado') {
        $nuevoEstado = $input['estado_caja'] ?? '';
        
        if (!in_array($nuevoEstado, ['Abierta', 'Cerrada'])) {
            jsonResponse(false, 'Estado no válido', null, 400);
        }
        
        try {
            // Verificar que la caja pertenezca al usuario
            $checkSql = "SELECT id_usuario, habilitada_por_gerente FROM cajas_usuario WHERE id_cajas_usuario = :id";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':id' => $id]);
            $caja = $checkStmt->fetch();
            
            if (!$caja) {
                jsonResponse(false, 'Caja no encontrada', null, 404);
            }
            
            if ($caja['id_usuario'] != $user['id_usuario']) {
                jsonResponse(false, 'No tienes permisos para modificar esta caja', null, 403);
            }
            
            // Si se está abriendo, verificar que esté habilitada
            if ($nuevoEstado === 'Abierta' && !$caja['habilitada_por_gerente']) {
                jsonResponse(false, 'Tu caja debe ser habilitada por el gerente primero', null, 403);
            }
            
            // VALIDACIÓN: Trabajador NO puede abrir su caja si la caja principal NO está abierta
            if ($nuevoEstado === 'Abierta') {
                $cajaPrincipalSql = "SELECT estado_caja FROM cajas WHERE DATE(fecha_creacion) = CURRENT_DATE ORDER BY hora_apertura DESC LIMIT 1";
                $cajaPrincipalStmt = $pdo->query($cajaPrincipalSql);
                $cajaPrincipal = $cajaPrincipalStmt->fetch();
                
                if (!$cajaPrincipal || $cajaPrincipal['estado_caja'] !== 'Abierta') {
                    jsonResponse(false, 'No puedes abrir tu caja porque la caja principal no está abierta. Contacta con tu gerente.', null, 403);
                }
            }
            
            $sql = "UPDATE cajas_usuario SET estado_caja = :estado";
            
            if ($nuevoEstado === 'Abierta') {
                $sql .= ", hora_apertura = NOW(), hora_cierre = NULL";
            } elseif ($nuevoEstado === 'Cerrada') {
                $sql .= ", hora_cierre = NOW(), saldo_final = saldo_actual";
            }
            
            $sql .= " WHERE id_cajas_usuario = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':estado' => $nuevoEstado,
                ':id' => $id
            ]);
            
            jsonResponse(true, 'Estado actualizado exitosamente');
            
        } catch (PDOException $e) {
            jsonResponse(false, 'Error al actualizar estado: ' . $e->getMessage(), null, 500);
        }
        return;
    }
    
    // Actualizar otros campos
    try {
        $updateFields = [];
        $params = [':id' => $id];
        
        if (isset($input['comentario'])) {
            $updateFields[] = "comentario = :comentario";
            $params[':comentario'] = $input['comentario'];
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE cajas_usuario SET " . implode(', ', $updateFields) . " WHERE id_cajas_usuario = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        jsonResponse(true, 'Caja actualizada exitosamente');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al actualizar caja: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete() {
    $user = requireGerente();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de caja requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    try {
        // Eliminar la caja
        $sql = "DELETE FROM cajas_usuario WHERE id_cajas_usuario = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(true, 'Caja eliminada exitosamente');
        } else {
            jsonResponse(false, 'Caja no encontrada', null, 404);
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al eliminar caja: ' . $e->getMessage(), null, 500);
    }
}
?>
