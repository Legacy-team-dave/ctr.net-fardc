<?php

/**
 * API Authentification pour les applications mobiles CONTROLEUR / ENROLEUR
 * Endpoints: POST /api/auth.php?action=login|logout|check
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With');
header('Access-Control-Allow-Private-Network: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

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
        echo json_encode(['success' => false, 'message' => 'Veuillez saisir votre utilisateur et votre mot de passe.']);
        return;
    }

    $rateLimit = check_login_rate_limit('mobile_api');
    if (!$rateLimit['allowed']) {
        $retryAfter = max(60, (int) ($rateLimit['retry_after'] ?? 0));
        header('Retry-After: ' . $retryAfter);
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Trop de tentatives de connexion. Réessayez dans quelques minutes.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE (login = ? OR nom_complet = ? OR email = ?) LIMIT 1");
        $stmt->execute([$login, $login, $login]);
        $user = $stmt->fetch();

        if (!$user) {
            $failureState = register_login_failure('mobile_api', $login);
            if (($failureState['retry_after'] ?? 0) > 0) {
                header('Retry-After: ' . max(60, (int) ($failureState['retry_after'] ?? 0)));
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Trop de tentatives de connexion. Réessayez dans quelques minutes.']);
                return;
            }

            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Utilisateur non créé dans la base de données.']);
            return;
        }

        if (empty($user['actif'])) {
            $failureState = register_login_failure('mobile_api', $login);
            if (($failureState['retry_after'] ?? 0) > 0) {
                header('Retry-After: ' . max(60, (int) ($failureState['retry_after'] ?? 0)));
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Trop de tentatives de connexion. Réessayez dans quelques minutes.']);
                return;
            }

            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Ce compte existe mais il est en attente d\'activation.']);
            return;
        }

        if (!password_verify($password, $user['mot_de_passe'])) {
            $failureState = register_login_failure('mobile_api', $login);
            if (($failureState['retry_after'] ?? 0) > 0) {
                header('Retry-After: ' . max(60, (int) ($failureState['retry_after'] ?? 0)));
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Trop de tentatives de connexion. Réessayez dans quelques minutes.']);
                return;
            }

            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Utilisateur ou mot de passe incorrects.']);
            return;
        }

        // Vérifier que c'est un profil mobile autorisé
        if (!is_mobile_only_profile($user['profil'])) {
            $failureState = register_login_failure('mobile_api', $login);
            if (($failureState['retry_after'] ?? 0) > 0) {
                header('Retry-After: ' . max(60, (int) ($failureState['retry_after'] ?? 0)));
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Trop de tentatives de connexion. Réessayez dans quelques minutes.']);
                return;
            }

            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Ce compte n\'est pas autorisé sur cette application mobile.']);
            return;
        }

        session_regenerate_id(true);

        // Générer un token API
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("UPDATE utilisateurs SET remember_token = ?, remember_token_expires = ?, dernier_acces = NOW() WHERE id_utilisateur = ?");
        $stmt->execute([$token, $expires, $user['id_utilisateur']]);
        clear_login_rate_limit('mobile_api');

        // Log connexion
        $ip = get_client_ip_address();
        $stmt = $pdo->prepare("INSERT INTO logs (id_utilisateur, action, table_concernee, details, ip_address, user_agent) VALUES (?, 'CONNEXION_MOBILE', 'utilisateurs', 'Connexion depuis l\'app mobile', ?, ?)");
        $stmt->execute([$user['id_utilisateur'], $ip, $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile App']);

        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => buildMobileUserPayload($user)
        ]);
    } catch (PDOException $e) {
        error_log("API auth error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur pendant la connexion. Veuillez réessayer.']);
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
        $stmt = $pdo->prepare("SELECT id_utilisateur, login, nom_complet, email, profil, avatar, dernier_acces, created_at FROM utilisateurs WHERE remember_token = ? AND remember_token_expires > NOW() AND actif = true");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token invalide ou expiré']);
            return;
        }

        echo json_encode([
            'success' => true,
            'user' => buildMobileUserPayload($user)
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}

function buildMobileUserPayload(array $user): array
{
    return [
        'id' => (int) ($user['id_utilisateur'] ?? 0),
        'id_utilisateur' => (int) ($user['id_utilisateur'] ?? 0),
        'login' => (string) ($user['login'] ?? ''),
        'nom' => (string) ($user['nom_complet'] ?? ''),
        'nom_complet' => (string) ($user['nom_complet'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'profil' => (string) ($user['profil'] ?? ''),
        'avatar' => (string) ($user['avatar'] ?? ''),
        'dernier_acces' => (string) ($user['dernier_acces'] ?? ''),
        'created_at' => (string) ($user['created_at'] ?? ''),
    ];
}

function getBearerToken()
{
    $candidates = [];

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $candidates[] = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $candidates[] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        $candidates[] = trim($_SERVER['HTTP_X_AUTH_TOKEN']);
    }

    if (function_exists('apache_request_headers')) {
        $reqHeaders = apache_request_headers();
        $candidates[] = $reqHeaders['Authorization'] ?? '';
        $candidates[] = $reqHeaders['authorization'] ?? '';
        $candidates[] = $reqHeaders['X-Auth-Token'] ?? '';
        $candidates[] = $reqHeaders['x-auth-token'] ?? '';
    }

    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            continue;
        }

        if (preg_match('/Bearer\s(.+)/i', $candidate, $matches)) {
            $token = trim($matches[1]);
            if ($token !== '') {
                return $token;
            }
        }

        return $candidate;
    }

    $rawInput = file_get_contents('php://input');
    if (is_string($rawInput) && trim($rawInput) !== '') {
        $payload = json_decode($rawInput, true);
        if (is_array($payload) && !empty($payload['auth_token'])) {
            return trim((string) $payload['auth_token']);
        }
    }

    return null;
}
