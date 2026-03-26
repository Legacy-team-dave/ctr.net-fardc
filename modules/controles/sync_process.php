<?php

/**
 * Traitement de synchronisation sélective des contrôles.
 * POST ids[] → synchronise contrôles + litiges associés
 * depuis la base locale (ctr.net-fardc-1) vers la base centrale (ctr.net-fardc-active-web-1).
 * Réservé à ADMIN_IG.
 *
 * Colonnes réelles :
 *   controles   : id(PK), matricule, type_controle, nom_beneficiaire, new_beneficiaire, lien_parente, date_controle, mention, observations, cree_le, id_source, db_source, sync_status, sync_date, sync_version
 *   litiges     : id(PK), matricule, noms, grade, type_controle, nom_beneficiaire, lien_parente, garnison, province, date_controle, observations, cree_le, id_source, db_source, sync_status, sync_date, sync_version
 *   sync_sessions : id_session(PK), id_utilisateur, type_sync, date_debut, date_fin, status, details
 *   sync_conflits : id_conflit(PK), id_session, table_concernee, id_record_terrain, id_record_central, version_terrain, version_central, donnees_terrain, donnees_central, resolution, date_resolution, id_utilisateur_resolution
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
    header('Location: liste.php');
    exit;
}

if (!isset($_POST['ids']) || empty($_POST['ids'])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Aucun contrôle sélectionné.'];
    header('Location: liste.php');
    exit;
}

$ids = array_map('intval', $_POST['ids']);
$instance_id = app_env('SYNC_INSTANCE_ID', 'terrain');

// 1. Créer une session de synchronisation dans la base LOCALE (l'utilisateur existe ici, pas dans la centrale)
$stmt = $pdo->prepare("INSERT INTO sync_sessions (id_utilisateur, type_sync, date_debut, status) VALUES (?, 'controles', NOW(), 'en_cours')");
$stmt->execute([$_SESSION['user_id']]);
$session_id = $pdo->lastInsertId();

$details = ['inserted' => 0, 'updated' => 0, 'conflicts' => 0];

try {
    $pdo_central->beginTransaction();

    foreach ($ids as $id_terrain) {
        // Récupérer le contrôle terrain
        $stmt = $pdo->prepare("SELECT * FROM controles WHERE id = ?");
        $stmt->execute([$id_terrain]);
        $controle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$controle) continue;

        // --- 2a. Synchroniser le contrôle ---
        $sync_version_terrain = $controle['sync_version'] ?? 1;

        $stmt_exist = $pdo_central->prepare("SELECT id, sync_version FROM controles WHERE id_source = ? AND db_source = ?");
        $stmt_exist->execute([$controle['id'], $instance_id]);
        $existant = $stmt_exist->fetch(PDO::FETCH_ASSOC);

        if ($existant) {
            if ($sync_version_terrain > ($existant['sync_version'] ?? 0)) {
                $pdo_central->prepare("UPDATE controles SET
                    matricule = ?, type_controle = ?, nom_beneficiaire = ?, new_beneficiaire = ?,
                    lien_parente = ?, date_controle = ?, mention = ?, observations = ?,
                    sync_version = ?, sync_date = NOW(), sync_status = 'synced'
                    WHERE id = ?")->execute([
                    $controle['matricule'],
                    $controle['type_controle'],
                    $controle['nom_beneficiaire'],
                    $controle['new_beneficiaire'],
                    $controle['lien_parente'],
                    $controle['date_controle'],
                    $controle['mention'],
                    $controle['observations'],
                    $sync_version_terrain + 1,
                    $existant['id']
                ]);
                $details['updated']++;
            } else {
                // Conflit — stocké en base locale (FK sync_sessions)
                $pdo->prepare("INSERT INTO sync_conflits (id_session, table_concernee, id_record_terrain, id_record_central, version_terrain, version_central, donnees_terrain, donnees_central, resolution)
                    VALUES (?, 'controles', ?, ?, ?, ?, ?, ?, 'pending')")->execute([
                    $session_id,
                    $controle['id'],
                    $existant['id'],
                    $sync_version_terrain,
                    $existant['sync_version'],
                    json_encode($controle),
                    json_encode($existant)
                ]);
                $pdo->prepare("UPDATE controles SET sync_status = 'conflict' WHERE id = ?")->execute([$controle['id']]);
                $pdo_central->prepare("UPDATE controles SET sync_status = 'conflict' WHERE id = ?")->execute([$existant['id']]);
                $details['conflicts']++;
            }
        } else {
            // Insertion nouvelle — colonnes explicites pour éviter les colonnes manquantes
            $pdo_central->prepare("INSERT INTO controles (matricule, type_controle, nom_beneficiaire, new_beneficiaire, lien_parente, date_controle, mention, observations, cree_le, id_source, db_source, sync_status, sync_date, sync_version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'synced', NOW(), ?)")->execute([
                $controle['matricule'],
                $controle['type_controle'],
                $controle['nom_beneficiaire'],
                $controle['new_beneficiaire'],
                $controle['lien_parente'],
                $controle['date_controle'],
                $controle['mention'],
                $controle['observations'],
                $controle['cree_le'],
                $controle['id'],
                $instance_id,
                $sync_version_terrain
            ]);
            $details['inserted']++;

            $pdo->prepare("UPDATE controles SET sync_status = 'synced', sync_date = NOW() WHERE id = ?")->execute([$controle['id']]);
        }

        // --- 2b. Synchroniser les litiges associés ---
        $date_only = substr($controle['date_controle'], 0, 10);
        $stmt_lit = $pdo->prepare("SELECT * FROM litiges WHERE matricule = ? AND date_controle = ?");
        $stmt_lit->execute([$controle['matricule'], $date_only]);

        foreach ($stmt_lit->fetchAll(PDO::FETCH_ASSOC) as $litige) {
            $lit_version = $litige['sync_version'] ?? 1;
            $stmt_le = $pdo_central->prepare("SELECT id, sync_version FROM litiges WHERE id_source = ? AND db_source = ?");
            $stmt_le->execute([$litige['id'], $instance_id]);
            $lit_central = $stmt_le->fetch(PDO::FETCH_ASSOC);

            if ($lit_central) {
                if ($lit_version > ($lit_central['sync_version'] ?? 0)) {
                    $pdo_central->prepare("UPDATE litiges SET
                        matricule = ?, noms = ?, grade = ?, type_controle = ?, nom_beneficiaire = ?,
                        lien_parente = ?, garnison = ?, province = ?, date_controle = ?, observations = ?,
                        sync_version = ?, sync_date = NOW(), sync_status = 'synced'
                        WHERE id = ?")->execute([
                        $litige['matricule'],
                        $litige['noms'],
                        $litige['grade'],
                        $litige['type_controle'],
                        $litige['nom_beneficiaire'],
                        $litige['lien_parente'],
                        $litige['garnison'],
                        $litige['province'],
                        $litige['date_controle'],
                        $litige['observations'],
                        $lit_version + 1,
                        $lit_central['id']
                    ]);
                } else {
                    $pdo->prepare("INSERT INTO sync_conflits (id_session, table_concernee, id_record_terrain, id_record_central, version_terrain, version_central, donnees_terrain, donnees_central, resolution)
                        VALUES (?, 'litiges', ?, ?, ?, ?, ?, ?, 'pending')")->execute([
                        $session_id,
                        $litige['id'],
                        $lit_central['id'],
                        $lit_version,
                        $lit_central['sync_version'],
                        json_encode($litige),
                        json_encode($lit_central)
                    ]);
                    $pdo->prepare("UPDATE litiges SET sync_status = 'conflict' WHERE id = ?")->execute([$litige['id']]);
                    $pdo_central->prepare("UPDATE litiges SET sync_status = 'conflict' WHERE id = ?")->execute([$lit_central['id']]);
                }
            } else {
                // Insertion — colonnes explicites
                $pdo_central->prepare("INSERT INTO litiges (matricule, noms, grade, type_controle, nom_beneficiaire, lien_parente, garnison, province, date_controle, observations, cree_le, id_source, db_source, sync_status, sync_date, sync_version)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'synced', NOW(), ?)")->execute([
                    $litige['matricule'],
                    $litige['noms'],
                    $litige['grade'],
                    $litige['type_controle'],
                    $litige['nom_beneficiaire'],
                    $litige['lien_parente'],
                    $litige['garnison'],
                    $litige['province'],
                    $litige['date_controle'],
                    $litige['observations'],
                    $litige['cree_le'],
                    $litige['id'],
                    $instance_id,
                    $lit_version
                ]);
                $pdo->prepare("UPDATE litiges SET sync_status = 'synced', sync_date = NOW() WHERE id = ?")->execute([$litige['id']]);
            }
        }
    }

    $pdo_central->commit();
} catch (Exception $e) {
    $pdo_central->rollBack();
    error_log("Erreur sync_process: " . $e->getMessage());
    $pdo->prepare("UPDATE sync_sessions SET date_fin = NOW(), status = 'echec', details = ? WHERE id_session = ?")->execute([json_encode(['error' => $e->getMessage()]), $session_id]);
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Erreur lors de la synchronisation.'];
    header('Location: liste.php');
    exit;
}

// 3. Finaliser la session
$status = ($details['conflicts'] > 0) ? 'partiel' : 'succes';
$details_json = json_encode($details);
$pdo->prepare("UPDATE sync_sessions SET date_fin = NOW(), status = ?, details = ? WHERE id_session = ?")->execute([$status, $details_json, $session_id]);

// 3b. Enregistrer la réception dans la base centrale (pour affichage côté central)
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

    $operateur_nom = $_SESSION['user_nom'] ?? 'Inconnu';
    $operateur_profil = $_SESSION['user_profil'] ?? '';

    // Garnisons distinctes des militaires contrôlés
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt_garn = $pdo->prepare("SELECT DISTINCT m.garnison FROM militaires m JOIN controles c ON c.matricule = m.matricule WHERE c.id IN ({$placeholders}) AND m.garnison IS NOT NULL AND m.garnison != ''");
    $stmt_garn->execute($ids);
    $garnisons = implode(', ', array_column($stmt_garn->fetchAll(PDO::FETCH_ASSOC), 'garnison'));

    $litiges_count = 0;
    foreach ($ids as $id_t) {
        $sc = $pdo->prepare("SELECT matricule, date_controle FROM controles WHERE id = ?");
        $sc->execute([$id_t]);
        $row = $sc->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $sl = $pdo->prepare("SELECT COUNT(*) FROM litiges WHERE matricule = ? AND date_controle = ?");
            $sl->execute([$row['matricule'], substr($row['date_controle'], 0, 10)]);
            $litiges_count += (int)$sl->fetchColumn();
        }
    }

    $pdo_central->prepare("INSERT INTO sync_receptions (instance_id, operateur_nom, operateur_profil, type_sync, garnisons, controles_count, litiges_count, date_reception, details) VALUES (?, ?, ?, 'controles', ?, ?, ?, NOW(), ?)")
        ->execute([$instance_id, $operateur_nom, $operateur_profil, $garnisons, $details['inserted'] + $details['updated'], $litiges_count, $details_json]);
} catch (Exception $e) {
    error_log("Erreur enregistrement sync_receptions: " . $e->getMessage());
}

// 4. Journaliser
log_action('SYNC_CONTROLES', 'controles', $session_id, "Sync: {$details['inserted']} insérés, {$details['updated']} mis à jour, {$details['conflicts']} conflits");

$msg = "{$details['inserted']} contrôle(s) synchronisé(s), {$details['updated']} mis à jour";
if ($details['conflicts'] > 0) $msg .= ", {$details['conflicts']} conflit(s)";
$_SESSION['flash_message'] = ['type' => ($details['conflicts'] > 0 ? 'warning' : 'success'), 'text' => $msg];
header('Location: liste.php');
exit;
