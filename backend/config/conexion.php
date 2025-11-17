<?php
// Configuración universal para MySQL y PostgreSQL

// Permitir configuración vía variables de entorno (para Docker)
$env = fn($key, $default = null) => getenv($key) !== false ? getenv($key) : $default;

// Configuración para MySQL
$mysql_config = [
    'host' => $env('DB_HOST', 'localhost'),
    'port' => $env('DB_PORT', '3306'),
    'dbname' => $env('DB_NAME', 'sistema_creditos'),
    'user' => $env('DB_USER', 'root'),
    'password' => $env('DB_PASSWORD', '')
];

// Configuración para PostgreSQL
$pgsql_config = [
    'host' => $env('PG_HOST', 'localhost'),
    'port' => $env('PG_PORT', '5432'),
    'dbname' => $env('PG_NAME', 'sistema_creditos'),
    'user' => $env('PG_USER', 'postgres'),
    'password' => $env('PG_PASSWORD', 'admin')
];

// Variable global para el tipo de BD actualmente en uso
$current_db_type = null;

/**
 * Detecta qué drivers de base de datos están disponibles
 */
function getAvailableDrivers() {
    $available = [];
    
    if (extension_loaded('pdo_mysql')) {
        $available[] = 'mysql';
    }
    
    if (extension_loaded('pdo_pgsql')) {
        $available[] = 'pgsql';
    }
    
    return $available;
}

/**
 * Intenta conectar a la base de datos disponible
 */
function getPDO() {
    global $mysql_config, $pgsql_config, $current_db_type;
    
    $availableDrivers = getAvailableDrivers();
    
    if (empty($availableDrivers)) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'No hay drivers de base de datos disponibles. Instale php-pdo-mysql o php-pdo-pgsql'
        ]);
        exit;
    }
    
    // Orden de prioridad: MySQL primero (para XAMPP), luego PostgreSQL
    $tryOrder = [];
    if (in_array('mysql', $availableDrivers)) {
        $tryOrder[] = 'mysql';
    }
    if (in_array('pgsql', $availableDrivers)) {
        $tryOrder[] = 'pgsql';
    }
    
    $lastError = '';
    
    foreach ($tryOrder as $dbType) {
        try {
            if ($dbType === 'mysql') {
                $cfg = $mysql_config;
                $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset=utf8mb4";
            } else {
                $cfg = $pgsql_config;
                $dsn = "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']}";
            }
            
            $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            // Configurar zona horaria según el tipo de base de datos
            if ($dbType === 'mysql') {
                // Establecer zona horaria de Perú (UTC-5) para MySQL
                $pdo->exec("SET time_zone = '-05:00'");
            } else if ($dbType === 'pgsql') {
                // Establecer zona horaria de Perú para PostgreSQL
                $pdo->exec("SET TIME ZONE 'America/Lima'");
            }
            
            // Si llegamos aquí, la conexión fue exitosa
            $current_db_type = $dbType;
            return $pdo;
            
        } catch (PDOException $e) {
            $lastError = $e->getMessage();
            continue; // Intentar con el siguiente driver
        }
    }
    
    // Si llegamos aquí, no pudimos conectar con ninguna BD
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => "No se pudo conectar a ninguna base de datos. Último error: $lastError",
        'available_drivers' => $availableDrivers
    ]);
    exit;
}

/**
 * Obtiene el tipo de base de datos actualmente en uso
 */
function getCurrentDbType() {
    global $current_db_type;
    return $current_db_type;
}
