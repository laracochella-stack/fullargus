ALTER TABLE argus_notification_destinatarios
    ADD COLUMN leido TINYINT(1) NOT NULL DEFAULT 0 AFTER entregado_en,
    ADD COLUMN leido_en TIMESTAMP NULL DEFAULT NULL AFTER leido;

UPDATE argus_notification_destinatarios
SET leido = entregado,
    leido_en = CASE WHEN entregado = 1 THEN IFNULL(entregado_en, NOW()) ELSE NULL END;
