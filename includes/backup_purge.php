<?php

/**
 * backup_purge.php
 * Exécution manuelle de la purge des archives de sauvegarde.
 */

set_time_limit(0);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

$maxKeep = isset($argv[1]) ? (int)$argv[1] : 30;
if ($maxKeep <= 0) {
    $maxKeep = 30;
}

$result = purge_backup_archives($maxKeep);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
