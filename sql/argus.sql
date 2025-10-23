-- Argus MVC schema and sample data (idempotent)
SET NAMES utf8mb4;
SET time_zone = '+00:00';

START TRANSACTION;

-- --------------------------------------------------------
-- Usuarios del sistema
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `argus_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `permission` varchar(50) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL DEFAULT '',
  `nombre_corto` varchar(80) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `notificaciones_activas` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_argus_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_users`
  ADD COLUMN IF NOT EXISTS `nombre_completo` varchar(150) NOT NULL DEFAULT '' AFTER `permission`,
  ADD COLUMN IF NOT EXISTS `nombre_corto` varchar(80) DEFAULT NULL AFTER `nombre_completo`,
  ADD COLUMN IF NOT EXISTS `email` varchar(150) DEFAULT NULL AFTER `nombre_corto`,
  ADD COLUMN IF NOT EXISTS `notificaciones_activas` tinyint(1) NOT NULL DEFAULT 0 AFTER `email`,
  ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `notificaciones_activas`;

ALTER TABLE `argus_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

INSERT INTO `argus_users` (`id`, `username`, `password`, `permission`, `nombre_completo`, `nombre_corto`, `email`, `notificaciones_activas`)
VALUES
  (1, 'admin', '$2y$10$yJTe2YD/AmWGxNWNwmr25OZIJI0rcV5rUkjrECBfw4gFoJz2rYWPS', 'admin', 'Administrador General', 'ADMIN', 'admin@example.com', 1),
  (2, 'abelomas', '$2y$10$yJTe2YD/AmWGxNWNwmr25OZIJI0rcV5rUkjrECBfw4gFoJz2rYWPS', 'user', 'Usuario Invitado', 'ABELOMAS', 'abelomas@example.com', 0)
ON DUPLICATE KEY UPDATE
  `permission` = VALUES(`permission`),
  `nombre_completo` = VALUES(`nombre_completo`),
  `nombre_corto` = VALUES(`nombre_corto`),
  `email` = VALUES(`email`),
  `notificaciones_activas` = VALUES(`notificaciones_activas`);

-- --------------------------------------------------------
-- Variables parametrizables (nacionalidades, tipos de contrato, etc.)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `argus_variables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(100) NOT NULL,
  `identificador` varchar(150) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_argus_variables_tipo_identificador` (`tipo`, `identificador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_variables`
  ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `argus_variables`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

INSERT INTO `argus_variables` (`id`, `tipo`, `identificador`, `nombre`)
VALUES
  (1, 'tipo_contrato', 'tradicional', 'Plan Tradicional'),
  (2, 'nacionalidad', 'MEX', 'Mexicana'),
  (3, 'nacionalidad', 'USA', 'Estadounidense')
ON DUPLICATE KEY UPDATE
  `nombre` = VALUES(`nombre`);

-- --------------------------------------------------------
-- Desarrollos inmobiliarios
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `argus_desarrollos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `tipo_contrato` varchar(150) NOT NULL,
  `descripcion` text NOT NULL,
  `superficie` varchar(100) NOT NULL,
  `clave_catastral` varchar(100) NOT NULL,
  `lotes_disponibles` text NOT NULL,
  `precio_lote` decimal(15,2) NOT NULL DEFAULT 0.00,
  `precio_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_desarrollos`
  ADD COLUMN IF NOT EXISTS `descripcion` text NOT NULL AFTER `tipo_contrato`,
  ADD COLUMN IF NOT EXISTS `superficie` varchar(100) NOT NULL DEFAULT '' AFTER `descripcion`,
  ADD COLUMN IF NOT EXISTS `clave_catastral` varchar(100) NOT NULL DEFAULT '' AFTER `superficie`,
  ADD COLUMN IF NOT EXISTS `lotes_disponibles` text NOT NULL AFTER `clave_catastral`,
  ADD COLUMN IF NOT EXISTS `precio_lote` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `lotes_disponibles`,
  ADD COLUMN IF NOT EXISTS `precio_total` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `precio_lote`,
  ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `precio_total`;

