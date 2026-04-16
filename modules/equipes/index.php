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

if (!function_exists('local_equipe_role_order_value')) {
    function local_equipe_role_order_value(?string $role): int {
        $normalized = strtolower(trim((string) $role));
        return match ($normalized) {
            "chef d'équipe", "chef d'equipe" => 1,
            'inspecteur' => 2,
            'opérateur pc', 'operateur pc', 'opérateur', 'operateur' => 3,
            'contrôleur', 'controleur', 'contôleur' => 4,
            'superviseur' => 5,
            default => 99,
        };
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
            "chef d'équipe", "chef d'equipe" => 'role-chef',
            'inspecteur' => 'role-inspecteur',
            'opérateur pc', 'operateur pc', 'opérateur', 'operateur' => 'role-operateur',
            'contrôleur', 'controleur', 'contôleur' => 'role-controleur',
            'superviseur' => 'role-superviseur',
            default => 'role-default',
        };
    }
}

// Fonction pour mettre en majuscules UNIQUEMENT les données du tableau
if (!function_exists('format_upper_table')) {
    function format_upper_table($value, $default = 'NON RENSEIGNÉ') {
        $value = trim((string) $value);
        return empty($value) ? $default : strtoupper($value);
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
            $matricule = strtoupper(trim($_POST['matricule'] ?? ''));
            $noms = strtoupper(trim($_POST['noms'] ?? ''));
            $grade = strtoupper(trim($_POST['grade'] ?? ''));
            $unites = strtoupper(trim($_POST['unites'] ?? ''));
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

// Récupérer les membres avec ordre personnalisé
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

$selected_role = trim((string) ($_GET['role'] ?? ''));
$filtered_membres = array_values(array_filter($membres, static function (array $membre) use ($selected_role): bool {
    $roleLabel = local_equipe_role_display_label($membre['role'] ?? '');
    return $selected_role === '' || $roleLabel === $selected_role;
}));
$display_membres = $filtered_membres;

// Récupérer la liste des unités pour le filtre
$stmt_unites = $pdo->query("SELECT DISTINCT unites FROM equipes WHERE unites IS NOT NULL AND unites != '' ORDER BY unites");
$unites_list = $stmt_unites->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTR.NET FARDC - Équipe de contrôle</title>
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
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
            background: linear-gradient(135deg, #1a472a 0%, #0d2818 100%);
            padding: 20px;
        }

        .modern-card {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .modern-card .card-header {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            padding: 15px 25px;
            border: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .modern-card .card-header h3 {
            color: white;
            margin: 0;
            font-weight: 600;
            font-size: 1.3rem;
        }

        .modern-card .card-header h3 i {
            margin-right: 8px;
        }

        .modern-card .card-body {
            padding: 25px;
        }

        .total-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .filters-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }

        .filter-item {
            flex: 1;
            min-width: 150px;
        }

        .filter-item .form-label {
            font-weight: 600;
            font-size: 0.75rem;
            color: #2e7d32;
            margin-bottom: 5px;
            display: block;
        }

        .filter-item .form-label i {
            margin-right: 4px;
        }

        .filter-item .form-select,
        .filter-item .form-control {
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            padding: 6px 10px;
            font-size: 0.85rem;
            width: 100%;
        }

        .btn-reset-modern {
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            background: #6c757d;
            color: white;
            font-weight: 500;
        }

        .btn-reset-modern:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-add {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            height: 34px;
            white-space: nowrap;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.7rem;
            transition: background 0.2s;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .form-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
        }

        .form-section h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #2e7d32;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-row-member {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
            min-width: 140px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-group label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select {
            padding: 6px 10px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: 'Barlow', sans-serif;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }

        .form-group:last-child {
            flex: 0 0 auto;
            min-width: auto;
        }

        .table-equipes {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
            min-width: 800px;
        }

        .table-equipes thead th {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: white;
            font-weight: 700;
            font-size: 0.85rem;
            padding: 10px 12px;
            border: none;
            text-align: left;
            vertical-align: middle;
        }

        .table-equipes thead th:first-child {
            border-radius: 10px 0 0 10px;
        }

        .table-equipes thead th:last-child {
            border-radius: 0 10px 10px 0;
        }

        .dataTables_wrapper table.dataTable thead tr:not(:first-child) {
            visibility: collapse !important;
            height: 0 !important;
        }

        .table-equipes tbody tr {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            transition: all 0.3s;
        }

        .table-equipes tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 125, 50, 0.15);
        }

        .table-equipes tbody td {
            padding: 10px 12px;
            border: none;
            font-size: 0.85rem;
            vertical-align: middle;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Style pour les données en majuscules DANS LE TABLEAU UNIQUEMENT */
        .table-equipes tbody td {
            text-transform: uppercase;
        }

        .table-equipes tbody td:first-child {
            border-radius: 10px 0 0 10px;
        }

        .table-equipes tbody td:last-child {
            border-radius: 0 10px 10px 10px;
        }

        .matricule-with-eye {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .matricule-with-eye i {
            color: #2e7d32;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .matricule-with-eye i:hover {
            color: #ffc107;
            transform: scale(1.1);
        }

        /* Styles des cartes statistiques - 3 colonnes */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Suppression de l'animation de survol : cartes fixes */
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
        }

        .stat-icon.total-card { background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); }
        .stat-icon.role-chef-card { background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); }
        .stat-icon.role-inspecteur-card { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        .stat-icon.role-operateur-card { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
        .stat-icon.role-controleur-card { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
        .stat-icon.role-superviseur-card { background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); }

        .stat-info {
            flex: 1;
        }

        .stat-info h4 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #2e7d32;
        }

        .stat-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .stat-info h4.total-value { color: #2e7d32; }
        .stat-info h4.role-chef-value { color: #f57c00; }
        .stat-info h4.role-inspecteur-value { color: #138496; }
        .stat-info h4.role-operateur-value { color: #0a58ca; }
        .stat-info h4.role-controleur-value { color: #c82333; }
        .stat-info h4.role-superviseur-value { color: #5a6268; }

        .role-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .role-chef { background: #fff3e0; color: #f57c00; }
        .role-inspecteur { background: #d1ecf1; color: #0c5460; }
        .role-operateur { background: #cce5ff; color: #004085; }
        .role-controleur { background: #f8d7da; color: #721c24; }
        .role-superviseur { background: #e2e3e5; color: #383d41; }
        .role-default { background: #e9ecef; color: #495057; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .actions-footer {
            display: flex;
            justify-content: flex-end;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .btn-continue {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
            color: white;
            text-decoration: none;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast-message {
            min-width: 300px;
            background: #28a745;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.3s ease;
        }

        .toast-message.error {
            background: #dc3545;
        }

        .toast-message strong {
            color: #ffd700;
            font-weight: 700;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .info-line {
            background: #e8f5e9;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            color: #1e7e34;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #1e7e34;
        }

        @media (max-width: 992px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .modern-card .card-body {
                padding: 16px;
            }
            
            .form-row-member {
                flex-direction: column;
            }
            
            .form-group:last-child {
                flex: 1;
            }
            
            .modern-card .card-header {
                flex-direction: column;
                text-align: center;
            }

            .filters-row {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="toast-container" id="toastContainer"></div>

    <div class="modern-card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> Équipe de contrôle</h3>
            <span class="total-badge"><i class="fas fa-database"></i> Total : <?= count($display_membres) ?></span>
        </div>

        <div class="card-body">
            <?php if ($is_setup): ?>
                <div class="info-line">
                    <i class="fas fa-info-circle"></i>
                    Enregistrez les membres de votre équipe de contrôle avant de continuer.
                </div>
            <?php else: ?>
                <div class="info-line">
                    <i class="fas fa-info-circle"></i>
                    Liste des membres de l'équipe de contrôle (lecture seule).
                </div>
            <?php endif; ?>

            <!-- Statistiques - 3 colonnes -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon total-card"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h4 class="total-value"><?= number_format($total_membres, 0, ',', ' ') ?></h4>
                        <p>Total membres</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon role-chef-card"><i class="fas fa-user-tie"></i></div>
                    <div class="stat-info">
                        <h4 class="role-chef-value"><?= number_format($role_counts["Chef d'équipe"] ?? 0, 0, ',', ' ') ?></h4>
                        <p>Chef d'équipe</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon role-inspecteur-card"><i class="fas fa-user-secret"></i></div>
                    <div class="stat-info">
                        <h4 class="role-inspecteur-value"><?= number_format($role_counts['Inspecteur'] ?? 0, 0, ',', ' ') ?></h4>
                        <p>Inspecteur</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon role-operateur-card"><i class="fas fa-desktop"></i></div>
                    <div class="stat-info">
                        <h4 class="role-operateur-value"><?= number_format($role_counts['Opérateur PC'] ?? 0, 0, ',', ' ') ?></h4>
                        <p>Opérateur PC</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon role-controleur-card"><i class="fas fa-clipboard-check"></i></div>
                    <div class="stat-info">
                        <h4 class="role-controleur-value"><?= number_format($role_counts['Contrôleur'] ?? 0, 0, ',', ' ') ?></h4>
                        <p>Contrôleur</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon role-superviseur-card"><i class="fas fa-user-shield"></i></div>
                    <div class="stat-info">
                        <h4 class="role-superviseur-value"><?= number_format($role_counts['Superviseur'] ?? 0, 0, ',', ' ') ?></h4>
                        <p>Superviseur</p>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert-error" style="background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout (uniquement en mode setup) -->
            <?php if ($is_setup): ?>
                <div class="form-section">
                    <h4><i class="fas fa-user-plus"></i> Ajouter un membre</h4>
                    <form method="post">
                        <div class="form-row-member">
                            <div class="form-group">
                                <label for="matricule">MATRICULE</label>
                                <input type="text" id="matricule" name="matricule" placeholder="123456" required>
                            </div>
                            <div class="form-group">
                                <label for="noms">NOMS</label>
                                <input type="text" id="noms" name="noms" placeholder="KABONGO MUTOMBO" required>
                            </div>
                            <div class="form-group">
                                <label for="grade">GRADE</label>
                                <input type="text" id="grade" name="grade" placeholder="COLONEL" required>
                            </div>
                            <div class="form-group">
                                <label for="unites">UNITÉ</label>
                                <input type="text" id="unites" name="unites" placeholder="EMG / 1ÈRE RÉGION" required>
                            </div>
                            <div class="form-group">
                                <label for="role">RÔLE</label>
                                <select id="role" name="role" required>
                                    <option value="">-- CHOISIR --</option>
                                    <?php foreach ($roles_equipe as $role_option): ?>
                                        <option value="<?= htmlspecialchars($role_option) ?>"><?= htmlspecialchars($role_option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn-add">
                                    <i class="fas fa-plus"></i> Enregistrer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Filtres -->
            <div class="filters-row">
                <div class="filter-item">
                    <label class="form-label"><i class="fas fa-user-tag"></i> Rôle</label>
                    <select id="role-filter" class="form-select">
                        <option value="">Tous</option>
                        <?php foreach ($roles_equipe as $role): ?>
                        <option value="<?= htmlspecialchars($role) ?>" <?= $selected_role === $role ? 'selected' : '' ?>><?= htmlspecialchars($role) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label class="form-label"><i class="fas fa-building"></i> Unités</label>
                    <select id="unites-filter" class="form-select">
                        <option value="">Toutes</option>
                        <?php foreach ($unites_list as $unite): ?>
                        <option value="<?= htmlspecialchars($unite) ?>"><?= htmlspecialchars($unite) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label class="form-label"><i class="fas fa-fist-raised"></i> Grade</label>
                    <select id="grade-filter" class="form-select">
                        <option value="">Tous</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" id="reset-filters" class="btn-reset-modern w-100"><i class="fas fa-undo-alt"></i> Réinitialiser</button>
                </div>
            </div>

            <!-- Liste des membres -->
            <?php if (empty($display_membres)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>Aucun membre enregistré</p>
                    <?php if ($is_setup): ?>
                        <small>Utilisez le formulaire ci-dessus pour ajouter des membres.</small>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table id="table-equipes" class="table-equipes" style="width:100%">
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Noms</th>
                            <th>Grade</th>
                            <th>Unités</th>
                            <th>Rôle</th>
                            <?php if ($is_setup): ?><th style="width: 70px">Action</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($display_membres as $m): ?>
                        <tr data-role="<?= htmlspecialchars(local_equipe_role_display_label($m['role'] ?? '')) ?>"
                            data-role-order="<?= local_equipe_role_order_value($m['role'] ?? '') ?>"
                            data-unites="<?= htmlspecialchars($m['unites'] ?? '') ?>"
                            data-grade="<?= htmlspecialchars($m['grade'] ?? '') ?>">
                            <td>
                                <?php if (!$is_setup): ?>
                                <div class="matricule-with-eye">
                                    <i class="fas fa-eye" onclick="window.location.href='voir.php?id=<?= urlencode($m['id']) ?>'"></i>
                                    <strong><?= htmlspecialchars(format_upper_table($m['matricule'] ?? '')) ?></strong>
                                </div>
                                <?php else: ?>
                                <strong><?= htmlspecialchars(format_upper_table($m['matricule'] ?? '')) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(format_upper_table($m['noms'])) ?></td>
                            <td><?= htmlspecialchars(format_upper_table($m['grade'])) ?></td>
                            <td><?= htmlspecialchars(format_upper_table($m['unites'] ?? '')) ?></td>
                            <td>
                                <span class="role-pill <?= htmlspecialchars(local_equipe_role_badge_class($m['role'] ?? '')) ?>">
                                    <?= htmlspecialchars(local_equipe_role_display_label($m['role'] ?? '')) ?>
                                </span>
                            </td>
                            <?php if ($is_setup): ?>
                            <td>
                                <form method="post" onsubmit="return confirm('Supprimer ce membre ?')">
                                    <input type="hidden" name="team_action" value="delete">
                                    <input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
                                    <button type="submit" class="btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Actions -->
            <div class="actions-footer">
                <?php if ($is_setup): ?>
                    <a href="<?= htmlspecialchars(app_url('modules/equipes/index.php?continue=1')) ?>" class="btn-continue">
                        <i class="fas fa-arrow-right"></i> Continuer
                    </a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(app_url('index.php')) ?>" class="btn-continue">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <script>
    $(document).ready(function() {
        // Récupérer les grades uniques pour le filtre
        const grades = [...new Set($('#table-equipes tbody tr').map(function() {
            return $(this).data('grade');
        }).get())].filter(g => g).sort();

        grades.forEach(grade => {
            $('#grade-filter').append(`<option value="${grade}">${grade}</option>`);
        });

        function cleanupDuplicateHeaderRows() {
            const $wrapper = $('#table-equipes').closest('.dataTables_wrapper');
            if ($wrapper.length) {
                $wrapper.find('table.dataTable thead tr').each(function(index) {
                    if (index > 0) {
                        $(this).css({
                            display: 'none',
                            height: 0,
                            visibility: 'collapse'
                        });
                    }
                });
            }
        }

        // Initialiser DataTable
        const table = $('#table-equipes').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json',
                search: '<i class="fas fa-search"></i>',
                lengthMenu: "Afficher _MENU_ éléments",
                info: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
                infoEmpty: "Affichage de 0 à 0 sur 0 élément",
                infoFiltered: "(filtré sur _MAX_ éléments au total)",
                zeroRecords: "Aucun enregistrement correspondant",
                paginate: {
                    first: "Premier",
                    previous: "Précédent",
                    next: "Suivant",
                    last: "Dernier"
                }
            },
            dom: 'rt<"datatable-bottom d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3"ip>',
            order: [[4, 'asc']],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            autoWidth: true,
            orderCellsTop: true,
            paging: true,
            initComplete: function() {
                cleanupDuplicateHeaderRows();
            }
        });

        table.on('draw.dt', function() {
            cleanupDuplicateHeaderRows();
        });

        cleanupDuplicateHeaderRows();

        // Filtres personnalisés
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'table-equipes') return true;

            const row = table.row(dataIndex);
            const rowNode = row.node();
            const $row = $(rowNode);

            const role = $row.data('role') || '';
            const unites = $row.data('unites') || '';
            const grade = $row.data('grade') || '';

            const roleFilter = $('#role-filter').val();
            const unitesFilter = $('#unites-filter').val();
            const gradeFilter = $('#grade-filter').val();

            if (roleFilter && role !== roleFilter) return false;
            if (unitesFilter && unites !== unitesFilter) return false;
            if (gradeFilter && grade !== gradeFilter) return false;

            return true;
        });

        $('#role-filter, #unites-filter, #grade-filter').on('change', function() {
            table.draw();
        });

        $('#reset-filters').on('click', function() {
            $('#role-filter, #unites-filter, #grade-filter').val('');
            table.draw();
        });

        // Toast notification
        <?php if ($toast_message): ?>
        const toastContainer = document.getElementById('toastContainer');
        if (toastContainer) {
            const toast = document.createElement('div');
            toast.className = 'toast-message <?= $toast_type === 'error' ? 'error' : '' ?>';
            toast.innerHTML = `
                <i class="fas <?= $toast_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                <span><?= htmlspecialchars($toast_message, ENT_QUOTES) ?></span>
            `;
            toastContainer.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 250);
            }, 5000);
        }
        <?php endif; ?>
    });
    </script>
</body>
</html>