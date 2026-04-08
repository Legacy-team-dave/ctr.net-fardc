<?php
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit;
}

check_profil(['ADMIN_IG', 'OPERATEUR']);

// Mode setup = après configuration initiale des préférences (formulaire éditable)
// Mode lecture seule = accès depuis index.php (consultation uniquement)
$is_setup = !empty($_SESSION['setup_equipes']);

// Clic sur "Continuer" → fin du setup équipes puis accès au contrôle
if (isset($_GET['continue']) && $is_setup) {
    unset($_SESSION['setup_equipes']);
    header('Location: ' . app_url('modules/controles/ajouter.php'));
    exit;
}

// Créer la table equipes si elle n'existe pas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `equipes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `matricule` VARCHAR(50) NULL,
        `noms` VARCHAR(150) NOT NULL,
        `grade` VARCHAR(50) NOT NULL,
        `unites` VARCHAR(150) NULL,
        `role` VARCHAR(50) NOT NULL,
        `id_source` INT NULL,
        `db_source` VARCHAR(50) NULL DEFAULT 'local',
        `sync_status` ENUM('local','synced') NOT NULL DEFAULT 'local',
        `sync_date` DATETIME NULL,
        `sync_version` INT NOT NULL DEFAULT 1,
        INDEX `idx_equipes_sync_status` (`sync_status`),
        INDEX `idx_equipes_source` (`db_source`, `id_source`),
        INDEX `idx_equipes_matricule` (`matricule`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach ([
        "ALTER TABLE `equipes` ADD COLUMN `matricule` VARCHAR(50) NULL AFTER `id`",
        "ALTER TABLE `equipes` ADD COLUMN `unites` VARCHAR(150) NULL AFTER `grade`",
        "ALTER TABLE `equipes` ADD COLUMN `id_source` INT NULL AFTER `role`",
        "ALTER TABLE `equipes` ADD COLUMN `db_source` VARCHAR(50) NULL DEFAULT 'local' AFTER `id_source`",
        "ALTER TABLE `equipes` ADD COLUMN `sync_status` ENUM('local','synced') NOT NULL DEFAULT 'local' AFTER `db_source`",
        "ALTER TABLE `equipes` ADD COLUMN `sync_date` DATETIME NULL AFTER `sync_status`",
        "ALTER TABLE `equipes` ADD COLUMN `sync_version` INT NOT NULL DEFAULT 1 AFTER `sync_date`"
    ] as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate column name') === false) {
                throw $e;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Erreur création table equipes: " . $e->getMessage());
}

$error = null;
$success = null;
$toast_message = $_SESSION['toast_message'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast_message'], $_SESSION['toast_type']);
$roles_equipe = ["Chef d'équipe", 'Inspecteur', 'Opérateur PC', 'Contrôleur', 'Superviseur'];

if (!function_exists('local_equipe_role_order_sql')) {
    function local_equipe_role_order_sql(string $column = 'role'): string
    {
        return "CASE LOWER(TRIM($column))
            WHEN 'chef d''équipe' THEN 1
            WHEN 'chef d''equipe' THEN 1
            WHEN 'inspecteur' THEN 2
            WHEN 'opérateur pc' THEN 3
            WHEN 'operateur pc' THEN 3
            WHEN 'opérateur' THEN 3
            WHEN 'operateur' THEN 3
            WHEN 'contrôleur' THEN 4
            WHEN 'controleur' THEN 4
            WHEN 'contôleur' THEN 4
            WHEN 'superviseur' THEN 5
            ELSE 99 END";
    }
}

if (!function_exists('local_equipe_role_display_label')) {
    function local_equipe_role_display_label(?string $role): string
    {
        $normalized = strtolower(trim((string) $role));
        return match ($normalized) {
            "chef d'équipe", "chef d'equipe" => "Chef d'équipe",
            'inspecteur' => 'Inspecteur',
            'opérateur pc', 'operateur pc', 'opérateur', 'operateur' => 'Opérateur PC',
            'contrôleur', 'controleur', 'contôleur' => 'Contrôleur',
            'superviseur' => 'Superviseur',
            default => trim((string) $role) !== '' ? trim((string) $role) : 'Non défini',
        };
    }
}

if (!function_exists('local_equipe_role_badge_class')) {
    function local_equipe_role_badge_class(?string $role): string
    {
        $normalized = strtolower(trim((string) $role));
        return match ($normalized) {
            "chef d'équipe", "chef d'equipe" => 'role-pill role-chef',
            'inspecteur' => 'role-pill role-inspecteur',
            'opérateur pc', 'operateur pc', 'opérateur', 'operateur' => 'role-pill role-operateur',
            'contrôleur', 'controleur', 'contôleur' => 'role-pill role-controleur',
            'superviseur' => 'role-pill role-superviseur',
            default => 'role-pill role-default',
        };
    }
}

// Actions d'édition uniquement en mode setup
if ($is_setup) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $teamAction = trim((string) ($_POST['team_action'] ?? 'add'));

        if ($teamAction === 'delete' && is_numeric($_POST['member_id'] ?? null)) {
            try {
                $memberId = (int) $_POST['member_id'];
                $stmt = $pdo->prepare("SELECT noms FROM equipes WHERE id = ?");
                $stmt->execute([$memberId]);
                $deletedMemberName = trim((string) $stmt->fetchColumn());

                $stmt = $pdo->prepare("DELETE FROM equipes WHERE id = ?");
                $stmt->execute([$memberId]);

                $_SESSION['toast_message'] = $deletedMemberName !== ''
                    ? 'Membre <strong>' . htmlspecialchars($deletedMemberName, ENT_QUOTES, 'UTF-8') . '</strong> supprimé avec succès.'
                    : 'Le membre sélectionné a été supprimé avec succès.';
                $_SESSION['toast_type'] = 'success';

                header('Location: ' . app_url('modules/equipes/index.php'));
                exit;
            } catch (PDOException $e) {
                error_log("Erreur suppression membre: " . $e->getMessage());
                $error = "Erreur lors de la suppression.";
            }
        }

        if ($teamAction === 'add') {
            $matricule = trim($_POST['matricule'] ?? '');
            $noms = trim($_POST['noms'] ?? '');
            $grade = trim($_POST['grade'] ?? '');
            $unites = trim($_POST['unites'] ?? '');
            $role = trim($_POST['role'] ?? '');

            if (empty($matricule) || empty($noms) || empty($grade) || empty($unites) || empty($role)) {
                $error = "Tous les champs sont obligatoires.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO equipes (matricule, noms, grade, unites, role, db_source, sync_status, sync_date, sync_version) VALUES (?, ?, ?, ?, ?, 'local', 'local', NULL, 1)");
                    $stmt->execute([$matricule, $noms, $grade, $unites, $role]);

                    $_SESSION['toast_message'] = 'Membre <strong>' . htmlspecialchars($noms, ENT_QUOTES, 'UTF-8') . '</strong> enregistré avec succès.';
                    $_SESSION['toast_type'] = 'success';

                    header('Location: ' . app_url('modules/equipes/index.php'));
                    exit;
                } catch (PDOException $e) {
                    error_log("Erreur ajout membre: " . $e->getMessage());
                    $error = "Erreur lors de l'ajout.";
                }
            }
        }
    }
}

