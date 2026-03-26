<?php

/**
 * Résolution d'un conflit de synchronisation.
 * POST : id_conflit + resolution (terrain_wins | central_wins | ignored)
 * Met à jour sync_conflits avec la résolution choisie.
 * Réservé à ADMIN_IG.
 *
 * Colonnes réelles sync_conflits :
 *   resolution enum('pending','terrain','central','merge')
 *   date_resolution datetime
 *   id_utilisateur_resolution int
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sync.php');
    exit;
}

$id_conflit = (int)($_POST['id_conflit'] ?? 0);
$resolution_input = $_POST['resolution'] ?? '';

if ($id_conflit <= 0) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Conflit invalide.'];
    header('Location: sync.php');
    exit;
}

// Mapper les valeurs du formulaire vers les valeurs enum de la DB
$resolution_map = [
    'terrain_wins' => 'terrain',
    'central_wins' => 'central',
    'ignored'      => 'merge'  // merge = résolution manuelle/ignorée
];

if (!isset($resolution_map[$resolution_input])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Résolution invalide.'];
    header('Location: sync.php');
    exit;
}

$resolution_value = $resolution_map[$resolution_input];

// Récupérer le conflit (stocké en base locale)
$stmt = $pdo->prepare("SELECT * FROM sync_conflits WHERE id_conflit = ? AND resolution = 'pending'");
$stmt->execute([$id_conflit]);
$conflit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conflit) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Conflit introuvable ou déjà résolu.'];
    header('Location: sync.php');
    exit;
}

try {
    $pdo_central->beginTransaction();

    $table = $conflit['table_concernee'];

    // Si terrain gagne → appliquer les données terrain sur le central
    if ($resolution_value === 'terrain') {
        $donnees_terrain = json_decode($conflit['donnees_terrain'], true);
        $id_central = $conflit['id_record_central'];

        if ($donnees_terrain && $id_central && in_array($table, ['controles', 'litiges'])) {
            if ($table === 'controles') {
                $fields = ['matricule', 'type_controle', 'nom_beneficiaire', 'new_beneficiaire', 'lien_parente', 'date_controle', 'mention', 'observations'];
            } else {
                $fields = ['matricule', 'noms', 'grade', 'type_controle', 'nom_beneficiaire', 'lien_parente', 'garnison', 'province', 'date_controle', 'observations'];
            }

            $sets = [];
            $values = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $donnees_terrain)) {
                    $sets[] = "`$f` = ?";
                    $values[] = $donnees_terrain[$f];
                }
            }
            $sets[] = "sync_status = 'synced'";
            $sets[] = "sync_date = NOW()";
            $sets[] = "sync_version = sync_version + 1";
            $values[] = $id_central;

            $pdo_central->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id = ?")->execute($values);
        }
    }

    // Si central gagne → marquer le central comme synced
    if ($resolution_value === 'central') {
        $id_central = $conflit['id_record_central'];
        if ($id_central && in_array($table, ['controles', 'litiges'])) {
            $pdo_central->prepare("UPDATE `$table` SET sync_status = 'synced', sync_date = NOW() WHERE id = ?")->execute([$id_central]);
        }
    }

    $pdo_central->commit();

    // MAJ locale (terrain) — hors transaction centrale
    $id_terrain = $conflit['id_record_terrain'];
    if ($id_terrain && in_array($table, ['controles', 'litiges'])) {
        $pdo->prepare("UPDATE `$table` SET sync_status = 'synced', sync_date = NOW() WHERE id = ?")->execute([$id_terrain]);
    }

    // Marquer le conflit comme résolu (base locale)
    $pdo->prepare("UPDATE sync_conflits SET resolution = ?, date_resolution = NOW(), id_utilisateur_resolution = ? WHERE id_conflit = ?")
        ->execute([$resolution_value, $_SESSION['user_id'], $id_conflit]);

    log_action('SYNC_RESOLVE', 'sync_conflits', $id_conflit, "Conflit #{$id_conflit} résolu: {$resolution_value}");
    $_SESSION['flash_message'] = ['type' => 'success', 'text' => "Conflit #{$id_conflit} résolu ({$resolution_input})."];
} catch (Exception $e) {
    if ($pdo_central->inTransaction()) {
        $pdo_central->rollBack();
    }
    error_log("Erreur sync_resolve: " . $e->getMessage());
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Erreur lors de la résolution du conflit.'];
}

header('Location: sync.php');
exit;
