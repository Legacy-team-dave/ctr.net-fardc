<?php
session_start();
require_once 'includes/functions.php';

// MODIFICATION : Supprimer le token "Se souvenir de moi" de la base de données et du cookie
if (isset($_SESSION['user_id'])) {
    try {
        // $pdo est déjà défini via functions.php (qui inclut database.php)
        global $pdo;
        $stmt = $pdo->prepare("UPDATE utilisateurs SET remember_token = NULL, remember_token_expires = NULL WHERE id_utilisateur = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du token remember_token : " . $e->getMessage());
    }
    // Supprimer le cookie
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

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
header('Location: ' . app_url('login.php'));
exit;
