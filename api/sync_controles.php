<?php

/**
 * API : Synchronisation sélective des contrôles vers le serveur central.
 * Reçoit un tableau d'IDs de contrôles, les envoie au central.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';
require_login();
check_profil(['ADMIN_IG', 'OPERATEUR']);

if (is_central_mode()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Action non autorisée en mode central.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode POST requise.']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ids = $input['ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucun contrôle sélectionné.']);
    exit;
}

// Nettoyer et valider les IDs (entiers uniquement)
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDs invalides.']);
    exit;
}

$config = sync_config();
if (empty($config['central_url']) || empty($config['shared_token'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration de synchronisation incomplète.']);
    exit;
}

// Récupérer les contrôles sélectionnés
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM controles WHERE id IN ($placeholders)");
$stmt->execute($ids);
$controles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($controles)) {
    echo json_encode(['success' => false, 'message' => 'Aucun contrôle trouvé.']);
    exit;
}

// Récupérer les militaires associés (pour s'assurer qu'ils existent côté central)
$matricules = array_unique(array_column($controles, 'matricule'));
$placeholdersMat = implode(',', array_fill(0, count($matricules), '?'));
$stmtMil = $pdo->prepare("SELECT * FROM militaires WHERE matricule IN ($placeholdersMat)");
$stmtMil->execute(array_values($matricules));
$militaires = $stmtMil->fetchAll(PDO::FETCH_ASSOC);

// Construire le payload
$payload = [
    'source_instance' => $config['instance_id'],
    'sent_at' => gmdate('c'),
    'tables' => [
        'militaires' => $militaires,
        'controles' => $controles
    ],
    'meta' => [
        'app_mode' => app_mode(),
        'total_records' => count($militaires) + count($controles),
        'sync_type' => 'controles_selectifs'
    ]
];

// Enregistrer localement dans la table synchronisation
ensure_sync_log_table($pdo);

// Envoyer au serveur central
$url = rtrim($config['central_url'], '/') . '/api/synchronisation.php?action=receive';
$headers = [
    'Authorization: Bearer ' . $config['shared_token'],
    'X-Sync-Instance: ' . $config['instance_id']
];

// Inclure les fonctions HTTP depuis synchronisation.php
require_once __DIR__ . '/synchronisation.php';

$response = http_post_json($url, $payload, $headers, $config['timeout']);

if ($response === false) {
    // Sauvegarder les IDs comme "en attente" pour retry ultérieur
    log_sync_attempt($pdo, $ids, 'echec', 'Impossible de contacter le serveur central.');
    echo json_encode(['success' => false, 'message' => 'Impossible de contacter le serveur central.']);
    exit;
}

$result = json_decode($response, true);

if (is_array($result) && ($result['success'] ?? false)) {
    // Marquer les contrôles comme synchronisés
    log_sync_attempt($pdo, $ids, 'succes', json_encode($result['stats'] ?? []));
    audit_action(
        'SYNC_CONTROLES',
        'controles',
        null,
        count($controles) . ' contrôles synchronisés vers le central — Instance: ' . $config['instance_id']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Synchronisation réussie.',
        'stats' => $result['stats'] ?? [],
        'synced_count' => count($controles)
    ]);
} else {
    log_sync_attempt($pdo, $ids, 'echec', $result['message'] ?? 'Erreur inconnue');
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Erreur lors de la synchronisation.'
    ]);
}

// ============================================
// Fonctions utilitaires
// ============================================

function ensure_sync_log_table(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS synchronisation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        controle_ids TEXT NOT NULL,
        nb_controles INT NOT NULL DEFAULT 0,
        statut ENUM('succes','echec','en_attente') NOT NULL DEFAULT 'en_attente',
        details TEXT NULL,
        utilisateur_id INT NULL,
        cree_le TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sync_statut (statut),
        INDEX idx_sync_date (cree_le)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function log_sync_attempt(PDO $pdo, array $ids, string $statut, string $details = '')
{
    $stmt = $pdo->prepare("INSERT INTO synchronisation (controle_ids, nb_controles, statut, details, utilisateur_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        json_encode($ids),
        count($ids),
        $statut,
        $details,
        $_SESSION['user_id'] ?? null
    ]);
}
