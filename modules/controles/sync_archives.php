<?php

/**
 * Synchronisation des fichiers d'archive (sauvegardes SQL, logs, pièces jointes).
 * Parcourt le répertoire des archives terrain, calcule le hash SHA256,
 * copie les fichiers vers le répertoire central si non existants.
 * Réservé à ADMIN_IG.
 *
 * Tables réelles :
 *   sync_sessions : id_session(PK), id_utilisateur, type_sync, date_debut, date_fin, status, details (PAS de instance_id)
 *   archives_sync : id_archive(PK), id_session, nom_fichier, chemin_source, chemin_dest(NOT NULL), taille, hash_sha256(nullable), date_creation(NOT NULL), date_sync(NOT NULL), status(enum:'ok','erreur'), message_erreur
 */
require_once '../../includes/functions.php';
require_once '../../config/database_central.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit;
}
check_profil(['ADMIN_IG']);

if (!$pdo_central) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Connexion à la base centrale impossible.'];
    header('Location: sync.php');
    exit;
}

// Chemins configurables
$source_dir = realpath(__DIR__ . '/../../backups/');
if ($source_dir) $source_dir .= DIRECTORY_SEPARATOR;
$dest_dir = app_env('SYNC_ARCHIVES_DEST', 'C:/laragon/www/ctr-net-fardc_active_front_web/archives/');

if (!$source_dir || !is_dir($source_dir)) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Répertoire des archives introuvable.'];
    header('Location: sync.php');
    exit;
}

if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0750, true);
}

$force = isset($_POST['force']) && $_POST['force'] === '1';

// Créer une session de synchronisation dans la base LOCALE (FK vers utilisateurs)
$stmt = $pdo->prepare("INSERT INTO sync_sessions (id_utilisateur, type_sync, date_debut, status) VALUES (?, 'archives', NOW(), 'en_cours')");
$stmt->execute([$_SESSION['user_id']]);
$session_id = $pdo->lastInsertId();

$resultats = ['ok' => 0, 'skipped' => 0, 'error' => 0];

$files = scandir($source_dir);
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $source_path = $source_dir . $file;
    if (!is_file($source_path)) continue;

    $hash = hash_file('sha256', $source_path);
    $date_creation = date('Y-m-d H:i:s', filemtime($source_path));

    // Vérifier si déjà synchronisé (par hash)
    if (!$force) {
        $check = $pdo->prepare("SELECT id_archive FROM archives_sync WHERE hash_sha256 = ?");
        $check->execute([$hash]);
        if ($check->fetch()) {
            $resultats['skipped']++;
            continue;
        }
    }

    $dest_path = $dest_dir . $file;
    if (copy($source_path, $dest_path)) {
        $taille = filesize($source_path);
        $pdo->prepare("INSERT INTO archives_sync (id_session, nom_fichier, chemin_source, chemin_dest, taille, hash_sha256, date_creation, date_sync, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'ok')")->execute([$session_id, $file, $source_path, $dest_path, $taille, $hash, $date_creation]);
        $resultats['ok']++;
    } else {
        $pdo->prepare("INSERT INTO archives_sync (id_session, nom_fichier, chemin_source, chemin_dest, taille, hash_sha256, date_creation, date_sync, status, message_erreur)
            VALUES (?, ?, ?, ?, 0, ?, ?, NOW(), 'erreur', ?)")->execute([$session_id, $file, $source_path, $dest_path, $hash, $date_creation, 'Échec de la copie']);
        $resultats['error']++;
    }
}

// Finaliser la session
$details_json = json_encode($resultats);
$status = ($resultats['error'] === 0) ? 'succes' : (($resultats['ok'] > 0) ? 'partiel' : 'echec');
$pdo->prepare("UPDATE sync_sessions SET date_fin = NOW(), status = ?, details = ? WHERE id_session = ?")
    ->execute([$status, $details_json, $session_id]);

// Enregistrer la réception dans la base centrale (pour affichage côté central)
try {
    $pdo_central->exec("CREATE TABLE IF NOT EXISTS sync_receptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        instance_id VARCHAR(50) NOT NULL,
        operateur_nom VARCHAR(100),
        operateur_profil VARCHAR(50),
        type_sync VARCHAR(50) DEFAULT 'controles',
        garnisons TEXT,
        controles_count INT DEFAULT 0,
        litiges_count INT DEFAULT 0,
        archives_count INT DEFAULT 0,
        date_reception DATETIME DEFAULT CURRENT_TIMESTAMP,
        details JSON
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $instance_id = app_env('SYNC_INSTANCE_ID', 'terrain');
    $operateur_nom = $_SESSION['user_nom'] ?? 'Inconnu';
    $operateur_profil = $_SESSION['user_profil'] ?? '';

    $pdo_central->prepare("INSERT INTO sync_receptions (instance_id, operateur_nom, operateur_profil, type_sync, archives_count, date_reception, details) VALUES (?, ?, ?, 'archives', ?, NOW(), ?)")
        ->execute([$instance_id, $operateur_nom, $operateur_profil, $resultats['ok'], $details_json]);
} catch (Exception $e) {
    error_log("Erreur enregistrement sync_receptions archives: " . $e->getMessage());
}

log_action('SYNC_ARCHIVES', 'archives_sync', $session_id, "Archives: {$resultats['ok']} copiés, {$resultats['skipped']} ignorés, {$resultats['error']} erreurs");

$_SESSION['flash_message'] = [
    'type' => ($resultats['error'] > 0 ? 'warning' : 'success'),
    'text' => "{$resultats['ok']} fichier(s) synchronisé(s), {$resultats['skipped']} ignoré(s), {$resultats['error']} erreur(s)."
];
header('Location: sync.php');
exit;
