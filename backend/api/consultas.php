<?php
// BACKEND/api/consultas.php - API para consultas de clientes
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGet();
} else {
    jsonResponse(false, 'Método no soportado', null, 405);
}

function handleGet() {
    try {
        $user = requireTrabajador(); // Solo trabajadores y superiores
        $pdo = getPDO();
        
        $action = $_GET['action'] ?? 'buscar';
        
        if ($action === 'buscar') {
            buscarCliente($pdo);
        } else {
            jsonResponse(false, 'Acción no válida', null, 400);
        }
    } catch (Exception $e) {
        error_log("Error en consultas.php: " . $e->getMessage());
        jsonResponse(false, 'Error de autenticación: ' . $e->getMessage(), null, 401);
    }
}

function buscarCliente($pdo) {
    $termino = $_GET['termino'] ?? '';
    
    if (empty(trim($termino))) {
        jsonResponse(false, 'Debe ingresar un término de búsqueda', null, 400);
        return;
    }
    
    try {
        // Actualizar automáticamente cuotas vencidas antes de consultar
        $sqlActualizar = "
            UPDATE cuotas 
            SET estado = 'Vencida'
            WHERE estado = 'Pendiente'
            AND fecha_programada < CURDATE()
        ";
        $pdo->exec($sqlActualizar);
        
        // Primero verificar si la persona existe pero no es cliente
        $sqlPersona = "SELECT COUNT(*) as count FROM personas WHERE dni LIKE :termino";
        $stmtPersona = $pdo->prepare($sqlPersona);
        $stmtPersona->execute([':termino' => "%$termino%"]);
        $personaExiste = $stmtPersona->fetch()['count'] > 0;
        
        // Buscar cliente por DNI o nombre
        $sql = "
            SELECT 
                cl.id_cliente,
                p.dni,
                p.nombres,
                p.apellido_paterno,
                p.apellido_materno,
                CONCAT(p.nombres, ' ', p.apellido_paterno, ' ', COALESCE(p.apellido_materno, '')) as nombre_completo,
                -- Créditos activos
                (
                    SELECT COUNT(*)
                    FROM creditos c
                    WHERE c.id_cliente = cl.id_cliente
                    AND c.estado_credito IN ('Aprobado', 'Activo')
                ) as creditos_activos,
                -- Próxima cuota (desde hoy en adelante o vencidas)
                (
                    SELECT MIN(cu.fecha_programada)
                    FROM creditos c
                    INNER JOIN cuotas cu ON c.id_credito = cu.id_credito
                    WHERE c.id_cliente = cl.id_cliente
                    AND cu.estado IN ('Pendiente', 'Vencida')
                    AND c.estado_credito IN ('Aprobado', 'Activo')
                ) as proxima_cuota_fecha,
                -- Cuotas vencidas
                (
                    SELECT COUNT(*)
                    FROM creditos c
                    INNER JOIN cuotas cu ON c.id_credito = cu.id_credito
                    WHERE c.id_cliente = cl.id_cliente
                    AND cu.estado = 'Vencida'
                    AND c.estado_credito IN ('Aprobado', 'Activo')
                ) as cuotas_vencidas
            FROM clientes cl
            INNER JOIN personas p ON cl.dni_persona = p.dni
            WHERE p.dni LIKE :termino
            OR CONCAT(p.nombres, ' ', p.apellido_paterno, ' ', COALESCE(p.apellido_materno, '')) LIKE :termino
            LIMIT 10
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':termino' => "%$termino%"]);
        $clientes = $stmt->fetchAll();
        
        // Si no hay clientes, devolver array vacío con mensaje informativo
        if (count($clientes) === 0) {
            $mensaje = $personaExiste 
                ? 'La persona existe pero no está registrada como cliente.' 
                : 'No se encontraron resultados para la búsqueda.';
            jsonResponse(true, $mensaje, []);
            return;
        }
        
        // Para cada cliente, obtener sus créditos activos con información básica
        foreach ($clientes as &$cliente) {
            $creditosSql = "
                SELECT 
                    c.id_credito,
                    c.fecha_otorgamiento,
                    c.fecha_vencimiento,
                    c.estado_credito,
                    c.plazos_meses,
                    c.periodo_pago,
                    -- Calcular total de cuotas según el periodo de pago (igual que en cancelados)
                    CASE 
                        WHEN c.periodo_pago = 'Semanal' THEN c.plazos_meses * 4
                        WHEN c.periodo_pago = 'Quincenal' THEN c.plazos_meses * 2
                        WHEN c.periodo_pago = 'Mensual' THEN c.plazos_meses
                        WHEN c.periodo_pago = 'Diario' THEN c.plazos_meses * 30
                        ELSE c.plazos_meses
                    END as total_cuotas,
                    -- Cuotas pagadas = contar pagos realizados
                    (
                        SELECT COUNT(DISTINCT p.id_pago)
                        FROM pagos p
                        WHERE p.id_credito = c.id_credito
                    ) as cuotas_pagadas,
                    -- Próxima cuota - FECHA (híbrido: cuotas o calculado)
                    COALESCE(
                        (SELECT cu.fecha_programada FROM cuotas cu WHERE cu.id_credito = c.id_credito AND cu.estado IN ('Pendiente', 'Vencida') ORDER BY cu.numero_cuota ASC LIMIT 1),
                        (SELECT DATE_ADD(MAX(p.fecha_pago), INTERVAL CASE WHEN c.periodo_pago = 'Semanal' THEN 7 WHEN c.periodo_pago = 'Quincenal' THEN 15 WHEN c.periodo_pago = 'Mensual' THEN 30 ELSE 7 END DAY) FROM pagos p WHERE p.id_credito = c.id_credito)
                    ) as proxima_cuota_fecha,
                    -- Próxima cuota - NÚMERO (híbrido: cuotas o calculado)
                    COALESCE(
                        (SELECT cu.numero_cuota FROM cuotas cu WHERE cu.id_credito = c.id_credito AND cu.estado IN ('Pendiente', 'Vencida') ORDER BY cu.numero_cuota ASC LIMIT 1),
                        (SELECT COUNT(*) + 1 FROM pagos p WHERE p.id_credito = c.id_credito)
                    ) as proxima_cuota_numero,
                    -- Próxima cuota - MONTO (con redondeo de moneda peruana)
                    FLOOR(
                        (c.monto_aprobado * (1 + ((c.tasa_interes / 100) * c.plazos_meses))) / 
                        CASE 
                            WHEN c.periodo_pago = 'Semanal' THEN c.plazos_meses * 4
                            WHEN c.periodo_pago = 'Quincenal' THEN c.plazos_meses * 2
                            WHEN c.periodo_pago = 'Mensual' THEN c.plazos_meses
                            WHEN c.periodo_pago = 'Diario' THEN CEIL(c.plazos_meses * 26)
                            ELSE c.plazos_meses
                        END
                    ) + 
                    CASE 
                        WHEN ROUND(((c.monto_aprobado * (1 + ((c.tasa_interes / 100) * c.plazos_meses))) / 
                            CASE 
                                WHEN c.periodo_pago = 'Semanal' THEN c.plazos_meses * 4
                                WHEN c.periodo_pago = 'Quincenal' THEN c.plazos_meses * 2
                                WHEN c.periodo_pago = 'Mensual' THEN c.plazos_meses
                                WHEN c.periodo_pago = 'Diario' THEN CEIL(c.plazos_meses * 26)
                                ELSE c.plazos_meses
                            END - FLOOR((c.monto_aprobado * (1 + ((c.tasa_interes / 100) * c.plazos_meses))) / 
                            CASE 
                                WHEN c.periodo_pago = 'Semanal' THEN c.plazos_meses * 4
                                WHEN c.periodo_pago = 'Quincenal' THEN c.plazos_meses * 2
                                WHEN c.periodo_pago = 'Mensual' THEN c.plazos_meses
                                WHEN c.periodo_pago = 'Diario' THEN CEIL(c.plazos_meses * 26)
                                ELSE c.plazos_meses
                            END)) * 100) < 10 THEN 0.00
                        WHEN ROUND(((c.monto_aprobado * (1 + ((c.tasa_interes / 100) * c.plazos_meses))) / 
                            CASE 
                                WHEN c.periodo_pago = 'Semanal' THEN c.plazos_meses * 4
                                WHEN c.periodo_pago = 'Quincenal' THEN c.plazos_meses * 2
                                WHEN c.periodo_pago = 'Mensual' THEN c.plazos_meses
                                WHEN c.periodo_pago = 'Diario' THEN CEIL(c.plazos_meses * 26)
                                ELSE c.plazos_meses
                            END - FLOOR((c.monto_aprobado * (1 + ((c.tasa_interes / 100) * c.plazos_meses))) / 
                            CASE 
                                WHEN c.periodo_pago = 'Semanal' THEN c.plazos_meses * 4
                                WHEN c.periodo_pago = 'Quincenal' THEN c.plazos_meses * 2
                                WHEN c.periodo_pago = 'Mensual' THEN c.plazos_meses
                                WHEN c.periodo_pago = 'Diario' THEN CEIL(c.plazos_meses * 26)
                                ELSE c.plazos_meses
                            END)) * 100) < 35 THEN 0.20
                        WHEN ROUND(((c.monto_aprobado * (1 + ((c.tasa_interes / 100) * c.plazos_meses))) / 
                            CASE 
                                WHEN c.periodo_pago = 'Semanal' THEN c.plazos_meses * 4
                                WHEN c.periodo_pago = 'Quincenal' THEN c.plazos_meses * 2
                                WHEN c.periodo_pago = 'Mensual' THEN c.plazos_meses
                                WHEN c.periodo_pago = 'Diario' THEN CEIL(c.plazos_meses * 26)
                                ELSE c.plazos_meses
                            END - FLOOR((c.monto_aprobado * (1 + ((c.tasa_interes / 100) * c.plazos_meses))) / 
                            CASE 
                                WHEN c.periodo_pago = 'Semanal' THEN c.plazos_meses * 4
                                WHEN c.periodo_pago = 'Quincenal' THEN c.plazos_meses * 2
                                WHEN c.periodo_pago = 'Mensual' THEN c.plazos_meses
                                WHEN c.periodo_pago = 'Diario' THEN CEIL(c.plazos_meses * 26)
                                ELSE c.plazos_meses
                            END)) * 100) < 75 THEN 0.50
                        ELSE 1.00
                    END as proxima_cuota_monto,
                    -- Próxima cuota - ESTADO (híbrido: cuotas o calculado)
                    COALESCE(
                        (SELECT cu.estado FROM cuotas cu WHERE cu.id_credito = c.id_credito AND cu.estado IN ('Pendiente', 'Vencida') ORDER BY cu.numero_cuota ASC LIMIT 1),
                        CASE WHEN (SELECT DATE_ADD(MAX(p.fecha_pago), INTERVAL CASE WHEN c.periodo_pago = 'Semanal' THEN 7 ELSE 7 END DAY) FROM pagos p WHERE p.id_credito = c.id_credito) < CURDATE() THEN 'Vencida' ELSE 'Pendiente' END
                    ) as proxima_cuota_estado,
                    -- Días hasta la próxima cuota (híbrido)
                    COALESCE(
                        (SELECT DATEDIFF(cu.fecha_programada, CURDATE()) FROM cuotas cu WHERE cu.id_credito = c.id_credito AND cu.estado IN ('Pendiente', 'Vencida') ORDER BY cu.numero_cuota ASC LIMIT 1),
                        (SELECT DATEDIFF(DATE_ADD(MAX(p.fecha_pago), INTERVAL CASE WHEN c.periodo_pago = 'Semanal' THEN 7 ELSE 7 END DAY), CURDATE()) FROM pagos p WHERE p.id_credito = c.id_credito)
                    ) as dias_hasta_cuota
                FROM creditos c
                WHERE c.id_cliente = :id_cliente
                AND c.estado_credito IN ('Aprobado', 'Activo')
                ORDER BY c.fecha_otorgamiento DESC
            ";
            
            $creditosStmt = $pdo->prepare($creditosSql);
            $creditosStmt->execute([':id_cliente' => $cliente['id_cliente']]);
            $cliente['creditos'] = $creditosStmt->fetchAll();
            
            // Obtener créditos cancelados/pagados - Calcula cuotas según periodo_pago
            $creditosCanceladosSql = "
                SELECT 
                    c.id_credito,
                    c.fecha_otorgamiento,
                    c.fecha_vencimiento,
                    c.estado_credito,
                    c.monto_aprobado,
                    c.plazos_meses,
                    c.periodo_pago,
                    -- Calcular total de cuotas según el periodo de pago
                    CASE 
                        WHEN c.periodo_pago = 'Semanal' THEN c.plazos_meses * 4
                        WHEN c.periodo_pago = 'Quincenal' THEN c.plazos_meses * 2
                        WHEN c.periodo_pago = 'Mensual' THEN c.plazos_meses
                        WHEN c.periodo_pago = 'Diario' THEN c.plazos_meses * 30
                        ELSE c.plazos_meses
                    END as total_cuotas,
                    -- Cuotas pagadas = total de pagos realizados
                    COUNT(DISTINCT p.id_pago) as cuotas_pagadas,
                    -- Fecha del último pago REAL de la tabla pagos
                    MAX(p.fecha_pago) as fecha_ultimo_pago,
                    -- Monto total pagado
                    SUM(p.monto_pagado) as monto_total_pagado
                FROM creditos c
                INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
                INNER JOIN pagos p ON c.id_credito = p.id_credito
                WHERE c.id_cliente = :id_cliente
                AND c.estado_credito IN ('Pagado', 'Cancelado', 'Finalizado')
                GROUP BY c.id_credito, c.fecha_otorgamiento, c.fecha_vencimiento, c.estado_credito, c.monto_aprobado, c.plazos_meses, c.periodo_pago
                ORDER BY c.fecha_otorgamiento DESC
                LIMIT 10
            ";
            
            $creditosCanceladosStmt = $pdo->prepare($creditosCanceladosSql);
            $creditosCanceladosStmt->execute([':id_cliente' => $cliente['id_cliente']]);
            $cliente['creditos_cancelados'] = $creditosCanceladosStmt->fetchAll();
        }
        
        jsonResponse(true, 'Búsqueda completada', $clientes);
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al buscar cliente: ' . $e->getMessage(), null, 500);
    }
}
?>
