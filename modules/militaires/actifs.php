<!DOCTYPE html>
<?php
header('Content-Type: text/html; charset=utf-8');
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

$statut_cible = 1;

$success_message = null;
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$page_titre = 'Liste des militaires actifs';
$breadcrumb = ['Militaires actifs' => '#'];
include '../../includes/header.php';

$grade_order = [
    'GENA', 'GAM', 'LTGEN', 'AMR', 'GENMAJ', 'VAM', 'GENBDE', 'CAM', 'COL', 'CPV',
    'LTCOL', 'CPF', 'MAJ', 'CPC', 'CAPT', 'LDV', 'LT', 'EV', 'SLT', '2EV', 'A-C', 'MCP',
    'A-1', '1MC', 'ADJ', 'MRC', '1SM', '1MR', 'SM', '2MR', '1SGT', 'MR', 'SGT', 'QMT',
    'CPL', '1MT', '1CL', '2MT', '2CL', 'MT', 'REC', 'ASK', 'COMD'
];

// Compter le nombre total de militaires avec le statut cible
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM militaires WHERE statut = ?");
$countStmt->execute([$statut_cible]);
$total_militaires = $countStmt->fetchColumn();

// Statistiques simplifiées (uniquement ce qui concerne les actifs)
$stats = [
    'total'      => $total_militaires,
    'categories' => $pdo->prepare("SELECT COUNT(DISTINCT categorie) FROM militaires WHERE statut = ? AND categorie IS NOT NULL AND categorie != ''")->execute([$statut_cible]) ? $pdo->query("SELECT COUNT(DISTINCT categorie) FROM militaires WHERE statut = $statut_cible AND categorie IS NOT NULL AND categorie != ''")->fetchColumn() : 0,
    'garnisons'  => $pdo->prepare("SELECT COUNT(DISTINCT garnison) FROM militaires WHERE statut = ? AND garnison IS NOT NULL AND garnison != ''")->execute([$statut_cible]) ? $pdo->query("SELECT COUNT(DISTINCT garnison) FROM militaires WHERE statut = $statut_cible AND garnison IS NOT NULL AND garnison != ''")->fetchColumn() : 0,
    'provinces'  => $pdo->prepare("SELECT COUNT(DISTINCT province) FROM militaires WHERE statut = ? AND province IS NOT NULL AND province != ''")->execute([$statut_cible]) ? $pdo->query("SELECT COUNT(DISTINCT province) FROM militaires WHERE statut = $statut_cible AND province IS NOT NULL AND province != ''")->fetchColumn() : 0,
    'actifs'     => $total_militaires,
];

// Listes pour les filtres (filtrées par statut)
$categories_list = $pdo->prepare("SELECT DISTINCT categorie FROM militaires WHERE statut = ? AND categorie IS NOT NULL AND categorie != '' ORDER BY categorie");
$categories_list->execute([$statut_cible]);
$categories_list = $categories_list->fetchAll(PDO::FETCH_COLUMN);

$garnisons_list = $pdo->prepare("SELECT DISTINCT garnison FROM militaires WHERE statut = ? AND garnison IS NOT NULL AND garnison != '' ORDER BY garnison");
$garnisons_list->execute([$statut_cible]);
$garnisons_list = $garnisons_list->fetchAll(PDO::FETCH_COLUMN);

$provinces_list = $pdo->prepare("SELECT DISTINCT province FROM militaires WHERE statut = ? AND province IS NOT NULL AND province != '' ORDER BY province");
$provinces_list->execute([$statut_cible]);
$provinces_list = $provinces_list->fetchAll(PDO::FETCH_COLUMN);

$categories_labels = [
    'ACTIF'       => 'Actif',
    'RETRAITES'   => 'Retraité',
    'INTEGRES'    => 'Intégré',
    'DCD_AV_BIO'  => 'Décédé Avant Bio',
    'DCD_AP_BIO'  => 'Décédé Après Bio'
];

// ===== FONCTION D'ICÔNES POUR LES CATÉGORIES =====
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

