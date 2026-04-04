<?php
// Définir l'encodage par défaut
mb_internal_encoding('UTF-8');
date_default_timezone_set('Africa/Kinshasa');

// Définir l'URL de base de l'application (utilisé pour les liens)
define('BASE_URL', '/ctr.net-fardc/');

require_once __DIR__ . '/load_config.php';

// -------------------------------------------------------------------
// Connexion à la base de données
// -------------------------------------------------------------------
$host = 'localhost';
$port = '3306';
$dbname = 'ctr.net-fardc-1';
$user = 'root';         // À modifier selon votre environnement

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Une fois la connexion établie, effacer le mot de passe en mémoire
unset($db_pass);
