<?php
// BACKEND/api/pagos.php
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
    $user = requireAuth();
    $pdo = getPDO();
    
    if (isset($_GET['id'])) {
        // Obtener pago específico con detalles
        $id = $_GET['id'];
        $sql = "
SELECT p.*, c.id_cliente, c.monto_aprobado,
                   pe.nombres, pe.apellido_paterno, pe.apellido_materno, pe.dni
            FROM pagos p
            INNER JOIN creditos c ON p.id_credito = c.id_credito
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            INNER JOIN personas pe ON cl.dni_persona = pe.dni
            WHERE p.id_pago = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $pago = $stmt->fetch();
        
        if ($pago) {
            // Obtener detalles del pago
            $detallesSql = "
                SELECT dp.*, cu.numero_cuota, cu.fecha_programada
                FROM detalles_pago dp
                LEFT JOIN cuotas cu ON dp.id_cuota = cu.id_cuota
                WHERE dp.id_pago = :id_pago
            ";
            $detallesStmt = $pdo->prepare($detallesSql);
            $detallesStmt->execute([':id_pago' => $id]);
            $pago['detalles'] = $detallesStmt->fetchAll();
            
            jsonResponse(true, 'Pago encontrado', $pago);
        } else {
            jsonResponse(false, 'Pago no encontrado', null, 404);
        }
    } else {
        // Obtener pagos con filtros y paginación
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $where = "WHERE 1=1";
        $params = [];
        
        // Filtros
        if (isset($_GET['credito_id'])) {
            $where .= " AND p.id_credito = :credito_id";
            $params[':credito_id'] = $_GET['credito_id'];
        }
        if (isset($_GET['fecha_desde'])) {
            $where .= " AND p.fecha_pago >= :fecha_desde";
            $params[':fecha_desde'] = $_GET['fecha_desde'];
        }
        if (isset($_GET['fecha_hasta'])) {
            $where .= " AND p.fecha_pago <= :fecha_hasta";
            $params[':fecha_hasta'] = $_GET['fecha_hasta'];
        }
        
        $sql = "
            SELECT p.id_pago, p.fecha_pago, p.monto_pagado, p.interes_pagado,
                   p.capital_pagado, p.mora_pagada, p.referencia_pago, p.descuento,
                   c.id_credito, c.monto_aprobado,
                   pe.nombres, pe.apellido_paterno, pe.apellido_materno, pe.dni
            FROM pagos p
            INNER JOIN creditos c ON p.id_credito = c.id_credito
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            INNER JOIN personas pe ON cl.dni_persona = pe.dni
            $where
            ORDER BY p.fecha_pago DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $pagos = $stmt->fetchAll();
        
        // Contar total
        $countSql = "
            SELECT COUNT(*) as total 
            FROM pagos p
            INNER JOIN creditos c ON p.id_credito = c.id_credito
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            $where
        ";
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetch()['total'];
        
        jsonResponse(true, 'Pagos obtenidos', [
            'pagos' => $pagos,
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
    
    // Validar datos requeridos
    $required = ['id_credito', 'monto_pagado'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            jsonResponse(false, "Campo requerido: $field", null, 400);
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        $creditoId = $input['id_credito'];
        $montoPagado = $input['monto_pagado'];
        $fechaPago = $input['fecha_pago'] ?? date('Y-m-d');
        
        // Validar que el crédito esté aprobado antes de permitir pagos
        $validarCreditoSql = "SELECT estado_credito FROM creditos WHERE id_credito = :id";
        $validarCreditoStmt = $pdo->prepare($validarCreditoSql);
        $validarCreditoStmt->execute([':id' => $creditoId]);
        $creditoEstado = $validarCreditoStmt->fetch();
        
        if (!$creditoEstado) {
            $pdo->rollBack();
            jsonResponse(false, 'El crédito no existe', null, 404);
            return;
        }
        
        if ($creditoEstado['estado_credito'] !== 'Aprobado' && $creditoEstado['estado_credito'] !== 'Vigente') {
            $pdo->rollBack();
            jsonResponse(false, 'No se pueden registrar pagos en un crédito que no está aprobado. Estado actual: ' . $creditoEstado['estado_credito'], null, 400);
            return;
        }
        
        // Obtener cuotas pendientes del crédito (si existen en la tabla)
        $cuotasSql = "
            SELECT * FROM cuotas 
            WHERE id_credito = :credito_id 
            AND estado IN ('Pendiente', 'Vencida') 
            ORDER BY numero_cuota
        ";
        $cuotasStmt = $pdo->prepare($cuotasSql);
        $cuotasStmt->execute([':credito_id' => $creditoId]);
        $cuotas = $cuotasStmt->fetchAll();
        
        // Verificar si el crédito está completamente pagado comparando con el monto total
        $creditoSql = "SELECT monto_aprobado, tasa_interes, plazos_meses FROM creditos WHERE id_credito = :id";
        $creditoStmt = $pdo->prepare($creditoSql);
        $creditoStmt->execute([':id' => $creditoId]);
        $creditoData = $creditoStmt->fetch();
        
        if ($creditoData) {
            $montoTotal = $creditoData['monto_aprobado'] * (1 + ($creditoData['tasa_interes'] / 100) * $creditoData['plazos_meses']);
            
            // Obtener total pagado
            $pagosSql = "SELECT COALESCE(SUM(monto_pagado), 0) as total_pagado FROM pagos WHERE id_credito = :id";
            $pagosStmt = $pdo->prepare($pagosSql);
            $pagosStmt->execute([':id' => $creditoId]);
            $pagosData = $pagosStmt->fetch();
            $totalPagado = $pagosData['total_pagado'];
            
            // Si ya se pagó todo o más, rechazar
            if ($totalPagado >= $montoTotal) {
                jsonResponse(false, 'Este crédito ya está completamente pagado', null, 400);
            }
        }
        
        // Si no hay cuotas en la tabla pero el crédito no está pagado, permitir el pago
        // (el sistema puede funcionar sin la tabla cuotas, usando solo referencias)
        
        // Obtener descuento si existe (para pago completo)
        $descuento = isset($input['descuento']) ? floatval($input['descuento']) : 0;
        
        // Insertar pago principal
        $pagoSql = "
            INSERT INTO pagos (id_credito, fecha_pago, monto_pagado, interes_pagado,
                             capital_pagado, mora_pagada, referencia_pago, id_usuario, descuento)
            VALUES (:id_credito, :fecha_pago, :monto_pagado, 0, 0, 0, :referencia, :usuario, :descuento)
        ";
        $pagoStmt = $pdo->prepare($pagoSql);
        $pagoStmt->execute([
            ':id_credito' => $creditoId,
            ':fecha_pago' => $fechaPago,
            ':monto_pagado' => $montoPagado,
            ':referencia' => $input['referencia_pago'] ?? 'PAGO-' . date('Ymd-His'),
            ':usuario' => $user['id'],
            ':descuento' => $descuento
        ]);
        
        if ($descuento > 0) {
            error_log("Descuento aplicado: $descuento");
        }
        
        $pagoId = $pdo->lastInsertId();
        
        // NUEVA LÓGICA: Distribuir pago secuencialmente en cuotas
        // El monto se aplica completando cada cuota antes de pasar a la siguiente
        $montoRestante = $montoPagado;
        $totalCapital = 0;
        $totalInteres = 0;
        $totalMora = 0;
        
        error_log("=== DISTRIBUYENDO PAGO ===");
        error_log("Monto total a distribuir: $montoPagado");
        error_log("Referencia: " . ($input['referencia_pago'] ?? 'N/A'));
        
        if (!empty($cuotas)) {
            foreach ($cuotas as $cuota) {
                if ($montoRestante <= 0.01) break; // Tolerancia de 1 céntimo
                
                // Calcular cuánto falta pagar de esta cuota
                $montoPendienteCuota = $cuota['monto_total'] - $cuota['monto_pagado'];
                
                if ($montoPendienteCuota <= 0.01) {
                    // Esta cuota ya está pagada, saltar
                    continue;
                }
                
                // Calcular mora si la cuota está vencida
                $mora = 0;
                if ($cuota['fecha_programada'] < $fechaPago) {
                    $diasVencido = (strtotime($fechaPago) - strtotime($cuota['fecha_programada'])) / (60 * 60 * 24);
                    $mora = $cuota['monto_capital'] * 0.05 * ($diasVencido / 30); // 5% mensual de mora
                }
                
                // El monto a aplicar es el MÍNIMO entre lo que queda por pagar y lo que falta de la cuota
                $montoAAplicarCuota = min($montoRestante, $montoPendienteCuota + $mora);
                
                error_log("Cuota #{$cuota['numero_cuota']}: Pendiente=$montoPendienteCuota, Mora=$mora, Aplicando=$montoAAplicarCuota");
                
                // Distribución: primero mora, luego intereses, luego capital
                $moraAplicada = min($montoAAplicarCuota, $mora);
                $montoParaCapitalInteres = $montoAAplicarCuota - $moraAplicada;
                
                // Calcular proporción de interés y capital en lo que falta por pagar
                $proporcionInteres = $cuota['monto_interes'] / $cuota['monto_total'];
                $interesAplicado = $montoParaCapitalInteres * $proporcionInteres;
                $capitalAplicado = $montoParaCapitalInteres - $interesAplicado;
            
                // Insertar detalle de pago
                $detalleSql = "
                    INSERT INTO detalles_pago (id_pago, id_cuota, monto_aplicado, capital_aplicado,
                                             interes_aplicado, mora_aplicada, fecha_aplicacion)
                    VALUES (:id_pago, :id_cuota, :monto_aplicado, :capital_aplicado,
                            :interes_aplicado, :mora_aplicada, NOW())
                ";
                $detalleStmt = $pdo->prepare($detalleSql);
                $detalleStmt->execute([
                    ':id_pago' => $pagoId,
                    ':id_cuota' => $cuota['id_cuota'],
                    ':monto_aplicado' => $montoAAplicarCuota,
                    ':capital_aplicado' => $capitalAplicado,
                    ':interes_aplicado' => $interesAplicado,
                    ':mora_aplicada' => $moraAplicada
                ]);
                
                // Actualizar cuota - NUEVO: calcular correctamente el monto pagado total
                $nuevoMontoPagadoCuota = $cuota['monto_pagado'] + $montoAAplicarCuota;
                $montoTotalCuota = $cuota['monto_total'] + $mora;
                
                // Determinar estado: Pagada si se pagó >= 99.9% (tolerancia por redondeos)
                $porcentajePagado = ($nuevoMontoPagadoCuota / $montoTotalCuota) * 100;
                $estadoCuota = ($porcentajePagado >= 99.9) ? 'Pagada' : 'Parcial';
                
                error_log("  Nuevo monto pagado: $nuevoMontoPagadoCuota / $montoTotalCuota = {$porcentajePagado}% -> Estado: $estadoCuota");
                
                $updateCuotaSql = "
                    UPDATE cuotas SET 
                        monto_pagado = :monto_pagado,
                        estado = :estado,
                        fecha_pago_real = :fecha_pago
                    WHERE id_cuota = :id_cuota
                ";
                $updateCuotaStmt = $pdo->prepare($updateCuotaSql);
                $updateCuotaStmt->execute([
                    ':monto_pagado' => $nuevoMontoPagadoCuota,
                    ':estado' => $estadoCuota,
                    ':fecha_pago' => $fechaPago,
                    ':id_cuota' => $cuota['id_cuota']
                ]);
            
                $totalCapital += $capitalAplicado;
                $totalInteres += $interesAplicado;
                $totalMora += $moraAplicada;
                $montoRestante -= $montoAAplicarCuota;
                
                error_log("  Monto restante después de esta cuota: $montoRestante");
            }
        } else {
            // Si no hay cuotas en la tabla, calcular distribución simple
            // basada en el crédito (interés simple)
            if ($creditoData) {
                $monto = $creditoData['monto_aprobado'];
                $tasa = $creditoData['tasa_interes'] / 100;
                $meses = $creditoData['plazos_meses'];
                $interesTotal = $monto * $tasa * $meses;
                $montoTotal = $monto + $interesTotal;
                
                // Calcular proporción de capital e interés
                $proporcionInteres = $interesTotal / $montoTotal;
                $totalInteres = $montoPagado * $proporcionInteres;
                $totalCapital = $montoPagado - $totalInteres;
            }
        }
        
        error_log("=== RESUMEN DE DISTRIBUCIÓN ===");
        error_log("Capital aplicado: $totalCapital");
        error_log("Interés aplicado: $totalInteres");
        error_log("Mora aplicada: $totalMora");
        error_log("Monto sobrante: $montoRestante");
        
        // Actualizar totales en el pago principal
        $updatePagoSql = "
            UPDATE pagos SET 
                capital_pagado = :capital,
                interes_pagado = :interes,
                mora_pagada = :mora
            WHERE id_pago = :id_pago
        ";
        $updatePagoStmt = $pdo->prepare($updatePagoSql);
        $updatePagoStmt->execute([
            ':capital' => $totalCapital,
            ':interes' => $totalInteres,
            ':mora' => $totalMora,
            ':id_pago' => $pagoId
        ]);
        
        // Verificar si el crédito está completamente pagado
        // Método 1: Verificar por cuotas (si existen)
        try {
            $verificarSql = "
                SELECT COUNT(*) as pendientes 
                FROM cuotas 
                WHERE id_credito = :credito_id 
                AND estado NOT IN ('Pagada')
            ";
            $verificarStmt = $pdo->prepare($verificarSql);
            $verificarStmt->execute([':credito_id' => $creditoId]);
            $pendientes = $verificarStmt->fetch()['pendientes'];
            
            if ($pendientes == 0) {
                $updateCreditoSql = "UPDATE creditos SET estado_credito = 'Pagado' WHERE id_credito = :id";
                $updateCreditoStmt = $pdo->prepare($updateCreditoSql);
                $updateCreditoStmt->execute([':id' => $creditoId]);
                error_log("Crédito #$creditoId marcado como Pagado (por cuotas)");
            }
        } catch (PDOException $e) {
            // Si no existe la tabla cuotas, verificar por saldo
            error_log("Tabla cuotas no existe, verificando por saldo");
        }
        
        // Método 2: Verificar por saldo real (siempre ejecutar como respaldo)
        $saldoSql = "
            SELECT 
                c.monto_aprobado * (1 + (c.tasa_interes / 100) * c.plazos_meses) as monto_total,
                COALESCE(SUM(p.monto_pagado), 0) as total_pagado,
                COALESCE(SUM(p.descuento), 0) as total_descuentos
            FROM creditos c
            LEFT JOIN pagos p ON c.id_credito = p.id_credito
            WHERE c.id_credito = :credito_id
            GROUP BY c.id_credito
        ";
        $saldoStmt = $pdo->prepare($saldoSql);
        $saldoStmt->execute([':credito_id' => $creditoId]);
        $saldoData = $saldoStmt->fetch();
        
        if ($saldoData) {
            $montoTotal = $saldoData['monto_total'];
            $totalPagado = $saldoData['total_pagado'];
            $totalDescuentos = $saldoData['total_descuentos'];
            $saldoPendiente = $montoTotal - $totalPagado - $totalDescuentos;
            
            error_log("Verificación de saldo: Total=$montoTotal, Pagado=$totalPagado, Descuentos=$totalDescuentos, Saldo=$saldoPendiente");
            
            // Si el saldo es 0 o negativo (con tolerancia de 1 sol por redondeos)
            if ($saldoPendiente <= 1) {
                $updateCreditoSql = "UPDATE creditos SET estado_credito = 'Pagado' WHERE id_credito = :id";
                $updateCreditoStmt = $pdo->prepare($updateCreditoSql);
                $updateCreditoStmt->execute([':id' => $creditoId]);
                error_log("Crédito #$creditoId marcado como Pagado (por saldo)");
            }
        }
        
        // Registrar en bitácora de operaciones (comentado temporalmente)
        // La tabla bitacora_operaciones no tiene la columna 'monto'
        /*
        $bitacoraSql = "
            INSERT INTO bitacora_operaciones 
            (tipo_operacion, descripcion, id_usuario, id_credito, fecha_operacion) 
            VALUES 
            (:tipo, :descripcion, :usuario, :credito, NOW())
        ";
        $bitacoraStmt = $pdo->prepare($bitacoraSql);
        $bitacoraStmt->execute([
            ':tipo' => 'PAGO',
            ':descripcion' => 'Registro de pago - Monto: ' . $montoPagado . ' - Referencia: ' . ($input['referencia_pago'] ?? 'PAGO-' . date('Ymd-His')),
            ':usuario' => $user['id'],
            ':credito' => $creditoId
        ]);
        */
        
        // Notificar al gerente sobre el nuevo pago SOLO si quien registra NO es gerente
        // Obtener el rol del usuario que está registrando el pago
        $rolUsuario = $user['id_rol'] ?? $user['rol'] ?? null;
        
        // Solo notificar al gerente si el usuario que registra es trabajador (rol 3) o admin (rol 1)
        // NO notificar si el gerente (rol 2) está registrando su propio pago
        if ($rolUsuario != 2) {
            $clienteSql = "
                SELECT CONCAT(p.nombres, ' ', p.apellido_paterno) as cliente_nombre
                FROM creditos c
                INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
                INNER JOIN personas p ON cl.dni_persona = p.dni
                WHERE c.id_credito = :id
            ";
            $clienteStmt = $pdo->prepare($clienteSql);
            $clienteStmt->execute([':id' => $creditoId]);
            $clienteNombre = $clienteStmt->fetchColumn();
            
            notificarGerente(
                $pdo,
                'pago_registrado',
                "Nuevo pago registrado: {$clienteNombre} pagó S/ " . number_format($montoPagado, 2) . " (Crédito #{$creditoId})",
                $user['id'],
                $pagoId,
                'pago'
            );
        }
        
        $pdo->commit();
        
        jsonResponse(true, 'Pago registrado exitosamente', [
            'id_pago' => $pagoId,
            'monto_aplicado' => $montoPagado - $montoRestante,
            'monto_sobrante' => $montoRestante
        ], 201);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al registrar pago: ' . $e->getMessage(), null, 500);
    }
}

function handlePut() {
    global $input;
    $user = requireAuth();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de pago requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    try {
        $updateFields = [];
        $params = [':id' => $id];
        
        if (isset($input['referencia_pago'])) {
            $updateFields[] = "referencia_pago = :referencia_pago";
            $params[':referencia_pago'] = $input['referencia_pago'];
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE pagos SET " . implode(', ', $updateFields) . " WHERE id_pago = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        jsonResponse(true, 'Pago actualizado exitosamente');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al actualizar pago: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete() {
    $user = requireGerente(); // Solo gerentes pueden eliminar pagos
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de pago requerido', null, 400);
        return;
    }
    
    $id = intval($_GET['id']);
    
    error_log("=== INICIANDO ELIMINACIÓN DE PAGO #$id ===");
    error_log("Usuario: {$user['id_usuario']} - Rol: {$user['id_rol']}");
    
    try {
        // Desactivar temporalmente las verificaciones de claves foráneas
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        $pdo->beginTransaction();
        
        // 1. Verificar que el pago existe
        $pagoSql = "SELECT id_pago, id_credito, monto_pagado FROM pagos WHERE id_pago = :id";
        $pagoStmt = $pdo->prepare($pagoSql);
        $pagoStmt->execute([':id' => $id]);
        $pago = $pagoStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pago) {
            $pdo->rollBack();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            error_log("ERROR: Pago #$id no encontrado en la base de datos");
            jsonResponse(false, 'Pago no encontrado', null, 404);
            return;
        }
        
        error_log("[OK] Pago encontrado: ID={$pago['id_pago']}, Crédito={$pago['id_credito']}, Monto={$pago['monto_pagado']}");
        
        // 2. Intentar eliminar detalles de pago (si la tabla existe)
        try {
            $checkTableSql = "SHOW TABLES LIKE 'detalles_pago'";
            $checkStmt = $pdo->query($checkTableSql);
            $tableExists = $checkStmt->rowCount() > 0;
            
            if ($tableExists) {
                error_log("[OK] Tabla detalles_pago existe, verificando detalles...");
                
                // Verificar si hay detalles
                $countDetallesSql = "SELECT COUNT(*) as total FROM detalles_pago WHERE id_pago = :id_pago";
                $countStmt = $pdo->prepare($countDetallesSql);
                $countStmt->execute([':id_pago' => $id]);
                $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("  Detalles encontrados: {$countResult['total']}");
                
                if ($countResult['total'] > 0) {
                    // Obtener detalles para revertir cuotas
                    $detallesSql = "SELECT id_cuota, monto_aplicado FROM detalles_pago WHERE id_pago = :id_pago";
                    $detallesStmt = $pdo->prepare($detallesSql);
                    $detallesStmt->execute([':id_pago' => $id]);
                    $detalles = $detallesStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Revertir cuotas
                    foreach ($detalles as $detalle) {
                        if ($detalle['id_cuota']) {
                            $revertSql = "
                                UPDATE cuotas 
                                SET monto_pagado = GREATEST(0, monto_pagado - :monto),
                                    estado = CASE 
                                        WHEN (monto_pagado - :monto) <= 0 THEN 'Pendiente'
                                        ELSE 'Parcial'
                                    END
                                WHERE id_cuota = :id_cuota
                            ";
                            $revertStmt = $pdo->prepare($revertSql);
                            $revertStmt->execute([
                                ':monto' => $detalle['monto_aplicado'],
                                ':id_cuota' => $detalle['id_cuota']
                            ]);
                            error_log("  [OK] Cuota #{$detalle['id_cuota']} revertida");
                        }
                    }
                    
                    // Eliminar detalles
                    $deleteDetallesSql = "DELETE FROM detalles_pago WHERE id_pago = :id_pago";
                    $deleteDetallesStmt = $pdo->prepare($deleteDetallesSql);
                    $deleteDetallesStmt->execute([':id_pago' => $id]);
                    error_log("  [OK] {$deleteDetallesStmt->rowCount()} detalles eliminados");
                }
            } else {
                error_log("[WARN] Tabla detalles_pago no existe (esto es normal)");
            }
        } catch (PDOException $e) {
            error_log("[WARN] Error al procesar detalles (continuando): " . $e->getMessage());
        }
        
        // 3. ELIMINAR EL PAGO (lo más importante)
        error_log("Ejecutando DELETE FROM pagos WHERE id_pago = $id");
        
        $deletePagoSql = "DELETE FROM pagos WHERE id_pago = :id";
        $deletePagoStmt = $pdo->prepare($deletePagoSql);
        $success = $deletePagoStmt->execute([':id' => $id]);
        $deletedRows = $deletePagoStmt->rowCount();
        
        error_log("DELETE ejecutado: success=$success, rowCount=$deletedRows");
        
        if (!$success || $deletedRows === 0) {
            $pdo->rollBack();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            error_log("[ERROR] ERROR: No se pudo eliminar el pago (rowCount=$deletedRows)");
            jsonResponse(false, 'No se pudo eliminar el pago de la base de datos', null, 500);
            return;
        }
        
        error_log("[OK] PAGO ELIMINADO DE LA BASE DE DATOS");
        
        // 4. Actualizar estado del crédito si estaba como Pagado
        try {
            $updateCreditoSql = "
                UPDATE creditos 
                SET estado_credito = 'Vigente' 
                WHERE id_credito = :id 
                AND estado_credito = 'Pagado'
            ";
            $updateCreditoStmt = $pdo->prepare($updateCreditoSql);
            $updateCreditoStmt->execute([':id' => $pago['id_credito']]);
            
            if ($updateCreditoStmt->rowCount() > 0) {
                error_log("[OK] Estado del crédito #{$pago['id_credito']} actualizado a Vigente");
            }
        } catch (PDOException $e) {
            error_log("[WARN] Error al actualizar crédito (no crítico): " . $e->getMessage());
        }
        
        // 5. Commit de la transacción
        $pdo->commit();
        
        // Reactivar verificaciones de claves foráneas
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        error_log("=== [OK] PAGO #$id ELIMINADO EXITOSAMENTE ===");
        
        jsonResponse(true, 'Pago eliminado exitosamente', [
            'id_pago' => $id,
            'id_credito' => $pago['id_credito'],
            'deleted' => true
        ]);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        error_log("[ERROR] ERROR CRÍTICO AL ELIMINAR PAGO");
        error_log("Error: " . $e->getMessage());
        error_log("Code: " . $e->getCode());
        error_log("File: " . $e->getFile() . ":" . $e->getLine());
        
        jsonResponse(false, 'Error al eliminar pago: ' . $e->getMessage(), [
            'error_code' => $e->getCode(),
            'error_details' => $e->getMessage()
        ], 500);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        error_log("[ERROR] ERROR GENERAL: " . $e->getMessage());
        jsonResponse(false, 'Error inesperado: ' . $e->getMessage(), null, 500);
    }
}
?>