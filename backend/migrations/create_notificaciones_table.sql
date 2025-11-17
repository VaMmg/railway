-- Crear tabla de notificaciones persistentes
CREATE TABLE IF NOT EXISTS notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    mensaje TEXT NOT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    leida BOOLEAN DEFAULT FALSE,
    destinatario_usuario INT NULL,
    destinatario_rol INT NULL,
    usuario_origen INT NULL,
    referencia_id INT NULL,
    referencia_tipo VARCHAR(50) NULL,
    INDEX idx_destinatario_usuario (destinatario_usuario),
    INDEX idx_destinatario_rol (destinatario_rol),
    INDEX idx_leida (leida),
    INDEX idx_fecha (fecha_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agregar foreign keys si las tablas existen
ALTER TABLE notificaciones
ADD CONSTRAINT fk_notif_destinatario FOREIGN KEY (destinatario_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
ADD CONSTRAINT fk_notif_origen FOREIGN KEY (usuario_origen) REFERENCES usuarios(id_usuario) ON DELETE SET NULL;
