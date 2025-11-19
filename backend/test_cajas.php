<?php
// Script para diagnosticar problemas con cajas
require_once 'config/conexion.php';
require_once 'config/auth.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Test Cajas</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        pre { background: #252526; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<h2 class='info'>🔍 Diagnóstico de Cajas</h2>

<?php
try {
    $pdo = getPDO();
    
    // Test 1: Verificar tabla cajas (caja principal)
    echo "<h3 class='info'>1. Verificando tabla 'cajas' (Caja Principal):</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE cajas");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        foreach ($columns as $col) {
            echo "{$col['Field']} - {$col['Type']}\n";
        }
        echo "</pre>";
        
        // Intentar obtener caja principal
        echo "<p class='info'>Intentando obtener caja principal del día...</p>";
        $sql = "SELECT * FROM cajas WHERE DATE(fecha_creacion) = CURRENT_DATE ORDER BY hora_apertura DESC LIMIT 1";
        $stmt = $pdo->query($sql);
        $cajaPrincipal = $stmt->fetch();
        
        if ($cajaPrincipal) {
            echo "<p class='success'>✓ Caja principal encontrada:</p><pre>";
            print_r($cajaPrincipal);
            echo "</pre>";
        } else {
            echo "<p class='info'>ℹ No hay caja principal abierta hoy</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error con tabla cajas: " . $e->getMessage() . "</p>";
        echo "<p class='info'>La tabla 'cajas' probablemente no existe. Creándola...</p>";
        
        // Crear tabla cajas si no existe
        $createCajas = "
        CREATE TABLE IF NOT EXISTS `cajas` (
          `id_caja` int(11) NOT NULL AUTO_INCREMENT,
          `id_usuario_gerente` int(11) NOT NULL,
          `hora_apertura` datetime DEFAULT NULL,
          `hora_cierre` datetime DEFAULT NULL,
          `saldo_actual` decimal(10,2) DEFAULT 0.00,
          `saldo_final` decimal(10,2) DEFAULT 0.00,
          `estado_caja` enum('Abierta','Cerrada') DEFAULT 'Abierta',
          `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id_caja`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $pdo->exec($createCajas);
            echo "<p class='success'>✓ Tabla 'cajas' creada exitosamente</p>";
        } catch (PDOException $e2) {
            echo "<p class='error'>✗ Error al crear tabla: " . $e2->getMessage() . "</p>";
        }
    }
    
    // Test 2: Verificar tabla cajas_usuario
    echo "<h3 class='info'>2. Verificando tabla 'cajas_usuario':</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE cajas_usuario");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        foreach ($columns as $col) {
            echo "{$col['Field']} - {$col['Type']}\n";
        }
        echo "</pre>";
        
        // Contar cajas
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM cajas_usuario");
        $count = $stmt->fetch()['total'];
        echo "<p class='success'>✓ Total de cajas de usuarios: $count</p>";
        
        // Mostrar cajas
        if ($count > 0) {
            echo "<p class='info'>Cajas registradas:</p><pre>";
            $stmt = $pdo->query("SELECT * FROM cajas_usuario");
            $cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            print_r($cajas);
            echo "</pre>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error con tabla cajas_usuario: " . $e->getMessage() . "</p>";
    }
    
    // Test 3: Probar consulta de cajas para admin
    echo "<h3 class='info'>3. Probando consulta de cajas (vista admin):</h3>";
    try {
        $sql = "
            SELECT c.id_cajas_usuario, c.hora_apertura, c.saldo_actual, c.estado_caja,
                   c.hora_cierre, c.fecha_creacion_caja, c.limite_credito, c.habilitada_por_gerente,
                   c.comentario, c.saldo_final,
                   u.usuario, p.nombres, p.apellido_paterno, u.id_rol
            FROM cajas_usuario c
            INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
            INNER JOIN personas p ON u.dni_persona = p.dni
            INNER JOIN (
                SELECT id_usuario, MAX(id_cajas_usuario) as ultima_caja
                FROM cajas_usuario
                GROUP BY id_usuario
            ) ultima ON c.id_usuario = ultima.id_usuario AND c.id_cajas_usuario = ultima.ultima_caja
            ORDER BY c.hora_apertura DESC
            LIMIT 10
        ";
        
        $stmt = $pdo->query($sql);
        $cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p class='success'>✓ Consulta ejecutada exitosamente. Resultados: " . count($cajas) . "</p>";
        if (!empty($cajas)) {
            echo "<pre>";
            print_r($cajas);
            echo "</pre>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error en consulta: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3 class='success'>✅ Diagnóstico completado</h3>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Error general: " . $e->getMessage() . "</p>";
}
?>

<p><a href='/' style='color:#569cd6;'>← Volver</a></p>
</body>
</html>
