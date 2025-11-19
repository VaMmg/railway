<?php
// Script para actualizar la tabla cajas_usuario correctamente
require_once 'config/conexion.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Fix Cajas Final</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
echo ".success{color:#4ec9b0;}.error{color:#f48771;}.info{color:#569cd6;}</style></head><body>";

try {
    $pdo = getPDO();
    
    echo "<h2 class='info'>Actualizando tabla cajas_usuario...</h2>";
    
    // Verificar estructura actual
    echo "<h3 class='info'>Estructura actual:</h3><pre>";
    $stmt = $pdo->query("DESCRIBE cajas_usuario");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    echo "</pre>";
    
    $updates = [];
    
    // 1. Renombrar id_caja a id_cajas_usuario si existe
    if (in_array('id_caja', $existingColumns) && !in_array('id_cajas_usuario', $existingColumns)) {
        echo "<p class='info'>Renombrando id_caja a id_cajas_usuario...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario CHANGE COLUMN id_caja id_cajas_usuario INT(11) NOT NULL AUTO_INCREMENT");
        $updates[] = "id_caja → id_cajas_usuario";
    }
    
    // 2. Agregar limite_credito si no existe
    if (!in_array('limite_credito', $existingColumns)) {
        echo "<p class='info'>Agregando columna limite_credito...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN limite_credito DECIMAL(10,2) DEFAULT 5000.00 AFTER id_usuario");
        $updates[] = "limite_credito";
    }
    
    // 3. Renombrar o agregar saldo_actual
    if (in_array('monto_inicial', $existingColumns) && !in_array('saldo_actual', $existingColumns)) {
        echo "<p class='info'>Renombrando monto_inicial a saldo_actual...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario CHANGE COLUMN monto_inicial saldo_actual DECIMAL(10,2) DEFAULT 0.00");
        $updates[] = "monto_inicial → saldo_actual";
    } elseif (!in_array('saldo_actual', $existingColumns)) {
        echo "<p class='info'>Agregando columna saldo_actual...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN saldo_actual DECIMAL(10,2) DEFAULT 0.00 AFTER limite_credito");
        $updates[] = "saldo_actual";
    }
    
    // 4. Renombrar o agregar saldo_final
    if (in_array('monto_final', $existingColumns) && !in_array('saldo_final', $existingColumns)) {
        echo "<p class='info'>Renombrando monto_final a saldo_final...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario CHANGE COLUMN monto_final saldo_final DECIMAL(10,2) DEFAULT 0.00");
        $updates[] = "monto_final → saldo_final";
    } elseif (!in_array('saldo_final', $existingColumns)) {
        echo "<p class='info'>Agregando columna saldo_final...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN saldo_final DECIMAL(10,2) DEFAULT 0.00 AFTER saldo_actual");
        $updates[] = "saldo_final";
    }
    
    // 5. Agregar habilitada_por_gerente si no existe
    if (!in_array('habilitada_por_gerente', $existingColumns)) {
        echo "<p class='info'>Agregando columna habilitada_por_gerente...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN habilitada_por_gerente TINYINT(1) DEFAULT 1 AFTER estado_caja");
        $updates[] = "habilitada_por_gerente";
    }
    
    // 6. Renombrar o agregar comentario
    if (in_array('observaciones', $existingColumns) && !in_array('comentario', $existingColumns)) {
        echo "<p class='info'>Renombrando observaciones a comentario...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario CHANGE COLUMN observaciones comentario TEXT DEFAULT NULL");
        $updates[] = "observaciones → comentario";
    } elseif (!in_array('comentario', $existingColumns)) {
        echo "<p class='info'>Agregando columna comentario...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN comentario TEXT DEFAULT NULL");
        $updates[] = "comentario";
    }
    
    // 7. Asegurar que hora_apertura y hora_cierre sean DATETIME
    echo "<p class='info'>Actualizando tipos de datos de hora_apertura y hora_cierre...</p>";
    $pdo->exec("ALTER TABLE cajas_usuario MODIFY COLUMN hora_apertura DATETIME DEFAULT NULL");
    $pdo->exec("ALTER TABLE cajas_usuario MODIFY COLUMN hora_cierre DATETIME DEFAULT NULL");
    $updates[] = "hora_apertura/hora_cierre → DATETIME";
    
    if (empty($updates)) {
        echo "<p class='success'>✓ La tabla ya tiene la estructura correcta</p>";
    } else {
        echo "<p class='success'>✓ Cambios realizados: " . implode(', ', $updates) . "</p>";
    }
    
    // Mostrar estructura final
    echo "<h3 class='info'>Estructura final:</h3><pre>";
    $stmt = $pdo->query("DESCRIBE cajas_usuario");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) " . 
             ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . 
             ($col['Key'] ? " [{$col['Key']}]" : "") . 
             ($col['Default'] !== null ? " DEFAULT {$col['Default']}" : "") . "\n";
    }
    echo "</pre>";
    
    // Mostrar datos actuales
    echo "<h3 class='info'>Datos actuales:</h3><pre>";
    $stmt = $pdo->query("SELECT id_cajas_usuario, id_usuario, saldo_actual, limite_credito, estado_caja, habilitada_por_gerente FROM cajas_usuario");
    $cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cajas)) {
        echo "No hay cajas registradas\n";
    } else {
        foreach ($cajas as $caja) {
            echo "ID: {$caja['id_cajas_usuario']}, Usuario: {$caja['id_usuario']}, ";
            echo "Saldo: {$caja['saldo_actual']}, Límite: {$caja['limite_credito']}, ";
            echo "Estado: {$caja['estado_caja']}, Habilitada: " . ($caja['habilitada_por_gerente'] ? 'Sí' : 'No') . "\n";
        }
    }
    echo "</pre>";
    
    echo "<h2 class='success'>✓ Actualización completada exitosamente</h2>";
    echo "<p class='info'>Ahora puedes usar el sistema de cajas normalmente.</p>";
    echo "<p><a href='/' style='color:#569cd6;'>Volver al inicio</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 class='error'>✗ Error de base de datos:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p class='info'>Código de error: " . $e->getCode() . "</p>";
} catch (Exception $e) {
    echo "<h2 class='error'>✗ Error:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "</body></html>";
