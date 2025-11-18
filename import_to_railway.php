<?php
// Script temporal para importar base de datos a Railway

$host = 'switchback.proxy.rlwy.net';
$port = 43047;
$dbname = 'railway';
$user = 'root';
$password = 'BnMtNy1HCVWAn1ZCopQxIecExZPntYkn';

echo "Conectando a Railway MySQL...\n";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✓ Conexión exitosa\n";
    echo "Leyendo archivo SQL...\n";
    
    $sql = file_get_contents('backend/sistema_creditos2.sql');
    
    if ($sql === false) {
        die("✗ Error: No se pudo leer el archivo SQL\n");
    }
    
    echo "✓ Archivo leído (" . strlen($sql) . " bytes)\n";
    echo "Importando base de datos...\n";
    
    // Dividir por punto y coma y ejecutar cada statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $total = count($statements);
    $executed = 0;
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                $executed++;
                if ($executed % 10 == 0) {
                    echo "  Progreso: $executed/$total statements\n";
                }
            } catch (PDOException $e) {
                // Ignorar errores de "tabla ya existe" y similares
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "  Advertencia: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "✓ Importación completada: $executed statements ejecutados\n";
    echo "\n¡Base de datos importada exitosamente!\n";
    
} catch (PDOException $e) {
    die("✗ Error de conexión: " . $e->getMessage() . "\n");
}
