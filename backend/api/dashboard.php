<?php
// BACKEND/api/dashboard.php
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/sql_adapter.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    jsonResponse(false, 'Solo método GET soportado', null, 405);
}

$user = requireAuth();
$pdo = getPDO();

try {
    // Estadísticas generales
    $stats = [];
    
    // Total de clientes
    $clientesSql = "SELECT COUNT(*) as total FROM clientes";
    $clientesStmt = $pdo->query($clientesSql);
    $clientesData = $clientesStmt->fetch();
    $stats['clientes'] = [
        'total' => (int)$clientesData['total'],
        'activos' => (int)$clientesData['total'] // Todos los clientes en la tabla están activos
    ];
    
    // Total de créditos
    $creditosSql = "
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN estado_credito = 'Vigente' THEN 1 END) as vigentes,
            COUNT(CASE WHEN estado_credito = 'Vencido' THEN 1 END) as vencidos,
            COUNT(CASE WHEN estado_credito = 'Pagado' THEN 1 END) as pagados,
            COALESCE(SUM(monto_aprobado), 0) as monto_total_aprobado,
            COALESCE(SUM(CASE WHEN estado_credito = 'Vigente' THEN monto_aprobado ELSE 0 END), 0) as monto_vigente
        FROM creditos
    ";
    $creditosStmt = $pdo->query($creditosSql);
    $creditosData = $creditosStmt->fetch();
    $stats['creditos'] = [
        'total' => (int)$creditosData['total'],
        'vigentes' => (int)$creditosData['vigentes'],
        'vencidos' => (int)$creditosData['vencidos'],
        'pagados' => (int)$creditosData['pagados'],
        'monto_total_aprobado' => (float)$creditosData['monto_total_aprobado'],
        'monto_vigente' => (float)$creditosData['monto_vigente']
    ];
    
    // Pagos del mes actual
    $pagosSql = "
        SELECT 
            COUNT(*) as total_pagos,
            COALESCE(SUM(monto_pagado), 0) as monto_total_pagado,
            COALESCE(SUM(capital_pagado), 0) as capital_pagado,
            COALESCE(SUM(interes_pagado), 0) as interes_pagado,
            COALESCE(SUM(mora_pagada), 0) as mora_pagada
        FROM pagos 
        WHERE MONTH(fecha_pago) = MONTH(CURDATE())
        AND YEAR(fecha_pago) = YEAR(CURDATE())
    ";
    $pagosStmt = $pdo->query($pagosSql);
    $pagosData = $pagosStmt->fetch();
    $stats['pagos_mes'] = [
        'total_pagos' => (int)$pagosData['total_pagos'],
        'monto_total' => (float)$pagosData['monto_total_pagado'],
        'capital' => (float)$pagosData['capital_pagado'],
        'intereses' => (float)$pagosData['interes_pagado'],
        'mora' => (float)$pagosData['mora_pagada']
    ];
    
    // Cuotas vencidas
    $cuotasVencidasSql = "
        SELECT COUNT(*) as total_vencidas,
               COALESCE(SUM(monto_total), 0) as monto_vencido
        FROM cuotas cu
        INNER JOIN creditos c ON cu.id_credito = c.id_credito
        WHERE cu.fecha_programada < CURDATE()
        AND cu.estado_cuota = 'Pendiente'
        AND c.estado_credito = 'Vigente'
    ";
    $cuotasVencidasStmt = $pdo->query($cuotasVencidasSql);
    $cuotasVencidasData = $cuotasVencidasStmt->fetch();
    $stats['cuotas_vencidas'] = [
        'total' => (int)$cuotasVencidasData['total_vencidas'],
        'monto' => (float)$cuotasVencidasData['monto_vencido']
    ];
    
    // Próximas cuotas (próximos 7 días)
    $proximasCuotasSql = "
        SELECT COUNT(*) as total_proximas,
               COALESCE(SUM(monto_total), 0) as monto_proximo
        FROM cuotas cu
        INNER JOIN creditos c ON cu.id_credito = c.id_credito
        WHERE cu.fecha_programada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND cu.estado_cuota = 'Pendiente'
        AND c.estado_credito = 'Vigente'
    ";
    $proximasCuotasStmt = $pdo->query($proximasCuotasSql);
    $proximasCuotasData = $proximasCuotasStmt->fetch();
    $stats['proximas_cuotas'] = [
        'total' => (int)$proximasCuotasData['total_proximas'],
        'monto' => (float)$proximasCuotasData['monto_proximo']
    ];
    
    // Gráficos - Créditos por mes (últimos 12 meses)
    $creditosPorMesSql = "
        SELECT 
            DATE_FORMAT(fecha_otorgamiento, '%Y-%m-01') as mes,
            COUNT(*) as cantidad,
            COALESCE(SUM(monto_aprobado), 0) as monto
        FROM creditos
        WHERE fecha_otorgamiento >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY mes
        ORDER BY mes
    ";
    $creditosPorMesStmt = $pdo->query($creditosPorMesSql);
    $stats['creditos_por_mes'] = $creditosPorMesStmt->fetchAll();
    
    // Pagos por mes (últimos 12 meses)
    $pagosPorMesSql = "
        SELECT 
            DATE_FORMAT(fecha_pago, '%Y-%m-01') as mes,
            COUNT(*) as cantidad,
            COALESCE(SUM(monto_pagado), 0) as monto
        FROM pagos
        WHERE fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY mes
        ORDER BY mes
    ";
    $pagosPorMesStmt = $pdo->query($pagosPorMesSql);
    $stats['pagos_por_mes'] = $pagosPorMesStmt->fetchAll();
    
    // Estados de créditos para gráfico de pastel
    $estadosCreditosSql = "
        SELECT estado_credito, COUNT(*) as cantidad
        FROM creditos
        GROUP BY estado_credito
        ORDER BY cantidad DESC
    ";
    $estadosCreditosStmt = $pdo->query($estadosCreditosSql);
    $stats['estados_creditos'] = $estadosCreditosStmt->fetchAll();
    
    // Top 5 clientes con mayor deuda
    $topClientesSql = "
        SELECT 
            p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
            COUNT(c.id_credito) as total_creditos,
            COALESCE(SUM(c.monto_aprobado), 0) as monto_total,
            COALESCE(SUM(CASE WHEN c.estado_credito = 'Vigente' THEN c.monto_aprobado ELSE 0 END), 0) as deuda_vigente
        FROM clientes cl
        INNER JOIN personas p ON cl.dni_persona = p.dni
        INNER JOIN creditos c ON cl.id_cliente = c.id_cliente
        WHERE c.estado_credito IN ('Vigente', 'Vencido')
        GROUP BY cl.id_cliente, p.dni, p.nombres, p.apellido_paterno, p.apellido_materno
        ORDER BY deuda_vigente DESC
        LIMIT 5
    ";
    $topClientesStmt = $pdo->query($topClientesSql);
    $stats['top_clientes'] = $topClientesStmt->fetchAll();
    
    // Actividad reciente (últimos 10 eventos)
    $actividadSql = "
        SELECT 
            'credito' as tipo,
            c.id_credito as id,
            'Crédito creado' as descripcion,
            c.fecha_otorgamiento as fecha,
            CONCAT(p.nombres, ' ', p.apellido_paterno) as cliente,
            c.monto_aprobado as monto
        FROM creditos c
        INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
        INNER JOIN personas p ON cl.dni_persona = p.dni
        WHERE c.fecha_otorgamiento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
            'pago' as tipo,
            pa.id_pago as id,
            'Pago registrado' as descripcion,
            pa.fecha_pago as fecha,
            CONCAT(p.nombres, ' ', p.apellido_paterno) as cliente,
            pa.monto_pagado as monto
        FROM pagos pa
        INNER JOIN creditos c ON pa.id_credito = c.id_credito
        INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
        INNER JOIN personas p ON cl.dni_persona = p.dni
        WHERE pa.fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        
        ORDER BY fecha DESC
        LIMIT 10
    ";
    $actividadStmt = $pdo->query($actividadSql);
    $stats['actividad_reciente'] = $actividadStmt->fetchAll();
    
    jsonResponse(true, 'Estadísticas obtenidas exitosamente', $stats);
    
} catch (PDOException $e) {
    jsonResponse(false, 'Error al obtener estadísticas: ' . $e->getMessage(), null, 500);
}
?>