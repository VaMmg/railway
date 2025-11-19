<?php
// Script definitivo para arreglar cajas_usuario
require_once 'config/conexion.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Fix Cajas NOW</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        .warning { color: #dcdcaa; }
        pre { background: #252526; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<?php

try {
    $pdo = getPDO();
    
    echo "<h2 class='info'>🔧 Arreglando tabla cajas_usuario...</h2>";
    
    // Paso 1: Ver estructura actual
    echo "<h3 class='info'>📋 Estructura actual:</h3><pre>";
    $stmt = $pdo->query("DESCRIBE cajas_usuario");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    foreach ($columns as $col) {
        echo "  {$col['Field']} - {$col['Type']} - {$col['Key']}\n";
    }
    echo "</pre>";
    
    // Paso 2: Ejecutar cambios uno por uno con manejo de errores
    $changes = [];
    
    // 1. Renombrar id_caja a id_cajas_usuario
    if (in_array('id_caja', $existingColumns)) {
        try {
            echo "<p class='info'>➤ Renombrando id_caja → id_cajas_usuario...</p>";
            $pdo->exec("ALTER TABLE cajas_usuario CHANGE COLUMN id_caja id_cajas_usuario INT(11) NOT NULL AUTO_INCREMENT");
            $changes[] = "✓ id_caja → id_cajas_usuario";
            echo "<p class='success'>  ✓ Hecho</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>  ⚠ Ya existe o error: " . $e->getMessage() . "</p>";
        }
    }
    
    // 2. Agregar limite_credito
    if (!in_array('limite_credito', $existingColumns)) {
        try {
            echo "<p class='info'>➤ Agregando limite_credito...</p>";
            $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN limite_credito DECIMAL(10,2) DEFAULT 5000.00 AFTER id_usuario");
            $changes[] = "✓ limite_credito agregado";
            echo "<p class='success'>  ✓ Hecho</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>  ⚠ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    // 3. Renombrar monto_inicial a saldo_actual
    if (in_array('monto_inicial', $existingColumns) && !in_array('saldo_actual', $existingColumns)) {
        try {
            echo "<p class='info'>➤ Renombrando monto_inicial → saldo_actual...</p>";
            $pdo->exec("ALTER TABLE cajas_usuario CHANGE COLUMN monto_inicial saldo_actual DECIMAL(10,2) DEFAULT 0.00");
            $changes[] = "✓ monto_inicial → saldo_actual";
            echo "<p class='success'>  ✓ Hecho</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>  ⚠ Error: " . $e->getMessage() . "</p>";
        }
    } elseif (!in_array('saldo_actual', $existingColumns)) {
        try {
            echo "<p class='info'>➤ Agregando saldo_actual...</p>";
            $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN saldo_actual DECIMAL(10,2) DEFAULT 0.00");
            $changes[] = "✓ saldo_actual agregado";
            echo "<p class='success'>  ✓ Hecho</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>  ⚠ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    // 4. Renombrar monto_final a saldo_final
    if (in_array('monto_final', $existingColumns) && !in_array('saldo_final', $existingColumns)) {
        try {
            echo "<p class='info'>➤ Renombrando monto_final → saldo_final...</p>";
            $pdo->exec("ALTER TABLE cajas_usuario CHANGE COLUMN monto_final saldo_final DECIMAL(10,2) DEFAULT 0.00");
            $changes[] = "✓ monto_final → saldo_final";
            echo "<p class='success'>  ✓ Hecho</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>  ⚠ Error: " . $e->getMessage() . "</p>";
        }
    } elseif (!in_array('saldo_final', $existingColumns)) {
        try {
            echo "<p class='info'>➤ Agregando saldo_final...</p>";
            $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN saldo_final DECIMAL(10,2) DEFAULT 0.00");
            $changes[] = "✓ saldo_final agregado";
            echo "<p class='success'>  ✓ Hecho</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>  ⚠ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    // 5. Agregar habilitada_por_gerente
    if (!in_array('habilitada_por_gerente', $existingColumns)) {
        try {
            echo "<p class='info'>➤ Agregando habilitada_por_gerente...</p>";
            $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN habilitada_por_gerente TINYINT(1) DEFAULT 1");
            $changes[] = "✓ habilitada_por_gerente agregado";
            echo "<p class='success'>  ✓ Hecho</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>  ⚠ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    // 6. Renombrar observaciones a comentario
    if (in_array('observaciones', $existingColumns) && !in_array('comentario', $existingColumns)) {
        try {
            echo "<p class='info'>➤ Renombrando observaciones → comentario...</p>";
            $pdo->exec("ALTER TABLE cajas_usuario CHANGE COLUMN observaciones comentario TEXT DEFAULT NULL");
            $changes[] = "✓ observaciones → comentario";
            echo "<p class='success'>  ✓ Hecho</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>  ⚠ Error: " . $e->getMessage() . "</p>";
        }
    } elseif (!in_array('comentario', $existingColumns)) {
        try {
            echo "<p class='info'>➤ Agregando comentario...</p>";
            $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN comentario TEXT DEFAULT NULL");
            $changes[] = "✓ comentario agregado";
            echo "<p class='success'>  ✓ Hecho</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>  ⚠ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    // 7. Actualizar tipos de hora_apertura y hora_cierre
    try {
        echo "<p class='info'>➤ Actualizando hora_apertura a DATETIME...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario MODIFY COLUMN hora_apertura DATETIME DEFAULT NULL");
        $changes[] = "✓ hora_apertura → DATETIME";
        echo "<p class='success'>  ✓ Hecho</p>";
    } catch (PDOException $e) {
        echo "<p class='warning'>  ⚠ Error: " . $e->getMessage() . "</p>";
    }
    
    try {
        echo "<p class='info'>➤ Actualizando hora_cierre a DATETIME...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario MODIFY COLUMN hora_cierre DATETIME DEFAULT NULL");
        $changes[] = "✓ hora_cierre → DATETIME";
        echo "<p class='success'>  ✓ Hecho</p>";
    } catch (PDOException $e) {
        echo "<p class='warning'>  ⚠ Error: " . $e->getMessage() . "</p>";
    }
    
    // Resumen de cambios
    echo "<h3 class='success'>✅ Cambios realizados:</h3>";
    if (empty($changes)) {
        echo "<p class='info'>No se realizaron cambios (la tabla ya estaba correcta)</p>";
    } else {
        echo "<ul>";
        foreach ($changes as $change) {
            echo "<li>$change</li>";
        }
        echo "</ul>";
    }
    
    // Mostrar estructura final
    echo "<h3 class='info'>📋 Estructura FINAL:</h3><pre>";
    $stmt = $pdo->query("DESCRIBE cajas_usuario");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        $key = $col['Key'] ? " [{$col['Key']}]" : "";
        $default = $col['Default'] !== null ? " = {$col['Default']}" : "";
        echo "  {$col['Field']} ({$col['Type']}){$key}{$default}\n";
    }
    echo "</pre>";
    
    // Mostrar datos
    echo "<h3 class='info'>📊 Datos actuales:</h3><pre>";
    try {
        $stmt = $pdo->query("SELECT * FROM cajas_usuario LIMIT 5");
        $cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($cajas)) {
            echo "No hay cajas registradas\n";
        } else {
            foreach ($cajas as $i => $caja) {
                echo "Caja #" . ($i + 1) . ":\n";
                echo "  ID: {$caja['id_cajas_usuario']}\n";
                echo "  Usuario: {$caja['id_usuario']}\n";
                echo "  Saldo: {$caja['saldo_actual']}\n";
                echo "  Límite: {$caja['limite_credito']}\n";
                echo "  Estado: {$caja['estado_caja']}\n";
                echo "  Habilitada: " . ($caja['habilitada_por_gerente'] ? 'Sí' : 'No') . "\n\n";
            }
        }
    } catch (PDOException $e) {
        echo "Error al leer datos: " . $e->getMessage() . "\n";
    }
    echo "</pre>";
    
    echo "<h2 class='success'>🎉 ¡PROCESO COMPLETADO!</h2>";
    echo "<p class='info'>El sistema de cajas ahora debería funcionar correctamente.</p>";
    echo "<p><a href='/' style='color:#569cd6; text-decoration:none;'>← Volver al inicio</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 class='error'>❌ Error crítico de base de datos:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p class='info'>Código: " . $e->getCode() . "</p>";
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Error:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

?>
</body>
</html>
