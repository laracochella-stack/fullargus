-- Agrega columnas de folio y restricciones de unicidad para solicitudes y contratos,
-- además de asegurar la unicidad del RFC en clientes.

ALTER TABLE `argus_solicitudes`
  ADD COLUMN `folio` varchar(50) DEFAULT NULL AFTER `id`;

UPDATE `argus_solicitudes`
SET `folio` = NULLIF(REGEXP_REPLACE(UPPER(JSON_UNQUOTE(JSON_EXTRACT(`solicitud_datta`, '$.folio'))), '[^A-Z0-9_-]', ''), '');

UPDATE `argus_solicitudes` s
JOIN (
    SELECT folio, MIN(id) AS id_min
    FROM argus_solicitudes
    WHERE folio IS NOT NULL
    GROUP BY folio
    HAVING COUNT(*) > 1
) dup ON dup.folio = s.folio AND s.id <> dup.id_min
SET s.folio = NULL;

ALTER TABLE `argus_solicitudes`
  ADD UNIQUE KEY `uq_argus_solicitudes_folio` (`folio`);

ALTER TABLE `argus_contratos_data`
  ADD COLUMN `folio` varchar(50) DEFAULT NULL AFTER `desarrollo_id`;

UPDATE `argus_contratos_data`
SET `folio` = NULLIF(REGEXP_REPLACE(UPPER(JSON_UNQUOTE(JSON_EXTRACT(`datta_contrato`, '$.contrato.folio'))), '[^A-Z0-9_-]', ''), '');

UPDATE `argus_contratos_data` c
JOIN (
    SELECT folio, MIN(id) AS id_min
    FROM argus_contratos_data
    WHERE folio IS NOT NULL
    GROUP BY folio
    HAVING COUNT(*) > 1
) dup ON dup.folio = c.folio AND c.id <> dup.id_min
SET c.folio = NULL;

ALTER TABLE `argus_contratos_data`
  ADD UNIQUE KEY `uq_argus_contratos_folio` (`folio`);

UPDATE `argus_clientes`
SET `rfc` = REGEXP_REPLACE(UPPER(`rfc`), '[^A-Z0-9&]', '');

UPDATE `argus_clientes` c
JOIN (
    SELECT rfc, MIN(id) AS id_min
    FROM argus_clientes
    WHERE rfc <> ''
    GROUP BY rfc
    HAVING COUNT(*) > 1
) dup ON dup.rfc = c.rfc AND c.id <> dup.id_min
SET c.rfc = CONCAT('D', LPAD(c.id, 12, '0'));

ALTER TABLE `argus_clientes`
  ADD UNIQUE KEY `uq_argus_clientes_rfc` (`rfc`);
