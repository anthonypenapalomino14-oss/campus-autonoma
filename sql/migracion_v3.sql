-- =====================================================================
--  Market Donna · Migración v3 (fotos de productos + colores de tiendas)
--  Ejecutar UNA sola vez (phpMyAdmin > base sistema_ventas > SQL).
--  Requiere haber corrido antes migracion_v2.sql.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE `productos`
  ADD COLUMN IF NOT EXISTS `imagen` varchar(255) DEFAULT NULL AFTER `descripcion`;

-- Colores de las tiendas acordes a la marca (solo si seguían los de fábrica)
UPDATE `sedes` SET `color` = '#16803c' WHERE `color` = '#0f766e';
UPDATE `sedes` SET `color` = '#f28c28' WHERE `color` = '#c2410c';
