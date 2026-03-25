<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? 'status';

switch ($action) {
    case 'push':
        handle_push($pdo);
        break;
    case 'receive':
        handle_receive($pdo);
        break;
    case 'status':
    default:
        json_out([
            'success' => true,
            'mode' => app_mode(),
            'sync' => [
                'instance_id' => sync_config()['instance_id'],
                'central_url' => sync_config()['central_url']
            ]
        ]);
        break;
}

function handle_push(PDO $pdo)
{
    if (is_central_mode()) {
        json_out(['success' => false, 'message' => 'Action non autorisée en mode central.'], 403);
    }

    require_login();
    check_profil(['ADMIN_IG', 'OPERATEUR']);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_out(['success' => false, 'message' => 'Méthode POST requise.'], 405);
    }

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        json_out(['success' => false, 'message' => 'Token CSRF invalide.'], 403);
    }

    $config = sync_config();
    if ($config['central_url'] === '' || $config['shared_token'] === '') {
        json_out([
            'success' => false,
            'message' => 'Configuration de synchronisation incomplète (SYNC_CENTRAL_URL / SYNC_SHARED_TOKEN).'
        ], 500);
    }

    $payload = build_export_payload($pdo, $config['allowed_tables'], $config['instance_id']);

    $targetUrl = $config['central_url'] . '/api/synchronisation.php?action=receive';
    $remoteResponse = http_post_json($targetUrl, $payload, [
        'Authorization: Bearer ' . $config['shared_token'],
        'X-Sync-Instance: ' . $config['instance_id']
    ], $config['timeout']);

    if (!$remoteResponse['ok']) {
        json_out([
            'success' => false,
            'message' => 'Échec de communication avec le serveur central.',
            'error' => $remoteResponse['error']
        ], 502);
    }

    $decoded = json_decode($remoteResponse['body'], true);
    if (!is_array($decoded)) {
        json_out([
            'success' => false,
            'message' => 'Réponse invalide du serveur central.',
            'raw' => mb_substr($remoteResponse['body'], 0, 500)
        ], 502);
    }

    if (!($decoded['success'] ?? false)) {
        json_out([
            'success' => false,
            'message' => $decoded['message'] ?? 'Le serveur central a rejeté la synchronisation.',
            'details' => $decoded
        ], 409);
    }

    audit_action(
        'SYNC_PUSH',
        'synchronisation',
        null,
        'Synchronisation envoyée depuis ' . $config['instance_id'] . ' vers ' . $config['central_url']
    );

    json_out([
        'success' => true,
        'message' => 'Synchronisation terminée avec succès.',
        'export' => [
            'tables' => array_map('count', $payload['tables']),
            'total' => $payload['meta']['total_records'] ?? 0
        ],
        'central' => $decoded['stats'] ?? null
    ]);
}