// Récupérer les membres
try {
    $membres = $pdo->query("SELECT * FROM equipes ORDER BY " . local_equipe_role_order_sql('role') . ", grade ASC, noms ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $membres = [];
    error_log("Erreur récupération équipes: " . $e->getMessage());
}

$total_membres = count($membres);
$local_source_label = trim(preferred_garnison_label());
if ($local_source_label === '') {
    $local_source_label = 'SITE LOCAL 01';
}

$role_counts = array_fill_keys($roles_equipe, 0);
foreach ($membres as $membreStat) {
    $roleLabel = local_equipe_role_display_label($membreStat['role'] ?? '');
    if (isset($role_counts[$roleLabel])) {
        $role_counts[$roleLabel]++;
    }
}
unset($membreStat);

$role_stat_cards = [
    [
        'label' => "Chef d'équipe",
        'count' => $role_counts["Chef d'équipe"] ?? 0,
        'icon' => 'fas fa-user-tie',
        'variant' => 'role-chef-card',
    ],
    [
        'label' => 'Inspecteur',
        'count' => $role_counts['Inspecteur'] ?? 0,
        'icon' => 'fas fa-user-secret',
        'variant' => 'role-inspecteur-card',
    ],
    [
        'label' => 'Opérateur PC',
        'count' => $role_counts['Opérateur PC'] ?? 0,
        'icon' => 'fas fa-desktop',
        'variant' => 'role-operateur-card',
    ],
    [
        'label' => 'Contrôleur',
        'count' => $role_counts['Contrôleur'] ?? 0,
        'icon' => 'fas fa-clipboard-check',
        'variant' => 'role-controleur-card',
    ],
    [
        'label' => 'Superviseur',
        'count' => $role_counts['Superviseur'] ?? 0,
        'icon' => 'fas fa-user-shield',
        'variant' => 'role-superviseur-card',
    ],
];

$selected_role = trim((string) ($_GET['role'] ?? ''));
$filtered_membres = array_values(array_filter($membres, static function (array $membre) use ($selected_role): bool {
    $roleLabel = local_equipe_role_display_label($membre['role'] ?? '');
    return $selected_role === '' || $roleLabel === $selected_role;
}));
$initial_team_count = count($filtered_membres);
$display_membres = $membres;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTR.NET FARDC - Équipe de contrôle</title>
    <link rel="stylesheet" href="../../assets/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/adminlte.min.css">
    <link rel="stylesheet" href="../../assets/css/tables-unified.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Barlow', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 10px;
            background: linear-gradient(135deg, rgba(30, 30, 30, 0.85), rgba(20, 60, 20, 0.85)),
                url('../../assets/img/fardc2.png') center/cover no-repeat fixed;
        }

        .equipe-card {
            width: 100%;
            max-width: 1380px;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.24);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .equipe-header {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            padding: 10px 16px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .equipe-header h2 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 3px 8px;
            border-radius: 18px;
            font-size: 0.76rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .equipe-body {
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .info-line {
            background: #e8f5e9;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .alert-error {
            background: #fce4ec;
            color: #c62828;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #a5d6a7;
        }

        .toast-container {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast-message {
            min-width: 300px;
            max-width: 420px;
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: #fff;
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.88rem;
            animation: slideInRight 0.25s ease;
        }

        .toast-message.error {
            background: linear-gradient(135deg, #dc3545, #b02a37);
        }

        .toast-message.warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(24px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 14px;
            border: 1px solid #e0e0e0;
        }

        .form-section h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-bottom: 10px;
        }

        .form-row.form-row-member {
            grid-template-columns: 1.05fr 1.8fr 0.95fr 1.35fr 1.15fr auto;
            align-items: end;
        }

        .form-actions-row {
            display: contents;
        }

        .form-group.form-group-action {
            justify-content: flex-end;
        }

        .form-group.form-group-action label {
            visibility: hidden;
        }

        .form-group.form-group-action .btn-add {
            width: 100%;
            min-height: 40px;
            height: 40px;
            padding: 6px 12px;
            justify-content: center;
            white-space: nowrap;
            border-radius: 6px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .form-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select {
            padding: 6px 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: 'Barlow', sans-serif;
            transition: border-color 0.2s;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }

        .btn-add {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-family: 'Barlow', sans-serif;
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-add:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 4px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 12px 14px;
            min-width: 0;
            width: 100%;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover,
        .stat-card.source-highlight {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(46, 125, 50, 0.14);
            border-color: rgba(46, 125, 50, 0.2);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.35rem;
            box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
            flex-shrink: 0;
        }

        .stat-icon.total-effectifs {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
        }

        .stat-icon.present {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }

        .stat-icon.favorable {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        }

        .stat-icon.inactif {
            background: linear-gradient(135deg, #6c757d, #545b62);
        }

        .stat-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
            flex: 1;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 600;
            font-size: 0.76rem;
            line-height: 1.2;
        }

        .stat-value {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.05;
            margin: 0;
        }

        .stat-value.total-effectifs { color: #2e7d32; }
        .stat-value.present { color: #28a745; }
        .stat-value.favorable { color: #e0a800; }
        .stat-value.inactif { color: #6c757d; }

        .stat-icon.role-chef-card { background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); }
        .stat-icon.role-inspecteur-card { background: linear-gradient(135deg, #17a2b8 0%, #11707f 100%); }
        .stat-icon.role-operateur-card { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
        .stat-icon.role-controleur-card { background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); }
        .stat-icon.role-superviseur-card { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }

        .stat-value.role-chef-card { color: #f57c00; }
        .stat-value.role-inspecteur-card { color: #11707f; }
        .stat-value.role-operateur-card { color: #0a58ca; }
        .stat-value.role-controleur-card { color: #b02a37; }
        .stat-value.role-superviseur-card { color: #495057; }

        .delete-inline-form {
            display: inline-flex;
            margin: 0;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            align-items: end;
        }

        .btn-filter,
        .btn-continue {
            border: none;
            border-radius: 8px;
            min-height: 38px;
            padding: 7px 14px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.3s;
        }

        .btn-filter i,
        .btn-continue i {
            font-size: 0.88rem;
        }

        .btn-apply,
        .btn-continue {
            background: #2e7d32;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(46, 125, 50, 0.28);
        }

        .btn-reset {
            background: #6c757d;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.24);
        }

        .btn-apply:hover,
        .btn-continue:hover {
            background: #ffc107;
            color: #333333;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 193, 7, 0.38);
            text-decoration: none;
        }

        .btn-reset:hover {
            background: #5a6268;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(90, 98, 104, 0.28);
            text-decoration: none;
        }

        .membres-section {
            background: #ffffff;
            border-radius: 10px;
            padding: 12px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 5px 16px rgba(0, 0, 0, 0.05);
        }

        .membres-section h4 {
            font-size: 0.92rem;
            font-weight: 700;
            color: #2e7d32;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .table-responsive.fixed-team-table {
            border: none;
            border-radius: 10px;
            overflow-x: auto;
            overflow-y: visible;
            width: 100%;
            background: transparent;
        }

        .membres-table {
            width: 100%;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0 6px;
            font-size: 0.8rem;
            table-layout: fixed !important;
            background: transparent;
        }

        .membres-table thead th {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: #ffffff;
            padding: 8px 10px;
            text-align: left;
            font-weight: 700;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.25px;
            white-space: normal;
            overflow-wrap: anywhere;
            border: none;
            vertical-align: middle;
        }

        .membres-table thead th:first-child {
            border-radius: 10px 0 0 10px;
        }

        .membres-table thead th:last-child {
            border-radius: 0 10px 10px 0;
        }

        .membres-table tbody tr {
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .membres-table tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 125, 50, 0.15);
        }

        .membres-table tbody td {
            background: #ffffff;
            padding: 8px 10px;
            border: none;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            vertical-align: middle;
            font-weight: 500;
        }

        .membres-table tbody td:first-child {
            border-radius: 10px 0 0 10px;
        }

        .membres-table tbody td:last-child {
            border-radius: 0 10px 10px 0;
        }

        .membres-table th:nth-child(1),
        .membres-table td:nth-child(1) { width: 12%; }

        .membres-table th:nth-child(2),
        .membres-table td:nth-child(2) { width: 27%; }

        .membres-table th:nth-child(3),
        .membres-table td:nth-child(3) { width: 13%; }

        .membres-table th:nth-child(4),
        .membres-table td:nth-child(4) { width: 22%; }

        .membres-table th:nth-child(5),
        .membres-table td:nth-child(5) { width: 18%; }

        .membres-table th:nth-child(6),
        .membres-table td:nth-child(6) { width: 8%; }

        .btn-delete {
            background: #ef5350;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-delete:hover {
            background: #c62828;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 15px;
            color: #9e9e9e;
        }

        .empty-state i {
            font-size: 1.5rem;
            margin-bottom: 5px;
            display: block;
        }

        .actions-footer {
            display: flex;
            justify-content: flex-end;
            padding-top: 5px;
        }

        .btn-continue {
            font-family: 'Barlow', sans-serif;
        }

        .badge-count {
            background: rgba(255, 255, 255, 0.25);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-left: 4px;
        }

        .role-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .role-chef { background: rgba(46, 125, 50, 0.12); color: #1b5e20; }
        .role-inspecteur { background: rgba(23, 162, 184, 0.15); color: #0c5460; }
        .role-operateur { background: rgba(13, 110, 253, 0.14); color: #0b5394; }
        .role-controleur { background: rgba(220, 53, 69, 0.12); color: #842029; }
        .role-superviseur { background: rgba(108, 117, 125, 0.15); color: #495057; }
        .role-default { background: rgba(111, 66, 193, 0.12); color: #5a3d99; }

        @media (max-width: 1100px) {
            .stats-container {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .membres-table {
                font-size: 0.78rem;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .equipe-header {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }

            .equipe-header h2 {
                font-size: 1.1rem;
            }

            .btn-continue {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="toast-container" id="toastContainer"></div>

    <div class="equipe-card">
        <div class="equipe-header">
            <h2><i class="fas fa-users"></i> Équipe de contrôle</h2>
            <div class="user-badge"><i class="fas fa-user-circle"></i>
                <?= htmlspecialchars($_SESSION['user_nom'] ?? 'Utilisateur') ?></div>
        </div>

        <div class="equipe-body">
            <?php if ($is_setup): ?>
                <div class="info-line"><i class="fas fa-info-circle"></i> Enregistrez les membres de votre équipe de
                    contrôle avant de continuer.</div>
            <?php else: ?>
                <div class="info-line"><i class="fas fa-info-circle"></i> Liste des membres de l'équipe de contrôle (lecture seule).</div>
            <?php endif; ?>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon total-effectifs"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <div class="stat-label">Membres enregistrés</div>
                        <div class="stat-value total-effectifs"><?= number_format($total_membres, 0, ',', ' ') ?></div>
                    </div>
                </div>
                <?php foreach ($role_stat_cards as $role_card): ?>
                    <div class="stat-card">
                        <div class="stat-icon <?= htmlspecialchars($role_card['variant']) ?>"><i class="<?= htmlspecialchars($role_card['icon']) ?>"></i></div>
                        <div class="stat-info">
                            <div class="stat-label"><?= htmlspecialchars($role_card['label']) ?></div>
                            <div class="stat-value <?= htmlspecialchars($role_card['variant']) ?>"><?= number_format((int) $role_card['count'], 0, ',', ' ') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($is_setup): ?>
                <!-- Formulaire d'ajout (uniquement en mode setup) -->
                <div class="form-section">
                    <h4><i class="fas fa-user-plus"></i> Ajouter un membre</h4>
                    <form method="post">
                        <div class="form-row form-row-member">
                            <div class="form-group">
                                <label for="matricule">Matricule</label>
                                <input type="text" id="matricule" name="matricule" placeholder="Ex: 123456" required>
                            </div>
                            <div class="form-group">
                                <label for="noms">Noms complets</label>
                                <input type="text" id="noms" name="noms" placeholder="Ex: KABONGO MUTOMBO" required>
                            </div>
                            <div class="form-group">
                                <label for="grade">Grade</label>
                                <input type="text" id="grade" name="grade" placeholder="Ex: Colonel" required>
                            </div>
                            <div class="form-group">
                                <label for="unites">Unité</label>
                                <input type="text" id="unites" name="unites" placeholder="Ex: EMG / 1ère Région" required>
                            </div>
                            <div class="form-group">
                                <label for="role">Rôle</label>
                                <select id="role" name="role" required>
                                    <option value="">-- Choisir --</option>
                                    <?php foreach ($roles_equipe as $role_option): ?>
                                        <option value="<?= htmlspecialchars($role_option) ?>"><?= htmlspecialchars($role_option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group form-group-action">
                                <label for="submit_member">Action</label>
                                <button type="submit" id="submit_member" class="btn-add"><i class="fas fa-plus"></i> Enregistrer</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!$is_setup): ?>
                <div class="membres-section">
                    <h4><i class="fas fa-filter"></i> Filtres du tableau équipe <span class="badge-count team-visible-count"><?= $initial_team_count ?></span></h4>
                    <form method="get" class="filters-grid" id="teamFilterForm">
                        <div class="form-group">
                            <label for="role_filter">Rôle</label>
                            <select id="role_filter" name="role">
                                <option value="">Tous les rôles</option>
                                <?php foreach ($roles_equipe as $role_option): ?>
                                    <option value="<?= htmlspecialchars($role_option) ?>" <?= $selected_role === $role_option ? 'selected' : '' ?>><?= htmlspecialchars($role_option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="source_filter">Source locale</label>
                            <input type="text" id="source_filter" value="<?= htmlspecialchars($local_source_label) ?>" readonly>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Liste des membres -->
            <div class="membres-section">
                <h4><i class="fas fa-list"></i> Membres enregistrés <span
                        class="badge-count team-visible-count"><?= $initial_team_count ?></span></h4>

                <?php if (empty($display_membres)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>Aucun membre enregistré</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive fixed-team-table">
                        <table class="membres-table table-militaires" id="team-members-table">
                            <thead>
                                <tr>
                                    <th>Matricule</th>
                                    <th>Noms</th>
                                    <th>Grade</th>
                                    <th>Unité</th>
                                    <th>Rôle</th>
                                    <?php if ($is_setup): ?><th>Action</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($display_membres as $m): ?>
                                    <tr data-role="<?= htmlspecialchars(local_equipe_role_display_label($m['role'] ?? '')) ?>">
                                        <td><?= htmlspecialchars($m['matricule'] ?? 'Non renseigné') ?></td>
                                        <td><?= htmlspecialchars($m['noms']) ?></td>
                                        <td><?= htmlspecialchars($m['grade']) ?></td>
                                        <td><?= htmlspecialchars($m['unites'] ?? 'Non renseigné') ?></td>
                                        <td><span class="<?= htmlspecialchars(local_equipe_role_badge_class($m['role'] ?? '')) ?>"><?= htmlspecialchars(local_equipe_role_display_label($m['role'] ?? '')) ?></span></td>
                                        <?php if ($is_setup): ?>
                                            <td>
                                                <form method="post" class="delete-inline-form" onsubmit="return confirm('Supprimer ce membre ?')">
                                                    <input type="hidden" name="team_action" value="delete">
                                                    <input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
                                                    <button type="submit" class="btn-delete"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="empty-state" id="teamNoResults" style="display:none;">
                        <i class="fas fa-filter"></i>
                        <p>Aucun membre ne correspond au filtre sélectionné.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bouton footer -->
            <div class="actions-footer">
                <?php if ($is_setup): ?>
                    <a href="<?= htmlspecialchars(app_url('modules/equipes/index.php?continue=1')) ?>" class="btn-continue"><i class="fas fa-arrow-right"></i> Continuer</a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(app_url('index.php')) ?>" class="btn-continue"><i class="fas fa-arrow-left"></i> Retour</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../../assets/js/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        (function() {
            function showToast(message, type = 'success') {
                const toastContainer = document.getElementById('toastContainer');
                if (!toastContainer || !message) {
                    return;
                }

                const iconMap = {
                    success: 'fa-check-circle',
                    error: 'fa-exclamation-circle',
                    warning: 'fa-info-circle'
                };

                const toast = document.createElement('div');
                toast.className = `toast-message ${type}`;
                toast.innerHTML = `
                    <i class="fas ${iconMap[type] || iconMap.success}"></i>
                    <span>${message}</span>
                `;

                toastContainer.appendChild(toast);

                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(20px)';
                    setTimeout(() => toast.remove(), 250);
                }, 5000);
            }

            function escapeRegex(value) {
                return String(value ?? '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            function highlightSearchTerm(data, searchTerm) {
                if (!searchTerm || !data) return data;
                const searchRegex = new RegExp('(' + escapeRegex(searchTerm) + ')', 'gi');
                if (typeof data === 'string') {
                    if (data.indexOf('<') !== -1 && data.indexOf('>') !== -1) {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = data;
                        const textContent = tempDiv.textContent || tempDiv.innerText || '';
                        if (textContent.match(searchRegex)) {
                            return data.replace(new RegExp(escapeRegex(textContent), 'g'),
                                textContent.replace(searchRegex, '<mark>$1</mark>'));
                        }
                        return data;
                    }
                    return data.replace(searchRegex, '<mark>$1</mark>');
                }
                return data;
            }

            document.addEventListener('DOMContentLoaded', function() {
                <?php if ($toast_message): ?>
                showToast(<?= json_encode($toast_message, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($toast_type, JSON_UNESCAPED_UNICODE) ?>);
                <?php endif; ?>

                const teamFilterForm = document.getElementById('teamFilterForm');
                const roleFilter = document.getElementById('role_filter');
                const countBadges = document.querySelectorAll('.team-visible-count');
                const noResultsState = document.getElementById('teamNoResults');
                const tableElement = document.getElementById('team-members-table');
                const headerCells = Array.from(document.querySelectorAll('#team-members-table thead th'));
                const roleColumnIndex = headerCells.findIndex((cell) => {
                    const label = (cell.textContent || '').trim().toLowerCase();
                    return label === 'rôle' || label === 'role';
                });
                const actionColumnIndex = headerCells.findIndex((cell) => (cell.textContent || '').trim().toLowerCase() === 'action');

                const updateVisibleCount = (count) => {
                    countBadges.forEach((badge) => {
                        badge.textContent = new Intl.NumberFormat('fr-FR').format(count);
                    });

                    if (noResultsState) {
                        noResultsState.style.display = count === 0 ? 'block' : 'none';
                    }
                };

                if (window.jQuery && $.fn.DataTable && tableElement) {
                    let searchTerm = '';

                    const teamTable = $('#team-members-table').DataTable({
                        language: {
                            search: '<i class="fas fa-search"></i>',
                            lengthMenu: 'Afficher _MENU_ éléments',
                            info: 'Affichage de _START_ à _END_ sur _TOTAL_ éléments',
                            infoEmpty: 'Affichage de 0 à 0 sur 0 élément',
                            infoFiltered: '(filtré sur _MAX_ éléments au total)',
                            zeroRecords: 'Aucun enregistrement correspondant',
                            paginate: {
                                first: 'Premier',
                                previous: 'Précédent',
                                next: 'Suivant',
                                last: 'Dernier'
                            }
                        },
                        dom: 'rt<"datatable-bottom d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3"ip>',
                        pageLength: 10,
                        lengthMenu: [10, 25, 50, 100],
                        autoWidth: false,
                        responsive: false,
                        scrollX: false,
                        order: [],
                        columnDefs: actionColumnIndex >= 0 ? [{
                            orderable: false,
                            targets: [actionColumnIndex]
                        }] : [],
                        createdRow: function(row) {
                            if (!searchTerm) {
                                return;
                            }

                            $(row).find('td').each(function() {
                                const $td = $(this);
                                if ($td.index() === actionColumnIndex) {
                                    return;
                                }
                                $td.html(highlightSearchTerm($td.html(), searchTerm));
                            });
                        },
                        initComplete: function() {
                            this.api().columns.adjust();
                            const filterDiv = $('#team-members-table_filter');
                            filterDiv.css({
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'flex-end',
                                gap: '10px',
                                flexWrap: 'wrap'
                            });

                            if (!filterDiv.children('.search-icon').length) {
                                filterDiv.prepend('<i class="fas fa-search search-icon" style="color: #2e7d32; font-size: 1rem;"></i>');
                            }

                            const searchLabel = filterDiv.find('label');
                            searchLabel.css({
                                display: 'flex',
                                alignItems: 'center',
                                marginBottom: '0',
                                flex: '0 1 auto'
                            });

                            searchLabel.find('i').remove();
                            searchLabel.contents().filter(function() {
                                return this.nodeType === 3;
                            }).remove();
                            searchLabel.find('input').attr('placeholder', 'Rechercher dans le tableau...');
                        }
                    });

                    const applyHighlight = () => {
                        if (!searchTerm) {
                            teamTable.rows().invalidate().draw(false);
                            return;
                        }

                        $('#team-members-table tbody tr').each(function() {
                            $(this).find('td').each(function() {
                                const $td = $(this);
                                if ($td.index() === actionColumnIndex) {
                                    return;
                                }

                                const originalHtml = $td.html();
                                if (!originalHtml || originalHtml.includes('<mark')) {
                                    return;
                                }

                                $td.html(highlightSearchTerm(originalHtml, searchTerm));
                            });
                        });
                    };

                    $('#team-members-table_filter input').on('keyup search input', function() {
                        searchTerm = $(this).val();
                        setTimeout(applyHighlight, 80);
                    });

                    const applyRealtimeFilters = () => {
                        const selectedRole = roleFilter ? roleFilter.value.trim() : '';

                        if (roleColumnIndex >= 0) {
                            teamTable.column(roleColumnIndex).search(selectedRole ? '^' + escapeRegex(selectedRole) + '$' : '', true, false);
                        }

                        teamTable.draw();
                        updateVisibleCount(teamTable.rows({ filter: 'applied' }).count());
                    };

                    teamTable.on('draw', function() {
                        updateVisibleCount(teamTable.rows({ filter: 'applied' }).count());
                        if (searchTerm) {
                            window.requestAnimationFrame(applyHighlight);
                        }
                    });

                    if (teamFilterForm) {
                        teamFilterForm.addEventListener('submit', function(event) {
                            event.preventDefault();
                            applyRealtimeFilters();
                        });
                    }

                    if (roleFilter) {
                        roleFilter.addEventListener('change', applyRealtimeFilters);
                    }

                    applyRealtimeFilters();
                    return;
                }

                const tableRows = Array.from(document.querySelectorAll('#team-members-table tbody tr'));
                const applyRealtimeFiltersFallback = () => {
                    if (!roleFilter || tableRows.length === 0) {
                        return;
                    }

                    const selectedRole = roleFilter.value.trim();
                    let visibleCount = 0;

                    tableRows.forEach((row) => {
                        const rowRole = row.dataset.role || '';
                        const matchesRole = selectedRole === '' || rowRole === selectedRole;

                        row.style.display = matchesRole ? '' : 'none';
                        if (matchesRole) {
                            visibleCount++;
                        }
                    });

                    updateVisibleCount(visibleCount);
                };

                if (teamFilterForm) {
                    teamFilterForm.addEventListener('submit', function(event) {
                        event.preventDefault();
                        applyRealtimeFiltersFallback();
                    });
                }

                if (roleFilter) {
                    roleFilter.addEventListener('change', applyRealtimeFiltersFallback);
                }

                applyRealtimeFiltersFallback();
            });
        })();
    </script>
</body>

</html>