<?php
// BACKEND/api/diagnostico.php - Diagnóstico del sistema
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/sql_adapter.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    jsonResponse(false, 'Solo método GET soportado', null, 405);
}

try {
    $diagnostico = [];
    
    // 1. Verificar extensiones PHP
    $diagnostico['php'] = [
        'version' => PHP_VERSION,
        'extensions' => [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'pdo_pgsql' => extension_loaded('pdo_pgsql'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring')
        ]
    ];
    
    // 2. Verificar drivers disponibles
    $availableDrivers = getAvailableDrivers();
    $diagnostico['drivers_disponibles'] = $availableDrivers;
    
    // 3. Intentar conexiones
    $diagnostico['conexiones'] = [];
    
    // Verificar MySQL
    if (in_array('mysql', $availableDrivers)) {
        try {
            global $mysql_config;
            $dsn = "mysql:host={$mysql_config['host']};port={$mysql_config['port']};dbname={$mysql_config['dbname']};charset=utf8mb4";
            $pdo_test = new PDO($dsn, $mysql_config['user'], $mysql_config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            $stmt = $pdo_test->query("SELECT VERSION() as version, DATABASE() as database");
            $info = $stmt->fetch();
            
            $diagnostico['conexiones']['mysql'] = [
                'status' => 'OK',
                'version' => $info['version'],
                'database' => $info['database'],
                'host' => $mysql_config['host'],
                'port' => $mysql_config['port']
            ];
        } catch (PDOException $e) {
            $diagnostico['conexiones']['mysql'] = [
                'status' => 'ERROR',
                'error' => $e->getMessage(),
                'host' => $mysql_config['host'],
                'port' => $mysql_config['port']
            ];
        }
    }
    
    // Verificar PostgreSQL
    if (in_array('pgsql', $availableDrivers)) {
        try {
            global $pgsql_config;
            $dsn = "pgsql:host={$pgsql_config['host']};port={$pgsql_config['port']};dbname={$pgsql_config['dbname']}";
            $pdo_test = new PDO($dsn, $pgsql_config['user'], $pgsql_config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            $stmt = $pdo_test->query("SELECT version() as version, current_database() as database");
            $info = $stmt->fetch();
            
            $diagnostico['conexiones']['postgresql'] = [
                'status' => 'OK',
                'version' => $info['version'],
                'database' => $info['database'],
                'host' => $pgsql_config['host'],
                'port' => $pgsql_config['port']
            ];
        } catch (PDOException $e) {
            $diagnostico['conexiones']['postgresql'] = [
                'status' => 'ERROR',
                'error' => $e->getMessage(),
                'host' => $pgsql_config['host'],
                'port' => $pgsql_config['port']
            ];
        }
    }
    
    // 4. Verificar conexión actual del sistema
    try {
        $pdo = getPDO();
        $dbType = getCurrentDbType();
        
        $diagnostico['conexion_actual'] = [
            'status' => 'OK',
            'tipo' => $dbType,
            'adaptador_activo' => true
        ];
        
        // Probar una consulta simple
        if ($dbType === 'mysql') {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()");
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_catalog = current_database() AND table_schema = 'public'");
        }
        
        $result = $stmt->fetch();
        $diagnostico['conexion_actual']['tablas_detectadas'] = (int)$result['count'];
        
        // Verificar tablas principales
        $tablas_principales = ['usuarios', 'personas', 'clientes', 'creditos', 'pagos', 'rol'];
        $tablas_existentes = [];
        
        foreach ($tablas_principales as $tabla) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $tabla LIMIT 1");
                $tablas_existentes[$tabla] = true;
            } catch (PDOException $e) {
                $tablas_existentes[$tabla] = false;
            }
        }
        
        $diagnostico['conexion_actual']['tablas_principales'] = $tablas_existentes;
        
    } catch (Exception $e) {
        $diagnostico['conexion_actual'] = [
            'status' => 'ERROR',
            'error' => $e->getMessage()
        ];
    }
    
    // 5. Verificar adaptador SQL
    try {
        $testQuery = "SELECT CURRENT_DATE as fecha, COUNT(*) as total FROM (SELECT 1 as dummy) t WHERE 1=1";
        $adaptedQuery = adaptSQL($testQuery);
        $diagnostico['adaptador_sql'] = [
            'status' => 'OK',
            'query_original' => $testQuery,
            'query_adaptada' => $adaptedQuery,
            'tipo_bd_detectado' => getDbType()
        ];
    } catch (Exception $e) {
        $diagnostico['adaptador_sql'] = [
            'status' => 'ERROR',
            'error' => $e->getMessage()
        ];
    }
    
    // 6. Recomendaciones
    $recomendaciones = [];
    
    if (!extension_loaded('pdo_mysql') && !extension_loaded('pdo_pgsql')) {
        $recomendaciones[] = 'CRÍTICO: Instale al menos un driver de base de datos (php-pdo-mysql o php-pdo-pgsql)';
    }
    
    if (!extension_loaded('pdo_mysql')) {
        $recomendaciones[] = 'Para usar MySQL, instale la extensión php-pdo-mysql';
    }
    
    if (!extension_loaded('pdo_pgsql')) {
        $recomendaciones[] = 'Para usar PostgreSQL, instale la extensión php-pdo-pgsql';
    }
    
    if (isset($diagnostico['conexion_actual']['status']) && $diagnostico['conexion_actual']['status'] === 'ERROR') {
        $recomendaciones[] = 'CRÍTICO: No se pudo establecer conexión con la base de datos. Verifique las credenciales en backend/config/conexion.php';
    }
    
    if (isset($diagnostico['conexion_actual']['tablas_detectadas']) && $diagnostico['conexion_actual']['tablas_detectadas'] === 0) {
        $recomendaciones[] = 'La base de datos existe pero no tiene tablas. Ejecute el script backend/sistema_creditos_universal.sql';
    }
    
    $diagnostico['recomendaciones'] = $recomendaciones;
    $diagnostico['timestamp'] = date('Y-m-d H:i:s');
    
    jsonResponse(true, 'Diagnóstico completado', $diagnostico);
    
} catch (Exception $e) {
    jsonResponse(false, 'Error en diagnóstico: ' . $e->getMessage(), null, 500);
}
?>