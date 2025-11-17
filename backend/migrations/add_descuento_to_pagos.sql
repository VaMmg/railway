-- Agregar columna descuento a la tabla pagos
-- Esta columna almacena el descuento aplicado cuando se hace un pago completo del crédito

ALTER TABLE pagos 
ADD COLUMN descuento DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Descuento aplicado en el pago (usado en pago completo)';

-- Verificar que se agregó correctamente
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'pagos' 
AND COLUMN_NAME = 'descuento';
