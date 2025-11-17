<?php
/**
 * API para gestión de respaldos de base de datos
 * Solo accesible para administradores
 */

require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

setCorsHeaders();

// Verificar autenticación
$user = requireAuth();

// Solo administradores pueden gestionar respaldos
if (($user['id_rol'] ?? $user['rol']) != 1) {
    jsonResponse(false, 'Acceso denegado. Solo administradores pueden gestionar respaldos', null, 403);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    default:
        jsonResponse(false, 'Método no permitido', null, 405);
}

/**
 * Listar respaldos disponibles
 */
function handleGet() {
    $backupDir = __DIR__ . '/../copias_de_respaldo';
    
    if (!file_exists($backupDir)) {
        jsonResponse(true, 'No hay respaldos disponibles', ['respaldos' => []]);
        return;
    }
    
    // Obtener archivos de respaldo
    $files = glob($backupDir . '/backup_*.sql.gz');
    
    $respaldos = [];
    foreach ($files as $file) {
        $filename = basename($file);
        $size = filesize($file);
        $sizeMB = round($size / 1024 / 1024, 2);
        $fecha = filemtime($file);
        
        // Extraer fecha del nombre del archivo
        preg_match('/backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})/', $filename, $matches);
        $fechaStr = isset($matches[1]) ? str_replace('_', ' ', $matches[1]) : date('Y-m-d H:i:s', $fecha);
        
        $respaldos[] = [
            'nombre' => $filename,
            'fecha' => $fechaStr,
            'timestamp' => $fecha,
            'tamano' => $sizeMB . ' MB',
            'tamano_bytes' => $size
        ];
    }
    
    // Ordenar por fecha (más reciente primero)
    usort($respaldos, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    // Leer log de respaldos
    $logFile = $backupDir . '/backup_log.txt';
    $ultimosLogs = [];
    
    if (file_exists($logFile)) {
        $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ultimosLogs = array_slice(array_reverse($logs), 0, 10);
    }
    
    jsonResponse(true, 'Respaldos obtenidos', [
        'respaldos' => $respaldos,
        'total' => count($respaldos),
        'ultimos_logs' => $ultimosLogs
    ]);
}

/**
 * Ejecutar respaldo manual
 */
function handlePost() {
    global $input;
    
    $action = $input['action'] ?? 'backup';
    
    if ($action === 'backup') {
        // Ejecutar script de respaldo
        $scriptPath = __DIR__ . '/../scripts/backup_database.php';
        
        if (!file_exists($scriptPath)) {
            jsonResponse(false, 'Script de respaldo no encontrado', null, 404);
            return;
        }
        
        // Ejecutar en segundo plano
        $phpPath = PHP_BINARY;
        $command = sprintf('"%s" "%s" > nul 2>&1 &', $phpPath, $scriptPath);
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            pclose(popen("start /B " . $command, "r"));
        } else {
            // Linux/Unix
            exec($command . " > /dev/null 2>&1 &");
        }
        
        jsonResponse(true, 'Respaldo iniciado en segundo plano. Revisa los logs en unos momentos.', [
            'mensaje' => 'El respaldo se está ejecutando. Actualiza la lista en unos segundos para ver el nuevo respaldo.'
        ]);
        
    } else {
        jsonResponse(false, 'Acción no válida', null, 400);
    }
}

function jsonResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
