<?php
// Script para agregar columna usuario_creacion a la tabla clientes
require_once 'config/conexion.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Fix Clientes - Add Usuario</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        pre { background: #252526; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<h2 class='info'>🔧 Agregando columna usuario_creacion a tabla clientes</h2>

<?php
try {
    $pdo = getPDO();
    
    echo "<h3 class='info'>Estructura ANTES:</h3><pre>";
    $stmt = $pdo->query("DESCRIBE clientes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    foreach ($columns as $col) {
        echo "{$col['Field']} ({$col['Type']})\n";
    }
    echo "</pre>";
    
    // Agregar columna usuario_creacion si no existe
    if (!in_array('usuario_creacion', $existingColumns)) {
        echo "<p class='info'>➤ Agregando columna usuario_creacion...</p>";
        $pdo->exec("ALTER TABLE clientes ADD COLUMN usuario_creacion INT(11) DEFAULT NULL AFTER estado_cliente");
        echo "<p class='success'>✓ Columna agregada</p>";
    } else {
        echo "<p class='info'>ℹ La columna usuario_creacion ya existe</p>";
    }
    
    echo "<h3 class='info'>Estructura DESPUÉS:</h3><pre>";
    $stmt = $pdo->query("DESCRIBE clientes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "{$col['Field']} ({$col['Type']})\n";
    }
    echo "</pre>";
    
    echo "<h3 class='success'>✅ Proceso completado</h3>";
    echo "<p>Ahora los clientes se asociarán correctamente con el trabajador que los creó.</p>";
    echo "<p><a href='/' style='color:#569cd6;'>← Volver</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 class='error'>❌ Error:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
</body>
</html>
