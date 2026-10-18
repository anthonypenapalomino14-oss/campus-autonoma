-- =====================================================================
--  VentaPro v2 · Migración (multi-sede, marcas, inventario, soporte)
--  Ejecutar UNA sola vez sobre la base `sistema_ventas` ya existente
--  (phpMyAdmin > base sistema_ventas > pestaña SQL > pegar > Continuar)
--  Haz un respaldo (Exportar) antes de correrlo.
-- =====================================================================

SET NAMES utf8mb4;

-- 1) SEDES (tiendas) ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `sedes` (
  `id_sede` tinyint(4) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `color` char(7) NOT NULL DEFAULT '#0f766e',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_sede`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solo 2 tiendas. Cambia los nombres/direcciones cuando quieras:
--   UPDATE sedes SET nombre='Tienda Centro', direccion='Av. ...' WHERE id_sede=1;
INSERT IGNORE INTO `sedes` (`id_sede`, `nombre`, `direccion`, `color`) VALUES
(1, 'Tienda 1', NULL, '#0f766e'),
(2, 'Tienda 2', NULL, '#c2410c');

-- 2) MARCAS ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `marcas` (
  `id_marca` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id_marca`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `productos`
  ADD COLUMN IF NOT EXISTS `id_marca` int(11) DEFAULT NULL AFTER `codigo`,
  ADD KEY IF NOT EXISTS `id_marca` (`id_marca`);

-- Marcas de los 3 productos que ya tienes
INSERT IGNORE INTO `marcas` (`nombre`) VALUES ('GLORIA'), ('Papios');
UPDATE `productos` SET `id_marca` = (SELECT `id_marca` FROM `marcas` WHERE `nombre`='GLORIA')
 WHERE `id_producto` IN (1,2) AND `id_marca` IS NULL;
UPDATE `productos` SET `id_marca` = (SELECT `id_marca` FROM `marcas` WHERE `nombre`='Papios')
 WHERE `id_producto` = 3 AND `id_marca` IS NULL;

ALTER TABLE `productos`
  ADD CONSTRAINT `productos_fk_marca` FOREIGN KEY (`id_marca`) REFERENCES `marcas` (`id_marca`);

-- 3) INVENTARIO POR SEDE ----------------------------------------------
--    (mismo producto, stock distinto en cada tienda)
CREATE TABLE IF NOT EXISTS `inventario` (
  `id_producto` int(11) NOT NULL,
  `id_sede` tinyint(4) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 5,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_producto`,`id_sede`),
  KEY `id_sede` (`id_sede`),
  CONSTRAINT `inventario_fk_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `inventario_fk_sede` FOREIGN KEY (`id_sede`) REFERENCES `sedes` (`id_sede`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Todo el stock actual pasa a la Tienda 1; la Tienda 2 parte en 0
-- (luego lo repartes con "Transferir" desde el módulo Inventario).
INSERT IGNORE INTO `inventario` (`id_producto`,`id_sede`,`stock`,`stock_minimo`)
  SELECT `id_producto`, 1, `stock`, 5 FROM `productos`;
INSERT IGNORE INTO `inventario` (`id_producto`,`id_sede`,`stock`,`stock_minimo`)
  SELECT `id_producto`, 2, 0, 5 FROM `productos`;

-- 4) KARDEX (historial de movimientos de stock) -----------------------
CREATE TABLE IF NOT EXISTS `movimientos_inventario` (
  `id_movimiento` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_sede` tinyint(4) NOT NULL,
  `tipo` enum('entrada','venta','ajuste','transferencia_salida','transferencia_entrada') NOT NULL,
  `cantidad` int(11) NOT NULL COMMENT 'positivo suma, negativo resta',
  `stock_resultante` int(11) NOT NULL,
  `motivo` varchar(200) DEFAULT NULL,
  `referencia` varchar(40) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_venta` bigint(20) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_movimiento`),
  KEY `id_producto` (`id_producto`),
  KEY `id_sede` (`id_sede`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_venta` (`id_venta`),
  CONSTRAINT `mov_fk_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `mov_fk_sede` FOREIGN KEY (`id_sede`) REFERENCES `sedes` (`id_sede`),
  CONSTRAINT `mov_fk_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `mov_fk_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `movimientos_inventario`
  (`id_producto`,`id_sede`,`tipo`,`cantidad`,`stock_resultante`,`motivo`,`creado_en`)
  SELECT p.`id_producto`, 1, 'entrada', p.`stock`, p.`stock`,
         'Saldo inicial (migración a multi-sede)', NOW()
  FROM `productos` p
  WHERE p.`stock` > 0
    AND NOT EXISTS (SELECT 1 FROM `movimientos_inventario` m WHERE m.`id_producto` = p.`id_producto`);

-- 5) USUARIOS: nuevo rol SOPORTE + sede asignada + activo -------------
ALTER TABLE `usuarios`
  MODIFY `rol` enum('cliente','vendedor','admin','soporte') NOT NULL DEFAULT 'cliente',
  ADD COLUMN IF NOT EXISTS `id_sede` tinyint(4) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `activo` tinyint(1) NOT NULL DEFAULT 1;

ALTER TABLE `usuarios`
  ADD KEY IF NOT EXISTS `id_sede` (`id_sede`),
  ADD CONSTRAINT `usuarios_fk_sede` FOREIGN KEY (`id_sede`) REFERENCES `sedes` (`id_sede`);

UPDATE `usuarios` SET `id_sede` = 1 WHERE `rol` = 'vendedor' AND `id_sede` IS NULL;

-- 6) VENTAS: en qué tienda se hizo cada venta -------------------------
ALTER TABLE `ventas`
  ADD COLUMN IF NOT EXISTS `id_sede` tinyint(4) NOT NULL DEFAULT 1 AFTER `id_vendedor`,
  ADD KEY IF NOT EXISTS `id_sede` (`id_sede`);

ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_fk_sede` FOREIGN KEY (`id_sede`) REFERENCES `sedes` (`id_sede`);

-- 7) SOPORTE: tickets de atención -------------------------------------
CREATE TABLE IF NOT EXISTS `tickets_soporte` (
  `id_ticket` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_venta` bigint(20) DEFAULT NULL,
  `asunto` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `estado` enum('abierto','en_proceso','resuelto') NOT NULL DEFAULT 'abierto',
  `respuesta` text DEFAULT NULL,
  `id_atendido_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_ticket`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_venta` (`id_venta`),
  KEY `id_atendido_por` (`id_atendido_por`),
  CONSTRAINT `tickets_fk_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `tickets_fk_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`),
  CONSTRAINT `tickets_fk_atiende` FOREIGN KEY (`id_atendido_por`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTA: `productos.stock` queda como "stock total" (suma de las sedes),
-- el sistema lo mantiene solo. La fuente real del stock es `inventario`.
