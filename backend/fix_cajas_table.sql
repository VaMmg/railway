-- Recrear la tabla cajas_usuario con la estructura correcta
DROP TABLE IF EXISTS `cajas_usuario`;

CREATE TABLE `cajas_usuario` (
  `id_cajas_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `limite_credito` decimal(10,2) DEFAULT 5000.00,
  `saldo_actual` decimal(10,2) DEFAULT 0.00,
  `saldo_final` decimal(10,2) DEFAULT 0.00,
  `estado_caja` enum('Abierta','Cerrada') DEFAULT 'Cerrada',
  `habilitada_por_gerente` tinyint(1) DEFAULT 0,
  `fecha_creacion_caja` date DEFAULT NULL,
  `hora_apertura` datetime DEFAULT NULL,
  `hora_cierre` datetime DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  PRIMARY KEY (`id_cajas_usuario`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_estado` (`estado_caja`),
  KEY `idx_fecha` (`fecha_creacion_caja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
