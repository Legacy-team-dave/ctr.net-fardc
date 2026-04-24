<?php
// ===== FORCER LA PURGE DU CACHE PHP (OPcache) =====
if (function_exists('opcache_reset')) { @opcache_reset(); }
if (function_exists('apc_clear_cache')) { @apc_clear_cache(); }
// ======================================================

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

// =====================================================================
// DÉTECTION AUTOMATIQUE DE L'INSTANCE ID
// =====================================================================
//
// RÈGLE ABSOLUE :
//   - Lire le fichier temp système UNIQUEMENT
//   - S'il contient exactement 16+ caractères HEXADÉCIMAUX → l'utiliser
//   - Sinon → supprimer l'ancien fichier + générer un NOUVEL ID
//
// NETTOYAGE À CHAQUE APPEL :
//   - Supprime l'entrée instance_id de sync_local_config (ancien code)
//   - Supprime instance_id.txt s'il existe (ancien code)
//
// AUCUNE LECTURE de fallback. AUCUN hostname. AUCUNE BDD locale.
// Format garanti : exactement 16 caractères hexadécimaux (0-9, a-f)
// =====================================================================

 $autoInstanceId = sync_auto_instance_id($pdo);
 $GLOBALS['sync_auto_instance_id'] = $autoInstanceId;

// ── Construction du source_instance : MACHINE (16 hex) + NAVIGATEUR (16 hex) ──
 $machineId = $autoInstanceId;
 $clientId = trim((string) ($input['client_id'] ?? ''));

if ($clientId !== '') {
    $normalizedClientId = preg_replace('/[^a-z0-9]/i', '', strtolower($clientId));
    $clientIdShort = substr($normalizedClientId, 0, 16);
    $sourceInstance = $machineId . '-' . $clientIdShort;
} else {
    $sourceInstance = $machineId;
}

 $sourceInstance = substr($sourceInstance, 0, 50);

// ── Garnisons filtrées : récupérées depuis les préférences utilisateur ──
// ── Garnisons filtrées : récupérées depuis les préférences utilisateur ──
 $garnisonsFromPrefs = [];
try {
    if (function_exists('preferred_garnison_labels')) {
        $garnisonsFromPrefs = preferred_garnison_labels();
    }
} catch (Throwable $e) {
    $garnisonsFromPrefs = [];
}

