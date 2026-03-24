<?php

/**
 * Endpoint de polling pour la liste des contrôles côté web.
 * Vérifie si de nouveaux contrôles ont été ajoutés depuis un timestamp donné.
 * Utilisé par liste.php pour actualiser automatiquement après un contrôle mobile.
 */
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../config/database.php';

// Vérifier que l'utilisateur est connecté via session web
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$since_id = intval($_GET['since_id'] ?? 0);

try {
    // Récupérer les contrôles ajoutés après l'ID donné
    $stmt = $pdo->prepare("
        SELECT c.id, c.matricule, c.mention, c.type_controle, c.lien_parente,
               c.nom_beneficiaire, c.new_beneficiaire, c.observations,
               c.date_controle, m.noms, m.grade
        FROM controles c
        LEFT JOIN militaires m ON c.matricule = m.matricule
        WHERE c.id > ?
        ORDER BY c.id ASC
        LIMIT 20
    ");
    $stmt->execute([$since_id]);
    $nouveaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer le dernier ID global
    $stmt_max = $pdo->query("SELECT COALESCE(MAX(id), 0) FROM controles");
    $max_id = intval($stmt_max->fetchColumn());

    // Compter le total
    $stmt_count = $pdo->query("SELECT COUNT(*) FROM controles");
    $total = intval($stmt_count->fetchColumn());

    echo json_encode([
        'success' => true,
        'nouveaux' => $nouveaux,
        'count' => count($nouveaux),
        'max_id' => $max_id,
        'total' => $total
    ]);
} catch (PDOException $e) {
    error_log("Poll controles error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false]);
}
