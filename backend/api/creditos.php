<?php
// BACKEND/api/creditos.php
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';
require_once '../helpers/notificaciones_helper.php';

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
    case 'DELETE':
        handleDelete();
        break;
    default:
        jsonResponse(false, 'Método no soportado', null, 405);
}

function handleGet() {
    $user = requireAuth();
    $pdo = getPDO();
    
    // Endpoint especial para obtener datos del contrato
    if (isset($_GET['action']) && $_GET['action'] === 'contrato' && isset($_GET['id'])) {
        handleGetContrato($pdo, $_GET['id'], $user);
        return;
    }
    
    // Endpoint especial para obtener datos de la solicitud de crédito
    if (isset($_GET['action']) && $_GET['action'] === 'solicitud' && isset($_GET['id'])) {
        handleGetSolicitud($pdo, $_GET['id'], $user);
        return;
    }
    
    if (isset($_GET['id'])) {
        // Obtener crédito específico con detalles completos
        $id = $_GET['id'];
        $sql = "
            SELECT c.*, 
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
                   cl.ingreso_mensual, cl.gasto_mensual,
                   con.numero1 AS telefono,
                   d.direccion1 AS direccion,
                   COUNT(cu.id_cuota) as total_cuotas,
                   COUNT(CASE WHEN cu.estado = 'Pagada' THEN 1 END) as cuotas_pagadas,
                   COALESCE(SUM(cu.monto_total), 0) as monto_total_cuotas,
                   COALESCE(SUM(CASE WHEN cu.estado = 'Pagada' THEN cu.monto_pagado ELSE 0 END), 0) as monto_pagado_total
            FROM creditos c
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            INNER JOIN personas p ON cl.dni_persona = p.dni
            LEFT JOIN contacto con ON p.dni = con.dni_persona
            LEFT JOIN direccion d ON p.dni = d.dni_persona
            LEFT JOIN cuotas cu ON c.id_credito = cu.id_credito
            WHERE c.id_credito = :id
            GROUP BY c.id_credito, p.dni, cl.id_cliente
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $credito = $stmt->fetch();
        
        if ($credito) {
            // Obtener cuotas del crédito
            $cuotasSql = "
                SELECT * FROM cuotas 
                WHERE id_credito = :id 
                ORDER BY numero_cuota
            ";
            $cuotasStmt = $pdo->prepare($cuotasSql);
            $cuotasStmt->execute([':id' => $id]);
            $credito['cuotas'] = $cuotasStmt->fetchAll();

            // Adjuntar fiadores
            $fiadoresSql = "
                SELECT f.id_fiador, f.id_persona AS dni, p.nombres, p.apellido_paterno, p.apellido_materno, f.tipo_relacion
                FROM fiadores f
                INNER JOIN personas p ON p.dni = f.id_persona
                WHERE f.id_credito = :id
                ORDER BY p.apellido_paterno, p.apellido_materno, p.nombres
            ";
            $fiadoresStmt = $pdo->prepare($fiadoresSql);
            $fiadoresStmt->execute([':id' => $id]);
            $credito['fiadores'] = $fiadoresStmt->fetchAll();

            // Adjuntar avales
            $avalesSql = "
                SELECT a.id_aval, a.id_persona AS dni, p.nombres, p.apellido_paterno, p.apellido_materno, a.tipo_relacion
                FROM avales a
                INNER JOIN personas p ON p.dni = a.id_persona
                WHERE a.id_credito = :id
                ORDER BY p.apellido_paterno, p.apellido_materno, p.nombres
            ";
            $avalesStmt = $pdo->prepare($avalesSql);
            $avalesStmt->execute([':id' => $id]);
            $credito['avales'] = $avalesStmt->fetchAll();

            // Adjuntar seguros del crédito
            $segurosSql = "
                SELECT sc.id_seguro_credito, sc.id_seguro, s.nombre_seguro, sc.costo_asignado, sc.fecha_contratacion
                FROM seguros_credito sc
                INNER JOIN seguros s ON s.id_seguro = sc.id_seguro
                WHERE sc.id_credito = :id
                ORDER BY sc.id_seguro_credito DESC
            ";
            $segurosStmt = $pdo->prepare($segurosSql);
            $segurosStmt->execute([':id' => $id]);
            $credito['seguros'] = $segurosStmt->fetchAll();
            
            jsonResponse(true, 'Crédito encontrado', $credito);
        } else {
            jsonResponse(false, 'Crédito no encontrado', null, 404);
        }
    } else {
        // Obtener todos los créditos con paginación
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $where = "WHERE 1=1";
        $params = [];
        
        // Filtros
        if (isset($_GET['estado'])) {
            $where .= " AND c.estado_credito = :estado";
            $params[':estado'] = $_GET['estado'];
        }
        if (isset($_GET['cliente_id'])) {
            $where .= " AND c.id_cliente = :cliente_id";
            $params[':cliente_id'] = $_GET['cliente_id'];
        }
        
        $sql = "
            SELECT 
                c.id_credito, c.monto_original, c.monto_aprobado, c.tasa_interes,
                c.plazos_meses, c.periodo_pago, c.fecha_otorgamiento, c.fecha_vencimiento,
                c.estado_credito, c.mora,
                p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
                con.numero1 AS telefono,
                d.direccion1 AS direccion,
                -- Agregados de cuotas
                (
                  SELECT COALESCE(SUM(cu.monto_total),0)
                  FROM cuotas cu
                  WHERE cu.id_credito = c.id_credito
                ) AS monto_total_programado,
                (
                  SELECT COALESCE(SUM(cu.monto_pagado),0)
                  FROM cuotas cu
                  WHERE cu.id_credito = c.id_credito
                ) AS total_pagado,
                -- Saldo pendiente calculado correctamente
                (
                    (c.monto_aprobado * (1 + ((c.tasa_interes / 100) * c.plazos_meses))) - 
                    COALESCE((SELECT SUM(p.monto_pagado) FROM pagos p WHERE p.id_credito = c.id_credito), 0)
                ) AS saldo_pendiente,
                (
                  SELECT MIN(cu.fecha_programada)
                  FROM cuotas cu
                  WHERE cu.id_credito = c.id_credito 
                    AND cu.estado IN ('Pendiente','Vencida','Parcial')
                ) AS proxima_cuota_fecha,
                (
                  SELECT cu2.monto_total
                  FROM cuotas cu2
                  WHERE cu2.id_credito = c.id_credito 
                    AND cu2.estado IN ('Pendiente','Vencida','Parcial')
                  ORDER BY cu2.fecha_programada ASC
                  LIMIT 1
                ) AS proxima_cuota_monto
            FROM creditos c
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            INNER JOIN personas p ON cl.dni_persona = p.dni
            LEFT JOIN contacto con ON p.dni = con.dni_persona
            LEFT JOIN direccion d ON p.dni = d.dni_persona
            $where
            ORDER BY c.fecha_otorgamiento DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $creditos = $stmt->fetchAll();
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM creditos c INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente $where";
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetch()['total'];
        
        jsonResponse(true, 'Créditos obtenidos', [
            'creditos' => $creditos,
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
    $required = ['id_cliente', 'monto_original', 'tasa_interes', 'plazos_meses'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            jsonResponse(false, "Campo requerido: $field", null, 400);
        }
    }
    
    // Cargar configuración
    $cfg = require_once __DIR__ . '/../config/app.php';
    $limitesAprobacion = $cfg['creditos']['limites_aprobacion'] ?? [
        1 => 100000,
        2 => 50000,
        3 => 10000,
    ];
    
    // Validar rangos de negocio usando configuración
    $montoMinimo = $cfg['creditos']['monto_minimo'] ?? 500;
    $montoMaximo = $cfg['creditos']['monto_maximo'] ?? 100000;
    $plazoMinimo = $cfg['creditos']['plazo_minimo'] ?? 1;
    $plazoMaximo = $cfg['creditos']['plazo_maximo'] ?? 60;
    $tasaMinima = $cfg['creditos']['tasa_minima'] ?? 5.0;
    $tasaMaxima = $cfg['creditos']['tasa_maxima'] ?? 35.0;
    
    // Validar monto
    if ($input['monto_original'] < $montoMinimo || $input['monto_original'] > $montoMaximo) {
        jsonResponse(false, "El monto debe estar entre {$montoMinimo} y {$montoMaximo}", null, 400);
    }
    
    // Validar plazo
    if ($input['plazos_meses'] < $plazoMinimo || $input['plazos_meses'] > $plazoMaximo) {
        jsonResponse(false, "El plazo debe estar entre {$plazoMinimo} y {$plazoMaximo} meses", null, 400);
    }
    
    // Validar tasa
    if ($input['tasa_interes'] < $tasaMinima || $input['tasa_interes'] > $tasaMaxima) {
        jsonResponse(false, "La tasa debe estar entre {$tasaMinima}% y {$tasaMaxima}%", null, 400);
    }
    
    $montoSolicitado = (float)$input['monto_original'];
    $limiteUsuario = $limitesAprobacion[$user['rol']] ?? 0;

    // Validar extras opcionales antes de proceder
    if (!validateOptionalExtras($pdo, $input)) {
        return; // validateOptionalExtras ya envió la respuesta de error
    }
    
    // Validar cliente
    if (!validateCliente($pdo, (int)$input['id_cliente'])) {
        return; // validateCliente ya envió respuesta
    }

    // Determinar el estado inicial del crédito según el rol del usuario
    // Rol 3 = Trabajador: siempre crea en estado Pendiente
    // Rol 2 = Gerente: puede aprobar directamente si está dentro de su límite
    // Rol 1 = Administrador: puede aprobar directamente cualquier monto
    $estadoInicial = 'Pendiente';
    $requiereAprobacion = true;
    
    if ($user['rol'] == 1) {
        // Administrador: aprueba automáticamente
        $estadoInicial = 'Aprobado';
        $requiereAprobacion = false;
    } elseif ($user['rol'] == 2 && $montoSolicitado <= $limiteUsuario) {
        // Gerente dentro de su límite: aprueba automáticamente
        $estadoInicial = 'Aprobado';
        $requiereAprobacion = false;
    }
    // Trabajadores (rol 3) siempre crean en Pendiente
    
    try {
        $pdo->beginTransaction();
        
        // Calcular montos
        $montoAprobado = $input['monto_aprobado'] ?? $input['monto_original'];
        
        // Insertar crédito usando funciones de MySQL para fechas
        if ($requiereAprobacion) {
            // Si requiere aprobación, no establecer fechas aún
            $creditSql = "
                INSERT INTO creditos (id_cliente, monto_original, monto_aprobado, periodo_pago,
                                    plazos_meses, tasa_interes, fecha_otorgamiento, fecha_vencimiento,
                                    mora, estado_credito, usuario_creacion)
                VALUES (:id_cliente, :monto_original, :monto_aprobado, :periodo_pago,
                        :plazos_meses, :tasa_interes, NULL, NULL, 0, :estado_credito, :usuario_creacion)
            ";
            $creditStmt = $pdo->prepare($creditSql);
            $creditStmt->execute([
                ':id_cliente' => $input['id_cliente'],
                ':monto_original' => $input['monto_original'],
                ':monto_aprobado' => $montoAprobado,
                ':periodo_pago' => $input['periodo_pago'] ?? 'Mensual',
                ':plazos_meses' => $input['plazos_meses'],
                ':tasa_interes' => $input['tasa_interes'],
                ':estado_credito' => $estadoInicial,
                ':usuario_creacion' => $user['id']
            ]);
        } else {
            // Si no requiere aprobación, usar CURDATE() de MySQL
            $creditSql = "
                INSERT INTO creditos (id_cliente, monto_original, monto_aprobado, periodo_pago,
                                    plazos_meses, tasa_interes, fecha_otorgamiento, fecha_vencimiento,
                                    mora, estado_credito, usuario_creacion)
                VALUES (:id_cliente, :monto_original, :monto_aprobado, :periodo_pago,
                        :plazos_meses, :tasa_interes, CURDATE(), 
                        DATE_ADD(CURDATE(), INTERVAL :plazos_meses MONTH), 0, :estado_credito, :usuario_creacion)
            ";
            $creditStmt = $pdo->prepare($creditSql);
            $creditStmt->execute([
                ':id_cliente' => $input['id_cliente'],
                ':monto_original' => $input['monto_original'],
                ':monto_aprobado' => $montoAprobado,
                ':periodo_pago' => $input['periodo_pago'] ?? 'Mensual',
                ':plazos_meses' => $input['plazos_meses'],
                ':tasa_interes' => $input['tasa_interes'],
                ':estado_credito' => $estadoInicial,
                ':usuario_creacion' => $user['id']
            ]);
        }
        
        $creditoId = $pdo->lastInsertId();
        
        // Solo generar cronograma de cuotas si el crédito fue aprobado automáticamente
        if (!$requiereAprobacion) {
            $periodoPago = $input['periodo_pago'] ?? 'Mensual';
            generatePaymentSchedule($pdo, $creditoId, $montoAprobado, $input['tasa_interes'], $input['plazos_meses'], $periodoPago);
        }
        
        // Registrar en historial
        $descripcionEvento = $requiereAprobacion ? 'Crédito creado, pendiente de aprobación' : 'Crédito creado y aprobado';
        $historialSql = "
            INSERT INTO historial_crediticio (id_credito, tipo_evento, descripcion, fecha_evento, usuario_registro)
            VALUES (:id_credito, 'Creación', :descripcion, :fecha, :usuario)
        ";
        $historialStmt = $pdo->prepare($historialSql);
        $historialStmt->execute([
            ':id_credito' => $creditoId,
            ':descripcion' => $descripcionEvento,
            ':fecha' => date('Y-m-d'),
            ':usuario' => $user['id']
        ]);
        
        // Solo aplicar comisión si fue aprobado automáticamente
        if (!$requiereAprobacion) {
            aplicarComisionOtorgamiento($pdo, $creditoId, $montoAprobado);
        }
        
        // Notificar al gerente si el crédito requiere aprobación
        if ($requiereAprobacion) {
            $clienteSql = "
                SELECT CONCAT(p.nombres, ' ', p.apellido_paterno, ' ', p.apellido_materno) as cliente_nombre
                FROM clientes cl
                INNER JOIN personas p ON cl.dni_persona = p.dni
                WHERE cl.id_cliente = :id_cliente
            ";
            $clienteStmt = $pdo->prepare($clienteSql);
            $clienteStmt->execute([':id_cliente' => $input['id_cliente']]);
            $clienteNombre = $clienteStmt->fetchColumn();
            
            notificarGerente(
                $pdo,
                'credito_pendiente',
                "Nuevo crédito pendiente de aprobación: {$clienteNombre} solicita S/ " . number_format($montoAprobado, 2) . " (ID: {$creditoId})",
                $user['id'],
                $creditoId,
                'credito'
            );
        }

        // Insertar opcionales: seguros, fiadores, avales (si vienen en el payload)
        insertOptionalExtras($pdo, $creditoId, $input);
        
        $pdo->commit();
        
        $mensaje = $requiereAprobacion 
            ? 'Crédito creado exitosamente. Pendiente de aprobación por gerente.' 
            : 'Crédito creado y aprobado exitosamente';
        
        jsonResponse(true, $mensaje, ['id_credito' => $creditoId, 'estado' => $estadoInicial], 201);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al crear crédito: ' . $e->getMessage(), null, 500);
    }
}

function generatePaymentSchedule($pdo, $creditoId, $monto, $tasaInteres, $plazos, $periodoPago = 'Mensual') {
    // La tasa de interés ya viene como porcentaje mensual (ej: 10 = 10% mensual)
    $tasaMensual = $tasaInteres / 100;
    
    // Validar parámetros
    if ($monto <= 0 || $tasaInteres < 0 || $plazos <= 0) {
        throw new InvalidArgumentException('Parámetros inválidos para generar cronograma');
    }
    
    // Obtener la fecha de otorgamiento del crédito
    $fechaSql = "SELECT fecha_otorgamiento FROM creditos WHERE id_credito = :id";
    $fechaStmt = $pdo->prepare($fechaSql);
    $fechaStmt->execute([':id' => $creditoId]);
    $fechaOtorgamiento = $fechaStmt->fetchColumn();
    
    if (!$fechaOtorgamiento) {
        // Si no hay fecha de otorgamiento, usar la fecha actual de Perú
        date_default_timezone_set('America/Lima');
        $fechaOtorgamiento = date('Y-m-d');
    }
    
    // Calcular interés total simple: Capital * Tasa * Plazo (en meses)
    $interesTotal = $monto * $tasaMensual * $plazos;
    $montoTotal = $monto + $interesTotal;
    
    // Determinar el intervalo y número de cuotas según el periodo de pago
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
    
    // Calcular cuota base sin redondear
    $cuotaBase = $montoTotal / $numeroCuotas;
    
    // Redondear la cuota base
    $cuotaRedondeada = redondearMonedaPeruana($cuotaBase);
    
    // Calcular cuántas cuotas redondeadas necesitamos
    $totalConCuotasRedondeadas = $cuotaRedondeada * $numeroCuotas;
    $diferencia = $montoTotal - $totalConCuotasRedondeadas;
    
    // Distribuir la diferencia en las últimas cuotas
    // Si la diferencia es positiva, algunas cuotas serán mayores
    // Si es negativa, algunas cuotas serán menores
    $ajustePorCuota = redondearMonedaPeruana($diferencia / $numeroCuotas);
    $cuotasConAjuste = 0;
    if (abs($diferencia) >= 0.10) {
        $cuotasConAjuste = (int)abs($diferencia / 0.10);
    }
    
    // Interés y capital por cuota
    $interesPorCuota = $interesTotal / $numeroCuotas;
    $capitalPorCuota = $monto / $numeroCuotas;
    
    $cuotas = [];
    $totalAcumulado = 0;
    
    // Preparar datos para inserción en lote
    for ($i = 1; $i <= $numeroCuotas; $i++) {
        // Calcular fecha de la cuota según el periodo de pago
        if ($intervaloBase === 'months') {
            $fechaCuota = date('Y-m-d', strtotime("+$i months", strtotime($fechaOtorgamiento)));
        } else {
            $diasTotal = $diasPrimerPago * $i;
            $fechaCuota = date('Y-m-d', strtotime("+$diasTotal days", strtotime($fechaOtorgamiento)));
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
            'numero_cuota' => $i,
            'fecha_programada' => $fechaCuota,
            'monto_total' => $cuotaTotal,
            'monto_capital' => round($capitalPorCuota, 2),
            'monto_interes' => round($interesPorCuota, 2),
            'monto_mora' => 0,
            'estado' => 'Pendiente'
        ];
    }
    
    // Inserción en lote para mejor rendimiento
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

function handlePut() {
    global $input;
    $user = requireAuth();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de crédito requerido', null, 400);
    }
    
    $id = $_GET['id'];
    $action = $input['action'] ?? 'actualizar';
    
    switch ($action) {
        case 'aprobar':
            if ($user['rol'] != 2 && $user['rol'] != 1) { // Solo gerente o admin
                jsonResponse(false, 'Sin permisos para aprobar créditos', null, 403);
            }
            aprobarCredito($id, $user, $pdo);
            break;
        case 'rechazar':
            if ($user['rol'] != 2 && $user['rol'] != 1) { // Solo gerente o admin
                jsonResponse(false, 'Sin permisos para rechazar créditos', null, 403);
            }
            $motivo = $input['motivo_rechazo'] ?? 'No especificado';
            rechazarCredito($id, $motivo, $user, $pdo);
            break;
        case 'reprogramar':
            if ($user['rol'] != 2 && $user['rol'] != 1) { // Solo gerente o admin
                jsonResponse(false, 'Sin permisos para reprogramar créditos', null, 403);
            }
            reprogramarCredito($id, $input, $user, $pdo);
            break;
        case 'actualizar':
        default:
            actualizarCredito($id, $input, $user, $pdo);
            break;
    }
}

function actualizarCredito($id, $input, $user, $pdo) {
    try {
        $updateFields = [];
        $params = [':id' => $id];

        if (isset($input['estado_credito'])) {
            $updateFields[] = "estado_credito = :estado_credito";
            $params[':estado_credito'] = $input['estado_credito'];
        }
        if (isset($input['mora'])) {
            $updateFields[] = "mora = :mora";
            $params[':mora'] = $input['mora'];
        }

        if (empty($updateFields)) {
            jsonResponse(true, 'Nada que actualizar');
        }

        $sql = "UPDATE creditos SET " . implode(', ', $updateFields) . " WHERE id_credito = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Registrar en historial
        $historialSql = "
            INSERT INTO historial_crediticio (id_credito, tipo_evento, descripcion, fecha_evento, usuario_registro)
            VALUES (:id_credito, 'Actualización', 'Crédito actualizado', NOW(), :usuario)
        ";
        $historialStmt = $pdo->prepare($historialSql);
        $historialStmt->execute([
            ':id_credito' => $id,
            ':usuario' => $user['id']
        ]);

        jsonResponse(true, 'Crédito actualizado exitosamente');

    } catch (PDOException $e) {
        jsonResponse(false, 'Error al actualizar crédito: ' . $e->getMessage(), null, 500);
    }
}

function crearCreditoPendienteAprobacion($input, $user, $pdo) {
    try {
        // Validar extras opcionales
        if (!validateOptionalExtras($pdo, $input)) {
            return; // validateOptionalExtras ya envió la respuesta de error
        }
        // Validar cliente
        if (!validateCliente($pdo, (int)$input['id_cliente'])) {
            return; // validateCliente ya envió respuesta
        }
        $pdo->beginTransaction();
        
        // Calcular fechas y montos usando zona horaria de Perú
        date_default_timezone_set('America/Lima');
        $fechaOtorgamiento = date('Y-m-d');
        $fechaVencimiento = date('Y-m-d', strtotime("+{$input['plazos_meses']} months"));
        $montoAprobado = $input['monto_aprobado'] ?? $input['monto_original'];
        
        // Insertar crédito en estado pendiente
        $creditSql = "
            INSERT INTO creditos (id_cliente, monto_original, monto_aprobado, periodo_pago,
                                plazos_meses, tasa_interes, fecha_otorgamiento, fecha_vencimiento,
                                mora, estado_credito, usuario_creacion, requiere_aprobacion)
            VALUES (:id_cliente, :monto_original, :monto_aprobado, :periodo_pago,
                    :plazos_meses, :tasa_interes, :fecha_otorgamiento, :fecha_vencimiento,
                    0, 'Pendiente_Aprobacion', :usuario_creacion, 1)
        ";
        $creditStmt = $pdo->prepare($creditSql);
        $creditStmt->execute([
            ':id_cliente' => $input['id_cliente'],
            ':monto_original' => $input['monto_original'],
            ':monto_aprobado' => $montoAprobado,
            ':periodo_pago' => $input['periodo_pago'] ?? 'Mensual',
            ':plazos_meses' => $input['plazos_meses'],
            ':tasa_interes' => $input['tasa_interes'],
            ':fecha_otorgamiento' => $fechaOtorgamiento,
            ':fecha_vencimiento' => $fechaVencimiento,
            ':usuario_creacion' => $user['id']
        ]);
        
        $creditoId = $pdo->lastInsertId();
        
        // Enviar notificación al gerente
        $notificationSql = "
            INSERT INTO notificaciones (tipo, mensaje, fecha_envio, destinatario_rol, 
                                      usuario_origen, referencia_id, leida)
            VALUES ('credito_pendiente_aprobacion', :mensaje, NOW(), 2, :usuario_origen, :referencia_id, 0)
        ";
        
        // Obtener info del cliente para el mensaje
        $clienteSql = "
            SELECT p.nombres, p.apellido_paterno, p.dni
            FROM clientes c
            INNER JOIN personas p ON c.dni_persona = p.dni
            WHERE c.id_cliente = :id_cliente
        ";
        $clienteStmt = $pdo->prepare($clienteSql);
        $clienteStmt->execute([':id_cliente' => $input['id_cliente']]);
        $cliente = $clienteStmt->fetch();
        
        $mensaje = "Solicitud de aprobación de crédito por S/. " . number_format($input['monto_original'], 2) . 
                  " para el cliente " . $cliente['nombres'] . " " . $cliente['apellido_paterno'] . 
                  " (DNI: " . $cliente['dni'] . "). Solicitado por: " . $user['usuario'];
        
        $notificationStmt = $pdo->prepare($notificationSql);
        $notificationStmt->execute([
            ':mensaje' => $mensaje,
            ':usuario_origen' => $user['id'],
            ':referencia_id' => $creditoId
        ]);
        
        // Registrar en historial
        $historialSql = "
            INSERT INTO historial_crediticio (id_credito, tipo_evento, descripcion, fecha_evento, usuario_registro)
            VALUES (:id_credito, 'Solicitud', 'Crédito creado, pendiente de aprobación', :fecha, :usuario)
        ";
        $historialStmt = $pdo->prepare($historialSql);
        $historialStmt->execute([
            ':id_credito' => $creditoId,
            ':fecha' => $fechaOtorgamiento,
            ':usuario' => $user['id']
        ]);

        // Insertar opcionales: seguros, fiadores, avales (si vienen en el payload)
        insertOptionalExtras($pdo, $creditoId, $input);
        
        $pdo->commit();
        
        jsonResponse(true, 'Crédito creado y enviado para aprobación del gerente', [
            'id_credito' => $creditoId,
            'estado' => 'Pendiente_Aprobacion',
            'requiere_aprobacion' => true
        ], 201);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al crear crédito: ' . $e->getMessage(), null, 500);
    }
}

function aprobarCredito($creditoId, $user, $pdo) {
    try {
        $pdo->beginTransaction();
        
        // Verificar que el crédito existe y está pendiente
        $checkSql = "SELECT * FROM creditos WHERE id_credito = :id AND estado_credito = 'Pendiente'";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':id' => $creditoId]);
        $credito = $checkStmt->fetch();
        
        if (!$credito) {
            jsonResponse(false, 'Crédito no encontrado o no está pendiente de aprobación', null, 404);
            return;
        }
        
        // Calcular fechas de otorgamiento y vencimiento usando CURDATE() de MySQL
        // Actualizar estado a aprobado y establecer fechas
        $updateSql = "
            UPDATE creditos 
            SET estado_credito = 'Aprobado', 
                fecha_otorgamiento = DATE(CONVERT_TZ(NOW(), @@session.time_zone, 'America/Lima')),
                fecha_vencimiento = DATE_ADD(DATE(CONVERT_TZ(NOW(), @@session.time_zone, 'America/Lima')), INTERVAL :plazos_meses MONTH)
            WHERE id_credito = :id
        ";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':id' => $creditoId,
            ':plazos_meses' => $credito['plazos_meses']
        ]);
        
        // Generar cronograma de cuotas ahora que está aprobado
        $periodoPago = $credito['periodo_pago'] ?? 'Mensual';
        generatePaymentSchedule($pdo, $creditoId, $credito['monto_aprobado'], $credito['tasa_interes'], $credito['plazos_meses'], $periodoPago);
        
        // Aplicar comisión por otorgamiento
        aplicarComisionOtorgamiento($pdo, $creditoId, $credito['monto_aprobado']);
        
        // Registrar en historial
        $historialSql = "
            INSERT INTO historial_crediticio (id_credito, tipo_evento, descripcion, fecha_evento, usuario_registro)
            VALUES (:id_credito, 'Aprobacion', 'Crédito aprobado por gerente', NOW(), :usuario)
        ";
        $historialStmt = $pdo->prepare($historialSql);
        $historialStmt->execute([
            ':id_credito' => $creditoId,
            ':usuario' => $user['id']
        ]);
        
        // Marcar notificación del gerente como procesada
        $updateNotificationSql = "
            UPDATE notificaciones 
            SET leida = 1
            WHERE referencia_id = :credito_id 
            AND tipo = 'credito_pendiente'
            AND destinatario_rol = 2
        ";
        $updateNotificationStmt = $pdo->prepare($updateNotificationSql);
        $updateNotificationStmt->execute([':credito_id' => $creditoId]);
        
        // Notificar al trabajador que creó el crédito
        if ($credito['usuario_creacion']) {
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
            
            notificarUsuario(
                $pdo,
                $credito['usuario_creacion'],
                'credito_aprobado',
                "El crédito de {$clienteNombre} (ID: {$creditoId}) ha sido APROBADO por el gerente",
                $user['id'],
                $creditoId,
                'credito'
            );
        }
        
        $pdo->commit();
        
        jsonResponse(true, 'Crédito aprobado exitosamente');
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al aprobar crédito: ' . $e->getMessage(), null, 500);
    }
}

