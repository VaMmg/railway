<?php
// Script para arreglar la tabla cajas_usuario
require_once 'config/conexion.php';

try {
    $pdo = getPDO();
    
    echo "Iniciando corrección de tabla cajas_usuario...\n";
    
    // Leer el archivo SQL
    $sql = file_get_contents(__DIR__ . '/fix_cajas_table.sql');
    
    // Ejecutar el SQL
    $pdo->exec($sql);
    
    echo "✓ Tabla cajas_usuario actualizada correctamente\n";
    echo "✓ Estructura de la tabla:\n";
    
    // Mostrar la estructura
    $stmt = $pdo->query("DESCRIBE cajas_usuario");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n✓ Corrección completada exitosamente\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
