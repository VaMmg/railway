<?php
// Adaptador SQL universal para MySQL y PostgreSQL

require_once 'conexion.php';

class SQLAdapter {
    private $dbType;
    
    public function __construct() {
        try {
            // Detectar el tipo de BD automáticamente
            $pdo = getPDO(); // Esto establecerá $current_db_type
            $this->dbType = getCurrentDbType();
        } catch (Exception $e) {
            // Si no se puede conectar, asumir MySQL por defecto
            $this->dbType = 'mysql';
        }
    }
    
    /**
     * Adapta una consulta SQL según la base de datos
     */
    public function adaptQuery($query) {
        if ($this->dbType === 'mysql') {
            return $this->adaptForMySQL($query);
        } else {
            return $this->adaptForPostgreSQL($query);
        }
    }
    
    /**
     * Adapta consulta para MySQL
     */
    private function adaptForMySQL($query) {
        // Convertir funciones de PostgreSQL a MySQL
        $replacements = [
            // Fechas - más específico
            'CURRENT_DATE' => 'CURDATE()',
            'EXTRACT(MONTH FROM fecha_pago) = EXTRACT(MONTH FROM CURRENT_DATE)' => 'MONTH(fecha_pago) = MONTH(CURDATE())',
            'EXTRACT(YEAR FROM fecha_pago) = EXTRACT(YEAR FROM CURRENT_DATE)' => 'YEAR(fecha_pago) = YEAR(CURDATE())',
            'EXTRACT(MONTH FROM' => 'MONTH(',
            'EXTRACT(YEAR FROM' => 'YEAR(',
            
            // Intervalos - más específico
            "CURRENT_DATE - INTERVAL '30 days'" => "DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            "CURRENT_DATE - INTERVAL '12 months'" => "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
            "CURRENT_DATE + INTERVAL '7 days'" => "DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
            "(CURRENT_DATE + INTERVAL '7 days')" => "DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
            "BETWEEN CURRENT_DATE AND (CURRENT_DATE + INTERVAL '7 days')" => "BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
            
            // Formateo de fechas
            "TO_CHAR(fecha_otorgamiento, 'YYYY-MM-01')" => "DATE_FORMAT(fecha_otorgamiento, '%Y-%m-01')",
            "TO_CHAR(fecha_pago, 'YYYY-MM-01')" => "DATE_FORMAT(fecha_pago, '%Y-%m-01')",
            
            // Tipos de datos
            'SERIAL PRIMARY KEY' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'BOOLEAN' => 'TINYINT(1)',
            'TEXT' => 'TEXT',
            'TIMESTAMP' => 'TIMESTAMP',
            
            // Casting
            '::date' => '',
            '::timestamp' => ''
        ];
        
        foreach ($replacements as $from => $to) {
            $query = str_replace($from, $to, $query);
        }
        
        return $query;
    }
    
    /**
     * Adapta consulta para PostgreSQL
     */
    private function adaptForPostgreSQL($query) {
        // Convertir funciones de MySQL a PostgreSQL
        $replacements = [
            // Fechas
            'CURDATE()' => 'CURRENT_DATE',
            'DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)' => "CURRENT_DATE - INTERVAL '30 days'",
            'DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)' => "CURRENT_DATE - INTERVAL '12 months'",
            'DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)' => "CURRENT_DATE + INTERVAL '7 days'",
            
            // Formateo de fechas
            "DATE_FORMAT(fecha_otorgamiento, '%Y-%m-01')" => "TO_CHAR(fecha_otorgamiento, 'YYYY-MM-01')",
            "DATE_FORMAT(fecha_pago, '%Y-%m-01')" => "TO_CHAR(fecha_pago, 'YYYY-MM-01')",
            
            // Funciones
            'MONTH(' => 'EXTRACT(MONTH FROM ',
            'YEAR(' => 'EXTRACT(YEAR FROM ',
            
            // Tipos de datos
            'INT AUTO_INCREMENT PRIMARY KEY' => 'SERIAL PRIMARY KEY',
            'TINYINT(1)' => 'BOOLEAN'
        ];
        
        foreach ($replacements as $from => $to) {
            $query = str_replace($from, $to, $query);
        }
        
        return $query;
    }
    
    /**
     * Obtiene la función de concatenación según la BD
     */
    public function concat($fields) {
        if ($this->dbType === 'mysql') {
            return 'CONCAT(' . implode(', ', $fields) . ')';
        } else {
            return implode(' || ', $fields);
        }
    }
    
    /**
     * Obtiene el límite de consulta según la BD
     */
    public function limit($limit, $offset = 0) {
        if ($this->dbType === 'mysql') {
            return $offset > 0 ? "LIMIT $offset, $limit" : "LIMIT $limit";
        } else {
            return $offset > 0 ? "LIMIT $limit OFFSET $offset" : "LIMIT $limit";
        }
    }
    
    /**
     * Obtiene la función de auto incremento según la BD
     */
    public function getAutoIncrement() {
        return $this->dbType === 'mysql' ? 'AUTO_INCREMENT' : 'SERIAL';
    }
    
    /**
     * Obtiene el tipo de dato booleano según la BD
     */
    public function getBooleanType() {
        return $this->dbType === 'mysql' ? 'TINYINT(1)' : 'BOOLEAN';
    }
    
    /**
     * Adapta INSERT con ON CONFLICT/ON DUPLICATE KEY
     */
    public function adaptUpsert($table, $fields, $values, $conflictField) {
        if ($this->dbType === 'mysql') {
            $fieldsList = implode(', ', $fields);
            $updateList = [];
            foreach ($fields as $field) {
                if ($field !== $conflictField) {
                    $updateList[] = "$field = VALUES($field)";
                }
            }
            $updateClause = implode(', ', $updateList);
            return "INSERT INTO $table ($fieldsList) VALUES $values ON DUPLICATE KEY UPDATE $updateClause";
        } else {
            $fieldsList = implode(', ', $fields);
            $excludeList = [];
            foreach ($fields as $field) {
                if ($field !== $conflictField) {
                    $excludeList[] = "$field = EXCLUDED.$field";
                }
            }
            $updateClause = implode(', ', $excludeList);
            return "INSERT INTO $table ($fieldsList) VALUES $values ON CONFLICT ($conflictField) DO UPDATE SET $updateClause";
        }
    }
    
    /**
     * Obtiene el tipo de base de datos actual
     */
    public function getDbType() {
        return $this->dbType;
    }
}

// Instancia global del adaptador
$sqlAdapter = null;

/**
 * Función helper para adaptar consultas
 */
function adaptSQL($query) {
    global $sqlAdapter;
    if ($sqlAdapter === null) {
        $sqlAdapter = new SQLAdapter();
    }
    return $sqlAdapter->adaptQuery($query);
}

/**
 * Función helper para obtener el tipo de BD
 */
function getDbType() {
    global $sqlAdapter;
    if ($sqlAdapter === null) {
        $sqlAdapter = new SQLAdapter();
    }
    return $sqlAdapter->getDbType();
}
