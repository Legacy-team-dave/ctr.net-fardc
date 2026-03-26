<?php

/**
 * Gestion du fichier de synchronisation unique.
 * 
 * Maintient un fichier JSON prêt à être envoyé au serveur central.
 * Le fichier est mis à jour :
 *  - Toutes les 8h via le cron (backup_cron.php)
 *  - Après chaque insertion de données (contrôles, litiges, militaires)
 */

/**
 * Chemin du fichier d'export sync prêt à envoyer.
 */
function get_sync_file_path(): string
{
    return get_backup_dir_path() . 'sync_export_ready.json';
}

/**
 * Chemin du fichier d'état du sync.
 */
function get_sync_state_path(): string
{
    return get_backup_dir_path() . 'sync_file_state.json';
}

/**
 * Chemin du fichier drapeau "données modifiées".
 */
function get_sync_dirty_flag_path(): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ctr_sync_dirty_' . md5(__DIR__) . '.flag';
}

/**
 * Marque les données comme modifiées (à re-exporter).
 * Appeler après chaque INSERT/UPDATE/DELETE sur les tables sync.
 */
function mark_sync_dirty(): void
{
    $flag = get_sync_dirty_flag_path();
    @touch($flag);
}

/**
 * Vérifie si les données ont été modifiées depuis le dernier export.
 */
function is_sync_dirty(): bool
{
    return file_exists(get_sync_dirty_flag_path());
}

/**
 * Efface le drapeau "données modifiées".
 */
function clear_sync_dirty(): void
{
    $flag = get_sync_dirty_flag_path();
    if (file_exists($flag)) {
        @unlink($flag);
    }
}

/**
 * Lit l'état du dernier export sync.
 */
function read_sync_file_state(): array
{
    $path = get_sync_state_path();
    if (!is_file($path)) {
        return [
            'last_export_at' => 0,
            'max_ids' => [],
            'total_records' => 0
        ];
    }
    $data = @json_decode(@file_get_contents($path), true);
    return is_array($data) ? $data : [
        'last_export_at' => 0,
        'max_ids' => [],
        'total_records' => 0
    ];
}

/**
 * Sauvegarde l'état du dernier export sync.
 */
function write_sync_file_state(array $state): void
{
    $path = get_sync_state_path();
    @file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Vérifie si le fichier sync doit être reconstruit.
 * Retourne true si :
 *  - Le fichier n'existe pas
 *  - Le drapeau dirty est posé
 *  - Les max IDs ont changé (filet de sécurité)
 */
function should_rebuild_sync_file(PDO $pdo): bool
{
    if (!file_exists(get_sync_file_path())) {
        return true;
    }

    if (is_sync_dirty()) {
        return true;
    }

    // Vérification par max IDs (filet de sécurité)
    $state = read_sync_file_state();
    $config = sync_config();
    foreach ($config['allowed_tables'] as $table) {
        $pk = table_primary_key($pdo, $table);
        if ($pk === null) {
            continue;
        }
        $currentMax = (int) $pdo->query("SELECT COALESCE(MAX(`{$pk}`), 0) FROM `{$table}`")->fetchColumn();
        $savedMax = (int) ($state['max_ids'][$table] ?? 0);
        if ($currentMax !== $savedMax) {
            return true;
        }
    }

    return false;
}

/**
 * Reconstruit le fichier d'export sync.
 * Génère un JSON contenant toutes les données des tables à synchroniser.
 */
function rebuild_sync_file(PDO $pdo): array
{
    $config = sync_config();
    $payload = build_export_payload($pdo, $config['allowed_tables'], $config['instance_id']);

    $path = get_sync_file_path();
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if (@file_put_contents($path, $json, LOCK_EX) === false) {
        error_log("Échec écriture fichier sync: {$path}");
        return ['success' => false, 'reason' => 'write_failed'];
    }

    // Mettre à jour l'état
    $maxIds = [];
    foreach ($config['allowed_tables'] as $table) {
        $pk = table_primary_key($pdo, $table);
        if ($pk !== null) {
            $maxIds[$table] = (int) $pdo->query("SELECT COALESCE(MAX(`{$pk}`), 0) FROM `{$table}`")->fetchColumn();
        }
    }

    write_sync_file_state([
        'last_export_at' => time(),
        'last_export_date' => date('c'),
        'max_ids' => $maxIds,
        'total_records' => $payload['meta']['total_records'] ?? 0,
        'file_size' => filesize($path),
        'sha256' => hash_file('sha256', $path)
    ]);

    clear_sync_dirty();

    return [
        'success' => true,
        'reason' => 'rebuilt',
        'total_records' => $payload['meta']['total_records'] ?? 0,
        'file' => $path,
        'file_size' => filesize($path)
    ];
}

/**
 * Met à jour le fichier sync si nécessaire.
 * Appelé par le cron et avant chaque push.
 */
function maybe_update_sync_file(PDO $pdo): array
{
    if (!should_rebuild_sync_file($pdo)) {
        $state = read_sync_file_state();
        return [
            'success' => true,
            'reason' => 'up_to_date',
            'total_records' => $state['total_records'] ?? 0,
            'last_export_at' => $state['last_export_at'] ?? 0
        ];
    }

    return rebuild_sync_file($pdo);
}

/**
 * Lit le contenu du fichier sync pré-construit.
 * Retourne le payload décodé ou null si le fichier n'existe pas.
 */
function read_sync_file(): ?array
{
    $path = get_sync_file_path();
    if (!is_file($path)) {
        return null;
    }

    $data = @json_decode(@file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

/**
 * Informations sur le fichier sync actuel.
 */
function get_sync_file_info(): array
{
    $path = get_sync_file_path();
    $state = read_sync_file_state();

    if (!is_file($path)) {
        return [
            'exists' => false,
            'dirty' => is_sync_dirty(),
            'state' => $state
        ];
    }

    return [
        'exists' => true,
        'file' => $path,
        'file_size' => filesize($path),
        'file_modified' => filemtime($path),
        'dirty' => is_sync_dirty(),
        'state' => $state
    ];
}