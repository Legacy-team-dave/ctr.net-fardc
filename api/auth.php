<?php

/**
 * API Authentification pour l'application mobile CONTROLEUR
 * Endpoints: POST /api/auth.php?action=login|logout|check
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin($pdo);
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        handleCheck();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
}

function handleLogin($pdo)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $login = trim($input['login'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($login) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Login et mot de passe requis']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE (login = ? OR nom_complet = ? OR email = ?) AND actif = true");
        $stmt->execute([$login, $login, $login]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['mot_de_passe'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
            return;
        }

        // Vérifier que c'est un CONTROLEUR
        if (strtoupper(trim($user['profil'])) !== 'CONTROLEUR') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cette application est réservée au profil CONTROLEUR']);
            return;
        }

        // Générer un token API
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("UPDATE utilisateurs SET remember_token = ?, remember_token_expires = ?, dernier_acces = NOW() WHERE id_utilisateur = ?");
        $stmt->execute([$token, $expires, $user['id_utilisateur']]);

        // Log connexion
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'mobile';
        $stmt = $pdo->prepare("INSERT INTO logs (id_utilisateur, action, table_concernee, details, ip_address, user_agent) VALUES (?, 'CONNEXION_MOBILE', 'utilisateurs', 'Connexion depuis l\'app mobile', ?, ?)");
        $stmt->execute([$user['id_utilisateur'], $ip, $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile App']);

        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id_utilisateur'],
                'login' => $user['login'],
                'nom' => $user['nom_complet'],
                'email' => $user['email'],
                'profil' => $user['profil'],
                'avatar' => $user['avatar']
            ]
        ]);
    } catch (PDOException $e) {
        error_log("API auth error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}

function handleLogout()
{
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Déconnexion réussie']);
}

function handleCheck()
{
    $token = getBearerToken();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token manquant']);
        return;
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id_utilisateur, login, nom_complet, email, profil, avatar FROM utilisateurs WHERE remember_token = ? AND remember_token_expires > NOW() AND actif = true");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token invalide ou expiré']);
            return;
        }

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id_utilisateur'],
                'login' => $user['login'],
                'nom' => $user['nom_complet'],
                'email' => $user['email'],
                'profil' => $user['profil'],
                'avatar' => $user['avatar']
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}

function getBearerToken()
{
    $headers = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $reqHeaders = apache_request_headers();
        $headers = $reqHeaders['Authorization'] ?? '';
    }
    if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }
    return null;
}
