<?php

/**
 * Connexion à la base de données CENTRALE (ctr.net-fardc-active-web-1).
 * Utilisée uniquement lors des opérations de synchronisation (ADMIN_IG).
 *
 * Si CENTRAL_DB_PASS n'est pas défini dans .env et que les deux bases
 * sont sur le même serveur MySQL, on réutilise le mot de passe local
 * chargé par load_config.php ($db_pass).
 */

require_once __DIR__ . '/app_config.php';

$central_host = app_env('CENTRAL_DB_HOST', 'localhost');
$central_port = app_env('CENTRAL_DB_PORT', '3306');
$central_dbname = app_env('CENTRAL_DB_NAME', 'ctr.net-fardc-active-web-1');
$central_user = app_env('CENTRAL_DB_USER', 'root');
$central_pass = app_env('CENTRAL_DB_PASS', null);

// Fallback : si pas de mot de passe central défini, utiliser celui de la base locale
if ($central_pass === null || $central_pass === '') {
    // Reconstituer le mot de passe depuis les fragments Base64 (mêmes constantes que load_config.php)
    $const_names = ['A', 'B', 'C', 'D', 'E'];
    $parts = [];
    $all_defined = true;
    foreach ($const_names as $c) {
        if (defined($c)) {
            $parts[] = base64_decode(constant($c));
        } else {
            $all_defined = false;
            break;
        }
    }
    if ($all_defined && !empty($parts)) {
        $central_pass = implode('', $parts);
    } else {
        // Si les constantes ne sont pas encore chargées, charger load_config.php
        require_once __DIR__ . '/load_config.php';
        $central_pass = $db_pass ?? '';
        unset($db_pass);
    }
}

try {
    $dsn_central = "mysql:host=$central_host;port=$central_port;dbname=$central_dbname;charset=utf8mb4";
    $pdo_central = new PDO($dsn_central, $central_user, $central_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("Erreur connexion base centrale: " . $e->getMessage());
    $pdo_central = null;
}

// Effacer le mot de passe en mémoire
unset($central_pass);
