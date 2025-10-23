-- Actualiza la tabla de notificaciones para soportar anuncios generales.

-- Eliminar la restricción anterior si existe.
SET @fk_notifications := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'argus_notifications'
      AND CONSTRAINT_NAME = 'fk_notifications_solicitud'
);
SET @sql_drop_fk := IF(
    @fk_notifications IS NOT NULL,
    'ALTER TABLE `argus_notifications` DROP FOREIGN KEY `fk_notifications_solicitud`; ',
    'SELECT 1'
);
PREPARE stmt FROM @sql_drop_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajustar columnas e índices necesarios para anuncios.
ALTER TABLE `argus_notifications`
  MODIFY COLUMN `solicitud_id` int NULL,
  ADD COLUMN IF NOT EXISTS `tipo` varchar(50) NOT NULL DEFAULT 'solicitud' AFTER `mensaje`,
  ADD COLUMN IF NOT EXISTS `referencia_id` int NULL AFTER `tipo`,
  ADD COLUMN IF NOT EXISTS `url` varchar(255) NULL AFTER `referencia_id`,
  ADD KEY `idx_argus_notifications_tipo` (`tipo`),
  ADD KEY `idx_argus_notifications_referencia` (`referencia_id`);

-- Crear nuevamente la restricción con borrado en cascada a NULL.
ALTER TABLE `argus_notifications`
  ADD CONSTRAINT IF NOT EXISTS `fk_notifications_solicitud`
    FOREIGN KEY (`solicitud_id`) REFERENCES `argus_solicitudes` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;