function rechazarCredito($creditoId, $motivo, $user, $pdo) {
    try {
        $pdo->beginTransaction();
        
        // Actualizar estado a desaprobado
        $updateSql = "
            UPDATE creditos 
            SET estado_credito = 'Rechazado'
            WHERE id_credito = :id AND estado_credito IN ('Pendiente', 'Pendiente_Aprobacion')
        ";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':id' => $creditoId
        ]);
        
        if ($updateStmt->rowCount() === 0) {
            $pdo->rollBack();
            jsonResponse(false, 'Crédito no encontrado o no está pendiente', null, 404);
            return;
        }
        
        // Registrar en historial
        $historialSql = "
            INSERT INTO historial_crediticio (id_credito, tipo_evento, descripcion, fecha_evento, usuario_registro)
            VALUES (:id_credito, 'Desaprobacion', :descripcion, NOW(), :usuario)
        ";
        $historialStmt = $pdo->prepare($historialSql);
        $historialStmt->execute([
            ':id_credito' => $creditoId,
            ':descripcion' => 'Crédito desaprobado por gerente. Motivo: ' . $motivo,
            ':usuario' => $user['id']
        ]);
        
        // Marcar notificación del gerente como procesada
        $updateNotificationSql = "
            UPDATE notificaciones 
            SET leida = 1
            WHERE referencia_id = :credito_id 
            AND tipo = 'credito_pendiente'
            AND destinatario_rol = 2
        ";
        $updateNotificationStmt = $pdo->prepare($updateNotificationSql);
        $updateNotificationStmt->execute([':credito_id' => $creditoId]);
        
        // Notificar al trabajador que creó el crédito
        $creditoSql = "SELECT usuario_creacion FROM creditos WHERE id_credito = :id";
        $creditoStmt = $pdo->prepare($creditoSql);
        $creditoStmt->execute([':id' => $creditoId]);
        $usuarioCreacion = $creditoStmt->fetchColumn();
        
        if ($usuarioCreacion) {
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
            
            notificarUsuario(
                $pdo,
                $usuarioCreacion,
                'credito_rechazado',
                "El crédito de {$clienteNombre} (ID: {$creditoId}) ha sido RECHAZADO. Motivo: {$motivo}",
                $user['id'],
                $creditoId,
                'credito'
            );
        }
        
        $pdo->commit();
        
        jsonResponse(true, 'Crédito desaprobado exitosamente');
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al rechazar crédito: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete() {
    $user = requireAuth();
    
    // Solo gerentes pueden eliminar/cancelar créditos
    if ($user['rol'] != 2 && $user['rol'] != 1) {
        jsonResponse(false, 'Sin permisos para eliminar créditos', null, 403);
        return;
    }
    
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de crédito requerido', null, 400);
        return;
    }
    
    $id = $_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        // Verificar si el crédito existe
        $checkCreditoSql = "SELECT id_credito, estado_credito FROM creditos WHERE id_credito = :id";
        $checkCreditoStmt = $pdo->prepare($checkCreditoSql);
        $checkCreditoStmt->execute([':id' => $id]);
        $credito = $checkCreditoStmt->fetch();
        
        if (!$credito) {
            $pdo->rollBack();
            jsonResponse(false, 'Crédito no encontrado', null, 404);
            return;
        }
        
        // Verificar si el crédito tiene pagos
        $checkSql = "SELECT COUNT(*) as count FROM pagos WHERE id_credito = :id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':id' => $id]);
        $payments = $checkStmt->fetch()['count'];
        
        if ($payments > 0) {
            // Si tiene pagos, cambiar estado a "Cancelado" en lugar de eliminar
            $updateSql = "UPDATE creditos SET estado_credito = 'Cancelado' WHERE id_credito = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([':id' => $id]);
            
            // Registrar en historial
            try {
                $historialSql = "
                    INSERT INTO historial_crediticio (id_credito, tipo_evento, descripcion, fecha_evento, usuario_registro)
                    VALUES (:id_credito, 'Cancelacion', 'Crédito cancelado por gerente (tenía pagos registrados)', NOW(), :usuario)
                ";
                $historialStmt = $pdo->prepare($historialSql);
                $historialStmt->execute([
                    ':id_credito' => $id,
                    ':usuario' => $user['id']
                ]);
            } catch (PDOException $e) {
                // Si falla el historial, continuar
            }
            
            $pdo->commit();
            jsonResponse(true, 'Crédito cancelado exitosamente. El crédito tenía pagos registrados, por lo que se cambió su estado a "Cancelado" para mantener el historial financiero.');
            return;
        }
        
        // Eliminar registros relacionados en orden (respetando claves foráneas)
        
        // 1. Eliminar detalles de cuotas si existen
        try {
            $deleteDetallesCuotasSql = "DELETE FROM detalles_pago WHERE id_cuota IN (SELECT id_cuota FROM cuotas WHERE id_credito = :id)";
            $deleteDetallesCuotasStmt = $pdo->prepare($deleteDetallesCuotasSql);
            $deleteDetallesCuotasStmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Tabla puede no existir, continuar
        }
        
        // 2. Eliminar cuotas
        try {
            $deleteCuotasSql = "DELETE FROM cuotas WHERE id_credito = :id";
            $deleteCuotasStmt = $pdo->prepare($deleteCuotasSql);
            $deleteCuotasStmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Tabla puede no existir, continuar
        }
        
        // 3. Eliminar historial crediticio
        try {
            $deleteHistorialSql = "DELETE FROM historial_crediticio WHERE id_credito = :id";
            $deleteHistorialStmt = $pdo->prepare($deleteHistorialSql);
            $deleteHistorialStmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Tabla puede no existir, continuar
        }
        
        // 4. Eliminar seguros del crédito
        try {
            $deleteSegurosSql = "DELETE FROM seguros_credito WHERE id_credito = :id";
            $deleteSegurosStmt = $pdo->prepare($deleteSegurosSql);
            $deleteSegurosStmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Tabla puede no existir, continuar
        }
        
        // 5. Eliminar fiadores
        try {
            $deleteFiadoresSql = "DELETE FROM fiadores WHERE id_credito = :id";
            $deleteFiadoresStmt = $pdo->prepare($deleteFiadoresSql);
            $deleteFiadoresStmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Tabla puede no existir, continuar
        }
        
        // 6. Eliminar avales
        try {
            $deleteAvalesSql = "DELETE FROM avales WHERE id_credito = :id";
            $deleteAvalesStmt = $pdo->prepare($deleteAvalesSql);
            $deleteAvalesStmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Tabla puede no existir, continuar
        }
        
        // 7. Eliminar comisiones
        try {
            $deleteComisionesSql = "DELETE FROM comisiones_credito WHERE id_credito = :id";
            $deleteComisionesStmt = $pdo->prepare($deleteComisionesSql);
            $deleteComisionesStmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Tabla puede no existir, continuar
        }
        
        // 8. Eliminar movimientos de cuenta
        try {
            $deleteMovimientosSql = "DELETE FROM movimientos_cuenta WHERE id_credito = :id";
            $deleteMovimientosStmt = $pdo->prepare($deleteMovimientosSql);
            $deleteMovimientosStmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Tabla puede no existir, continuar
        }
        
        // 9. Eliminar reprogramaciones
        try {
            $deleteReprogDetallesSql = "DELETE FROM reprogramaciones_detalle WHERE id_reprogramacion IN (SELECT id_reprogramacion FROM reprogramaciones WHERE id_credito = :id)";
            $deleteReprogDetallesStmt = $pdo->prepare($deleteReprogDetallesSql);
            $deleteReprogDetallesStmt->execute([':id' => $id]);
            
            $deleteReprogSql = "DELETE FROM reprogramaciones WHERE id_credito = :id";
            $deleteReprogStmt = $pdo->prepare($deleteReprogSql);
            $deleteReprogStmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Tabla puede no existir, continuar
        }
        
        // 10. Finalmente, eliminar el crédito
        $deleteCreditoSql = "DELETE FROM creditos WHERE id_credito = :id";
        $deleteCreditoStmt = $pdo->prepare($deleteCreditoSql);
        $deleteCreditoStmt->execute([':id' => $id]);
        
        $pdo->commit();
        
        jsonResponse(true, 'Crédito eliminado exitosamente');
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al eliminar crédito: ' . $e->getMessage(), null, 500);
    }
}

