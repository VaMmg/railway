<?php
// Test endpoint
require_once '../config/cors.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        jsonResponse(true, 'Backend funcionando correctamente', [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => 'GET',
            'server' => $_SERVER['SERVER_NAME']
        ]);
        break;
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        jsonResponse(true, 'POST recibido correctamente', [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => 'POST',
            'received_data' => $input,
            'server' => $_SERVER['SERVER_NAME']
        ]);
        break;
    default:
        jsonResponse(false, 'Método no soportado', null, 405);
}
?>
