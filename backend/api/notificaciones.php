<?php
// BACKEND/api/notificaciones.php
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
    case 'PUT':
        handlePut();
        break;
    case 'POST':
        handlePost();
        break;
    default:
        jsonResponse(false, 'Método no soportado', null, 405);
}

function handleGet() {
    $user = requireAuth();
    $pdo = getPDO();
    
    $idUsuario = $user['id'] ?? $user['id_usuario'];
    $idRol = $user['id_rol'] ?? $user['rol'];
    
    // Obtener notificaciones persistentes (personales o por rol)
    $sql = "
        SELECT 
            n.*,
            u.usuario as usuario_origen_nombre
        FROM notificaciones n
        LEFT JOIN usuarios u ON n.usuario_origen = u.id_usuario
        WHERE (n.destinatario_usuario = :id_usuario OR n.destinatario_rol = :id_rol)
        AND n.leida = 0
        ORDER BY n.fecha_envio DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_usuario' => $idUsuario,
        ':id_rol' => $idRol
    ]);
    $notificaciones = $stmt->fetchAll();
    
    jsonResponse(true, 'Notificaciones obtenidas', [
        'notificaciones' => $notificaciones,
        'total' => count($notificaciones)
    ]);
}

function handlePut() {
    $user = requireAuth();
    $pdo = getPDO();
    global $input;
    
    $idUsuario = $user['id'] ?? $user['id_usuario'];
    
    // Marcar notificación como leída
    if (isset($_GET['id'])) {
        $idNotificacion = $_GET['id'];
        
        $sql = "
            UPDATE notificaciones 
            SET leida = 1 
            WHERE id_notificacion = :id 
            AND (destinatario_usuario = :id_usuario OR destinatario_rol IN (SELECT id_rol FROM usuarios WHERE id_usuario = :id_usuario2))
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $idNotificacion,
            ':id_usuario' => $idUsuario,
            ':id_usuario2' => $idUsuario
        ]);
        
        jsonResponse(true, 'Notificación marcada como leída');
    } else if (isset($input['action']) && $input['action'] === 'marcar_todas_leidas') {
        // Marcar todas como leídas
        $sql = "
            UPDATE notificaciones 
            SET leida = 1 
            WHERE (destinatario_usuario = :id_usuario OR destinatario_rol IN (SELECT id_rol FROM usuarios WHERE id_usuario = :id_usuario2))
            AND leida = 0
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':id_usuario2' => $idUsuario
        ]);
        
        jsonResponse(true, 'Todas las notificaciones marcadas como leídas');
    }
}

function handlePost() {
    // Endpoint para crear notificaciones manualmente (si es necesario)
    $user = requireAuth();
    $pdo = getPDO();
    global $input;
    
    require_once '../helpers/notificaciones_helper.php';
    
    $tipo = $input['tipo'] ?? 'general';
    $mensaje = $input['mensaje'] ?? '';
    $destinatarioUsuario = $input['destinatario_usuario'] ?? null;
    $destinatarioRol = $input['destinatario_rol'] ?? null;
    $referenciaId = $input['referencia_id'] ?? null;
    $referenciaTipo = $input['referencia_tipo'] ?? null;
    
    $usuarioOrigen = $user['id'] ?? $user['id_usuario'];
    
    $resultado = crearNotificacion(
        $pdo, $tipo, $mensaje, 
        $destinatarioUsuario, $destinatarioRol, $usuarioOrigen,
        $referenciaId, $referenciaTipo
    );
    
    if ($resultado) {
        jsonResponse(true, 'Notificación creada');
    } else {
        jsonResponse(false, 'Error al crear notificación', null, 500);
    }
}