// Función para aplicar comisión por otorgamiento
function aplicarComisionOtorgamiento($pdo, $creditoId, $montoCredito) {
    try {
        // Calcular comisión (2% del monto del crédito)
        $porcentajeComision = 2.0; // 2%
        $montoComision = $montoCredito * ($porcentajeComision / 100);
        
        // Insertar comisión
        $comisionSql = "
            INSERT INTO comisiones_credito (id_credito, tipo_comision, monto, fecha_aplicacion, descripcion)
            VALUES (:id_credito, 'Otorgamiento', :monto, NOW(), :descripcion)
        ";
        $comisionStmt = $pdo->prepare($comisionSql);
        $comisionStmt->execute([
            ':id_credito' => $creditoId,
            ':monto' => $montoComision,
            ':descripcion' => "Comisión por otorgamiento ({$porcentajeComision}% de " . number_format($montoCredito, 2) . ")"
        ]);
        
        // Registrar movimiento contable
        $movimientoSql = "
            INSERT INTO movimientos_cuenta (id_credito, tipo_movimiento, descripcion, monto, fecha, usuario)
            VALUES (:id_credito, 'Ingreso', :descripcion, :monto, NOW(), 1)
        ";
        $movimientoStmt = $pdo->prepare($movimientoSql);
        $movimientoStmt->execute([
            ':id_credito' => $creditoId,
            ':descripcion' => "Ingreso por comisión de otorgamiento",
            ':monto' => $montoComision
        ]);
        
    } catch (PDOException $e) {
        // No fallar la creación del crédito por problemas de comisión
        error_log('Error aplicando comisión: ' . $e->getMessage());
    }
}

