-- Tabla para almacenar información de respaldos de base de datos
CREATE TABLE IF NOT EXISTS respaldos_bd (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo ENUM('manual', 'automatic') NOT NULL DEFAULT 'manual',
    estado ENUM('success', 'error', 'in_progress') NOT NULL DEFAULT 'in_progress',
    tamano_mb DECIMAL(10,2) NULL,
    duracion_segundos DECIMAL(10,2) NULL,
    ruta_archivo VARCHAR(500) NULL,
    observaciones TEXT NULL,
    usuario_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_fecha_creacion (fecha_creacion),
    INDEX idx_tipo (tipo),
    INDEX idx_estado (estado)
);

-- Tabla para configuración del sistema (si no existe)
CREATE TABLE IF NOT EXISTS configuracion_sistema (
    id INT PRIMARY KEY AUTO_INCREMENT,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT NOT NULL,
    descripcion VARCHAR(255) NULL,
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_clave (clave)
);

-- Insertar configuración por defecto para respaldos
INSERT INTO configuracion_sistema (clave, valor, descripcion, tipo) VALUES
('backup_auto_enabled', 'true', 'Habilitar respaldos automáticos', 'boolean'),
('backup_interval_days', '3', 'Intervalo en días para respaldos automáticos', 'number'),
('backup_max_files', '10', 'Máximo número de archivos de respaldo a mantener', 'number'),
('backup_compression', 'true', 'Habilitar compresión en respaldos', 'boolean'),
('backup_time', '02:00', 'Hora para ejecutar respaldos automáticos (HH:MM)', 'string')
ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion),
    tipo = VALUES(tipo);

-- Crear directorio para respaldos (esto debe hacerse manualmente en el servidor)
-- mkdir -p /path/to/sistemaCredito/backend/backups
-- chmod 755 /path/to/sistemaCredito/backend/backups

-- Ejemplo de datos de prueba para respaldos (opcional)
INSERT INTO respaldos_bd (fecha_creacion, tipo, estado, tamano_mb, duracion_segundos, ruta_archivo, observaciones) VALUES
(DATE_SUB(NOW(), INTERVAL 3 DAY), 'automatic', 'success', 15.2, 2.3, '../backups/backup_sistemaCredito_2024-10-17_02-00-00.sql', 'Respaldo automático exitoso'),
(DATE_SUB(NOW(), INTERVAL 6 DAY), 'manual', 'success', 14.8, 1.9, '../backups/backup_sistemaCredito_2024-10-14_14-30-15.sql', 'Respaldo manual antes de actualización'),
(DATE_SUB(NOW(), INTERVAL 9 DAY), 'automatic', 'success', 14.5, 2.1, '../backups/backup_sistemaCredito_2024-10-11_02-00-00.sql', 'Respaldo automático exitoso');

-- Crear evento para respaldos automáticos (requiere permisos de EVENT)
-- DELIMITER $$
-- CREATE EVENT IF NOT EXISTS evento_respaldo_automatico
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CONCAT(CURDATE() + INTERVAL 1 DAY, ' 02:00:00')
-- DO
-- BEGIN
--     DECLARE backup_enabled BOOLEAN DEFAULT FALSE;
--     DECLARE backup_interval INT DEFAULT 3;
--     DECLARE last_backup_date DATETIME;
--     DECLARE should_backup BOOLEAN DEFAULT FALSE;
    
--     -- Verificar si los respaldos automáticos están habilitados
--     SELECT CAST(valor AS UNSIGNED) INTO backup_enabled 
--     FROM configuracion_sistema 
--     WHERE clave = 'backup_auto_enabled';
    
--     IF backup_enabled THEN
--         -- Obtener intervalo de respaldo
--         SELECT CAST(valor AS UNSIGNED) INTO backup_interval 
--         FROM configuracion_sistema 
--         WHERE clave = 'backup_interval_days';
        
--         -- Obtener fecha del último respaldo exitoso
--         SELECT MAX(fecha_creacion) INTO last_backup_date 
--         FROM respaldos_bd 
--         WHERE estado = 'success';
        
--         -- Verificar si es tiempo de hacer respaldo
--         IF last_backup_date IS NULL OR DATEDIFF(NOW(), last_backup_date) >= backup_interval THEN
--             -- Aquí se ejecutaría el script de respaldo
--             -- Por ahora solo registramos la intención
--             INSERT INTO respaldos_bd (tipo, estado, observaciones) 
--             VALUES ('automatic', 'in_progress', 'Respaldo automático iniciado por evento programado');
--         END IF;
--     END IF;
-- END$$
-- DELIMITER ;
