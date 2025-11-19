<?php
// Script para crear la tabla reprogramaciones
require_once 'config/conexion.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Crear Tabla Reprogramaciones</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        pre { background: #252526; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<h2 class='info'>🔧 Creando tabla reprogramaciones</h2>

<?php
try {
    $pdo = getPDO();
    
    echo "<p class='info'>➤ Verificando si la tabla existe...</p>";
    
    // Verificar si la tabla existe
    try {
        $pdo->query("SELECT 1 FROM reprogramaciones LIMIT 1");
        echo "<p class='success'>✓ La tabla 'reprogramaciones' ya existe</p>";
    } catch (PDOException $e) {
        echo "<p class='info'>➤ La tabla no existe. Creándola...</p>";
        
        // Crear la tabla
        $createTableSQL = "
        CREATE TABLE `reprogramaciones` (
          `id_reprogramacion` int(11) NOT NULL AUTO_INCREMENT,
          `id_credito` int(11) NOT NULL,
          `motivo` text NOT NULL,
          `nuevo_plazo_meses` int(11) DEFAULT NULL,
          `nueva_tasa_interes` decimal(5,2) DEFAULT NULL,
          `nuevo_monto` decimal(10,2) DEFAULT NULL,
          `nuevo_periodo_pago` enum('Diario','Semanal','Quincenal','Mensual') DEFAULT NULL,
          `plazo_anterior` int(11) DEFAULT NULL,
          `tasa_anterior` decimal(5,2) DEFAULT NULL,
          `monto_anterior` decimal(10,2) DEFAULT NULL,
          `periodo_pago_anterior` enum('Diario','Semanal','Quincenal','Mensual') DEFAULT NULL,
          `usuario_registro` int(11) DEFAULT NULL,
          `usuario_aprobacion` int(11) DEFAULT NULL,
          `estatus` enum('Pendiente','Aprobada','Rechazada','Aplicada') DEFAULT 'Pendiente',
          `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
          `fecha_aprobacion` datetime DEFAULT NULL,
          `observaciones` text DEFAULT NULL,
          PRIMARY KEY (`id_reprogramacion`),
          KEY `idx_credito` (`id_credito`),
          KEY `idx_estatus` (`estatus`),
          KEY `idx_fecha` (`fecha_creacion`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($createTableSQL);
        echo "<p class='success'>✓ Tabla 'reprogramaciones' creada exitosamente</p>";
    }
    
    // Mostrar estructura
    echo "<h3 class='info'>Estructura de la tabla:</h3><pre>";
    $stmt = $pdo->query("DESCRIBE reprogramaciones");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "{$col['Field']} ({$col['Type']})\n";
    }
    echo "</pre>";
    
    echo "<h3 class='success'>✅ Proceso completado</h3>";
    echo "<p>La tabla 'reprogramaciones' está lista para usar.</p>";
    echo "<p><a href='/' style='color:#569cd6;'>← Volver al sistema</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 class='error'>❌ Error:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
</body>
</html>
