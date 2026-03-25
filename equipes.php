<?php
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit;
}

// Créer la table equipes si elle n'existe pas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `equipes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `noms` VARCHAR(150) NOT NULL,
        `grade` VARCHAR(50) NOT NULL,
        `role` VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    error_log("Erreur création table equipes: " . $e->getMessage());
}

$error = null;
$success = null;

// Suppression d'un membre
if (isset($_GET['supprimer']) && is_numeric($_GET['supprimer'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM equipes WHERE id = ?");
        $stmt->execute([(int)$_GET['supprimer']]);
        log_action('SUPPRESSION', 'equipes', (int)$_GET['supprimer'], 'Suppression membre équipe');
        $success = "Membre supprimé avec succès.";
    } catch (PDOException $e) {
        error_log("Erreur suppression équipe: " . $e->getMessage());
        $error = "Erreur lors de la suppression.";
    }
}

// Ajout d'un membre
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noms = trim($_POST['noms'] ?? '');
    $grade = trim($_POST['grade'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if (empty($noms) || empty($grade) || empty($role)) {
        $error = "Tous les champs sont obligatoires.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO equipes (noms, grade, role) VALUES (?, ?, ?)");
            $stmt->execute([$noms, $grade, $role]);
            log_action('AJOUT', 'equipes', (int)$pdo->lastInsertId(), "Ajout membre équipe: $noms");
            $success = "Membre ajouté avec succès.";
        } catch (PDOException $e) {
            error_log("Erreur ajout équipe: " . $e->getMessage());
            $error = "Erreur lors de l'ajout.";
        }
    }
}

// Récupérer les membres
try {
    $membres = $pdo->query("SELECT * FROM equipes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $membres = [];
    error_log("Erreur récupération équipes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTR.NET FARDC - Équipe de contrôle</title>
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/adminlte.min.css">
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
            align-items: center;
            padding: 20px;
            background: linear-gradient(135deg, rgba(30, 30, 30, 0.85), rgba(20, 60, 20, 0.85)),
                url('assets/img/fardc2.png') center/cover no-repeat fixed;
        }

        .equipe-card {
            width: 100%;
            max-width: 650px;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .equipe-header {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            padding: 14px 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .equipe-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .equipe-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .info-line {
            background: #e8f5e9;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.82rem;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .alert-error {
            background: #fce4ec;
            color: #c62828;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #a5d6a7;
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

        .membres-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 14px;
            border: 1px solid #e0e0e0;
        }

        .membres-section h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .membres-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .membres-table thead th {
            background: #2e7d32;
            color: white;
            padding: 7px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .membres-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }

        .membres-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }

        .membres-table tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid #e8e8e8;
        }

        .membres-table tbody tr:hover {
            background: #f1f8e9;
        }

        .membres-table tbody tr:last-child td {
            border-bottom: none;
        }

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
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Barlow', sans-serif;
            text-decoration: none;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.25);
        }

        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.35);
            color: white;
        }

        .badge-count {
            background: rgba(255, 255, 255, 0.25);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-left: 4px;
        }

        @media (max-width: 600px) {
            .form-row {
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
    <div class="equipe-card">
        <div class="equipe-header">
            <h2><i class="fas fa-users"></i> Équipe de contrôle</h2>
            <div class="user-badge"><i class="fas fa-user-circle"></i>
                <?= htmlspecialchars($_SESSION['user_nom'] ?? 'Utilisateur') ?></div>
        </div>

        <div class="equipe-body">
            <div class="info-line"><i class="fas fa-info-circle"></i> Enregistrez les membres de votre équipe de
                contrôle avant de continuer.</div>

            <?php if ($error): ?>
                <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Formulaire d'ajout -->
            <div class="form-section">
                <h4><i class="fas fa-user-plus"></i> Ajouter un membre</h4>
                <form method="post" id="equipeForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="noms">Noms</label>
                            <input type="text" id="noms" name="noms" placeholder="Nom complet" required maxlength="150">
                        </div>
                        <div class="form-group">
                            <label for="grade">Grade</label>
                            <input type="text" id="grade" name="grade" placeholder="Grade militaire" required
                                maxlength="50">
                        </div>
                        <div class="form-group">
                            <label for="role">Rôle</label>
                            <select id="role" name="role" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="Chef d'équipe">Chef d'équipe</option>
                                <option value="Contrôleur">Contrôleur</option>
                                <option value="Opérateur">Opérateur</option>
                                <option value="Superviseur">Superviseur</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-add"><i class="fas fa-plus-circle"></i> Ajouter</button>
                </form>
            </div>

            <!-- Liste des membres -->
            <div class="membres-section">
                <h4><i class="fas fa-list"></i> Membres enregistrés <span
                        class="badge-count"><?= count($membres) ?></span></h4>

                <?php if (empty($membres)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>Aucun membre enregistré</p>
                    </div>
                <?php else: ?>
                    <table class="membres-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Noms</th>
                                <th>Grade</th>
                                <th>Rôle</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($membres as $i => $m): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($m['noms']) ?></td>
                                    <td><?= htmlspecialchars($m['grade']) ?></td>
                                    <td><?= htmlspecialchars($m['role']) ?></td>
                                    <td>
                                        <a href="equipes.php?supprimer=<?= (int)$m['id'] ?>" class="btn-delete"
                                            onclick="return confirm('Supprimer ce membre ?')"><i
                                                class="fas fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Bouton continuer -->
            <div class="actions-footer">
                <a href="index.php" class="btn-continue"><i class="fas fa-arrow-right"></i> Continuer</a>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
</body>

</html>