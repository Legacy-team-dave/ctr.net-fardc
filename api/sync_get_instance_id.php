<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

 $pdo = require_once __DIR__ . '/../includes/database.php';

try {
    $tempDir = sys_get_temp_dir();
    $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'ctr_ops_fardc_unique_machine_id';

    $instanceId = null;

    if (file_exists($tempFile) && is_readable($tempFile)) {
        $tempId = trim(file_get_contents($tempFile));
        if ($tempId !== '' && strlen($tempId) >= 16 && ctype_xdigit($tempId)) {
            echo json_encode(['success' => true, 'instance_id' => $tempId], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Fichier inexistant ou invalide → générer un nouvel ID
    $instanceId = strtolower(bin2hex(random_bytes(8));

    @file_put_contents($tempFile, $instanceId, LOCK_EX);

    echo json_encode(['success' => true, 'instance_id' => $instanceId], JSON_UNESCAPED_UNICODE);
exit;