// Función para aplicar comisión por mora
function aplicarComisionMora($pdo, $creditoId, $montoMora) {
    try {
        // Comisión del 10% sobre la mora
        $porcentajeComision = 10.0;
        $montoComision = $montoMora * ($porcentajeComision / 100);
        
        $comisionSql = "
            INSERT INTO comisiones_credito (id_credito, tipo_comision, monto, fecha_aplicacion, descripcion)
            VALUES (:id_credito, 'Mora', :monto, NOW(), :descripcion)
        ";
        $comisionStmt = $pdo->prepare($comisionSql);
        $comisionStmt->execute([
            ':id_credito' => $creditoId,
            ':monto' => $montoComision,
            ':descripcion' => "Comisión por mora ({$porcentajeComision}% de " . number_format($montoMora, 2) . ")"
        ]);
        
        // Registrar movimiento contable
        $movimientoSql = "
            INSERT INTO movimientos_cuenta (id_credito, tipo_movimiento, descripcion, monto, fecha, usuario)
            VALUES (:id_credito, 'Ingreso', :descripcion, :monto, NOW(), 1)
        ";
        $movimientoStmt = $pdo->prepare($movimientoSql);
        $movimientoStmt->execute([
            ':id_credito' => $creditoId,
            ':descripcion' => "Ingreso por comisión de mora",
            ':monto' => $montoComision
        ]);
        
    } catch (PDOException $e) {
        error_log('Error aplicando comisión de mora: ' . $e->getMessage());
    }
}
// Inserta seguros, fiadores y avales opcionales tras crear un crédito
function insertOptionalExtras($pdo, $creditoId, $input) {
    try {
        // Seguros: aceptar array de objetos o ids
        if (!empty($input['seguros']) && is_array($input['seguros'])) {
            $sql = "INSERT INTO seguros_credito (id_credito, id_seguro, costo_asignado, fecha_contratacion)
                    VALUES (:id_credito, :id_seguro, :costo_asignado, :fecha_contratacion)";
            $stmt = $pdo->prepare($sql);
            foreach ($input['seguros'] as $seg) {
                // Permitir formato {id_seguro, costo_asignado?, fecha_contratacion?} o un id directo
                $id_seguro = is_array($seg) ? ($seg['id_seguro'] ?? null) : $seg;
                if (!$id_seguro) { continue; }
                $stmt->execute([
                    ':id_credito' => $creditoId,
                    ':id_seguro' => $id_seguro,
                    ':costo_asignado' => is_array($seg) && isset($seg['costo_asignado']) ? $seg['costo_asignado'] : null,
                    ':fecha_contratacion' => is_array($seg) && isset($seg['fecha_contratacion']) ? $seg['fecha_contratacion'] : date('Y-m-d')
                ]);
            }
        }

        // Fiadores: aceptar array de objetos {id_persona, tipo_relacion?, observaciones?}
        if (!empty($input['fiadores']) && is_array($input['fiadores'])) {
            $sql = "INSERT INTO fiadores (id_credito, id_persona, tipo_relacion, observaciones)
                    VALUES (:id_credito, :id_persona, :tipo_relacion, :observaciones)";
            $stmt = $pdo->prepare($sql);
            foreach ($input['fiadores'] as $f) {
                if (empty($f['id_persona'])) { continue; }
                $stmt->execute([
                    ':id_credito' => $creditoId,
                    ':id_persona' => $f['id_persona'],
                    ':tipo_relacion' => $f['tipo_relacion'] ?? null,
                    ':observaciones' => $f['observaciones'] ?? null
                ]);
            }
        }

        // Avales: aceptar array de objetos {id_persona, tipo_relacion?, observaciones?}
        if (!empty($input['avales']) && is_array($input['avales'])) {
            $sql = "INSERT INTO avales (id_credito, id_persona, tipo_relacion, observaciones)
                    VALUES (:id_credito, :id_persona, :tipo_relacion, :observaciones)";
            $stmt = $pdo->prepare($sql);
            foreach ($input['avales'] as $a) {
                if (empty($a['id_persona'])) { continue; }
                $stmt->execute([
                    ':id_credito' => $creditoId,
                    ':id_persona' => $a['id_persona'],
                    ':tipo_relacion' => $a['tipo_relacion'] ?? null,
                    ':observaciones' => $a['observaciones'] ?? null
                ]);
            }
        }
    } catch (PDOException $e) {
        // No interrumpir el flujo principal si fallan extras opcionales
        error_log('Error insertando extras opcionales: ' . $e->getMessage());
    }
}

