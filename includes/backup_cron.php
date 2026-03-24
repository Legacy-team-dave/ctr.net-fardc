<?php

/**
 * backup_cron.php
 * Script exécuté par cron pour déclencher la sauvegarde automatique.
 * Doit être placé dans un dossier accessible (de préférence hors web).
 */

// Désactiver la limite de temps d'exécution
set_time_limit(0);

// Inclusion des fichiers nécessaires
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

$maxKeep = isset($argv[1]) ? (int)$argv[1] : 30;
if ($maxKeep <= 0) {
    $maxKeep = 30;
}

// Créer le dossier de sauvegarde si nécessaire
get_backup_dir_path();

// Verrou pour éviter les exécutions simultanées
$lockFile = sys_get_temp_dir() . '/backup_cron.lock';
$fp = fopen($lockFile, 'c');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    error_log("Une sauvegarde est déjà en cours, abandon.");
    exit(0);
}

try {
    $result = maybe_create_backup(false);

    if (!empty($result['created'])) {
        $counts = $result['counts'] ?? [];
        error_log(
            "Sauvegarde incrémentale exécutée: "
                . ($result['file'] ?? 'archive inconnue')
                . " | controles=" . ($counts['controles'] ?? 0)
                . " | litiges=" . ($counts['litiges'] ?? 0)
                . " | non_vus=" . ($counts['non_vus'] ?? 0)
        );
    } else {
        error_log("Sauvegarde incrémentale non exécutée: " . ($result['reason'] ?? 'raison inconnue'));
    }

    $purge = purge_backup_archives($maxKeep);
    error_log(
        "Purge archives (max_keep=" . $maxKeep . "): scanned=" . ($purge['scanned'] ?? 0)
            . " | kept=" . ($purge['unique_kept'] ?? 0)
            . " | deleted_total=" . ($purge['deleted_total'] ?? 0)
            . " | duplicates=" . ($purge['deleted_duplicates'] ?? 0)
            . " | overflow=" . ($purge['deleted_overflow'] ?? 0)
    );
} catch (Exception $e) {
    error_log("Exception lors de la sauvegarde : " . $e->getMessage());
} finally {
    flock($fp, LOCK_UN);
    fclose($fp);
}
