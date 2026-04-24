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

/*
 * ========================================================================
 * POINT CRITIQUE : l'instance_id est lu depuis le FICHIER LOCAL.
 *
 * Pourquoi pas depuis la BDD ?
 * -------------------------------
 * Quand on clone le repo depuis Git, la BDD est copiée avec
 * l'instance_id du PC d'origine. Si on lit depuis la BDD, tous les
 * PCs clonés auront le même ID → même source_instance → les données
 * se mélangent sur le serveur central → une seule carte affichée.
 *
 * Le fichier instance_id.txt est local à chaque machine et n'est
 * JAMAIS versionné dans Git (ajouté dans .gitignore). Donc :
 *   - Clone Git = fichier absent = détection garantie de clone
 *   - Fichier présent = ID valide de ce PC précis
 *
 * La BDD ne sert qu'à mémoriser l'ID pour reference,
 * jamais à le décider.
 * ========================================================================
 */
 $autoInstanceId = sync_auto_instance_id($pdo);
 $GLOBALS['sync_auto_instance_id'] = $autoInstanceId;

 $selectedGarnisons = function_exists('preferred_garnison_labels') ? preferred_garnison_labels() : [];
 $baseSourceLabel = sync_join_garnison_labels($selectedGarnisons);
if ($baseSourceLabel === '') {
    $baseSourceLabel = 'SITE LOCAL 01';
}

 $pendingControles = $pdo->query("SELECT * FROM controles WHERE COALESCE(sync_status, 'local') <> 'synced' ORDER BY date_controle ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
 $equipesRows = $pdo->query("SELECT * FROM equipes WHERE COALESCE(sync_status, 'local') <> 'synced' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

 $sourceInstance = sync_build_source_instance($autoInstanceId);
 $sourceLabel = sync_build_source_label($baseSourceLabel, $sourceInstance);

if (empty($pendingControles) && empty($equipesRows)) {
    sync_json_response(true, 'Aucune donnée à synchroniser.', 200, null, [
        'summary' => 'Aucun membre d\'équipe ni contrôle n\'est actuellement en attente de synchronisation.',
        'sync_state' => 'no_data',
        'sent' => ['equipes' => 0, 'controles' => 0],
        'stats' => ['equipes' => 0, 'controles' => 0],
        'instance_id' => $autoInstanceId,
    ]);
}

sync_progress_event('progress', [
    'percentage' => 10,
    'step' => 'Analyse des données locales en attente...',
    'instance_id' => $autoInstanceId,
]);

sync_progress_event('progress', [
    'percentage' => 30,
    'step' => sprintf('Préparation de %d membre(s) d\'équipe et %d contrôle(s)...', count($equipesRows), count($pendingControles)),
    'sent' => ['equipes' => count($equipesRows), 'controles' => count($pendingControles)],
    'instance_id' => $autoInstanceId,
]);

 $payload = [
    'source_instance' => $sourceInstance,
    'source_label' => $sourceLabel,
    'sent_at' => gmdate('c'),
    'tables' => [
        'equipes' => $equipesRows,
        'controles' => $pendingControles,
    ],
    'meta' => [
        'app_mode' => app_mode(),
        'sync_type' => 'equipes_controles',
        'total_records' => count($equipesRows) + count($pendingControles),
        'source_label' => $sourceLabel,
        'origin_instance_id' => $autoInstanceId,
    ],
];

 $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($payloadJson === false) {
    sync_json_response(false, 'Impossible de préparer les données à envoyer.', 500, 'PAYLOAD_BUILD_FAILED');
}

sync_progress_event('progress', [
    'percentage' => 55,
    'step' => 'Connexion au serveur distant et envoi des données...',
    'sent' => ['equipes' => count($equipesRows), 'controles' => count($pendingControles)],
    'instance_id' => $autoInstanceId,
]);

 $syncTimeout = min(max(15, (int) ($config['timeout'] ?? 30)), 120);

try {
    $forwardResponse = forward_sync_payload_to_server($serverIp, $payloadJson, $syncTimeout);
} catch (InvalidArgumentException $exception) {
    sync_json_response(false, $exception->getMessage(), 400, 'SERVER_ADDRESS_INVALID');
}

if ($forwardResponse['body'] === false) {
    log_sync_attempt($pdo, array_column($pendingControles, 'id'), array_column($equipesRows, 'id'), 'echec', json_encode([
        'server_ip' => $serverIp,
        'target_url' => $forwardResponse['target_url'],
        'attempted_urls' => $forwardResponse['attempted_urls'],
        'error' => $forwardResponse['transport_error'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    sync_json_response(false, 'Impossible de joindre le service de synchronisation.', 502, 'REMOTE_CONNECTION_FAILED', [
        'transport_error' => $forwardResponse['transport_error'],
        'target_url' => $forwardResponse['target_url'],
    ]);
}

 $cleanRemoteBody = sync_extract_json_payload((string) ($forwardResponse['body'] ?? ''));
 $remote = json_decode($cleanRemoteBody, true);
if (!is_array($remote)) {
    sync_json_response(false, 'Réponse invalide du serveur distant.', 502, 'REMOTE_INVALID_RESPONSE', [
        'target_url' => $forwardResponse['target_url'],
        'raw_response' => mb_substr($cleanRemoteBody !== '' ? $cleanRemoteBody : (string) $forwardResponse['body'], 0, 500),
        'json_error' => json_last_error_msg(),
    ]);
}

 $remoteStats = is_array($remote['stats'] ?? null) ? $remote['stats'] : [];
 $remoteEquipes = is_array($remoteStats['equipes'] ?? null) ? $remoteStats['equipes'] : [];
 $remoteControles = is_array($remoteStats['controles'] ?? null) ? $remoteStats['controles'] : [];
 $duplicatesTotal = (int) ($remoteEquipes['doublons'] ?? 0) + (int) ($remoteControles['doublons'] ?? 0);
 $invalidTotal = (int) ($remoteEquipes['invalides'] ?? 0) + (int) ($remoteControles['invalides'] ?? 0);
 $insertedTotal = (int) ($remoteEquipes['inseres'] ?? 0) + (int) ($remoteControles['inseres'] ?? 0);
 $updatedTotal = (int) ($remoteEquipes['maj'] ?? 0) + (int) ($remoteControles['maj'] ?? 0);
 $remotePendingConflicts = (int) ($remote['pending_conflicts'] ?? 0);
 $hasRemoteConflicts = $remotePendingConflicts > 0;
 $isRemoteAlreadySynced = !($remote['success'] ?? false)
    && $duplicatesTotal > 0
    && $invalidTotal === 0
    && ($insertedTotal + $updatedTotal) === 0;

if (!($remote['success'] ?? false) && !$isRemoteAlreadySynced) {
    log_sync_attempt($pdo, array_column($pendingControles, 'id'), array_column($equipesRows, 'id'), 'echec', json_encode([
        'server_ip' => $serverIp,
        'target_url' => $forwardResponse['target_url'],
        'remote_response' => $remote,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $remoteMessage = (string) ($remote['message'] ?? 'Le serveur a rejeté la synchronisation.');
    if (stripos($remoteMessage, 'serveur central') !== false || stripos($remoteMessage, 'mode central') !== false) {
        $remoteMessage = 'Le serveur saisi ne correspond pas au point de réception central.';
    }
    sync_json_response(false, $remoteMessage, 409, 'REMOTE_REJECTED', [
        'target_url' => $forwardResponse['target_url'],
        'remote_response' => $remote,
    ]);
}

sync_progress_event('progress', [
    'percentage' => 82,
    'step' => 'Réponse du serveur reçue. Mise à jour des statuts locaux...',
    'sent' => ['equipes' => count($equipesRows), 'controles' => count($pendingControles)],
    'instance_id' => $autoInstanceId,
]);

 $controleIds = array_values(array_filter(array_map(static fn($row) => (int) ($row['id'] ?? 0), $pendingControles)));
if (!empty($controleIds)) {
    $placeholders = implode(',', array_fill(0, count($controleIds), '?'));
    $pdo->prepare("UPDATE controles SET sync_status = 'synced', sync_date = NOW() WHERE id IN ($placeholders)")->execute($controleIds);
}

 $equipeIds = array_values(array_filter(array_map(static fn($row) => (int) ($row['id'] ?? 0), $equipesRows)));
if (!empty($equipeIds)) {
    $placeholders = implode(',', array_fill(0, count($equipeIds), '?'));
    $pdo->prepare("UPDATE equipes SET sync_status = 'synced', sync_date = NOW() WHERE id IN ($placeholders)")->execute($equipeIds);
}

 $summaryParts = [];
if (!empty($equipesRows)) $summaryParts[] = sprintf('%d membre(s) d\'équipe', count($equipesRows));
if (!empty($pendingControles)) $summaryParts[] = sprintf('%d contrôle(s)', count($pendingControles));

 $syncState = $hasRemoteConflicts ? 'conflicts_pending' : ($isRemoteAlreadySynced ? 'already_synced' : 'completed');
 $summary = $hasRemoteConflicts
    ? 'Synchronisation transmise : ' . $remotePendingConflicts . ' conflit(s) en attente sur le serveur central.'
    : ($isRemoteAlreadySynced
        ? 'Données déjà présentes sur le serveur central. Statuts locaux mis à jour.'
        : 'Synchronisation effectuée : ' . implode(' et ', $summaryParts) . ' vers ' . $serverIp . '.');

log_sync_attempt($pdo, $controleIds, $equipeIds, 'succes', json_encode([
    'server_ip' => $serverIp,
    'target_url' => $forwardResponse['target_url'],
    'remote_response' => $remote,
    'summary' => $summary,
    'sync_state' => $syncState,
    'instance_id' => $autoInstanceId,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

audit_action('SYNC_CONTROLES', 'synchronisation', null, $summary);

sync_progress_event('progress', [
    'percentage' => 100,
    'step' => $hasRemoteConflicts
        ? 'Synchronisation transmise. Conflits en attente sur le serveur central.'
        : ($isRemoteAlreadySynced
            ? 'Données déjà présentes. Mise à jour locale terminée.'
            : 'Synchronisation finalisée avec succès.'),
    'sent' => ['equipes' => count($equipesRows), 'controles' => count($pendingControles)],
    'instance_id' => $autoInstanceId,
]);

sync_json_response(true, $hasRemoteConflicts
    ? 'Synchronisation transmise avec conflits en attente.'
    : ($isRemoteAlreadySynced ? 'Données déjà sur le serveur central.' : 'Synchronisation terminée avec succès.'), 200, null, [
    'summary' => $summary,
    'sync_state' => $syncState,
    'pending_conflicts' => $remotePendingConflicts,
    'conflict_page' => $remote['conflict_page'] ?? null,
    'target_url' => $forwardResponse['target_url'],
    'sent' => ['equipes' => count($equipesRows), 'controles' => count($pendingControles)],
    'stats' => $remote['stats'] ?? [
        'equipes' => ['recus' => count($equipesRows), 'inseres' => count($equipesRows), 'maj' => 0],
        'controles' => ['recus' => count($pendingControles), 'inseres' => count($pendingControles), 'maj' => 0],
    ],
    'instance_id' => $autoInstanceId,
]);


// =====================================================================
// SCHÉMA
// =====================================================================

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
        try { $pdo->exec($statement); } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate column name') === false) throw $e;
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS sync_local_config (
        config_key VARCHAR(100) NOT NULL PRIMARY KEY,
        config_value TEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensure_sync_log_table($pdo);
}

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
        try { $pdo->exec($statement); } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate column name') === false) throw $e;
        }
    }
}


// =====================================================================
// IDENTIFIANT UNIQUE DU PC CLIENT
// =====================================================================

/**
 * Récupère ou génère l'identifiant unique de CE poste client.
 *
 * ╔════════════════════════════════════════════════════════════════════╗
 * ║  PRINCIPE : le fichier instance_id.txt est la SOURCE DE VÉRITÉ. ║
 * ║  La BDD ne sert qu'à mémoriser, jamais à décider.                ║
 * ╚════════════════════════════════════════════════════════════════════╝
 *
 * Pourquoi ce choix ?
 * ------------------
 * Quand on clone un repo Git, deux choses sont copiées :
 *   1. Les fichiers PHP (versionnés) → identiques sur tous les PCs
 *   2. La BDD locale (NON versionnée) → copiée avec l'ID d'origine
 *
 * Si on lit l'ID depuis la BDD, tous les PCs clonés auront le même
 * ID → même source_instance → données mélangées sur le serveur →
 * une seule carte affichée.
 *
 * Le fichier instance_id.txt est ajouté au .gitignore du projet.
 * Il n'existe JAMAIS après un clone Git. Donc :
 *   - Clone Git → fichier absent → NOUVEL ID généré automatiquement
 *   - Lancement normal → fichier présent → ID lu depuis le fichier
 *
 * Ce fichier est le seul endroit qui distingue physiquement deux PCs
 * ayant le même hostname et la même BDD copiée.
 *
 * FORMAT : <hostname-nettoyé>-<4 caractères hexa>
 * Exemples : "wendie-pc-1a58", "poste-gestion-7f2e"
 *
 * @param PDO $pdo Connexion BDD locale (utilisation : mémorisation uniquement)
 * @return string Identifiant unique de ce PC
 */
function sync_auto_instance_id(PDO $pdo): string
{
    if (!empty($GLOBALS['sync_cached_instance_id'])) {
        return $GLOBALS['sync_cached_instance_id'];
    }

    /*
     * Chemin du fichier local.
     * Placé à la racine du projet (même niveau que index.php).
     * Ce fichier DOIT être dans .gitignore pour ne jamais être versionné.
     */
    $file = __DIR__ . '/../instance_id.txt';

    // ──────────────────────────────────────────────────────────
    // CAS 1 : le fichier existe → ID valide de ce PC précis
    // ──────────────────────────────────────────────────────────
    if (file_exists($file) && is_readable($file)) {
        $idFromFile = trim(file_get_contents($file));

        if ($idFromFile !== '' && strlen($idFromFile) >= 5) {
            // Mémoriser dans la BDD pour référence (pas pour décision)
            sync_persist_instance_id($pdo, $idFromFile);

            error_log(sprintf(
                'sync_auto_instance_id: ID lu depuis le fichier local : "%s"',
                $idFromFile
            ));

            $GLOBALS['sync_cached_instance_id'] = $idFromFile;
            return $idFromFile;
        }

        // Fichier existe mais vide/corrompu → le recréer
        error_log('sync_auto_instance_id: fichier local corrompu ou vide, régénération.');
    }

    // ──────────────────────────────────────────────────────────
    // CAS 2 : le fichier N'EXISTE PAS → clone Git détecté
    // ──────────────────────────────────────────────────────────
    //
    // La BDD a peut-être un ID (copié du PC d'origine), mais on
    // l'ignore complètement. Seul le fichier fait autorité.
    // On génère un nouvel ID unique pour CE PC.
    //
    error_log('sync_auto_instance_id: fichier local absent → clone Git détecté → génération d\'un nouvel ID.');

    $newId = sync_generate_unique_instance_id($pdo, $file);

    $GLOBALS['sync_cached_instance_id'] = $newId;
    return $newId;
}

/**
 * Génère un identifiant unique : <hostname>-<4 hex>
 *
 * @param PDO $pdo
 * @param string $file Chemin du fichier à créer
 * @return string
 */
function sync_generate_unique_instance_id(PDO $pdo, string $file): string
{
    $hostname = gethostname() ?: php_uname('n') ?: 'poste';

    // Nettoyer : lettres, chiffres et tirets uniquement
    $hostname = preg_replace('/[^a-zA-Z0-9]/', '-', strtolower($hostname)) ?? 'poste';
    $hostname = trim($hostname, '-_');
    if ($hostname === '' || strlen($hostname) < 2) {
        $hostname = 'poste';
    }
    $hostname = substr($hostname, 0, 35);

    // Tenter jusqu'à 5 fois en cas de collision (théoriquement impossible)
    for ($attempt = 0; $attempt < 5; $attempt++) {
        // 2 octets aléatoires = 4 caractères hexa (65 536 combinaisons)
        $suffix = strtolower(bin2hex(random_bytes(2)));
        $instanceId = $hostname . '-' . $suffix;

        // Normalisation
        $instanceId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $instanceId) ?? 'poste-unknown';
        $instanceId = trim($instanceId, '-_');
        $instanceId = substr($instanceId, 0, 50);

        if ($instanceId === '' || strlen($instanceId) < 5) {
            continue;
        }

        // Vérifier que cet ID n'existe pas déjà sur le serveur central
        // (au cas où la BDD aurait été copiée puis le fichier recréé manuellement)
        if (sync_is_instance_id_locally_unique($pdo, $instanceId)) {
            sync_persist_instance_id($pdo, $instanceId);
            @file_put_contents($file, $instanceId, LOCK_EX);

            error_log(sprintf(
                'sync_auto_instance_id: nouvel ID généré : "%s" (tentative %d)',
                $instanceId,
                $attempt + 1
            ));

            return $instanceId;
        }
    }

    // Dernier recours : ajouter un timestamp pour garantie absolue
    $instanceId = $hostname . '-' . strtolower(bin2hex(random_bytes(2))) . '-' . time();
    $instanceId = substr($instanceId, 0, 50);
    sync_persist_instance_id($pdo, $instanceId);
    @file_put_contents($file, $instanceId, LOCK_EX);

    return $instanceId;
}

/**
 * Vérifie qu'un ID n'existe pas déjà dans la BDD locale.
 * Sécurité supplémentaire au cas où on recréerait manuellement le fichier.
 */
function sync_is_instance_id_locally_unique(PDO $pdo, string $instanceId): bool
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sync_local_config WHERE config_key = 'instance_id' AND config_value = ?");
        $stmt->execute([$instanceId]);
        return (int) $stmt->fetchColumn() === 0;
    } catch (Throwable $e) {
        return true;
    }
}

/**
 * Mémorise l'ID dans la BDD (à des fins de référence uniquement).
 * La BDD ne décide JAMAIS de l'ID actif — seul le fichier le fait.
 */
function sync_persist_instance_id(PDO $pdo, string $instanceId): void
{
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO sync_local_config (config_key, config_value, updated_at)
             VALUES ('instance_id', ?, NOW())
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()"
        );
        $stmt->execute([$instanceId]);
    } catch (Throwable $e) {
        error_log('sync_persist_instance_id: ' . $e->getMessage());
    }
}


// =====================================================================
// UTILITAIRES
// =====================================================================

function sync_extract_json_payload(string $body): string
{
    $body = preg_replace('/^\xEF\xBB\xBF/u', '', $body) ?? $body;
    $body = ltrim($body, "\x00..\x20");
    $decoded = json_decode($body, true);
    if (is_array($decoded)) return $body;
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
    return $value !== '' && (bool) preg_match('/^(https?:\/\/)?([a-zA-Z0-9.-]+|\[[0-9a-fA-F:]+\])(?::\d+)?(\/.*)?$/', $value);
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
    if ($baseLabel === '') $baseLabel = 'SITE LOCAL 01';
    return mb_substr('Equipe : ' . $baseLabel, 0, 150);
}

function sync_build_source_instance(string $instanceId): string
{
    $instanceId = trim($instanceId);
    if ($instanceId === '') $instanceId = php_uname('n') ?: 'client';
    $normalized = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($instanceId)) ?? 'client';
    $normalized = trim($normalized, '-_');
    if ($normalized === '') $normalized = 'client';
    return substr($normalized, 0, 50);
}

function sync_progress_event(string $event, array $payload = []): void
{
    if (empty($GLOBALS['sync_stream_enabled'])) return;
    echo 'data: ' . json_encode(array_merge(['event' => $event], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    if (function_exists('ob_flush')) @ob_flush();
    flush();
}

function sync_json_response(bool $success, string $message, int $httpCode = 200, ?string $errorCode = null, array $data = []): void
{
    http_response_code($httpCode);
    $response = ['success' => $success, 'message' => $message, 'timestamp' => gmdate('c')];
    if ($errorCode !== null) $response['error_code'] = $errorCode;
    if (!empty($data)) $response['data'] = $data;
    if (!empty($GLOBALS['sync_stream_enabled'])) {
        sync_progress_event($success ? 'complete' : 'error', $response);
        exit;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function log_sync_attempt(PDO $pdo, array $controleIds, array $equipeIds, string $statut, string $details = ''): void
{
    $pdo->prepare("INSERT INTO synchronisation (controle_ids, equipe_ids, nb_controles, nb_equipes, statut, details, utilisateur_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            json_encode($controleIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($equipeIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            count($controleIds), count($equipeIds), $statut, $details,
            $_SESSION['user_id'] ?? null,
        ]);
}