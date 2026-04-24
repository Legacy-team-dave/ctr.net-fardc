<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/server_sync_forwarder.php';

require_login();
check_profil(['ADMIN_IG', 'OPERATEUR']);

if (is_central_mode()) {
    sync_json_response(false, 'Action non autorisée en mode central.', 403, 'CENTRAL_MODE_FORBIDDEN');
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    sync_json_response(false, 'Méthode POST requise.', 405, 'METHOD_NOT_ALLOWED');
}

 $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    sync_json_response(false, 'Token CSRF invalide.', 403, 'CSRF_INVALID');
}

 $rawInput = file_get_contents('php://input');
 $input = json_decode($rawInput ?: '[]', true);
if (!is_array($input)) {
    $input = [];
}

if (!empty($_POST)) {
    $input = array_merge($input, $_POST);
}

 $isProgressRequest = (string) ($input['ajax_progress'] ?? '') === '1';
 $GLOBALS['sync_stream_enabled'] = $isProgressRequest;

if ($isProgressRequest) {
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no');
}

 $serverIp = trim((string) ($input['server_ip'] ?? $input['server_url'] ?? ''));
if ($serverIp === '') {
    sync_json_response(false, 'Adresse du serveur manquante.', 400, 'SERVER_ADDRESS_MISSING');
}

if (!is_valid_server_address($serverIp)) {
    sync_json_response(false, 'Adresse du serveur invalide.', 400, 'SERVER_ADDRESS_INVALID');
}

 $_SESSION['sync_server_ip'] = $serverIp;

 $config = sync_config();
ensure_equipes_sync_columns($pdo);
ensure_sync_log_table($pdo);

// ── Détection automatique de l'instance ID (fichier temp système) ──
 $autoInstanceId = sync_auto_instance_id($pdo);
 $GLOBALS['sync_auto_instance_id'] = $autoInstanceId;

// ── Construction du source_instance : MACHINE + NAVIGATEUR ──
// L'ID machine est garanti unique par PC (fichier dans répertoire temp système)
// L'ID navigateur permet de différencier plusieurs navigateurs/utilisateurs sur le même PC
 $machineId = $autoInstanceId;
 $clientId = trim((string) ($input['client_id'] ?? ''));

if ($clientId !== '') {
    // Combiner machine + navigateur pour unicité maximale
    // Normaliser le clientId UUID : supprimer les tirets et prendre les 16 premiers caractères
    $normalizedClientId = preg_replace('/[^a-z0-9]/i', '', strtolower($clientId));
    $clientIdShort = substr($normalizedClientId, 0, 16);

    // Format final : "a3f2b1c4e5d67890-550e8400e29b41d4" (machine-navigateur)
    $sourceInstance = $machineId . '-' . $clientIdShort;
} else {
    // Pas de clientId (appel API direct sans navigateur) → utiliser seulement l'ID machine
    $sourceInstance = $machineId;
}

// S'assurer que le source_instance ne dépasse pas 50 caractères
 $sourceInstance = substr($sourceInstance, 0, 50);

// ── Libellé de garnison (envoyé par le PC client) ──
 $garnisonLabelFromClient = trim((string) ($input['garnison_label'] ?? ''));
