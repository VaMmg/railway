-- ============================================
-- CORRECCIÓN DE TIMESTAMPS EN TABLA CAJAS
-- ============================================

-- Primero, modificar las columnas TIMESTAMP para permitir NULL
ALTER TABLE cajas 
MODIFY COLUMN hora_apertura TIMESTAMP NULL DEFAULT NULL,
MODIFY COLUMN hora_cierre TIMESTAMP NULL DEFAULT NULL;

-- Ahora agregar los nuevos campos
ALTER TABLE cajas 
ADD COLUMN IF NOT EXISTS id_usuario_gerente INT AFTER id_sucursal,
ADD COLUMN IF NOT EXISTS estado_caja VARCHAR(50) DEFAULT 'Cerrada' AFTER hora_cierre,
ADD COLUMN IF NOT EXISTS fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER estado_caja;

-- Modificar tabla cajas_usuario también
ALTER TABLE cajas_usuario 
MODIFY COLUMN hora_apertura TIMESTAMP NULL DEFAULT NULL,
MODIFY COLUMN hora_cierre TIMESTAMP NULL DEFAULT NULL,
MODIFY COLUMN fecha_creacion_caja TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

-- Agregar campo de habilitación por gerente
ALTER TABLE cajas_usuario 
ADD COLUMN IF NOT EXISTS habilitada_por_gerente BOOLEAN DEFAULT FALSE AFTER estado_caja;

-- Actualizar registros existentes
UPDATE cajas SET estado_caja = 'Cerrada' WHERE estado_caja IS NULL OR estado_caja = '';
UPDATE cajas_usuario SET habilitada_por_gerente = FALSE WHERE habilitada_por_gerente IS NULL;

-- ============================================
-- VERIFICACIÓN
-- ============================================
-- Ejecuta esto para verificar que todo está correcto:
-- DESCRIBE cajas;
-- DESCRIBE cajas_usuario;
