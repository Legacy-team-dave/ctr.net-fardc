<?php

/**
 * API Profil pour l'application mobile CONTROLEUR
 * Endpoints: GET/POST /api/profil.php?action=get|update
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$user = authenticateToken($pdo);
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$action = $_GET['action'] ?? 'get';

switch ($action) {
    case 'get':
        handleGet($pdo, $user);
        break;
    case 'update':
        handleUpdate($pdo, $user);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
}

function handleGet($pdo, $user)
{
    try {
        $stmt = $pdo->prepare("SELECT id_utilisateur, login, nom_complet, email, avatar, profil, dernier_acces, created_at FROM utilisateurs WHERE id_utilisateur = ?");
        $stmt->execute([$user['id_utilisateur']]);
        $profile = $stmt->fetch();

        if (!$profile) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
            return;
        }

        echo json_encode(['success' => true, 'user' => $profile]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}

function handleUpdate($pdo, $user)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode POST requise']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $nom_complet = trim($input['nom_complet'] ?? '');
    $email = trim($input['email'] ?? '');
    $old_password = $input['old_password'] ?? '';
    $new_password = $input['new_password'] ?? '';

    if (empty($nom_complet) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nom et email requis']);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email invalide']);
        return;
    }

    try {
        if (!empty($new_password)) {
            if (empty($old_password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ancien mot de passe requis']);
                return;
            }

            $stmt = $pdo->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id_utilisateur = ?");
            $stmt->execute([$user['id_utilisateur']]);
            $current = $stmt->fetchColumn();

            if (!password_verify($old_password, $current)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ancien mot de passe incorrect']);
                return;
            }

            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateurs SET nom_complet = ?, email = ?, mot_de_passe = ? WHERE id_utilisateur = ?");
            $stmt->execute([$nom_complet, $email, $hash, $user['id_utilisateur']]);
        } else {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET nom_complet = ?, email = ? WHERE id_utilisateur = ?");
            $stmt->execute([$nom_complet, $email, $user['id_utilisateur']]);
        }

        echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès']);
    } catch (PDOException $e) {
        error_log("API profil update error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}

function authenticateToken($pdo)
{
    $token = getBearerToken();
    if (!$token) return null;

    try {
        $stmt = $pdo->prepare("SELECT id_utilisateur, login, nom_complet, profil FROM utilisateurs WHERE remember_token = ? AND remember_token_expires > NOW() AND actif = true");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user && strtoupper(trim($user['profil'])) === 'CONTROLEUR') {
            return $user;
        }
    } catch (PDOException $e) {
        error_log("API auth error: " . $e->getMessage());
    }
    return null;
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
