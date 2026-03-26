<?php
// ajax/litiges.php
require_once '../includes/functions.php';
require_login();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ============================================
// LISTE DES LITIGES
// ============================================
if ($action === 'list') {
    $sql = "SELECT id, matricule, noms, grade, type_controle, nom_beneficiaire, lien_parente, garnison, province, date_controle, observations, cree_le 
            FROM litiges 
            ORDER BY date_controle DESC";
    $stmt = $pdo->query($sql);
    $litiges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'litiges' => $litiges]);
    exit;
}

// ============================================
// RÉCUPÉRATION D'UN LITIGE PAR ID
// ============================================
if ($action === 'get') {
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM litiges WHERE id = ?");
    $stmt->execute([$id]);
    $litige = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($litige) {
        echo json_encode(['success' => true, 'litige' => $litige]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Litige introuvable']);
    }
    exit;
}

// ============================================
// CRÉATION D'UN LITIGE
// ============================================
if ($action === 'create') {
    // Récupération des données
    $matricule        = $_POST['matricule'] ?? '';
    $noms             = $_POST['noms'] ?? '';
    $grade            = $_POST['grade'] ?? null;           // nouveau champ
    $type_controle    = $_POST['type_controle'] ?? '';
    $nom_beneficiaire = $_POST['nom_beneficiaire'] ?? '';
    $lien_parente     = $_POST['lien_parente'] ?? null;
    $garnison         = $_POST['garnison'] ?? null;
    $province         = $_POST['province'] ?? null;
    $date_controle    = $_POST['date_controle'] ?? '';
    $observations     = $_POST['observations'] ?? null;

    // Validation du type de contrôle
    if (!in_array($type_controle, ['Militaire', 'Bénéficiaire'])) {
        echo json_encode(['success' => false, 'message' => 'Type de contrôle invalide.']);
        exit;
    }

    // Validation des champs obligatoires communs
    if (empty($matricule) || empty($noms) || empty($type_controle) || empty($date_controle)) {
        echo json_encode(['success' => false, 'message' => 'Matricule, noms, type de contrôle et date sont obligatoires']);
        exit;
    }

    // Validation conditionnelle selon le type
    if ($type_controle === 'Bénéficiaire') {
        if (empty(trim($nom_beneficiaire))) {
            echo json_encode(['success' => false, 'message' => 'Le nom du bénéficiaire est obligatoire pour un contrôle de type Bénéficiaire']);
            exit;
        }
        if (empty($lien_parente)) {
            echo json_encode(['success' => false, 'message' => 'Le lien de parenté est obligatoire pour un contrôle de type Bénéficiaire']);
            exit;
        }
    } else { // Militaire
        // Valeurs par défaut
        $nom_beneficiaire = 'Militaire lui-même';
        $lien_parente = null;
    }

    // Insertion (avec grade)
    $sql = "INSERT INTO litiges 
            (matricule, noms, grade, type_controle, nom_beneficiaire, lien_parente, garnison, province, date_controle, observations)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        $matricule,
        $noms,
        $grade,
        $type_controle,
        $nom_beneficiaire,
        $lien_parente,
        $garnison,
        $province,
        $date_controle,
        $observations
    ]);

    if ($success) {
        mark_sync_dirty();
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'insertion']);
    }
    exit;
}

// ============================================
// MISE À JOUR D'UN LITIGE
// ============================================
if ($action === 'update') {
    $id               = $_POST['id'] ?? 0;
    $matricule        = $_POST['matricule'] ?? '';
    $noms             = $_POST['noms'] ?? '';
    $grade            = $_POST['grade'] ?? null;           // nouveau champ
    $type_controle    = $_POST['type_controle'] ?? '';
    $nom_beneficiaire = $_POST['nom_beneficiaire'] ?? '';
    $lien_parente     = $_POST['lien_parente'] ?? null;
    $garnison         = $_POST['garnison'] ?? null;
    $province         = $_POST['province'] ?? null;
    $observations     = $_POST['observations'] ?? null;

    // Validation du type de contrôle
    if (!in_array($type_controle, ['Militaire', 'Bénéficiaire'])) {
        echo json_encode(['success' => false, 'message' => 'Type de contrôle invalide.']);
        exit;
    }

    // Validation des champs obligatoires communs
    if (empty($id) || empty($matricule) || empty($noms) || empty($type_controle)) {
        echo json_encode(['success' => false, 'message' => 'Données incomplètes']);
        exit;
    }

    // Validation conditionnelle selon le type
    if ($type_controle === 'Bénéficiaire') {
        if (empty(trim($nom_beneficiaire))) {
            echo json_encode(['success' => false, 'message' => 'Le nom du bénéficiaire est obligatoire pour un contrôle de type Bénéficiaire']);
            exit;
        }
        if (empty($lien_parente)) {
            echo json_encode(['success' => false, 'message' => 'Le lien de parenté est obligatoire pour un contrôle de type Bénéficiaire']);
            exit;
        }
    } else { // Militaire
        $nom_beneficiaire = 'Militaire lui-même';
        $lien_parente = null;
    }

    // Requête UPDATE (avec grade, sans date_controle)
    $sql = "UPDATE litiges 
            SET matricule = ?, noms = ?, grade = ?, type_controle = ?, nom_beneficiaire = ?, lien_parente = ?, garnison = ?, province = ?, observations = ?
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        $matricule,
        $noms,
        $grade,
        $type_controle,
        $nom_beneficiaire,
        $lien_parente,
        $garnison,
        $province,
        $observations,
        $id
    ]);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
    exit;
}

// ============================================
// SUPPRESSION D'UN LITIGE
// ============================================
if ($action === 'delete') {
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM litiges WHERE id = ?");
    $success = $stmt->execute([$id]);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
    }
    exit;
}

// Si aucune action valide
echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
