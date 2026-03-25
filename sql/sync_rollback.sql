-- =============================================================
-- CTR.NET-FARDC - Rollback Synchronisation (serveur central)
-- =============================================================
-- ATTENTION: ce script supprime définitivement les données
--            de synchronisation (batchs et mappings).
--
-- Usage recommandé:
--   USE `ctr.net-fardc`;
--   SOURCE sql/sync_rollback.sql;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `sync_record_map`;
DROP TABLE IF EXISTS `sync_batches`;

SET FOREIGN_KEY_CHECKS = 1;

-- Vérification post-rollback
SELECT
    TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('sync_batches', 'sync_record_map');
