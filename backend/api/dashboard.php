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
    
    // Cuotas vencidas - simplificado
    $stats['cuotas_vencidas'] = [
        'total' => 0,
        'monto' => 0
    ];
    
    // Próximas cuotas - simplificado
    $stats['proximas_cuotas'] = [
        'total' => 0,
        'monto' => 0
    ];
    
    // Gráficos y datos adicionales - simplificados por ahora
    $stats['creditos_por_mes'] = [];
    $stats['pagos_por_mes'] = [];
    $stats['estados_creditos'] = [];
    $stats['top_clientes'] = [];
    $stats['actividad_reciente'] = [];
    
    jsonResponse(true, 'Estadísticas obtenidas exitosamente', $stats);
    
} catch (PDOException $e) {
    jsonResponse(false, 'Error al obtener estadísticas: ' . $e->getMessage(), null, 500);
}
?>