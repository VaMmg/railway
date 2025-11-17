-- Script para actualizar el estado de créditos que ya están completamente pagados
-- Esto es útil después de agregar la funcionalidad de descuentos

-- Actualizar créditos donde el saldo pendiente es 0 o negativo
UPDATE creditos c
SET estado_credito = 'Pagado'
WHERE c.id_credito IN (
    SELECT credito_id FROM (
        SELECT 
            c2.id_credito as credito_id,
            c2.monto_aprobado * (1 + (c2.tasa_interes / 100) * c2.plazos_meses) as monto_total,
            COALESCE(SUM(p.monto_pagado), 0) as total_pagado,
            COALESCE(SUM(p.descuento), 0) as total_descuentos,
            (c2.monto_aprobado * (1 + (c2.tasa_interes / 100) * c2.plazos_meses)) - 
            COALESCE(SUM(p.monto_pagado), 0) - 
            COALESCE(SUM(p.descuento), 0) as saldo_pendiente
        FROM creditos c2
        LEFT JOIN pagos p ON c2.id_credito = p.id_credito
        WHERE c2.estado_credito != 'Pagado'
        GROUP BY c2.id_credito
        HAVING saldo_pendiente <= 1
    ) AS creditos_pagados
);

-- Verificar los créditos actualizados
SELECT 
    c.id_credito,
    CONCAT(pe.nombres, ' ', pe.apellido_paterno) as cliente,
    c.estado_credito,
    c.monto_aprobado * (1 + (c.tasa_interes / 100) * c.plazos_meses) as monto_total,
    COALESCE(SUM(p.monto_pagado), 0) as total_pagado,
    COALESCE(SUM(p.descuento), 0) as total_descuentos,
    (c.monto_aprobado * (1 + (c.tasa_interes / 100) * c.plazos_meses)) - 
    COALESCE(SUM(p.monto_pagado), 0) - 
    COALESCE(SUM(p.descuento), 0) as saldo_pendiente
FROM creditos c
LEFT JOIN pagos p ON c.id_credito = p.id_credito
LEFT JOIN clientes cl ON c.id_cliente = cl.id_cliente
LEFT JOIN personas pe ON cl.dni_persona = pe.dni
WHERE c.estado_credito = 'Pagado'
GROUP BY c.id_credito
ORDER BY c.id_credito DESC;
