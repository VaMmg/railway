<?php
// Script para importar base de datos usando las variables de entorno de Railway

echo "=== Importador de Base de Datos para Railway ===\n\n";

// Usar las variables de entorno que ya están configuradas
$host = getenv('DB_HOST') ?: 'mysql.railway.internal';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'railway';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

echo "Conectando a: $host:$port\n";
echo "Base de datos: $dbname\n";
echo "Usuario: $user\n\n";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✓ Conexión exitosa\n\n";
    
    // Leer el archivo SQL
    $sqlFile = __DIR__ . '/backend/sistema_creditos2.sql';
    
    if (!file_exists($sqlFile)) {
        die("✗ Error: No se encontró el archivo SQL en: $sqlFile\n");
    }
    
    echo "Leyendo archivo SQL...\n";
    $sql = file_get_contents($sqlFile);
    
    echo "✓ Archivo leído (" . number_format(strlen($sql)) . " bytes)\n\n";
    echo "Importando base de datos (esto puede tomar 1-2 minutos)...\n\n";
    
    // Dividir por punto y coma y ejecutar cada statement
    $statements = explode(';', $sql);
    $total = count($statements);
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        
        if (empty($statement) || substr($statement, 0, 2) === '--') {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            if ($executed % 50 == 0) {
                echo "  Progreso: $executed statements ejecutados...\n";
            }
        } catch (PDOException $e) {
            // Ignorar algunos errores comunes
            $msg = $e->getMessage();
            if (strpos($msg, 'already exists') === false && 
                strpos($msg, 'Duplicate') === false) {
                $errors++;
                if ($errors < 5) {
                    echo "  Advertencia en statement " . ($index + 1) . ": " . substr($msg, 0, 100) . "...\n";
                }
            }
        }
    }
    
    echo "\n✓ Importación completada\n";
    echo "  - Statements ejecutados: $executed\n";
    echo "  - Errores: $errors\n\n";
    
    // Verificar que se importaron las tablas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Tablas en la base de datos: " . count($tables) . "\n";
    
    if (count($tables) > 0) {
        echo "\nTablas importadas:\n";
        foreach (array_slice($tables, 0, 10) as $table) {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "  - $table: $count registros\n";
        }
        if (count($tables) > 10) {
            echo "  ... y " . (count($tables) - 10) . " tablas más\n";
        }
    }
    
    echo "\n¡Base de datos importada exitosamente!\n";
    echo "Ahora puedes iniciar sesión en tu aplicación.\n";
    
} catch (PDOException $e) {
    die("\n✗ Error: " . $e->getMessage() . "\n");
}
