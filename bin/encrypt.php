#!/usr/bin/env php
<?php
/**
 * CLI - Chiffrement des Fichiers Sources
 * 
 * Utilisation:
 *   php bin/encrypt.php init              - Générer une clé
 *   php bin/encrypt.php encrypt           - Chiffrer les fichiers
 *   php bin/encrypt.php status            - Afficher l'état
 *   php bin/encrypt.php encrypt <file>    - Chiffrer un fichier spécifique
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rootPath = dirname(dirname(__FILE__));
require_once $rootPath . '/config/encryption.php';

// Fichiers sensibles à chiffrer par défaut
$filesToEncrypt = [
    'config/database.php',
    'config/load_config.php',
    'includes/functions.php',
    'includes/auth.php',
    'includes/header.php',
    'login.php',
    'logout.php',
    'index.php'
];

function cli_print($msg, $type = 'info')
{
    $colors = [
        'success' => "\033[92m",
        'error' => "\033[91m",
        'warning' => "\033[93m",
        'info' => "\033[94m",
        'reset' => "\033[0m"
    ];

    $color = $colors[$type] ?? $colors['info'];
    echo $color . $msg . $colors['reset'] . PHP_EOL;
}

function show_help()
{
    cli_print("═══════════════════════════════════════════", 'info');
    cli_print("✓ Chiffrement des Sources CTR.NET-FARDC", 'info');
    cli_print("═══════════════════════════════════════════", 'info');
    cli_print("", 'info');
    cli_print("Commandes disponibles:", 'info');
    cli_print("  php bin/encrypt.php init               Générer une nouvelle clé", 'info');
    cli_print("  php bin/encrypt.php encrypt            Chiffrer tous les fichiers", 'info');
    cli_print("  php bin/encrypt.php encrypt <file>     Chiffrer un fichier spécifique", 'info');
    cli_print("  php bin/encrypt.php decrypt <file>     Déchiffrer un fichier", 'info');
    cli_print("  php bin/encrypt.php status             Afficher l'état du chiffrement", 'info');
    cli_print("  php bin/encrypt.php list               Lister les fichiers chiffrables", 'info');
    cli_print("", 'info');
}

$action = $argv[1] ?? 'help';
$target = $argv[2] ?? null;

try {
    switch ($action) {
        case 'init':
            cli_print("Génération d'une nouvelle clé de chiffrement...", 'info');
            $newKey = generate_encryption_key();
            save_encryption_key_to_env($newKey);
            cli_print("✓ Clé générée et sauvegardée en .env", 'success');
            cli_print("   Clé: " . substr($newKey, 0, 32) . "...", 'info');
            cli_print("   IMPORTANT: Cette clé est essentielle pour déchiffrer les fichiers!", 'warning');
            break;

        case 'encrypt':
            if ($target) {
                // Chiffrer un fichier spécifique
                $filePath = realpath($rootPath . '/' . $target);
                if (!$filePath || !file_exists($filePath)) {
                    cli_print("✗ Fichier non trouvé: $target", 'error');
                    exit(1);
                }

                cli_print("Chiffrement de: $target", 'info');
                $encryptedPath = encrypt_file($filePath);
                cli_print("✓ Fichier chiffré: " . basename($encryptedPath), 'success');
            } else {
                // Chiffrer tous les fichiers listés
                global $filesToEncrypt;
                $count = 0;

                cli_print("Chiffrement de tous les fichiers sensibles...", 'info');

                foreach ($filesToEncrypt as $relPath) {
                    $filePath = realpath($rootPath . '/' . $relPath);
                    if (!$filePath || !file_exists($filePath)) {
                        cli_print("  ✗ Non trouvé: $relPath", 'warning');
                        continue;
                    }

                    // Vérifier si déjà chiffré
                    if (file_exists($filePath . '.encrypted')) {
                        cli_print("  ⊘ Déjà chiffré: $relPath", 'warning');
                        continue;
                    }

                    try {
                        encrypt_file($filePath);
                        cli_print("  ✓ Chiffré: $relPath", 'success');
                        $count++;
                    } catch (Exception $e) {
                        cli_print("  ✗ Erreur: " . $e->getMessage(), 'error');
                    }
                }

                cli_print("", 'info');
                cli_print("Résumé: $count fichiers chiffrés", 'success');
            }
            break;

        case 'decrypt':
            if (!$target) {
                cli_print("✗ Spécifiez le fichier à déchiffrer", 'error');
                exit(1);
            }

            $encryptedPath = realpath($rootPath . '/' . $target);
            if (!$encryptedPath || !file_exists($encryptedPath)) {
                cli_print("✗ Fichier non trouvé: $target", 'error');
                exit(1);
            }

            cli_print("Déchiffrement de: $target", 'info');
            $plaintext = decrypt_file_contents($encryptedPath);

            $originalPath = preg_replace('/\.encrypted$/', '', $encryptedPath);
            file_put_contents($originalPath, $plaintext);

            cli_print("✓ Fichier déchiffré et restauré: " . basename($originalPath), 'success');
            break;

        case 'status':
            cli_print("État du chiffrement:", 'info');

            $originalCount = 0;
            $encryptedCount = 0;
            $encryptedFiles = [];

            global $filesToEncrypt;

            foreach ($filesToEncrypt as $relPath) {
                $filePath = realpath($rootPath . '/' . $relPath);
                if ($filePath && file_exists($filePath)) {
                    $originalCount++;
                }

                $encPath = $filePath . '.encrypted';
                if ($encPath && file_exists($encPath)) {
                    $encryptedCount++;
                    $encryptedFiles[] = $relPath;
                }
            }

            cli_print("  Fichiers originaux: $originalCount", 'info');
            cli_print("  Fichiers chiffrés: $encryptedCount", 'info');

            if (!empty($encryptedFiles)) {
                cli_print("  ", 'info');
                cli_print("  Fichiers chiffrés:", 'info');
                foreach ($encryptedFiles as $f) {
                    cli_print("    - $f", 'success');
                }
            }
            break;

        case 'list':
            cli_print("Fichiers chiffrables:", 'info');
            global $filesToEncrypt;
            foreach ($filesToEncrypt as $f) {
                $path = realpath($rootPath . '/' . $f);
                if ($path && file_exists($path)) {
                    $status = file_exists($path . '.encrypted') ? '[CHIFFRÉ]' : '[ORIGINAL]';
                    cli_print("  $status $f", 'info');
                }
            }
            break;

        case 'help':
        default:
            show_help();
            break;
    }
} catch (Exception $e) {
    cli_print("✗ Erreur: " . $e->getMessage(), 'error');
    exit(1);
}
?>