<?php
// BACKEND/api/log_accesos.php - Consulta de logs de acceso
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet();
        break;
    default:
        jsonResponse(false, 'Método no soportado', null, 405);
}

function handleGet() {
    // Solo administradores y gerentes pueden ver logs
    $userInfo = requireGerente();
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 100;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    $auth = new AuthSystem();
    try {
        $logs = $auth->getAccessLogs($limit, $userId);
        jsonResponse(true, 'Logs obtenidos', [
            'data' => $logs,
            'count' => count($logs)
        ]);
    } catch (Exception $e) {
        jsonResponse(false, 'Error al obtener logs: ' . $e->getMessage(), null, 500);
    }
}
?>
