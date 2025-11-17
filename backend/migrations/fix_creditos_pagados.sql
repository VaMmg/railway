-- Script para corregir créditos marcados como "Pagado" pero con cuotas pendientes

-- 1. Ver el problema actual
SELECT 
    c.id_credito,
    c.estado_credito,
    COUNT(cu.id_cuota) as total_cuotas,
    SUM(CASE WHEN cu.estado = 'Pagada' THEN 1 ELSE 0 END) as cuotas_pagadas,
    SUM(CASE WHEN cu.estado != 'Pagada' THEN 1 ELSE 0 END) as cuotas_pendientes
FROM creditos c
LEFT JOIN cuotas cu ON c.id_credito = cu.id_credito
WHERE c.estado_credito IN ('Pagado', 'Cancelado', 'Finalizado')
GROUP BY c.id_credito, c.estado_credito
HAVING cuotas_pendientes > 0;

-- 2. Marcar todas las cuotas como pagadas para créditos con estado "Pagado"
UPDATE cuotas cu
INNER JOIN creditos c ON cu.id_credito = c.id_credito
SET 
    cu.estado = 'Pagada',
    cu.fecha_pago_real = COALESCE(cu.fecha_pago_real, cu.fecha_programada),
    cu.monto_pagado = COALESCE(cu.monto_pagado, cu.monto_total)
WHERE c.estado_credito IN ('Pagado', 'Cancelado', 'Finalizado')
AND cu.estado != 'Pagada';

-- 3. Verificar que se corrigió
SELECT 
    c.id_credito,
    c.estado_credito,
    COUNT(cu.id_cuota) as total_cuotas,
    SUM(CASE WHEN cu.estado = 'Pagada' THEN 1 ELSE 0 END) as cuotas_pagadas
FROM creditos c
LEFT JOIN cuotas cu ON c.id_credito = cu.id_credito
WHERE c.estado_credito IN ('Pagado', 'Cancelado', 'Finalizado')
GROUP BY c.id_credito, c.estado_credito;

-- 4. Alternativamente, cambiar el estado de créditos que no tienen todas las cuotas pagadas
-- (Descomentar si prefieres esta opción)
/*
UPDATE creditos c
SET c.estado_credito = 'Activo'
WHERE c.estado_credito IN ('Pagado', 'Cancelado', 'Finalizado')
AND EXISTS (
    SELECT 1 
    FROM cuotas cu 
    WHERE cu.id_credito = c.id_credito 
    AND cu.estado != 'Pagada'
);
*/
