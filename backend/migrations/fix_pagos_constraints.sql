-- Script para verificar y arreglar restricciones de clave foránea en la tabla pagos
-- Esto permite eliminar pagos sin problemas

-- Ver las restricciones actuales de la tabla pagos
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM 
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND (TABLE_NAME = 'pagos' OR REFERENCED_TABLE_NAME = 'pagos');

-- Si hay restricciones que impiden eliminar pagos, ejecutar:
-- ALTER TABLE detalles_pago DROP FOREIGN KEY nombre_de_la_constraint;
-- ALTER TABLE detalles_pago ADD CONSTRAINT fk_detalles_pago_pagos 
--     FOREIGN KEY (id_pago) REFERENCES pagos(id_pago) ON DELETE CASCADE;

-- Verificar que la tabla pagos existe y tiene datos
SELECT COUNT(*) as total_pagos FROM pagos;

-- Ver los últimos 5 pagos
SELECT id_pago, id_credito, monto_pagado, fecha_pago 
FROM pagos 
ORDER BY id_pago DESC 
LIMIT 5;
