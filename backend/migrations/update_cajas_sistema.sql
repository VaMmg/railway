-- ============================================
-- ACTUALIZACIÓN DEL SISTEMA DE CAJAS
-- ============================================

-- 1. Primero agregar el campo id_usuario_gerente
ALTER TABLE cajas 
ADD COLUMN id_usuario_gerente INT AFTER id_sucursal;

-- 2. Agregar estado_caja al final de la tabla
ALTER TABLE cajas 
ADD COLUMN estado_caja VARCHAR(50) DEFAULT 'Cerrada';

-- 3. Agregar fecha_creacion
ALTER TABLE cajas 
ADD COLUMN fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- 4. Agregar campo de habilitación por gerente en cajas_usuario
ALTER TABLE cajas_usuario 
ADD COLUMN habilitada_por_gerente BOOLEAN DEFAULT FALSE;

-- 5. Actualizar cajas existentes
UPDATE cajas SET estado_caja = 'Cerrada' WHERE estado_caja IS NULL OR estado_caja = '';
UPDATE cajas_usuario SET habilitada_por_gerente = FALSE WHERE habilitada_por_gerente IS NULL;

-- ============================================
-- LÓGICA DEL SISTEMA:
-- ============================================
-- 1. El GERENTE abre la CAJA PRINCIPAL (tabla: cajas)
-- 2. Solo con caja principal abierta, el gerente puede HABILITAR cajas de trabajadores
-- 3. Los TRABAJADORES pueden ACTIVAR su caja solo si:
--    a) La caja principal está abierta
--    b) Su caja está habilitada por el gerente
-- 4. Si la caja del trabajador no está activa, NO puede usar el sistema
-- ============================================