ALTER TABLE `argus_desarrollos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

INSERT INTO `argus_desarrollos` (`id`, `nombre`, `tipo_contrato`, `descripcion`, `superficie`, `clave_catastral`, `lotes_disponibles`, `precio_lote`, `precio_total`)
VALUES
  (1, 'Villas del Bosque', 'tradicional', 'Desarrollo residencial de prueba.', '120', 'CAT-0001', '["L1","L2","L3"]', 350000.00, 1050000.00)
ON DUPLICATE KEY UPDATE
  `nombre` = VALUES(`nombre`),
  `tipo_contrato` = VALUES(`tipo_contrato`),
  `descripcion` = VALUES(`descripcion`),
  `superficie` = VALUES(`superficie`),
  `clave_catastral` = VALUES(`clave_catastral`),
  `lotes_disponibles` = VALUES(`lotes_disponibles`),
  `precio_lote` = VALUES(`precio_lote`),
  `precio_total` = VALUES(`precio_total`);

-- --------------------------------------------------------
-- Clientes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `argus_clientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `nacionalidad` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `rfc` varchar(13) NOT NULL,
  `curp` varchar(20) NOT NULL,
  `ine` varchar(50) NOT NULL,
  `estado_civil` varchar(120) NOT NULL,
  `ocupacion` varchar(120) NOT NULL,
  `telefono` varchar(30) NOT NULL,
  `domicilio` varchar(255) NOT NULL,
  `email` varchar(120) NOT NULL,
  `beneficiario` varchar(150) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_argus_clientes_rfc` (`rfc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_clientes`
  ADD COLUMN IF NOT EXISTS `nacionalidad` varchar(100) NOT NULL DEFAULT '' AFTER `nombre`,
  ADD COLUMN IF NOT EXISTS `fecha_nacimiento` date NOT NULL DEFAULT '1970-01-01' AFTER `nacionalidad`,
  ADD COLUMN IF NOT EXISTS `curp` varchar(20) NOT NULL DEFAULT '' AFTER `rfc`,
  ADD COLUMN IF NOT EXISTS `ine` varchar(50) NOT NULL DEFAULT '' AFTER `curp`,
  ADD COLUMN IF NOT EXISTS `estado_civil` varchar(120) NOT NULL DEFAULT '' AFTER `ine`,
  ADD COLUMN IF NOT EXISTS `ocupacion` varchar(120) NOT NULL DEFAULT '' AFTER `estado_civil`,
  ADD COLUMN IF NOT EXISTS `telefono` varchar(30) NOT NULL DEFAULT '' AFTER `ocupacion`,
  ADD COLUMN IF NOT EXISTS `domicilio` varchar(255) NOT NULL DEFAULT '' AFTER `telefono`,
  ADD COLUMN IF NOT EXISTS `email` varchar(120) NOT NULL DEFAULT '' AFTER `domicilio`,
  ADD COLUMN IF NOT EXISTS `beneficiario` varchar(150) NOT NULL DEFAULT '' AFTER `email`,
  ADD COLUMN IF NOT EXISTS `estado` varchar(20) NOT NULL DEFAULT 'activo' AFTER `beneficiario`,
  ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `beneficiario`;

ALTER TABLE `argus_clientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

INSERT INTO `argus_clientes` (`id`, `nombre`, `nacionalidad`, `fecha_nacimiento`, `rfc`, `curp`, `ine`, `estado_civil`, `ocupacion`, `telefono`, `domicilio`, `email`, `beneficiario`, `estado`)
VALUES
  (1, 'CLIENTE DEMO', 'Mexicana', '1990-05-12', 'DEM900512A12', 'DEMJ900512HDFRRL01', '1234567890123', 'SOLTERO', 'INGENIERO', '+5215512345678', 'CALLE FICTICIA 123, CDMX', 'cliente.demo@example.com', 'ANA DEMO (HERMANA)', 'activo')
ON DUPLICATE KEY UPDATE
  `nombre` = VALUES(`nombre`),
  `nacionalidad` = VALUES(`nacionalidad`),
  `fecha_nacimiento` = VALUES(`fecha_nacimiento`),
  `estado_civil` = VALUES(`estado_civil`),
  `ocupacion` = VALUES(`ocupacion`),
  `telefono` = VALUES(`telefono`),
  `domicilio` = VALUES(`domicilio`),
  `email` = VALUES(`email`),
  `beneficiario` = VALUES(`beneficiario`),
  `estado` = VALUES(`estado`);

