<?php
// Définir l'en-tête UTF-8 pour la page avec BOM
header('Content-Type: text/html; charset=utf-8');

// Envoyer le BOM UTF-8 pour les navigateurs qui en ont besoin
echo "\xEF\xBB\xBF";

require_once '../../includes/functions.php';
require_login();

check_profil(['ADMIN_IG']);

// --- AJOUT LOG : journalisation des exports ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'log_export') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? '';
    $filtres = $_GET['filtres'] ?? '';
    if ($type) {
        $details = "Export $type" . ($filtres ? " avec filtres: $filtres" : "");
        audit_action('EXPORT', 'militaires', null, $details);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
// --- FIN AJOUT LOG ---

// Récupération du message de succès depuis la session
$success_message = null;
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// ===== AJOUT : Récupération du paramètre province depuis l'URL =====
$selected_province = isset($_GET['province']) ? $_GET['province'] : '';

$page_titre = 'Liste des militaires';
$breadcrumb = ['Militaires' => '#'];
include '../../includes/header.php';

// Ordre personnalisé des grades
$grade_order = [
    'GENA',
    'GAM',
    'LTGEN',
    'AMR',
    'GENMAJ',
    'VAM',
    'GENBDE',
    'CAM',
    'COL',
    'CPV',
    'LTCOL',
    'CPF',
    'MAJ',
    'CPC',
    'CAPT',
    'LDV',
    'LT',
    'EV',
    'SLT',
    '2EV',
    'A-C',
    'MCP',
    'A-1',
    '1MC',
    'ADJ',
    'MRC',
    '1SM',
    '1MR',
    'SM',
    '2MR',
    '1SGT',
    'MR',
    'SGT',
    'QMT',
    'CPL',
    '1MT',
    '1CL',
    '2MT',
    '2CL',
    'MT',
    'REC',
    'ASK',
    'COMD'
];

// Compter le nombre total de militaires
$countStmt = $pdo->query("SELECT COUNT(*) FROM militaires");
$total_militaires = $countStmt->fetchColumn();

// Statistiques complètes
$stats = [
    'total' => $total_militaires,
    'categories' => $pdo->query("SELECT COUNT(DISTINCT categorie) FROM militaires WHERE categorie IS NOT NULL AND categorie != ''")->fetchColumn(),
    'garnisons' => $pdo->query("SELECT COUNT(DISTINCT garnison) FROM militaires WHERE garnison IS NOT NULL AND garnison != ''")->fetchColumn(),
    'provinces' => $pdo->query("SELECT COUNT(DISTINCT province) FROM militaires WHERE province IS NOT NULL AND province != ''")->fetchColumn(),
    'actifs' => $pdo->query("SELECT COUNT(*) FROM militaires WHERE statut = 1")->fetchColumn(),
    'retraites' => $pdo->query("SELECT COUNT(*) FROM militaires WHERE categorie = 'RETRAITES'")->fetchColumn(),
    'integres' => $pdo->query("SELECT COUNT(*) FROM militaires WHERE categorie = 'INTEGRES'")->fetchColumn(),
    'dcd_av_bio' => $pdo->query("SELECT COUNT(*) FROM militaires WHERE categorie = 'DCD_AV_BIO'")->fetchColumn(),
    'dcd_ap_bio' => $pdo->query("SELECT COUNT(*) FROM militaires WHERE categorie = 'DCD_AP_BIO'")->fetchColumn()
];

// Récupérer les listes pour les filtres
$categories_list = $pdo->query("SELECT DISTINCT categorie FROM militaires WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie")->fetchAll(PDO::FETCH_COLUMN);
$garnisons_list = $pdo->query("SELECT DISTINCT garnison FROM militaires WHERE garnison IS NOT NULL AND garnison != '' ORDER BY garnison")->fetchAll(PDO::FETCH_COLUMN);
$provinces_list = $pdo->query("SELECT DISTINCT province FROM militaires WHERE province IS NOT NULL AND province != '' ORDER BY province")->fetchAll(PDO::FETCH_COLUMN);

// Libellés des catégories avec gestion UTF-8
$categories_labels = [
    'ACTIF' => 'Actif',
    'RETRAITES' => 'Retraité',
    'INTEGRES' => 'Intégré',
    'DCD_AV_BIO' => 'Décédé Avant Bio',
    'DCD_AP_BIO' => 'Décédé Après Bio'
];

// ===== NOUVELLE FONCTION : retourne l'icône correspondant au libellé de catégorie =====
function getIconeCategorie($categorie_libelle)
{
    $icones = [
        'Actif' => 'fa-user-check',
        'Intégré' => 'fa-user-plus',
        'Retraité' => 'fa-user-clock',
        'Décédé Après Bio' => 'fa-skull',
        'Décédé Avant Bio' => 'fa-skull-crossbones'
    ];
    return $icones[$categorie_libelle] ?? 'fa-user-tag';
}

// Récupération des tops pour les nouvelles cartes
$top_categories = $pdo->query("
    SELECT categorie, COUNT(*) as total 
    FROM militaires 
    WHERE categorie IS NOT NULL AND categorie != '' 
    GROUP BY categorie 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

$top_garnisons = $pdo->query("
    SELECT garnison, COUNT(*) as total 
    FROM militaires 
    WHERE garnison IS NOT NULL AND garnison != '' 
    GROUP BY garnison 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

$top_provinces = $pdo->query("
    SELECT province, COUNT(*) as total 
    FROM militaires 
    WHERE province IS NOT NULL AND province != '' 
    GROUP BY province 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

// Récupération des tops par catégorie
$top_actifs = $pdo->query("
    SELECT grade, COUNT(*) as total 
    FROM militaires 
    WHERE statut = 1 
    GROUP BY grade 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

$top_retraites = $pdo->query("
    SELECT grade, COUNT(*) as total 
    FROM militaires 
    WHERE categorie = 'RETRAITES' 
    GROUP BY grade 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

$top_integres = $pdo->query("
    SELECT grade, COUNT(*) as total 
    FROM militaires 
    WHERE categorie = 'INTEGRES' 
    GROUP BY grade 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

$top_dcd_av_bio = $pdo->query("
    SELECT grade, COUNT(*) as total 
    FROM militaires 
    WHERE categorie = 'DCD_AV_BIO' 
    GROUP BY grade 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

$top_dcd_ap_bio = $pdo->query("
    SELECT grade, COUNT(*) as total 
    FROM militaires 
    WHERE categorie = 'DCD_AP_BIO' 
    GROUP BY grade 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

// Générer un timestamp pour les noms de fichiers
$timestamp = date('Y-m-d_H\hi');
?>

<!-- Inclusion des styles -->
<link rel="stylesheet" href="../../assets/css/tables-unified.css">

<style>
    /* Styles de base */
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

    /* Boutons d'action */
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

    /* Bouton Nouveau (Jaune) */
    .btn-primary-modern {
        background: #ffc107;
        color: #333;
        box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
    }

    .btn-primary-modern:hover {
        background: #ffb300;
        color: #333;
        box-shadow: 0 6px 15px rgba(255, 193, 7, 0.4);
    }

    /* Bouton Importer (Rouge) */
    .btn-secondary-modern {
        background: #dc3545;
        color: white;
        box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
    }

    .btn-secondary-modern:hover {
        background: #c82333;
        color: white;
        box-shadow: 0 6px 15px rgba(220, 53, 69, 0.4);
    }

    /* Bouton Réinitialiser */
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

    /* Boutons d'export */
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

    /* Styles des sections de statistiques */
    .stats-section {
        margin-bottom: 30px;
    }

    .section-title {
        color: #000000;
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 15px;
        padding-bottom: 5px;
        border-bottom: 2px solid #e0e0e0;
    }

    .section-title i {
        margin-right: 8px;
        color: #2e7d32;
    }

    .stats-container {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    /* Style pour les 5 cartes de la section détails */
    .stats-container.compact .stat-card {
        padding: 12px 15px;
        min-width: 150px;
    }

    .stats-container.compact .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
    }

    .stats-container.compact .stat-info h4 {
        font-size: 1.3rem;
    }

    .stats-container.compact .stat-info p {
        font-size: 0.7rem;
        text-transform: none;
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

    .stat-icon.blue {
        background: linear-gradient(135deg, #1e4b8c 0%, #0d2b4f 100%);
    }

    .stat-icon.orange {
        background: linear-gradient(135deg, #b85e00 0%, #8a4700 100%);
    }

    .stat-icon.red {
        background: linear-gradient(135deg, #8b1e1e 0%, #5e1414 100%);
    }

    .stat-icon.purple {
        background: linear-gradient(135deg, #6f42c1 0%, #4e2a8a 100%);
    }

    .stat-icon.teal {
        background: linear-gradient(135deg, #20c997 0%, #169b74 100%);
    }

    .stat-icon.green {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    }

    .stat-icon.gray {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    }

    .stat-icon.cyan {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
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
        text-transform: none;
    }

    .stat-info p::first-letter {
        text-transform: uppercase;
    }

    /* Styles du tableau */
    .table-militaires {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
        min-width: 1300px;
    }

    .table-militaires thead th {
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

    .table-militaires thead th:first-child {
        border-radius: 10px 0 0 10px;
        text-align: center;
        width: 50px;
    }

    .table-militaires thead th:last-child {
        border-radius: 0 10px 10px 0;
    }

    .table-militaires tbody tr {
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border-radius: 10px;
        transition: all 0.3s;
    }

    .table-militaires tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(46, 125, 50, 0.15);
    }

    .table-militaires tbody td {
        padding: 15px;
        border: none;
        font-size: 0.9rem;
        vertical-align: middle;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .table-militaires tbody td:first-child {
        border-radius: 10px 0 0 10px;
        text-align: center;
    }

    .table-militaires tbody td:last-child {
        border-radius: 0 10px 10px 0;
    }


    .categorie-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.75rem;
        background: #e9ecef;
        color: #495057;
        white-space: nowrap;
    }

    .zdef-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.75rem;
        background: #2e7d32;
        color: white;
        white-space: nowrap;
    }

    .zdef-badge-1 {
        background: #1e4b8c;
    }

    .zdef-badge-2 {
        background: #b85e00;
    }

    .zdef-badge-3 {
        background: #8b1e1e;
    }

    .null-value {
        color: #6c757d;
        font-weight: 500;
        font-style: italic;
    }

    .uppercase-text {
        text-transform: uppercase;
    }

    /* DataTables - Styles pour l'alignement recherche/boutons */
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

    /* Style modifié pour aligner recherche et boutons */
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

    /* Responsive pour les petits écrans */
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
        .dataTables_wrapper .dataTables_filter .action-buttons {
            flex-direction: column;
            width: 100%;
        }

        .btn-modern {
            width: 100%;
            justify-content: center;
        }
    }

    /* Pour les 5 colonnes sur la même ligne */
    .col-lg-2-4 {
        flex: 0 0 auto;
        width: 20%;
    }

    @media (max-width: 1200px) {
        .col-lg-2-4 {
            width: 33.333%;
        }
    }

    @media (max-width: 768px) {
        .col-lg-2-4 {
            width: 100%;
        }
    }
</style>

<div class="container-fluid py-3">
    <!-- Messages de succès -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Section 1: Statistiques générales -->
    <div class="stats-section">
        <div class="section-title">
            <i class="fas fa-chart-pie"></i> Statistiques Générales
        </div>
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h4><?= $stats['total'] ?></h4>
                    <p>Total Militaires</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-tags"></i></div>
                <div class="stat-info">
                    <h4><?= $stats['categories'] ?></h4>
                    <p>Total Catégories</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-map-pin"></i></div>
                <div class="stat-info">
                    <h4><?= $stats['garnisons'] ?></h4>
                    <p>Total Garnisons</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-map-marked-alt
"></i></div>
                <div class="stat-info">
                    <h4><?= $stats['provinces'] ?></h4>
                    <p>Total Provinces</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 1.5: Top catégories, garnisons et provinces -->
    <div class="stats-section">
        <div class="section-title">
            <i class="fas fa-chart-line"></i> Top Catégories, Garnisons & Provinces
        </div>
        <div class="row g-4">
            <!-- Top Catégories -->
            <div class="col-md-4">
                <div class="stat-card" style="flex-direction: column; align-items: flex-start; padding: 20px;">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; width: 100%;">
                        <div class="stat-icon blue" style="width: 45px; height: 45px; font-size: 1.2rem;">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 1.2rem;">Top Catégories</h4>
                            <p style="margin: 0; font-size: 0.8rem; color: #6c757d;">Les plus représentées</p>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <?php if (empty($top_categories)): ?>
                            <p class="text-muted text-center py-3">Aucune donnée disponible</p>
                        <?php else: ?>
                            <?php foreach ($top_categories as $index => $item):
                                $libelle = $categories_labels[$item['categorie']] ?? $item['categorie'];
                                $icone = getIconeCategorie($libelle);
                                $percentage = round(($item['total'] / $stats['total']) * 100, 1);
                                $colors = ['#2e7d32', '#1e4b8c', '#b85e00', '#6f42c1', '#20c997'];
                            ?>
                                <div style="margin-bottom: 12px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                        <span style="font-size: 0.85rem; font-weight: 500;">
                                            <?= $index + 1 ?>. <i class="fas <?= $icone ?> me-1"
                                                style="color: <?= $colors[$index] ?>;"></i>
                                            <?= htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span style="font-size: 0.85rem; font-weight: 600; color: <?= $colors[$index] ?>;">
                                            <?= $item['total'] ?> (<?= $percentage ?>%)
                                        </span>
                                    </div>
                                    <div style="height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                                        <div
                                            style="height: 100%; width: <?= $percentage ?>%; background: <?= $colors[$index] ?>; border-radius: 3px;">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Garnisons -->
            <div class="col-md-4">
                <div class="stat-card" style="flex-direction: column; align-items: flex-start; padding: 20px;">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; width: 100%;">
                        <div class="stat-icon orange" style="width: 45px; height: 45px; font-size: 1.2rem;">
                            <i class="fas fa-map-pin"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 1.2rem;">Top Garnisons</h4>
                            <p style="margin: 0; font-size: 0.8rem; color: #6c757d;">Les plus peuplées</p>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <?php if (empty($top_garnisons)): ?>
                            <p class="text-muted text-center py-3">Aucune donnée disponible</p>
                        <?php else: ?>
                            <?php foreach ($top_garnisons as $index => $item):
                                $percentage = round(($item['total'] / $stats['total']) * 100, 1);
                                $colors = ['#2e7d32', '#1e4b8c', '#b85e00', '#6f42c1', '#20c997'];
                            ?>
                                <div style="margin-bottom: 12px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                        <span style="font-size: 0.85rem; font-weight: 500; text-transform: uppercase;">
                                            <?= $index + 1 ?>. <?= htmlspecialchars($item['garnison'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span style="font-size: 0.85rem; font-weight: 600; color: <?= $colors[$index] ?>;">
                                            <?= $item['total'] ?> (<?= $percentage ?>%)
                                        </span>
                                    </div>
                                    <div style="height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                                        <div
                                            style="height: 100%; width: <?= $percentage ?>%; background: <?= $colors[$index] ?>; border-radius: 3px;">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Provinces -->
            <div class="col-md-4">
                <div class="stat-card" style="flex-direction: column; align-items: flex-start; padding: 20px;">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; width: 100%;">
                        <div class="stat-icon red" style="width: 45px; height: 45px; font-size: 1.2rem;">
                            <i class="fas fa-map-marked-alt
"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 1.2rem;">Top Provinces</h4>
                            <p style="margin: 0; font-size: 0.8rem; color: #6c757d;">Les plus représentées</p>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <?php if (empty($top_provinces)): ?>
                            <p class="text-muted text-center py-3">Aucune donnée disponible</p>
                        <?php else: ?>
                            <?php foreach ($top_provinces as $index => $item):
                                $percentage = round(($item['total'] / $stats['total']) * 100, 1);
                                $colors = ['#2e7d32', '#1e4b8c', '#b85e00', '#6f42c1', '#20c997'];
                            ?>
                                <div style="margin-bottom: 12px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                        <span style="font-size: 0.85rem; font-weight: 500; text-transform: uppercase;">
                                            <?= $index + 1 ?>. <?= htmlspecialchars($item['province'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span style="font-size: 0.85rem; font-weight: 600; color: <?= $colors[$index] ?>;">
                                            <?= $item['total'] ?> (<?= $percentage ?>%)
                                        </span>
                                    </div>
                                    <div style="height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                                        <div
                                            style="height: 100%; width: <?= $percentage ?>%; background: <?= $colors[$index] ?>; border-radius: 3px;">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Détails des catégories -->
    <div class="stats-section">
        <div class="section-title">
            <i class="fas fa-chart-bar"></i> Détails des Catégories
        </div>
        <div class="stats-container compact">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas <?= getIconeCategorie('Actif') ?>"></i></div>
                <div class="stat-info">
                    <h4><?= $stats['actifs'] ?></h4>
                    <p>Actifs</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas <?= getIconeCategorie('Retraité') ?>"></i></div>
                <div class="stat-info">
                    <h4><?= $stats['retraites'] ?></h4>
                    <p>Retraités</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas <?= getIconeCategorie('Intégré') ?>"></i></div>
                <div class="stat-info">
                    <h4><?= $stats['integres'] ?></h4>
                    <p>Intégrés</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gray"><i class="fas <?= getIconeCategorie('Décédé Avant Bio') ?>"></i></div>
                <div class="stat-info">
                    <h4><?= $stats['dcd_av_bio'] ?></h4>
                    <p>Décédés Avant Bio</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas <?= getIconeCategorie('Décédé Après Bio') ?>"></i></div>
                <div class="stat-info">
                    <h4><?= $stats['dcd_ap_bio'] ?></h4>
                    <p>Décédés Après Bio</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2.5: Top détails par catégorie -->
    <div class="stats-section">
        <div class="section-title">
            <i class="fas fa-chart-line"></i> Top par Catégorie (Grades les plus représentés)
        </div>
        <div class="row g-4">
            <!-- Top Actifs -->
            <div class="col-md-4 col-lg-2-4">
                <div class="stat-card" style="flex-direction: column; align-items: flex-start; padding: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; width: 100%;">
                        <div class="stat-icon green" style="width: 35px; height: 35px; font-size: 1rem;">
                            <i class="fas <?= getIconeCategorie('Actif') ?>"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 1rem;">Top Actifs</h4>
                            <p style="margin: 0; font-size: 0.7rem; color: #6c757d;">Grades</p>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <?php if (empty($top_actifs)): ?>
                            <p class="text-muted text-center py-2 small">Aucune donnée</p>
                        <?php else: ?>
                            <?php foreach ($top_actifs as $index => $item):
                                $percentage = round(($item['total'] / $stats['actifs']) * 100, 1);
                                $colors = ['#2e7d32', '#1e4b8c', '#b85e00', '#6f42c1', '#20c997'];
                            ?>
                                <div style="margin-bottom: 8px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                                        <span style="font-size: 0.75rem; font-weight: 500; text-transform: uppercase;">
                                            <?= $index + 1 ?>. <?= htmlspecialchars($item['grade'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span style="font-size: 0.7rem; font-weight: 600; color: <?= $colors[$index] ?>;">
                                            <?= $item['total'] ?> (<?= $percentage ?>%)
                                        </span>
                                    </div>
                                    <div style="height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden;">
                                        <div
                                            style="height: 100%; width: <?= $percentage ?>%; background: <?= $colors[$index] ?>; border-radius: 2px;">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Retraités -->
            <div class="col-md-4 col-lg-2-4">
                <div class="stat-card" style="flex-direction: column; align-items: flex-start; padding: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; width: 100%;">
                        <div class="stat-icon blue" style="width: 35px; height: 35px; font-size: 1rem;">
                            <i class="fas <?= getIconeCategorie('Retraité') ?>"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 1rem;">Top Retraités</h4>
                            <p style="margin: 0; font-size: 0.7rem; color: #6c757d;">Grades</p>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <?php if (empty($top_retraites)): ?>
                            <p class="text-muted text-center py-2 small">Aucune donnée</p>
                        <?php else: ?>
                            <?php foreach ($top_retraites as $index => $item):
                                $percentage = round(($item['total'] / $stats['retraites']) * 100, 1);
                                $colors = ['#2e7d32', '#1e4b8c', '#b85e00', '#6f42c1', '#20c997'];
                            ?>
                                <div style="margin-bottom: 8px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                                        <span style="font-size: 0.75rem; font-weight: 500; text-transform: uppercase;">
                                            <?= $index + 1 ?>. <?= htmlspecialchars($item['grade'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span style="font-size: 0.7rem; font-weight: 600; color: <?= $colors[$index] ?>;">
                                            <?= $item['total'] ?> (<?= $percentage ?>%)
                                        </span>
                                    </div>
                                    <div style="height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden;">
                                        <div
                                            style="height: 100%; width: <?= $percentage ?>%; background: <?= $colors[$index] ?>; border-radius: 2px;">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Intégrés -->
            <div class="col-md-4 col-lg-2-4">
                <div class="stat-card" style="flex-direction: column; align-items: flex-start; padding: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; width: 100%;">
                        <div class="stat-icon red" style="width: 35px; height: 35px; font-size: 1rem;">
                            <i class="fas <?= getIconeCategorie('Intégré') ?>"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 1rem;">Top Intégrés</h4>
                            <p style="margin: 0; font-size: 0.7rem; color: #6c757d;">Grades</p>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <?php if (empty($top_integres)): ?>
                            <p class="text-muted text-center py-2 small">Aucune donnée</p>
                        <?php else: ?>
                            <?php foreach ($top_integres as $index => $item):
                                $percentage = round(($item['total'] / $stats['integres']) * 100, 1);
                                $colors = ['#2e7d32', '#1e4b8c', '#b85e00', '#6f42c1', '#20c997'];
                            ?>
                                <div style="margin-bottom: 8px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                                        <span style="font-size: 0.75rem; font-weight: 500; text-transform: uppercase;">
                                            <?= $index + 1 ?>. <?= htmlspecialchars($item['grade'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span style="font-size: 0.7rem; font-weight: 600; color: <?= $colors[$index] ?>;">
                                            <?= $item['total'] ?> (<?= $percentage ?>%)
                                        </span>
                                    </div>
                                    <div style="height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden;">
                                        <div
                                            style="height: 100%; width: <?= $percentage ?>%; background: <?= $colors[$index] ?>; border-radius: 2px;">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Décédés Avant Bio -->
            <div class="col-md-4 col-lg-2-4">
                <div class="stat-card" style="flex-direction: column; align-items: flex-start; padding: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; width: 100%;">
                        <div class="stat-icon gray" style="width: 35px; height: 35px; font-size: 1rem;">
                            <i class="fas <?= getIconeCategorie('Décédé Avant Bio') ?>"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 1rem;">Top Décédés Avant Bio</h4>
                            <p style="margin: 0; font-size: 0.7rem; color: #6c757d;">Grades</p>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <?php if (empty($top_dcd_av_bio)): ?>
                            <p class="text-muted text-center py-2 small">Aucune donnée</p>
                        <?php else: ?>
                            <?php foreach ($top_dcd_av_bio as $index => $item):
                                $percentage = round(($item['total'] / $stats['dcd_av_bio']) * 100, 1);
                                $colors = ['#2e7d32', '#1e4b8c', '#b85e00', '#6f42c1', '#20c997'];
                            ?>
                                <div style="margin-bottom: 8px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                                        <span style="font-size: 0.75rem; font-weight: 500; text-transform: uppercase;">
                                            <?= $index + 1 ?>. <?= htmlspecialchars($item['grade'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span style="font-size: 0.7rem; font-weight: 600; color: <?= $colors[$index] ?>;">
                                            <?= $item['total'] ?> (<?= $percentage ?>%)
                                        </span>
                                    </div>
                                    <div style="height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden;">
                                        <div
                                            style="height: 100%; width: <?= $percentage ?>%; background: <?= $colors[$index] ?>; border-radius: 2px;">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Décédés Après Bio -->
            <div class="col-md-4 col-lg-2-4">
                <div class="stat-card" style="flex-direction: column; align-items: flex-start; padding: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; width: 100%;">
                        <div class="stat-icon purple"
                            style="width: 35px; height: 35px; font-size: 1rem; background: linear-gradient(135deg, #6f42c1 0%, #4e2a8a 100%);">
                            <i class="fas <?= getIconeCategorie('Décédé Après Bio') ?>"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 1rem;">Top Décédés Après Bio</h4>
                            <p style="margin: 0; font-size: 0.7rem; color: #6c757d;">Grades</p>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <?php if (empty($top_dcd_ap_bio)): ?>
                            <p class="text-muted text-center py-2 small">Aucune donnée</p>
                        <?php else: ?>
                            <?php foreach ($top_dcd_ap_bio as $index => $item):
                                $percentage = round(($item['total'] / $stats['dcd_ap_bio']) * 100, 1);
                                $colors = ['#2e7d32', '#1e4b8c', '#b85e00', '#6f42c1', '#20c997'];
                            ?>
                                <div style="margin-bottom: 8px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                                        <span style="font-size: 0.75rem; font-weight: 500; text-transform: uppercase;">
                                            <?= $index + 1 ?>. <?= htmlspecialchars($item['grade'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span style="font-size: 0.7rem; font-weight: 600; color: <?= $colors[$index] ?>;">
                                            <?= $item['total'] ?> (<?= $percentage ?>%)
                                        </span>
                                    </div>
                                    <div style="height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden;">
                                        <div
                                            style="height: 100%; width: <?= $percentage ?>%; background: <?= $colors[$index] ?>; border-radius: 2px;">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card modern-card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-users"></i> Tous les militaires</h3>
                    <div class="d-flex align-items-center gap-3">
                        <span class="total-badge"><i class="fas fa-database"></i> Total : <?= $stats['total'] ?></span>
                        <div class="export-buttons">
                            <button class="btn-export csv" id="export-csv"><i class="fas fa-file-csv"></i> CSV</button>
                            <button class="btn-export excel" id="export-excel"><i class="fas fa-file-excel"></i>
                                Excel</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filtres -->
                    <div class="filters-container mb-4"
                        style="background: #f8f9fa; padding: 15px; border-radius: 10px;">
                        <div class="row align-items-end g-2">
                            <div class="col-md-2">
                                <label for="categorie-filter" class="form-label fw-bold small" style="color: #2e7d32;">
                                    <i class="fas fa-tag"></i> Catégorie
                                </label>
                                <select id="categorie-filter" class="form-select form-select-sm">
                                    <option value="">Toutes</option>
                                    <?php foreach ($categories_list as $categorie):
                                        $libelle = $categories_labels[$categorie] ?? $categorie;
                                    ?>
                                        <option value="<?= htmlspecialchars($categorie, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="garnison-filter" class="form-label fw-bold small" style="color: #2e7d32;">
                                    <i class="fas fa-map-pin"></i> Garnison
                                </label>
                                <select id="garnison-filter" class="form-select form-select-sm">
                                    <option value="">Toutes</option>
                                    <?php foreach ($garnisons_list as $garnison): ?>
                                        <option value="<?= htmlspecialchars($garnison, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($garnison, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="province-filter" class="form-label fw-bold small" style="color: #2e7d32;">
                                    <i class="fas fa-map-marked-alt"></i> Province
                                </label>
                                <select id="province-filter" class="form-select form-select-sm">
                                    <option value="">Toutes</option>
                                    <?php foreach ($provinces_list as $province): ?>
                                        <option value="<?= htmlspecialchars($province, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= ($province == $selected_province) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($province, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="zdef-filter" class="form-label fw-bold small" style="color: #2e7d32;">
                                    <i class="fas fa-shield-alt"></i> Zone Défense
                                </label>
                                <select id="zdef-filter" class="form-select form-select-sm">
                                    <option value="">Toutes</option>
                                    <option value="1ZDef">1ZDef</option>
                                    <option value="2ZDef">2ZDef</option>
                                    <option value="3ZDef">3ZDef</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="statut-filter" class="form-label fw-bold small" style="color: #2e7d32;">
                                    <i class="fas fa-user-check"></i> Statut
                                </label>
                                <select id="statut-filter" class="form-select form-select-sm">
                                    <option value="">Tous</option>
                                    <option value="1">Actif</option>
                                    <option value="0">Inactif</option>
                                </select>
                            </div>
                            <div class="col-md-2 text-end">
                                <button id="reset-filters" class="btn-reset-modern w-100">
                                    <i class="fas fa-undo-alt"></i> Réinitialiser
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-toolbar-actions mb-3 d-flex justify-content-end gap-2 flex-wrap">
                        <a href="ajouter.php" class="btn-modern btn-primary-modern">
                            <i class="fas fa-user-plus"></i> Nouveau
                        </a>
                        <a href="importer.php" class="btn-modern btn-secondary-modern">
                            <i class="fas fa-file-import"></i> Importer
                        </a>
                    </div>

                    <!-- Tableau -->
                    <table id="table-militaires" class="table-militaires" style="width:100%">
                        <thead>
                            <tr>
                                <th>MATRICULE</th>
                                <th>NOMS</th>
                                <th>GRADE</th>
                                <th>UNITÉ</th>
                                <th>BÉNÉFICIAIRE</th>
                                <th>GARNISON</th>
                                <th>PROVINCE</th>
                                <th>CATÉGORIE</th>
                                <th>ZDEF</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Les données seront chargées via Ajax -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Scripts supplémentaires (exports) -->
<script src="../../assets/js/xlsx.full.min.js"></script>
<script src="../../assets/fontawesome/js/all.min.js"></script>

<script>
    $(document).ready(function() {
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
                timerProgressBar: true,
            });
        <?php endif; ?>

        // Ordre personnalisé des grades
        const gradeOrder = [
            'GENA', 'GAM', 'LTGEN', 'AMR', 'GENMAJ', 'VAM',
            'GENBDE', 'CAM', 'COL', 'CPV', 'LTCOL', 'CPF',
            'MAJ', 'CPC', 'CAPT', 'LDV', 'LT', 'EV', 'SLT', '2EV',
            'A-C', 'MCP', 'A-1', '1MC', 'ADJ', 'MRC', '1SM', '1MR',
            'SM', '2MR', '1SGT', 'MR', 'SGT', 'QMT', 'CPL', '1MT',
            '1CL', '2MT', '2CL', 'MT', 'REC', 'ASK', 'COMD'
        ];

        // ===== NOUVELLE FONCTION JS : retourne l'icône correspondant au libellé de catégorie =====
        function getIconeCategorie(libelle) {
            const icones = {
                'Actif': 'fa-user-check',
                'Intégré': 'fa-user-plus',
                'Retraité': 'fa-user-clock',
                'Décédé Après Bio': 'fa-skull',
                'Décédé Avant Bio': 'fa-skull-crossbones'
            };
            return icones[libelle] || 'fa-user-tag';
        }

        // Fonction pour calculer ZDEF
        function getZoneDefense(province) {
            if (!province) return '';

            province = province.toUpperCase().trim()
                .replace(/[ÉÈÊË]/g, 'E')
                .replace(/[ÂÄ]/g, 'A')
                .replace(/[ÎÏ]/g, 'I')
                .replace(/[ÔÖ]/g, 'O')
                .replace(/[ÛÜ]/g, 'U')
                .replace(/Ç/g, 'C');

            const zone1 = ['KWILU', 'KWANGO', 'MAI-NDOMBE', 'MAI NDOMBE', 'MAINDOMBE', 'KONGO-CENTRAL',
                'KONGO CENTRAL', 'KONGOCENTRAL', 'KINSHASA', 'EQUATEUR', 'ÉQUATEUR', 'MONGALA', 'NORD-UBANGI',
                'NORD UBANGI', 'NORDUBANGI', 'SUD-UBANGI', 'SUD UBANGI', 'SUDUBANGI', 'TSHUAPA'
            ];
            const zone2 = ['HAUT-KATANGA', 'HAUT KATANGA', 'HAUTKATANGA', 'HAUT-LOMAMI', 'HAUT LOMAMI',
                'HAUTLOMAMI', 'LUALABA', 'TANGANYIKA', 'KASAI', 'KASAÏ', 'KASAI-CENTRAL', 'KASAI CENTRAL',
                'KASAICENTRAL', 'KASAÏ-CENTRAL', 'KASAI-ORIENTAL', 'KASAI ORIENTAL', 'KASAIORIENTAL',
                'KASAÏ-ORIENTAL', 'SANKURU', 'LOMAMI'
            ];
            const zone3 = ['HAUT-UELE', 'HAUT UELE', 'HAUTUELE', 'BAS-UELE', 'BAS UELE', 'BASUELE', 'ITURI',
                'TSHOPO', 'NORD-KIVU', 'NORD KIVU', 'NORDKIVU', 'SUD-KIVU', 'SUD KIVU', 'SUDKIVU', 'MANIEMA'
            ];

            if (zone1.includes(province)) return '1ZDef';
            if (zone2.includes(province)) return '2ZDef';
            if (zone3.includes(province)) return '3ZDef';
            return '';
        }

        // Fonction de tri personnalisé pour les grades (pour le tri côté client)
        $.fn.dataTable.ext.order['grade-pre'] = function(data) {
            const grade = $(data).text().trim();
            const index = gradeOrder.indexOf(grade);
            return index >= 0 ? index : 999;
        };

        // Initialisation DataTables
        const table = $('#table-militaires').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json',
                search: '<i class="fas fa-search"></i>',
                lengthMenu: "Afficher _MENU_ éléments",
                info: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
                infoEmpty: "Aucun élément",
                infoFiltered: "(filtré de _MAX_ éléments)",
                zeroRecords: "Aucun enregistrement correspondant",
                paginate: {
                    first: "Premier",
                    previous: "Précédent",
                    next: "Suivant",
                    last: "Dernier"
                }
            },
            processing: true,
            serverSide: true,
            ajax: {
                url: 'ajax_get_militaires.php',
                type: 'POST',
                data: function(d) {
                    d.categorie = $('#categorie-filter').val();
                    d.garnison = $('#garnison-filter').val();
                    d.province = $('#province-filter').val();
                    d.zdef = $('#zdef-filter').val();
                    d.statut = $('#statut-filter').val();
                    d.search_value = d.search.value;

                    // Ajouter l'ordre personnalisé des grades pour le traitement côté serveur
                    d.grade_order = gradeOrder;

                    // Si le tri est sur la colonne grade (index 2), utiliser l'ordre personnalisé
                    if (d.order && d.order[0] && d.order[0].column == 2) {
                        d.order[0].custom_order = 'grade_custom';
                    }
                },
                dataSrc: function(json) {
                    // S'assurer que les données sont correctement encodées
                    return json.data;
                }
            },
            columns: [{
                    data: 'matricule',
                    className: 'uppercase-text',
                    render: function(data) {
                        return data ? escapeHtml(data.toUpperCase()) :
                            '<span class="null-value">NULL</span>';
                    }
                },
                {
                    data: 'noms',
                    className: 'uppercase-text',
                    render: function(data) {
                        return data ? escapeHtml(data.toUpperCase()) :
                            '<span class="null-value">NULL</span>';
                    }
                },
                {
                    data: 'grade',
                    className: 'uppercase-text',
                    render: function(data) {
                        return data ? escapeHtml(data.toUpperCase()) :
                            '<span class="null-value">NULL</span>';
                    },
                    type: 'grade' // Utiliser le type de tri personnalisé
                },
                {
                    data: 'unite',
                    className: 'uppercase-text',
                    render: function(data) {
                        return data ? escapeHtml(data.toUpperCase()) :
                            '<span class="null-value">NULL</span>';
                    }
                },
                {
                    data: 'beneficiaire',
                    className: 'uppercase-text',
                    render: function(data) {
                        return data ? escapeHtml(data.toUpperCase()) :
                            '<span class="null-value"></span>';
                    }
                },
                {
                    data: 'garnison',
                    className: 'uppercase-text',
                    render: function(data) {
                        return data ? escapeHtml(data.toUpperCase()) :
                            '<span class="null-value">NULL</span>';
                    }
                },
                {
                    data: 'province',
                    className: 'uppercase-text',
                    render: function(data) {
                        return data ? escapeHtml(data.toUpperCase()) :
                            '<span class="null-value">NULL</span>';
                    }
                },
                {
                    data: 'categorie',
                    className: 'uppercase-text',
                    render: function(data) {
                        if (!data) return '<span class="null-value">NULL</span>';
                        let libelle = data;
                        const labels = {
                            'ACTIF': 'Actif',
                            'RETRAITES': 'Retraité',
                            'INTEGRES': 'Intégré',
                            'DCD_AV_BIO': 'Décédé Avant Bio',
                            'DCD_AP_BIO': 'Décédé Après Bio'
                        };
                        libelle = labels[data] || data;
                        const icone = getIconeCategorie(libelle);
                        return '<span class="categorie-badge"><i class="fas ' + icone +
                            ' me-1"></i> ' + escapeHtml(libelle) + '</span>';
                    }
                },
                {
                    data: 'province',
                    className: 'uppercase-text',
                    render: function(data) {
                        const zdef = getZoneDefense(data);
                        if (!zdef) return '<span class="null-value">-</span>';
                        let zdefClass = '';
                        if (zdef === '1ZDef') zdefClass = 'zdef-badge-1';
                        else if (zdef === '2ZDef') zdefClass = 'zdef-badge-2';
                        else if (zdef === '3ZDef') zdefClass = 'zdef-badge-3';
                        return '<span class="zdef-badge ' + zdefClass + '">' + zdef + '</span>';
                    }
                }
            ],
            dom: 'rt<"datatable-bottom d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3"ip>',
            order: [
                [2, 'asc'] // Tri par défaut sur la colonne grade en ordre ascendant
            ],
            columnDefs: [{
                targets: [6, 7],
                visible: false,
                searchable: true
            }],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            scrollX: false,
            scrollY: false,
            scrollCollapse: false,
            autoWidth: true,
            initComplete: function() {
                $('.datatable-bottom').css({
                    'row-gap': '10px'
                });
            },
            drawCallback: function() {}
        });

        // Fonction pour échapper les caractères HTML
        function escapeHtml(text) {
            if (!text) return text;
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        }

        // Filtres
        $('#categorie-filter, #garnison-filter, #province-filter, #zdef-filter, #statut-filter').on('change',
            function() {
                table.ajax.reload();
            });

        $('#reset-filters').on('click', function() {
            $('#categorie-filter, #garnison-filter, #province-filter, #zdef-filter, #statut-filter').val(
                '');
            table.ajax.reload();
        });

        // ===== AJOUT : Si une province est passée dans l'URL, on applique le filtre =====
        let selectedProvince = <?= json_encode($selected_province) ?>;
        if (selectedProvince) {
            $('#province-filter').val(selectedProvince);
            table.ajax.reload();
        }

        // Exports
        function getExportData() {
            return new Promise((resolve, reject) => {
                const searchInput = $('.dataTables_filter input').val();

                $.ajax({
                    url: 'ajax_export_militaires.php',
                    method: 'POST',
                    data: {
                        categorie: $('#categorie-filter').val(),
                        garnison: $('#garnison-filter').val(),
                        province: $('#province-filter').val(),
                        zdef: $('#zdef-filter').val(),
                        statut: $('#statut-filter').val(),
                        search: searchInput,
                        selected: [],
                        order_column: table.order()[0][0],
                        order_dir: table.order()[0][1],
                        grade_order: gradeOrder // Envoyer l'ordre des grades pour le tri côté serveur
                    },
                    dataType: 'json',
                    success: function(response) {
                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur export:', error);
                        reject(error);
                    }
                });
            });
        }

        function getTimestamp() {
            const d = new Date();
            return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}_${String(d.getHours()).padStart(2,'0')}h${String(d.getMinutes()).padStart(2,'0')}`;
        }

        function escapeCsvValue(value) {
            if (value === null || value === undefined) return '';
            value = String(value);
            if (value.includes(';') || value.includes('"') || value.includes('\n')) {
                return '"' + value.replace(/"/g, '""') + '"';
            }
            return value;
        }

        $('#export-csv').on('click', async function() {
            try {
                // --- AJOUT LOG : journalisation de l'export CSV ---
                const filters = {
                    categorie: $('#categorie-filter').val(),
                    garnison: $('#garnison-filter').val(),
                    province: $('#province-filter').val(),
                    zdef: $('#zdef-filter').val(),
                    statut: $('#statut-filter').val()
                };
                $.get('?ajax=log_export', {
                    type: 'CSV',
                    filtres: JSON.stringify(filters)
                });
                // --- FIN AJOUT LOG ---

                Swal.fire({
                    title: 'Préparation de l\'export...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const {
                    headers,
                    data
                } = await getExportData();

                if (!data || data.length === 0) {
                    Swal.close();
                    Swal.fire('Aucune donnée',
                        'Veuillez sélectionner des lignes ou vérifier les filtres.', 'info');
                    return;
                }

                // Formatage CSV
                const csvRows = [];
                csvRows.push(headers.map(h => escapeCsvValue(h)).join(';'));

                // Trier les données selon l'ordre personnalisé des grades si nécessaire
                const sortedData = [...data];
                if (table.order()[0][0] === 3) {
                    sortedData.sort((a, b) => {
                        const gradeA = a[2] || '';
                        const gradeB = b[2] || '';
                        const indexA = gradeOrder.indexOf(gradeA);
                        const indexB = gradeOrder.indexOf(gradeB);

                        if (indexA === -1 && indexB === -1) return 0;
                        if (indexA === -1) return 1;
                        if (indexB === -1) return -1;

                        if (table.order()[0][1] === 'asc') {
                            return indexA - indexB;
                        } else {
                            return indexB - indexA;
                        }
                    });
                }

                sortedData.forEach(row => {
                    const escapedRow = row.map(cell => escapeCsvValue(cell)).join(';');
                    csvRows.push(escapedRow);
                });

                const csvContent = csvRows.join('\n');

                // Ajouter BOM UTF-8
                const blob = new Blob(["\uFEFF" + csvContent], {
                    type: 'text/csv;charset=utf-8;'
                });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `militaires_${getTimestamp()}.csv`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);

                Swal.close();

            } catch (error) {
                console.error('Erreur export CSV:', error);
                Swal.close();
                Swal.fire('Erreur', 'Une erreur est survenue lors de l\'export CSV', 'error');
            }
        });

        $('#export-excel').on('click', async function() {
            try {
                // --- AJOUT LOG : journalisation de l'export Excel ---
                const filters = {
                    categorie: $('#categorie-filter').val(),
                    garnison: $('#garnison-filter').val(),
                    province: $('#province-filter').val(),
                    zdef: $('#zdef-filter').val(),
                    statut: $('#statut-filter').val()
                };
                $.get('?ajax=log_export', {
                    type: 'Excel',
                    filtres: JSON.stringify(filters)
                });
                // --- FIN AJOUT LOG ---

                Swal.fire({
                    title: 'Préparation de l\'export...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const {
                    headers,
                    data
                } = await getExportData();

                if (!data || data.length === 0) {
                    Swal.close();
                    Swal.fire('Aucune donnée',
                        'Veuillez sélectionner des lignes ou vérifier les filtres.', 'info');
                    return;
                }

                // Trier les données selon l'ordre personnalisé des grades si nécessaire
                const sortedData = [...data];
                if (table.order()[0][0] === 3) {
                    sortedData.sort((a, b) => {
                        const gradeA = a[2] || '';
                        const gradeB = b[2] || '';
                        const indexA = gradeOrder.indexOf(gradeA);
                        const indexB = gradeOrder.indexOf(gradeB);

                        if (indexA === -1 && indexB === -1) return 0;
                        if (indexA === -1) return 1;
                        if (indexB === -1) return -1;

                        if (table.order()[0][1] === 'asc') {
                            return indexA - indexB;
                        } else {
                            return indexB - indexA;
                        }
                    });
                }

                const wb = XLSX.utils.book_new();
                const wsData = [headers, ...sortedData];
                const ws = XLSX.utils.aoa_to_sheet(wsData);

                const colWidths = headers.map(h => ({
                    wch: Math.max(h.length, 15)
                }));
                ws['!cols'] = colWidths;

                XLSX.utils.book_append_sheet(wb, ws, 'Militaires');

                const wbout = XLSX.write(wb, {
                    bookType: 'xlsx',
                    type: 'array'
                });

                const blob = new Blob([wbout], {
                    type: 'application/octet-stream'
                });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `militaires_${getTimestamp()}.xlsx`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);

                Swal.close();

            } catch (error) {
                console.error('Erreur export Excel:', error);
                Swal.close();
                Swal.fire('Erreur', 'Une erreur est survenue lors de l\'export Excel', 'error');
            }
        });
    });
</script>

<?php
// --- AJOUT LOG : journalisation de la consultation de la page ---
audit_action('CONSULTATION', 'militaires', null, 'Consultation de la liste complète des militaires');
// --- FIN AJOUT LOG ---
?>