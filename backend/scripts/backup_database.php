<?php
/**
 * Script de Respaldo Automático de Base de Datos
 * Ejecutar diariamente al finalizar el día (ej: 23:59)
 */

// Configuración de la base de datos
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'sistema_creditos';

// Configuración de respaldo
$backupDir = __DIR__ . '/../copias_de_respaldo';
$maxBackups = 30; // Mantener últimos 30 días de respaldo

// Crear directorio de respaldos si no existe
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Nombre del archivo de respaldo con fecha y hora
$fecha = date('Y-m-d_H-i-s');
$backupFile = $backupDir . '/backup_' . $fecha . '.sql';

// Ruta de mysqldump (ajustar según instalación)
// Para XAMPP en Windows:
$mysqldumpPath = 'C:/xampp/mysql/bin/mysqldump';

// Si no existe, intentar con la ruta del sistema
if (!file_exists($mysqldumpPath)) {
    $mysqldumpPath = 'mysqldump'; // Usar del PATH del sistema
}

echo "===========================================\n";
echo "RESPALDO AUTOMÁTICO DE BASE DE DATOS\n";
echo "===========================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Base de datos: $dbName\n";
echo "Archivo: " . basename($backupFile) . "\n";
echo "-------------------------------------------\n";

try {
    // Construir comando de mysqldump
    $command = sprintf(
        '"%s" --user=%s --password=%s --host=%s --single-transaction --routines --triggers %s > "%s" 2>&1',
        $mysqldumpPath,
        $dbUser,
        $dbPass,
        $dbHost,
        $dbName,
        $backupFile
    );
    
    // Ejecutar respaldo
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
        $fileSize = filesize($backupFile);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);
        
        echo "[OK] Respaldo completado exitosamente\n";
        echo "[SIZE] Tamaño: $fileSizeMB MB\n";
        echo "[PATH] Ubicación: $backupFile\n";
        
        // Comprimir el archivo SQL para ahorrar espacio
        if (function_exists('gzopen')) {
            $gzFile = $backupFile . '.gz';
            $fp = fopen($backupFile, 'rb');
            $gz = gzopen($gzFile, 'wb9');
            
            while (!feof($fp)) {
                gzwrite($gz, fread($fp, 1024 * 512));
            }
            
            fclose($fp);
            gzclose($gz);
            
            // Eliminar archivo SQL sin comprimir
            unlink($backupFile);
            
            $gzSize = filesize($gzFile);
            $gzSizeMB = round($gzSize / 1024 / 1024, 2);
            $compression = round((1 - $gzSize / $fileSize) * 100, 1);
            
            echo "[ZIP] Comprimido: $gzSizeMB MB (ahorro: $compression%)\n";
        }
        
        // Limpiar respaldos antiguos
        cleanOldBackups($backupDir, $maxBackups);
        
        // Registrar en log
        logBackup($backupDir, $fecha, $fileSizeMB, true);
        
        echo "-------------------------------------------\n";
        echo "[OK] Proceso completado\n";
        echo "===========================================\n";
        
    } else {
        throw new Exception("Error al crear el respaldo. Código: $returnCode");
    }
    
} catch (Exception $e) {
    echo "[ERROR] ERROR: " . $e->getMessage() . "\n";
    
    if (!empty($output)) {
        echo "Detalles:\n";
        echo implode("\n", $output) . "\n";
    }
    
    // Registrar error en log
    logBackup($backupDir, $fecha, 0, false, $e->getMessage());
    
    echo "===========================================\n";
    exit(1);
}

/**
 * Limpiar respaldos antiguos
 */
function cleanOldBackups($dir, $maxBackups) {
    $files = glob($dir . '/backup_*.sql.gz');
    
    if (count($files) <= $maxBackups) {
        echo "[INFO] Respaldos actuales: " . count($files) . " (límite: $maxBackups)\n";
        return;
    }
    
    // Ordenar por fecha (más antiguos primero)
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    // Eliminar los más antiguos
    $toDelete = count($files) - $maxBackups;
    $deleted = 0;
    
    for ($i = 0; $i < $toDelete; $i++) {
        if (unlink($files[$i])) {
            $deleted++;
        }
    }
    
    echo "[CLEAN] Respaldos eliminados: $deleted (antiguos)\n";
    echo "[INFO] Respaldos actuales: " . ($maxBackups) . "\n";
}

/**
 * Registrar respaldo en log
 */
function logBackup($dir, $fecha, $sizeMB, $success, $error = null) {
    $logFile = $dir . '/backup_log.txt';
    $status = $success ? 'EXITOSO' : 'ERROR';
    $logEntry = sprintf(
        "[%s] %s - Tamaño: %s MB%s\n",
        $fecha,
        $status,
        $sizeMB,
        $error ? " - Error: $error" : ""
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
