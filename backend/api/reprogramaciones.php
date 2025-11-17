<?php
// BACKEND/api/reprogramaciones.php
require_once '../config/conexion.php';
require_once '../config/cors.php';

setCorsHeaders();

// Función para redondear según la monetización peruana
// Moneda mínima: 10 céntimos (S/ 0.10)
// Redondea al múltiplo de 0.10 más cercano
function redondearMonedaPeruana($valor) {
    // Redondear a múltiplos de 0.10 (10 céntimos)
    return round($valor * 10) / 10;
}

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
    default:
        jsonResponse(false, 'Método no soportado', null, 405);
}

function handleGet() {
    $user = requireAuth();
    $pdo = getPDO();
    
    if (isset($_GET['id'])) {
        // Obtener reprogramación específica
        $id = $_GET['id'];
        $sql = "
            SELECT r.*,
                   c.id_credito, c.monto_aprobado, c.plazos_meses, c.tasa_interes, c.periodo_pago,
                   p.nombres, p.apellido_paterno, p.apellido_materno,
                   u1.usuario as usuario_solicita,
                   u2.usuario as usuario_aprueba
            FROM reprogramaciones r
            INNER JOIN creditos c ON r.id_credito = c.id_credito
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            INNER JOIN personas p ON cl.dni_persona = p.dni
            LEFT JOIN usuarios u1 ON r.usuario_registro = u1.id_usuario
            LEFT JOIN usuarios u2 ON r.usuario_aprobacion = u2.id_usuario
            WHERE r.id_reprogramacion = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $reprogramacion = $stmt->fetch();
        
        if ($reprogramacion) {
            jsonResponse(true, 'Reprogramación obtenida', $reprogramacion);
        } else {
            jsonResponse(false, 'Reprogramación no encontrada', null, 404);
        }
    } else {
        // Listar reprogramaciones con filtros
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        
        // Filtro por crédito
        if (isset($_GET['credito_id'])) {
            $where[] = "r.id_credito = :credito_id";
            $params[':credito_id'] = $_GET['credito_id'];
        }
        
        // Filtro por estatus
        if (isset($_GET['estatus'])) {
            $where[] = "r.estatus = :estatus";
            $params[':estatus'] = $_GET['estatus'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM reprogramaciones r $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // Obtener reprogramaciones
        $sql = "
            SELECT r.*,
                   c.id_credito, c.monto_aprobado, c.plazos_meses, c.tasa_interes, c.periodo_pago,
                   p.nombres, p.apellido_paterno, p.apellido_materno,
                   u1.usuario as usuario_solicita,
                   u2.usuario as usuario_aprueba
            FROM reprogramaciones r
            INNER JOIN creditos c ON r.id_credito = c.id_credito
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            INNER JOIN personas p ON cl.dni_persona = p.dni
            LEFT JOIN usuarios u1 ON r.usuario_registro = u1.id_usuario
            LEFT JOIN usuarios u2 ON r.usuario_aprobacion = u2.id_usuario
            $whereClause
            ORDER BY r.fecha_creacion DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $reprogramaciones = $stmt->fetchAll();
        
        jsonResponse(true, 'Reprogramaciones obtenidas', [
            'reprogramaciones' => $reprogramaciones,
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
    $user = requireAuth();
    $pdo = getPDO();
    
    // Permitir a todos los usuarios autenticados solicitar reprogramaciones
    // La validación real se hará en la aprobación
    
    // Validar datos requeridos
    if (!isset($input['id_credito']) || !isset($input['motivo'])) {
        jsonResponse(false, 'Datos incompletos', null, 400);
    }
    
    $pdo->beginTransaction();
    
    try {
        // Obtener datos actuales del crédito
        $creditoSql = "SELECT * FROM creditos WHERE id_credito = :id";
        $creditoStmt = $pdo->prepare($creditoSql);
        $creditoStmt->execute([':id' => $input['id_credito']]);
        $credito = $creditoStmt->fetch();
        
        if (!$credito) {
            throw new Exception('Crédito no encontrado');
        }
        
        // Validar que el crédito esté en estado válido para reprogramar
        if (!in_array($credito['estado_credito'], ['Aprobado', 'En Mora'])) {
            throw new Exception('El crédito debe estar Aprobado o En Mora para poder reprogramarse');
        }
        
        // Verificar que no haya reprogramaciones pendientes
        $pendientesSql = "SELECT COUNT(*) FROM reprogramaciones WHERE id_credito = :id AND estatus = 'Pendiente'";
        $pendientesStmt = $pdo->prepare($pendientesSql);
        $pendientesStmt->execute([':id' => $input['id_credito']]);
        $pendientes = $pendientesStmt->fetchColumn();
        
        if ($pendientes > 0) {
            throw new Exception('Ya existe una solicitud de reprogramación pendiente para este crédito');
        }
        
        // Verificar límite de reprogramaciones (máximo 3)
        $totalReprogSql = "SELECT COUNT(*) FROM reprogramaciones WHERE id_credito = :id AND estatus IN ('Aprobada', 'Aplicada')";
        $totalReprogStmt = $pdo->prepare($totalReprogSql);
        $totalReprogStmt->execute([':id' => $input['id_credito']]);
        $totalReprogs = $totalReprogStmt->fetchColumn();
        
        if ($totalReprogs >= 3) {
            throw new Exception('Este crédito ya alcanzó el límite máximo de 3 reprogramaciones');
        }
        
        // Insertar reprogramación
        $sql = "
            INSERT INTO reprogramaciones (
                id_credito, motivo,
                nuevo_plazo_meses, nueva_tasa_interes, nuevo_monto, nuevo_periodo_pago,
                plazo_anterior, tasa_anterior, monto_anterior, periodo_pago_anterior,
                usuario_registro, estatus, fecha_creacion, observaciones
            ) VALUES (
                :id_credito, :motivo,
                :nuevo_plazo, :nueva_tasa, :nuevo_monto, :nuevo_periodo,
                :plazo_anterior, :tasa_anterior, :monto_anterior, :periodo_anterior,
                :usuario, 'Pendiente', NOW(), :observaciones
            )
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_credito' => $input['id_credito'],
            ':motivo' => $input['motivo'],
            ':nuevo_plazo' => $input['nuevo_plazo_meses'] ?? $credito['plazos_meses'],
            ':nueva_tasa' => $input['nueva_tasa_interes'] ?? $credito['tasa_interes'],
            ':nuevo_monto' => $input['nuevo_monto'] ?? $credito['monto_aprobado'],
            ':nuevo_periodo' => $input['nuevo_periodo_pago'] ?? $credito['periodo_pago'],
            ':plazo_anterior' => $credito['plazos_meses'],
            ':tasa_anterior' => $credito['tasa_interes'],
            ':monto_anterior' => $credito['monto_aprobado'],
            ':periodo_anterior' => $credito['periodo_pago'],
            ':usuario' => $user['id'] ?? $user['id_usuario'],
            ':observaciones' => $input['observaciones'] ?? null
        ]);
        
        $idReprogramacion = $pdo->lastInsertId();
        
        // Verificar si el usuario que solicita es gerente
        $nombreRol = strtolower($user['nombre_rol'] ?? '');
        $idRol = $user['rol'] ?? $user['id_rol'] ?? null;
        $esGerente = ($nombreRol === 'gerente') || ($idRol == 2);
        
        if ($esGerente) {
            // Si es gerente, aprobar automáticamente
            $pdo->commit(); // Commit de la inserción primero
            
            // Llamar a la función de aprobación
            aprobarReprogramacion($idReprogramacion, $user, $pdo);
            return; // La función aprobarReprogramacion ya envía la respuesta
        }
        
        // Si no es gerente, registrar en bitácora y notificar al gerente
        $bitacoraSql = "
            INSERT INTO bitacora_operaciones (id_credito, tipo_operacion, descripcion, id_usuario, fecha)
            VALUES (:id_credito, 'Reprogramación Solicitada', :descripcion, :usuario, NOW())
        ";
        $bitacoraStmt = $pdo->prepare($bitacoraSql);
        $bitacoraStmt->execute([
            ':id_credito' => $input['id_credito'],
            ':descripcion' => 'Solicitud de reprogramación: ' . $input['motivo'],
            ':usuario' => $user['id'] ?? $user['id_usuario']
        ]);
        
        // Crear notificación para el gerente
        try {
            $clienteSql = "
                SELECT CONCAT(p.nombres, ' ', p.apellido_paterno) as cliente_nombre
                FROM creditos c
                INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
                INNER JOIN personas p ON cl.dni_persona = p.dni
                WHERE c.id_credito = :id
            ";
            $clienteStmt = $pdo->prepare($clienteSql);
            $clienteStmt->execute([':id' => $input['id_credito']]);
            $clienteNombre = $clienteStmt->fetchColumn();
            
            $notifSql = "
                INSERT INTO notificaciones (tipo, mensaje, fecha_envio, destinatario_rol, usuario_origen, referencia_id, leida)
                VALUES ('reprogramacion_pendiente', :mensaje, NOW(), 2, :usuario_origen, :referencia_id, 0)
            ";
            $notifStmt = $pdo->prepare($notifSql);
            $notifStmt->execute([
                ':mensaje' => "Solicitud de reprogramación para el crédito de {$clienteNombre}. Motivo: {$input['motivo']}",
                ':usuario_origen' => $user['id'] ?? $user['id_usuario'],
                ':referencia_id' => $idReprogramacion
            ]);
        } catch (Exception $e) {
            // Si falla la notificación, continuar (no es crítico)
            error_log("Error al crear notificación: " . $e->getMessage());
        }
        
        $pdo->commit();
        
        jsonResponse(true, 'Reprogramación solicitada exitosamente. El gerente será notificado.', [
            'id_reprogramacion' => $idReprogramacion
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al solicitar reprogramación: ' . $e->getMessage(), null, 500);
    }
}

function handlePut() {
    global $input;
    $user = requireAuth();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de reprogramación requerido', null, 400);
    }
    
    $id = $_GET['id'];
    $action = $input['action'] ?? 'actualizar';
    
    $nombreRol = strtolower($user['nombre_rol'] ?? '');
    $idRol = $user['rol'] ?? $user['id_rol'] ?? null;
    
    switch ($action) {
        case 'aprobar':
            // Solo gerente
            $esGerente = ($nombreRol === 'gerente') || ($idRol == 2);
            if (!$esGerente) {
                jsonResponse(false, 'Solo el gerente puede aprobar reprogramaciones', null, 403);
                return;
            }
            aprobarReprogramacion($id, $user, $pdo);
            break;
        case 'rechazar':
            // Solo gerente
            $esGerente = ($nombreRol === 'gerente') || ($idRol == 2);
            if (!$esGerente) {
                jsonResponse(false, 'Solo el gerente puede rechazar reprogramaciones', null, 403);
                return;
            }
            $motivo = $input['motivo_rechazo'] ?? 'No especificado';
            rechazarReprogramacion($id, $motivo, $user, $pdo);
            break;
        default:
            jsonResponse(false, 'Acción no válida', null, 400);
    }
}

function aprobarReprogramacion($id, $user, $pdo) {
    $pdo->beginTransaction();
    
    try {
        // Obtener reprogramación
        $sql = "SELECT * FROM reprogramaciones WHERE id_reprogramacion = :id AND estatus = 'Pendiente'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $reprog = $stmt->fetch();
        
        if (!$reprog) {
            throw new Exception('Reprogramación no encontrada o ya procesada');
        }
        
        // Actualizar estatus de reprogramación
        $updateReprogSql = "
            UPDATE reprogramaciones 
            SET estatus = 'Aprobada',
                usuario_aprobacion = :usuario,
                fecha_aprobacion = NOW()
            WHERE id_reprogramacion = :id
        ";
        $updateReprogStmt = $pdo->prepare($updateReprogSql);
        $updateReprogStmt->execute([
            ':usuario' => $user['id'] ?? $user['id_usuario'],
            ':id' => $id
        ]);
        
        // Guardar snapshot de cuotas antes de reprogramar (para historial)
        try {
            $snapshotSql = "
                INSERT INTO reprogramaciones_detalle (
                    id_reprogramacion, numero_cuota, fecha_pago, monto_total, capital, interes, seguros
                )
                SELECT 
                    :id_reprogramacion, numero_cuota, fecha_programada, monto_total, monto_capital, monto_interes, 0
                FROM cuotas
                WHERE id_credito = :id_credito AND estado = 'Pendiente'
            ";
            $snapshotStmt = $pdo->prepare($snapshotSql);
            $snapshotStmt->execute([
                ':id_reprogramacion' => $id,
                ':id_credito' => $reprog['id_credito']
            ]);
        } catch (Exception $e) {
            // Si falla el snapshot, continuar (no es crítico)
            error_log("Error al guardar snapshot de cuotas: " . $e->getMessage());
        }
        
        // Calcular saldo pendiente (monto ya pagado)
        $saldoPagadoSql = "
            SELECT COALESCE(SUM(monto_pagado), 0) as total_pagado
            FROM pagos
            WHERE id_credito = :id_credito
        ";
        $saldoPagadoStmt = $pdo->prepare($saldoPagadoSql);
        $saldoPagadoStmt->execute([':id_credito' => $reprog['id_credito']]);
        $totalPagado = $saldoPagadoStmt->fetchColumn();
        
        // Calcular nuevo saldo pendiente
        $nuevoSaldoPendiente = $reprog['nuevo_monto'] - $totalPagado;
        
        if ($nuevoSaldoPendiente < 0) {
            throw new Exception('El nuevo monto no puede ser menor al monto ya pagado');
        }
        
        // Actualizar crédito con nuevos valores
        $updateCreditoSql = "
            UPDATE creditos 
            SET plazos_meses = :plazo,
                tasa_interes = :tasa,
                monto_aprobado = :monto,
                periodo_pago = :periodo
            WHERE id_credito = :id_credito
        ";
        $updateCreditoStmt = $pdo->prepare($updateCreditoSql);
        $updateCreditoStmt->execute([
            ':plazo' => $reprog['nuevo_plazo_meses'],
            ':tasa' => $reprog['nueva_tasa_interes'],
            ':monto' => $reprog['nuevo_monto'],
            ':periodo' => $reprog['nuevo_periodo_pago'],
            ':id_credito' => $reprog['id_credito']
        ]);
        
        // Eliminar SOLO cuotas pendientes (no pagadas ni parciales)
        $deleteCuotasSql = "DELETE FROM cuotas WHERE id_credito = :id_credito AND estado = 'Pendiente'";
        $deleteCuotasStmt = $pdo->prepare($deleteCuotasSql);
        $deleteCuotasStmt->execute([':id_credito' => $reprog['id_credito']]);
        
        // Generar nuevas cuotas basadas en el saldo pendiente
        generatePaymentScheduleReprogramacion(
            $pdo,
            $reprog['id_credito'],
            $nuevoSaldoPendiente,
            $reprog['nueva_tasa_interes'],
            $reprog['nuevo_plazo_meses'],
            $reprog['nuevo_periodo_pago']
        );
        
        // Actualizar estatus final
        $finalStatusSql = "UPDATE reprogramaciones SET estatus = 'Aplicada' WHERE id_reprogramacion = :id";
        $finalStatusStmt = $pdo->prepare($finalStatusSql);
        $finalStatusStmt->execute([':id' => $id]);
        
        // Registrar en bitácora
        $bitacoraSql = "
            INSERT INTO bitacora_operaciones (id_credito, tipo_operacion, descripcion, id_usuario, fecha)
            VALUES (:id_credito, 'Reprogramación Aprobada', 'Reprogramación aprobada y aplicada', :usuario, NOW())
        ";
        $bitacoraStmt = $pdo->prepare($bitacoraSql);
        $bitacoraStmt->execute([
            ':id_credito' => $reprog['id_credito'],
            ':usuario' => $user['id'] ?? $user['id_usuario']
        ]);
        
        // Notificar al usuario que solicitó la reprogramación (solo si no es el mismo que aprueba)
        try {
            $usuarioAprueba = $user['id'] ?? $user['id_usuario'];
            $usuarioSolicita = $reprog['usuario_registro'];
            
            // Solo notificar si el usuario que aprueba es diferente al que solicitó
            if ($usuarioSolicita && $usuarioSolicita != $usuarioAprueba) {
                $notifSql = "
                    INSERT INTO notificaciones (tipo, mensaje, fecha_envio, destinatario_usuario, usuario_origen, referencia_id, leida)
                    VALUES ('reprogramacion_aprobada', :mensaje, NOW(), :destinatario, :usuario_origen, :referencia_id, 0)
                ";
                $notifStmt = $pdo->prepare($notifSql);
                $notifStmt->execute([
                    ':mensaje' => "Tu solicitud de reprogramación ha sido aprobada y aplicada al crédito #{$reprog['id_credito']}",
                    ':destinatario' => $usuarioSolicita,
                    ':usuario_origen' => $usuarioAprueba,
                    ':referencia_id' => $id
                ]);
            }
        } catch (Exception $e) {
            error_log("Error al crear notificación: " . $e->getMessage());
        }
        
        $pdo->commit();
        
        jsonResponse(true, 'Reprogramación aprobada y aplicada exitosamente');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al aprobar reprogramación: ' . $e->getMessage(), null, 500);
    }
}

function rechazarReprogramacion($id, $motivo, $user, $pdo) {
    try {
        $sql = "
            UPDATE reprogramaciones 
            SET estatus = 'Rechazada',
                usuario_aprobacion = :usuario,
                fecha_aprobacion = NOW(),
                motivo_rechazo = :motivo
            WHERE id_reprogramacion = :id AND estatus = 'Pendiente'
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':usuario' => $user['id'] ?? $user['id_usuario'],
            ':motivo' => $motivo,
            ':id' => $id
        ]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(false, 'Reprogramación no encontrada o ya procesada', null, 404);
            return;
        }
        
        // Obtener id_credito para bitácora
        $getIdSql = "SELECT id_credito FROM reprogramaciones WHERE id_reprogramacion = :id";
        $getIdStmt = $pdo->prepare($getIdSql);
        $getIdStmt->execute([':id' => $id]);
        $idCredito = $getIdStmt->fetchColumn();
        
        // Obtener usuario que solicitó
        $getUsuarioSql = "SELECT usuario_registro FROM reprogramaciones WHERE id_reprogramacion = :id";
        $getUsuarioStmt = $pdo->prepare($getUsuarioSql);
        $getUsuarioStmt->execute([':id' => $id]);
        $usuarioSolicita = $getUsuarioStmt->fetchColumn();
        
        // Registrar en bitácora
        $bitacoraSql = "
            INSERT INTO bitacora_operaciones (id_credito, tipo_operacion, descripcion, id_usuario, fecha)
            VALUES (:id_credito, 'Reprogramación Rechazada', :descripcion, :usuario, NOW())
        ";
        $bitacoraStmt = $pdo->prepare($bitacoraSql);
        $bitacoraStmt->execute([
            ':id_credito' => $idCredito,
            ':descripcion' => 'Reprogramación rechazada: ' . $motivo,
            ':usuario' => $user['id'] ?? $user['id_usuario']
        ]);
        
        // Notificar al usuario que solicitó
        try {
            if ($usuarioSolicita) {
                $notifSql = "
                    INSERT INTO notificaciones (tipo, mensaje, fecha_envio, destinatario_usuario, usuario_origen, referencia_id, leida)
                    VALUES ('reprogramacion_rechazada', :mensaje, NOW(), :destinatario, :usuario_origen, :referencia_id, 0)
                ";
                $notifStmt = $pdo->prepare($notifSql);
                $notifStmt->execute([
                    ':mensaje' => "Tu solicitud de reprogramación fue rechazada. Motivo: {$motivo}",
                    ':destinatario' => $usuarioSolicita,
                    ':usuario_origen' => $user['id'] ?? $user['id_usuario'],
                    ':referencia_id' => $id
                ]);
            }
        } catch (Exception $e) {
            error_log("Error al crear notificación: " . $e->getMessage());
        }
        
        jsonResponse(true, 'Reprogramación rechazada');
        
    } catch (Exception $e) {
        jsonResponse(false, 'Error al rechazar reprogramación: ' . $e->getMessage(), null, 500);
    }
}

// Función para generar cronograma de pagos en reprogramación
function generatePaymentScheduleReprogramacion($pdo, $creditoId, $monto, $tasaInteres, $plazos, $periodoPago = 'Mensual') {
    $tasaMensual = $tasaInteres / 100;
    
    if ($monto <= 0 || $tasaInteres < 0 || $plazos <= 0) {
        throw new InvalidArgumentException('Parámetros inválidos para generar cronograma');
    }
    
    // Calcular interés total simple (en meses)
    $interesTotal = $monto * $tasaMensual * $plazos;
    $montoTotal = $monto + $interesTotal;
    
    // Determinar intervalo y número de cuotas según periodo de pago
    $diasPrimerPago = 0;
    $intervaloBase = '';
    $numeroCuotas = $plazos; // Por defecto, para mensual
    
    switch (strtolower($periodoPago)) {
        case 'diario':
            $diasPrimerPago = 1;
            $intervaloBase = 'days';
            $numeroCuotas = ceil($plazos * 26); // ~26 días hábiles por mes
            break;
        case 'semanal':
            $diasPrimerPago = 7;
            $intervaloBase = 'days';
            $numeroCuotas = ceil($plazos * 4); // 4 semanas por mes
            break;
        case 'quincenal':
            $diasPrimerPago = 15;
            $intervaloBase = 'days';
            $numeroCuotas = $plazos * 2; // 2 quincenas por mes
            break;
        case 'mensual':
        default:
            $diasPrimerPago = 1;
            $intervaloBase = 'months';
            $numeroCuotas = $plazos; // 1 cuota por mes
            break;
    }
    
    // Calcular cuota base y redondearla
    $cuotaBase = $montoTotal / $numeroCuotas;
    $cuotaRedondeada = redondearMonedaPeruana($cuotaBase);
    
    // Calcular diferencia y cuotas con ajuste
    $totalConCuotasRedondeadas = $cuotaRedondeada * $numeroCuotas;
    $diferencia = $montoTotal - $totalConCuotasRedondeadas;
    $cuotasConAjuste = 0;
    if (abs($diferencia) >= 0.10) {
        $cuotasConAjuste = (int)abs($diferencia / 0.10);
    }
    
    $interesPorCuota = $interesTotal / $numeroCuotas;
    $capitalPorCuota = $monto / $numeroCuotas;
    
    // Obtener el número de la última cuota pagada
    $ultimaCuotaSql = "SELECT MAX(numero_cuota) as ultima FROM cuotas WHERE id_credito = :id AND estado IN ('Pagada', 'Parcial')";
    $ultimaCuotaStmt = $pdo->prepare($ultimaCuotaSql);
    $ultimaCuotaStmt->execute([':id' => $creditoId]);
    $ultimaCuota = $ultimaCuotaStmt->fetchColumn() ?: 0;
    
    // Para reprogramaciones, usar la fecha actual como base (fecha de la reprogramación)
    $fechaBase = date('Y-m-d');
    
    $cuotas = [];
    $totalAcumulado = 0;
    
    for ($i = 1; $i <= $numeroCuotas; $i++) {
        // Calcular fecha según periodo desde la fecha de reprogramación
        if ($intervaloBase === 'months') {
            $fechaCuota = date('Y-m-d', strtotime("+$i months", strtotime($fechaBase)));
        } else {
            $diasTotal = $diasPrimerPago * $i;
            $fechaCuota = date('Y-m-d', strtotime("+$diasTotal days", strtotime($fechaBase)));
        }
        
        // Última cuota: ajustar para que sume exactamente el total
        if ($i === $numeroCuotas) {
            $cuotaTotal = round($montoTotal - $totalAcumulado, 2);
        } else {
            // Aplicar ajuste en las últimas cuotas si es necesario
            if ($cuotasConAjuste > 0 && $i > ($numeroCuotas - $cuotasConAjuste)) {
                $cuotaTotal = $cuotaRedondeada + ($diferencia > 0 ? 0.10 : -0.10);
            } else {
                $cuotaTotal = $cuotaRedondeada;
            }
            $totalAcumulado += $cuotaTotal;
        }
        
        $cuotas[] = [
            'id_credito' => $creditoId,
            'numero_cuota' => $ultimaCuota + $i,
            'fecha_programada' => $fechaCuota,
            'monto_total' => $cuotaTotal,
            'monto_capital' => round($capitalPorCuota, 2),
            'monto_interes' => round($interesPorCuota, 2),
            'monto_mora' => 0,
            'estado' => 'Pendiente'
        ];
    }
    
    $cuotaSql = "
        INSERT INTO cuotas (id_credito, numero_cuota, fecha_programada, monto_total,
                          monto_capital, monto_interes, monto_mora, estado)
        VALUES (:id_credito, :numero_cuota, :fecha_programada, :monto_total,
                :monto_capital, :monto_interes, :monto_mora, :estado)
    ";
    $cuotaStmt = $pdo->prepare($cuotaSql);
    
    foreach ($cuotas as $cuota) {
        $cuotaStmt->execute($cuota);
    }
    
    return count($cuotas);
}

?>
