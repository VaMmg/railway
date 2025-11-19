<?php
// Script para diagnosticar el API de clientes
require_once 'config/conexion.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Test Clientes API</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        pre { background: #252526; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<h2 class='info'>🔍 Diagnóstico API de Clientes</h2>

<?php
try {
    $pdo = getPDO();
    
    // Test 1: Consulta directa a la base de datos
    echo "<h3 class='info'>1. Clientes en la base de datos:</h3>";
    $sql = "
        SELECT c.id_cliente, c.dni_persona, c.estado_cliente, c.usuario_creacion,
               p.nombres, p.apellido_paterno, p.apellido_materno
        FROM clientes c
        INNER JOIN personas p ON c.dni_persona = p.dni
        ORDER BY c.id_cliente DESC
    ";
    $stmt = $pdo->query($sql);
    $clientesDB = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    echo "Total clientes: " . count($clientesDB) . "\n\n";
    foreach ($clientesDB as $cliente) {
        echo "ID: {$cliente['id_cliente']}\n";
        echo "Nombre: {$cliente['nombres']} {$cliente['apellido_paterno']}\n";
        echo "DNI: {$cliente['dni_persona']}\n";
        echo "Estado: {$cliente['estado_cliente']}\n";
        echo "Usuario Creación: {$cliente['usuario_creacion']}\n";
        echo "---\n";
    }
    echo "</pre>";
    
    // Test 2: Simular la consulta del API
    echo "<h3 class='info'>2. Simulando consulta del API (con paginación):</h3>";
    $page = 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $sql = "
        SELECT c.id_cliente, c.ingreso_mensual, c.gasto_mensual, c.ocupacion, 
               c.empresa_trabajo, c.tiempo_trabajo, c.estado_cliente, c.fecha_registro,
               c.usuario_creacion,
               p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
               co.correo1 AS correo_principal, con.numero1 AS numero_principal
        FROM clientes c
        INNER JOIN personas p ON c.dni_persona = p.dni
        LEFT JOIN correos co ON p.dni = co.dni_persona
        LEFT JOIN contacto con ON p.dni = con.dni_persona
        ORDER BY c.fecha_registro DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $clientesAPI = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    echo "Total clientes (API): " . count($clientesAPI) . "\n\n";
    echo json_encode($clientesAPI, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    
    // Test 3: Filtrar solo activos
    echo "<h3 class='info'>3. Clientes ACTIVOS (filtrados):</h3>";
    $clientesActivos = array_filter($clientesAPI, function($c) {
        return $c['estado_cliente'] === 'Activo';
    });
    
    echo "<pre>";
    echo "Total clientes activos: " . count($clientesActivos) . "\n\n";
    if (empty($clientesActivos)) {
        echo "⚠ NO HAY CLIENTES ACTIVOS\n";
        echo "Esto explica por qué no aparecen en el dropdown.\n";
    } else {
        foreach ($clientesActivos as $cliente) {
            echo "✓ {$cliente['nombres']} {$cliente['apellido_paterno']} (DNI: {$cliente['dni']})\n";
        }
    }
    echo "</pre>";
    
    echo "<h3 class='success'>✅ Diagnóstico completado</h3>";
    echo "<p><a href='/' style='color:#569cd6;'>← Volver</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 class='error'>❌ Error:</h2>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
</body>
</html>
