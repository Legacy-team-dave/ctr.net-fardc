<?php
require_once '../../includes/functions.php';
require_login();

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

$page_titre = 'Journal des actions';
$breadcrumb = ['Logs' => '#'];
include '../../includes/header.php';

// Récupération des logs avec jointure sur utilisateurs (nom_complet)
$stmt = $pdo->query("
    SELECT l.*, u.nom_complet, u.login, u.email
    FROM logs l
    LEFT JOIN utilisateurs u ON l.id_utilisateur = u.id_utilisateur
    ORDER BY l.date_action DESC
");
$logs = $stmt->fetchAll();

// Statistiques générales
$total_logs = count($logs);

// Compter les connexions (action 'Connexion')
$total_connexions = count(array_filter($logs, fn($log) => strtolower($log['action'] ?? '') === 'connexion'));

// Compter les déconnexions (action 'Déconnexion' ou 'Deconnexion')
$total_deconnexions = count(array_filter($logs, function ($log) {
    $action = $log['action'] ?? '';
    return stripos($action, 'déconnexion') !== false || stripos($action, 'deconnexion') !== false;
}));

// Compter les utilisateurs actifs
$stmt_users = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE actif = 1");
$total_utilisateurs_actifs = $stmt_users->fetchColumn();

// Liste unique des actions et tables pour les filtres
$actions_list = array_unique(array_column($logs, 'action'));
$tables_list = array_unique(array_column($logs, 'table_concernee'));
sort($actions_list);
sort($tables_list);

// Liste des utilisateurs pour le filtre
$users = [];
foreach ($logs as $log) {
    $uid = $log['id_utilisateur'] ?? '';
    $uname = $log['nom_complet'] ?? $log['login'] ?? 'Inconnu';
    if ($uid && !isset($users[$uid])) {
        $users[$uid] = $uname;
    }
}
asort($users);
// MODIFICATION : Ajouter une option "Inconnu" pour les logs sans id_utilisateur
$users[''] = 'Inconnu';
// On peut trier à nouveau si désiré, mais ce n'est pas obligatoire
// asort($users);

// Liste des champs pour l'export
$export_fields = [
    'serie'            => ['label' => 'Série', 'enabled' => true, 'required' => true],
    'id_log'           => ['label' => 'ID Log', 'enabled' => true, 'required' => false],
    'utilisateur'      => ['label' => 'Utilisateur', 'enabled' => true, 'required' => false],
    'action'           => ['label' => 'Action', 'enabled' => true, 'required' => false],
    'table_concernee'  => ['label' => 'Table', 'enabled' => true, 'required' => false],
    'id_enregistrement' => ['label' => 'ID Enregistrement', 'enabled' => true, 'required' => false],
    'details'          => ['label' => 'Détails', 'enabled' => true, 'required' => false],
    'ip_address'       => ['label' => 'Adresse IP', 'enabled' => true, 'required' => false],
    'user_agent'       => ['label' => 'User Agent', 'enabled' => true, 'required' => false],
    'date_action'      => ['label' => 'Date', 'enabled' => true, 'required' => false]
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Journal des actions</title>
    <!-- Font Awesome (local) -->
    <link rel="stylesheet" href="../../assets/css/all.min.css">
    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables Bootstrap 5 theme (CDN) version alignée avec JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- SweetAlert2 CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Styles personnalisés -->
    <style>
    /* ... (conserve les styles inchangés) ... */
    body {
        font-family: 'Barlow', sans-serif;
        background-color: #f5f5f5;
    }

    .modern-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 30px;
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

    .action-buttons {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-shrink: 0;
    }

    .btn-modern {
        border: none;
        border-radius: 8px;
        padding: 8px 16px;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-modern i {
        font-size: 0.9rem;
    }

    .btn-modern:hover {
        transform: translateY(-2px);
        text-decoration: none;
    }

    .btn-primary-modern {
        background: #ffc107;
        color: #333;
        box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
    }

    .btn-primary-modern:hover {
        background: #2e7d32;
        color: white;
        box-shadow: 0 6px 15px rgba(46, 125, 50, 0.4);
    }

    .btn-primary-modern:hover i {
        color: white;
    }

    .btn-reset-modern {
        border: none;
        border-radius: 8px;
        padding: 8px 16px;
        font-size: 0.9rem;
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

    .export-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-export {
        border-radius: 8px;
        padding: 6px 12px;
        font-weight: 500;
        font-size: 0.8rem;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        color: white;
    }

    .btn-export i {
        font-size: 0.9rem;
    }

    .btn-export.csv {
        background: #28a745;
    }

    .btn-export.csv:hover {
        background: #218838;
        transform: translateY(-2px);
    }

    .btn-export.excel {
        background: #1e7e34;
    }

    .btn-export.excel:hover {
        background: #19692c;
        transform: translateY(-2px);
    }

    .btn-export.pdf {
        background: #dc3545;
    }

    .btn-export.pdf:hover {
        background: #c82333;
        transform: translateY(-2px);
    }

    .btn-export.zip {
        background: #9b59b6;
        color: white;
    }

    .btn-export.zip:hover {
        background: #8e44ad;
        transform: translateY(-2px);
    }

    .btn-export.choisir {
        background: #6c757d;
    }

    .btn-export.choisir:hover {
        background: #5a6268;
        transform: translateY(-2px);
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
        backdrop-filter: blur(5px);
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
        font-size: 0.85rem;
        color: #2e7d32;
        margin-bottom: 5px;
        display: block;
    }

    .filter-item .form-label i {
        margin-right: 4px;
    }

    .filter-item .form-select,
    .filter-item .form-control {
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 8px 12px;
        font-size: 0.9rem;
        width: 100%;
    }

    .table-logs {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
        min-width: 1200px;
    }

    .table-logs thead th {
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
        color: white;
        font-weight: 700;
        font-size: 0.9rem;
        padding: 12px 15px;
        border: none;
        text-align: left;
        vertical-align: middle;
        text-transform: uppercase;
    }

    .table-logs thead th:first-child {
        border-radius: 10px 0 0 10px;
        text-align: center;
        width: 50px;
    }

    .table-logs thead th:last-child {
        border-radius: 0 10px 10px 0;
    }

    .table-logs tbody tr {
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border-radius: 10px;
        transition: all 0.3s;
    }

    .table-logs tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(46, 125, 50, 0.15);
    }

    .table-logs tbody td {
        padding: 15px;
        border: none;
        font-size: 0.9rem;
        vertical-align: middle;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .table-logs tbody td:first-child {
        border-radius: 10px 0 0 10px;
        text-align: center;
    }

    .table-logs tbody td:last-child {
        border-radius: 0 10px 10px 0;
    }

    .select-all-checkbox,
    .row-checkbox {
        width: 14px;
        height: 14px;
        cursor: pointer;
    }

    .select-all-checkbox {
        accent-color: #ffd700;
    }

    .row-checkbox {
        accent-color: #2e7d32;
    }

    .dataTables_wrapper {
        width: 100%;
        padding: 0;
        position: relative;
        clear: both;
    }

    .dataTables_wrapper .dataTables_length {
        float: left;
        margin-bottom: 20px;
    }

    .dataTables_wrapper .dataTables_length select {
        width: auto;
        display: inline-block;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 6px 12px;
        margin: 0 5px;
    }

    .dataTables_wrapper .dataTables_filter {
        float: right;
        margin-bottom: 20px;
        display: flex !important;
        align-items: center;
        gap: 10px;
        width: auto;
        max-width: 100%;
    }

    .dataTables_wrapper .dataTables_filter label {
        font-weight: 500;
        color: #2e7d32;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        gap: 5px;
        flex: 0 1 auto;
    }

    .dataTables_wrapper .dataTables_filter input {
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 6px 12px;
        width: 250px;
        min-width: 200px;
    }

    .dataTables_wrapper .dataTables_filter .action-buttons {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
        margin-left: 5px;
    }

    .dataTables_wrapper .dataTables_info {
        float: left;
        margin-top: 20px;
        font-size: 0.9rem;
        color: #2e7d32;
    }

    .dataTables_wrapper .dataTables_paginate {
        float: right;
        margin-top: 20px;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 3px;
        padding: 5px 10px;
        margin: 0 2px;
        border: 1px solid #ccc;
        background: white;
        color: #666;
        font-size: 0.85rem;
        cursor: pointer;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #e0e0e0;
        color: #333;
        border-color: #aaa;
        font-weight: 500;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f0f0f0;
        color: #333;
        border-color: #999;
    }

    .dataTables_scrollBody {
        overflow: visible !important;
        border-radius: 10px;
    }

    .stats-container {
        display: flex;
        gap: 20px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px 25px;
        flex: 1;
        min-width: 180px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(46, 125, 50, 0.15);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
    }

    .stat-icon.purple {
        background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
    }

    .stat-icon.teal {
        background: linear-gradient(135deg, #20c997 0%, #198754 100%);
    }

    .stat-icon.warning {
        background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
    }

    .stat-icon.danger {
        background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
    }

    .stat-info h4 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 700;
        color: #2e7d32;
        line-height: 1.2;
    }

    .stat-info p {
        margin: 0;
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 500;
    }

    .filtre-tags {
        margin-top: 10px;
    }

    .filtre-tag {
        background: #e9ecef;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 0.8rem;
        display: inline-block;
        margin-right: 5px;
    }

    .modal-champs-export {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
    }

    .modal-champs-content {
        background-color: #fff;
        margin: 2% auto;
        padding: 15px;
        border-radius: 15px;
        width: 90%;
        max-width: 700px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        animation: slideDown 0.3s ease;
        max-height: none;
        overflow-y: visible;
    }

    .modal-champs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 2px solid #2e7d32;
    }

    .modal-champs-header h4 {
        margin: 0;
        color: #2e7d32;
        font-weight: 600;
        font-size: 1.2rem;
    }

    .modal-champs-header h4 i {
        margin-right: 8px;
    }

    .modal-champs-close {
        background: none;
        border: none;
        font-size: 1.6rem;
        cursor: pointer;
        color: #6c757d;
        transition: color 0.3s;
        line-height: 1;
    }

    .modal-champs-close:hover {
        color: #dc3545;
    }

    .modal-champs-body .champs-section {
        margin-bottom: 12px;
    }

    .modal-champs-body .champs-section-title {
        font-weight: 600;
        color: #495057;
        margin-bottom: 6px;
        padding-bottom: 3px;
        border-bottom: 1px solid #dee2e6;
        font-size: 0.95rem;
    }

    .modal-champs-body .champs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 6px;
    }

    .modal-champs-body .champ-item {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 5px;
        background: #f8f9fa;
        border-radius: 4px;
        transition: background 0.3s;
    }

    .modal-champs-body .champ-item:hover {
        background: #e9ecef;
    }

    .modal-champs-body .champ-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #2e7d32;
    }

    .modal-champs-body .champ-item label {
        margin: 0;
        cursor: pointer;
        font-size: 0.85rem;
        color: #495057;
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .modal-champs-body .champ-item.required {
        background: #e8f5e9;
        border-left: 3px solid #2e7d32;
    }

    .modal-champs-body .champ-item.required label {
        font-weight: 600;
        color: #2e7d32;
    }

    .modal-champs-body .champ-item.required input[type="checkbox"] {
        accent-color: #2e7d32;
        opacity: 0.8;
    }

    .modal-champs-body .champ-item.required input[type="checkbox"]:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .modal-champs-footer {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 12px;
        padding-top: 8px;
        border-top: 1px solid #dee2e6;
    }

    .modal-champs-footer button {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s;
    }

    .modal-champs-footer .btn-annuler {
        background: #6c757d;
        color: white;
    }

    .modal-champs-footer .btn-annuler:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }

    .modal-champs-footer .btn-confirmer {
        background: #2e7d32;
        color: white;
    }

    .modal-champs-footer .btn-confirmer:hover {
        background: #1b5e20;
        transform: translateY(-2px);
    }

    @media (max-width: 992px) {
        .dataTables_wrapper .dataTables_filter {
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
        }

        .dataTables_wrapper .dataTables_filter label {
            width: 100%;
        }

        .dataTables_wrapper .dataTables_filter input {
            width: 100%;
        }

        .dataTables_wrapper .dataTables_filter .action-buttons {
            width: 100%;
            justify-content: flex-start;
            margin-left: 0;
            margin-top: 5px;
        }
    }

    @media (max-width: 768px) {
        .modern-card .card-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .export-buttons {
            width: 100%;
            justify-content: flex-start;
        }

        .stats-container {
            flex-direction: column;
        }

        .filters-row {
            flex-direction: column;
        }

        .dataTables_wrapper .dataTables_filter .action-buttons {
            flex-direction: column;
            width: 100%;
        }

        .btn-modern {
            width: 100%;
            justify-content: center;
        }

        .modal-champs-content {
            width: 95%;
            margin: 10% auto;
            padding: 15px;
        }

        .modal-champs-body .champs-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>
    <div class="container-fluid py-3">
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-history"></i></div>
                <div class="stat-info">
                    <h4><?= $total_logs ?></h4>
                    <p>Total actions</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-sign-in-alt"></i></div>
                <div class="stat-info">
                    <h4><?= $total_connexions ?></h4>
                    <p>Connexions</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon danger"><i class="fas fa-sign-out-alt"></i></div>
                <div class="stat-info">
                    <h4><?= $total_deconnexions ?></h4>
                    <p>Déconnexions</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h4><?= $total_utilisateurs_actifs ?></h4>
                    <p>Utilisateurs actifs</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card modern-card">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-clipboard-list"></i> Journal des actions</h3>
                        <div class="d-flex align-items-center gap-3">
                            <span class="total-badge"><i class="fas fa-database"></i> Total : <?= $total_logs ?></span>
                            <div class="export-buttons">
                                <button class="btn-export choisir" id="choisir-champs"><i class="fas fa-sliders-h"></i>
                                    Champs</button>
                                <button class="btn-export csv" id="export-csv"><i class="fas fa-file-csv"></i>
                                    CSV</button>
                                <button class="btn-export excel" id="export-excel"><i class="fas fa-file-excel"></i>
                                    Excel</button>
                                <button class="btn-export pdf" id="export-pdf"><i class="fas fa-file-pdf"></i>
                                    PDF</button>
                                <button class="btn-export zip" id="export-zip"><i class="fas fa-file-archive"></i>
                                    ZIP</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtres -->
                        <div class="filters-row">
                            <div class="filter-item">
                                <label class="form-label"><i class="fas fa-tag"></i> Action</label>
                                <select id="action-filter" class="form-select">
                                    <option value="">Toutes</option>
                                    <?php foreach ($actions_list as $action): ?>
                                    <option value="<?= htmlspecialchars($action) ?>"><?= htmlspecialchars($action) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-item">
                                <label class="form-label"><i class="fas fa-table"></i> Table concernée</label>
                                <select id="table-filter" class="form-select">
                                    <option value="">Toutes</option>
                                    <?php foreach ($tables_list as $table): ?>
                                    <option value="<?= htmlspecialchars($table) ?>"><?= htmlspecialchars($table) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-item">
                                <label class="form-label"><i class="fas fa-user"></i> Utilisateur</label>
                                <select id="user-filter" class="form-select">
                                    <option value="">Tous</option>
                                    <?php foreach ($users as $uid => $uname): ?>
                                    <option value="<?= $uid ?>"><?= htmlspecialchars($uname) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-item">
                                <label class="form-label"><i class="fas fa-calendar-alt"></i> Début</label>
                                <input type="date" id="date-debut" class="form-control">
                            </div>
                            <div class="filter-item">
                                <label class="form-label"><i class="fas fa-calendar-alt"></i> Fin</label>
                                <input type="date" id="date-fin" class="form-control">
                            </div>
                            <div class="filter-item">
                                <label class="form-label">&nbsp;</label>
                                <button id="reset-filters" class="btn-reset-modern w-100"><i
                                        class="fas fa-undo-alt"></i> Réinitialiser</button>
                            </div>
                        </div>

                        <!-- Tags des filtres actifs -->
                        <div class="filtre-tags mb-3" style="display: none;"></div>

                        <!-- Tableau sans les colonnes ID et ID Enregistrement -->
                        <table id="table-logs" class="table-logs" style="width:100%">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" class="select-all-checkbox" id="select-all"></th>
                                    <th>Utilisateur</th>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>Détails</th>
                                    <th>Adresse IP</th>
                                    <th>User Agent</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log):
                                    $utilisateur = $log['nom_complet'] ?? $log['login'] ?? 'Inconnu';
                                    $details = $log['details'] ?? '';
                                    $user_agent = $log['user_agent'] ?? '';
                                    $date_action = $log['date_action'] ?? '';
                                ?>
                                <tr data-id="<?= $log['id_log'] ?>"
                                    data-action="<?= htmlspecialchars($log['action'] ?? '') ?>"
                                    data-table="<?= htmlspecialchars($log['table_concernee'] ?? '') ?>"
                                    data-user="<?= $log['id_utilisateur'] ?? '' ?>"
                                    data-date="<?= htmlspecialchars($date_action) ?>">
                                    <td><input type="checkbox" class="row-checkbox" value="<?= $log['id_log'] ?>"></td>
                                    <td><?= htmlspecialchars($utilisateur) ?></td>
                                    <td><?= htmlspecialchars($log['action'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($log['table_concernee'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($details) ?></td>
                                    <td><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($user_agent) ?></td>
                                    <td><?= htmlspecialchars($date_action) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de sélection des champs -->
    <div id="modal-champs-export" class="modal-champs-export">
        <div class="modal-champs-content">
            <div class="modal-champs-header">
                <h4><i class="fas fa-sliders-h"></i> Choisir les champs à exporter</h4>
                <button class="modal-champs-close" id="modal-champs-close">&times;</button>
            </div>
            <div class="modal-champs-body">
                <div class="champs-section">
                    <div class="champs-section-title">Champs obligatoires</div>
                    <div class="champs-grid" id="champs-obligatoires">
                        <?php foreach ($export_fields as $key => $field): ?>
                        <?php if ($field['required']): ?>
                        <div class="champ-item required">
                            <input type="checkbox" id="field_<?= $key ?>" value="<?= $key ?>" checked disabled>
                            <label for="field_<?= $key ?>"><?= $field['label'] ?></label>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="champs-section">
                    <div class="champs-section-title">Champs optionnels</div>
                    <div class="champs-grid" id="champs-optionnels">
                        <?php foreach ($export_fields as $key => $field): ?>
                        <?php if (!$field['required']): ?>
                        <div class="champ-item">
                            <input type="checkbox" id="field_<?= $key ?>" value="<?= $key ?>"
                                <?= $field['enabled'] ? 'checked' : '' ?>>
                            <label for="field_<?= $key ?>"><?= $field['label'] ?></label>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-champs-footer">
                <button class="btn-annuler" id="modal-champs-annuler">Annuler</button>
                <button class="btn-confirmer" id="modal-champs-confirmer">Confirmer</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- jQuery (CDN) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 JS (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables (CDN) version 1.13.4 -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 est déjà chargé dans header.php, donc on ne le recharge pas ici -->
    <!-- Librairies d'export (CDN) -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <script>
    $(document).ready(function() {
        // État des champs sélectionnés pour l'export
        let selectedExportFields = <?php echo json_encode(array_map(function ($field) {
                                            return $field['enabled'];
                                        }, $export_fields)); ?>;

        const getTimestamp = () => {
            const d = new Date();
            return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}_${String(d.getHours()).padStart(2,'0')}h${String(d.getMinutes()).padStart(2,'0')}`;
        };

        const loadImage = async (url) => {
            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const blob = await response.blob();
                return await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onloadend = () => {
                        const img = new Image();
                        img.onload = () => resolve({
                            dataURL: reader.result,
                            width: img.width,
                            height: img.height
                        });
                        img.onerror = reject;
                        img.src = reader.result;
                    };
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                });
            } catch (error) {
                console.error('Erreur chargement image:', error);
                throw error;
            }
        };

        <?php if ($success_message): ?>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: '<?= addslashes($success_message) ?>',
            timer: 2000,
            showConfirmButton: false,
            position: 'top-end',
            toast: true,
            background: '#28a745',
            color: '#ffffff',
            iconColor: '#ffffff',
            timerProgressBar: true
        });
        <?php endif; ?>

        let searchTerm = '';

        function highlightSearchTerm(data, term) {
            if (!term || !data) return data;
            const regex = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            if (typeof data === 'string') {
                if (data.indexOf('<') !== -1 && data.indexOf('>') !== -1) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data;
                    const textContent = tempDiv.textContent || tempDiv.innerText;
                    if (textContent.match(regex)) {
                        return data.replace(new RegExp(textContent.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
                                'g'),
                            textContent.replace(regex, '<mark>$1</mark>'));
                    }
                    return data;
                }
                return data.replace(regex, '<mark>$1</mark>');
            }
            return data;
        }

        const table = $('#table-logs').DataTable({
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
            order: [
                [7, 'desc']
            ],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            scrollX: false,
            scrollY: false,
            scrollCollapse: false,
            paging: true,
            columnDefs: [{
                orderable: false,
                targets: [0]
            }],
            createdRow: function(row, data, dataIndex) {
                if (searchTerm) {
                    $(row).find('td').each(function() {
                        const $td = $(this);
                        if ($td.index() === 0) return;
                        $td.html(highlightSearchTerm($td.html(), searchTerm));
                    });
                }
            },
            initComplete: function() {
                const filterDiv = $('.dataTables_filter');

                filterDiv.css({
                    'display': 'flex',
                    'align-items': 'center',
                    'gap': '10px',
                    'float': 'right'
                });

                const searchLabel = filterDiv.find('label');
                searchLabel.css({
                    'display': 'flex',
                    'align-items': 'center',
                    'margin-bottom': '0',
                    'flex': '0 1 auto'
                });

                $('.dataTables_filter label').contents().filter(function() {
                    return this.nodeType === 3;
                }).remove();
            }
        });

        $('.dataTables_filter input').on('keyup search input', function() {
            searchTerm = $(this).val();
            setTimeout(() => {
                if (searchTerm) {
                    $('#table-logs tbody tr').each(function() {
                        $(this).find('td').each(function() {
                            const $td = $(this);
                            if ($td.index() === 0) return;
                            const originalHtml = $td.html();
                            if (!originalHtml.includes('<mark')) {
                                $td.html(highlightSearchTerm(originalHtml,
                                    searchTerm));
                            }
                        });
                    });
                } else {
                    table.rows().invalidate().draw(false);
                }
            }, 100);
        });

        function updateSelectAll() {
            $('#select-all').prop('checked', $('.row-checkbox:checked').length === $('.row-checkbox')
                .length);
        }

        $('#select-all').on('change', function() {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
        });

        $(document).on('change', '.row-checkbox', updateSelectAll);

        table.on('draw', function() {
            $('#select-all').prop('checked', false);
        });

        function updateFilterTags() {
            const tags = [];
            if ($('#action-filter').val()) tags.push(
                `Action: ${$('#action-filter').find('option:selected').text()}`);
            if ($('#table-filter').val()) tags.push(
                `Table: ${$('#table-filter').find('option:selected').text()}`);
            if ($('#user-filter').val()) tags.push(
                `Utilisateur: ${$('#user-filter').find('option:selected').text()}`);
            if ($('#date-debut').val()) tags.push(`Du: ${$('#date-debut').val()}`);
            if ($('#date-fin').val()) tags.push(`Au: ${$('#date-fin').val()}`);

            const $tagsContainer = $('.filtre-tags');
            $tagsContainer.empty();
            tags.forEach(tag => $tagsContainer.append(`<span class="filtre-tag me-2">${tag}</span>`));
            $tagsContainer.toggle(tags.length > 0);
        }

        // MODIFICATION : Filtre utilisateur réécrit avec .attr('data-user')
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'table-logs') return true;

            const row = table.row(dataIndex);
            const rowNode = row.node();
            const $row = $(rowNode);

            const action = $row.attr('data-action') || '';
            const tableName = $row.attr('data-table') || '';
            const user = $row.attr('data-user') || '';
            const dateIso = $row.attr('data-date') || '';

            const actionFilter = $('#action-filter').val();
            const tableFilter = $('#table-filter').val();
            const userFilter = $('#user-filter').val();
            const dateDebut = $('#date-debut').val();
            const dateFin = $('#date-fin').val();

            if (actionFilter && action !== actionFilter) return false;
            if (tableFilter && tableName !== tableFilter) return false;
            if (userFilter && user !== userFilter) return false;
            if (dateDebut && dateIso && dateIso < dateDebut) return false;
            if (dateFin && dateIso && dateIso > dateFin) return false;

            return true;
        });

        $('#action-filter, #table-filter, #user-filter, #date-debut, #date-fin')
            .on('change keyup', function() {
                table.draw();
                updateFilterTags();
            });

        $('#reset-filters').on('click', function() {
            $('#action-filter, #table-filter, #user-filter, #date-debut, #date-fin')
                .val('');
            table.draw();
            updateFilterTags();
        });

        // Modal de sélection des champs
        $('#choisir-champs').on('click', function() {
            $('#champs-optionnels input[type="checkbox"]').each(function() {
                const fieldName = $(this).val();
                $(this).prop('checked', selectedExportFields[fieldName] || false);
            });
            $('#modal-champs-export').fadeIn(300);
        });

        $('#modal-champs-close, #modal-champs-annuler').on('click', function() {
            $('#modal-champs-export').fadeOut(300);
        });

        $(window).on('click', function(e) {
            if ($(e.target).is('#modal-champs-export')) {
                $('#modal-champs-export').fadeOut(300);
            }
        });

        $('#modal-champs-confirmer').on('click', function() {
            $('#champs-optionnels input[type="checkbox"]').each(function() {
                const fieldName = $(this).val();
                selectedExportFields[fieldName] = $(this).prop('checked');
            });
            $('#modal-champs-export').fadeOut(300);

            const selectedCount = Object.values(selectedExportFields).filter(v => v).length;
            Swal.fire({
                icon: 'success',
                title: 'Champs sélectionnés',
                text: `${selectedCount} champs seront exportés`,
                timer: 1500,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        });

        // Fonctions d'export (inchangées)
        function getFilteredRows() {
            let rowsToExport = [];
            const checkboxes = $('.row-checkbox:checked');
            if (checkboxes.length > 0) {
                checkboxes.each(function() {
                    const row = table.row($(this).closest('tr'));
                    if (row) rowsToExport.push(row);
                });
            } else {
                table.rows({
                    search: 'applied',
                    filter: 'applied'
                }).every(function() {
                    rowsToExport.push(this);
                });
            }
            return rowsToExport;
        }

        function extractRowData(row) {
            const node = row.node();
            const $cells = $(node).find('td');
            const $row = $(node);

            // Indices après suppression des colonnes ID et ID Enreg :
            // 0: checkbox, 1: Utilisateur, 2: Action, 3: Table, 4: Détails, 5: IP, 6: UA, 7: Date
            const rowData = {};

            rowData.id_log = $row.data('id') || '';
            rowData.id_enregistrement = '';

            rowData.utilisateur = $cells.eq(1).text().trim().toUpperCase();
            rowData.action = $cells.eq(2).text().trim().toUpperCase();
            rowData.table_concernee = $cells.eq(3).text().trim().toUpperCase();
            rowData.details = $cells.eq(4).text().trim();
            rowData.ip_address = $cells.eq(5).text().trim();
            rowData.user_agent = $cells.eq(6).text().trim();
            rowData.date_action = $cells.eq(7).text().trim();

            return rowData;
        }

        const fieldOrder = ['serie', 'id_log', 'utilisateur', 'action', 'table_concernee', 'id_enregistrement',
            'details', 'ip_address', 'user_agent', 'date_action'
        ];
        const fullLabels = {
            'serie': 'SÉRIE',
            'id_log': 'ID LOG',
            'utilisateur': 'UTILISATEUR',
            'action': 'ACTION',
            'table_concernee': 'TABLE',
            'id_enregistrement': 'ID ENREG.',
            'details': 'DÉTAILS',
            'ip_address': 'IP',
            'user_agent': 'USER AGENT',
            'date_action': 'DATE'
        };
        const abbrLabels = {
            'serie': 'N°',
            'id_log': 'ID',
            'utilisateur': 'USER',
            'action': 'ACT.',
            'table_concernee': 'TABLE',
            'id_enregistrement': 'ID ENR.',
            'details': 'DÉTAILS',
            'ip_address': 'IP',
            'user_agent': 'UA',
            'date_action': 'DATE'
        };

        function prepareExportData(useAbbreviatedHeaders) {
            const rows = getFilteredRows();
            if (rows.length === 0) return null;

            const rawRows = rows.map(row => extractRowData(row));

            rawRows.sort((a, b) => (a.date_action < b.date_action) ? 1 : -1);

            const selectedFields = fieldOrder.filter(field => selectedExportFields[field]);

            const labels = useAbbreviatedHeaders ? abbrLabels : fullLabels;
            const headers = selectedFields.map(field => labels[field]);

            const data = rawRows.map((row, index) => {
                return selectedFields.map(field => {
                    if (field === 'serie') {
                        return (index + 1).toString();
                    } else {
                        return row[field] || '';
                    }
                });
            });

            const headerLines = [
                ['MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS'],
                ['INSPECTORAT GENERAL DES FARDC'],
                ['JOURNAL DES ACTIONS']
            ];

            return {
                headerLines,
                headers,
                data
            };
        }

        async function generatePDFBlob(exportData) {
            const {
                headerLines,
                headers,
                data
            } = exportData;
            const headerStrings = headerLines.map(line => line[0]);

            let logo, qrCode, watermark;
            try {
                [logo, qrCode, watermark] = await Promise.all([
                    loadImage('../../assets/img/new-logo-ig-fardc.png'),
                    loadImage('../../assets/img/qr-code-ig-fardc.png'),
                    loadImage('../../assets/img/filigrane_logo_ig_fardc.png')
                ]);
            } catch (imageError) {
                console.warn('Images non trouvées', imageError);
                logo = {
                    dataURL: null,
                    width: 100,
                    height: 100
                };
                qrCode = {
                    dataURL: null,
                    width: 100,
                    height: 100
                };
                watermark = {
                    dataURL: null,
                    width: 100,
                    height: 100
                };
            }

            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4'
            });

            const pageWidth = doc.internal.pageSize.width;
            const pageHeight = doc.internal.pageSize.height;
            const margin = 15;
            const rightMargin = 15;
            const topMargin = 50;
            const bottomMargin = 35;

            const columnStyles = {};
            headers.forEach((_, index) => {
                columnStyles[index] = {
                    halign: index === 0 ? 'center' : 'left'
                };
            });

            let headerAdded = false;

            function addWatermark() {
                if (!watermark.dataURL) return;
                try {
                    doc.saveGraphicsState();
                    doc.setGState(new doc.GState({
                        opacity: 0.2
                    }));
                    const wmWidth = 100;
                    const wmHeight = (watermark.height / watermark.width) * wmWidth;
                    const x = (pageWidth - wmWidth) / 2;
                    const y = (pageHeight - wmHeight) / 2;
                    doc.addImage(watermark.dataURL, 'PNG', x, y, wmWidth, wmHeight);
                    doc.restoreGraphicsState();
                } catch (e) {
                    console.warn('Impossible d\'ajouter le filigrane', e);
                }
            }

            function addFirstPageHeader() {
                if (logo.dataURL) {
                    try {
                        const logoHeight = 18;
                        const logoWidth = (logo.width / logo.height) * logoHeight;
                        doc.addImage(logo.dataURL, 'PNG', margin, 8, logoWidth, logoHeight);
                    } catch (e) {
                        console.warn('Impossible d\'ajouter le logo');
                    }
                }

                doc.setFontSize(9);
                doc.setTextColor(100);
                doc.setFont('helvetica', 'normal');
                const dateStr = 'Kinshasa, le ' + new Date().toLocaleDateString('fr-FR');
                doc.text(dateStr, pageWidth - rightMargin, 12, {
                    align: 'right'
                });

                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.setFont('helvetica', 'bold');
                doc.text(headerStrings[0], pageWidth / 2, 25, {
                    align: 'center'
                });

                doc.setFontSize(11);
                doc.text(headerStrings[1], pageWidth / 2, 32, {
                    align: 'center'
                });

                doc.setFontSize(14);
                doc.setTextColor(255, 0, 0);
                doc.text(headerStrings[2], pageWidth / 2, 42, {
                    align: 'center'
                });

                doc.setDrawColor(200);
                doc.setLineWidth(0.5);
                doc.line(margin, 48, pageWidth - rightMargin, 48);

                headerAdded = true;
            }

            function addFooter(pageNumber) {
                const footerY = pageHeight - 15;
                const lineY = pageHeight - 20;
                const lineWidth = pageWidth - margin - rightMargin;
                const segmentWidth = lineWidth / 3;

                doc.setFillColor(0, 162, 232);
                doc.rect(margin, lineY, segmentWidth, 1, 'F');

                doc.setFillColor(255, 215, 0);
                doc.rect(margin + segmentWidth, lineY, segmentWidth, 1, 'F');

                doc.setFillColor(239, 43, 45);
                doc.rect(margin + (2 * segmentWidth), lineY, segmentWidth, 1, 'F');

                if (qrCode.dataURL) {
                    try {
                        const qrSize = 8;
                        const qrX = pageWidth - rightMargin - qrSize;
                        const qrY = lineY - qrSize;
                        doc.addImage(qrCode.dataURL, 'PNG', qrX, qrY, qrSize, qrSize);
                    } catch (e) {
                        console.warn('Impossible d\'ajouter le QR code');
                    }
                }

                doc.setFontSize(7);
                doc.setTextColor(100);
                doc.setFont('helvetica', 'normal');
                doc.text(
                    'Inspectorat Général des FARDC, Avenue des écuries, N°54, Quartier Joli Parc, Commune de NGALIEMA',
                    pageWidth / 2, footerY, {
                        align: 'center'
                    });
                doc.setFont('times', 'italic');
                doc.text('Emails : infoigfardc2017@gmail.com; igfardc2017@yahoo.fr',
                    pageWidth / 2, footerY + 5, {
                        align: 'center'
                    });
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(6);
                doc.text(`Page ${pageNumber}`, pageWidth - rightMargin, pageHeight - 8, {
                    align: 'right'
                });
            }

            doc.autoTable({
                head: [headers],
                body: data,
                startY: topMargin,
                margin: {
                    left: margin,
                    right: rightMargin,
                    bottom: bottomMargin
                },
                styles: {
                    fontSize: 7,
                    cellPadding: 2,
                    font: 'helvetica',
                    halign: 'left',
                    valign: 'middle',
                    lineColor: [200, 200, 200],
                    lineWidth: 0.1
                },
                headStyles: {
                    fillColor: [255, 255, 255],
                    textColor: [0, 0, 0],
                    fontStyle: 'bold',
                    halign: 'center',
                    fontSize: 7,
                    lineColor: [200, 200, 200],
                    lineWidth: 0.1
                },
                columnStyles: columnStyles,
                showHead: 'firstPage',
                didDrawPage: function(data) {
                    addWatermark();
                    if (data.pageNumber === 1 && !headerAdded) {
                        addFirstPageHeader();
                    }
                    addFooter(data.pageNumber);
                }
            });

            return doc.output('blob');
        }

        // --- Export ZIP ---
        $('#export-zip').on('click', async function() {
            const exportDataFull = prepareExportData(false);
            const exportDataAbbr = prepareExportData(true);
            if (!exportDataFull || exportDataFull.data.length === 0) {
                return Swal.fire('Aucune donnée à exporter', '', 'info');
            }

            Swal.fire({
                title: 'Génération du ZIP...',
                text: 'Préparation des fichiers',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const csvHeaderLines = exportDataFull.headerLines.map(line => line[0]);
                const csvContent = [...csvHeaderLines, '', exportDataFull.headers.join(';'), ...
                    exportDataFull.data.map(r => r.join(';'))
                ].join('\n');
                const csvBlob = new Blob(["\uFEFF" + csvContent], {
                    type: 'text/csv;charset=utf-8;'
                });

                const worksheetData = [...exportDataFull.headerLines, [], exportDataFull.headers,
                    ...exportDataFull.data
                ];
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(worksheetData);
                const range = XLSX.utils.decode_range(ws['!ref']);
                if (!ws['!merges']) ws['!merges'] = [];
                for (let i = 0; i < 3; i++) {
                    ws['!merges'].push({
                        s: {
                            r: i,
                            c: 0
                        },
                        e: {
                            r: i,
                            c: exportDataFull.headers.length - 1
                        }
                    });
                }
                XLSX.utils.book_append_sheet(wb, ws, 'LOGS');
                const excelBlob = new Blob([XLSX.write(wb, {
                    bookType: 'xlsx',
                    type: 'array'
                })], {
                    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                });

                const pdfBlob = await generatePDFBlob(exportDataAbbr);

                const zip = new JSZip();
                zip.file("logs.csv", csvBlob);
                zip.file("logs.xlsx", excelBlob);
                zip.file("logs.pdf", pdfBlob);

                const zipBlob = await zip.generateAsync({
                    type: "blob"
                });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(zipBlob);
                link.download = `logs_${getTimestamp()}.zip`;
                link.click();

                Swal.close();
            } catch (error) {
                Swal.close();
                console.error('Erreur ZIP:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la création du ZIP.'
                });
            }
        });

        // --- Export CSV ---
        $('#export-csv').on('click', function() {
            const exportData = prepareExportData(false);
            if (!exportData || exportData.data.length === 0) {
                return Swal.fire('Aucune donnée à exporter', '', 'info');
            }
            const csvHeaderLines = exportData.headerLines.map(line => line[0]);
            const csvContent = [...csvHeaderLines, '', exportData.headers.join(';'), ...exportData.data
                .map(r => r.join(';'))
            ].join('\n');
            const blob = new Blob(["\uFEFF" + csvContent], {
                type: 'text/csv;charset=utf-8;'
            });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `logs_${getTimestamp()}.csv`;
            link.click();
        });

        // --- Export Excel ---
        $('#export-excel').on('click', function() {
            const exportData = prepareExportData(false);
            if (!exportData || exportData.data.length === 0) {
                return Swal.fire('Aucune donnée à exporter', '', 'info');
            }
            const worksheetData = [...exportData.headerLines, [], exportData.headers, ...exportData
                .data
            ];
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(worksheetData);
            const range = XLSX.utils.decode_range(ws['!ref']);
            if (!ws['!merges']) ws['!merges'] = [];
            for (let i = 0; i < 3; i++) {
                ws['!merges'].push({
                    s: {
                        r: i,
                        c: 0
                    },
                    e: {
                        r: i,
                        c: exportData.headers.length - 1
                    }
                });
            }
            XLSX.utils.book_append_sheet(wb, ws, 'LOGS');
            XLSX.writeFile(wb, `logs_${getTimestamp()}.xlsx`);
        });

        // --- Export PDF ---
        $('#export-pdf').on('click', async function() {
            const exportData = prepareExportData(true);
            if (!exportData || exportData.data.length === 0) {
                return Swal.fire({
                    icon: 'info',
                    title: 'Aucune donnée à exporter',
                    text: 'Veuillez sélectionner des lignes ou vérifier vos filtres.'
                });
            }

            Swal.fire({
                title: 'Préparation du PDF...',
                text: 'Chargement des images',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const pdfBlob = await generatePDFBlob(exportData);
                const link = document.createElement('a');
                link.href = URL.createObjectURL(pdfBlob);
                link.download = `logs_${getTimestamp()}.pdf`;
                link.click();
                Swal.close();
            } catch (error) {
                Swal.close();
                console.error('Erreur PDF:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la génération du PDF.'
                });
            }
        });
    });
    </script>

    <?php include '../../includes/footer.php'; ?>