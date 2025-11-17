-- Crear tabla rol si no existe
CREATE TABLE IF NOT EXISTS rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar roles básicos si no existen
INSERT IGNORE INTO rol (id_rol, nombre_rol, descripcion) VALUES
(1, 'Administrador', 'Acceso completo al sistema'),
(2, 'Gerente', 'Gestión de créditos, aprobaciones y reprogramaciones'),
(3, 'Trabajador', 'Operaciones básicas de créditos y pagos');

-- Verificar que la tabla usuarios tenga la columna id_rol
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS id_rol INT,
ADD CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES rol(id_rol);
