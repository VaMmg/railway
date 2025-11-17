<?php
// BACKEND/api/cajas.php
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';
require_once '../helpers/notificaciones_helper.php';

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
    $user = requireTrabajador(); // Trabajadores y superiores pueden ver cajas
    $pdo = getPDO();
    $auth = new AuthSystem();
    
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
            // Buscar la caja más reciente del usuario (cualquier estado excepto eliminada)
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
            
            error_log("Buscando caja para usuario {$user['id_usuario']}: " . ($caja ? "Encontrada (ID: {$caja['id_cajas_usuario']}, Estado: {$caja['estado_caja']})" : "No encontrada"));
            
            if ($caja) {
                jsonResponse(true, 'Caja encontrada', ['caja' => $caja]);
            } else {
                jsonResponse(true, 'No tienes caja asignada', ['caja' => null]);
            }
        } catch (PDOException $e) {
            error_log("Error al obtener caja: " . $e->getMessage());
            jsonResponse(false, 'Error al obtener caja: ' . $e->getMessage(), null, 500);
        }
        return;
    }
    
    if (isset($_GET['status'])) {
        // Obtener estado de cajas
        $response = [
            'main_cash_open' => $auth->isMainCashOpen(),
            'user_can_open' => false,
            'user_cash_open' => false,
            'reason' => ''
        ];
        
        if ($user['id_rol'] == ROLE_TRABAJADOR) {
            $canOpen = $auth->canOpenWorkerCash($user['id_usuario']);
            $response['user_can_open'] = $canOpen['can_open'];
            $response['reason'] = $canOpen['reason'];
            
            // Verificar si ya tiene caja abierta
            $sql = "
                SELECT COUNT(*) as count
                FROM cajas_usuario
                WHERE id_usuario = :user_id 
                AND estado_caja = 'Abierta'
                AND DATE(fecha_creacion_caja) = CURRENT_DATE
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => $user['id_usuario']]);
            $response['user_cash_open'] = $stmt->fetch()['count'] > 0;
        }
        
        jsonResponse(true, 'Estado de cajas obtenido', $response);
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
        // Obtener cajas con filtros
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $where = "WHERE 1=1";
        $params = [];
        
        // Filtro por fecha (opcional)
        if (isset($_GET['fecha'])) {
            $where .= " AND DATE(c.fecha_creacion_caja) = :fecha";
            $params[':fecha'] = $_GET['fecha'];
        }
        
        // Filtro por usuario (para trabajadores solo sus cajas)
        if ($user['id_rol'] == ROLE_TRABAJADOR) {
            $where .= " AND c.id_usuario = :user_id";
            $params[':user_id'] = $user['id_usuario'];
        }
        
        // Para gerentes, mostrar solo la última caja de cada trabajador
        $sql = "
            SELECT c.id_cajas_usuario, c.hora_apertura, c.saldo_actual, c.estado_caja,
                   c.hora_cierre, c.fecha_creacion_caja, c.limite_credito, c.habilitada_por_gerente,
                   c.comentario,
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
        
        // Contar total (última caja de cada usuario)
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
    $user = requireTrabajador(); // Trabajadores y superiores pueden abrir cajas
    $pdo = getPDO();
    $auth = new AuthSystem();
    
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
        
        $saldoInicial = $input['saldo_inicial'] ?? 0;
        
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
            // Verificar que no haya cajas de trabajadores abiertas
            $checkSql = "SELECT COUNT(*) as count FROM cajas_usuario WHERE estado_caja = 'Abierta' AND DATE(fecha_creacion_caja) = CURRENT_DATE";
            $checkStmt = $pdo->query($checkSql);
            $result = $checkStmt->fetch();
            
            if ($result['count'] > 0) {
                jsonResponse(false, 'No puedes cerrar la caja principal mientras haya cajas de trabajadores abiertas', null, 400);
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
        // Obtener el ID del usuario al que se le asignará la caja
        // Si viene id_usuario en el input, es porque un gerente está asignando (usar ese)
        // Si no viene, es porque el usuario está abriendo su propia caja (usar el del token)
        $idUsuarioAsignado = $input['id_usuario'] ?? $user['id_usuario'];
        
        $saldoInicial = $input['saldo_inicial'] ?? 0;
        $limiteCredito = $input['limite_credito'] ?? 0;
        
        // Determinar si es gerente asignando o trabajador activando
        $esGerenteAsignando = isset($input['id_usuario']);
        
        try {
            $sql = "
                INSERT INTO cajas_usuario (id_usuario, limite_credito, hora_apertura, saldo_actual,
                                         estado_caja, habilitada_por_gerente, fecha_creacion_caja)
                VALUES (:id_usuario, :limite_credito, NOW(), :saldo_inicial, :estado, :habilitada, NOW())
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $idUsuarioAsignado,
                ':limite_credito' => $limiteCredito,
                ':saldo_inicial' => $saldoInicial,
                ':estado' => $esGerenteAsignando ? 'Cerrada' : 'Abierta',
                ':habilitada' => $esGerenteAsignando ? 1 : 0
            ]);
            
            $cajaId = $pdo->lastInsertId();
            
            // Notificar al gerente si el trabajador abrió su caja
            if (!$esGerenteAsignando) {
                $usuarioSql = "SELECT CONCAT(p.nombres, ' ', p.apellido_paterno) as nombre_completo 
                              FROM usuarios u 
                              INNER JOIN personas p ON u.dni_persona = p.dni 
                              WHERE u.id_usuario = :id";
                $usuarioStmt = $pdo->prepare($usuarioSql);
                $usuarioStmt->execute([':id' => $idUsuarioAsignado]);
                $nombreUsuario = $usuarioStmt->fetchColumn();
                
                notificarGerente(
                    $pdo,
                    'caja_abierta',
                    "{$nombreUsuario} abrió su caja con saldo inicial de S/ " . number_format($saldoInicial, 2),
                    $user['id_usuario'],
                    $cajaId,
                    'caja'
                );
            }
            
            jsonResponse(true, 'Caja asignada exitosamente', ['id_caja' => $cajaId], 201);
            
        } catch (PDOException $e) {
            jsonResponse(false, 'Error al asignar caja: ' . $e->getMessage(), null, 500);
        }
        
    } elseif ($action === 'habilitar') {
        // Solo gerentes pueden habilitar/deshabilitar cajas
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
        // Cerrar caja
        $idCaja = $input['id_caja'] ?? null;
        $comentario = $input['comentario'] ?? '';
        
        if (!$idCaja) {
            jsonResponse(false, 'ID de caja requerido', null, 400);
        }
        
        try {
            // Verificar que la caja pertenezca al usuario
            $checkSql = "SELECT id_usuario, estado_caja FROM cajas_usuario WHERE id_cajas_usuario = :id";
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
            
            // Obtener saldo final para la notificación
            $saldoSql = "SELECT saldo_final FROM cajas_usuario WHERE id_cajas_usuario = :id";
            $saldoStmt = $pdo->prepare($saldoSql);
            $saldoStmt->execute([':id' => $idCaja]);
            $saldoFinal = $saldoStmt->fetchColumn();
            
            // Notificar al gerente
            $usuarioSql = "SELECT CONCAT(p.nombres, ' ', p.apellido_paterno) as nombre_completo 
                          FROM usuarios u 
                          INNER JOIN personas p ON u.dni_persona = p.dni 
                          WHERE u.id_usuario = :id";
            $usuarioStmt = $pdo->prepare($usuarioSql);
            $usuarioStmt->execute([':id' => $user['id_usuario']]);
            $nombreUsuario = $usuarioStmt->fetchColumn();
            
            notificarGerente(
                $pdo,
                'caja_cerrada',
                "{$nombreUsuario} cerró su caja con saldo final de S/ " . number_format($saldoFinal, 2),
                $user['id_usuario'],
                $idCaja,
                'caja'
            );
            
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
    
    // Verificar que la caja pertenezca al usuario (excepto admin/gerente)
    if ($user['id_rol'] == ROLE_TRABAJADOR) {
        $checkSql = "SELECT id_usuario FROM cajas_usuario WHERE id_cajas_usuario = :id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':id' => $id]);
        $caja = $checkStmt->fetch();
        
        if (!$caja || $caja['id_usuario'] != $user['id_usuario']) {
            jsonResponse(false, 'No tienes permisos para editar esta caja', null, 403);
        }
    }
    
    $action = $input['action'] ?? '';
    
    // Actualizar estado de la caja
    if ($action === 'actualizar_estado') {
        $nuevoEstado = $input['estado_caja'] ?? '';
        
        if (!in_array($nuevoEstado, ['Abierta', 'Cerrada'])) {
            jsonResponse(false, 'Estado no válido', null, 400);
        }
        
        try {
            // Verificar si la caja está habilitada por el gerente antes de permitir abrirla
            if ($nuevoEstado === 'Abierta') {
                // 1. Verificar que la caja principal esté abierta
                $cajaPrincipalSql = "SELECT estado_caja FROM cajas WHERE DATE(fecha_creacion) = CURRENT_DATE ORDER BY hora_apertura DESC LIMIT 1";
                $cajaPrincipalStmt = $pdo->query($cajaPrincipalSql);
                $cajaPrincipal = $cajaPrincipalStmt->fetch();
                
                if (!$cajaPrincipal || $cajaPrincipal['estado_caja'] !== 'Abierta') {
                    jsonResponse(false, 'La caja principal debe estar abierta primero. Contacta con tu gerente.', null, 403);
                }
                
                // 2. Verificar que la caja esté habilitada por el gerente
                $checkSql = "SELECT habilitada_por_gerente FROM cajas_usuario WHERE id_cajas_usuario = :id";
                $checkStmt = $pdo->prepare($checkSql);
                $checkStmt->execute([':id' => $id]);
                $caja = $checkStmt->fetch();
                
                if (!$caja || !$caja['habilitada_por_gerente']) {
                    jsonResponse(false, 'Tu caja debe ser habilitada por el gerente primero', null, 403);
                }
            }
            
            $sql = "UPDATE cajas_usuario SET estado_caja = :estado";
            
            // Si se está abriendo, actualizar hora_apertura
            if ($nuevoEstado === 'Abierta') {
                $sql .= ", hora_apertura = NOW(), hora_cierre = NULL";
            }
            // Si se está cerrando, actualizar hora_cierre y saldo_final
            elseif ($nuevoEstado === 'Cerrada') {
                $sql .= ", hora_cierre = NOW(), saldo_final = saldo_actual";
            }
            
            $sql .= " WHERE id_cajas_usuario = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':estado' => $nuevoEstado,
                ':id' => $id
            ]);
            
            jsonResponse(true, 'Estado actualizado exitosamente');
            return;
            
        } catch (PDOException $e) {
            jsonResponse(false, 'Error al actualizar estado: ' . $e->getMessage(), null, 500);
        }
    }
    
    try {
        $updateFields = [];
        $params = [':id' => $id];
        
        if (isset($input['tipo_transaccion']) && isset($input['monto'])) {
            $monto = (float)$input['monto'];
            $tipo = $input['tipo_transaccion'];
            
            // Obtener saldo actual
            $saldoSql = "SELECT saldo_actual FROM cajas_usuario WHERE id_cajas_usuario = :id";
            $saldoStmt = $pdo->prepare($saldoSql);
            $saldoStmt->execute([':id' => $id]);
            $saldoActual = $saldoStmt->fetch()['saldo_actual'];
            
            $nuevoSaldo = $saldoActual;
            if ($tipo === 'Ingreso') {
                $nuevoSaldo += $monto;
            } elseif ($tipo === 'Egreso') {
                if ($saldoActual < $monto) {
                    jsonResponse(false, 'Saldo insuficiente', null, 400);
                }
                $nuevoSaldo -= $monto;
            }
            
            $updateFields[] = "saldo_actual = :saldo_actual";
            $updateFields[] = "tipo_transaccion = :tipo_transaccion";
            $updateFields[] = "monto = :monto";
            $params[':saldo_actual'] = $nuevoSaldo;
            $params[':tipo_transaccion'] = $tipo;
            $params[':monto'] = $monto;
        }
        
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
    $user = requireGerente(); // Solo gerentes y admin pueden eliminar cajas
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de caja requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    try {
        // No eliminar, solo cerrar si está abierta
        $sql = "
            UPDATE cajas_usuario 
            SET estado_caja = 'Cancelada',
                hora_cierre = NOW(),
                comentario = COALESCE(comentario, '') || ' - Cancelada por: ' || :user_name
            WHERE id_cajas_usuario = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':user_name' => $user['nombres'] . ' ' . $user['apellido_paterno']
        ]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(true, 'Caja cancelada exitosamente');
        } else {
            jsonResponse(false, 'Caja no encontrada', null, 404);
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al cancelar caja: ' . $e->getMessage(), null, 500);
    }
}
?>