<?php
/**
 * Script para respaldos automáticos de la base de datos
 * Este script debe ejecutarse mediante cron job cada día
 * Ejemplo de cron: 0 2 * * * /usr/bin/php /path/to/sistemaCredito/backend/scripts/auto_backup.php
 */

require_once '../config/database.php';

class AutoBackup {
    private $pdo;
    private $backupDir;
    
    public function __construct() {
        $database = new Database();
        $this->pdo = $database->getConnection();
        $this->backupDir = dirname(__DIR__) . '/backups/';
        
        // Crear directorio si no existe
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    public function run() {
        try {
            $this->log("Iniciando verificación de respaldo automático...");
            
            // Verificar si los respaldos automáticos están habilitados
            if (!$this->isAutoBackupEnabled()) {
                $this->log("Respaldos automáticos deshabilitados. Terminando.");
                return;
            }
            
            // Verificar si es necesario hacer respaldo
            if (!$this->shouldCreateBackup()) {
                $this->log("No es necesario crear respaldo en este momento.");
                return;
            }
            
            // Crear respaldo
            $this->createBackup();
            
        } catch (Exception $e) {
            $this->log("Error: " . $e->getMessage(), 'ERROR');
        }
    }
    
    private function isAutoBackupEnabled() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT valor FROM configuracion_sistema 
                WHERE clave = 'backup_auto_enabled'
            ");
            $stmt->execute();
            $enabled = $stmt->fetchColumn();
            
            return $enabled === 'true' || $enabled === '1';
        } catch (Exception $e) {
            $this->log("Error verificando configuración: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    private function shouldCreateBackup() {
        try {
            // Obtener intervalo de respaldo
            $stmt = $this->pdo->prepare("
                SELECT valor FROM configuracion_sistema 
                WHERE clave = 'backup_interval_days'
            ");
            $stmt->execute();
            $intervalDays = (int)($stmt->fetchColumn() ?: 3);
            
            // Obtener último respaldo exitoso
            $stmt = $this->pdo->prepare("
                SELECT fecha_creacion 
                FROM respaldos_bd 
                WHERE estado = 'success' 
                ORDER BY fecha_creacion DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $lastBackup = $stmt->fetchColumn();
            
            if (!$lastBackup) {
                $this->log("No hay respaldos previos. Creando primer respaldo.");
                return true;
            }
            
            $lastBackupDate = new DateTime($lastBackup);
            $now = new DateTime();
            $daysDiff = $now->diff($lastBackupDate)->days;
            
            $this->log("Último respaldo: {$lastBackup}, Días transcurridos: {$daysDiff}, Intervalo: {$intervalDays}");
            
            return $daysDiff >= $intervalDays;
            
        } catch (Exception $e) {
            $this->log("Error verificando necesidad de respaldo: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    private function createBackup() {
        $startTime = microtime(true);
        $backupId = null;
        
        try {
            $this->log("Iniciando creación de respaldo automático...");
            
            // Registrar inicio del respaldo
            $stmt = $this->pdo->prepare("
                INSERT INTO respaldos_bd (tipo, estado, observaciones) 
                VALUES ('automatic', 'in_progress', 'Respaldo automático iniciado')
            ");
            $stmt->execute();
            $backupId = $this->pdo->lastInsertId();
            
            // Generar nombre del archivo
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_sistemaCredito_auto_{$timestamp}.sql";
            $filepath = $this->backupDir . $filename;
            
            // Obtener configuración de la base de datos
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $dbname = $_ENV['DB_NAME'] ?? 'sistema_credito';
            $username = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASS'] ?? '';
            
            // Crear comando mysqldump
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers --lock-tables=false %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($dbname),
                escapeshellarg($filepath)
            );
            
            $this->log("Ejecutando mysqldump...");
            
            // Ejecutar el comando
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                // Respaldo exitoso
                $fileSize = round(filesize($filepath) / 1024 / 1024, 2); // MB
                
                $stmt = $this->pdo->prepare("
                    UPDATE respaldos_bd 
                    SET estado = 'success', 
                        tamano_mb = ?, 
                        duracion_segundos = ?, 
                        ruta_archivo = ?,
                        observaciones = 'Respaldo automático completado exitosamente'
                    WHERE id = ?
                ");
                $stmt->execute([$fileSize, $duration, $filepath, $backupId]);
                
                $this->log("Respaldo creado exitosamente: {$filename} ({$fileSize} MB, {$duration}s)");
                
                // Limpiar respaldos antiguos
                $this->cleanOldBackups();
                
            } else {
                // Error en el respaldo
                $errorMsg = implode('\n', $output);
                
                $stmt = $this->pdo->prepare("
                    UPDATE respaldos_bd 
                    SET estado = 'error', 
                        duracion_segundos = ?, 
                        observaciones = ?
                    WHERE id = ?
                ");
                $stmt->execute([$duration, "Error en mysqldump: " . $errorMsg, $backupId]);
                
                // Eliminar archivo parcial si existe
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                
                throw new Exception("Error ejecutando mysqldump (código: {$returnCode}): " . $errorMsg);
            }
            
        } catch (Exception $e) {
            $this->log("Error creando respaldo: " . $e->getMessage(), 'ERROR');
            
            // Actualizar estado a error si tenemos ID
            if ($backupId) {
                try {
                    $stmt = $this->pdo->prepare("
                        UPDATE respaldos_bd 
                        SET estado = 'error', 
                            observaciones = ?
                        WHERE id = ?
                    ");
                    $stmt->execute(["Error: " . $e->getMessage(), $backupId]);
                } catch (Exception $updateError) {
                    $this->log("Error actualizando estado: " . $updateError->getMessage(), 'ERROR');
                }
            }
            
            throw $e;
        }
    }
    
    private function cleanOldBackups() {
        try {
            $this->log("Limpiando respaldos antiguos...");
            
            // Obtener configuración de máximo de respaldos
            $stmt = $this->pdo->prepare("
                SELECT valor FROM configuracion_sistema 
                WHERE clave = 'backup_max_files'
            ");
            $stmt->execute();
            $maxBackups = (int)($stmt->fetchColumn() ?: 10);
            
            // Obtener respaldos antiguos
            $stmt = $this->pdo->prepare("
                SELECT id, ruta_archivo, fecha_creacion
                FROM respaldos_bd 
                WHERE estado = 'success' 
                ORDER BY fecha_creacion DESC 
                LIMIT 999 OFFSET ?
            ");
            $stmt->execute([$maxBackups]);
            $oldBackups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $deletedCount = 0;
            foreach ($oldBackups as $backup) {
                // Eliminar archivo físico
                if ($backup['ruta_archivo'] && file_exists($backup['ruta_archivo'])) {
                    if (unlink($backup['ruta_archivo'])) {
                        $this->log("Archivo eliminado: " . basename($backup['ruta_archivo']));
                    }
                }
                
                // Eliminar registro de la BD
                $deleteStmt = $this->pdo->prepare("DELETE FROM respaldos_bd WHERE id = ?");
                $deleteStmt->execute([$backup['id']]);
                $deletedCount++;
            }
            
            if ($deletedCount > 0) {
                $this->log("Eliminados {$deletedCount} respaldos antiguos");
            } else {
                $this->log("No hay respaldos antiguos para eliminar");
            }
            
        } catch (Exception $e) {
            $this->log("Error limpiando respaldos antiguos: " . $e->getMessage(), 'ERROR');
        }
    }
    
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        // Log a archivo
        $logFile = $this->backupDir . 'backup.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Log a consola si se ejecuta desde CLI
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
}

// Ejecutar solo si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $autoBackup = new AutoBackup();
    $autoBackup->run();
}
?>
