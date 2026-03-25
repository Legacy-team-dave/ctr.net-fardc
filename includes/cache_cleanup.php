<?php

/**
 * cache_cleanup.php
 * Script exécuté par cron ou manuellement pour nettoyer les caches automatiquement.
 * Intégré dans le job planifié via backup_cron.php, ou exécutable séparément.
 */

set_time_limit(0);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

$jours_logs = isset($argv[1]) ? (int)$argv[1] : 90;
if ($jours_logs <= 0) {
    $jours_logs = 90;
}

try {
    $result = nettoyer_caches($jours_logs);

    $total = $result['temp_xlsx_supprimes']
        + $result['lock_files_supprimes']
        + $result['remember_tokens_expires']
        + $result['reset_tokens_expires']
        + $result['logs_supprimes'];

    if ($total > 0) {
        error_log(
            "Nettoyage caches exécuté: "
                . "temp_xlsx=" . $result['temp_xlsx_supprimes']
                . " | lock=" . $result['lock_files_supprimes']
                . " | remember_tokens=" . $result['remember_tokens_expires']
                . " | reset_tokens=" . $result['reset_tokens_expires']
                . " | logs=" . $result['logs_supprimes']
        );
    } else {
        error_log("Nettoyage caches: rien à nettoyer.");
    }

    if (!empty($result['erreurs'])) {
        error_log("Erreurs nettoyage caches: " . implode('; ', $result['erreurs']));
    }

    // Si exécuté en mode CLI direct, afficher le rapport
    if (php_sapi_name() === 'cli') {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
} catch (Exception $e) {
    error_log("Exception lors du nettoyage caches: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo json_encode(['error' => $e->getMessage()]) . PHP_EOL;
    }
}
