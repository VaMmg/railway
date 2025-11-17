-- ============================================
-- SISTEMA DE CRÉDITOS - BASE DE DATOS
-- Versión: 2.0
-- Fecha: 2024
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- CREAR BASE DE DATOS
-- ============================================
CREATE DATABASE IF NOT EXISTS `sistema_creditos` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sistema_creditos`;

-- ============================================
-- TABLA: rol
-- Descripción: Roles de usuarios del sistema
-- ============================================
CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rol` (`id_rol`, `nombre_rol`, `descripcion`) VALUES
(1, 'Administrador', 'Acceso total al sistema'),
(2, 'Gerente', 'Gestión de créditos y aprobaciones'),
(3, 'Trabajador', 'Registro de operaciones diarias');

-- ============================================
-- TABLA: personas
-- Descripción: Información personal de todas las personas
-- ============================================
CREATE TABLE `personas` (
  `dni` varchar(20) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellido_paterno` varchar(100) NOT NULL,
  `apellido_materno` varchar(100) DEFAULT NULL,
  `sexo` enum('M','F') DEFAULT 'M',
  `fecha_nacimiento` date DEFAULT NULL,
  `nacionalidad` varchar(50) DEFAULT 'Peruana',
  `estado_civil` enum('Soltero','Casado','Divorciado','Viudo','Conviviente') DEFAULT 'Soltero',
  `nivel_educativo` varchar(50) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `id_correo` int(11) DEFAULT NULL,
  `id_contacto` int(11) DEFAULT NULL,
  `id_direccion` int(11) DEFAULT NULL,
  PRIMARY KEY (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: correos
-- Descripción: Correos electrónicos de personas
-- ============================================
CREATE TABLE `correos` (
  `id_correo` int(11) NOT NULL AUTO_INCREMENT,
  `dni_persona` varchar(20) NOT NULL,
  `cuantas_correos` int(11) DEFAULT 1,
  `correo1` varchar(100) DEFAULT NULL,
  `correo2` varchar(100) DEFAULT NULL,
  `correo3` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_correo`),
  KEY `dni_persona` (`dni_persona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: contacto
-- Descripción: Números de contacto de personas
-- ============================================
CREATE TABLE `contacto` (
  `id_contacto` int(11) NOT NULL AUTO_INCREMENT,
  `dni_persona` varchar(20) NOT NULL,
  `cantidad_numeros` int(11) DEFAULT 1,
  `numero1` varchar(20) DEFAULT NULL,
  `numero2` varchar(20) DEFAULT NULL,
  `numero3` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_contacto`),
  KEY `dni_persona` (`dni_persona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: direccion
-- Descripción: Direcciones de personas
-- ============================================
CREATE TABLE `direccion` (
  `id_direccion` int(11) NOT NULL AUTO_INCREMENT,
  `dni_persona` varchar(20) NOT NULL,
  `cuantas_direcciones` int(11) DEFAULT 1,
  `direccion1` text DEFAULT NULL,
  `direccion2` text DEFAULT NULL,
  `direccion3` text DEFAULT NULL,
  PRIMARY KEY (`id_direccion`),
  KEY `dni_persona` (`dni_persona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: usuarios
-- Descripción: Usuarios del sistema
-- ============================================
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `id_rol` int(11) NOT NULL,
  `dni_persona` varchar(20) NOT NULL,
  `usuario` varchar(50) NOT NULL UNIQUE,
  `pwd` varchar(255) NOT NULL,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` datetime DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  KEY `id_rol` (`id_rol`),
  KEY `dni_persona` (`dni_persona`),
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`),
  CONSTRAINT `fk_usuarios_persona` FOREIGN KEY (`dni_persona`) REFERENCES `personas` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- USUARIOS INICIALES DEL SISTEMA
-- ============================================

-- Primero insertar las personas en la tabla personas
INSERT INTO `personas` (`dni`, `nombres`, `apellido_paterno`, `apellido_materno`, `sexo`, `nacionalidad`, `estado_civil`) VALUES
('10000001', 'Administrador', 'Del', 'Sistema', 'M', 'Peruana', 'Soltero'),
('10000002', 'Gerente', 'De', 'Creditos', 'M', 'Peruana', 'Soltero');

