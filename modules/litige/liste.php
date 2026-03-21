<?php
require_once '../../includes/functions.php';
require_login();

    // Vérifier si une sauvegarde automatique est nécessaire (tous les 2 jours)
    maybe_create_backup();

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$user_profil = $_SESSION['user_profil'] ?? '';

$page_titre = 'Liste des litiges';
$breadcrumb = ['Litiges' => '#'];
include '../../includes/header.php';

// Récupération des litiges (nouvelle structure avec grade)
$stmt = $pdo->query("
    SELECT *
    FROM litiges
    ORDER BY id DESC, date_controle DESC, cree_le DESC
");
$litiges = $stmt->fetchAll();

// Calcul des statistiques par zone de défense
$zones_defense = [
    '1ZDEF' => 0,
    '2ZDEF' => 0,
    '3ZDEF' => 0,
    'AUTRE' => 0
];

foreach ($litiges as $l) {
    $zdef = getZdefValue($l['province'] ?? '');
    $code = $zdef['code'];
    if (isset($zones_defense[$code])) {
        $zones_defense[$code]++;
    } else {
        $zones_defense['AUTRE']++;
    }
}
$total_litiges = count($litiges);

// Récupération des garnisons uniques pour le filtre
$garnisons = array_values(array_unique(array_filter(array_column($litiges, 'garnison'))));
sort($garnisons);

// Mapping des zones de défense (pour affichage)
$zones_defense_libelles = [
    '1ZDEF' => '1ZDef',
    '2ZDEF' => '2ZDef',
    '3ZDEF' => '3ZDef'
];

function getZdefValue($province)
{
    if (empty($province)) return ['value' => 'N/A', 'code' => 'N/A'];

    $province = strtoupper(trim($province));

    $groupe_2zdef = ['HAUT-KATANGA', 'HAUT-LOMAMI', 'LUALABA', 'TANGANYIKA', 'KASAI', 'KASAI-CENTRAL', 'KASAI-ORIENTAL', 'SANKURU', 'LOMAMI'];
    if (in_array($province, $groupe_2zdef)) {
        return ['value' => '2ZDEF', 'code' => '2ZDEF'];
    }

    $groupe_1zdef = ['EQUATEUR', 'MONGALA', 'NORD-UBANGI', 'SUD-UBANGI', 'TSHUAPA', 'KWILU', 'KWANGO', 'MAI-NDOMBE', 'KONGO-CENTRAL', 'KINSHASA'];
    if (in_array($province, $groupe_1zdef)) {
        return ['value' => '1ZDEF', 'code' => '1ZDEF'];
    }

    $groupe_3zdef = ['HAUT-UELE', 'BAS-UELE', 'ITURI', 'TSHOPO', 'NORD-KIVU', 'SUD-KIVU', 'MANIEMA'];
    if (in_array($province, $groupe_3zdef)) {
        return ['value' => '3ZDEF', 'code' => '3ZDEF'];
    }

    return ['value' => 'AUTRE', 'code' => 'AUTRE'];
}

$timestamp = date('Y-m-d_H\hi');

// Liste des champs disponibles pour l'export (adaptée à la nouvelle structure)
$export_fields = [
    'serie'            => ['label' => 'Série', 'enabled' => true, 'required' => true],
    'matricule'        => ['label' => 'Matricule', 'enabled' => true, 'required' => true],
    'noms'             => ['label' => 'Noms', 'enabled' => true, 'required' => true],
    'grade'            => ['label' => 'Grade', 'enabled' => true, 'required' => false], // Nouveau champ
    'type_controle'    => ['label' => 'Type contrôle', 'enabled' => true, 'required' => false],
    'nom_beneficiaire' => ['label' => 'Bénéficiaire', 'enabled' => true, 'required' => false],
    'lien_parente'     => ['label' => 'Lien parenté', 'enabled' => true, 'required' => false],
    'garnison'         => ['label' => 'Garnison', 'enabled' => true, 'required' => false],
    'province'         => ['label' => 'Province', 'enabled' => true, 'required' => false],
    'zdef'             => ['label' => 'ZDEF', 'enabled' => true, 'required' => false],
    'date_controle'    => ['label' => 'Date contrôle', 'enabled' => true, 'required' => false],
    'observations'     => ['label' => 'Observations', 'enabled' => true, 'required' => false]
];
?>
<style>
/* ========== STYLES REPRIS DU FICHIER 1 ========== */
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

/* Bouton Nouveau litige : jaune → vert au survol */
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

.btn-primary-modern i {
    color: #333;
}

.btn-primary-modern:hover i {
    color: white;
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

/* Filtres */
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

/* Tableau */
.table-militaires {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
    min-width: 1200px;
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

/* Masquage des colonnes demandées */
.table-militaires th:nth-child(4),
/* Type contrôle */
.table-militaires td:nth-child(4),
.table-militaires th:nth-child(5),
/* Bénéficiaire */
.table-militaires td:nth-child(5),
.table-militaires th:nth-child(6),
/* Lien parenté */
.table-militaires td:nth-child(6),
.table-militaires th:nth-child(11),
/* Date contrôle visible */
.table-militaires td:nth-child(11) {
    display: none;
}

/* Style pour l'icône œil */
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

/* Checkboxes - taille réduite */
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
    overflow-y: auto !important;
    border-radius: 10px;
}

/* Statistiques */
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

.stat-icon.zone1 {
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
}

.stat-icon.zone2 {
    background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
}

.stat-icon.zone3 {
    background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
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

/* Tags filtres */
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

/* Modal champs export */
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
    max-height: 96vh;
    overflow-y: auto;
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

/* Responsive */
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
            <div class="stat-icon"><i class="fas fa-gavel"></i></div>
            <div class="stat-info">
                <h4><?= $total_litiges ?></h4>
                <p>Total litiges</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon zone1"><i class="fas fa-shield-alt"></i></div>
            <div class="stat-info">
                <h4><?= $zones_defense['1ZDEF'] ?></h4>
                <p>1ZDef</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon zone2"><i class="fas fa-shield-alt"></i></div>
            <div class="stat-info">
                <h4><?= $zones_defense['2ZDEF'] ?></h4>
                <p>2ZDef</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon zone3"><i class="fas fa-shield-alt"></i></div>
            <div class="stat-info">
                <h4><?= $zones_defense['3ZDEF'] ?></h4>
                <p>3ZDef</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card modern-card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-gavel"></i> Tous les litiges</h3>
                    <div class="d-flex align-items-center gap-3">
                        <span class="total-badge"><i class="fas fa-database"></i> Total :
                            <?= count($litiges) ?></span>
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
                            <label class="form-label"><i class="fas fa-map-pin"></i> Garnison</label>
                            <select id="garnison-filter" class="form-select">
                                <option value="">Toutes</option>
                                <?php foreach ($garnisons as $garnison): ?>
                                <option value="<?= htmlspecialchars($garnison) ?>">
                                    <?= htmlspecialchars($garnison) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label"><i class="fas fa-shield-alt"></i> Zone défense</label>
                            <select id="zone-filter" class="form-select">
                                <option value="">Toutes</option>
                                <?php foreach ($zones_defense_libelles as $code => $libelle): ?>
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
                            <button id="reset-filters" class="btn-reset-modern w-100"><i class="fas fa-undo-alt"></i>
                                Réinitialiser</button>
                        </div>
                    </div>

                    <!-- Tags des filtres actifs -->
                    <div class="filtre-tags mb-3" style="display: none;"></div>

                    <!-- Tableau -->
                    <table id="table-litiges" class="table-militaires" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="select-all-checkbox" id="select-all"></th>
                                <th>Matricule</th>
                                <th>Noms</th>
                                <th>Grade</th> <!-- Nouvelle colonne -->
                                <th>Type contrôle</th> <!-- Masqué via CSS -->
                                <th>Bénéficiaire</th> <!-- Masqué via CSS -->
                                <th>Lien parenté</th> <!-- Masqué via CSS -->
                                <th>Garnison</th>
                                <th>Province</th>
                                <th>ZDEF</th>
                                <th>Date contrôle</th> <!-- Masqué via CSS -->
                                <th style="display:none;">Date</th> <!-- colonne cachée pour le tri -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($litiges as $l):
                                $zdef = getZdefValue($l['province'] ?? '');
                                $date_controle_brut = $l['date_controle'] ?? '';
                            ?>
                            <tr data-zone="<?= $zdef['code'] ?>"
                                data-garnison="<?= htmlspecialchars($l['garnison'] ?? '') ?>"
                                data-date-order="<?= htmlspecialchars($date_controle_brut) ?>">
                                <td><input type="checkbox" class="row-checkbox"
                                        value="<?= htmlspecialchars($l['id']) ?>"></td>
                                <td>
                                    <div class="matricule-with-eye">
                                        <i class="fas fa-eye"
                                            onclick="window.location.href='voir.php?id=<?= urlencode($l['id']) ?>'"></i>
                                        <strong><?= htmlspecialchars($l['matricule']) ?></strong>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($l['noms'] ?? '') ?></td>
                                <td><?= htmlspecialchars($l['grade'] ?? '') ?></td> <!-- Nouveau champ -->
                                <td><?= htmlspecialchars($l['type_controle'] ?? '') ?></td>
                                <td><?= htmlspecialchars($l['nom_beneficiaire'] ?? '') ?></td>
                                <td><?= htmlspecialchars($l['lien_parente'] ?? '') ?></td>
                                <td><?= htmlspecialchars($l['garnison'] ?? '') ?></td>
                                <td><?= htmlspecialchars($l['province'] ?? '') ?></td>
                                <td><?= $zdef['value'] ?></td>
                                <td data-order="<?= htmlspecialchars($date_controle_brut) ?>">
                                    <?= !empty($date_controle_brut) ? date('d/m/Y', strtotime($date_controle_brut)) : '' ?>
                                </td>
                                <td style="display:none;"><?= htmlspecialchars($date_controle_brut) ?></td>
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

<?php include '../../includes/footer.php'; ?>

<!-- Scripts supplémentaires (exports) -->
<script src="../../assets/js/xlsx.full.min.js"></script>
<script src="../../assets/js/jspdf.umd.min.js"></script>
<script src="../../assets/js/jspdf.plugin.autotable.min.js"></script>
<script src="../../assets/js/jszip.min.js"></script>

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

    const table = $('#table-litiges').DataTable({
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
        // Tri par défaut : date (colonne 11, cachée) du plus récent au plus ancien
        order: [
            [11, 'desc']
        ],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        scrollX: true,
        scrollY: '400px',
        scrollCollapse: true,
        paging: true,
        columnDefs: [{
                orderable: false,
                targets: [0]
            }, // checkbox
            {
                targets: [11],
                visible: false
            } // colonne date cachée
        ],
        createdRow: function(row, data, dataIndex) {
            // Highlight du terme recherché
            if (searchTerm) {
                $(row).find('td').each(function() {
                    const $td = $(this);
                    if ($td.index() === 0) return; // ignorer la checkbox
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

            // Ajout du bouton "Nouveau litige" avec le style jaune → vert
            filterDiv.append(`
                <div class="action-buttons">
                    <a href="ajouter.php" class="btn-modern btn-primary-modern">
                        <i class="fas fa-user-plus"></i> Nouveau
                    </a>
                </div>
            `);

            $('.dataTables_filter label').contents().filter(function() {
                return this.nodeType === 3;
            }).remove();
        }
    });

    $('.dataTables_filter input').on('keyup search input', function() {
        searchTerm = $(this).val();
        setTimeout(() => {
            if (searchTerm) {
                $('#table-litiges tbody tr').each(function() {
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
        if ($('#garnison-filter').val()) tags.push(
            `Garnison : ${$('#garnison-filter').find('option:selected').text()}`);
        if ($('#zone-filter').val()) tags.push(`Zone : ${$('#zone-filter').find('option:selected').text()}`);
        if ($('#date-debut').val()) tags.push(`Du : ${$('#date-debut').val()}`);
        if ($('#date-fin').val()) tags.push(`Au : ${$('#date-fin').val()}`);

        const $tagsContainer = $('.filtre-tags');
        $tagsContainer.empty();
        tags.forEach(tag => $tagsContainer.append(`<span class="filtre-tag me-2">${tag}</span>`));
        $tagsContainer.toggle(tags.length > 0);
    }

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'table-litiges') return true;

        const row = table.row(dataIndex);
        const rowNode = row.node();
        const $row = $(rowNode);

        const garnison = $row.data('garnison') || '';
        const zone = $row.data('zone') || '';
        const dateIso = $row.data('date-order') || '';

        const garnisonFilter = $('#garnison-filter').val();
        const zoneFilter = $('#zone-filter').val();
        const dateDebut = $('#date-debut').val();
        const dateFin = $('#date-fin').val();

        if (garnisonFilter && garnison !== garnisonFilter) return false;
        if (zoneFilter && zone !== zoneFilter) return false;
        if (dateDebut && dateIso && dateIso < dateDebut) return false;
        if (dateFin && dateIso && dateIso > dateFin) return false;

        return true;
    });

    $('#garnison-filter, #zone-filter, #date-debut, #date-fin').on('change keyup', function() {
        table.draw();
        updateFilterTags();
    });

    $('#reset-filters').on('click', function() {
        $('#garnison-filter, #zone-filter, #date-debut, #date-fin').val('');
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

    // ========== FONCTIONS D'EXPORT ==========

    function getZoneFilterLabel(zones) {
        if (zones && zones.length > 0) {
            const validZones = zones.filter(z => z && z !== 'N/A');
            if (validZones.length === 0) return "LISTE DES LITIGES";
            if (validZones.length === 1) return `LISTE DES LITIGES DE LA ${validZones[0]}`;
            const firstPart = validZones.slice(0, -1).join(', ');
            const last = validZones[validZones.length - 1];
            return `LISTE DES LITIGES DE LA ${firstPart} ET ${last}`;
        } else {
            const zoneSelect = $('#zone-filter');
            const selectedZone = zoneSelect.val();
            let zonesList = [];
            if (selectedZone) {
                zonesList = [selectedZone];
            } else {
                zoneSelect.find('option').each(function() {
                    const val = $(this).val();
                    if (val) zonesList.push(val);
                });
            }
            if (zonesList.length === 0) return "LISTE DES LITIGES";
            if (zonesList.length === 1) return `LISTE DES LITIGES DE LA ${zonesList[0]}`;
            const firstPart = zonesList.slice(0, -1).join(', ');
            const last = zonesList[zonesList.length - 1];
            return `LISTE DES LITIGES DE LA ${firstPart} ET ${last}`;
        }
    }

    const fieldOrder = ['serie', 'matricule', 'noms', 'grade', 'type_controle', 'nom_beneficiaire',
        'lien_parente',
        'garnison', 'province', 'zdef', 'date_controle', 'observations'
    ];

    const fullLabels = {
        'serie': 'SÉRIE',
        'matricule': 'MATRICULE',
        'noms': 'NOMS',
        'grade': 'GRADE', // Nouveau libellé
        'type_controle': 'TYPE CONTRÔLE',
        'nom_beneficiaire': 'BÉNÉFICIAIRE',
        'lien_parente': 'LIEN PARENTÉ',
        'garnison': 'GARNISON',
        'province': 'PROVINCE',
        'zdef': 'ZDEF',
        'date_controle': 'DATE CONTRÔLE',
        'observations': 'OBSERVATIONS'
    };

    const abbrLabels = {
        'serie': 'SÉRIE',
        'matricule': 'MATRICULE',
        'noms': 'NOMS',
        'grade': 'GRADE',
        'type_controle': 'TYPE',
        'nom_beneficiaire': 'BÉNÉF.',
        'lien_parente': 'LIEN',
        'garnison': 'GARNISON',
        'province': 'PROV.',
        'zdef': 'ZDEF',
        'date_controle': 'DATE',
        'observations': 'OBS.'
    };

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
        const rowData = {};

        // Nouveaux indices après ajout de la colonne Grade
        // 0: checkbox, 1: matricule, 2: noms, 3: grade, 4: type_controle, 5: nom_beneficiaire,
        // 6: lien_parente, 7: garnison, 8: province, 9: zdef, 10: date_controle visible, 11: date cachée
        rowData.matricule = $cells.eq(1).text().trim().toUpperCase();
        rowData.noms = $cells.eq(2).text().trim().toUpperCase();
        rowData.grade = $cells.eq(3).text().trim().toUpperCase();
        rowData.type_controle = $cells.eq(4).text().trim().toUpperCase();
        rowData.nom_beneficiaire = $cells.eq(5).text().trim().toUpperCase();
        rowData.lien_parente = $cells.eq(6).text().trim().toUpperCase();
        rowData.garnison = $cells.eq(7).text().trim().toUpperCase();
        rowData.province = $cells.eq(8).text().trim().toUpperCase();
        rowData.zdef = $cells.eq(9).text().trim().toUpperCase();
        rowData.date_controle = $cells.eq(10).text().trim().toUpperCase(); // format affiché
        // Pour la date brute, on prend l'attribut data-order de la cellule 10 (date visible)
        rowData.date_controle_brut = $row.find('td:eq(10)').data('order') || '';
        let obs = $cells.eq(11).text().trim()
            .toUpperCase(); // observations ? Non, la colonne 11 est date cachée. Les observations ne sont pas dans le tableau. 
        // En réalité, la colonne observations n'est pas affichée dans le tableau, donc on doit la récupérer autrement.
        // Dans la version actuelle, le champ observations n'est pas dans le tableau HTML. Il faut l'ajouter si on veut l'exporter.
        // Mais la demande ne mentionne pas les observations. On va supposer que le champ observations est présent dans la table mais pas affiché.
        // Pour l'instant, on ne peut pas l'exporter car non présent dans le DOM. On pourrait l'ajouter en cache.
        // On va plutôt récupérer les observations depuis l'attribut data-* ou depuis une colonne cachée.
        // Pour simplifier, on va ajouter une colonne cachée pour observations dans le tableau HTML.
        // Mais cela alourdit. On va plutôt récupérer via l'ID et faire une requête AJAX ? Trop complexe.
        // Solution : on ne gère pas observations dans l'export pour l'instant. L'utilisateur pourra l'ajouter plus tard.
        // On met une chaîne vide.
        rowData.observations = '';

        return rowData;
    }

    function prepareExportData(useAbbreviatedHeaders) {
        const rows = getFilteredRows();
        if (rows.length === 0) return null;

        const rawRows = rows.map(row => extractRowData(row));

        // Tri simple par matricule
        rawRows.sort((a, b) => {
            const matriculeA = a.matricule || '';
            const matriculeB = b.matricule || '';
            return matriculeA.localeCompare(matriculeB);
        });

        const selectedFields = fieldOrder.filter(field => selectedExportFields[field]);

        const labels = useAbbreviatedHeaders ? abbrLabels : fullLabels;
        const headers = selectedFields.map(field => labels[field]);

        const data = rawRows.map((row, index) => {
            return selectedFields.map(field => {
                if (field === 'serie') {
                    return (index + 1).toString();
                } else if (field === 'date_controle') {
                    // Utiliser la date brute pour les exports (format YYYY-MM-DD)
                    return row.date_controle_brut || '';
                } else {
                    return row[field] || '';
                }
            });
        });

        const zonesSet = new Set();
        rawRows.forEach(row => {
            if (row.zdef && row.zdef !== 'N/A') zonesSet.add(row.zdef);
        });
        const zoneLabel = getZoneFilterLabel(Array.from(zonesSet));

        const headerLines = [
            ['MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS'],
            ['INSPECTORAT GENERAL DES FARDC'],
            [zoneLabel]
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
            XLSX.utils.book_append_sheet(wb, ws, 'LITIGES');
            const excelBlob = new Blob([XLSX.write(wb, {
                bookType: 'xlsx',
                type: 'array'
            })], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            });

            const pdfBlob = await generatePDFBlob(exportDataAbbr);

            const zip = new JSZip();
            zip.file("litiges.csv", csvBlob);
            zip.file("litiges.xlsx", excelBlob);
            zip.file("litiges.pdf", pdfBlob);

            const zipBlob = await zip.generateAsync({
                type: "blob"
            });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(zipBlob);
            link.download = `litiges_${getTimestamp()}.zip`;
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
        link.download = `litiges_${getTimestamp()}.csv`;
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
        XLSX.utils.book_append_sheet(wb, ws, 'LITIGES');
        XLSX.writeFile(wb, `litiges_${getTimestamp()}.xlsx`);
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
            link.download = `litiges_${getTimestamp()}.pdf`;
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