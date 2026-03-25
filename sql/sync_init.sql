-- =============================================================
-- CTR.NET-FARDC - Initialisation Synchronisation (serveur central)
-- =============================================================
-- Usage recommandé:
--   1) Sélectionner la base cible
--   2) Exécuter ce script
--
-- Exemple:
--   USE `ctr.net-fardc`;
--   SOURCE sql/sync_init.sql;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `sync_batches` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `source_instance` VARCHAR(120) NOT NULL,
    `received_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `payload_sha256` CHAR(64) NOT NULL,
    `status` VARCHAR(20) NOT NULL,
    `details_json` LONGTEXT NULL,
    `total_records` INT NOT NULL DEFAULT 0,
    INDEX `idx_sync_batches_source` (`source_instance`),
    INDEX `idx_sync_batches_received_at` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sync_record_map` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `source_instance` VARCHAR(120) NOT NULL,
    `table_name` VARCHAR(64) NOT NULL,
    `source_pk` VARCHAR(128) NOT NULL,
    `target_pk` VARCHAR(128) NOT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_sync_record` (`source_instance`, `table_name`, `source_pk`),
    INDEX `idx_sync_record_target` (`table_name`, `target_pk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- Vérifications rapides
-- =============================================================

SELECT
    TABLE_NAME,
    ENGINE,
    TABLE_COLLATION,
    CREATE_TIME,
    UPDATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('sync_batches', 'sync_record_map');

SELECT
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('sync_batches', 'sync_record_map')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
