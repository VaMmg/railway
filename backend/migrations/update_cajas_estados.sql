-- Agregar campo para control de habilitación por gerente
ALTER TABLE cajas_usuario 
ADD COLUMN habilitada_por_gerente BOOLEAN DEFAULT FALSE AFTER estado_caja;

-- Actualizar las cajas existentes
UPDATE cajas_usuario 
SET habilitada_por_gerente = TRUE 
WHERE estado_caja = 'Abierta';

-- Comentarios sobre los estados:
-- habilitada_por_gerente: TRUE = El gerente ha habilitado esta caja para que el trabajador pueda usarla
-- estado_caja: 'Abierta' = El trabajador ha activado su caja y puede trabajar
-- estado_caja: 'Cerrada' = El trabajador ha cerrado su caja o aún no la ha activado
