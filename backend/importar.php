<?php
set_time_limit(300);
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Importar BD</title></head><body>";
echo "<h1>Importando Base de Datos...</h1>";
flush();

// Conexión directa
$host = 'switchback.proxy.rlwy.net';
$port = 43047;
$dbname = 'railway';
$user = 'root';
$pass = 'BnMtNy1HCVWAn1ZCopQxIecExZPntYkn';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✓ Conectado a MySQL</p>";
    flush();
    
    // Leer SQL
    $sqlFile = __DIR__ . '/sistema_creditos2.sql';
    if (!file_exists($sqlFile)) {
        die("<p style='color:red'>✗ No se encuentra sistema_creditos2.sql</p></body></html>");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "<p>✓ Archivo leído: " . number_format(strlen($sql)) . " bytes</p>";
    flush();
    
    // Ejecutar
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $total = count($statements);
    $ok = 0;
    
    echo "<p>Ejecutando $total statements...</p>";
    flush();
    
    foreach ($statements as $i => $stmt) {
        if (empty($stmt) || substr($stmt, 0, 2) === '--') continue;
        
        try {
            $pdo->exec($stmt);
            $ok++;
            if ($ok % 100 == 0) {
                echo "<p>Progreso: $ok/$total</p>";
                flush();
            }
        } catch (PDOException $e) {
            // Ignorar errores de "ya existe"
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "<p style='color:orange'>Advertencia en statement $i: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<h2 style='color:green'>✓ Importación completada</h2>";
    echo "<p>Statements ejecutados: $ok</p>";
    
    // Verificar tablas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tablas creadas: " . count($tables) . "</p>";
    echo "<ul>";
    foreach (array_slice($tables, 0, 10) as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "<li>$table: $count registros</li>";
    }
    echo "</ul>";
    
    echo "<p><a href='https://triumphant-laughter-production-up.railway.app'>Ir a la aplicación</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