// Validar cliente y relación con personas
function validateCliente($pdo, $id_cliente) {
    try {
        $stmt = $pdo->prepare("SELECT id_cliente, dni_persona FROM clientes WHERE id_cliente = :id LIMIT 1");
        $stmt->execute([':id' => $id_cliente]);
        $cli = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cli) {
            jsonResponse(false, 'El cliente especificado no existe', null, 400);
            return false;
        }
        // Validar que su DNI exista en personas
        $dni = $cli['dni_persona'];
        if (empty($dni)) {
            jsonResponse(false, 'El cliente no tiene DNI asociado', null, 400);
            return false;
        }
        $p = $pdo->prepare("SELECT 1 FROM personas WHERE dni = :dni LIMIT 1");
        $p->execute([':dni' => $dni]);
        if (!$p->fetchColumn()) {
            jsonResponse(false, 'El DNI del cliente no corresponde a una persona válida', null, 400);
            return false;
        }
        return true;
    } catch (Exception $e) {
        jsonResponse(false, 'Error validando cliente: ' . $e->getMessage(), null, 500);
        return false;
    }
}

// Validación de extras opcionales (fiadores, avales, seguros)
function validateOptionalExtras($pdo, $input) {
    try {
        // Cargar configuración
        $config = require_once __DIR__ . '/../config/app.php';
        $dniLen = (int)($config['creditos']['dni_length'] ?? 8);
        $maxFiadores = (int)($config['creditos']['max_fiadores'] ?? 3);
        $maxAvales   = (int)($config['creditos']['max_avales'] ?? 3);

        // Normalizar fiadores
        $fiadores = [];
        if (!empty($input['fiadores']) && is_array($input['fiadores'])) {
            foreach ($input['fiadores'] as $f) {
                $dni = is_array($f) ? ($f['id_persona'] ?? null) : $f;
                if ($dni !== null) {
                    $dni = preg_replace('/\D/', '', trim($dni)); // normalizar solo dígitos
                    if ($dni !== '') $fiadores[] = $dni;
                }
            }
        }
        // Normalizar avales
        $avales = [];
        if (!empty($input['avales']) && is_array($input['avales'])) {
            foreach ($input['avales'] as $a) {
                $dni = is_array($a) ? ($a['id_persona'] ?? null) : $a;
                if ($dni !== null) {
                    $dni = preg_replace('/\D/', '', trim($dni));
                    if ($dni !== '') $avales[] = $dni;
                }
            }
        }
        // Normalizar seguros
        $seguros = [];
        if (!empty($input['seguros']) && is_array($input['seguros'])) {
            foreach ($input['seguros'] as $s) {
                $id = is_array($s) ? ($s['id_seguro'] ?? null) : $s;
                if ($id !== null) $seguros[] = (int)$id;
            }
        }

        // Formato de DNI (8 dígitos)
        $pattern = '/^\d{' . $dniLen . '}$/';
        $invalidFiadores = array_values(array_filter($fiadores, function($d) use ($pattern){ return !preg_match($pattern, $d); }));
        $invalidAvales = array_values(array_filter($avales, function($d) use ($pattern){ return !preg_match($pattern, $d); }));
        if (!empty($invalidFiadores)) {
            jsonResponse(false, 'DNIs inválidos en fiadores (8 dígitos): ' . implode(', ', $invalidFiadores), null, 400);
            return false;
        }
        if (!empty($invalidAvales)) {
            jsonResponse(false, 'DNIs inválidos en avales (8 dígitos): ' . implode(', ', $invalidAvales), null, 400);
            return false;
        }

        // Límites de cantidad
        if (count($fiadores) > $maxFiadores) {
            jsonResponse(false, 'Máximo permitido de fiadores es ' . $maxFiadores, null, 400);
            return false;
        }
        if (count($avales) > $maxAvales) {
            jsonResponse(false, 'Máximo permitido de avales es ' . $maxAvales, null, 400);
            return false;
        }

        // Duplicados internos
        if (count($fiadores) !== count(array_unique($fiadores))) {
            jsonResponse(false, 'Hay DNIs duplicados en la lista de fiadores', null, 400);
            return false;
        }
        if (count($avales) !== count(array_unique($avales))) {
            jsonResponse(false, 'Hay DNIs duplicados en la lista de avales', null, 400);
            return false;
        }
        if (count($seguros) !== count(array_unique($seguros))) {
            jsonResponse(false, 'Hay seguros duplicados en la lista', null, 400);
            return false;
        }
        // Duplicados cruzados
        $intersect = array_intersect($fiadores, $avales);
        if (!empty($intersect)) {
            jsonResponse(false, 'Un DNI no puede ser fiador y aval a la vez: ' . implode(', ', $intersect), null, 400);
            return false;
        }

        // Verificar existencia en personas
        $todosDnis = array_values(array_unique(array_merge($fiadores, $avales)));
        if (!empty($todosDnis)) {
            // Construir placeholders
            $ph = implode(',', array_fill(0, count($todosDnis), '?'));
            $stmt = $pdo->prepare("SELECT dni FROM personas WHERE dni IN ($ph)");
            foreach ($todosDnis as $i => $dni) {
                $stmt->bindValue($i+1, $dni, PDO::PARAM_STR);
            }
            $stmt->execute();
            $existing = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            $missing = array_values(array_diff($todosDnis, $existing));
            if (!empty($missing)) {
                jsonResponse(false, 'Algunos DNIs no existen en personas: ' . implode(', ', $missing), null, 400);
                return false;
            }
        }

        // Evitar que el cliente sea fiador o aval
        if (!empty($input['id_cliente'])) {
            $stc = $pdo->prepare("SELECT dni_persona FROM clientes WHERE id_cliente = :id LIMIT 1");
            $stc->execute([':id' => (int)$input['id_cliente']]);
            $cliDni = $stc->fetchColumn();
            if ($cliDni) {
                $cliDni = preg_replace('/\D/', '', $cliDni);
                if (in_array($cliDni, $fiadores)) {
                    jsonResponse(false, 'El cliente no puede ser fiador de su propio crédito', null, 400);
                    return false;
                }
                if (in_array($cliDni, $avales)) {
                    jsonResponse(false, 'El cliente no puede ser aval de su propio crédito', null, 400);
                    return false;
                }
            }
        }

        // Verificar existencia de seguros
        if (!empty($seguros)) {
            $ph = implode(',', array_fill(0, count($seguros), '?'));
            $stmt = $pdo->prepare("SELECT id_seguro FROM seguros WHERE id_seguro IN ($ph)");
            foreach ($seguros as $i => $id) {
                $stmt->bindValue($i+1, $id, PDO::PARAM_INT);
            }
            $stmt->execute();
            $existing = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            $missing = array_values(array_diff($seguros, $existing));
            if (!empty($missing)) {
                jsonResponse(false, 'Algunos seguros no existen: ' . implode(', ', $missing), null, 400);
                return false;
            }
        }
        
        return true;
    } catch (Exception $e) {
        jsonResponse(false, 'Error validando datos: ' . $e->getMessage(), null, 500);
        return false;
    }
}