if ($garnisonLabelFromClient !== '') {
    // Met à jour la table sync_source_labels pour que le central affiche le bon libellé
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sync_source_labels (
            source_instance VARCHAR(120) NOT NULL PRIMARY KEY,
            source_label VARCHAR(150) NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $pdo->prepare("INSERT INTO sync_source_labels (source_instance, source_label) VALUES (?, ?) ON DUPLICATE KEY UPDATE source_label = VALUES(source_label)");
        $stmt->execute([$sourceInstance, $garnisonLabelFromClient]);
    } catch (Throwable $e) {}

    $sourceLabel = 'Equipe : ' . $garnisonLabelFromClient;
} else {
    // Fallback : utilisation des garnisons configurées localement
    $selectedGarnisons = function_exists('preferred_garnison_labels') ? preferred_garnison_labels() : [];
    $baseSourceLabel = sync_join_garnison_labels($selectedGarnisons);
    if ($baseSourceLabel === '') {
        $baseSourceLabel = 'SITE LOCAL 01';
    }
    $sourceLabel = sync_build_source_label($baseSourceLabel, $sourceInstance);
}

 $pendingControles = $pdo->query("SELECT * FROM controles WHERE COALESCE(sync_status, 'local') <> 'synced' ORDER BY date_controle ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
 $equipesRows = $pdo->query("SELECT * FROM equipes WHERE COALESCE(sync_status, 'local') <> 'synced' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($pendingControles) && empty($equipesRows)) {
    sync_json_response(true, 'Aucune donnée à synchroniser.', 200, null, [
        'summary' => 'Aucun membre d\'équipe ni contrôle n\'est actuellement en attente de synchronisation.',
        'sync_state' => 'no_data',
        'sent' => [
            'equipes' => 0,
            'controles' => 0,
        ],
        'stats' => [
            'equipes' => 0,
            'controles' => 0,
        ],
    ]);
}

sync_progress_event('progress', [
    'percentage' => 10,
    'step' => 'Analyse des données locales en attente...',
]);

sync_progress_event('progress', [
    'percentage' => 30,
    'step' => sprintf('Préparation de %d membre(s) d\'équipe et %d contrôle(s) à synchroniser...', count($equipesRows), count($pendingControles)),
    'sent' => [
        'equipes' => count($equipesRows),
        'controles' => count($pendingControles),
    ],
]);

 $payload = [
    'source_instance' => $sourceInstance,
    'source_label'   => $sourceLabel,
    'sent_at'        => gmdate('c'),
    'tables'         => [
        'equipes'   => $equipesRows,
        'controles' => $pendingControles,
    ],
    'meta' => [
        'app_mode'           => app_mode(),
        'sync_type'          => 'equipes_controles',
        'total_records'      => count($equipesRows) + count($pendingControles),
        'source_label'       => $sourceLabel,
        'origin_instance_id' => $autoInstanceId,
        'client_id'          => $clientId ?: null,
    ],
];

 $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($payloadJson === false) {
    sync_json_response(false, 'Impossible de préparer les données à envoyer.', 500, 'PAYLOAD_BUILD_FAILED');
}

sync_progress_event('progress', [
    'percentage' => 55,
    'step' => 'Connexion au serveur distant et envoi des données...',
    'sent' => [
        'equipes'   => count($equipesRows),
        'controles' => count($pendingControles),
    ],
]);

 $syncTimeout = min(max(15, (int) ($config['timeout'] ?? 30)), 120);

try {
    $forwardResponse = forward_sync_payload_to_server($serverIp, $payloadJson, $syncTimeout);
} catch (InvalidArgumentException $exception) {
    sync_json_response(false, $exception->getMessage(), 400, 'SERVER_ADDRESS_INVALID');
}

if ($forwardResponse['body'] === false) {
    log_sync_attempt($pdo, array_column($pendingControles, 'id'), array_column($equipesRows, 'id'), 'echec', json_encode([
        'server_ip'      => $serverIp,
        'target_url'     => $forwardResponse['target_url'],
        'attempted_urls' => $forwardResponse['attempted_urls'],
        'error'          => $forwardResponse['transport_error'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    sync_json_response(false, 'Impossible de joindre le service de synchronisation.', 502, 'REMOTE_CONNECTION_FAILED', [
        'transport_error' => $forwardResponse['transport_error'],
        'target_url'      => $forwardResponse['target_url'],
    ]);
}

 $cleanRemoteBody = sync_extract_json_payload((string) ($forwardResponse['body'] ?? ''));
 $remote = json_decode($cleanRemoteBody, true);
if (!is_array($remote)) {
    sync_json_response(false, 'Réponse invalide du serveur distant.', 502, 'REMOTE_INVALID_RESPONSE', [
        'target_url'   => $forwardResponse['target_url'],
        'raw_response' => mb_substr($cleanRemoteBody !== '' ? $cleanRemoteBody : (string) $forwardResponse['body'], 0, 500),
        'json_error'   => json_last_error_msg(),
    ]);
}

 $remoteStats            = is_array($remote['stats'] ?? null) ? $remote['stats'] : [];
 $remoteEquipes          = is_array($remoteStats['equipes'] ?? null) ? $remoteStats['equipes'] : [];
 $remoteControles        = is_array($remoteStats['controles'] ?? null) ? $remoteStats['controles'] : [];
 $duplicatesTotal        = (int) ($remoteEquipes['doublons'] ?? 0) + (int) ($remoteControles['doublons'] ?? 0);
 $invalidTotal           = (int) ($remoteEquipes['invalides'] ?? 0) + (int) ($remoteControles['invalides'] ?? 0);
 $insertedTotal          = (int) ($remoteEquipes['inseres'] ?? 0) + (int) ($remoteControles['inseres'] ?? 0);
 $updatedTotal           = (int) ($remoteEquipes['maj'] ?? 0) + (int) ($remoteControles['maj'] ?? 0);
 $remotePendingConflicts = (int) ($remote['pending_conflicts'] ?? 0);
 $hasRemoteConflicts     = $remotePendingConflicts > 0;
 $isRemoteAlreadySynced  = !($remote['success'] ?? false)
    && $duplicatesTotal > 0
    && $invalidTotal === 0
    && ($insertedTotal + $updatedTotal) === 0;

if (!($remote['success'] ?? false) && !$isRemoteAlreadySynced) {
    log_sync_attempt($pdo, array_column($pendingControles, 'id'), array_column($equipesRows, 'id'), 'echec', json_encode([
        'server_ip'       => $serverIp,
        'target_url'      => $forwardResponse['target_url'],
        'remote_response' => $remote,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $remoteMessage = (string) ($remote['message'] ?? 'Le serveur a rejeté la synchronisation.');
    if (
        stripos($remoteMessage, 'serveur central') !== false
        || stripos($remoteMessage, 'mode central') !== false
    ) {
        $remoteMessage = 'Le serveur saisi ne correspond pas au point de réception central. Utilisez l\'IP/URL du serveur central, par exemple http://IP/ctr-net-fardc_active_front_web.';
    }

    sync_json_response(false, $remoteMessage, 409, 'REMOTE_REJECTED', [
        'target_url'      => $forwardResponse['target_url'],
        'remote_response' => $remote,
    ]);
}

sync_progress_event('progress', [
    'percentage' => 82,
    'step'       => 'Réponse du serveur reçue. Mise à jour des statuts locaux...',
    'sent'       => [
        'equipes'   => count($equipesRows),
        'controles' => count($pendingControles),
    ],
]);

 $controleIds = array_values(array_filter(array_map(static fn($row) => (int) ($row['id'] ?? 0), $pendingControles)));
if (!empty($controleIds)) {
    $placeholders = implode(',', array_fill(0, count($controleIds), '?'));
    $stmtUpdate = $pdo->prepare("UPDATE controles SET sync_status = 'synced', sync_date = NOW() WHERE id IN ($placeholders)");
    $stmtUpdate->execute($controleIds);
}

 $equipeIds = array_values(array_filter(array_map(static fn($row) => (int) ($row['id'] ?? 0), $equipesRows)));
if (!empty($equipeIds)) {
    $placeholders = implode(',', array_fill(0, count($equipeIds), '?'));
    $stmtUpdate = $pdo->prepare("UPDATE equipes SET sync_status = 'synced', sync_date = NOW() WHERE id IN ($placeholders)");
    $stmtUpdate->execute($equipeIds);
}

 $summaryParts = [];
if (!empty($equipesRows)) {
    $summaryParts[] = sprintf('%d membre(s) d\'équipe', count($equipesRows));
}
if (!empty($pendingControles)) {
    $summaryParts[] = sprintf('%d contrôle(s)', count($pendingControles));
}

 $syncState = $hasRemoteConflicts ? 'conflicts_pending' : ($isRemoteAlreadySynced ? 'already_synced' : 'completed');
 $summary = $hasRemoteConflicts
    ? 'Synchronisation transmise : ' . $remotePendingConflicts . ' conflit(s) sont en attente d\'arbitrage sur le serveur central.'
    : ($isRemoteAlreadySynced
        ? 'Synchronisation vérifiée : les données existaient déjà sur le serveur central. Les statuts locaux ont été mis à jour.'
        : 'Synchronisation effectuée : ' . implode(' et ', $summaryParts) . ' envoyé(s) vers ' . $serverIp . '.');

log_sync_attempt($pdo, $controleIds, $equipeIds, 'succes', json_encode([
    'server_ip'       => $serverIp,
    'target_url'      => $forwardResponse['target_url'],
    'remote_response' => $remote,
    'summary'         => $summary,
    'sync_state'      => $syncState,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

audit_action('SYNC_CONTROLES', 'synchronisation', null, $summary);

sync_progress_event('progress', [
    'percentage' => 100,
    'step'       => $hasRemoteConflicts
        ? 'Synchronisation transmise. Des conflits sont en attente sur le serveur central.'
        : ($isRemoteAlreadySynced
            ? 'Les données étaient déjà présentes sur le serveur. Mise à jour locale terminée.'
            : 'Synchronisation finalisée avec succès.'),
    'sent' => [
        'equipes'   => count($equipesRows),
        'controles' => count($pendingControles),
    ],
]);

sync_json_response(true, $hasRemoteConflicts
    ? 'Synchronisation transmise avec conflits en attente.'
    : ($isRemoteAlreadySynced ? 'Les données existaient déjà sur le serveur central.' : 'Synchronisation terminée avec succès.'), 200, null, [
    'summary'           => $summary,
    'sync_state'        => $syncState,
    'pending_conflicts' => $remotePendingConflicts,
    'conflict_page'     => $remote['conflict_page'] ?? null,
    'target_url'        => $forwardResponse['target_url'],
    'sent'              => [
        'equipes'   => count($equipesRows),
        'controles' => count($pendingControles),
    ],
    'stats' => $remote['stats'] ?? [
        'equipes'   => ['recus' => count($equipesRows), 'inseres' => count($equipesRows), 'maj' => 0],
        'controles' => ['recus' => count($pendingControles), 'inseres' => count($pendingControles), 'maj' => 0],
    ],
]);

// =====================================================================
// DÉTECTION AUTOMATIQUE DE L'INSTANCE ID — VERSION ROBUSTE
// =====================================================================
//
// Problème résolu :
//   Quand l'application est clonée (image système, copie de dossier),
//   la BDD locale et le fichier instance_id.txt sont copiés avec,
//   ce qui donne le même ID à tous les PC clonés → fusion des données.
//
// Solution :
//   Utiliser le répertoire temporaire SYSTÈME comme source de vérité.
//   Ce répertoire n'est JAMAIS copié lors du clonage :
//     - Windows : C:\Users\[user]\AppData\Local\Temp
//     - Linux   : /tmp
//
//   Chaque PC aura donc son propre fichier avec un ID unique.
//
// Format de l'ID : 16 caractères hexadécimaux (ex: "a3f2b1c4e5d67890")
// =====================================================================

function sync_auto_instance_id(PDO $pdo): string
{
    // Cache en mémoire pour la requête courante
    if (!empty($GLOBALS['sync_cached_instance_id'])) {
        return $GLOBALS['sync_cached_instance_id'];
    }

    // ── 1. SOURCE DE VÉRITÉ : Fichier dans le répertoire temporaire système ──
    $tempDir = sys_get_temp_dir();
    $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'ctr_ops_fardc_unique_machine_id';

    if (file_exists($tempFile) && is_readable($tempFile)) {
        $tempId = trim(file_get_contents($tempFile));
        if ($tempId !== '' && strlen($tempId) >= 16 && ctype_alnum($tempId)) {
            $GLOBALS['sync_cached_instance_id'] = $tempId;
            sync_persist_instance_id($pdo, $tempId);
            return $tempId;
        }
    }

    // ── 2. Fallback : BDD locale (anciennes installations) ──
    $dbId = sync_read_instance_id_from_db($pdo);
    if ($dbId !== '' && strlen($dbId) >= 16 && ctype_alnum($dbId) && !file_exists($tempFile)) {
        @file_put_contents($tempFile, $dbId, LOCK_EX);
        $GLOBALS['sync_cached_instance_id'] = $dbId;
        return $dbId;
    }

    // ── 3. Fallback : Fichier dans l'application ──
    $appFile = __DIR__ . '/../instance_id.txt';
    if (file_exists($appFile) && is_readable($appFile) && !file_exists($tempFile)) {
        $fileId = trim(file_get_contents($appFile));
        if ($fileId !== '' && strlen($fileId) >= 8) {
            @file_put_contents($tempFile, $fileId, LOCK_EX);
            sync_persist_instance_id($pdo, $fileId);
            $GLOBALS['sync_cached_instance_id'] = $fileId;
            return $fileId;
        }
    }

    // ── 4. GÉNÉRATION D'UN NOUVEL ID UNIQUE ──
    $instanceId = strtolower(bin2hex(random_bytes(8)));

    @file_put_contents($tempFile, $instanceId, LOCK_EX);
    sync_persist_instance_id($pdo, $instanceId);
    @file_put_contents($appFile, $instanceId, LOCK_EX);

    $GLOBALS['sync_cached_instance_id'] = $instanceId;
    return $instanceId;
}

/**
 * Lit l'ID instance depuis la BDD locale.
 */
function sync_read_instance_id_from_db(PDO $pdo): string
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sync_local_config (
            config_key VARCHAR(100) NOT NULL PRIMARY KEY,
            config_value TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $pdo->prepare("SELECT config_value FROM sync_local_config WHERE config_key = 'instance_id' LIMIT 1");
        $stmt->execute();
        $value = $stmt->fetchColumn();
        return $value !== false ? trim((string) $value) : '';
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Persiste l'ID instance dans la BDD locale.
 */
function sync_persist_instance_id(PDO $pdo, string $instanceId): void
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sync_local_config (
            config_key VARCHAR(100) NOT NULL PRIMARY KEY,
            config_value TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $pdo->prepare("INSERT INTO sync_local_config (config_key, config_value) VALUES ('instance_id', ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()");
        $stmt->execute([$instanceId]);
    } catch (Throwable $e) {
        // Silencieux — le fichier temp est la source de vérité
    }
}

// =====================================================================
// FONCTIONS UTILITAIRES
// =====================================================================

function ensure_sync_log_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS synchronisation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        controle_ids TEXT NULL,
        equipe_ids TEXT NULL,
        nb_controles INT NOT NULL DEFAULT 0,
        nb_equipes INT NOT NULL DEFAULT 0,
        statut ENUM('succes','echec','en_attente') NOT NULL DEFAULT 'en_attente',
        details LONGTEXT NULL,
        utilisateur_id INT NULL,
        cree_le TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sync_statut (statut),
        INDEX idx_sync_date (cree_le)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach ([
        "ALTER TABLE synchronisation ADD COLUMN equipe_ids TEXT NULL AFTER controle_ids",
        "ALTER TABLE synchronisation ADD COLUMN nb_equipes INT NOT NULL DEFAULT 0 AFTER nb_controles"
    ] as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate column name') === false) {
                throw $e;
            }
        }
    }
}

function ensure_equipes_sync_columns(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS equipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        matricule VARCHAR(50) NULL,
        noms VARCHAR(150) NOT NULL,
        grade VARCHAR(50) NOT NULL,
        unites VARCHAR(150) NULL,
        role VARCHAR(50) NOT NULL,
        id_source INT NULL,
        db_source VARCHAR(50) NULL DEFAULT 'local',
        sync_status ENUM('local','synced') NOT NULL DEFAULT 'local',
        sync_date DATETIME NULL,
        sync_version INT NOT NULL DEFAULT 1,
        INDEX idx_equipes_sync_status (sync_status),
        INDEX idx_equipes_source (db_source, id_source),
        INDEX idx_equipes_matricule (matricule)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach ([
        "ALTER TABLE equipes ADD COLUMN matricule VARCHAR(50) NULL AFTER id",
        "ALTER TABLE equipes ADD COLUMN unites VARCHAR(150) NULL AFTER grade",
        "ALTER TABLE equipes ADD COLUMN id_source INT NULL AFTER role",
        "ALTER TABLE equipes ADD COLUMN db_source VARCHAR(50) NULL DEFAULT 'local' AFTER id_source",
        "ALTER TABLE equipes ADD COLUMN sync_status ENUM('local','synced') NOT NULL DEFAULT 'local' AFTER db_source",
        "ALTER TABLE equipes ADD COLUMN sync_date DATETIME NULL AFTER sync_status",
        "ALTER TABLE equipes ADD COLUMN sync_version INT NOT NULL DEFAULT 1 AFTER sync_date"
    ] as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate column name') === false) {
                throw $e;
            }
        }
    }
}

function sync_extract_json_payload(string $body): string
{
    $body = preg_replace('/^\xEF\xBB\xBF/u', '', $body) ?? $body;
    $body = ltrim($body, "\x00..\x20");

    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        return $body;
    }

    $jsonStart = strpos($body, '{');
    $jsonEnd = strrpos($body, '}');
    if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd >= $jsonStart) {
        return substr($body, $jsonStart, $jsonEnd - $jsonStart + 1);
    }

    return $body;
}

function is_valid_server_address(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    return (bool) preg_match('/^(https?:\/\/)?([a-zA-Z0-9.-]+|\[[0-9a-fA-F:]+\])(?::\d+)?(\/.*)?$/', $value);
}

function sync_join_garnison_labels(array $garnisons): string
{
    $labels = [];
    foreach ($garnisons as $garnison) {
        $garnison = trim((string) $garnison);
        if ($garnison !== '' && !in_array($garnison, $labels, true)) {
            $labels[] = $garnison;
        }
    }

    return implode(' • ', $labels);
}

function sync_build_source_label(string $baseLabel, string $sourceInstance): string
{
    $baseLabel = trim($baseLabel);
    if ($baseLabel === '') {
        $baseLabel = 'SITE LOCAL 01';
    }

    return mb_substr('Equipe : ' . $baseLabel, 0, 150);
}

function sync_progress_event(string $event, array $payload = []): void
{
    if (empty($GLOBALS['sync_stream_enabled'])) {
        return;
    }

    echo 'data: ' . json_encode(array_merge([
        'event' => $event,
    ], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

function sync_json_response(bool $success, string $message, int $httpCode = 200, ?string $errorCode = null, array $data = []): void
{
    http_response_code($httpCode);

    $response = [
        'success'   => $success,
        'message'   => $message,
        'timestamp' => gmdate('c'),
    ];

    if ($errorCode !== null) {
        $response['error_code'] = $errorCode;
    }

    if (!empty($data)) {
        $response['data'] = $data;
    }

    if (!empty($GLOBALS['sync_stream_enabled'])) {
        sync_progress_event($success ? 'complete' : 'error', $response);
        exit;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function log_sync_attempt(PDO $pdo, array $controleIds, array $equipeIds, string $statut, string $details = ''): void
{
    $stmt = $pdo->prepare("INSERT INTO synchronisation (controle_ids, equipe_ids, nb_controles, nb_equipes, statut, details, utilisateur_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        json_encode($controleIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($equipeIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        count($controleIds),
        count($equipeIds),
        $statut,
        $details,
        $_SESSION['user_id'] ?? null,
    ]);
}