<?php

// Définir l'encodage par défaut
mb_internal_encoding('UTF-8');
date_default_timezone_set('Africa/Kinshasa');

// Définir l'URL de base de l'application (utilisé pour les liens)
define('BASE_URL', '/ctr.net-fardc/');

// Activation de l'affichage des erreurs en environnement de développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connexion à la base de données MySQL via PDO
$host = 'localhost';
$port = '3306';         // Par défaut MySQL
$dbname = 'ctr.net-fardc';
$user = 'root';         // À modifier selon votre environnement
$pass = 'ZrEu@js*YY)wEh*m';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
