<?php
// api/sync_controles.php (serveur central)

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

// Vérification de la méthode et authentification (à adapter selon votre configuration)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupération du payload JSON
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$sourceInstance = $payload['source_instance'] ?? 'inconnu';
$sourceLabel    = $payload['source_label'] ?? 'Source inconnue';
$tables         = $payload['tables'] ?? [];

$equipesRows   = $tables['equipes'] ?? [];
$controlesRows = $tables['controles'] ?? [];

$stats = [
    'equipes'   => ['recus' => count($equipesRows), 'inseres' => 0, 'maj' => 0, 'doublons' => 0],
    'controles' => ['recus' => count($controlesRows), 'inseres' => 0, 'maj' => 0, 'doublons' => 0],
];

$conflicts = [];
$pendingConflicts = 0;

// --- Traitement des équipes ---
foreach ($equipesRows as $equipe) {
    $matricule = $equipe['matricule'] ?? null;
    if (!$matricule) continue;

    // Vérifier si un enregistrement existe déjà (même matricule, même source)
    $stmt = $pdo->prepare("SELECT * FROM equipes WHERE matricule = ? AND COALESCE(db_source, 'central') = ?");
    $stmt->execute([$matricule, $sourceInstance]);
    $existing = $stmt->fetch();

    // Insertion systématique du nouvel enregistrement
    $insertStmt = $pdo->prepare("INSERT INTO equipes 
        (matricule, noms, grade, unites, role, id_source, db_source, sync_status, sync_date, sync_version)
        VALUES (:matricule, :noms, :grade, :unites, :role, :id_source, :db_source, 'synced', NOW(), 1)");
    $insertStmt->execute([
        ':matricule'   => $matricule,
        ':noms'        => $equipe['noms'] ?? '',
        ':grade'       => $equipe['grade'] ?? '',
        ':unites'      => $equipe['unites'] ?? '',
        ':role'        => $equipe['role'] ?? '',
        ':id_source'   => $equipe['id_source'] ?? null,
        ':db_source'   => $sourceInstance,
    ]);

    if ($existing) {
        // Un conflit est détecté : on incrémente le compteur et on enregistre le conflit
        $stats['equipes']['doublons']++;
        $pendingConflicts++;

        $conflictReason = "Conflit de synchronisation : le matricule '$matricule' existe déjà pour la source '$sourceInstance'.";
        recordSyncConflict(
            'equipes',
            $sourceInstance,
            $matricule,
            $equipe['id_source'] ?? null,
            $conflictReason,
            json_encode($equipe, JSON_UNESCAPED_UNICODE),
            json_encode($existing, JSON_UNESCAPED_UNICODE)
        );
    } else {
        $stats['equipes']['inseres']++;
    }
}

// --- Traitement des contrôles ---
foreach ($controlesRows as $controle) {
    $matricule = $controle['matricule'] ?? '';
    $dateControle = $controle['date_controle'] ?? '';
    // Critère de détection de conflit : même matricule, même date, même source
    $stmt = $pdo->prepare("SELECT * FROM controles 
        WHERE matricule = ? AND date_controle = ? AND COALESCE(db_source, 'central') = ?");
    $stmt->execute([$matricule, $dateControle, $sourceInstance]);
    $existing = $stmt->fetch();

    // Insertion systématique
    $insertStmt = $pdo->prepare("INSERT INTO controles 
        (matricule, type_controle, nom_beneficiaire, new_beneficiaire, lien_parente, date_controle, mention, observations, 
         id_source, db_source, sync_status, sync_date, sync_version)
        VALUES (:matricule, :type_controle, :nom_beneficiaire, :new_beneficiaire, :lien_parente, :date_controle, :mention, :observations,
                :id_source, :db_source, 'synced', NOW(), 1)");
    $insertStmt->execute([
        ':matricule'         => $matricule,
        ':type_controle'     => $controle['type_controle'] ?? '',
        ':nom_beneficiaire'  => $controle['nom_beneficiaire'] ?? '',
        ':new_beneficiaire'  => $controle['new_beneficiaire'] ?? '',
        ':lien_parente'      => $controle['lien_parente'] ?? '',
        ':date_controle'     => $dateControle,
        ':mention'           => $controle['mention'] ?? '',
        ':observations'      => $controle['observations'] ?? '',
        ':id_source'         => $controle['id_source'] ?? null,
        ':db_source'         => $sourceInstance,
    ]);

    if ($existing) {
        $stats['controles']['doublons']++;
        $pendingConflicts++;

        $conflictReason = "Conflit de synchronisation : un contrôle pour le matricule '$matricule' à la date '$dateControle' existe déjà.";
        recordSyncConflict(
            'controles',
            $sourceInstance,
            $matricule . '|' . $dateControle,
            $controle['id_source'] ?? null,
            $conflictReason,
            json_encode($controle, JSON_UNESCAPED_UNICODE),
            json_encode($existing, JSON_UNESCAPED_UNICODE)
        );
    } else {
        $stats['controles']['inseres']++;
    }
}

// Mise à jour éventuelle des labels de source
updateSyncSourceLabel($sourceInstance, $sourceLabel, $pdo);

$response = [
    'success'           => true,
    'message'           => $pendingConflicts > 0 
        ? "Synchronisation acceptée avec $pendingConflicts conflit(s) en attente." 
        : 'Synchronisation réussie.',
    'stats'             => $stats,
    'pending_conflicts' => $pendingConflicts,
    'conflict_page'     => app_url('admin/conflicts.php'),
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

// ---------------------------------------------------------------------
// Fonctions utilitaires
// ---------------------------------------------------------------------

/**
 * Enregistre un conflit dans la table sync_conflicts.
 */
function recordSyncConflict($entityType, $sourceInstance, $conflictKey, $remoteRecordId, $reason, $incomingJson, $existingJson) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO sync_conflicts 
            (entity_type, source_instance, conflict_key, remote_record_id, conflict_reason, incoming_payload, existing_payload, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())");
        $stmt->execute([
            $entityType,
            $sourceInstance,
            $conflictKey,
            $remoteRecordId,
            $reason,
            $incomingJson,
            $existingJson
        ]);
    } catch (Exception $e) {
        error_log("Erreur enregistrement conflit: " . $e->getMessage());
    }
}

/**
 * Met à jour ou crée le label associé à une source.
 */
function updateSyncSourceLabel($sourceInstance, $sourceLabel, $pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO sync_source_labels (source_instance, source_label, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE source_label = VALUES(source_label), updated_at = NOW()");
        $stmt->execute([$sourceInstance, $sourceLabel]);
    } catch (Exception $e) {
        error_log("Erreur mise à jour sync_source_labels: " . $e->getMessage());
    }
}