if (empty($garnisonsFromPrefs)) {
    try {
        $stmtPrefs = $pdo->prepare(
            "SELECT u.preferences, u.utilisateur_garnison FROM utilisateurs u WHERE u.id = ? LIMIT 1"
        );
        $stmtPrefs->execute([$_SESSION['user_id'] ?? 0]);
        $prefsRow = $stmtPrefs->fetch(PDO::FETCH_ASSOC);

        $rawPrefs = trim((string) ($prefsRow['preferences'] ?? ''));
        $rawGarnison = trim((string) ($prefsRow['utilisateur_garnison'] ?? ''));

        if ($rawGarnison !== '' && $rawGarnison !== 'NULL') {
            $garnisonsFromPrefs[] = $rawGarnison;
        }

        if (empty($garnisonsFromPrefs) && $rawPrefs !== '' && $rawPrefs !== 'NULL') {
            $prefs = json_decode($rawPrefs, true);
            if (is_array($prefs)) {
                $keys = ['garnisons', 'garnison', 'garnisons_filtrees', 'garnison_filtree', 'garnisons_filtrées', 'garnison_filtrée'];
                foreach ($keys as $key) {
                    if (isset($prefs[$key]) && is_array($prefs[$key])) {
                        foreach ($prefs[$key] as $g) {
                            $g = trim((string) $g);
                            if ($g !== '' && !in_array($g, $garnisonsFromPrefs)) {
                                $garnisonsFromPrefs[] = $g;
                            }
                        }
                    }
                }
                if (empty($garnisonsFromPrefs)) {
                    foreach ($prefs as $key => $val) {
                        if (is_array($val)) {
                            foreach ($val as $v) {
                                $v = trim((string) $v);
                                if ($v !== '' && strlen($v) > 3 && !is_numeric($v) && !in_array($v, $garnisonsFromPrefs)) {
                                    $garnisonsFromPrefs[] = $v;
                                }
                            }
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        $garnisonsFromPrefs = [];
    }
}

 $garnisonLabel = sync_join_garnison_labels($garnisonsFromPrefs);

if ($garnisonLabel === '') {
    $garnisonLabel = 'SITE LOCAL 01';
}
 $pendingControles = $pdo->query("SELECT * FROM controles WHERE COALESCE(sync_status, 'local') <> 'synced' ORDER BY date_controle ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
 $equipesRows = $pdo->query("SELECT * FROM equipes WHERE COALESCE(sync_status, 'local') <> 'synced' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($pendingControles) && empty($equipesRows)) {
    sync_json_response(true, 'Aucune donnée à synchroniser.', 200, null, [
        'summary' => 'Aucun membre d\'équipe ni contrôle n\'est en attente.',
        'sync_state' => 'no_data',
        'sent' => ['equipes' => 0, 'controles' => 0],
        'stats' => ['equipes' => 0, 'controles' => 0],
    ]);
}

sync_progress_event('progress', [
    'percentage' => 10,
    'step' => 'Analyse des données locales en attente...',
]);

sync_progress_event('progress', [
    'percentage' => 30,
    'step' => sprintf('Préparation de %d membre(s) d\'équipe et %d contrôle(s)...', count($equipesRows), count($pendingControles)),
    'sent' => ['equipes' => count($equipesRows), 'controles' => count($pendingControles)],
]);

 $payload = [
    'source_instance' => $sourceInstance,
    'source_label'   => 'Equipe : ' . $garnisonLabel,
    'sent_at'        => gmdate('c'),
    'tables'         => [
        'equipes'   => $equipesRows,
        'controles' => $pendingControles,
    ],
    'meta' => [
        'app_mode'           => app_mode(),
        'sync_type'          => 'equipes_controles',
        'total_records'      => count($equipesRows) + count($pendingControles),
        'source_label'       => 'Equipe : ' . $garnisonLabel,
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
    'sent' => ['equipes' => count($equipesRows), 'controles' => count($pendingControles)],
]);

 $syncTimeout = min(max(15, (int) ($config['timeout'] ?? 30)), 120);

try {
    $forwardResponse = forward_sync_payload_to_server($serverIp, $payloadJson, $syncTimeout);
} catch (InvalidArgumentException $exception) {
    sync_json_response(false, $exception->getMessage(), 400, 'SERVER_ADDRESS_INVALID');
}

if ($forwardResponse['body'] === false) {
    log_sync_attempt($pdo, array_column($pendingControles, 'id'), array_column($equipesRows, 'id'), 'echec', json_encode([
        'server_ip' => $serverIp, 'target_url' => $forwardResponse['target_url'],
        'attempted_urls' => $forwardResponse['attempted_urls'], 'error' => $forwardResponse['transport_error'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    sync_json_response(false, 'Impossible de joindre le service de synchronisation.', 502, 'REMOTE_CONNECTION_FAILED', [
        'transport_error' => $forwardResponse['transport_error', 'target_url' => $forwardResponse['target_url'],
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

 $remoteStats            = is_array($remote['stats'] ?? null) ? $remote['stats'] : [];
 $remoteEquipes          = is_array($remoteStats['equipes'] ?? null) ? $remoteStats['equipes'] : [];
 $remoteControles        = is_array($remoteStats['controles'] ?? null) ? $remoteStats['controles'] : [];
 $duplicatesTotal        = (int) ($remoteEquipes['doublons'] ?? 0) + (int) ($remoteControles['doublons'] ?? 0);
 $invalidTotal           = (int) ($remoteEquipes['invalides'] ?? 0) + (int) ($remoteControles['invalides'] ?? 0);
 $insertedTotal          = (int) ($remoteEquipes['inseres'] ?? 0) + (int) ($remoteControles['inseres'] ?? 0);
 $updatedTotal           = (int) ($remoteEquipes['maj'] ?? 0) + (int) ($remoteControles['maj'] ?? 0);
 $remotePendingConflicts = (int) ($remote['pending_conflicts'] ?? 0);
 $hasRemoteConflicts     = $remotePendingConflicts > 0;
 $isRemoteAlreadySynced  = !($remote['success'] ?? false) && $duplicatesTotal > 0 && $invalidTotal === 0 && ($insertedTotal + $updatedTotal) === 0;

if (!($remote['success'] ?? false) && !$isRemoteAlreadySynced) {
    log_sync_attempt($pdo, array_column($pendingControles, 'id'), array_column($equipesRows, 'id'), 'echec', json_encode([
        'server_ip' => $serverIp, 'target_url' => $forwardResponse['target_url'], 'remote_response' => $remote,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $remoteMessage = (string) ($remote['message'] ?? 'Le serveur a rejeté la synchronisation.');
    if (stripos($remoteMessage, 'serveur central') !== false || stripos($remoteMessage, 'mode central') !== false) {
        $remoteMessage = 'Le serveur saisi ne correspond pas au point de réception central.';
    }
    sync_json_response(false, $remoteMessage, 409, 'REMOTE_REJECTED', [
        'target_url' => $forwardResponse['target_url'], 'remote_response' => $remote,
    ]);
}

sync_progress_event('progress', [
    'percentage' => 82,
    'step' => 'Réponse du serveur reçue. Mise à jour des statuts locaux...',
    'sent' => ['equipes' => count($equipesRows), 'controles' => count($pendingControles)],
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
    ? 'Synchronisation transmise : ' . $remotePendingConflicts . ' conflit(s) en attente.'
    : ($isRemoteAlreadySynced ? 'Données déjà présentes.' : 'Synchronisation : ' . implode(' et ', $summaryParts) . '.');

log_sync_attempt($pdo, $controleIds, $equipeIds, 'succes', json_encode([
    'server_ip' => $serverIp, 'target_url' => $forwardResponse['target_url'],
    'remote_response' => $remote, 'summary' => $summary, 'sync_state' => $syncState,
    'source_instance' => $sourceInstance, 'garnison_label' => $garnisonLabel,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

audit_action('SYNC_CONTROLES', 'synchronisation', null, $summary);

sync_progress_event('progress', [
    'percentage' => 100,
    'step' => $hasRemoteConflicts ? 'Synchronisation transmise avec conflits.' : ($isRemoteAlreadySynced ? 'Données déjà présentes.' : 'Synchronisation finalisée.'),
    'sent' => ['equipes' => count($equipesRows), 'controles' => count($pendingControles)],
]);

sync_json_response(true, $hasRemoteConflicts ? 'Synchronisation transmise.' : ($isRemoteAlreadySynced ? 'Données déjà présentes.' : 'Terminée.'), 200, null, [
    'summary' => $summary, 'sync_state' => $syncState, 'pending_conflicts' => $remotePendingConflicts,
    'conflict_page' => $remote['conflict_page'] ?? null, 'target_url' => $forwardResponse['target_url'],
    'sent' => ['equipes' => count($equipesRows), 'controles' => count($pendingControles)],
    'stats' => $remote['stats'] ?? [
        'equipes' => ['recus' => count($equipesRows), 'inseres' => count($equipesRows), 'maj' => 0],
        'controles' => ['recus' => count($pendingControles), 'inseres' => count($pendingControles), 'maj' => 0],
    ],
]);

// =====================================================================
// sync_auto_instance_id — VERSION DÉFINITIVE AVEC NETTOYAGE
// =====================================================================
// Si après déploiement vous voyez encore "wendie-pc-xxxx" dans
// sync_local_config, c'est que OPcache sert l'ancien bytecode.
// La ligne opcache_reset() en haut de ce fichier résout cela.
//
// Format garanti : exactement 16 caractères hexadécimaux (0-9, a-f)
// =====================================================================

function sync_auto_instance_id(PDO $pdo): string
{
    if (!empty($GLOBALS['sync_cached_instance_id'])) {
        return $GLOBALS['sync_cached_instance_id'];
    }

    // ── NETTOYAGE : supprimer les traces de l'ancien code ──
    try {
        $pdo->exec("DELETE FROM sync_local_config WHERE config_key = 'instance_id'");
    } catch (Throwable $e) {}

    $oldFile = __DIR__ . '/../instance_id.txt';
    if (file_exists($oldFile)) {
        @unlink($oldFile);
    }

    // ── LECTURE : fichier temp système UNIQUEMENT ──
    $tempDir = sys_get_temp_dir();
    $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'ctr_ops_fardc_unique_machine_id';

    if (file_exists($tempFile) && is_readable($tempFile)) {
        $tempId = trim(file_get_contents($tempFile));
        if ($tempId !== '' && strlen($tempId) >= 16 && ctype_xdigit($tempId)) {
            $GLOBALS['sync_cached_instance_id'] = $tempId;
            return $tempId;
        }
    }

    // ── GÉNÉRATION : nouvel ID unique ──
    $instanceId = strtolower(bin2hex(random_bytes(8)));

    @file_put_contents($tempFile, $instanceId, LOCK_EX);

    @file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'ctr_ops_instance_debug.log',
        date('Y-m-d H:i:s') . ' NEW=' . $instanceId . ' OLD_PURGED' . PHP_EOL,
        FILE_APPEND
    );

    $GLOBALS['sync_cached_instance_id'] = $instanceId;
    return $instanceId;
}

// =====================================================================
// FONCTIONS UTILITAIRES
// =====================================================================

function ensure_sync_log_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS synchronisation (
        id INT AUTO_INCREMENT PRIMARY KEY, controle_ids TEXT NULL, equipe_ids TEXT NULL,
        nb_controles INT NOT NULL DEFAULT 0, nb_equipes INT NOT NULL DEFAULT 0,
        statut ENUM('succes','echec','en_attente') NOT NULL DEFAULT 'en_attente',
        details LONGTEXT NULL, utilisateur_id INT NULL,
        cree_le TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sync_statut (statut), INDEX idx_sync_date (cree_le)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ([
        "ALTER TABLE synchronisation ADD COLUMN equipe_ids TEXT NULL AFTER controle_ids",
        "ALTER TABLE synchronisation ADD COLUMN nb_equipes INT NOT NULL DEFAULT 0 AFTER nb_controles"
    ] as $s) { try { $pdo->exec($s); } catch (PDOException $e) { if (stripos($e->getMessage(), 'Duplicate column name') === false) throw $e; } }
}

function ensure_equipes_sync_columns(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS equipes (
        id INT AUTO_INCREMENT PRIMARY KEY, matricule VARCHAR(50) NULL, noms VARCHAR(150) NOT NULL,
        grade VARCHAR(50) NOT NULL, unites VARCHAR(150) NULL, role VARCHAR(50) NOT NULL,
        id_source INT NULL, db_source VARCHAR(50) NULL DEFAULT 'local',
        sync_status ENUM('local','synced') NOT NULL DEFAULT 'local', sync_date DATETIME NULL,
        sync_version INT NOT NULL DEFAULT 1,
        INDEX idx_equipes_sync_status (sync_status), INDEX idx_equipes_source (db_source, id_source),
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
    ] as $s) { try { $pdo->exec($s); } catch (PDOException $e) { if (stripos($e->getMessage(), 'Duplicate column name') === false) throw $e; } }
}

function sync_extract_json_payload(string $body): string
{
    $body = preg_replace('/^\xEF\xBB\xBF/u', '', $body) ?? $body;
    $body = ltrim($body, "\x00..\x20");
    if (is_array(json_decode($body, true))) return $body;
    $j = strpos($body, '{'); $f = strrpos($body, '}');
    if ($j !== false && $f !== false && $f >= $j) return substr($body, $j, $f - $j + 1);
    return $body;
}

function is_valid_server_address(string $value): bool
{
    $v = trim($value); return $v !== '' && (bool) preg_match('/^(https?:\/\/)?([a-zA-Z0-9.-]+|\[[0-9a-fA-F:]+\])(?::\d+)?(\/.*)?$/', $v);
}

function sync_join_garnison_labels(array $g): string
{
    $l = [];
    foreach ($g as $v) {
        $v = trim((string) $v);
        if ($v !== '' && !in_array($v, $l, true)) $l[] = $v;
    }
    return implode(' - ', $l);
}

function sync_progress_event(string $e, array $p = []): void
{
    if (empty($GLOBALS['sync_stream_enabled'])) return;
    echo 'data: ' . json_encode(array_merge(['event' => $e], $p), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    if (function_exists('ob_flush')) @ob_flush();
    flush();
}

function sync_json_response(bool $ok, string $msg, int $c = 200, ?string $ec = null, array $d = []): void
{
    http_response_code($c);
    $r = ['success' => $ok, 'message' => $msg, 'timestamp' => gmdate('c')];
    if ($ec !== null) $r['error_code'] = $ec;
    if (!empty($d)) $r['data'] = $d;
    if (!empty($GLOBALS['sync_stream_enabled'])) { sync_progress_event($ok ? 'complete' : 'error', $r); exit; }
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}

function log_sync_attempt(PDO $pdo, array $ci, array $ei, string $st, string $det = ''): void
{
    $pdo->prepare("INSERT INTO synchronisation (controle_ids, equipe_ids, nb_controles, nb_equipes, statut, details, utilisateur_id) VALUES (?,?,?,?,?,?,?,?)")->execute([
        json_encode($ci, JSON_UNESCAPED_UNICODE), json_encode($ei, JSON_UNESCAPED_UNICODE),
        count($ci), count($ei), $st, $det, $_SESSION['user_id'] ?? null
    ]);
}