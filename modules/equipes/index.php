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
            "chef d'équipe", "chef d'equipe" => "CHEF D'ÉQUIPE",
            'inspecteur' => 'INSPECTEUR',
            'opérateur pc', 'operateur pc', 'opérateur', 'operateur' => 'OPÉRATEUR PC',
            'contrôleur', 'controleur', 'contôleur' => 'CONTRÔLEUR',
            'superviseur' => 'SUPERVISEUR',
            default => strtoupper(trim((string) $role) !== '' ? trim((string) $role) : 'NON DÉFINI'),
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

// Fonction pour mettre en majuscules les données
if (!function_exists('format_upper')) {
    function format_upper($value, $default = 'NON RENSEIGNÉ') {
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
                    ? 'Membre <strong>' . htmlspecialchars(strtoupper($deletedMemberName), ENT_QUOTES, 'UTF-8') . '</strong> supprimé avec succès.'
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
    // Convertir le label en majuscules pour la comparaison
    $roleLabelUpper = strtoupper($roleLabel);
    foreach ($role_counts as $key => $value) {
        if (strtoupper($key) === $roleLabelUpper) {
            $role_counts[$key]++;
            break;
        }
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
    return $selected_role === '' || strtoupper($roleLabel) === strtoupper($selected_role);
}));
$initial_team_count = count($filtered_membres);
$display_membres = $filtered_membres;
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
            background: linear-gradient(135deg, #1a472a 0%, #0d2818 100%);
            padding: 20px;
        }

        .equipe-card {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .equipe-header {
            background: linear-gradient(135deg, #1e7e34 0%, #145c24 100%);
            padding: 20px 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .equipe-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .equipe-body {
            padding: 24px;
        }

        .info-line {
            background: #e8f5e9;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #1e7e34;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #1e7e34;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(30, 126, 52, 0.15);
            border-color: #1e7e34;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .stat-icon.total-effectifs { background: linear-gradient(135deg, #1e7e34, #145c24); }
        .stat-icon.role-chef-card { background: linear-gradient(135deg, #ff9800, #f57c00); }
        .stat-icon.role-inspecteur-card { background: linear-gradient(135deg, #17a2b8, #138496); }
        .stat-icon.role-operateur-card { background: linear-gradient(135deg, #007bff, #0056b3); }
        .stat-icon.role-controleur-card { background: linear-gradient(135deg, #dc3545, #c82333); }
        .stat-icon.role-superviseur-card { background: linear-gradient(135deg, #6c757d, #5a6268); }

        .stat-info {
            flex: 1;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
        }

        .stat-value.total-effectifs { color: #1e7e34; }
        .stat-value.role-chef-card { color: #f57c00; }
        .stat-value.role-inspecteur-card { color: #138496; }
        .stat-value.role-operateur-card { color: #0056b3; }
        .stat-value.role-controleur-card { color: #c82333; }
        .stat-value.role-superviseur-card { color: #5a6268; }

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
            color: #1e7e34;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-row-member {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select {
            padding: 8px 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 0.85rem;
            font-family: 'Barlow', sans-serif;
            transition: all 0.2s;
            text-transform: uppercase;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1e7e34;
            box-shadow: 0 0 0 3px rgba(30, 126, 52, 0.1);
        }

        .btn-add {
            background: linear-gradient(135deg, #1e7e34, #145c24);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            height: 38px;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 126, 52, 0.3);
        }

        .membres-section {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            margin-bottom: 25px;
        }

        .section-header {
            padding: 16px 20px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e7e34;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filters-grid {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #495057;
            text-transform: uppercase;
        }

        .filter-group select,
        .filter-group input {
            padding: 6px 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 0.85rem;
            font-family: 'Barlow', sans-serif;
            background: white;
        }

        .table-responsive {
            padding: 0 20px 20px 20px;
            overflow-x: auto;
        }

        .membres-table {
            width: 100%;
            border-collapse: collapse;
        }

        .membres-table thead th {
            background: #f8f9fa;
            color: #495057;
            padding: 12px;
            text-align: left;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
        }

        .membres-table tbody td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            text-transform: uppercase;
            font-weight: 500;
        }

        .membres-table tbody tr:hover {
            background: #f8f9fa;
        }

        .role-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .role-chef { background: #fff3e0; color: #f57c00; }
        .role-inspecteur { background: #d1ecf1; color: #0c5460; }
        .role-operateur { background: #cce5ff; color: #004085; }
        .role-controleur { background: #f8d7da; color: #721c24; }
        .role-superviseur { background: #e2e3e5; color: #383d41; }
        .role-default { background: #e9ecef; color: #495057; }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: background 0.2s;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .actions-footer {
            display: flex;
            justify-content: flex-end;
            padding-top: 10px;
        }

        .btn-continue {
            background: linear-gradient(135deg, #1e7e34, #145c24);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
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
            box-shadow: 0 4px 12px rgba(30, 126, 52, 0.3);
            color: white;
            text-decoration: none;
        }

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

        .badge-count {
            background: #1e7e34;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            margin-left: 8px;
        }

        .alert-error,
        .alert-success {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
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

        @media (max-width: 768px) {
            .equipe-body {
                padding: 16px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .form-row-member {
                grid-template-columns: 1fr;
            }
            
            .equipe-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="toast-container" id="toastContainer"></div>

    <div class="equipe-card">
        <div class="equipe-header">
            <h2><i class="fas fa-users"></i> Équipe de contrôle</h2>
            <div class="user-badge">
                <i class="fas fa-user-circle"></i>
                <?= htmlspecialchars(strtoupper($_SESSION['user_nom'] ?? 'UTILISATEUR')) ?>
            </div>
        </div>

        <div class="equipe-body">
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

            <!-- Statistiques -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon total-effectifs"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <div class="stat-label">MEMBRES ENREGISTRÉS</div>
                        <div class="stat-value total-effectifs"><?= number_format($total_membres, 0, ',', ' ') ?></div>
                    </div>
                </div>
                <?php foreach ($role_stat_cards as $role_card): ?>
                    <div class="stat-card">
                        <div class="stat-icon <?= htmlspecialchars($role_card['variant']) ?>">
                            <i class="<?= htmlspecialchars($role_card['icon']) ?>"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label"><?= strtoupper(htmlspecialchars($role_card['label'])) ?></div>
                            <div class="stat-value <?= htmlspecialchars($role_card['variant']) ?>">
                                <?= number_format((int) $role_card['count'], 0, ',', ' ') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout (uniquement en mode setup) -->
            <?php if ($is_setup): ?>
                <div class="form-section">
                    <h4><i class="fas fa-user-plus"></i> AJOUTER UN MEMBRE</h4>
                    <form method="post">
                        <div class="form-row-member">
                            <div class="form-group">
                                <label for="matricule">MATRICULE</label>
                                <input type="text" id="matricule" name="matricule" placeholder="EX: 123456" required>
                            </div>
                            <div class="form-group">
                                <label for="noms">NOMS COMPLETS</label>
                                <input type="text" id="noms" name="noms" placeholder="EX: KABONGO MUTOMBO" required>
                            </div>
                            <div class="form-group">
                                <label for="grade">GRADE</label>
                                <input type="text" id="grade" name="grade" placeholder="EX: COLONEL" required>
                            </div>
                            <div class="form-group">
                                <label for="unites">UNITÉ</label>
                                <input type="text" id="unites" name="unites" placeholder="EX: EMG / 1ÈRE RÉGION" required>
                            </div>
                            <div class="form-group">
                                <label for="role">RÔLE</label>
                                <select id="role" name="role" required>
                                    <option value="">-- CHOISIR --</option>
                                    <?php foreach ($roles_equipe as $role_option): ?>
                                        <option value="<?= htmlspecialchars($role_option) ?>"><?= strtoupper(htmlspecialchars($role_option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn-add">
                                    <i class="fas fa-plus"></i> ENREGISTRER
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Liste des membres -->
            <div class="membres-section">
                <div class="section-header">
                    <h4><i class="fas fa-list"></i> MEMBRES ENREGISTRÉS</h4>
                    <?php if (!$is_setup): ?>
                    <form method="get" class="filters-grid" id="teamFilterForm">
                        <div class="filter-group">
                            <label for="role_filter"><i class="fas fa-user-tag"></i> FILTRER PAR RÔLE</label>
                            <select id="role_filter" name="role">
                                <option value="">TOUS LES RÔLES</option>
                                <?php foreach ($roles_equipe as $role_option): ?>
                                    <option value="<?= htmlspecialchars($role_option) ?>" <?= $selected_role === $role_option ? 'selected' : '' ?>>
                                        <?= strtoupper(htmlspecialchars($role_option)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-map-pin"></i> SOURCE LOCALE</label>
                            <input type="text" value="<?= htmlspecialchars(strtoupper($local_source_label)) ?>" readonly disabled>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($display_membres)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>AUCUN MEMBRE ENREGISTRÉ</p>
                        <?php if ($is_setup): ?>
                            <small>Utilisez le formulaire ci-dessus pour ajouter des membres.</small>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="membres-table" id="team-members-table">
                            <thead>
                                <tr>
                                    <th>MATRICULE</th>
                                    <th>NOMS</th>
                                    <th>GRADE</th>
                                    <th>UNITÉ</th>
                                    <th>RÔLE</th>
                                    <?php if ($is_setup): ?><th style="width: 80px">ACTION</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($display_membres as $m): ?>
                                    <tr data-role="<?= htmlspecialchars(local_equipe_role_display_label($m['role'] ?? '')) ?>">
                                        <td><?= format_upper($m['matricule'] ?? '') ?></td>
                                        <td><?= format_upper($m['noms']) ?></td>
                                        <td><?= format_upper($m['grade']) ?></td>
                                        <td><?= format_upper($m['unites'] ?? '') ?></td>
                                        <td><span class="role-pill <?= htmlspecialchars(local_equipe_role_badge_class($m['role'] ?? '')) ?>">
                                            <?= htmlspecialchars(local_equipe_role_display_label($m['role'] ?? '')) ?>
                                        </span></td>
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
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="actions-footer">
                <?php if ($is_setup): ?>
                    <a href="<?= htmlspecialchars(app_url('modules/equipes/index.php?continue=1')) ?>" class="btn-continue">
                        <i class="fas fa-arrow-right"></i> CONTINUER
                    </a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(app_url('index.php')) ?>" class="btn-continue">
                        <i class="fas fa-arrow-left"></i> RETOUR
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        (function() {
            // Toast notification
            <?php if ($toast_message): ?>
            const toastContainer = document.getElementById('toastContainer');
            if (toastContainer) {
                const toast = document.createElement('div');
                toast.className = 'toast-message <?= $toast_type === 'error' ? 'error' : '' ?>';
                toast.innerHTML = `
                    <i class="fas <?= $toast_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                    <span><?= htmlspecialchars(strtoupper($toast_message), ENT_QUOTES) ?></span>
                `;
                toastContainer.appendChild(toast);
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 250);
                }, 5000);
            }
            <?php endif; ?>

            // Filtrage par rôle
            const roleFilter = document.getElementById('role_filter');
            if (roleFilter) {
                roleFilter.addEventListener('change', function() {
                    const selectedRole = this.value;
                    const currentUrl = new URL(window.location.href);
                    if (selectedRole) {
                        currentUrl.searchParams.set('role', selectedRole);
                    } else {
                        currentUrl.searchParams.delete('role');
                    }
                    window.location.href = currentUrl.toString();
                });
            }
        })();
    </script>
</body>

</html>