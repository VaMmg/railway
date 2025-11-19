<?php
// Script para arreglar la tabla cajas_usuario
require_once 'config/conexion.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Fix Cajas</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
echo ".success{color:#4ec9b0;}.error{color:#f48771;}.info{color:#569cd6;}</style></head><body>";

try {
    $pdo = getPDO();
    
    echo "<h2 class='info'>Iniciando corrección de tabla cajas_usuario...</h2>";
    
    // Leer el archivo SQL
    $sqlFile = __DIR__ . '/fix_cajas_table.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Archivo SQL no encontrado: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    echo "<p class='info'>Ejecutando SQL...</p>";
    
    // Ejecutar el SQL
    $pdo->exec($sql);
    
    echo "<p class='success'>✓ Tabla cajas_usuario actualizada correctamente</p>";
    echo "<h3 class='info'>Estructura de la tabla:</h3>";
    echo "<pre>";
    
    // Mostrar la estructura
    $stmt = $pdo->query("DESCRIBE cajas_usuario");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) " . 
             ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . 
             ($col['Key'] ? " [{$col['Key']}]" : "") . "\n";
    }
    
    echo "</pre>";
    echo "<h2 class='success'>✓ Corrección completada exitosamente</h2>";
    echo "<p><a href='/'>Volver al inicio</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 class='error'>✗ Error de base de datos:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit(1);
} catch (Exception $e) {
    echo "<h2 class='error'>✗ Error:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit(1);
}

echo "</body></html>";
