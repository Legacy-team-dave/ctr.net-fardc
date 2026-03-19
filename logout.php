<?php
session_start();
require_once 'includes/functions.php';

// Journaliser la déconnexion
if (isset($_SESSION['user_id'])) {
    audit_action('DECONNEXION', 'utilisateurs', $_SESSION['user_id'], 'Déconnexion');
}

// Détruire la session
$_SESSION = array();

// Détruire le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Rediriger vers la page de connexion
header('Location: login.php');
exit;
