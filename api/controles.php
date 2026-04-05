<?php

/**
 * API Contrôles pour les applications mobiles CONTROLEUR / ENROLEUR
 * Endpoints: GET/POST /api/controles.php?action=search|valider|historique|enroll_vivant
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
require_once __DIR__ . '/../includes/functions.php';

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
    case 'militaire':
        handleMilitaire($pdo);
        break;
    case 'qr_lookup':
        handleQrLookup($pdo);
        break;
    case 'valider':
        handleValider($pdo, $user);
        break;
    case 'historique':
        handleHistorique($pdo);
        break;
    case 'enroll_vivant':
        handleEnrollVivant($pdo, $user);
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
        if ($user && is_mobile_only_profile($user['profil'])) {
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

function handleMilitaire($pdo)
{
    $matricule = trim($_GET['matricule'] ?? '');
    if ($matricule === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Matricule requis']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT matricule, noms, grade, unite, garnison, province, statut, categorie, beneficiaire
                               FROM militaires
                               WHERE matricule = ?
                               LIMIT 1");
        $stmt->execute([$matricule]);
        $militaire = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$militaire) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Militaire introuvable']);
            return;
        }

        $check = $pdo->prepare("SELECT id FROM controles WHERE matricule = ? LIMIT 1");
        $check->execute([$matricule]);

        $militaire['age'] = calculerAge($matricule);
        $militaire['deja_controle'] = (bool) $check->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $militaire,
        ]);
    } catch (PDOException $e) {
        error_log("API militaire error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}

function handleQrLookup($pdo)
{
    $controleId = (int) ($_GET['controle_id'] ?? 0);
    $matricule = trim($_GET['matricule'] ?? '');

    if ($controleId <= 0 && $matricule === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Identifiant QR requis']);
        return;
    }

    try {
        if ($controleId > 0 && $matricule !== '') {
            $stmt = $pdo->prepare("SELECT c.id AS controle_id, c.matricule, c.type_controle, c.nom_beneficiaire, c.new_beneficiaire,
                                          c.lien_parente, c.date_controle, c.mention, c.observations,
                                          m.noms, m.grade, m.unite, m.garnison, m.province, m.categorie
                                   FROM controles c
                                   INNER JOIN militaires m ON c.matricule = m.matricule
                                   WHERE c.id = ? AND c.matricule = ?
                                   LIMIT 1");
            $stmt->execute([$controleId, $matricule]);
        } elseif ($controleId > 0) {
            $stmt = $pdo->prepare("SELECT c.id AS controle_id, c.matricule, c.type_controle, c.nom_beneficiaire, c.new_beneficiaire,
                                          c.lien_parente, c.date_controle, c.mention, c.observations,
                                          m.noms, m.grade, m.unite, m.garnison, m.province, m.categorie
                                   FROM controles c
                                   INNER JOIN militaires m ON c.matricule = m.matricule
                                   WHERE c.id = ?
                                   LIMIT 1");
            $stmt->execute([$controleId]);
        } else {
            $stmt = $pdo->prepare("SELECT c.id AS controle_id, c.matricule, c.type_controle, c.nom_beneficiaire, c.new_beneficiaire,
                                          c.lien_parente, c.date_controle, c.mention, c.observations,
                                          m.noms, m.grade, m.unite, m.garnison, m.province, m.categorie
                                   FROM controles c
                                   INNER JOIN militaires m ON c.matricule = m.matricule
                                   WHERE c.matricule = ?
                                   ORDER BY c.date_controle DESC, c.id DESC
                                   LIMIT 1");
            $stmt->execute([$matricule]);
        }

        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'QR ou contrôle introuvable']);
            return;
        }

        if (($record['type_controle'] ?? '') !== 'Militaire') {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'QR refusé : seuls les militaires contrôlés vivants peuvent être enrôlés.']);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'source' => 'ctr.net-fardc',
                'payload_version' => 2,
                'controle_id' => (int) ($record['controle_id'] ?? 0),
                'matricule' => $record['matricule'] ?? '',
                'noms' => $record['noms'] ?? '',
                'grade' => $record['grade'] ?? '',
                'unite' => $record['unite'] ?? '',
                'garnison' => $record['garnison'] ?? '',
                'province' => $record['province'] ?? '',
                'categorie' => $record['categorie'] ?? '',
                'type_controle' => $record['type_controle'] ?? '',
                'lien_parente' => $record['lien_parente'] ?? '',
                'nom_beneficiaire' => $record['nom_beneficiaire'] ?? '',
                'new_beneficiaire' => $record['new_beneficiaire'] ?? '',
                'date_controle' => $record['date_controle'] ?? '',
                'mention' => $record['mention'] ?? '',
                'observations' => $record['observations'] ?? '',
            ]
        ]);
    } catch (PDOException $e) {
        error_log("API qr_lookup error: " . $e->getMessage());
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

        if (function_exists('mark_sync_dirty')) {
            mark_sync_dirty('controles', (int) $controle_id);
        }

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

function handleEnrollVivant($pdo, $user)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode POST requise']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payload JSON invalide']);
        return;
    }

    $matricule = trim($input['matricule'] ?? '');
    $noms = trim($input['noms'] ?? '');
    $grade = trim($input['grade'] ?? '');
    $unite = trim($input['unite'] ?? '');
    $garnison = trim($input['garnison'] ?? '');
    $province = trim($input['province'] ?? '');
    $categorie_input = trim($input['categorie'] ?? '');
    $observations = trim($input['observations'] ?? '');
    $photo_data = trim($input['photo_data'] ?? '');
    $empreinte_gauche_data = trim($input['empreinte_gauche_data'] ?? '');
    $empreinte_droite_data = trim($input['empreinte_droite_data'] ?? '');
    $appareil_id = substr(trim($input['appareil_id'] ?? ($input['device_label'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Tablette'))), 0, 255);
    $cree_le_input = trim($input['cree_le'] ?? '');
    $sync_status = strtolower(trim($input['sync_status'] ?? 'local')) === 'synced' ? 'synced' : 'local';
    $qr_payload_array = is_array($input['qr_payload'] ?? null) ? $input['qr_payload'] : null;
    $qr_payload = !empty($qr_payload_array)
        ? json_encode($qr_payload_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

    if ($matricule === '' || $noms === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Matricule et noms du militaire requis']);
        return;
    }

    if ($photo_data === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La photo du militaire est obligatoire']);
        return;
    }

    if ($empreinte_gauche_data === '' && $empreinte_droite_data === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Au moins une empreinte doit être capturée']);
        return;
    }

    if (is_array($qr_payload_array) && !empty($qr_payload_array['type_controle']) && $qr_payload_array['type_controle'] !== 'Militaire') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'QR refusé : seuls les militaires contrôlés vivants peuvent être enrôlés.']);
        return;
    }

    try {
        ensureEnrollementsVivantsTable($pdo);

        $stmt = $pdo->prepare("SELECT noms, grade, unite, garnison, province, categorie FROM militaires WHERE matricule = ? LIMIT 1");
        $stmt->execute([$matricule]);
        $militaire = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $noms = $noms ?: ($militaire['noms'] ?? '');
        $grade = $grade ?: ($militaire['grade'] ?? '');
        $unite = $unite ?: ($militaire['unite'] ?? '');
        $garnison = $garnison ?: ($militaire['garnison'] ?? '');
        $province = $province ?: ($militaire['province'] ?? '');
        $categorie = $categorie_input ?: trim($militaire['categorie'] ?? 'ACTIF');
        $cree_le = $cree_le_input !== '' && strtotime($cree_le_input) !== false
            ? date('Y-m-d H:i:s', strtotime($cree_le_input))
            : date('Y-m-d H:i:s');

        $insert = $pdo->prepare("INSERT INTO enrollements_vivants (
            matricule, noms, grade, unite, garnison, province, categorie,
            qr_payload, photo_data, empreinte_gauche_data, empreinte_droite_data,
            observations, appareil_id, cree_le, sync_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $insert->execute([
            $matricule,
            $noms,
            $grade ?: null,
            $unite ?: null,
            $garnison ?: null,
            $province ?: null,
            $categorie ?: null,
            $qr_payload,
            $photo_data,
            $empreinte_gauche_data ?: null,
            $empreinte_droite_data ?: null,
            $observations ?: null,
            $appareil_id,
            $cree_le,
            $sync_status
        ]);

        $enrollement_id = (int) $pdo->lastInsertId();

        $details = "Enrôlement vivant mobile | Matricule: {$matricule} | Nom: {$noms}";
        $logStmt = $pdo->prepare("INSERT INTO logs (id_utilisateur, action, table_concernee, id_enregistrement, details, ip_address, user_agent) VALUES (?, 'ENROLLEMENT_MOBILE', 'enrollements_vivants', ?, ?, ?, ?)");
        $logStmt->execute([
            $user['id_utilisateur'],
            $enrollement_id,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'mobile',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile App'
        ]);

        if (function_exists('mark_sync_dirty')) {
            mark_sync_dirty('enrollements_vivants', $enrollement_id);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Enrôlement du militaire vivant enregistré avec succès',
            'data' => ['enrollement_id' => $enrollement_id]
        ]);
    } catch (Exception $e) {
        error_log("API enroll_vivant error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function ensureEnrollementsVivantsTable($pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS enrollements_vivants (
        id INT NOT NULL AUTO_INCREMENT,
        matricule VARCHAR(20) NOT NULL,
        noms VARCHAR(150) NOT NULL,
        grade VARCHAR(80) DEFAULT NULL,
        unite VARCHAR(150) DEFAULT NULL,
        garnison VARCHAR(150) DEFAULT NULL,
        province VARCHAR(100) DEFAULT NULL,
        categorie VARCHAR(80) DEFAULT NULL,
        qr_payload LONGTEXT DEFAULT NULL,
        photo_data LONGTEXT NOT NULL,
        empreinte_gauche_data LONGTEXT DEFAULT NULL,
        empreinte_droite_data LONGTEXT DEFAULT NULL,
        observations TEXT DEFAULT NULL,
        appareil_id VARCHAR(255) DEFAULT NULL,
        cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        sync_status ENUM('local', 'synced') NOT NULL DEFAULT 'local',
        PRIMARY KEY (id),
        KEY idx_enrol_matricule (matricule),
        KEY idx_enrol_date (cree_le)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $requiredColumns = [
        'categorie' => "ALTER TABLE enrollements_vivants ADD COLUMN categorie VARCHAR(80) DEFAULT NULL AFTER province",
        'qr_payload' => "ALTER TABLE enrollements_vivants ADD COLUMN qr_payload LONGTEXT DEFAULT NULL AFTER categorie",
        'photo_data' => "ALTER TABLE enrollements_vivants ADD COLUMN photo_data LONGTEXT NOT NULL AFTER qr_payload",
        'empreinte_gauche_data' => "ALTER TABLE enrollements_vivants ADD COLUMN empreinte_gauche_data LONGTEXT DEFAULT NULL AFTER photo_data",
        'empreinte_droite_data' => "ALTER TABLE enrollements_vivants ADD COLUMN empreinte_droite_data LONGTEXT DEFAULT NULL AFTER empreinte_gauche_data",
        'observations' => "ALTER TABLE enrollements_vivants ADD COLUMN observations TEXT DEFAULT NULL AFTER empreinte_droite_data",
        'appareil_id' => "ALTER TABLE enrollements_vivants ADD COLUMN appareil_id VARCHAR(255) DEFAULT NULL AFTER observations",
        'cree_le' => "ALTER TABLE enrollements_vivants ADD COLUMN cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER appareil_id",
        'sync_status' => "ALTER TABLE enrollements_vivants ADD COLUMN sync_status ENUM('local', 'synced') NOT NULL DEFAULT 'local' AFTER cree_le"
    ];

    foreach ($requiredColumns as $columnName => $alterSql) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enrollements_vivants' AND COLUMN_NAME = ?");
        $stmt->execute([$columnName]);
        $columnExists = (int) $stmt->fetchColumn() > 0;
        if (!$columnExists) {
            $pdo->exec($alterSql);
        }
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
