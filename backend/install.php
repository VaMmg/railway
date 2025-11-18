<?php
// Instalador simple para Railway
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Instalador - Sistema de Créditos</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
    <h1>🚀 Instalador del Sistema de Créditos</h1>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<h2>Instalando base de datos completa...</h2>";
        echo "<p class='info'>Esto puede tomar 2-3 minutos, por favor espera...</p>";
        flush();
        
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        
        require_once __DIR__ . '/config/conexion.php';
        
        try {
            $pdo = getPDO();
            echo "<p class='success'>✓ Conectado a la base de datos</p>";
            flush();
            
            // Leer el archivo SQL completo
            $sqlFile = __DIR__ . '/sistema_creditos2.sql';
            
            if (!file_exists($sqlFile)) {
                throw new Exception("No se encontró el archivo sistema_creditos2.sql");
            }
            
            echo "<p class='info'>Leyendo archivo SQL...</p>";
            flush();
            
            $sql = file_get_contents($sqlFile);
            echo "<p class='success'>✓ Archivo leído (" . number_format(strlen($sql)) . " bytes)</p>";
            flush();
            
            echo "<p class='info'>Ejecutando SQL (esto puede tomar tiempo)...</p>";
            flush();
            
            // Dividir por punto y coma y ejecutar
            $statements = explode(';', $sql);
            $total = count($statements);
            $executed = 0;
            $errors = 0;
            
            foreach ($statements as $index => $statement) {
                $statement = trim($statement);
                
                if (empty($statement) || substr($statement, 0, 2) === '--' || substr($statement, 0, 2) === '/*') {
                    continue;
                }
                
                try {
                    $pdo->exec($statement);
                    $executed++;
                    
                    if ($executed % 200 == 0) {
                        echo "<p class='info'>Progreso: $executed statements ejecutados...</p>";
                        flush();
                    }
                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    if (strpos($msg, 'already exists') === false && 
                        strpos($msg, 'Duplicate') === false) {
                        $errors++;
                    }
                }
            }
            
            echo "<p class='success'>✓ SQL ejecutado: $executed statements</p>";
            if ($errors > 0) {
                echo "<p class='info'>Advertencias ignoradas: $errors</p>";
            }
            flush();
            
            // Verificar tablas
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "<p class='success'>✓ Tablas creadas: " . count($tables) . "</p>";
            
            if (count($tables) > 0) {
                echo "<h3>Primeras 10 tablas:</h3><ul>";
                foreach (array_slice($tables, 0, 10) as $table) {
                    try {
                        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                        echo "<li>$table: $count registros</li>";
                    } catch (Exception $e) {
                        echo "<li>$table</li>";
                    }
                }
                echo "</ul>";
                
                if (count($tables) > 10) {
                    echo "<p>... y " . (count($tables) - 10) . " tablas más</p>";
                }
            }
            
            echo "<p class='success'><strong>¡Base de datos importada exitosamente!</strong></p>";
            echo "<p>Ahora puedes <a href='https://triumphant-laughter-production-up.railway.app' target='_blank'>iniciar sesión en tu aplicación</a></p>";
            echo "<p class='info'>Usa las credenciales que tenías en tu sistema local.</p>";
            
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
    } else {
        ?>
        <p>Este instalador creará las tablas básicas y un usuario administrador para tu sistema.</p>
        <p><strong>Nota:</strong> Solo ejecuta esto una vez. Si ya instalaste, no es necesario volver a ejecutar.</p>
        
        <form method="POST">
            <button type="submit">Instalar Base de Datos</button>
        </form>
        <?php
    }
    ?>
</body>
</html>
