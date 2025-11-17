<?php
// API para actualizar estados de cuotas vencidas
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no soportado', null, 405);
}

$user = requireGerente();
$pdo = getPDO();

try {
    $pdo->beginTransaction();
    
    $sql = "
        UPDATE cuotas 
        SET estado = 'Vencida'
        WHERE estado = 'Pendiente'
        AND fecha_programada < CURDATE()
    ";
    
    $affected = $pdo->exec($sql);
    $pdo->commit();
    
    jsonResponse(true, "Se actualizaron $affected cuotas a estado Vencida", [
        'cuotas_actualizadas' => $affected,
        'fecha_ejecucion' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Error al actualizar estados: ' . $e->getMessage(), null, 500);
}