function handle_receive(PDO $pdo)
{
    if (!is_central_mode()) {
        json_out(['success' => false, 'message' => 'Endpoint réservé au serveur central.'], 403);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_out(['success' => false, 'message' => 'Méthode POST requise.'], 405);
    }

    $config = sync_config();
    if ($config['require_https'] && !is_https_request()) {
        json_out(['success' => false, 'message' => 'HTTPS requis pour la synchronisation.'], 403);
    }

    $token = bearer_token();
    if ($config['shared_token'] === '' || !hash_equals($config['shared_token'], $token ?? '')) {
        json_out(['success' => false, 'message' => 'Token de synchronisation invalide.'], 401);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        json_out(['success' => false, 'message' => 'Payload JSON invalide.'], 400);
    }

    $sourceInstance = trim((string)($payload['source_instance'] ?? ''));
    $tables = $payload['tables'] ?? null;

    if ($sourceInstance === '' || !is_array($tables)) {
        json_out(['success' => false, 'message' => 'source_instance ou tables manquant.'], 400);
    }

    ensure_sync_tables($pdo);

    $allowedTables = array_flip(sync_config()['allowed_tables']);
    $stats = [
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'tables' => []
    ];

    $payloadHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));

    $pdo->beginTransaction();
    try {
        foreach ($tables as $table => $rows) {
            $tableName = strtolower(trim((string)$table));
            if (!isset($allowedTables[$tableName]) || !is_array($rows)) {
                $stats['skipped']++;
                $stats['tables'][$tableName]['skipped'] = ($stats['tables'][$tableName]['skipped'] ?? 0) + 1;
                continue;
            }

            $meta = table_metadata($pdo, $tableName);
            if (empty($meta['pk']) || empty($meta['columns'])) {
                $stats['errors']++;
                $stats['tables'][$tableName]['error'] = 'Table sans PK exploitable.';
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    $stats['skipped']++;
                    continue;
                }

                $result = upsert_row_from_source($pdo, $tableName, $meta, $sourceInstance, $row);
                $stats[$result] = ($stats[$result] ?? 0) + 1;
                $stats['tables'][$tableName][$result] = ($stats['tables'][$tableName][$result] ?? 0) + 1;
            }
        }

        $total = $stats['inserted'] + $stats['updated'] + $stats['skipped'] + $stats['errors'];

        $stmt = $pdo->prepare(
            "INSERT INTO sync_batches (source_instance, payload_sha256, status, details_json, total_records)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $sourceInstance,
            $payloadHash,
            $stats['errors'] > 0 ? 'partial' : 'success',
            json_encode($stats, JSON_UNESCAPED_UNICODE),
            $total
        ]);

        $batchId = (int) $pdo->lastInsertId();
        $pdo->commit();

        json_out([
            'success' => true,
            'message' => 'Synchronisation reçue et fusionnée.',
            'batch_id' => $batchId,
            'stats' => $stats
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_out([
            'success' => false,
            'message' => 'Erreur durant la fusion centrale.',
            'error' => $e->getMessage()
        ], 500);
    }
}

function build_export_payload(PDO $pdo, array $tables, $instanceId)
{
    $result = [
        'source_instance' => $instanceId,
        'sent_at' => gmdate('c'),
        'tables' => [],
        'meta' => [
            'app_mode' => app_mode(),
            'total_records' => 0
        ]
    ];

    foreach ($tables as $table) {
        $tableName = safe_table_name($table);
        if ($tableName === null) {
            continue;
        }

        $pk = table_primary_key($pdo, $tableName);
        $sql = 'SELECT * FROM `' . $tableName . '`';
        if ($pk !== null) {
            $sql .= ' ORDER BY `' . $pk . '` ASC';
        }

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $result['tables'][$tableName] = $rows;
        $result['meta']['total_records'] += count($rows);
    }

    return $result;
}

