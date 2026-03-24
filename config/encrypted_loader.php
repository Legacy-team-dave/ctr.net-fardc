<?php

/**
 * Loader Automatisé des Fichiers Chiffrés
 * 
 * À inclure en début d'exécution pour déchiffrer automatiquement
 * les fichiers .encrypted à la volée
 * 
 * Utilisation:
 *   require_once 'config/encrypted_loader.php';
 */

// Charger la config de chiffrement
require_once dirname(__FILE__) . '/encryption.php';

/**
 * Autoloader personnalisé qui gère les fichiers chiffrés
 */
spl_autoload_register(function ($className) {
    $file = str_replace('\\', '/', $className);
    $paths = [
        dirname(dirname(__FILE__)) . '/includes/' . $file . '.php',
        dirname(dirname(__FILE__)) . '/config/' . $file . '.php',
        dirname(dirname(__FILE__)) . '/modules/' . $file . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require $path;
            return;
        }

        // Vérifier la version chiffrée
        $encPath = $path . '.encrypted';
        if (file_exists($encPath)) {
            try {
                $phpCode = decrypt_file_contents($encPath);
                eval('?>' . $phpCode);
return;
} catch (Exception $e) {
error_log("Erreur déchiffrement autoload: " . $e->getMessage());
continue;
}
}
}
});

/**
* Override le require/include pour les fichiers chiffrés
* Utile pour les inclusions directes en PHP
*/
if (!function_exists('require_safe')) {
/**
* Requiert un fichier, déchiffrant automatiquement s'il est chiffré
*
* @param string $file Chemin du fichier à requérir
* @param array $localVars Variables à injecter (optionnel)
* @return mixed
*/
function require_safe($file, $localVars = [])
{
$rootPath = dirname(dirname(__FILE__));

// Resoudre le chemin
if (strpos($file, '/') === 0) {
$filePath = $file;
} else {
$filePath = $rootPath . '/' . $file;
}

$filePath = realpath($filePath);

if (!$filePath || !file_exists($filePath)) {
// Essayer la version chiffrée
$encPath = $filePath . '.encrypted';
if (!$encPath || !file_exists($encPath)) {
throw new Exception("Fichier non trouvé: $file");
}
$filePath = $encPath;
}

// Si fichier chiffré
if (strpos($filePath, '.encrypted') !== false) {
try {
return load_encrypted_php($filePath, $localVars);
} catch (Exception $e) {
error_log("Erreur déchiffrement: " . $e->getMessage());
throw $e;
}
}

// Sinon, requérir normalement
extract($localVars, EXTR_SKIP);
return require $filePath;
}
}

/**
* Enveloppe pour include
*/
if (!function_exists('include_safe')) {
function include_safe($file, $localVars = [])
{
try {
return require_safe($file, $localVars);
} catch (Exception $e) {
error_log("Warning: " . $e->getMessage());
return false;
}
}
}

/**
* Détection automatique: si un fichier .encrypted existe, l'utiliser
* Cette fonction intercepte require_once/include_once
*/
class EncryptedFileInterceptor
{

private static $intercepted = [];

public static function intercept($requiredFile)
{
if (isset(self::$intercepted[$requiredFile])) {
return true;
}

$rootPath = dirname(dirname(__FILE__));

// Construire les chemins possibles
$possiblePaths = [
$rootPath . '/' . ltrim($requiredFile, '/'),
realpath($requiredFile),
$requiredFile
];

foreach ($possiblePaths as $testPath) {
if (!$testPath) continue;

// Vérifier la version chiffrée d'abord
if (file_exists($testPath . '.encrypted')) {
try {
$phpCode = decrypt_file_contents($testPath . '.encrypted');
eval('?>' . $phpCode);
self::$intercepted[$requiredFile] = true;
return true;
} catch (Exception $e) {
error_log("Erreur lors du déchiffrement: " . $testPath . ".encrypted - " . $e->getMessage());
}
}
}

return false;
}
}

// Enregistrer le loader automatique
spl_autoload_register([EncryptedFileInterceptor::class, 'intercept']);

/**
* Helper pour vérifier si un fichier est chiffré
*
* @param string $filePath Chemin du fichier
* @return bool
*/
function is_file_encrypted($filePath)
{
return file_exists($filePath . '.encrypted');
}

/**
* Helper pour obtenir le statut de chiffrement d'un fichier
*
* @param string $filePath Chemin du fichier
* @return array ['encrypted' => bool, 'path' => string]
*/
function get_file_encryption_status($filePath)
{
$encPath = $filePath . '.encrypted';

if (file_exists($encPath) && !file_exists($filePath)) {
return ['encrypted' => true, 'path' => $encPath];
} elseif (file_exists($filePath)) {
return ['encrypted' => false, 'path' => $filePath];
}

return ['encrypted' => null, 'path' => null];
}