// Reprogramación de crédito: registra cabecera y detalle sin alterar cuotas actuales
function reprogramarCredito($creditoId, $input, $user, $pdo) {
    try {
        $pdo->beginTransaction();

        $motivo = $input['motivo'] ?? 'Reprogramación solicitada';
        $estatus = $input['estatus'] ?? 'Pendiente';
        $nuevoPlazos = isset($input['nuevo_plazos_meses']) ? (int)$input['nuevo_plazos_meses'] : null;
        $nuevaTasa = isset($input['nueva_tasa_interes']) ? (float)$input['nueva_tasa_interes'] : null;
        $nuevoCronograma = $input['nuevo_cronograma'] ?? null; // array de objetos opcional

        // Insertar cabecera
        $sqlCab = "INSERT INTO reprogramaciones (id_credito, motivo, fecha_creacion, usuario_registro, estatus)
                   VALUES (:id_credito, :motivo, NOW(), :usuario, :estatus)";
        $stmtCab = $pdo->prepare($sqlCab);
        $stmtCab->execute([
            ':id_credito' => $creditoId,
            ':motivo' => $motivo,
            ':usuario' => $user['id'],
            ':estatus' => $estatus
        ]);
        $idReprog = $pdo->lastInsertId();

        // Insertar detalle
        if (is_array($nuevoCronograma) && !empty($nuevoCronograma)) {
            $sqlDet = "INSERT INTO reprogramaciones_detalle (id_reprogramacion, numero_cuota, fecha_pago, monto_total, capital, interes, seguros)
                       VALUES (:id_reprogramacion, :numero_cuota, :fecha_pago, :monto_total, :capital, :interes, :seguros)";
            $stmtDet = $pdo->prepare($sqlDet);
            foreach ($nuevoCronograma as $i => $cuota) {
                $stmtDet->execute([
                    ':id_reprogramacion' => $idReprog,
                    ':numero_cuota' => $cuota['numero_cuota'] ?? ($i + 1),
                    ':fecha_pago' => $cuota['fecha_pago'] ?? date('Y-m-d', strtotime("+" . ($i + 1) . " months")),
                    ':monto_total' => $cuota['monto_total'] ?? null,
                    ':capital' => $cuota['capital'] ?? null,
                    ':interes' => $cuota['interes'] ?? null,
                    ':seguros' => $cuota['seguros'] ?? null,
                ]);
            }
        } else if ($nuevoPlazos !== null || $nuevaTasa !== null) {
            // Generar cronograma simple de referencia (no altera cuotas)
            $cred = $pdo->prepare("SELECT monto_aprobado, tasa_interes, plazos_meses FROM creditos WHERE id_credito = :id");
            $cred->execute([':id' => $creditoId]);
            $c = $cred->fetch(PDO::FETCH_ASSOC);
            if (!$c) { throw new Exception('Crédito no encontrado'); }
            $monto = (float)$c['monto_aprobado'];
            $tasa = $nuevaTasa !== null ? $nuevaTasa : (float)$c['tasa_interes'];
            $plazos = $nuevoPlazos !== null ? $nuevoPlazos : (int)$c['plazos_meses'];
            $tasaMensual = $tasa / 100 / 12;
            if ($plazos <= 0) { throw new Exception('Plazos inválidos para reprogramación'); }
            if ($tasaMensual == 0) {
                $cuotaFija = $monto / $plazos;
            } else {
                $cuotaFija = $monto * ($tasaMensual * pow(1 + $tasaMensual, $plazos)) / (pow(1 + $tasaMensual, $plazos) - 1);
            }
            $saldo = $monto;
            $sqlDet = "INSERT INTO reprogramaciones_detalle (id_reprogramacion, numero_cuota, fecha_pago, monto_total, capital, interes, seguros)
                       VALUES (:id_reprogramacion, :numero_cuota, :fecha_pago, :monto_total, :capital, :interes, :seguros)";
            $stmtDet = $pdo->prepare($sqlDet);
            for ($i = 1; $i <= $plazos; $i++) {
                $interes = $saldo * $tasaMensual;
                $capital = $cuotaFija - $interes;
                $saldo -= $capital;
                if ($i === $plazos) { $capital += $saldo; $cuotaFija = $capital + $interes; }
                $stmtDet->execute([
                    ':id_reprogramacion' => $idReprog,
                    ':numero_cuota' => $i,
                    ':fecha_pago' => date('Y-m-d', strtotime("+{$i} months")),
                    ':monto_total' => round($cuotaFija, 2),
                    ':capital' => round($capital, 2),
                    ':interes' => round($interes, 2),
                    ':seguros' => 0,
                ]);
            }
        }

        // Registrar en historial
        $hist = $pdo->prepare("INSERT INTO historial_crediticio (id_credito, tipo_evento, descripcion, fecha_evento, usuario_registro)
                               VALUES (:id_credito, 'Reprogramacion', :desc, NOW(), :usuario)");
        $hist->execute([
            ':id_credito' => $creditoId,
            ':desc' => 'Reprogramación registrada (no altera cuotas existentes) ID: ' . $idReprog,
            ':usuario' => $user['id']
        ]);

        $pdo->commit();
        jsonResponse(true, 'Reprogramación registrada', ['id_reprogramacion' => $idReprog]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al reprogramar crédito: ' . $e->getMessage(), null, 500);
    }
}

// Función para obtener datos completos del contrato
function handleGetContrato($pdo, $idCredito, $user) {
    try {
        // Obtener datos del crédito y cliente (deudor)
        $sqlCredito = "
            SELECT c.*, 
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.dni, p.estado_civil, p.sexo,
                   cl.ingreso_mensual, cl.gasto_mensual,
                   con.numero1 AS telefono,
                   d.direccion1 AS direccion
            FROM creditos c
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            INNER JOIN personas p ON cl.dni_persona = p.dni
            LEFT JOIN contacto con ON p.dni = con.dni_persona
            LEFT JOIN direccion d ON p.dni = d.dni_persona
            WHERE c.id_credito = :id
        ";
        $stmt = $pdo->prepare($sqlCredito);
        $stmt->execute([':id' => $idCredito]);
        $credito = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$credito) {
            jsonResponse(false, 'Crédito no encontrado', null, 404);
            return;
        }
        
        // Obtener información del acreedor (usuario que creó el crédito)
        $idUsuarioAcreedor = $credito['usuario_creacion'] ?? $user['id'];
        $sqlAcreedor = "
            SELECT u.id_usuario, u.usuario,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.dni, p.estado_civil,
                   con.numero1 AS telefono,
                   d.direccion1 AS direccion
            FROM usuarios u
            INNER JOIN personas p ON u.dni_persona = p.dni
            LEFT JOIN contacto con ON p.dni = con.dni_persona
            LEFT JOIN direccion d ON p.dni = d.dni_persona
            WHERE u.id_usuario = :id_usuario
        ";
        $stmtAcreedor = $pdo->prepare($sqlAcreedor);
        $stmtAcreedor->execute([':id_usuario' => $idUsuarioAcreedor]);
        $acreedor = $stmtAcreedor->fetch(PDO::FETCH_ASSOC);
        
        // Si no se encuentra el acreedor, usar datos del usuario actual
        if (!$acreedor) {
            $sqlAcreedor = "
                SELECT u.id_usuario, u.usuario,
                       p.nombres, p.apellido_paterno, p.apellido_materno, p.dni, p.estado_civil,
                       con.numero1 AS telefono,
                       d.direccion1 AS direccion
                FROM usuarios u
                INNER JOIN personas p ON u.dni_persona = p.dni
                LEFT JOIN contacto con ON p.dni = con.dni_persona
                LEFT JOIN direccion d ON p.dni = d.dni_persona
                WHERE u.id_usuario = :id_usuario
            ";
            $stmtAcreedor = $pdo->prepare($sqlAcreedor);
            $stmtAcreedor->execute([':id_usuario' => $user['id']]);
            $acreedor = $stmtAcreedor->fetch(PDO::FETCH_ASSOC);
        }
        
        // Obtener número total de cuotas
        $sqlCuotas = "SELECT COUNT(*) as total_cuotas FROM cuotas WHERE id_credito = :id";
        $stmtCuotas = $pdo->prepare($sqlCuotas);
        $stmtCuotas->execute([':id' => $idCredito]);
        $totalCuotas = $stmtCuotas->fetchColumn();
        
        // Calcular fecha de firma (fecha de otorgamiento o fecha actual)
        $fechaFirma = $credito['fecha_otorgamiento'] ?? date('Y-m-d');
        
        // Preparar datos del contrato
        $datosContrato = [
            'acreedor' => [
                'nombres' => $acreedor['nombres'] ?? '',
                'apellido_paterno' => $acreedor['apellido_paterno'] ?? '',
                'apellido_materno' => $acreedor['apellido_materno'] ?? '',
                'dni' => $acreedor['dni'] ?? '',
                'estado_civil' => $acreedor['estado_civil'] ?? 'Soltero',
                'direccion' => $acreedor['direccion'] ?? '',
                'distrito' => $acreedor['distrito'] ?? 'Satipo',
                'provincia' => $acreedor['provincia'] ?? 'Satipo',
                'departamento' => $acreedor['departamento'] ?? 'Junín',
                'telefono' => $acreedor['telefono'] ?? '964945187'
            ],
            'deudor' => [
                'nombres' => $credito['nombres'] ?? '',
                'apellido_paterno' => $credito['apellido_paterno'] ?? '',
                'apellido_materno' => $credito['apellido_materno'] ?? '',
                'dni' => $credito['dni'] ?? '',
                'estado_civil' => $credito['estado_civil'] ?? 'Soltero',
                'direccion' => $credito['direccion'] ?? '',
                'distrito' => $credito['distrito'] ?? 'Satipo',
                'provincia' => $credito['provincia'] ?? 'Satipo',
                'departamento' => $credito['departamento'] ?? 'Junín',
                'telefono' => $credito['telefono'] ?? ''
            ],
            'credito' => [
                'id_credito' => $credito['id_credito'],
                'monto' => floatval($credito['monto_aprobado'] ?? $credito['monto_original'] ?? 0),
                'tasa_interes' => floatval($credito['tasa_interes'] ?? 0),
                'total_cuotas' => intval($totalCuotas),
                'fecha_otorgamiento' => $fechaFirma,
                'periodo_pago' => $credito['periodo_pago'] ?? 'Mensual'
            ],
            'responsable_cobranza' => [
                'nombre' => ($acreedor['nombres'] ?? '') . ' ' . ($acreedor['apellido_paterno'] ?? ''),
                'telefono' => $acreedor['telefono'] ?? '964945187'
            ]
        ];
        
        jsonResponse(true, 'Datos del contrato obtenidos', $datosContrato);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Error al obtener datos del contrato: ' . $e->getMessage(), null, 500);
    }
}

// Función para obtener datos completos de la solicitud de crédito
function handleGetSolicitud($pdo, $idCredito, $user) {
    try {
        // Obtener datos del crédito y cliente
        $sqlCredito = "
            SELECT c.*, 
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.dni, p.estado_civil, p.sexo, p.fecha_nacimiento,
                   cl.ingreso_mensual, cl.gasto_mensual, cl.tipo_contrato, cl.antiguedad_laboral,
                   con.numero1 AS telefono,
                   d.direccion1 AS direccion
            FROM creditos c
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            INNER JOIN personas p ON cl.dni_persona = p.dni
            LEFT JOIN contacto con ON p.dni = con.dni_persona
            LEFT JOIN direccion d ON p.dni = d.dni_persona
            WHERE c.id_credito = :id
        ";
        $stmt = $pdo->prepare($sqlCredito);
        $stmt->execute([':id' => $idCredito]);
        $credito = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$credito) {
            jsonResponse(false, 'Crédito no encontrado', null, 404);
            return;
        }
        
        // Calcular edad
        $edad = null;
        if ($credito['fecha_nacimiento']) {
            $fechaNac = new DateTime($credito['fecha_nacimiento']);
            $hoy = new DateTime();
            $edad = $hoy->diff($fechaNac)->y;
        }
        
        // Obtener datos del cónyuge si está casado
        $conyuge = null;
        if (strtoupper($credito['estado_civil']) === 'CASADO' || strtoupper($credito['estado_civil']) === 'CONVIVIENTE') {
            // Buscar cónyuge en la tabla de personas relacionadas (si existe)
            // Por ahora, dejamos vacío ya que no hay una tabla específica para cónyuges
        }
        
        // Obtener codeudor (fiador principal)
        $codeudor = null;
        $sqlCodeudor = "
            SELECT p.nombres, p.apellido_paterno, p.apellido_materno, p.dni
            FROM fiadores f
            INNER JOIN personas p ON f.id_persona = p.dni
            WHERE f.id_credito = :id
            LIMIT 1
        ";
        $stmtCodeudor = $pdo->prepare($sqlCodeudor);
        $stmtCodeudor->execute([':id' => $idCredito]);
        $codeudor = $stmtCodeudor->fetch(PDO::FETCH_ASSOC);
        
        // Calcular cuota mensual aproximada
        $monto = floatval($credito['monto_aprobado'] ?? $credito['monto_original'] ?? 0);
        $tasaInteres = floatval($credito['tasa_interes'] ?? 0);
        $plazosMeses = intval($credito['plazos_meses'] ?? 0);
        $cuotaMensual = 0;
        if ($plazosMeses > 0) {
            $interesTotal = $monto * ($tasaInteres / 100) * $plazosMeses;
            $montoTotal = $monto + $interesTotal;
            $cuotaMensual = $montoTotal / $plazosMeses;
        }
        
        // Determinar tipo de crédito (si no existe, usar un valor por defecto)
        $tipoCredito = $credito['tipo_credito'] ?? 'GASTOS PERSONALES';
        
        // Determinar condición
        $condicion = 'REPRESTAMO'; // Por defecto
        
        // Obtener ocupación del tipo_contrato o usar un valor por defecto
        $ocupacion = '';
        if ($credito['tipo_contrato']) {
            $ocupacion = $credito['tipo_contrato'] === 'Independiente' ? 'TRABAJADOR INDEPENDIENTE' : 'EMPLEADO';
        } else {
            $ocupacion = 'TRABAJADOR INDEPENDIENTE';
        }
        
        // Preparar datos de la solicitud
        $datosSolicitud = [
            'codigo' => 'C' . str_pad($idCredito, 5, '0', STR_PAD_LEFT),
            'credito' => [
                'id_credito' => $credito['id_credito'],
                'monto_solicitado' => floatval($credito['monto_original'] ?? 0),
                'tipo_credito' => $tipoCredito,
                'fecha_solicitud' => $credito['fecha_creacion'] ?? $credito['fecha_otorgamiento'] ?? date('Y-m-d'),
                'plazo' => $plazosMeses,
                'numero_credito' => $credito['id_credito'],
                'interes' => $tasaInteres,
                'cuota' => round($cuotaMensual, 2),
                'condicion' => $condicion
            ],
            'cliente' => [
                'nombres' => $credito['nombres'] ?? '',
                'apellido_paterno' => $credito['apellido_paterno'] ?? '',
                'apellido_materno' => $credito['apellido_materno'] ?? '',
                'dni' => $credito['dni'] ?? '',
                'direccion' => $credito['direccion'] ?? '',
                'ocupacion' => $ocupacion,
                'telefono' => $credito['telefono'] ?? '',
                'estado_civil' => strtoupper($credito['estado_civil'] ?? 'SOLTERO'),
                'tipo_vivienda' => 'PROPIA', // Valor por defecto ya que no existe en la BD
                'edad' => $edad
            ],
            'negocio' => [
                'tipo' => '', // No existe en la BD
                'direccion' => $credito['direccion'] ?? '',
                'empleados' => 0,
                'ruc' => ''
            ],
            'referencial_ingresos' => [
                'inventarios' => 0,
                'total_activos' => floatval($credito['ingreso_mensual'] ?? 0) * 12, // Aproximación basada en ingresos
                'otros_negocios' => '',
                'descripcion' => '',
                'ventas_diarias' => 0,
                'ventas_semanales' => floatval($credito['ingreso_mensual'] ?? 0) / 4, // Aproximación
                'caja' => 0
            ],
            'referencial_financiera' => [
                'total_entidades' => 0,
                'prestamistas' => 0,
                'terceros' => 0
            ],
            'conyuge' => $conyuge,
            'codeudor' => $codeudor
        ];
        
        jsonResponse(true, 'Datos de la solicitud obtenidos', $datosSolicitud);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Error al obtener datos de la solicitud: ' . $e->getMessage(), null, 500);
    }
}

?>
