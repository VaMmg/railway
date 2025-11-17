-- Actualizar tabla de reprogramaciones para incluir más información
ALTER TABLE reprogramaciones
ADD COLUMN IF NOT EXISTS nuevo_plazo_meses INT AFTER motivo,
ADD COLUMN IF NOT EXISTS nueva_tasa_interes DECIMAL(5,2) AFTER nuevo_plazo_meses,
ADD COLUMN IF NOT EXISTS nuevo_monto DECIMAL(12,2) AFTER nueva_tasa_interes,
ADD COLUMN IF NOT EXISTS nuevo_periodo_pago VARCHAR(50) AFTER nuevo_monto,
ADD COLUMN IF NOT EXISTS plazo_anterior INT AFTER nuevo_periodo_pago,
ADD COLUMN IF NOT EXISTS tasa_anterior DECIMAL(5,2) AFTER plazo_anterior,
ADD COLUMN IF NOT EXISTS monto_anterior DECIMAL(12,2) AFTER tasa_anterior,
ADD COLUMN IF NOT EXISTS periodo_pago_anterior VARCHAR(50) AFTER monto_anterior,
ADD COLUMN IF NOT EXISTS usuario_aprobacion INT AFTER usuario_registro,
ADD COLUMN IF NOT EXISTS fecha_aprobacion TIMESTAMP NULL AFTER usuario_aprobacion,
ADD COLUMN IF NOT EXISTS motivo_rechazo TEXT AFTER fecha_aprobacion,
ADD COLUMN IF NOT EXISTS observaciones TEXT AFTER motivo_rechazo;

-- Actualizar estatus si no existe
-- Posibles valores: 'Pendiente', 'Aprobada', 'Rechazada', 'Aplicada'
UPDATE reprogramaciones SET estatus = 'Pendiente' WHERE estatus IS NULL OR estatus = '';
