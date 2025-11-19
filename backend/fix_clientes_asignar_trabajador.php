<?php
// Script para asignar clientes huérfanos a trabajadores
require_once 'config/conexion.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Asignar Clientes a Trabajadores</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        .warning { color: #dcdcaa; }
        pre { background: #252526; padding: 10px; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #444; padding: 8px; text-align: left; }
        th { background: #252526; }
        .btn { padding: 5px 10px; margin: 2px; cursor: pointer; border: none; border-radius: 3px; }
        .btn-primary { background: #0e639c; color: white; }
        .btn-success { background: #107c10; color: white; }
    </style>
</head>
<body>
<h2 class='info'>🔧 Asignar Clientes Huérfanos a Trabajadores</h2>

<?php
try {
    $pdo = getPDO();
    
    // Obtener clientes sin usuario_creacion
    echo "<h3 class='info'>📋 Clientes sin trabajador asignado:</h3>";
    $clientesSql = "
        SELECT c.id_cliente, c.dni_persona, c.fecha_registro,
               p.nombres, p.apellido_paterno, p.apellido_materno
        FROM clientes c
        INNER JOIN personas p ON c.dni_persona = p.dni
        WHERE c.usuario_creacion IS NULL
        ORDER BY c.fecha_registro DESC
    ";
    $clientesStmt = $pdo->query($clientesSql);
    $clientesHuerfanos = $clientesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($clientesHuerfanos)) {
        echo "<p class='success'>✓ No hay clientes sin asignar. Todos los clientes tienen un trabajador asociado.</p>";
    } else {
        echo "<p class='warning'>⚠ Se encontraron " . count($clientesHuerfanos) . " cliente(s) sin trabajador asignado:</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Cliente</th><th>DNI</th><th>Fecha Registro</th></tr>";
        foreach ($clientesHuerfanos as $cliente) {
            echo "<tr>";
            echo "<td>{$cliente['id_cliente']}</td>";
            echo "<td>{$cliente['nombres']} {$cliente['apellido_paterno']} {$cliente['apellido_materno']}</td>";
            echo "<td>{$cliente['dni_persona']}</td>";
            echo "<td>{$cliente['fecha_registro']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Obtener trabajadores disponibles
    echo "<h3 class='info'>👥 Trabajadores disponibles:</h3>";
    $trabajadoresSql = "
        SELECT u.id_usuario, u.usuario, p.nombres, p.apellido_paterno,
               (SELECT COUNT(*) FROM clientes WHERE usuario_creacion = u.id_usuario) as total_clientes
        FROM usuarios u
        INNER JOIN personas p ON u.dni_persona = p.dni
        INNER JOIN rol r ON u.id_rol = r.id_rol
        WHERE r.nombre_rol LIKE '%Trabajador%'
        ORDER BY total_clientes ASC, p.nombres ASC
    ";
    $trabajadoresStmt = $pdo->query($trabajadoresSql);
    $trabajadores = $trabajadoresStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($trabajadores)) {
        echo "<p class='error'>✗ No hay trabajadores registrados en el sistema.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Trabajador</th><th>Usuario</th><th>Clientes Actuales</th></tr>";
        foreach ($trabajadores as $trabajador) {
            echo "<tr>";
            echo "<td>{$trabajador['id_usuario']}</td>";
            echo "<td>{$trabajador['nombres']} {$trabajador['apellido_paterno']}</td>";
            echo "<td>@{$trabajador['usuario']}</td>";
            echo "<td>{$trabajador['total_clientes']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Si hay clientes huérfanos y trabajadores, ofrecer asignación automática
    if (!empty($clientesHuerfanos) && !empty($trabajadores)) {
        echo "<h3 class='warning'>🤖 Asignación Automática</h3>";
        
        if (isset($_GET['asignar']) && $_GET['asignar'] === 'auto') {
            echo "<p class='info'>➤ Asignando clientes al trabajador con menos clientes...</p>";
            
            // Obtener el trabajador con menos clientes
            $trabajadorMenosClientes = $trabajadores[0];
            $idTrabajador = $trabajadorMenosClientes['id_usuario'];
            
            // Asignar todos los clientes huérfanos a este trabajador
            $updateSql = "UPDATE clientes SET usuario_creacion = :id_trabajador WHERE usuario_creacion IS NULL";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([':id_trabajador' => $idTrabajador]);
            
            $clientesAsignados = $updateStmt->rowCount();
            
            echo "<p class='success'>✓ Se asignaron {$clientesAsignados} cliente(s) a:</p>";
            echo "<p class='success'>   👤 {$trabajadorMenosClientes['nombres']} {$trabajadorMenosClientes['apellido_paterno']} (@{$trabajadorMenosClientes['usuario']})</p>";
            
            echo "<h3 class='success'>✅ Proceso completado</h3>";
            echo "<p>Ahora todos los clientes tienen un trabajador asignado.</p>";
            echo "<p><a href='?' style='color:#569cd6;'>🔄 Verificar nuevamente</a> | ";
            echo "<a href='/' style='color:#569cd6;'>← Volver al sistema</a></p>";
            
        } else {
            echo "<p>Se asignarán automáticamente todos los clientes huérfanos al trabajador con menos clientes.</p>";
            echo "<p><a href='?asignar=auto' class='btn btn-success'>✓ Asignar Automáticamente</a></p>";
        }
    } else if (empty($clientesHuerfanos)) {
        echo "<h3 class='success'>✅ Todo está correcto</h3>";
        echo "<p>Todos los clientes tienen un trabajador asignado.</p>";
        echo "<p><a href='/' style='color:#569cd6;'>← Volver al sistema</a></p>";
    }
    
} catch (PDOException $e) {
    echo "<h2 class='error'>❌ Error:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>

</body>
</html>
