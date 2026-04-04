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

$config = sync_config();
$serverIp = trim((string) ($input['server_ip'] ?? $input['server_url'] ?? ($config['central_url'] ?? '')));
if ($serverIp === '') {
    sync_json_response(false, 'Adresse du serveur manquante.', 400, 'SERVER_ADDRESS_MISSING');
}

if (!is_valid_server_address($serverIp)) {
    sync_json_response(false, 'Adresse du serveur invalide.', 400, 'SERVER_ADDRESS_INVALID');
}

$_SESSION['sync_server_ip'] = $serverIp;

ensure_equipes_sync_columns($pdo);
ensure_sync_log_table($pdo);
$selectedGarnisons = function_exists('preferred_garnison_labels') ? preferred_garnison_labels() : [];
$baseSourceLabel = sync_join_garnison_labels($selectedGarnisons);
if ($baseSourceLabel === '') {
    $baseSourceLabel = 'SITE LOCAL 01';
}

$pendingControles = $pdo->query("SELECT * FROM controles WHERE COALESCE(sync_status, 'local') <> 'synced' ORDER BY date_controle ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipesRows = $pdo->query("SELECT * FROM equipes WHERE COALESCE(sync_status, 'local') <> 'synced' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$sourceInstance = sync_build_source_instance((string) ($config['instance_id'] ?? ''));
$sourceLabel = sync_build_source_label($baseSourceLabel, $sourceInstance);
$siteContext = sync_build_site_context($config, $baseSourceLabel, $sourceInstance, $sourceLabel);
$equipesPayloadRows = sync_prepare_sync_rows($equipesRows, $siteContext['site_timezone'], ['sync_date']);
$controlesPayloadRows = sync_prepare_sync_rows($pendingControles, $siteContext['site_timezone'], ['date_controle', 'cree_le', 'sync_date']);
$syncBatch = sync_build_batch_context($sourceInstance, $equipesPayloadRows, $controlesPayloadRows);

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
    'client_id' => $sourceInstance,
    'source_instance' => $sourceInstance,
    'source_label' => $sourceLabel,
    'sent_at' => gmdate('c'),
    'sent_at_local' => sync_current_site_timestamp($siteContext['site_timezone']),
    'batch_id' => $syncBatch['batch_id'],
    'equipes' => $equipesPayloadRows,
    'controles' => $controlesPayloadRows,
    'meta' => [
        'app_mode' => app_mode(),
        'sync_type' => 'equipes_controles',
        'total_records' => count($equipesPayloadRows) + count($controlesPayloadRows),
        'source_label' => $sourceLabel,
        'origin_instance_id' => (string) ($config['instance_id'] ?? ''),
        'site_code' => $siteContext['site_code'],
        'site_name' => $siteContext['site_name'],
        'site_region' => $siteContext['site_region'],
        'site_timezone' => $siteContext['site_timezone'],
        'hostname' => $siteContext['hostname'],
        'target_server_region' => trim((string) ($config['server_region'] ?? '')),
        'batch_id' => $syncBatch['batch_id'],
        'records_fingerprint' => $syncBatch['records_fingerprint'],
    ],
];

$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($payloadJson === false) {
    sync_json_response(false, 'Impossible de préparer les données à envoyer.', 500, 'PAYLOAD_BUILD_FAILED');
}

$payloadHash = hash('sha256', $payloadJson);
$forwardHeaders = [
    'X-Sync-Batch-Id: ' . $syncBatch['batch_id'],
    'X-Idempotency-Key: ' . $syncBatch['idempotency_key'],
    'X-Sync-Site-Code: ' . $siteContext['site_code'],
    'X-Sync-Site-Name: ' . sync_header_safe_value($siteContext['site_name']),
    'X-Sync-Site-Timezone: ' . $siteContext['site_timezone'],
    'X-Sync-Sent-At: ' . $payload['sent_at'],
    'X-Sync-Payload-Hash: ' . $payloadHash,
];

sync_progress_event('progress', [
    'percentage' => 55,
    'step' => 'Connexion au serveur distant et envoi des données...',
    'sent' => [
        'equipes' => count($equipesRows),
        'controles' => count($pendingControles),
    ],
]);

try {
    $forwardResponse = forward_sync_payload_to_server($serverIp, $payloadJson, [
        'timeout' => min(max(10, (int) ($config['timeout'] ?? 45)), 120),
        'connect_timeout' => min(max(3, (int) ($config['connect_timeout'] ?? 10)), 45),
        'max_retries' => min(max(1, (int) ($config['max_retries'] ?? 3)), 5),
        'retry_delay_ms' => max(250, (int) ($config['retry_delay_ms'] ?? 1200)),
        'headers' => $forwardHeaders,
    ]);
} catch (InvalidArgumentException $exception) {
    sync_json_response(false, $exception->getMessage(), 400, 'SERVER_ADDRESS_INVALID');
}

