<?php
// Script para actualizar la tabla cajas_usuario sin perder datos
require_once 'config/conexion.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Update Cajas</title>";
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
    
    // Agregar columnas faltantes
    $updates = [];
    
    if (!in_array('id_cajas_usuario', $existingColumns)) {
        echo "<p class='info'>Agregando columna id_cajas_usuario...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN id_cajas_usuario INT AUTO_INCREMENT PRIMARY KEY FIRST");
        $updates[] = "id_cajas_usuario";
    }
    
    if (!in_array('limite_credito', $existingColumns)) {
        echo "<p class='info'>Agregando columna limite_credito...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN limite_credito DECIMAL(10,2) DEFAULT 5000.00 AFTER id_usuario");
        $updates[] = "limite_credito";
    }
    
    if (!in_array('saldo_actual', $existingColumns)) {
        echo "<p class='info'>Agregando columna saldo_actual...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN saldo_actual DECIMAL(10,2) DEFAULT 0.00 AFTER limite_credito");
        $updates[] = "saldo_actual";
        
        // Copiar datos de monto_inicial a saldo_actual si existe
        if (in_array('monto_inicial', $existingColumns)) {
            echo "<p class='info'>Copiando datos de monto_inicial a saldo_actual...</p>";
            $pdo->exec("UPDATE cajas_usuario SET saldo_actual = monto_inicial");
        }
    }
    
    if (!in_array('saldo_final', $existingColumns)) {
        echo "<p class='info'>Agregando columna saldo_final...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN saldo_final DECIMAL(10,2) DEFAULT 0.00 AFTER saldo_actual");
        $updates[] = "saldo_final";
        
        // Copiar datos de monto_final a saldo_final si existe
        if (in_array('monto_final', $existingColumns)) {
            echo "<p class='info'>Copiando datos de monto_final a saldo_final...</p>";
            $pdo->exec("UPDATE cajas_usuario SET saldo_final = monto_final");
        }
    }
    
    if (!in_array('habilitada_por_gerente', $existingColumns)) {
        echo "<p class='info'>Agregando columna habilitada_por_gerente...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN habilitada_por_gerente TINYINT(1) DEFAULT 1 AFTER estado_caja");
        $updates[] = "habilitada_por_gerente";
    }
    
    if (!in_array('comentario', $existingColumns)) {
        echo "<p class='info'>Agregando columna comentario...</p>";
        $pdo->exec("ALTER TABLE cajas_usuario ADD COLUMN comentario TEXT DEFAULT NULL");
        $updates[] = "comentario";
        
        // Copiar datos de observaciones a comentario si existe
        if (in_array('observaciones', $existingColumns)) {
            echo "<p class='info'>Copiando datos de observaciones a comentario...</p>";
            $pdo->exec("UPDATE cajas_usuario SET comentario = observaciones");
        }
    }
    
    if (empty($updates)) {
        echo "<p class='success'>✓ La tabla ya tiene todas las columnas necesarias</p>";
    } else {
        echo "<p class='success'>✓ Columnas agregadas: " . implode(', ', $updates) . "</p>";
    }
    
    // Mostrar estructura final
    echo "<h3 class='info'>Estructura final:</h3><pre>";
    $stmt = $pdo->query("DESCRIBE cajas_usuario");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) " . 
             ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . 
             ($col['Key'] ? " [{$col['Key']}]" : "") . "\n";
    }
    echo "</pre>";
    
    // Mostrar datos actuales
    echo "<h3 class='info'>Datos actuales:</h3><pre>";
    $stmt = $pdo->query("SELECT * FROM cajas_usuario");
    $cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cajas)) {
        echo "No hay cajas registradas\n";
    } else {
        foreach ($cajas as $caja) {
            echo "Caja ID: {$caja['id_cajas_usuario']}, Usuario: {$caja['id_usuario']}, ";
            echo "Saldo: {$caja['saldo_actual']}, Estado: {$caja['estado_caja']}\n";
        }
    }
    echo "</pre>";
    
    echo "<h2 class='success'>✓ Actualización completada exitosamente</h2>";
    echo "<p><a href='/'>Volver al inicio</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 class='error'>✗ Error de base de datos:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
} catch (Exception $e) {
    echo "<h2 class='error'>✗ Error:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "</body></html>";
