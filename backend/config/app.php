<?php
return [
    'creditos' => [
        // Límite máximo de personas por rol
        'max_fiadores' => 3,
        'max_avales' => 3,
        // Longitud de DNI obligatoria
        'dni_length' => 8,
        
        // Límites de aprobación por rol (1=Admin, 2=Gerente, 3=Trabajador)
        'limites_aprobacion' => [
            1 => 100000,  // Admin - sin límite práctico
            2 => 50000,   // Gerente
            3 => 10000,   // Trabajador
        ],
        
        // Rangos de montos permitidos
        'monto_minimo' => 500,
        'monto_maximo' => 100000,
        
        // Rangos de plazos (en meses)
        'plazo_minimo' => 1,
        'plazo_maximo' => 60,
        
        // Rangos de tasas de interés (porcentaje)
        'tasa_minima' => 5.0,
        'tasa_maxima' => 35.0,
        
        // Días máximos para vencimiento de cuota
        'dias_gracia_vencimiento' => 30,
        
        // Límites de seguros por crédito
        'max_seguros' => 5,
        
        // Estados válidos para créditos
        'estados_validos' => ['Pendiente', 'Aprobado', 'Rechazado', 'Desembolsado', 'Cancelado'],
        
        // Tipos de cuota válidos
        'tipos_cuota' => ['Mensual', 'Quincenal', 'Semanal'],
    ],
    
    'sistema' => [
        // Configuración general del sistema
        'nombre_empresa' => 'Sistema de Créditos',
        'moneda' => 'PEN', // Soles peruanos
        'idioma_defecto' => 'es',
        'idiomas_disponibles' => ['es', 'en'],
        
        // Paginación
        'items_per_page' => 20,
        'max_items_per_page' => 100,
        
        // Validaciones
        'intentos_login_max' => 5,
        'tiempo_bloqueo_minutos' => 30,
    ],
];
