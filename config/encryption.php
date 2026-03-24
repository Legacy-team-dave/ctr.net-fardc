<?php

/**
 * Configuration du Chiffrement des Sources
 * 
 * Gère le chiffrement AES-256-CBC des fichiers PHP sensibles.
 * Les fichiers chiffrés sont stockés en .encrypted et déchiffrés au runtime.
 */

// Clé de chiffrement maître (à générer et stocker de manière sécurisée)
// IMPORTANT: Cette clé doit être unique par installation et JAMAIS en clair en production
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY') ??
    hash('sha256', 'ctr-net-fardc-default-key-change-in-production', false));

// Algorithme de chiffrement
define('ENCRYPTION_CIPHER', 'AES-256-CBC');

// Répertoires contenant les fichiers potentiellement chiffrés
define('ENCRYPTED_DIRS', [
    dirname(__DIR__) . '/includes',
    dirname(__DIR__) . '/config',
    dirname(__DIR__) . '/modules'
]);

/**
 * Chiffre une chaîne de caractères
 * 
 * @param string $plaintext Texte à chiffrer
 * @param string $key Clé de chiffrement (hex)
 * @return string Tableau encodé en base64 [iv, ciphertext]
 */
function encrypt_string($plaintext, $key = null)
{
    $key = $key ?? hex2bin(ENCRYPTION_KEY);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_CIPHER));

    $encrypted = openssl_encrypt(
        $plaintext,
        ENCRYPTION_CIPHER,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($encrypted === false) {
        throw new Exception('Erreur lors du chiffrement: ' . openssl_error_string());
    }

    // Retourner [IV + Ciphertext] en base64
    return base64_encode($iv . $encrypted);
}

/**
 * Déchiffre une chaîne de caractères
 * 
 * @param string $encryptedData Chaîne chiffrée en base64
 * @param string $key Clé de chiffrement (hex)
 * @return string Texte original
 */
function decrypt_string($encryptedData, $key = null)
{
    $key = $key ?? hex2bin(ENCRYPTION_KEY);
    $data = base64_decode($encryptedData);

    if ($data === false) {
        throw new Exception('Erreur: données en base64 invalides');
    }

    $ivLen = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = substr($data, 0, $ivLen);
    $ciphertext = substr($data, $ivLen);

    $plaintext = openssl_decrypt(
        $ciphertext,
        ENCRYPTION_CIPHER,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($plaintext === false) {
        throw new Exception('Erreur lors du déchiffrement: ' . openssl_error_string());
    }

    return $plaintext;
}

/**
 * Chiffre un fichier PHP
 * 
 * @param string $filePath Chemin du fichier à chiffrer
 * @param string $outputPath Chemin du fichier chiffré (optionnel)
 * @return string Chemin du fichier chiffré
 */
function encrypt_file($filePath, $outputPath = null)
{
    if (!file_exists($filePath)) {
        throw new Exception("Fichier non trouvé: $filePath");
    }

    $plaintext = file_get_contents($filePath);
    if ($plaintext === false) {
        throw new Exception("Impossible de lire: $filePath");
    }

    $encryptedData = encrypt_string($plaintext);
    $outputPath = $outputPath ?? $filePath . '.encrypted';

    if (file_put_contents($outputPath, $encryptedData) === false) {
        throw new Exception("Impossible d'écrire: $outputPath");
    }

    return $outputPath;
}

/**
 * Déchiffre un fichier PHP et le charge en mémoire
 * 
 * @param string $encryptedFilePath Chemin du fichier chiffré
 * @return string Contenu du fichier déchiffré
 */
function decrypt_file_contents($encryptedFilePath)
{
    if (!file_exists($encryptedFilePath)) {
        throw new Exception("Fichier chiffré non trouvé: $encryptedFilePath");
    }

    $encryptedData = file_get_contents($encryptedFilePath);
    if ($encryptedData === false) {
        throw new Exception("Impossible de lire: $encryptedFilePath");
    }

    return decrypt_string($encryptedData);
}

/**
 * Charge et exécute un fichier PHP chiffré
 * Utilisé par le système de loader automatisé
 * 
 * @param string $encryptedFilePath Chemin du fichier chiffré
 * @param array $variables Variables à injecter dans le scope
 * @return mixed Résultat de l'exécution (si return utilisé)
 */
function load_encrypted_php($encryptedFilePath, $variables = [])
{
    $phpCode = decrypt_file_contents($encryptedFilePath);

    // Extraire les variables dans le scope local
    extract($variables, EXTR_SKIP);

    // Évaluer le code PHP déchiffré
    // ATTENTION: eval() est dangereux, cette approche est sécurisée uniquement si on trust la clé
    return eval('?>' . $phpCode);
}

/**
* Génère une nouvelle clé de chiffrement
*
* @return string Clé en hexadécimal (512 bits)
*/
function generate_encryption_key()
{
$key = openssl_random_pseudo_bytes(32); // 256 bits
return bin2hex($key);
}

/**
* Exporte la clé en variable d'environnement (.env)
*
* @param string $key Clé en hexadécimal
* @param string $envFilePath Chemin du fichier .env
* @return bool
*/
function save_encryption_key_to_env($key, $envFilePath = null)
{
$envFilePath = $envFilePath ?? dirname(__DIR__) . '/.env';

if (!file_exists($envFilePath)) {
file_put_contents($envFilePath, "");
}

$envContent = file_get_contents($envFilePath);

// Supprimer l'ancienne clé si elle existe
$envContent = preg_replace('/^ENCRYPTION_KEY=.+$/m', '', $envContent);

// Ajouter la nouvelle
$envContent .= "\nENCRYPTION_KEY=$key\n";

return file_put_contents($envFilePath, $envContent) !== false;
}