function upsert_row_from_source(PDO $pdo, $tableName, array $meta, $sourceInstance, array $row)
{
    $pk = $meta['pk'];
    $columns = $meta['columns'];

    if (!array_key_exists($pk, $row)) {
        return 'skipped';
    }

    $sourcePk = (string) $row[$pk];
    if ($sourcePk === '') {
        return 'skipped';
    }

    $filtered = [];
    foreach ($columns as $col) {
        if (array_key_exists($col, $row)) {
            $filtered[$col] = $row[$col];
        }
    }

    if (empty($filtered)) {
        return 'skipped';
    }

    $targetPk = mapped_target_pk($pdo, $sourceInstance, $tableName, $sourcePk);

    $updatable = $filtered;
    unset($updatable[$pk]);

    if ($targetPk !== null && target_row_exists($pdo, $tableName, $pk, $targetPk)) {
        if (!empty($updatable)) {
            $set = [];
            $values = [];
            foreach ($updatable as $col => $value) {
                $set[] = '`' . $col . '` = ?';
                $values[] = $value;
            }
            $values[] = $targetPk;

            $sql = 'UPDATE `' . $tableName . '` SET ' . implode(', ', $set) . ' WHERE `' . $pk . '` = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        }

        save_mapping($pdo, $sourceInstance, $tableName, $sourcePk, (string)$targetPk);
        return 'updated';
    }

    if (empty($updatable)) {
        return 'skipped';
    }

    $insertColumns = array_keys($updatable);
    $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
    $sql = 'INSERT INTO `' . $tableName . '` (`' . implode('`, `', $insertColumns) . '`) VALUES (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($updatable));

    $newTargetPk = (string) $pdo->lastInsertId();
    if ($newTargetPk === '0' || $newTargetPk === '') {
        $newTargetPk = find_pk_by_non_pk_values($pdo, $tableName, $pk, $updatable);
    }

    save_mapping($pdo, $sourceInstance, $tableName, $sourcePk, (string)$newTargetPk);
    return 'inserted';
}

function mapped_target_pk(PDO $pdo, $sourceInstance, $tableName, $sourcePk)
{
    $stmt = $pdo->prepare(
        'SELECT target_pk FROM sync_record_map WHERE source_instance = ? AND table_name = ? AND source_pk = ? LIMIT 1'
    );
    $stmt->execute([$sourceInstance, $tableName, $sourcePk]);
    $value = $stmt->fetchColumn();

    return $value === false ? null : (string)$value;
}

function save_mapping(PDO $pdo, $sourceInstance, $tableName, $sourcePk, $targetPk)
{
    $stmt = $pdo->prepare(
        "INSERT INTO sync_record_map (source_instance, table_name, source_pk, target_pk)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE target_pk = VALUES(target_pk), updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$sourceInstance, $tableName, $sourcePk, $targetPk]);
}

function target_row_exists(PDO $pdo, $tableName, $pk, $targetPk)
{
    $sql = 'SELECT 1 FROM `' . $tableName . '` WHERE `' . $pk . '` = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$targetPk]);
    return (bool)$stmt->fetchColumn();
}

function find_pk_by_non_pk_values(PDO $pdo, $tableName, $pk, array $values)
{
    $where = [];
    $params = [];
    foreach ($values as $col => $value) {
        $where[] = '`' . $col . '` <=> ?';
        $params[] = $value;
    }

    $sql = 'SELECT `' . $pk . '` FROM `' . $tableName . '` WHERE ' . implode(' AND ', $where) . ' ORDER BY `' . $pk . '` DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $found = $stmt->fetchColumn();
    return $found === false ? '' : (string)$found;
}

function table_metadata(PDO $pdo, $tableName)
{
    static $cache = [];

    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . $tableName . '`');
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $col['Field'];
    }

    $cache[$tableName] = [
        'columns' => $columns,
        'pk' => table_primary_key($pdo, $tableName)
    ];

    return $cache[$tableName];
}

function table_primary_key(PDO $pdo, $tableName)
{
    $stmt = $pdo->query("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($keys) !== 1) {
        return null;
    }

    return $keys[0]['Column_name'] ?? null;
}

function ensure_sync_tables(PDO $pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sync_batches (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            source_instance VARCHAR(120) NOT NULL,
            received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            payload_sha256 CHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL,
            details_json LONGTEXT NULL,
            total_records INT NOT NULL DEFAULT 0,
            INDEX idx_sync_batches_source (source_instance),
            INDEX idx_sync_batches_received_at (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sync_record_map (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            source_instance VARCHAR(120) NOT NULL,
            table_name VARCHAR(64) NOT NULL,
            source_pk VARCHAR(128) NOT NULL,
            target_pk VARCHAR(128) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sync_record (source_instance, table_name, source_pk),
            INDEX idx_sync_record_target (table_name, target_pk)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function bearer_token()
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if ($header === '') {
        return null;
    }

    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }

    return null;
}

function is_https_request()
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if ($https === 'on' || $https === '1') {
        return true;
    }

    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwardedProto === 'https';
}

function http_post_json($url, array $payload, array $extraHeaders = [], $timeout = 30)
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $headers = array_merge([
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($body)
    ], $extraHeaders);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => $timeout,
            'ignore_errors' => true
        ]
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];

    if ($responseBody === false) {
        $error = error_get_last();
        return ['ok' => false, 'error' => $error['message'] ?? 'Erreur réseau inconnue'];
    }

    return [
        'ok' => true,
        'body' => $responseBody,
        'headers' => $responseHeaders
    ];
}

function safe_table_name($table)
{
    $table = strtolower(trim((string)$table));
    if ($table === '' || !preg_match('/^[a-z0-9_]+$/', $table)) {
        return null;
    }
    return $table;
}

function json_out(array $data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
