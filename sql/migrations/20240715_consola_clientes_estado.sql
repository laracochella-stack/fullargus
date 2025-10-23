ALTER TABLE `argus_clientes`
  ADD COLUMN IF NOT EXISTS `estado` varchar(20) NOT NULL DEFAULT 'activo' AFTER `beneficiario`;

UPDATE `argus_clientes`
  SET `estado` = 'activo'
  WHERE `estado` IS NULL OR `estado` = '';

CREATE TABLE IF NOT EXISTS `argus_admin_announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mensaje` text NOT NULL,
  `vigente_hasta` datetime DEFAULT NULL,
  `creado_por` int DEFAULT NULL,
  `activo` tinyint NOT NULL DEFAULT '1',
  `mostrar_en_dashboard` tinyint NOT NULL DEFAULT '1',
  `mostrar_en_popup` tinyint NOT NULL DEFAULT '0',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_announcements_activo` (`activo`),
  KEY `idx_admin_announcements_vigencia` (`vigente_hasta`),
  KEY `idx_admin_announcements_dashboard` (`mostrar_en_dashboard`),
  KEY `idx_admin_announcements_popup` (`mostrar_en_popup`),
  CONSTRAINT `fk_admin_announcements_usuario` FOREIGN KEY (`creado_por`) REFERENCES `argus_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_admin_announcements`
  ADD COLUMN IF NOT EXISTS `mensaje` text NOT NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `vigente_hasta` datetime DEFAULT NULL AFTER `mensaje`,
  ADD COLUMN IF NOT EXISTS `creado_por` int DEFAULT NULL AFTER `vigente_hasta`,
  ADD COLUMN IF NOT EXISTS `activo` tinyint NOT NULL DEFAULT '1' AFTER `creado_por`,
  ADD COLUMN IF NOT EXISTS `mostrar_en_dashboard` tinyint NOT NULL DEFAULT '1' AFTER `activo`,
  ADD COLUMN IF NOT EXISTS `mostrar_en_popup` tinyint NOT NULL DEFAULT '0' AFTER `mostrar_en_dashboard`,
  ADD COLUMN IF NOT EXISTS `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `mostrar_en_popup`,
  ADD COLUMN IF NOT EXISTS `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `creado_en`,
  ADD INDEX IF NOT EXISTS `idx_admin_announcements_dashboard` (`mostrar_en_dashboard`),
  ADD INDEX IF NOT EXISTS `idx_admin_announcements_popup` (`mostrar_en_popup`);
