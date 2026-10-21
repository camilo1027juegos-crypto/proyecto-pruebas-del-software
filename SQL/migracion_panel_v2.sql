-- =====================================================================
-- migracion_panel_v2.sql
-- Ejecutar UNA sola vez sobre `rubicam_db` (phpMyAdmin → pestaña SQL).
--
-- Actualiza los tipos de documento del legajo aduanero a:
--   Declaración de Importación, Factura Comercial, Lista de Empaque,
--   RUT, BL/Guía aérea, Otro
-- y convierte los registros existentes con los nombres antiguos.
-- =====================================================================

SET NAMES utf8mb4;

-- 1) ENUM temporal con valores antiguos + nuevos (para no perder datos).
ALTER TABLE `documentos_do`
  MODIFY `tipo_documento` ENUM(
    'Declaración de Importación','Factura Comercial','Lista de Empaque','RUT','BL/Guía aérea','Otro',
    'Formulario 500','Factura','Documento de Transporte'
  ) NOT NULL;

-- 2) Convertir valores antiguos.
UPDATE `documentos_do` SET `tipo_documento` = 'Declaración de Importación' WHERE `tipo_documento` = 'Formulario 500';
UPDATE `documentos_do` SET `tipo_documento` = 'Factura Comercial'          WHERE `tipo_documento` = 'Factura';
UPDATE `documentos_do` SET `tipo_documento` = 'BL/Guía aérea'              WHERE `tipo_documento` = 'Documento de Transporte';

-- 3) ENUM definitivo.
ALTER TABLE `documentos_do`
  MODIFY `tipo_documento` ENUM(
    'Declaración de Importación','Factura Comercial','Lista de Empaque','RUT','BL/Guía aérea','Otro'
  ) NOT NULL;

