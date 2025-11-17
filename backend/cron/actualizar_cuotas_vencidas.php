<?php
// Script para actualizar automáticamente el estado de cuotas vencidas
require_once __DIR__ . '/../config/conexion.php';

$pdo = getPDO();

try {
    $pdo->beginTransaction();
    
    // Actualizar cuotas pendientes que ya vencieron
    $sql = "
        UPDATE cuotas 
        SET estado = 'Vencida'
        WHERE estado = 'Pendiente'
        AND fecha_programada < CURDATE()
    ";
    
    $affected = $pdo->exec($sql);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Se actualizaron $affected cuotas a estado Vencida",
        'cuotas_actualizadas' => $affected,
        'fecha_ejecucion' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