-- Luego crear los usuarios del sistema
-- Usuario: admin | Contraseña: admin123
-- Usuario: gerente | Contraseña: gerente123
INSERT INTO `usuarios` (`id_rol`, `dni_persona`, `usuario`, `pwd`, `estado`) VALUES
(1, '10000001', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Activo'),
(2, '10000002', 'gerente', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Activo');

-- NOTA: Los usuarios trabajadores serán creados por el administrador desde el sistema

-- ============================================
-- TABLA: log_accesos
-- Descripción: Registro de accesos al sistema
-- ============================================
CREATE TABLE `log_accesos` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `ip_origen` varchar(50) DEFAULT NULL,
  `navegador` varchar(200) DEFAULT NULL,
  `fecha_acceso` datetime DEFAULT CURRENT_TIMESTAMP,
  `exitoso` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_log`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `fk_log_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: clientes
-- Descripción: Clientes del sistema de créditos
-- ============================================
CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `dni_persona` varchar(20) NOT NULL,
  `ingreso_mensual` decimal(10,2) DEFAULT 0.00,
  `gasto_mensual` decimal(10,2) DEFAULT 0.00,
  `ocupacion` varchar(100) DEFAULT NULL,
  `empresa_trabajo` varchar(150) DEFAULT NULL,
  `tiempo_trabajo` varchar(50) DEFAULT NULL,
  `referencias_personales` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `estado_cliente` enum('Activo','Inactivo','Moroso') DEFAULT 'Activo',
  PRIMARY KEY (`id_cliente`),
  KEY `dni_persona` (`dni_persona`),
  CONSTRAINT `fk_clientes_persona` FOREIGN KEY (`dni_persona`) REFERENCES `personas` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: creditos
-- Descripción: Créditos otorgados a clientes
-- ============================================
CREATE TABLE `creditos` (
  `id_credito` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `monto_original` decimal(10,2) NOT NULL,
  `monto_aprobado` decimal(10,2) NOT NULL,
  `periodo_pago` enum('Diario','Semanal','Quincenal','Mensual') DEFAULT 'Mensual',
  `plazos_meses` int(11) NOT NULL,
  `tasa_interes` decimal(5,2) NOT NULL COMMENT 'Tasa de interés mensual en porcentaje',
  `fecha_otorgamiento` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `mora` decimal(10,2) DEFAULT 0.00,
  `estado_credito` enum('Pendiente','Aprobado','Vigente','Pagado','Vencido','Rechazado') DEFAULT 'Pendiente',
  `usuario_creacion` int(11) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_credito`),
  KEY `id_cliente` (`id_cliente`),
  KEY `usuario_creacion` (`usuario_creacion`),
  CONSTRAINT `fk_creditos_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `fk_creditos_usuario` FOREIGN KEY (`usuario_creacion`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: cuotas
-- Descripción: Cronograma de pagos de créditos
-- ============================================
CREATE TABLE `cuotas` (
  `id_cuota` int(11) NOT NULL AUTO_INCREMENT,
  `id_credito` int(11) NOT NULL,
  `numero_cuota` int(11) NOT NULL,
  `fecha_programada` date NOT NULL,
  `monto_total` decimal(10,2) NOT NULL,
  `monto_capital` decimal(10,2) NOT NULL,
  `monto_interes` decimal(10,2) NOT NULL,
  `monto_mora` decimal(10,2) DEFAULT 0.00,
  `monto_pagado` decimal(10,2) DEFAULT 0.00,
  `fecha_pago_real` date DEFAULT NULL,
  `estado` enum('Pendiente','Parcial','Pagada','Vencida') DEFAULT 'Pendiente',
  PRIMARY KEY (`id_cuota`),
  KEY `id_credito` (`id_credito`),
  CONSTRAINT `fk_cuotas_credito` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id_credito`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: pagos
-- Descripción: Registro de pagos realizados
-- ============================================
CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_credito` int(11) NOT NULL,
  `fecha_pago` date NOT NULL,
  `monto_pagado` decimal(10,2) NOT NULL,
  `interes_pagado` decimal(10,2) DEFAULT 0.00,
  `capital_pagado` decimal(10,2) DEFAULT 0.00,
  `mora_pagada` decimal(10,2) DEFAULT 0.00,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `referencia_pago` varchar(100) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pago`),
  KEY `id_credito` (`id_credito`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `fk_pagos_credito` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id_credito`),
  CONSTRAINT `fk_pagos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: detalles_pago
-- Descripción: Detalle de aplicación de pagos a cuotas
-- ============================================
CREATE TABLE `detalles_pago` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_pago` int(11) NOT NULL,
  `id_cuota` int(11) DEFAULT NULL,
  `monto_aplicado` decimal(10,2) NOT NULL,
  `capital_aplicado` decimal(10,2) DEFAULT 0.00,
  `interes_aplicado` decimal(10,2) DEFAULT 0.00,
  `mora_aplicada` decimal(10,2) DEFAULT 0.00,
  `fecha_aplicacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_detalle`),
  KEY `id_pago` (`id_pago`),
  KEY `id_cuota` (`id_cuota`),
  CONSTRAINT `fk_detalles_pago` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`id_pago`) ON DELETE CASCADE,
  CONSTRAINT `fk_detalles_cuota` FOREIGN KEY (`id_cuota`) REFERENCES `cuotas` (`id_cuota`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: fiadores
-- Descripción: Fiadores de créditos
-- ============================================
CREATE TABLE `fiadores` (
  `id_fiador` int(11) NOT NULL AUTO_INCREMENT,
  `id_credito` int(11) NOT NULL,
  `id_persona` varchar(20) NOT NULL,
  `tipo_relacion` varchar(50) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_fiador`),
  KEY `id_credito` (`id_credito`),
  KEY `id_persona` (`id_persona`),
  CONSTRAINT `fk_fiadores_credito` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id_credito`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiadores_persona` FOREIGN KEY (`id_persona`) REFERENCES `personas` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: avales
-- Descripción: Avales de créditos
-- ============================================
CREATE TABLE `avales` (
  `id_aval` int(11) NOT NULL AUTO_INCREMENT,
  `id_credito` int(11) NOT NULL,
  `id_persona` varchar(20) NOT NULL,
  `tipo_relacion` varchar(50) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_aval`),
  KEY `id_credito` (`id_credito`),
  KEY `id_persona` (`id_persona`),
  CONSTRAINT `fk_avales_credito` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id_credito`) ON DELETE CASCADE,
  CONSTRAINT `fk_avales_persona` FOREIGN KEY (`id_persona`) REFERENCES `personas` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: seguros
-- Descripción: Catálogo de seguros disponibles
-- ============================================
CREATE TABLE `seguros` (
  `id_seguro` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_seguro` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `costo_base` decimal(10,2) DEFAULT 0.00,
  `tipo_seguro` varchar(50) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`id_seguro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: seguros_credito
-- Descripción: Seguros asignados a créditos
-- ============================================
CREATE TABLE `seguros_credito` (
  `id_seguro_credito` int(11) NOT NULL AUTO_INCREMENT,
  `id_credito` int(11) NOT NULL,
  `id_seguro` int(11) NOT NULL,
  `costo_asignado` decimal(10,2) NOT NULL,
  `fecha_contratacion` date DEFAULT NULL,
  PRIMARY KEY (`id_seguro_credito`),
  KEY `id_credito` (`id_credito`),
  KEY `id_seguro` (`id_seguro`),
  CONSTRAINT `fk_seguros_credito_credito` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id_credito`) ON DELETE CASCADE,
  CONSTRAINT `fk_seguros_credito_seguro` FOREIGN KEY (`id_seguro`) REFERENCES `seguros` (`id_seguro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: historial_crediticio
-- Descripción: Historial de eventos de créditos
-- ============================================
CREATE TABLE `historial_crediticio` (
  `id_historial` int(11) NOT NULL AUTO_INCREMENT,
  `id_credito` int(11) NOT NULL,
  `tipo_evento` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_evento` date NOT NULL,
  `usuario_registro` int(11) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_historial`),
  KEY `id_credito` (`id_credito`),
  KEY `usuario_registro` (`usuario_registro`),
  CONSTRAINT `fk_historial_credito` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id_credito`) ON DELETE CASCADE,
  CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`usuario_registro`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: notificaciones
-- Descripción: Sistema de notificaciones
-- ============================================
CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) NOT NULL,
  `mensaje` text NOT NULL,
  `fecha_envio` datetime DEFAULT CURRENT_TIMESTAMP,
  `destinatario_rol` int(11) DEFAULT NULL,
  `destinatario_usuario` int(11) DEFAULT NULL,
  `usuario_origen` int(11) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `referencia_tipo` varchar(50) DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_lectura` datetime DEFAULT NULL,
  PRIMARY KEY (`id_notificacion`),
  KEY `destinatario_rol` (`destinatario_rol`),
  KEY `destinatario_usuario` (`destinatario_usuario`),
  KEY `usuario_origen` (`usuario_origen`),
  CONSTRAINT `fk_notif_rol` FOREIGN KEY (`destinatario_rol`) REFERENCES `rol` (`id_rol`),
  CONSTRAINT `fk_notif_usuario_dest` FOREIGN KEY (`destinatario_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_notif_usuario_orig` FOREIGN KEY (`usuario_origen`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: tasas_interes
-- Descripción: Tasas de interés configurables
-- ============================================
CREATE TABLE `tasas_interes` (
  `id_tasa` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_tasa` varchar(100) NOT NULL,
  `porcentaje` decimal(5,2) NOT NULL,
  `tipo_tasa` enum('Mensual','Anual') DEFAULT 'Mensual',
  `descripcion` text DEFAULT NULL,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_tasa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: sucursal
-- Descripción: Sucursales de la empresa
-- ============================================
CREATE TABLE `sucursal` (
  `id_sucursal` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_sucursal` varchar(100) NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `ciudad` varchar(50) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: cajas_usuario
-- Descripción: Control de cajas por usuario
-- ============================================
CREATE TABLE `cajas_usuario` (
  `id_caja` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `monto_inicial` decimal(10,2) DEFAULT 0.00,
  `monto_final` decimal(10,2) DEFAULT 0.00,
  `estado_caja` enum('Abierta','Cerrada') DEFAULT 'Abierta',
  `fecha_creacion_caja` date DEFAULT NULL,
  `hora_apertura` time DEFAULT NULL,
  `hora_cierre` time DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_caja`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `fk_cajas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: bitacora_operaciones
-- Descripción: Registro de operaciones del sistema
-- ============================================
CREATE TABLE `bitacora_operaciones` (
  `id_bitacora` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_operacion` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_credito` int(11) DEFAULT NULL,
  `fecha_operacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_bitacora`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_credito` (`id_credito`),
  CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_bitacora_credito` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id_credito`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: comisiones
-- Descripción: Comisiones por operaciones
-- ============================================
CREATE TABLE `comisiones` (
  `id_comision` int(11) NOT NULL AUTO_INCREMENT,
  `id_credito` int(11) NOT NULL,
  `tipo_comision` varchar(50) NOT NULL,
  `monto_comision` decimal(10,2) NOT NULL,
  `porcentaje` decimal(5,2) DEFAULT NULL,
  `fecha_aplicacion` date DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_comision`),
  KEY `id_credito` (`id_credito`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `fk_comisiones_credito` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id_credito`),
  CONSTRAINT `fk_comisiones_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VISTAS ÚTILES
-- ============================================

-- Vista: Resumen de créditos por cliente
CREATE OR REPLACE VIEW `vista_creditos_cliente` AS
SELECT 
    c.id_cliente,
    CONCAT(p.nombres, ' ', p.apellido_paterno, ' ', IFNULL(p.apellido_materno, '')) AS nombre_completo,
    p.dni,
    COUNT(cr.id_credito) AS total_creditos,
    SUM(CASE WHEN cr.estado_credito = 'Vigente' THEN 1 ELSE 0 END) AS creditos_vigentes,
    SUM(CASE WHEN cr.estado_credito = 'Pagado' THEN 1 ELSE 0 END) AS creditos_pagados,
    SUM(CASE WHEN cr.estado_credito = 'Vencido' THEN 1 ELSE 0 END) AS creditos_vencidos,
    COALESCE(SUM(cr.monto_aprobado), 0) AS monto_total_creditos
FROM clientes c
INNER JOIN personas p ON c.dni_persona = p.dni
LEFT JOIN creditos cr ON c.id_cliente = cr.id_cliente
GROUP BY c.id_cliente, p.dni, p.nombres, p.apellido_paterno, p.apellido_materno;

-- Vista: Cuotas vencidas
CREATE OR REPLACE VIEW `vista_cuotas_vencidas` AS
SELECT 
    cu.id_cuota,
    cu.id_credito,
    cu.numero_cuota,
    cu.fecha_programada,
    cu.monto_total,
    cu.monto_pagado,
    (cu.monto_total - cu.monto_pagado) AS saldo_pendiente,
    DATEDIFF(CURDATE(), cu.fecha_programada) AS dias_vencidos,
    c.id_cliente,
    CONCAT(p.nombres, ' ', p.apellido_paterno) AS cliente_nombre,
    p.dni,
    con.numero1 AS telefono
FROM cuotas cu
INNER JOIN creditos c ON cu.id_credito = c.id_credito
INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
INNER JOIN personas p ON cl.dni_persona = p.dni
LEFT JOIN contacto con ON p.dni = con.dni_persona
WHERE cu.estado IN ('Vencida', 'Pendiente')
AND cu.fecha_programada < CURDATE()
ORDER BY cu.fecha_programada ASC;

COMMIT;

-- ============================================
-- FIN DEL SCRIPT
-- ============================================
