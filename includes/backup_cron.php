<?php

/**
 * backup_cron.php
 * Script exécuté par cron pour déclencher la sauvegarde automatique.
 * Doit être placé dans un dossier accessible (de préférence hors web).
 */

// Désactiver la limite de temps d'exécution
set_time_limit(0);

// Inclusion des fichiers nécessaires (chemins relatifs à la racine du projet)
require_once __DIR__ . '/functions.php';       // contient generate_backup()
require_once __DIR__ . '/../config/database.php';  // contient $pdo

// Créer le dossier de sauvegarde s'il n'existe pas (facultatif, déjà fait dans generate_backup)
$backup_dir = dirname(__DIR__, 2) . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Verrou pour éviter les exécutions simultanées
$lockFile = sys_get_temp_dir() . '/backup_cron.lock';
$fp = fopen($lockFile, 'c');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    error_log("Une sauvegarde est déjà en cours, abandon.");
    exit(0);
}

try {
    $success = generate_backup(true);
    if ($success) {
        error_log("Sauvegarde automatique exécutée avec succès.");
    } else {
        error_log("Échec de la sauvegarde automatique.");
    }
} catch (Exception $e) {
    error_log("Exception lors de la sauvegarde : " . $e->getMessage());
} finally {
    flock($fp, LOCK_UN);
    fclose($fp);
}