// Tops généraux (catégories, garnisons, provinces)
$top_categories = $pdo->prepare("
    SELECT categorie, COUNT(*) as total 
    FROM militaires 
    WHERE statut = ? AND categorie IS NOT NULL AND categorie != '' 
    GROUP BY categorie 
    ORDER BY total DESC 
    LIMIT 5
");
$top_categories->execute([$statut_cible]);
$top_categories = $top_categories->fetchAll();

$top_garnisons = $pdo->prepare("
    SELECT garnison, COUNT(*) as total 
    FROM militaires 
    WHERE statut = ? AND garnison IS NOT NULL AND garnison != '' 
    GROUP BY garnison 
    ORDER BY total DESC 
    LIMIT 5
");
$top_garnisons->execute([$statut_cible]);
$top_garnisons = $top_garnisons->fetchAll();

$top_provinces = $pdo->prepare("
    SELECT province, COUNT(*) as total 
    FROM militaires 
    WHERE statut = ? AND province IS NOT NULL AND province != '' 
    GROUP BY province 
    ORDER BY total DESC 
    LIMIT 5
");
$top_provinces->execute([$statut_cible]);
$top_provinces = $top_provinces->fetchAll();

// Top des grades parmi les actifs (uniquement celui-ci)
$top_actifs = $pdo->prepare("
    SELECT grade, COUNT(*) as total 
    FROM militaires 
    WHERE statut = ? 
    GROUP BY grade 
    ORDER BY total DESC 
    LIMIT 5
");
$top_actifs->execute([$statut_cible]);
$top_actifs = $top_actifs->fetchAll();

$timestamp = date('Y-m-d_H\hi');
?>

<style>
/* ========== STYLES AVEC BOUTONS NOUVEAU/IMPORTER/RÉINITIALISER ========== */
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
    /* Suppression de l'animation de survol : cartes fixes */
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
        overflow-x: auto !important;
        overflow-y: visible !important;
}

.col-lg-2-4 {
    flex: 0 0 auto;
    width: 20%;
}

/* Responsive pour les petits écrans */
@media (max-width: 1200px) {
    .col-lg-2-4 {
        width: 33.333%;
    }
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
    .col-lg-2-4 {
        width: 100%;
    }

    .dataTables_wrapper .dataTables_filter .action-buttons {
        flex-direction: column;
        width: 100%;
    }

    .btn-modern {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="container-fluid py-3">
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Section 1: Statistiques générales -->
    <div class="stats-section">
        <div class="section-title">
            <i class="fas fa-chart-pie"></i> Statistiques Générales (Actifs)
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

    <!-- Section 2: Détails des catégories (simplifiée : seulement Actifs) -->
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
            <!-- Les autres catégories (retraités, intégrés, décédés) ne sont pas affichées car inexistantes chez les actifs -->
        </div>
    </div>

    <!-- Section 2.5: Top par Catégorie (uniquement Top Actifs) -->
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
            <!-- Les autres tops (retraités, intégrés, décédés) sont supprimés -->
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card modern-card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-users"></i> Militaires actifs</h3>
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
                    <!-- Filtres (sans catégorie) -->
                    <div class="filters-container mb-4"
                        style="background: #f8f9fa; padding: 15px; border-radius: 10px;">
                        <div class="row align-items-end g-2">
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
                                    <option value="<?= htmlspecialchars($province, ENT_QUOTES, 'UTF-8') ?>">
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
                            <div class="col-md-4 text-end">
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
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts complémentaires -->
<!-- JQuery en premier -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

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
        timerProgressBar: true
    });
    <?php endif; ?>

    const gradeOrder = [
        'GENA', 'GAM', 'LTGEN', 'AMR', 'GENMAJ', 'VAM', 'GENBDE', 'CAM', 'COL', 'CPV', 'LTCOL', 'CPF',
        'MAJ', 'CPC', 'CAPT', 'LDV', 'LT', 'EV', 'SLT', '2EV', 'A-C', 'MCP', 'A-1', '1MC', 'ADJ', 'MRC',
        '1SM', '1MR', 'SM', '2MR', '1SGT', 'MR', 'SGT', 'QMT', 'CPL', '1MT', '1CL', '2MT', '2CL', 'MT',
        'REC', 'ASK', 'COMD'
    ];

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

    function getZoneDefense(province) {
        if (!province) return '';
        province = province.toUpperCase().trim()
            .replace(/[ÉÈÊË]/g, 'E').replace(/[ÂÄ]/g, 'A')
            .replace(/[ÎÏ]/g, 'I').replace(/[ÔÖ]/g, 'O')
            .replace(/[ÛÜ]/g, 'U').replace(/Ç/g, 'C');
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

    $.fn.dataTable.ext.order['grade-pre'] = function(data) {
        const grade = $(data).text().trim();
        const index = gradeOrder.indexOf(grade);
        return index >= 0 ? index : 999;
    };

    const tableSelector = '#table-militaires';
    if ($.fn.dataTable.isDataTable(tableSelector)) {
        $(tableSelector).DataTable().clear().destroy();
        $(tableSelector + ' tbody').empty();
    }

    const table = $(tableSelector).DataTable({
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
        processing: true,
        serverSide: true,
        ajax: {
            url: 'ajax_get_militaires.php',
            type: 'POST',
            data: function(d) {
                d.garnison = $('#garnison-filter').val();
                d.province = $('#province-filter').val();
                d.zdef = $('#zdef-filter').val();
                d.statut = 1; // valeur fixe pour les actifs
                d.search_value = d.search.value;
                d.grade_order = gradeOrder;
                if (d.order && d.order[0] && d.order[0].column == 2) {
                    d.order[0].custom_order = 'grade_custom';
                }
            },
            dataSrc: function(json) {
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
                type: 'grade'
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
            [2, 'asc']
        ],
        columnDefs: [{
            targets: [6, 7],
            visible: false,
            searchable: true
        }],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        autoWidth: false,
        initComplete: function() {
            const api = this.api();
            $('.datatable-bottom').css({
                'row-gap': '10px'
            });
            window.requestAnimationFrame(function() {
                api.columns.adjust();
            });
        },
        drawCallback: function() {}
    });

    function escapeHtml(text) {
        if (!text) return text;
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    $('#garnison-filter, #province-filter, #zdef-filter').on('change', function() {
        table.ajax.reload();
    });
    $('#reset-filters').on('click', function() {
        $('#garnison-filter, #province-filter, #zdef-filter').val('');
        table.ajax.reload();
    });

    // Exports
    function getExportData() {
        return new Promise((resolve, reject) => {
            const searchInput = $('.dataTables_filter input').val();
            $.ajax({
                url: 'ajax_export_militaires.php',
                method: 'POST',
                data: {
                    garnison: $('#garnison-filter').val(),
                    province: $('#province-filter').val(),
                    zdef: $('#zdef-filter').val(),
                    statut: 1,
                    search: searchInput,
                    selected: [],
                    order_column: table.order()[0][0],
                    order_dir: table.order()[0][1],
                    grade_order: gradeOrder
                },
                dataType: 'json',
                success: resolve,
                error: (xhr, status, error) => {
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
        if (value.includes(';') || value.includes('"') || value.includes('\n')) return '"' + value.replace(/"/g,
            '""') + '"';
        return value;
    }

    $('#export-csv').on('click', async function() {
        try {
            const filters = {
                garnison: $('#garnison-filter').val(),
                province: $('#province-filter').val(),
                zdef: $('#zdef-filter').val()
            };
            $.get('?ajax=log_export', {
                type: 'CSV',
                filtres: JSON.stringify(filters)
            });

            Swal.fire({
                title: 'Préparation...',
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
            const csvRows = [];
            csvRows.push(headers.map(h => escapeCsvValue(h)).join(';'));
            const sortedData = [...data];
            if (table.order()[0][0] === 3) {
                sortedData.sort((a, b) => {
                    const gradeA = a[2] || '',
                        gradeB = b[2] || '';
                    const indexA = gradeOrder.indexOf(gradeA),
                        indexB = gradeOrder.indexOf(gradeB);
                    if (indexA === -1 && indexB === -1) return 0;
                    if (indexA === -1) return 1;
                    if (indexB === -1) return -1;
                    return table.order()[0][1] === 'asc' ? indexA - indexB : indexB -
                    indexA;
                });
            }
            sortedData.forEach(row => csvRows.push(row.map(cell => escapeCsvValue(cell)).join(
            ';')));
            const blob = new Blob(["\uFEFF" + csvRows.join('\n')], {
                type: 'text/csv;charset=utf-8;'
            });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `militaires_actifs_${getTimestamp()}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
            Swal.close();
        } catch (e) {
            console.error(e);
            Swal.close();
            Swal.fire('Erreur', 'Une erreur est survenue', 'error');
        }
    });

    $('#export-excel').on('click', async function() {
        try {
            const filters = {
                garnison: $('#garnison-filter').val(),
                province: $('#province-filter').val(),
                zdef: $('#zdef-filter').val()
            };
            $.get('?ajax=log_export', {
                type: 'Excel',
                filtres: JSON.stringify(filters)
            });

            Swal.fire({
                title: 'Préparation...',
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
            const sortedData = [...data];
            if (table.order()[0][0] === 3) {
                sortedData.sort((a, b) => {
                    const gradeA = a[2] || '',
                        gradeB = b[2] || '';
                    const indexA = gradeOrder.indexOf(gradeA),
                        indexB = gradeOrder.indexOf(gradeB);
                    if (indexA === -1 && indexB === -1) return 0;
                    if (indexA === -1) return 1;
                    if (indexB === -1) return -1;
                    return table.order()[0][1] === 'asc' ? indexA - indexB : indexB -
                    indexA;
                });
            }
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet([headers, ...sortedData]);
            ws['!cols'] = headers.map(h => ({
                wch: Math.max(h.length, 15)
            }));
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
            link.download = `militaires_actifs_${getTimestamp()}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
            Swal.close();
        } catch (e) {
            console.error(e);
            Swal.close();
            Swal.fire('Erreur', 'Une erreur est survenue', 'error');
        }
    });
});
</script>

<?php
audit_action('CONSULTATION', 'militaires', null, 'Consultation de la liste des militaires actifs');
include '../../includes/footer.php';
?>