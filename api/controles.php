<?php

/**
 * API Contrôles pour l'application mobile CONTROLEUR
 * Endpoints: GET/POST /api/controles.php?action=search|valider|historique
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

// Authentification par token
$user = authenticateToken($pdo);
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'search':
        handleSearch($pdo);
        break;
    case 'valider':
        handleValider($pdo, $user);
        break;
    case 'historique':
        handleHistorique($pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
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

function handleSearch($pdo)
{
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode([]);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT matricule, noms, grade, unite, garnison, province, statut, categorie, beneficiaire
                               FROM militaires 
                               WHERE matricule LIKE ? OR noms LIKE ? 
                               ORDER BY 
                                   CASE 
                                       WHEN matricule = ? THEN 0 
                                       WHEN matricule LIKE ? THEN 1 
                                       WHEN noms LIKE ? THEN 2 
                                       ELSE 3 
                                   END, noms 
                               LIMIT 10");
        $searchTerm = "%$q%";
        $stmt->execute([$searchTerm, $searchTerm, $q, "$q%", "$q%"]);
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrichir chaque résultat avec l'âge estimé
        foreach ($resultats as &$r) {
            $r['age'] = calculerAge($r['matricule']);
            // Vérifier si déjà contrôlé
            $check = $pdo->prepare("SELECT id FROM controles WHERE matricule = ?");
            $check->execute([$r['matricule']]);
            $r['deja_controle'] = $check->rowCount() > 0;
        }

        echo json_encode($resultats);
    } catch (PDOException $e) {
        error_log("API search error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}

function handleValider($pdo, $user)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode POST requise']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $matricule = trim($input['matricule'] ?? '');
    $mention = trim($input['mention'] ?? '');
    $lien = trim($input['lien'] ?? '');
    $beneficiaire = trim($input['beneficiaire'] ?? '');
    $new_beneficiaire = trim($input['new_beneficiaire'] ?? '');
    $observations = trim($input['observations'] ?? '');
    $statut_vivant = $input['statut_vivant'] ?? false;
    $statut_decede = $input['statut_decede'] ?? false;
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;

    if (empty($matricule) || empty($mention)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Matricule et mention requis']);
        return;
    }

    if (empty($lien)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Lien de parenté requis']);
        return;
    }

    try {
        // Vérifier doublon
        $stmt = $pdo->prepare("SELECT id FROM controles WHERE matricule = ?");
        $stmt->execute([$matricule]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Ce militaire a déjà été contrôlé']);
            return;
        }

        // Récupérer les infos du militaire
        $stmt = $pdo->prepare("SELECT noms, categorie FROM militaires WHERE matricule = ?");
        $stmt->execute([$matricule]);
        $militaire_info = $stmt->fetch();

        if (!$militaire_info) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Militaire introuvable']);
            return;
        }

        // Calcul du lien de parenté transformé
        $lien_parente = match ($lien) {
            'Epoux' => 'Veuf',
            'Epouse' => 'Veuve',
            'Fils', 'Fille' => 'Orphelin',
            'Père', 'Mère', 'Frère', 'Sœur' => 'Tuteur',
            'Militaire lui-même' => $militaire_info['categorie'] ?? 'Militaire lui-même',
            default => $lien
        };

        // Type de contrôle
        $type_controle = 'Bénéficiaire';
        if ($lien === 'Militaire lui-même') {
            $type_controle = 'Militaire';
            $beneficiaire = null;
            $new_beneficiaire = null;
        } else {
            $cat = $militaire_info['categorie'] ?? '';
            $is_dcd_av_bio = ($cat === 'DCD_AV_BIO');
            if (($statut_vivant && !$statut_decede) || (!$is_dcd_av_bio && !$statut_vivant && !$statut_decede)) {
                $type_controle = 'Militaire';
            }

            // Vérifier bénéficiaire
            if (empty($beneficiaire) && empty($new_beneficiaire)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Un bénéficiaire est requis']);
                return;
            }
        }

        // DCD_AP_BIO + vivant sans décédé => "Mort vivant"
        $cat = $militaire_info['categorie'] ?? '';
        if ($cat === 'DCD_AP_BIO' && $statut_vivant && !$statut_decede) {
            $observations = empty($observations) ? 'Mort vivant' : 'Mort vivant - ' . $observations;
        }

        // Ajouter coordonnées GPS aux observations si disponibles
        if ($latitude !== null && $longitude !== null) {
            $gps = "GPS: $latitude, $longitude";
            $observations = empty($observations) ? $gps : $observations . " | $gps";
        }

        // Insertion
        $stmt = $pdo->prepare("INSERT INTO controles (
            matricule, type_controle, nom_beneficiaire, new_beneficiaire, 
            lien_parente, date_controle, mention, observations, cree_le
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW())");

        $stmt->execute([
            $matricule,
            $type_controle,
            $beneficiaire ?: null,
            $new_beneficiaire ?: null,
            $lien_parente,
            $mention,
            $observations
        ]);

        $controle_id = $pdo->lastInsertId();

        // Log
        $details = "Matricule: $matricule, Mention: $mention (via Mobile)";
        if (!empty($beneficiaire)) $details .= ", Bénéficiaire: $beneficiaire";
        if (!empty($new_beneficiaire)) $details .= ", Nouveau bénéficiaire: $new_beneficiaire";
        if (!empty($lien_parente)) $details .= ", Lien: $lien_parente";

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'mobile';
        $stmt = $pdo->prepare("INSERT INTO logs (id_utilisateur, action, table_concernee, id_enregistrement, details, ip_address, user_agent) VALUES (?, 'AJOUT_MOBILE', 'controles', ?, ?, ?, ?)");
        $stmt->execute([$user['id_utilisateur'], $controle_id, $details, $ip, $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile App']);

        echo json_encode([
            'success' => true,
            'message' => 'Contrôle enregistré avec succès pour : ' . $militaire_info['noms'],
            'controle_id' => $controle_id
        ]);
    } catch (Exception $e) {
        error_log("API valider error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleHistorique($pdo)
{
    $limit = min(intval($_GET['limit'] ?? 20), 100);
    $offset = max(intval($_GET['offset'] ?? 0), 0);

    try {
        $stmt = $pdo->prepare("SELECT c.*, m.noms, m.grade, m.unite 
                               FROM controles c 
                               LEFT JOIN militaires m ON c.matricule = m.matricule 
                               ORDER BY c.date_controle DESC 
                               LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        $controles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $pdo->query("SELECT COUNT(*) FROM controles");
        $total = $countStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'data' => $controles,
            'total' => intval($total),
            'limit' => $limit,
            'offset' => $offset
        ]);
    } catch (PDOException $e) {
        error_log("API historique error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}

function calculerAge($matricule)
{
    if (strlen($matricule) >= 3 && is_numeric($matricule)) {
        $yy = intval(substr($matricule, 1, 2));
        $currentYear = intval(date('Y'));
        $currentTwoDigits = intval(date('y'));
        $anneeNaissance = ($yy <= $currentTwoDigits) ? (2000 + $yy) : (1900 + $yy);
        $age = $currentYear - $anneeNaissance;
        return ($age >= 0 && $age <= 120) ? $age : null;
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