if ($forwardResponse['body'] === false) {
    log_sync_attempt($pdo, array_column($pendingControles, 'id'), array_column($equipesRows, 'id'), 'echec', json_encode([
        'server_ip' => $serverIp,
        'target_url' => $forwardResponse['target_url'],
        'attempted_urls' => $forwardResponse['attempted_urls'],
        'error' => $forwardResponse['transport_error'],
        'batch_id' => $syncBatch['batch_id'],
        'idempotency_key' => $syncBatch['idempotency_key'],
        'site' => $siteContext,
        'attempt_count' => $forwardResponse['attempt_count'] ?? 0,
        'duration_ms' => $forwardResponse['duration_ms'] ?? null,
        'retry_delay_ms_total' => $forwardResponse['retry_delay_ms_total'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    sync_json_response(false, 'Impossible de joindre le service de synchronisation.', 502, 'REMOTE_CONNECTION_FAILED', [
        'transport_error' => $forwardResponse['transport_error'],
        'target_url' => $forwardResponse['target_url'],
    ]);
}

$cleanRemoteBody = sync_extract_json_payload((string) ($forwardResponse['body'] ?? ''));
$remote = json_decode($cleanRemoteBody, true);
if (!is_array($remote)) {
    sync_json_response(false, 'Réponse invalide du serveur distant : le serveur a répondu avec un contenu non JSON.', 502, 'REMOTE_INVALID_RESPONSE', [
        'target_url' => $forwardResponse['target_url'],
        'raw_response' => mb_substr($cleanRemoteBody !== '' ? $cleanRemoteBody : (string) $forwardResponse['body'], 0, 800),
        'json_error' => json_last_error_msg(),
        'transport_error' => $forwardResponse['transport_error'] ?? '',
        'attempted_urls' => $forwardResponse['attempted_urls'] ?? [],
    ]);
}

if (!($remote['success'] ?? false)) {
    log_sync_attempt($pdo, array_column($pendingControles, 'id'), array_column($equipesRows, 'id'), 'echec', json_encode([
        'server_ip' => $serverIp,
        'target_url' => $forwardResponse['target_url'],
        'remote_response' => $remote,
        'batch_id' => $syncBatch['batch_id'],
        'idempotency_key' => $syncBatch['idempotency_key'],
        'site' => $siteContext,
        'attempt_count' => $forwardResponse['attempt_count'] ?? 0,
        'duration_ms' => $forwardResponse['duration_ms'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    sync_json_response(false, (string) ($remote['message'] ?? 'Le serveur a rejeté la synchronisation.'), 409, 'REMOTE_REJECTED', [
        'target_url' => $forwardResponse['target_url'],
        'remote_response' => $remote,
    ]);
}

sync_progress_event('progress', [
    'percentage' => 82,
    'step' => 'Réponse du serveur reçue. Mise à jour des statuts locaux...',
    'sent' => [
        'equipes' => count($equipesRows),
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

$summary = 'Synchronisation distante effectuée depuis ' . ($siteContext['site_name'] !== '' ? $siteContext['site_name'] : $sourceInstance) . ' vers ' . $serverIp . ' : ' . implode(' et ', $summaryParts) . '.';

log_sync_attempt($pdo, $controleIds, $equipeIds, 'succes', json_encode([
    'server_ip' => $serverIp,
    'target_url' => $forwardResponse['target_url'],
    'remote_response' => $remote,
    'summary' => $summary,
    'batch_id' => $syncBatch['batch_id'],
    'idempotency_key' => $syncBatch['idempotency_key'],
    'site' => $siteContext,
    'attempt_count' => $forwardResponse['attempt_count'] ?? 0,
    'duration_ms' => $forwardResponse['duration_ms'] ?? null,
    'retry_delay_ms_total' => $forwardResponse['retry_delay_ms_total'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

audit_action('SYNC_CONTROLES', 'synchronisation', null, $summary);

sync_progress_event('progress', [
    'percentage' => 100,
    'step' => 'Synchronisation finalisée avec succès.',
    'sent' => [
        'equipes' => count($equipesRows),
        'controles' => count($pendingControles),
    ],
]);

sync_json_response(true, 'Synchronisation terminée avec succès.', 200, null, [
    'summary' => $summary,
    'sync_state' => 'completed',
    'target_url' => $forwardResponse['target_url'],
    'batch_id' => $syncBatch['batch_id'],
    'remote_status' => $remote['status'] ?? 'success',
    'deduplicated' => (bool) ($remote['deduplicated'] ?? false),
    'site' => $siteContext,
    'transport' => [
        'attempt_count' => $forwardResponse['attempt_count'] ?? 0,
        'duration_ms' => $forwardResponse['duration_ms'] ?? null,
        'retry_used' => (bool) ($forwardResponse['retry_used'] ?? false),
        'retry_delay_ms_total' => $forwardResponse['retry_delay_ms_total'] ?? null,
    ],
    'sent' => [
        'equipes' => count($equipesRows),
        'controles' => count($pendingControles),
    ],
    'stats' => $remote['stats'] ?? [
        'equipes' => ['recus' => count($equipesRows), 'inseres' => count($equipesRows), 'maj' => 0],
        'controles' => ['recus' => count($pendingControles), 'inseres' => count($pendingControles), 'maj' => 0],
    ],
]);

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

    foreach (['{"success"', '{"status"', '{"message"', '{"error"', '[{"success"'] as $needle) {
        $jsonStart = strpos($body, $needle);
        if ($jsonStart === false) {
            continue;
        }

        $candidate = substr($body, $jsonStart);
        $jsonEnd = max(
            strrpos($candidate, '}') === false ? -1 : strrpos($candidate, '}'),
            strrpos($candidate, ']') === false ? -1 : strrpos($candidate, ']')
        );

        if ($jsonEnd >= 0) {
            $candidate = substr($candidate, 0, $jsonEnd + 1);
        }

        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $candidate;
        }
    }

    $jsonStart = strpos($body, '{');
    $jsonEnd = strrpos($body, '}');
    if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd >= $jsonStart) {
        $candidate = substr($body, $jsonStart, $jsonEnd - $jsonStart + 1);
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $candidate;
        }
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

function sync_build_source_instance(string $instanceId): string
{
    $instanceId = trim($instanceId);
    if ($instanceId === '') {
        $instanceId = php_uname('n') ?: 'client';
    }

    $normalized = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($instanceId)) ?? 'client';
    $normalized = trim($normalized, '-_');

    if ($normalized === '') {
        $normalized = 'client';
    }

    return substr($normalized, 0, 50);
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
        'success' => $success,
        'message' => $message,
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

function sync_build_site_context(array $config, string $baseSourceLabel, string $sourceInstance, string $sourceLabel): array
{
    $siteCode = trim((string) ($config['site_code'] ?? $sourceInstance));
    if ($siteCode === '') {
        $siteCode = $sourceInstance !== '' ? $sourceInstance : 'remote-site';
    }

    $siteName = trim((string) ($config['site_name'] ?? ''));
    if ($siteName === '') {
        $siteName = trim($baseSourceLabel) !== '' ? trim($baseSourceLabel) : $sourceLabel;
    }

    $siteRegion = trim((string) ($config['site_region'] ?? ''));
    $siteTimezone = trim((string) ($config['site_timezone'] ?? ''));
    if ($siteTimezone === '') {
        $siteTimezone = date_default_timezone_get() ?: 'UTC';
    }

    return [
        'site_code' => substr($siteCode, 0, 80),
        'site_name' => mb_substr($siteName, 0, 150),
        'site_region' => mb_substr($siteRegion, 0, 120),
        'site_timezone' => substr($siteTimezone, 0, 80),
        'hostname' => substr((string) (php_uname('n') ?: $sourceInstance), 0, 120),
    ];
}

function sync_prepare_sync_rows(array $rows, string $timezone, array $dateFields = []): array
{
    $prepared = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        foreach ($dateFields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null && $row[$field] !== '') {
                $row[$field] = sync_export_datetime_for_remote($row[$field], $timezone);
            }
        }

        $prepared[] = $row;
    }

    return $prepared;
}

function sync_export_datetime_for_remote($value, string $timezone): string
{
    try {
        $tz = new DateTimeZone($timezone !== '' ? $timezone : 'UTC');
        return (new DateTime((string) $value, $tz))->format(DateTimeInterface::ATOM);
    } catch (Throwable $e) {
        return (string) $value;
    }
}

function sync_build_batch_context(string $sourceInstance, array $equipesRows, array $controlesRows): array
{
    $signature = [
        'equipes' => array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'id_source' => (int) ($row['id_source'] ?? $row['id'] ?? 0),
                'matricule' => (string) ($row['matricule'] ?? ''),
                'sync_version' => (int) ($row['sync_version'] ?? 1),
            ];
        }, $equipesRows),
        'controles' => array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'id_source' => (int) ($row['id_source'] ?? $row['id'] ?? 0),
                'matricule' => (string) ($row['matricule'] ?? ''),
                'date_controle' => (string) ($row['date_controle'] ?? ''),
                'sync_version' => (int) ($row['sync_version'] ?? 1),
            ];
        }, $controlesRows),
    ];

    $fingerprintPayload = json_encode($signature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: uniqid($sourceInstance, true);
    $recordsFingerprint = hash('sha256', $fingerprintPayload);
    $safeInstance = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($sourceInstance)) ?: 'client';
    $safeInstance = trim($safeInstance, '-_') ?: 'client';
    $batchId = substr($safeInstance, 0, 30) . '-' . gmdate('YmdHis') . '-' . substr($recordsFingerprint, 0, 10);

    return [
        'batch_id' => substr($batchId, 0, 120),
        'idempotency_key' => hash('sha256', $safeInstance . '|' . $recordsFingerprint),
        'records_fingerprint' => $recordsFingerprint,
    ];
}

function sync_current_site_timestamp(string $timezone): string
{
    try {
        return (new DateTime('now', new DateTimeZone($timezone !== '' ? $timezone : 'UTC')))->format(DateTimeInterface::ATOM);
    } catch (Throwable $e) {
        return gmdate('c');
    }
}

function sync_header_safe_value(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}
