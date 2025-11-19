<?php
require_once 'config/conexion.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Check Clientes Table</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        pre { background: #252526; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<h2 class='info'>🔍 Verificando tabla clientes</h2>

<?php
try {
    $pdo = getPDO();
    
    echo "<h3 class='info'>Estructura de tabla 'clientes':</h3><pre>";
    $stmt = $pdo->query("DESCRIBE clientes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "{$col['Field']} ({$col['Type']})\n";
    }
    echo "</pre>";
    
    echo "<h3 class='info'>Estructura de tabla 'contacto':</h3><pre>";
    $stmt = $pdo->query("DESCRIBE contacto");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "{$col['Field']} ({$col['Type']})\n";
    }
    echo "</pre>";
    
    echo "<p class='success'>✓ Tablas verificadas</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
}
?>
</body>
</html>
