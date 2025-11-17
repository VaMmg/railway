<?php
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

// Inicializar conexión PDO
$pdo = getPDO();

// CORS y preflight
setCorsHeaders();
header('Content-Type: application/json');

// Verificar autenticación
$usuario = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

try {
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
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

/**
 * Verifica si existe una tabla en la BD actual.
 * Evita fallos cuando 'eventos_calendario' no existe.
 */
function tableExists($tableName) {
    global $pdo;
    try {
        $pdo->query("SELECT 1 FROM {$tableName} LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function handleGet() {
    global $pdo, $usuario;
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'eventos_mes':
            $año = $_GET['año'] ?? date('Y');
            $mes = $_GET['mes'] ?? date('m');
            echo json_encode(getEventosDelMes($año, $mes));
            break;
            
        case 'eventos_dia':
            $fecha = $_GET['fecha'] ?? date('Y-m-d');
            echo json_encode(getEventosDelDia($fecha));
            break;
            
        case 'evento':
            $id = $_GET['id'] ?? 0;
            echo json_encode(getEvento($id));
            break;
            
        case 'tipos_evento':
            echo json_encode(getTiposEvento());
            break;
            
        case 'eventos_proximos':
            $limite = $_GET['limite'] ?? 10;
            echo json_encode(getEventosProximos($limite));
            break;
            
        default:
            // Por defecto, obtener eventos del mes actual
            echo json_encode(getEventosDelMes(date('Y'), date('m')));
            break;
    }
}

function handlePost() {
    global $pdo, $usuario;
    
    $input = json_decode(file_get_contents('php://input'), true);
    echo json_encode(crearEvento($input));
}

function handlePut() {
    global $pdo, $usuario;
    
    $id = $_GET['id'] ?? 0;
    $input = json_decode(file_get_contents('php://input'), true);
    echo json_encode(actualizarEvento($id, $input));
}

function handleDelete() {
    global $pdo, $usuario;
    
    $id = $_GET['id'] ?? 0;
    echo json_encode(eliminarEvento($id));
}

function getEventosDelMes($año, $mes) {
    global $pdo, $usuario;

    // Calcular rango de fechas [inicioMes, finMes)
    $inicioMes = date('Y-m-01 00:00:00', strtotime("$año-$mes-01"));
    $finMes = date('Y-m-01 00:00:00', strtotime("$año-$mes-01 +1 month"));
    $inicioMesFecha = substr($inicioMes, 0, 10);
    $finMesFecha = substr($finMes, 0, 10);
    
    try {
        $eventos_personalizados = [];

        // 1) Eventos personalizados (si existe la tabla eventos_calendario)
        if (tableExists('eventos_calendario')) {
            $eventos_query = "
                SELECT 
                    e.id_evento,
                    e.titulo,
                    e.descripcion,
                    e.fecha_inicio,
                    e.fecha_fin,
                    e.todo_el_dia,
                    e.tipo_evento,
                    e.estado,
                    e.id_credito,
                    e.id_cliente,
                    e.prioridad,
                    e.color,
                    c.monto_aprobado,
                    cl.id_cliente as cliente_id,
                    p.nombres,
                    p.apellido_paterno,
                    p.apellido_materno
                FROM eventos_calendario e
                LEFT JOIN creditos c ON e.id_credito = c.id_credito
                LEFT JOIN clientes cl ON e.id_cliente = cl.id_cliente
                LEFT JOIN personas p ON cl.dni_persona = p.dni
                WHERE e.fecha_inicio >= :inicioMes AND e.fecha_inicio < :finMes
                ORDER BY e.fecha_inicio ASC
            ";
            $stmt = $pdo->prepare($eventos_query);
            $stmt->bindParam(':inicioMes', $inicioMes);
            $stmt->bindParam(':finMes', $finMes);
            $stmt->execute();
            $eventos_personalizados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 1.b) Eventos en fechas_calendario (mapea a estructura del calendario)
        $stmt = $pdo->prepare("SELECT fc.id_fecha, fc.fecha, fc.es_feriado, fc.descripcion, cp.nombre_calendario
                               FROM fechas_calendario fc
                               LEFT JOIN calendarios_pago cp ON fc.id_calendario_pago = cp.id_calendario_pago
                               WHERE fc.fecha >= :inicioFecha AND fc.fecha < :finFecha
                               ORDER BY fc.fecha ASC");
        $stmt->bindParam(':inicioFecha', $inicioMesFecha);
        $stmt->bindParam(':finFecha', $finMesFecha);
        $stmt->execute();
        $fechas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $eventos_fechas = [];
        foreach ($fechas as $f) {
            $eventos_fechas[] = [
                'id_evento' => 'fc_' . $f['id_fecha'],
                'titulo' => !empty($f['descripcion']) ? $f['descripcion'] : ($f['es_feriado'] ? 'Feriado' : ($f['nombre_calendario'] ?? 'Calendario')),
                'descripcion' => $f['descripcion'],
                'fecha_inicio' => $f['fecha'],
                'fecha_fin' => $f['fecha'],
                'todo_el_dia' => true,
                'tipo_evento' => $f['es_feriado'] ? 'feriado' : 'calendario_pago',
                'estado' => 'programada',
                'id_credito' => null,
                'id_cliente' => null,
                'prioridad' => 'media',
                'color' => $f['es_feriado'] ? '#16a34a' : '#0ea5e9'
            ];
        }
        
        // 2) Obtener pagos vencidos y próximos a vencer
        $pagos_query = "
            SELECT 
                c.id_credito,
                c.fecha_vencimiento as fecha,
                c.monto_aprobado,
                c.estado_credito,
                cl.id_cliente,
                p.nombres,
                p.apellido_paterno,
                p.apellido_materno,
                co.numero_principal as telefono,
                COALESCE(SUM(pg.monto_pagado), 0) as total_pagado,
                (c.monto_aprobado - COALESCE(SUM(pg.monto_pagado), 0)) as saldo_pendiente
            FROM creditos c
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            INNER JOIN personas p ON cl.dni_persona = p.dni
            LEFT JOIN contacto co ON p.id_contacto = co.id_contacto
            LEFT JOIN pagos pg ON c.id_credito = pg.id_credito
            WHERE c.estado_credito IN ('vigente', 'vencido')
                AND c.fecha_vencimiento >= :inicioMes 
                AND c.fecha_vencimiento < :finMes
            GROUP BY c.id_credito, c.fecha_vencimiento, c.monto_aprobado, c.estado_credito,
                     cl.id_cliente, p.nombres, p.apellido_paterno, p.apellido_materno, co.numero_principal
            HAVING saldo_pendiente > 0
            ORDER BY c.fecha_vencimiento ASC
        ";
        
        $stmt = $pdo->prepare($pagos_query);
        $stmt->bindParam(':inicioMes', $inicioMes);
        $stmt->bindParam(':finMes', $finMes);
        $stmt->execute();
        $creditos_vencimiento = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convertir créditos a eventos
        $eventos_creditos = [];
        foreach ($creditos_vencimiento as $credito) {
            $es_vencido = strtotime($credito['fecha']) < time();
            
            $eventos_creditos[] = [
                'id_evento' => 'credito_' . $credito['id_credito'],
                'titulo' => $es_vencido ? 'Crédito VENCIDO' : 'Vencimiento de Crédito',
                'descripcion' => $credito['nombres'] . ' ' . $credito['apellido_paterno'] . ' - S/' . number_format($credito['saldo_pendiente'], 2),
                'fecha_inicio' => $credito['fecha'],
                'fecha_fin' => $credito['fecha'],
                'todo_el_dia' => true,
                'tipo_evento' => $es_vencido ? 'vencido' : 'vencimiento',
                'estado' => $es_vencido ? 'vencido' : 'pendiente',
                'id_credito' => $credito['id_credito'],
                'id_cliente' => $credito['id_cliente'],
                'prioridad' => $es_vencido ? 'alta' : 'media',
                'color' => $es_vencido ? '#dc2626' : '#f59e0b',
                'monto_aprobado' => $credito['monto_aprobado'],
                'saldo_pendiente' => $credito['saldo_pendiente'],
                'telefono' => $credito['telefono'],
                'cliente_nombre' => $credito['nombres'] . ' ' . $credito['apellido_paterno']
            ];
        }
        
        // 3) Obtener próximas cuotas programadas
        $cuotas_query = "
            SELECT 
                cu.id_cuota,
                cu.id_credito,
                cu.fecha_programada as fecha,
                cu.monto_total,
                cu.estado as estado_cuota,
                c.monto_aprobado,
                cl.id_cliente,
                p.nombres,
                p.apellido_paterno,
                p.apellido_materno
            FROM cuotas cu
            INNER JOIN creditos c ON cu.id_credito = c.id_credito
            INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
            INNER JOIN personas p ON cl.dni_persona = p.dni
            WHERE cu.estado = 'pendiente'
                AND cu.fecha_programada >= :inicioMes 
                AND cu.fecha_programada < :finMes
                AND c.estado_credito = 'vigente'
            ORDER BY cu.fecha_programada ASC
        ";
        
        $stmt = $pdo->prepare($cuotas_query);
        $stmt->bindParam(':inicioMes', $inicioMes);
        $stmt->bindParam(':finMes', $finMes);
        $stmt->execute();
        $cuotas_programadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convertir cuotas a eventos
        $eventos_cuotas = [];
        foreach ($cuotas_programadas as $cuota) {
            $eventos_cuotas[] = [
                'id_evento' => 'cuota_' . $cuota['id_cuota'],
                'titulo' => 'Cuota Programada',
                'descripcion' => $cuota['nombres'] . ' ' . $cuota['apellido_paterno'] . ' - S/' . number_format($cuota['monto_total'], 2),
                'fecha_inicio' => $cuota['fecha'],
                'fecha_fin' => $cuota['fecha'],
                'todo_el_dia' => true,
                'tipo_evento' => 'cuota',
                'estado' => 'programada',
                'id_credito' => $cuota['id_credito'],
                'id_cliente' => $cuota['id_cliente'],
                'prioridad' => 'media',
                'color' => '#3b82f6',
                'monto' => $cuota['monto_total'],
                'cliente_nombre' => $cuota['nombres'] . ' ' . $cuota['apellido_paterno']
            ];
        }
        
        // Combinar todos los eventos
        $todos_eventos = array_merge($eventos_personalizados, $eventos_fechas, $eventos_creditos, $eventos_cuotas);
        
        return [
            'success' => true,
            'data' => $todos_eventos,
            'estadisticas' => [
                'total_eventos' => count($todos_eventos),
                'eventos_personalizados' => count($eventos_personalizados),
                'eventos_fechas' => count($eventos_fechas),
                'vencimientos' => count($eventos_creditos),
                'cuotas_programadas' => count($eventos_cuotas)
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al obtener eventos: ' . $e->getMessage()
        ];
    }
}

function getEventosDelDia($fecha) {
    global $pdo, $usuario;

    $inicio = $fecha . ' 00:00:00';
    $fin = date('Y-m-d 00:00:00', strtotime($fecha . ' +1 day'));
    
    try {
        $eventos = [];

        if (tableExists('eventos_calendario')) {
            $query = "
                SELECT 
                    e.id_evento,
                    e.titulo,
                    e.descripcion,
                    e.fecha_inicio,
                    e.fecha_fin,
                    e.todo_el_dia,
                    e.tipo_evento,
                    e.estado,
                    e.id_credito,
                    e.id_cliente,
                    e.prioridad,
                    e.color,
                    c.monto_aprobado,
                    cl.id_cliente as cliente_id,
                    p.nombres,
                    p.apellido_paterno,
                    p.apellido_materno
                FROM eventos_calendario e
                LEFT JOIN creditos c ON e.id_credito = c.id_credito
                LEFT JOIN clientes cl ON e.id_cliente = cl.id_cliente
                LEFT JOIN personas p ON cl.dni_persona = p.dni
                WHERE e.fecha_inicio >= :inicio AND e.fecha_inicio < :fin
                ORDER BY e.fecha_inicio ASC
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':inicio', $inicio);
            $stmt->bindParam(':fin', $fin);
            $stmt->execute();
            $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // También incluir fechas_calendario de ese día
        $stmt = $pdo->prepare("SELECT id_fecha, fecha, es_feriado, descripcion FROM fechas_calendario WHERE fecha = :fecha");
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();
        $fechas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fechas as $f) {
            $eventos[] = [
                'id_evento' => 'fc_' . $f['id_fecha'],
                'titulo' => !empty($f['descripcion']) ? $f['descripcion'] : ($f['es_feriado'] ? 'Feriado' : 'Calendario'),
                'descripcion' => $f['descripcion'],
                'fecha_inicio' => $f['fecha'],
                'fecha_fin' => $f['fecha'],
                'todo_el_dia' => true,
                'tipo_evento' => $f['es_feriado'] ? 'feriado' : 'calendario_pago',
                'estado' => 'programada',
                'prioridad' => 'media',
                'color' => $f['es_feriado'] ? '#16a34a' : '#0ea5e9'
            ];
        }
        
        return [
            'success' => true,
            'data' => $eventos,
            'fecha' => $fecha,
            'total' => count($eventos)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al obtener eventos del día: ' . $e->getMessage()
        ];
    }
}

function crearEvento($data) {
    global $pdo, $usuario;
    
    try {
        if (tableExists('eventos_calendario')) {
            $query = "
                INSERT INTO eventos_calendario (
                    titulo, descripcion, fecha_inicio, fecha_fin, todo_el_dia,
                    tipo_evento, estado, id_credito, id_cliente, prioridad, color,
                    id_usuario_creador, fecha_creacion
                ) VALUES (
                    :titulo, :descripcion, :fecha_inicio, :fecha_fin, :todo_el_dia,
                    :tipo_evento, :estado, :id_credito, :id_cliente, :prioridad, :color,
                    :id_usuario_creador, NOW()
                )
            ";
            
            $stmt = $pdo->prepare($query);
            
            $stmt->bindParam(':titulo', $data['titulo']);
            $stmt->bindParam(':descripcion', $data['descripcion']);
            $stmt->bindParam(':fecha_inicio', $data['fecha_inicio']);
            $stmt->bindParam(':fecha_fin', $data['fecha_fin']);
            $stmt->bindValue(':todo_el_dia', $data['todo_el_dia'] ?? false, PDO::PARAM_BOOL);
            $stmt->bindParam(':tipo_evento', $data['tipo_evento']);
            $stmt->bindValue(':estado', $data['estado'] ?? 'pendiente');
            $stmt->bindValue(':id_credito', $data['id_credito'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':id_cliente', $data['id_cliente'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':prioridad', $data['prioridad'] ?? 'media');
            $stmt->bindValue(':color', $data['color'] ?? '#3b82f6');
            $stmt->bindParam(':id_usuario_creador', $usuario['id_usuario']);
            
            $stmt->execute();
            $id_evento = $pdo->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Evento creado exitosamente',
                'data' => [
                    'id_evento' => $id_evento
                ]
            ];
        } else {
            // Fallback: guardar en fechas_calendario (usa solo fecha/descripcion)
            // Tomar cualquier calendario existente
            $calRes = $pdo->query("SELECT id_calendario_pago FROM calendarios_pago ORDER BY id_calendario_pago ASC LIMIT 1");
            $row = $calRes->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['id_calendario_pago'])) {
                return [
                    'success' => false,
                    'message' => 'No hay calendarios de pago definidos. Cree uno antes de registrar eventos.'
                ];
            }
            $id_cal = (int)$row['id_calendario_pago'];

            $fecha = isset($data['fecha_inicio']) ? date('Y-m-d', strtotime($data['fecha_inicio'])) : date('Y-m-d');
            $descripcion = $data['descripcion'] ?? ($data['titulo'] ?? 'Evento');

            $stmt = $pdo->prepare("INSERT INTO fechas_calendario (id_calendario_pago, fecha, es_feriado, descripcion) VALUES (:id_cal, :fecha, :es_feriado, :descripcion)");
            $esFeriado = 0; // false
            $stmt->bindParam(':id_cal', $id_cal, PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $fecha);
            $stmt->bindParam(':es_feriado', $esFeriado, PDO::PARAM_INT);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->execute();

            return [
                'success' => true,
                'message' => 'Evento creado en calendario de pagos',
                'data' => [
                    'id_evento' => $pdo->lastInsertId(),
                    'tabla' => 'fechas_calendario'
                ]
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al crear evento: ' . $e->getMessage()
        ];
    }
}

function actualizarEvento($id, $data) {
    global $pdo, $usuario;
    
    try {
        if (tableExists('eventos_calendario')) {
            $query = "
                UPDATE eventos_calendario SET
                    titulo = :titulo,
                    descripcion = :descripcion,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin,
                    todo_el_dia = :todo_el_dia,
                    tipo_evento = :tipo_evento,
                    estado = :estado,
                    id_credito = :id_credito,
                    id_cliente = :id_cliente,
                    prioridad = :prioridad,
                    color = :color,
                    fecha_modificacion = NOW()
                WHERE id_evento = :id
            ";
            
            $stmt = $pdo->prepare($query);
            
            $stmt->bindParam(':titulo', $data['titulo']);
            $stmt->bindParam(':descripcion', $data['descripcion']);
            $stmt->bindParam(':fecha_inicio', $data['fecha_inicio']);
            $stmt->bindParam(':fecha_fin', $data['fecha_fin']);
            $stmt->bindValue(':todo_el_dia', $data['todo_el_dia'] ?? false, PDO::PARAM_BOOL);
            $stmt->bindParam(':tipo_evento', $data['tipo_evento']);
            $stmt->bindValue(':estado', $data['estado'] ?? 'pendiente');
            $stmt->bindValue(':id_credito', $data['id_credito'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':id_cliente', $data['id_cliente'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':prioridad', $data['prioridad'] ?? 'media');
            $stmt->bindValue(':color', $data['color'] ?? '#3b82f6');
            $stmt->bindParam(':id', $id);
            
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Evento actualizado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No se encontró el evento o no se realizaron cambios'
                ];
            }
        } else {
            // Fallback: actualizar en fechas_calendario (solo fecha/descripcion)
            $fecha = isset($data['fecha_inicio']) ? date('Y-m-d', strtotime($data['fecha_inicio'])) : null;
            $campos = [];
            $params = [':id' => $id];
            if ($fecha !== null) { $campos[] = 'fecha = :fecha'; $params[':fecha'] = $fecha; }
            if (isset($data['descripcion'])) { $campos[] = 'descripcion = :descripcion'; $params[':descripcion'] = $data['descripcion']; }
            if (isset($data['es_feriado'])) { $campos[] = 'es_feriado = :es_feriado'; $params[':es_feriado'] = $data['es_feriado'] ? 1 : 0; }
            if (empty($campos)) {
                return ['success' => false, 'message' => 'Nada para actualizar'];
            }
            $sql = 'UPDATE fechas_calendario SET ' . implode(', ', $campos) . ' WHERE id_fecha = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'message' => 'Evento actualizado'];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al actualizar evento: ' . $e->getMessage()
        ];
    }
}

function eliminarEvento($id) {
    global $pdo, $usuario;
    
    try {
        if (tableExists('eventos_calendario')) {
            $query = "DELETE FROM eventos_calendario WHERE id_evento = :id";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Evento eliminado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No se encontró el evento'
                ];
            }
        } else {
            $stmt = $pdo->prepare('DELETE FROM fechas_calendario WHERE id_fecha = :id');
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Evento eliminado'];
            }
            return ['success' => false, 'message' => 'No se encontró el evento'];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al eliminar evento: ' . $e->getMessage()
        ];
    }
}