-- --------------------------------------------------------
-- Consola administrativa
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- Solicitudes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `argus_solicitudes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folio` varchar(50) DEFAULT NULL,
  `solicitud_datta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'borrador',
  `usuario_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `motivo_retorno` text DEFAULT NULL,
  `devuelto_por` int DEFAULT NULL,
  `devuelto_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_argus_solicitudes_folio` (`folio`),
  KEY `idx_argus_solicitudes_usuario` (`usuario_id`),
  KEY `idx_argus_solicitudes_estado` (`estado`),
  CONSTRAINT `chk_argus_solicitudes_json` CHECK (JSON_VALID(`solicitud_datta`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_solicitudes`
  ADD COLUMN IF NOT EXISTS `motivo_retorno` text DEFAULT NULL AFTER `updated_at`,
  ADD COLUMN IF NOT EXISTS `devuelto_por` int DEFAULT NULL AFTER `motivo_retorno`,
  ADD COLUMN IF NOT EXISTS `devuelto_en` timestamp NULL DEFAULT NULL AFTER `devuelto_por`;

ALTER TABLE `argus_solicitudes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `argus_solicitudes`
  DROP FOREIGN KEY IF EXISTS `fk_solicitudes_usuario`;

ALTER TABLE `argus_solicitudes`
  ADD CONSTRAINT `fk_solicitudes_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `argus_users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

SET @solicitud_json = JSON_OBJECT(
  'folio', 'SOL-0001',
  'nombre_completo', 'CLIENTE DEMO',
  'estado', 'borrador',
  'fecha', '2025-01-05',
  'celular', '+5215512345678',
  'albacea', JSON_OBJECT('activo', false)
);

INSERT INTO `argus_solicitudes` (`id`, `folio`, `solicitud_datta`, `estado`, `usuario_id`, `motivo_retorno`, `devuelto_por`, `devuelto_en`)
VALUES
  (1, 'SOL-0001', @solicitud_json, 'borrador', 1, NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE
  `solicitud_datta` = VALUES(`solicitud_datta`),
  `estado` = VALUES(`estado`),
  `usuario_id` = VALUES(`usuario_id`),
  `motivo_retorno` = VALUES(`motivo_retorno`),
  `devuelto_por` = VALUES(`devuelto_por`),
  `devuelto_en` = VALUES(`devuelto_en`);

-- --------------------------------------------------------
-- Contratos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `argus_contratos_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cliente_id` int NOT NULL,
  `desarrollo_id` int NOT NULL,
  `folio` varchar(50) DEFAULT NULL,
  `datta_contrato` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `creado_por` int DEFAULT NULL,
  `estatus` tinyint DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_argus_contratos_folio` (`folio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_contratos_data`
  ADD COLUMN IF NOT EXISTS `creado_por` int DEFAULT NULL AFTER `datta_contrato`,
  ADD COLUMN IF NOT EXISTS `estatus` tinyint DEFAULT 1 AFTER `creado_por`,
  ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `estatus`;

ALTER TABLE `argus_contratos_data`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `argus_contratos_data`
  DROP FOREIGN KEY IF EXISTS `fk_contrato_cliente`,
  DROP FOREIGN KEY IF EXISTS `fk_contrato_desarrollo`,
  DROP FOREIGN KEY IF EXISTS `fk_contrato_usuario`;

ALTER TABLE `argus_contratos_data`
  ADD CONSTRAINT `fk_contrato_cliente`
    FOREIGN KEY (`cliente_id`) REFERENCES `argus_clientes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contrato_desarrollo`
    FOREIGN KEY (`desarrollo_id`) REFERENCES `argus_desarrollos` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contrato_usuario`
    FOREIGN KEY (`creado_por`) REFERENCES `argus_users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

SET @contrato_json = JSON_OBJECT(
  'cliente', JSON_OBJECT('id', 1, 'nombre', 'CLIENTE DEMO'),
  'desarrollo', JSON_OBJECT('id', 1, 'nombre', 'Villas del Bosque'),
  'contrato', JSON_OBJECT(
      'folio', 'CT-0001',
      'estado', 'activo',
      'solicitud_origen_id', 1,
      'monto_precio_inmueble', '1050000.00'
  )
);

INSERT INTO `argus_contratos_data` (`id`, `cliente_id`, `desarrollo_id`, `folio`, `datta_contrato`, `creado_por`, `estatus`)
VALUES
  (1, 1, 1, 'CT-0001', @contrato_json, 1, 1)
ON DUPLICATE KEY UPDATE
  `cliente_id` = VALUES(`cliente_id`),
  `desarrollo_id` = VALUES(`desarrollo_id`),
  `datta_contrato` = VALUES(`datta_contrato`),
  `creado_por` = VALUES(`creado_por`),
  `estatus` = VALUES(`estatus`);

-- --------------------------------------------------------
-- Plantillas de contratos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `argus_plantillas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo_contrato_id` int DEFAULT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_argus_plantillas_tipo` (`tipo_contrato_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_plantillas`
  ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `argus_plantillas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `argus_plantillas`
  DROP FOREIGN KEY IF EXISTS `fk_plantilla_tipo`;

ALTER TABLE `argus_plantillas`
  ADD CONSTRAINT `fk_plantilla_tipo`
    FOREIGN KEY (`tipo_contrato_id`) REFERENCES `argus_variables` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO `argus_plantillas` (`id`, `tipo_contrato_id`, `nombre_archivo`, `ruta_archivo`)
VALUES
  (1, 1, 'plantilla-contrato-demo.docx', 'vistas/plantillas/plantilla-contrato-demo.docx')
ON DUPLICATE KEY UPDATE
  `tipo_contrato_id` = VALUES(`tipo_contrato_id`),
  `nombre_archivo` = VALUES(`nombre_archivo`),
  `ruta_archivo` = VALUES(`ruta_archivo`);

-- --------------------------------------------------------
-- Plantillas para solicitudes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `argus_solicitud_plantillas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(30) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_argus_solicitud_plantillas_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_solicitud_plantillas`
  ADD COLUMN IF NOT EXISTS `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `argus_solicitud_plantillas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

INSERT INTO `argus_solicitud_plantillas` (`id`, `tipo`, `nombre_archivo`, `ruta_archivo`)
VALUES
  (1, 'solicitud', 'plantilla-solicitud-demo.docx', 'vistas/plantillas/plantilla-solicitud-demo.docx')
ON DUPLICATE KEY UPDATE
  `nombre_archivo` = VALUES(`nombre_archivo`),
  `ruta_archivo` = VALUES(`ruta_archivo`);

-- --------------------------------------------------------
-- Notificaciones
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `argus_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `solicitud_id` int DEFAULT NULL,
  `mensaje` varchar(255) NOT NULL,
  `tipo` varchar(50) NOT NULL DEFAULT 'solicitud',
  `referencia_id` int DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_argus_notifications_solicitud` (`solicitud_id`),
  KEY `idx_argus_notifications_tipo` (`tipo`),
  KEY `idx_argus_notifications_referencia` (`referencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_notifications`
  ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `argus_notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `argus_notifications`
  ADD COLUMN IF NOT EXISTS `tipo` varchar(50) NOT NULL DEFAULT 'solicitud' AFTER `mensaje`,
  ADD COLUMN IF NOT EXISTS `referencia_id` int DEFAULT NULL AFTER `tipo`,
  ADD COLUMN IF NOT EXISTS `url` varchar(255) DEFAULT NULL AFTER `referencia_id`,
  MODIFY `solicitud_id` int DEFAULT NULL,
  ADD KEY `idx_argus_notifications_tipo` (`tipo`),
  ADD KEY `idx_argus_notifications_referencia` (`referencia_id`);

ALTER TABLE `argus_notifications`
  ADD CONSTRAINT IF NOT EXISTS `fk_notifications_solicitud`
    FOREIGN KEY (`solicitud_id`) REFERENCES `argus_solicitudes` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO `argus_notifications` (`id`, `solicitud_id`, `mensaje`)
VALUES
  (1, 1, 'La solicitud SOL-0001 fue creada para revisión.')
ON DUPLICATE KEY UPDATE
  `solicitud_id` = VALUES(`solicitud_id`),
  `mensaje` = VALUES(`mensaje`);

CREATE TABLE IF NOT EXISTS `argus_notification_destinatarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notification_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `entregado` tinyint(1) NOT NULL DEFAULT 0,
  `entregado_en` timestamp NULL DEFAULT NULL,
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  `leido_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_argus_notification_usuario` (`notification_id`, `usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `argus_notification_destinatarios`
  ADD COLUMN IF NOT EXISTS `entregado` tinyint(1) NOT NULL DEFAULT 0 AFTER `usuario_id`,
  ADD COLUMN IF NOT EXISTS `entregado_en` timestamp NULL DEFAULT NULL AFTER `entregado`,
  ADD COLUMN IF NOT EXISTS `leido` tinyint(1) NOT NULL DEFAULT 0 AFTER `entregado_en`,
  ADD COLUMN IF NOT EXISTS `leido_en` timestamp NULL DEFAULT NULL AFTER `leido`;

ALTER TABLE `argus_notification_destinatarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `argus_notification_destinatarios`
  ADD CONSTRAINT IF NOT EXISTS `fk_notification_destinatario`
    FOREIGN KEY (`notification_id`) REFERENCES `argus_notifications` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT IF NOT EXISTS `fk_notification_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `argus_users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO `argus_notification_destinatarios` (`id`, `notification_id`, `usuario_id`, `entregado`)
VALUES
  (1, 1, 1, 0)
ON DUPLICATE KEY UPDATE
  `entregado` = VALUES(`entregado`);

COMMIT;
