<?php
header('Content-Type: text/plain; charset=utf-8');

// Aumentar límites
set_time_limit(300);
ini_set('memory_limit', '512M');

echo "=== Importador de Base de Datos ===\n\n";

// Incluir configuración de conexión
require_once __DIR__ . '/../config/conexion.php';

try {
    $pdo = getPDO();
    $dbType = getCurrentDbType();
    
    echo "✓ Conectado a base de datos ($dbType)\n\n";
    
    // Leer archivo SQL
    $sqlFile = __DIR__ . '/../sistema_creditos2.sql';
    
    if (!file_exists($sqlFile)) {
        die("✗ Error: No se encontró el archivo SQL\n");
    }
    
    echo "Leyendo archivo SQL...\n";
    $sql = file_get_contents($sqlFile);
    echo "✓ Archivo leído (" . number_format(strlen($sql)) . " bytes)\n\n";
    
    echo "Importando base de datos...\n";
    echo "Esto puede tomar 1-2 minutos, por favor espera...\n\n";
    
    // Dividir por punto y coma
    $statements = explode(';', $sql);
    $total = count($statements);
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        
        // Ignorar comentarios y líneas vacías
        if (empty($statement) || 
            substr($statement, 0, 2) === '--' || 
            substr($statement, 0, 2) === '/*') {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            if ($executed % 100 == 0) {
                echo "  Progreso: $executed statements ejecutados...\n";
                flush();
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Ignorar errores comunes
            if (strpos($msg, 'already exists') === false && 
                strpos($msg, 'Duplicate') === false &&
                strpos($msg, 'Unknown database') === false) {
                $errors++;
                if ($errors <= 3) {
                    echo "  Advertencia: " . substr($msg, 0, 100) . "...\n";
                }
            }
        }
    }
    
    echo "\n✓ Importación completada\n";
    echo "  - Statements ejecutados: $executed\n";
    echo "  - Advertencias: $errors\n\n";
    
    // Verificar tablas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Tablas en la base de datos: " . count($tables) . "\n\n";
    
    if (count($tables) > 0) {
        echo "Primeras 15 tablas importadas:\n";
        foreach (array_slice($tables, 0, 15) as $table) {
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                echo "  - $table: $count registros\n";
            } catch (Exception $e) {
                echo "  - $table: (error al contar)\n";
            }
        }
        if (count($tables) > 15) {
            echo "  ... y " . (count($tables) - 15) . " tablas más\n";
        }
    }
    
    echo "\n¡Base de datos importada exitosamente!\n";
    echo "Ahora puedes iniciar sesión en tu aplicación.\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nDetalles técnicos:\n";
    echo $e->getTraceAsString();
}