function getTiposEvento() {
    return [
        'success' => true,
        'data' => [
            ['value' => 'cita', 'label' => 'Cita con Cliente', 'color' => '#3b82f6'],
            ['value' => 'llamada', 'label' => 'Llamada de Seguimiento', 'color' => '#10b981'],
            ['value' => 'reunion', 'label' => 'Reunión', 'color' => '#8b5cf6'],
            ['value' => 'tarea', 'label' => 'Tarea Administrativa', 'color' => '#f59e0b'],
            ['value' => 'visita', 'label' => 'Visita de Campo', 'color' => '#ef4444'],
            ['value' => 'otro', 'label' => 'Otro', 'color' => '#6b7280']
        ]
    ];
}

function getEvento($id) {
    global $pdo, $usuario;
    
    try {
        if (!tableExists('eventos_calendario')) {
            // Fallback simple: intentar en fechas_calendario
            $stmt = $pdo->prepare('SELECT id_fecha as id_evento, descripcion as titulo, descripcion, fecha as fecha_inicio, fecha as fecha_fin FROM fechas_calendario WHERE id_fecha = :id');
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($evento) {
                $evento['todo_el_dia'] = true;
                $evento['tipo_evento'] = 'calendario_pago';
                $evento['estado'] = 'programada';
                return ['success' => true, 'data' => $evento];
            }
            return ['success' => false, 'message' => 'Evento no encontrado'];
        }

        $query = "
            SELECT 
                e.id_evento,
                e.titulo,
                e.descripcion,
                e.fecha_inicio,
                e.fecha_fin,
                e.todo_el_dia,
                e.tipo_evento,
                e.estado,
                e.id_credito,
                e.id_cliente,
                e.prioridad,
                e.color,
                c.monto_aprobado,
                cl.id_cliente as cliente_id,
                p.nombres,
                p.apellido_paterno,
                p.apellido_materno
            FROM eventos_calendario e
            LEFT JOIN creditos c ON e.id_credito = c.id_credito
            LEFT JOIN clientes cl ON e.id_cliente = cl.id_cliente
            LEFT JOIN personas p ON cl.dni_persona = p.dni
            WHERE e.id_evento = :id
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($evento) {
            return [
                'success' => true,
                'data' => $evento
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Evento no encontrado'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al obtener evento: ' . $e->getMessage()
        ];
    }
}

function getEventosProximos($limite = 10) {
    global $pdo, $usuario;
    
    try {
        if (!tableExists('eventos_calendario')) {
            // Fallback: próximas fechas en fechas_calendario
            $stmt = $pdo->prepare("SELECT id_fecha as id_evento, descripcion as titulo, descripcion, fecha as fecha_inicio, fecha as fecha_fin FROM fechas_calendario WHERE fecha >= CURRENT_DATE ORDER BY fecha ASC LIMIT :lim");
            $stmt->bindValue(':lim', (int)$limite, PDO::PARAM_INT);
            $stmt->execute();
            $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'data' => $eventos];
        }

        $query = "
            SELECT 
                e.id_evento,
                e.titulo,
                e.descripcion,
                e.fecha_inicio,
                e.fecha_fin,
                e.tipo_evento,
                e.prioridad,
                e.color,
                p.nombres,
                p.apellido_paterno
            FROM eventos_calendario e
            LEFT JOIN clientes cl ON e.id_cliente = cl.id_cliente
            LEFT JOIN personas p ON cl.dni_persona = p.dni
            WHERE e.fecha_inicio >= NOW()
                AND e.estado != 'completado'
            ORDER BY e.fecha_inicio ASC
            LIMIT :limite
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $eventos
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al obtener eventos próximos: ' . $e->getMessage()
        ];
    }
}

// Nota: Este módulo se adapta a la ausencia de la tabla 'eventos_calendario'
// usando 'fechas_calendario' del esquema existente cuando es necesario.

?>
