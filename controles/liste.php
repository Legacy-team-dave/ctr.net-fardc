<?php
// ============================================
// LISTE DES CONTRÔLES - PAGE PRINCIPALE
// ============================================

// --- INITIALISATION ---
require_once '../../includes/functions.php';
require_login();

// --- AJAX : journalisation des exports ---
// --- AJAX : journalisation des exports ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'log_export') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? '';
    $filtres = $_GET['filtres'] ?? '';
    if ($type) {
        $details = "Export $type" . ($filtres ? " avec filtres: $filtres" : "");
        audit_action('EXPORT', 'controles', null, $details);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// --- AJAX : statistiques dynamiques ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'stats') {
    // On recalcule les stats à jour
    $stmt = $pdo->query("
        SELECT c.*, m.noms as nom_militaire, m.grade, m.statut as militaire_statut, 
            m.categorie as militaire_categorie, m.province, m.unite, m.garnison
        FROM controles c
        INNER JOIN militaires m ON c.matricule = m.matricule
        ORDER BY c.cree_le DESC
    ");
    $controles = $stmt->fetchAll();
    $total_controles = count($controles);
    $total_presents = count(array_filter($controles, fn($c) => $c['mention'] === 'Présent'));
    $total_favorables = count(array_filter($controles, fn($c) => $c['mention'] === 'Favorable'));
    $total_defavorables = count(array_filter($controles, fn($c) => $c['mention'] === 'Défavorable'));
    echo json_encode([
        'success' => true,
        'total_controles' => $total_controles,
        'total_presents' => $total_presents,
        'total_favorables' => $total_favorables,
        'total_defavorables' => $total_defavorables
    ]);
    exit;
}

// --- NETTOYAGE SESSION ---
unset($_SESSION['success_message'], $_SESSION['success_type']);

// --- VARIABLES GLOBALES ---
$user_profil = $_SESSION['user_profil'] ?? '';
$csrf_token = generate_csrf_token();
$page_titre = 'Liste des contrôles';
$breadcrumb = ['Contrôles' => '#'];
include '../../includes/header.php';

// --- RÉCUPÉRATION DES DONNÉES ---
$stmt = $pdo->query("
    SELECT c.*, m.noms as nom_militaire, m.grade, m.statut as militaire_statut, 
        m.categorie as militaire_categorie, m.province, m.unite, m.garnison
    FROM controles c
    INNER JOIN militaires m ON c.matricule = m.matricule
    ORDER BY c.cree_le DESC
");
$controles = $stmt->fetchAll();

// --- STATISTIQUES ---
$total_controles = count($controles);
$total_presents = count(array_filter($controles, fn($c) => $c['mention'] === 'Présent'));
$total_favorables = count(array_filter($controles, fn($c) => $c['mention'] === 'Favorable'));
$total_defavorables = count(array_filter($controles, fn($c) => $c['mention'] === 'Défavorable'));

// --- LISTES POUR FILTRES ---
$mentions = array_values(array_unique(array_filter(array_column($controles, 'mention'))));
sort($mentions);

$garnisons = array_values(array_unique(array_filter(array_column($controles, 'garnison'))));
sort($garnisons);

$categories_list = [
    'ACTIF' => 'Actif',
    'RETRAITES' => 'Retraité',
    'INTEGRES' => 'Intégré',
    'DCD_AV_BIO' => 'Décédé Avant Bio',
    'DCD_AP_BIO' => 'Décédé Après Bio'
];

$zones_defense = [
    '1ZDEF' => '1ZDef',
    '2ZDEF' => '2ZDef',
    '3ZDEF' => '3ZDef'
];

$liens_tuteur = ['Frère', 'Sœur', 'Père', 'Mère'];

function formatLienParente($lien)
{
    global $liens_tuteur;
    return empty($lien) ? null : (in_array($lien, $liens_tuteur) ? 'Tuteur' : $lien);
}

$timestamp = date('Y-m-d_H\hi');

// --- CHAMPS POUR EXPORT ---
$export_fields = [
    'serie'               => ['label' => 'Série', 'enabled' => true, 'required' => true],
    'matricule'           => ['label' => 'Matricule', 'enabled' => true, 'required' => true],
    'noms'                => ['label' => 'Noms', 'enabled' => true, 'required' => true],
    'grade'               => ['label' => 'Grade', 'enabled' => true, 'required' => true],
    'ancien_beneficiaire' => ['label' => 'Ancien bénéficiaire', 'enabled' => true, 'required' => false],
    'beneficiaire'        => ['label' => 'Bénéficiaire', 'enabled' => true, 'required' => false],
    'lien_parente'        => ['label' => 'Lien parenté', 'enabled' => true, 'required' => false],
    'unite'               => ['label' => 'Unité', 'enabled' => true, 'required' => false],
    'garnison'            => ['label' => 'Garnison', 'enabled' => true, 'required' => false],
    'province'            => ['label' => 'Province', 'enabled' => true, 'required' => false],
    'categorie'           => ['label' => 'Catégorie', 'enabled' => true, 'required' => false],
    'mention'             => ['label' => 'Mention', 'enabled' => true, 'required' => false],
    'observations'        => ['label' => 'Observations', 'enabled' => true, 'required' => false],
    'zdef'                => ['label' => 'ZDEF', 'enabled' => true, 'required' => false],
    'date_controle'       => ['label' => 'Date contrôle', 'enabled' => true, 'required' => false]
];
?>

<!-- ============================================ -->
<!-- STYLES CSS -->
<!-- ============================================ -->
<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
<style>
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

.btn-modern {
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
    text-decoration: none;
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

.btn-reset-modern {
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    transition: all 0.3s;
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
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: white;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-export i {
    font-size: 0.9rem;
}

.btn-export.csv { background: #28a745; }
.btn-export.csv:hover { background: #218838; transform: translateY(-2px); }

.btn-export.excel { background: #1e7e34; }
.btn-export.excel:hover { background: #19692c; transform: translateY(-2px); }

.btn-export.pdf { background: #dc3545; }
.btn-export.pdf:hover { background: #c82333; transform: translateY(-2px); }

.btn-export.zip { background: #9b59b6; }
.btn-export.zip:hover { background: #8e44ad; transform: translateY(-2px); }

.btn-export.choisir { background: #6c757d; }
.btn-export.choisir:hover { background: #5a6268; transform: translateY(-2px); }

.btn-export.sync { background: #17a2b8; }
.btn-export.sync:hover { background: #138496; transform: translateY(-2px); }

.total-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
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
    width: 100%;
}

.table-militaires {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

.table-militaires thead th {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
    padding: 12px 15px;
    text-transform: uppercase;
}

.table-militaires thead th:first-child {
    border-radius: 10px 0 0 10px;
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
    font-size: 0.9rem;
    vertical-align: middle;
}

.table-militaires tbody td:first-child {
    border-radius: 10px 0 0 10px;
}

.table-militaires tbody td:last-child {
    border-radius: 0 10px 10px 10px;
}

.matricule-with-eye {
    display: flex;
    align-items: center;
    gap: 8px;
}

.matricule-with-eye i {
    color: #2e7d32;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
}

.matricule-with-eye i:hover {
    color: #ffc107;
    transform: scale(1.1);
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

.stat-icon.present { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); }
.stat-icon.favorable { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); }
.stat-icon.defavorable { background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%); }

.stat-info h4 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #2e7d32;
}

.stat-info p {
    margin: 0;
    font-size: 0.9rem;
    color: #6c757d;
}

/* DataTables */
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
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 6px 12px;
}

.dataTables_wrapper .dataTables_filter {
    float: right;
    margin-bottom: 20px;
    display: flex !important;
    align-items: center;
    gap: 10px;
}

.dataTables_wrapper .dataTables_filter label {
    font-weight: 500;
    color: #2e7d32;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 5px;
}

.dataTables_wrapper .dataTables_filter input {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 6px 12px;
    width: 250px;
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

/* Modal */
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
    margin: 5% auto;
    padding: 20px;
    border-radius: 15px;
    width: 90%;
    max-width: 700px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    animation: slideDown 0.3s ease;
}

.modal-champs-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #2e7d32;
    padding-bottom: 10px;
}

.modal-champs-header h4 {
    color: #2e7d32;
    margin: 0;
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
}

.modal-champs-close:hover {
    color: #dc3545;
}

.modal-champs-body .champs-section {
    margin-bottom: 15px;
}

.modal-champs-body .champs-section-title {
    font-weight: 600;
    color: #495057;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid #dee2e6;
}

.modal-champs-body .champs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.modal-champs-body .champ-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px;
    background: #f8f9fa;
    border-radius: 5px;
    transition: background 0.3s;
}

.modal-champs-body .champ-item:hover {
    background: #e9ecef;
}

.modal-champs-body .champ-item.required {
    background: #e8f5e9;
    border-left: 3px solid #2e7d32;
}

.modal-champs-body .champ-item.required label {
    font-weight: 600;
    color: #2e7d32;
}

.modal-champs-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.modal-champs-footer button {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 500;
}

.modal-champs-footer .btn-annuler { background: #6c757d; color: white; }
.modal-champs-footer .btn-annuler:hover { background: #5a6268; transform: translateY(-2px); }
.modal-champs-footer .btn-confirmer { background: #2e7d32; color: white; }
.modal-champs-footer .btn-confirmer:hover { background: #1b5e20; transform: translateY(-2px); }

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (max-width: 768px) {
    .modern-card .card-header { flex-direction: column; align-items: flex-start; }
    .stats-container { flex-direction: column; }
    .filters-row { flex-direction: column; }
    .dataTables_wrapper .dataTables_filter { float: none; margin-bottom: 20px; }
    .dataTables_wrapper .dataTables_filter input { width: 100%; }
    .modal-champs-content { width: 95%; margin: 10% auto; }
}
</style>

<div class="container-fluid py-3">

    <!-- Statistiques -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-info">
                <h4><?= $total_controles ?></h4>
                <p>Total contrôlés</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon present"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h4><?= $total_presents ?></h4>
                <p>Présents</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon favorable"><i class="fas fa-thumbs-up"></i></div>
            <div class="stat-info">
                <h4><?= $total_favorables ?></h4>
                <p>Favorables</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon defavorable"><i class="fas fa-thumbs-down"></i></div>
            <div class="stat-info">
                <h4><?= $total_defavorables ?></h4>
                <p>Défavorables</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card modern-card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Tous les contrôles</h3>
                    <div class="d-flex align-items-center gap-3">
                        <span class="total-badge"><i class="fas fa-database"></i> Total : <?= count($controles) ?></span>
                        <div class="export-buttons">
                            <button type="button" class="btn-export choisir" id="choisir-champs"><i class="fas fa-sliders-h"></i> Champs</button>
                            <button type="button" class="btn-export csv" id="export-csv"><i class="fas fa-file-csv"></i> CSV</button>
                            <button type="button" class="btn-export excel" id="export-excel"><i class="fas fa-file-excel"></i> Excel</button>
                            <button type="button" class="btn-export pdf" id="export-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                            <button type="button" class="btn-export zip" id="export-zip"><i class="fas fa-file-archive"></i> ZIP</button>
                            <?php if (in_array($user_profil, ['ADMIN_IG', 'OPERATEUR'])): ?>
                            <a href="sync.php" class="btn-export sync">
                                <i class="fas fa-cloud-upload-alt"></i> Synchronisation
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filtres -->
                    <div class="filters-row">
                        <div class="filter-item">
                            <label class="form-label"><i class="fas fa-tag"></i> Catégorie</label>
                            <select id="categorie-filter" class="form-select">
                                <option value="">Toutes</option>
                                <?php foreach ($categories_list as $code => $libelle): ?>
                                    <option value="<?= $code ?>"><?= $libelle ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label"><i class="fas fa-user-check"></i> Statut</label>
                            <select id="statut-filter" class="form-select">
                                <option value="">Tous</option>
                                <option value="1">Actif</option>
                                <option value="0">Inactif</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label"><i class="fas fa-star"></i> Mention</label>
                            <select id="mention-filter" class="form-select">
                                <option value="">Toutes</option>
                                <?php foreach ($mentions as $mention): ?>
                                    <option value="<?= htmlspecialchars($mention) ?>"><?= htmlspecialchars($mention) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label"><i class="fas fa-map-pin"></i> Garnison</label>
                            <select id="garnison-filter" class="form-select">
                                <option value="">Toutes</option>
                                <?php foreach ($garnisons as $garnison): ?>
                                    <option value="<?= htmlspecialchars($garnison) ?>"><?= htmlspecialchars($garnison) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label"><i class="fas fa-shield-alt"></i> Zone défense</label>
                            <select id="zone-filter" class="form-select">
                                <option value="">Toutes</option>
                                <?php foreach ($zones_defense as $code => $libelle): ?>
                                    <option value="<?= $code ?>"><?= $libelle ?></option>
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
                            <button id="reset-filters" class="btn-reset-modern"><i class="fas fa-undo-alt"></i> Réinitialiser</button>
                        </div>
                    </div>

                    <!-- Tags des filtres actifs -->
                    <div class="filtre-tags mb-3" style="display: none;"></div>

                    <!-- Tableau -->
                    <table id="table-controles" class="table-militaires" style="width:100%">
                        <thead>
                            <th>Matricule</th>
                            <th>Noms</th>
                            <th>Grade</th>
                            <th>Unité</th>
                            <th>OBN</th>
                            <th>ZDEF</th>
                            <th style="display:none;">Date</th>
                        </thead>
                        <tbody>
                            <?php foreach ($controles as $c):
                                $zdef = getZdefValue($c['province'] ?? '');
                                $observations = strtoupper($c['observations'] ?? '');
                            ?>
                                <tr data-id="<?= $c['id'] ?>"
                                    data-zone="<?= $zdef['code'] ?>"
                                    data-mention="<?= strtoupper($c['mention'] ?? '') ?>"
                                    data-categorie="<?= strtoupper($c['militaire_categorie'] ?? '') ?>"
                                    data-statut="<?= $c['militaire_statut'] ?? '' ?>"
                                    data-garnison="<?= strtoupper($c['garnison'] ?? '') ?>"
                                    data-date-order="<?= $c['date_controle'] ?? '' ?>"
                                    data-ancien-beneficiaire="<?= strtoupper($c['nom_beneficiaire'] ?? '') ?>"
                                    data-beneficiaire="<?= strtoupper($c['new_beneficiaire'] ?? '') ?>"
                                    data-lien-parente="<?= strtoupper(formatLienParente($c['lien_parente'] ?? '')) ?>"
                                    data-province="<?= strtoupper($c['province'] ?? '') ?>"
                                    data-date-controle="<?= $c['date_controle'] ?? '' ?>">
                                    <td>
                                        <div class="matricule-with-eye">
                                            <i class="fas fa-eye" onclick="window.location.href='voir.php?id=<?= urlencode($c['id']) ?>'"></i>
                                            <strong><?= mb_strtoupper((string)($c['matricule'] ?? ''), 'UTF-8') ?></strong>
                                        </div>
                                    </td>
                                    <td><?= mb_strtoupper((string)($c['nom_militaire'] ?? ''), 'UTF-8') ?></td>
                                    <td><?= mb_strtoupper((string)($c['grade'] ?? ''), 'UTF-8') ?></td>
                                    <td><?= mb_strtoupper((string)($c['unite'] ?? ''), 'UTF-8') ?></td>
                                    <td><?= mb_strtoupper((string)($observations ?? ''), 'UTF-8') ?></td>
                                    <td><?= mb_strtoupper((string)($zdef['value'] ?? ''), 'UTF-8') ?></td>
                                    <td style="display:none;"><?= $c['date_controle'] ?? '' ?></td>
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
                <div class="champs-grid">
                    <?php foreach ($export_fields as $key => $field): ?>
                        <?php if ($field['required']): ?>
                            <div class="champ-item required">
                                <input type="checkbox" value="<?= $key ?>" checked disabled>
                                <label><?= $field['label'] ?></label>
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
                                <input type="checkbox" id="field_<?= $key ?>" value="<?= $key ?>" <?= $field['enabled'] ? 'checked' : '' ?>>
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

<?php include '../../includes/footer.php'; ?>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/xlsx.full.min.js"></script>
<script src="../../assets/js/jspdf.umd.min.js"></script>
<script src="../../assets/js/jspdf.plugin.autotable.min.js"></script>
<script src="../../assets/js/jszip.min.js"></script>

<script>
$(document).ready(function() {
    let selectedExportFields = <?php echo json_encode(array_map(function ($field) { return $field['enabled']; }, $export_fields)); ?>;

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
                    img.onload = () => resolve({ dataURL: reader.result, width: img.width, height: img.height });
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

    const gradeOrder = ['GENA', 'GAM', 'LTGEN', 'AMR', 'GENMAJ', 'VAM', 'GENBDE', 'CAM', 'COL', 'CPV',
        'LTCOL', 'CPF', 'MAJ', 'CPC', 'CAPT', 'LDV', 'LT', 'EV', 'SLT', '2EV', 'A-C', 'MCP', 'A-1',
        '1MC', 'ADJ', 'MRC', '1SM', '1MR', 'SM', '2MR', '1SGT', 'MR', 'SGT', 'QMT', 'CPL', '1MT', 
        '1CL', '2MT', '2CL', 'MT', 'REC', 'ASK', 'COMD'];

    function getGradeIndex(grade) {
        const index = gradeOrder.indexOf(grade);
        return index === -1 ? 999 : index;
    }

    const table = $('#table-controles').DataTable({
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
        order: [[6, 'desc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        columnDefs: [{ targets: [6], visible: false }],
        initComplete: function() {
            const filterDiv = $('.dataTables_filter');
            
            filterDiv.css({
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px',
                'float': 'right'
            });
            
            filterDiv.prepend('<i class="fas fa-search search-icon" style="color: #2e7d32; font-size: 1rem;"></i>');
            
            const searchLabel = filterDiv.find('label');
            searchLabel.css({
                'display': 'flex',
                'align-items': 'center',
                'margin-bottom': '0',
                'flex': '0 1 auto'
            });
            
            searchLabel.find('i').remove();
            
            searchLabel.contents().filter(function() {
                return this.nodeType === 3;
            }).remove();
            
            filterDiv.append(`
                <div class="action-buttons">
                    <a href="ajouter.php" class="btn-modern btn-primary-modern">
                        <i class="fas fa-user-plus"></i> Nouveau
                    </a>
                </div>
            `);
        }
    });

    function updateFilterTags() {
        const tags = [];
        if ($('#mention-filter').val()) tags.push(`Mention : ${$('#mention-filter').val()}`);
        if ($('#statut-filter').val()) tags.push(`Statut : ${$('#statut-filter').find('option:selected').text()}`);
        if ($('#zone-filter').val()) tags.push(`Zone : ${$('#zone-filter').find('option:selected').text()}`);
        if ($('#categorie-filter').val()) tags.push(`Catégorie : ${$('#categorie-filter').find('option:selected').text()}`);
        if ($('#garnison-filter').val()) tags.push(`Garnison : ${$('#garnison-filter').find('option:selected').text()}`);
        if ($('#date-debut').val()) tags.push(`Du : ${$('#date-debut').val()}`);
        if ($('#date-fin').val()) tags.push(`Au : ${$('#date-fin').val()}`);

        const $tagsContainer = $('.filtre-tags');
        $tagsContainer.empty();
        tags.forEach(tag => $tagsContainer.append(`<span class="filtre-tag me-2">${tag}</span>`));
        $tagsContainer.toggle(tags.length > 0);
    }

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'table-controles') return true;

        const row = table.row(dataIndex);
        const rowNode = row.node();
        const $row = $(rowNode);

        const mention = $row.data('mention') || '';
        const statut = $row.data('statut');
        const zone = $row.data('zone') || '';
        const categorie = $row.data('categorie') || '';
        const garnison = $row.data('garnison') || '';
        const dateIso = $row.data('date-order') || '';

        const mentionFilter = $('#mention-filter').val();
        const statutFilter = $('#statut-filter').val();
        const zoneFilter = $('#zone-filter').val();
        const categorieFilter = $('#categorie-filter').val();
        const garnisonFilter = $('#garnison-filter').val();
        const dateDebut = $('#date-debut').val();
        const dateFin = $('#date-fin').val();

        if (mentionFilter && mention !== mentionFilter) return false;
        if (statutFilter !== '' && Number(statut) !== Number(statutFilter)) return false;
        if (zoneFilter && zone !== zoneFilter) return false;
        if (categorieFilter && categorie !== categorieFilter) return false;
        if (garnisonFilter && garnison !== garnisonFilter) return false;
        if (dateDebut && dateIso && dateIso < dateDebut) return false;
        if (dateFin && dateIso && dateIso > dateFin) return false;

        return true;
    });

    $('#mention-filter, #statut-filter, #zone-filter, #categorie-filter, #garnison-filter, #date-debut, #date-fin')
        .on('change keyup', function() { table.draw(); updateFilterTags(); });

    $('#reset-filters').on('click', function() {
        $('#mention-filter, #statut-filter, #zone-filter, #categorie-filter, #garnison-filter, #date-debut, #date-fin').val('');
        table.draw();
        updateFilterTags();
    });

    // ============================================
    // FONCTIONS D'EXPORT
    // ============================================

    const fieldOrder = ['serie', 'matricule', 'noms', 'grade', 'ancien_beneficiaire', 'beneficiaire',
        'lien_parente', 'unite', 'garnison', 'province', 'categorie', 'mention', 'observations', 'zdef', 'date_controle'];

    const fullLabels = {
        'serie': 'SÉRIE', 'matricule': 'MATRICULE', 'noms': 'NOMS', 'grade': 'GRADE',
        'ancien_beneficiaire': 'ANCIEN BÉNÉFICIAIRE', 'beneficiaire': 'BÉNÉFICIAIRE',
        'lien_parente': 'LIEN PARENTÉ', 'unite': 'UNITÉ', 'garnison': 'GARNISON',
        'province': 'PROVINCE', 'categorie': 'CATÉGORIE', 'mention': 'MENTION',
        'observations': 'OBSERVATIONS', 'zdef': 'ZDEF', 'date_controle': 'DATE CONTRÔLE'
    };

    function getZoneFilterLabel(zones) {
        if (zones && zones.length > 0) {
            const validZones = zones.filter(z => z && z !== 'N/A');
            if (validZones.length === 0) return "LISTE DES MILITAIRES CONTRÔLÉS";
            if (validZones.length === 1) return `LISTE DES MILITAIRES CONTRÔLÉS DE LA ${validZones[0]}`;
            const firstPart = validZones.slice(0, -1).join(', ');
            const last = validZones[validZones.length - 1];
            return `LISTE DES MILITAIRES CONTRÔLÉS DE LA ${firstPart} ET ${last}`;
        }
        return "LISTE DES MILITAIRES CONTRÔLÉS";
    }

    function getFilteredRows() {
        let rowsToExport = [];
        table.rows({ search: 'applied', filter: 'applied' }).every(function() {
            rowsToExport.push(this);
        });
        return rowsToExport;
    }

    function extractRowData(row) {
        const node = row.node();
        const $cells = $(node).find('td');
        const $row = $(node);
        const rowData = {};

        rowData.matricule = $cells.eq(0).text().trim().toUpperCase();
        rowData.noms = $cells.eq(1).text().trim().toUpperCase();
        rowData.grade = $cells.eq(2).text().trim().toUpperCase();
        rowData.unite = $cells.eq(3).text().trim().toUpperCase();
        rowData.observations = $cells.eq(4).text().trim().toUpperCase();
        rowData.zdef = $cells.eq(5).text().trim().toUpperCase();

        rowData.ancien_beneficiaire = ($row.attr('data-ancien-beneficiaire') || '').toUpperCase();
        rowData.beneficiaire = ($row.attr('data-beneficiaire') || '').toUpperCase();
        rowData.lien_parente = ($row.attr('data-lien-parente') || '').toUpperCase();
        rowData.garnison = ($row.attr('data-garnison') || '').toUpperCase();
        rowData.province = ($row.attr('data-province') || '').toUpperCase();
        rowData.categorie = ($row.attr('data-categorie') || '').toUpperCase();
        rowData.mention = ($row.attr('data-mention') || '').toUpperCase();
        rowData.date_controle = $row.attr('data-date-controle') || '';
        rowData.zoneAttr = $row.data('zone') || '';

        return rowData;
    }

    function prepareExportData() {
        const rows = getFilteredRows();
        if (rows.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Aucune donnée', text: 'Aucune ligne à exporter.' });
            return null;
        }

        const rawRows = rows.map(row => extractRowData(row));

        rawRows.sort((a, b) => {
            const idxA = getGradeIndex(a.grade || '');
            const idxB = getGradeIndex(b.grade || '');
            if (idxA !== idxB) return idxA - idxB;
            return (a.matricule || '').localeCompare(b.matricule || '');
        });

        const selectedFields = fieldOrder.filter(field => selectedExportFields[field]);
        const headers = selectedFields.map(field => fullLabels[field]);

        const data = rawRows.map((row, index) => {
            return selectedFields.map(field => {
                if (field === 'serie') return (index + 1).toString();
                return row[field] || '';
            });
        });

        const zonesSet = new Set();
        rawRows.forEach(row => {
            if (row.zoneAttr && row.zoneAttr !== 'N/A') zonesSet.add(row.zoneAttr);
        });
        const zoneLabel = getZoneFilterLabel(Array.from(zonesSet));

        const headerLines = [
            'MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS',
            'INSPECTORAT GENERAL DES FARDC',
            zoneLabel
        ];

        return { headerLines, headers, data };
    }

    async function generatePDFBlob(exportData) {
        const { headerLines, headers, data } = exportData;

        let logo, qrCode, watermark;
        try {
            [logo, qrCode, watermark] = await Promise.all([
                loadImage('../../assets/img/new-logo-ig-fardc.png'),
                loadImage('../../assets/img/qr-code-ig-fardc.png'),
                loadImage('../../assets/img/filigrane_logo_ig_fardc.png')
            ]);
        } catch (imageError) {
            console.warn('Images non trouvées', imageError);
            logo = { dataURL: null, width: 100, height: 100 };
            qrCode = { dataURL: null, width: 100, height: 100 };
            watermark = { dataURL: null, width: 100, height: 100 };
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

        const pageWidth = doc.internal.pageSize.width;
        const pageHeight = doc.internal.pageSize.height;
        const margin = 15;
        const rightMargin = 15;
        const bottomMargin = 30;

        const columnStyles = {};
        headers.forEach((_, index) => {
            columnStyles[index] = { halign: index === 0 ? 'center' : 'left' };
        });

        let headerAdded = false;
        let currentPage = 1;

        function addWatermark() {
            if (!watermark.dataURL) return;
            try {
                doc.saveGraphicsState();
                doc.setGState(new doc.GState({ opacity: 0.15 }));
                const wmWidth = 80;
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
            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'normal');
            const dateStr = 'KINSHASA, LE ' + new Date().toLocaleDateString('fr-FR').toUpperCase();
            doc.text(dateStr, pageWidth - rightMargin, 12, { align: 'right' });

            doc.setFontSize(12);
            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'bold');
            doc.text(headerLines[0], pageWidth / 2, 25, { align: 'center' });

            doc.setFontSize(11);
            doc.setTextColor(0, 0, 0);
            doc.text(headerLines[1], pageWidth / 2, 32, { align: 'center' });

            doc.setFontSize(13);
            doc.setTextColor(255, 0, 0);
            doc.text(headerLines[2], pageWidth / 2, 42, { align: 'center' });

            doc.setDrawColor(0, 0, 0);
            doc.setLineWidth(0.5);
            doc.line(margin, 48, pageWidth - rightMargin, 48);

            headerAdded = true;
        }

        function addFooter() {
            const footerY = pageHeight - 15;
            const lineY = pageHeight - 20;
            const lineWidth = pageWidth - margin - rightMargin;
            const segmentWidth = lineWidth / 3;

            doc.setFillColor(0, 162, 232);
            doc.rect(margin, lineY, segmentWidth, 2, 'F');
            doc.setFillColor(255, 215, 0);
            doc.rect(margin + segmentWidth, lineY, segmentWidth, 2, 'F');
            doc.setFillColor(239, 43, 45);
            doc.rect(margin + (2 * segmentWidth), lineY, segmentWidth, 2, 'F');

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
            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'normal');
            doc.text('INSPECTORAT GÉNÉRAL DES FARDC, AVENUE DES ÉCURIES, N°54, QUARTIER JOLI PARC, COMMUNE DE NGALIEMA',
                pageWidth / 2, footerY, { align: 'center' });
            doc.setFont('times', 'italic');
            doc.text('Emails : infoigfardc2017@gmail.com; igfardc2017@yahoo.fr',
                pageWidth / 2, footerY + 5, { align: 'center' });
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(6);
            doc.text(`Page ${currentPage}`, pageWidth - rightMargin, pageHeight - 8, { align: 'right' });
        }

        addWatermark();
        addFirstPageHeader();

        doc.autoTable({
            head: [headers],
            body: data,
            startY: 55,
            margin: { left: margin, right: rightMargin, bottom: bottomMargin },
            styles: {
                fontSize: 8,
                cellPadding: 2,
                font: 'helvetica',
                halign: 'left',
                valign: 'middle',
                textColor: [0, 0, 0],
                lineColor: [0, 0, 0],
                lineWidth: 0.2,
                fillColor: [255, 255, 255]
            },
            headStyles: {
                fillColor: [255, 255, 255],
                textColor: [0, 0, 0],
                fontStyle: 'bold',
                halign: 'center',
                fontSize: 8,
                lineColor: [0, 0, 0],
                lineWidth: 0.2
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245],
                textColor: [0, 0, 0],
                lineColor: [0, 0, 0],
                lineWidth: 0.2
            },
            columnStyles: columnStyles,
            didDrawPage: function(data) {
                currentPage = data.pageNumber;
                addFooter();
                addWatermark();
            }
        });

        return doc.output('blob');
    }

    // Export CSV
    $('#export-csv').on('click', function() {
        const exportData = prepareExportData();
        if (!exportData) return;
        
        const { headers, data } = exportData;
        const csvRows = [headers.join(';'), ...data.map(row => row.map(cell => `"${cell}"`).join(';'))];
        const blob = new Blob(["\uFEFF" + csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `controles_${getTimestamp()}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
        
        $.get(window.location.href, { ajax: 'log_export', type: 'CSV', filtres: 'tous' });
        Swal.fire({ icon: 'success', title: 'Export CSV', text: 'Fichier CSV généré avec succès', timer: 1500, showConfirmButton: false });
    });

    // Export Excel
    $('#export-excel').on('click', function() {
        const exportData = prepareExportData();
        if (!exportData) return;
        
        const { headers, data } = exportData;
        const wsData = [headers, ...data];
        const ws = XLSX.utils.aoa_to_sheet(wsData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Controles');
        XLSX.writeFile(wb, `controles_${getTimestamp()}.xlsx`);
        
        $.get(window.location.href, { ajax: 'log_export', type: 'Excel', filtres: 'tous' });
        Swal.fire({ icon: 'success', title: 'Export Excel', text: 'Fichier Excel généré avec succès', timer: 1500, showConfirmButton: false });
    });

    // Export PDF
    $('#export-pdf').on('click', async function() {
        Swal.fire({ title: 'Génération du PDF...', text: 'Veuillez patienter', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        const exportData = prepareExportData();
        if (!exportData) {
            Swal.close();
            return;
        }
        
        const pdfBlob = await generatePDFBlob(exportData);
        const link = document.createElement('a');
        link.href = URL.createObjectURL(pdfBlob);
        link.download = `controles_${getTimestamp()}.pdf`;
        link.click();
        URL.revokeObjectURL(link.href);
        
        Swal.close();
        $.get(window.location.href, { ajax: 'log_export', type: 'PDF', filtres: 'tous' });
        Swal.fire({ icon: 'success', title: 'Export PDF', text: 'Fichier PDF généré avec succès', timer: 1500, showConfirmButton: false });
    });

    // Export ZIP
    $('#export-zip').on('click', async function() {
        Swal.fire({ title: 'Génération du ZIP...', text: 'Veuillez patienter', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        const exportData = prepareExportData();
        if (!exportData) {
            Swal.close();
            return;
        }
        
        const zip = new JSZip();
        const timestamp = getTimestamp();
        const { headers, data } = exportData;
        
        // CSV
        const csvRows = [headers.join(';'), ...data.map(row => row.map(cell => `"${cell}"`).join(';'))];
        zip.file(`controles_${timestamp}.csv`, "\uFEFF" + csvRows.join('\n'));
        
        // Excel
        const wsData = [headers, ...data];
        const ws = XLSX.utils.aoa_to_sheet(wsData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Controles');
        const excelBuffer = XLSX.write(wb, { type: 'array', bookType: 'xlsx' });
        zip.file(`controles_${timestamp}.xlsx`, excelBuffer);
        
        // PDF
        const pdfBlob = await generatePDFBlob(exportData);
        zip.file(`controles_${timestamp}.pdf`, pdfBlob);
        
        const content = await zip.generateAsync({ type: 'blob' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(content);
        link.download = `controles_${timestamp}.zip`;
        link.click();
        URL.revokeObjectURL(link.href);
        
        Swal.close();
        $.get(window.location.href, { ajax: 'log_export', type: 'ZIP', filtres: 'tous' });
        Swal.fire({ icon: 'success', title: 'Export ZIP', text: 'Fichier ZIP généré avec succès', timer: 2000, showConfirmButton: false });
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
            toast: true
        });
    });
    // === POLLING AUTOMATIQUE NOUVEAUX CONTRÔLES ===
    let lastId = 0;

    function getLastIdFromTable() {
        // On suppose que le matricule est unique, mais on va utiliser la date la plus récente comme repère
        let maxId = 0;
        $('#table-controles tbody tr').each(function() {
            const id = parseInt($(this).attr('data-id'), 10);
            if (!isNaN(id) && id > maxId) maxId = id;
        });
        return maxId;
    }

    // Affiche une notification toast simple
    function showToast(message) {
        let toast = document.createElement('div');
        toast.className = 'custom-toast';
        toast.innerHTML = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => document.body.removeChild(toast), 300);
        }, 2500);
    }

    function ajouterControleLigne(ctrl) {
        const zdef = ctrl.zdef || '';
        // Correction : garantir que l'unité provient bien de la table militaires (champ 'unite')
        const rowData = [
            '<div class="matricule-with-eye"><i class="fas fa-eye" onclick="window.location.href=\'voir.php?id=' + ctrl.id + '\'"></i><strong>' + (ctrl.matricule || '').toUpperCase() + '</strong></div>',
            (ctrl.noms || '').toUpperCase(),
            (ctrl.grade || '').toUpperCase(),
            (ctrl.unite || ctrl['unite'] || '').toUpperCase(),
            (ctrl.observations || '').toUpperCase(),
            zdef,
            ctrl.date_controle || ''
        ];
        // Ajout via DataTables API
        const rowNode = table.row.add(rowData).draw(false).node();
        // Ajoute les attributs data-* nécessaires pour le filtrage
        $(rowNode)
            .attr('data-id', ctrl.id)
            .attr('data-zone', ctrl.zdef || '')
            .attr('data-mention', (ctrl.mention || '').toUpperCase())
            .attr('data-categorie', (ctrl.categorie || ''))
            .attr('data-statut', ctrl.statut || '')
            .attr('data-garnison', (ctrl.garnison || ''))
            .attr('data-unite', (ctrl.unite || '').toUpperCase())
            .attr('data-date-order', ctrl.date_controle || '')
            .attr('data-ancien-beneficiaire', (ctrl.nom_beneficiaire || ''))
            .attr('data-beneficiaire', (ctrl.new_beneficiaire || ''))
            .attr('data-lien-parente', (ctrl.lien_parente || ''))
            .attr('data-province', (ctrl.province || ''))
            .attr('data-date-controle', ctrl.date_controle || '');
        // Effet visuel : surlignage temporaire
        $(rowNode).addClass('row-highlight');
        setTimeout(() => { $(rowNode).removeClass('row-highlight'); }, 2000);
        // Notification toast avec nom complet
        const nomComplet = ((ctrl.grade ? ctrl.grade + ' ' : '') + (ctrl.noms || '')).toUpperCase();
        showToast('Nouveau contrôle ajouté : <b>' + nomComplet + '</b>');
    }


    // Rafraîchit dynamiquement les cartes de stats
    function refreshStatsCards() {
        $.get(window.location.pathname + '?ajax=stats', function(resp) {
            if (resp.success) {
                $(".stat-card .stat-info h4").eq(0).text(resp.total_controles);
                $(".stat-card .stat-info h4").eq(1).text(resp.total_presents);
                $(".stat-card .stat-info h4").eq(2).text(resp.total_favorables);
                $(".stat-card .stat-info h4").eq(3).text(resp.total_defavorables);
            }
        }, 'json');
    }

    function pollControles() {
        if (lastId === 0) lastId = getLastIdFromTable();
        // 1. Vérifie s'il y a un toast à afficher
        $.get('/ctr.net-fardc/api/toast.php', function(resp) {
            if (resp.toast && resp.toast.message) {
                showToast(resp.toast.message, resp.toast.type || 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 4000); // Recharge la page 4s après le toast
            }
        }, 'json');
        // 2. Polling des nouveaux contrôles
        $.get('/ctr.net-fardc/api/controles_poll.php?since_id=' + lastId, function(data) {
            if (data.success && data.count > 0) {
                data.nouveaux.forEach(function(ctrl) {
                    ajouterControleLigne(ctrl);
                    lastId = Math.max(lastId, ctrl.id);
                });
                refreshStatsCards(); // MAJ stats après ajout
            }
            setTimeout(function() {
                refreshStatsCards(); // MAJ stats à chaque polling
                pollControles();
            }, 5000);
        }, 'json').fail(function() {
            setTimeout(pollControles, 10000);
        });
    }

    // Ajout du style pour le toast et le surlignage
    const style = document.createElement('style');
    style.innerHTML = `
    .custom-toast {
        position: fixed;
        top : 20px;
        right: 20%;
        transform: translateX(-50%);
        background: #379b04;
        color: #fff;
        padding: 14px 28px;
        border-radius: 6px;
        font-size: 1.1em;
        opacity: 0;
        pointer-events: none;
        z-index: 9999;
        transition: opacity 0.3s;
    }
    .custom-toast.show {
        opacity: 0.95;
        pointer-events: auto;
    }
    .row-highlight {
        animation: highlight-fade 2s;
        background: #ffe082 !important;
    }
    @keyframes highlight-fade {
        0% { background: #ffe082; }
        100% { background: inherit; }
    }
    `;
    document.head.appendChild(style);
    pollControles();
});
</script>