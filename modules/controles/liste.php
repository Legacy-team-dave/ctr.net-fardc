<?php
require_once '../../includes/functions.php';
require_login();

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
// --- Fin AJAX ---

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$user_profil = $_SESSION['user_profil'] ?? '';
$csrf_token = generate_csrf_token();

$page_titre = 'Liste des contrôles';
$breadcrumb = ['Contrôles' => '#'];
include '../../includes/header.php';

// Récupération des contrôles avec jointure sur militaires
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

// Liste des champs disponibles pour l'export
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

<!-- ========== STYLES SPÉCIFIQUES ========== -->
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

.btn-export.sync {
    background: #17a2b8;
}

.btn-export.sync:hover {
    background: #138496;
    transform: translateY(-2px);
}

.sync-server-input-row {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    margin-top: 1rem;
}

.sync-server-input-row .swal2-input {
    margin: 0;
    width: 100%;
}

.sync-server-test-btn {
    width: 48px;
    min-width: 48px;
    height: 48px;
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 1.1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease, transform 0.2s ease;
}

.sync-server-test-btn:hover {
    transform: translateY(-1px);
}

.sync-server-test-btn.pending {
    background: #f0ad4e;
}

.sync-server-test-btn.success {
    background: #28a745;
}

.sync-server-test-btn.error {
    background: #dc3545;
}

.sync-server-feedback {
    margin-top: 10px;
    font-size: 0.9rem;
    text-align: left;
    color: #6c757d;
    min-height: 1.2rem;
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

.stat-icon.present {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
}

.stat-icon.favorable {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
}

.stat-icon.defavorable {
    background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%);
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

/* Styles spécifiques pour le QR code modal */
#qrCodeModal .modal-content {
    border-radius: 15px;
}

#qrCodeModal .modal-header {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
    border-bottom: none;
}

#qrCodeModal .modal-header .btn-close {
    filter: brightness(0) invert(1);
}

@media (max-width: 992px) {
    #qrcode canvas {
        width: 100% !important;
        height: auto !important;
    }
}

/* Toast notifications (même design que ajouter.php / mobile) */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast-message {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    color: white;
    padding: 15px 25px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(40, 167, 69, 0.3);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    animation: slideIn 0.3s ease-out, fadeOut 0.5s ease-out 3s forwards;
    font-weight: 500;
    min-width: 320px;
    font-size: 0.95rem;
}

.toast-message i {
    font-size: 1.2rem;
}

.toast-message.error {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    box-shadow: 0 5px 20px rgba(220, 53, 69, 0.3);
}

.toast-message.warning {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    color: #212529;
    box-shadow: 0 5px 20px rgba(255, 193, 7, 0.3);
}

.toast-message.info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    box-shadow: 0 5px 20px rgba(23, 162, 184, 0.3);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }

    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
        transform: translateX(0);
    }

    to {
        opacity: 0;
        transform: translateX(100%);
    }
}
</style>

<!-- Toast container pour notifications mobile -->
<div class="toast-container" id="mobile-toast-container"></div>

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
                <!-- FORMULAIRE POUR LA SYNCHRONISATION -->
                <form action="sync.php" method="get" id="syncForm">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-clipboard-list"></i> Tous les contrôles</h3>
                        <div class="d-flex align-items-center gap-3">
                            <span class="total-badge"><i class="fas fa-database"></i> Total :
                                <?= count($controles) ?></span>
                            <div class="export-buttons">
                                <button type="button" class="btn-export choisir" id="choisir-champs"><i
                                        class="fas fa-sliders-h"></i>
                                    Champs</button>
                                <button type="button" class="btn-export csv" id="export-csv"><i
                                        class="fas fa-file-csv"></i> CSV</button>
                                <button type="button" class="btn-export excel" id="export-excel"><i
                                        class="fas fa-file-excel"></i>
                                    Excel</button>
                                <button type="button" class="btn-export pdf" id="export-pdf"><i
                                        class="fas fa-file-pdf"></i> PDF</button>
                                <button type="button" class="btn-export zip" id="export-zip"><i
                                        class="fas fa-file-archive"></i>
                                    ZIP</button>
                                <?php if (in_array($user_profil, ['ADMIN_IG', 'OPERATEUR'])): ?>
                                <button type="submit" class="btn-export sync" id="sync-submit">
                                    <i class="fas fa-cloud-upload-alt"></i> Ouvrir la synchronisation
                                </button>
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
                                    <option value="<?= htmlspecialchars($mention) ?>"><?= htmlspecialchars($mention) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-item">
                                <label class="form-label"><i class="fas fa-map-pin"></i> Garnison</label>
                                <select id="garnison-filter" class="form-select">
                                    <option value="">Toutes</option>
                                    <?php foreach ($garnisons as $garnison): ?>
                                    <option value="<?= htmlspecialchars($garnison) ?>">
                                        <?= htmlspecialchars($garnison) ?>
                                    </option>
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
                                <button type="button" id="reset-filters" class="btn-reset-modern w-100"><i
                                        class="fas fa-undo-alt"></i>
                                    Réinitialiser</button>
                            </div>
                        </div>

                        <!-- Tags des filtres actifs -->
                        <div class="filtre-tags mb-3" style="display: none;"></div>

                        <!-- Tableau avec une colonne cachée pour la date -->
                        <table id="table-controles" class="table-militaires" style="width:100%">
                            <thead>
                                <th><input type="checkbox" class="select-all-checkbox" id="select-all"></th>
                                <th>Matricule</th>
                                <th>Noms</th>
                                <th>Grade</th>
                                <th>Unité</th>
                                <th>Observations</th>
                                <th>ZDEF</th>
                                <th>QR</th>
                                <th style="display:none;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($controles as $c):
                                    $categorie_libelle = $categories_list[$c['militaire_categorie'] ?? ''] ?? ($c['militaire_categorie'] ?? '');
                                    $lien_parente = formatLienParente($c['lien_parente'] ?? '');
                                    $zdef = getZdefValue($c['province'] ?? '');
                                    $unite = $c['unite'] ?? '';
                                    $garnison = $c['garnison'] ?? '';
                                    $province = $c['province'] ?? '';
                                    $grade = $c['grade'] ?? '';
                                    $observations = $c['observations'] ?? '';
                                    $ancien_beneficiaire = $c['nom_beneficiaire'] ?? '';
                                    $date_controle_brut = $c['date_controle'] ?? '';

                                    // Données pour le QR code
                                    $qrData = [
                                        'source'         => 'ctr.net-fardc',
                                        'payload_version'=> 1,
                                        'matricule'      => $c['matricule'],
                                        'noms'           => $c['nom_militaire'],
                                        'grade'          => $grade,
                                        'unite'          => $unite,
                                        'garnison'       => $garnison,
                                        'province'       => $province,
                                        'categorie'      => $c['militaire_categorie'] ?? '',
                                        'date_controle'  => $date_controle_brut,
                                        'mention'        => $c['mention']
                                    ];
                                ?>
                                <tr data-zone="<?= $zdef['code'] ?>"
                                    data-mention="<?= htmlspecialchars($c['mention'] ?? '') ?>"
                                    data-categorie="<?= htmlspecialchars($c['militaire_categorie'] ?? '') ?>"
                                    data-statut="<?= htmlspecialchars($c['militaire_statut'] ?? '') ?>"
                                    data-garnison="<?= htmlspecialchars($garnison) ?>"
                                    data-date-order="<?= htmlspecialchars($date_controle_brut) ?>"
                                    data-ancien-beneficiaire="<?= htmlspecialchars($ancien_beneficiaire) ?>"
                                    data-beneficiaire="<?= htmlspecialchars($c['new_beneficiaire'] ?? '') ?>"
                                    data-lien-parente="<?= htmlspecialchars($lien_parente) ?>"
                                    data-province="<?= htmlspecialchars($province) ?>"
                                    data-categorie-libelle="<?= htmlspecialchars($categorie_libelle) ?>"
                                    data-date-controle="<?= htmlspecialchars($date_controle_brut) ?>">
                                    <td><input type="checkbox" class="row-checkbox" name="ids[]"
                                            value="<?= htmlspecialchars($c['id']) ?>"></td>
                                    <td>
                                        <div class="matricule-with-eye">
                                            <i class="fas fa-eye"
                                                onclick="window.location.href='voir.php?id=<?= urlencode($c['id']) ?>'"></i>
                                            <strong><?= htmlspecialchars($c['matricule']) ?></strong>
                                        </div>
                                    </td>
                                    <td><?= !empty($c['nom_militaire']) ? htmlspecialchars($c['nom_militaire']) : '' ?>
                                    </td>
                                    <td><?= !empty($grade) ? htmlspecialchars($grade) : '' ?></td>
                                    <td><?= !empty($unite) ? htmlspecialchars($unite) : '' ?></td>
                                    <td><?= !empty($observations) ? htmlspecialchars($observations) : '' ?></td>
                                    <td><?= $zdef['value'] ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-success btn-qr-code"
                                            data-info='<?= htmlspecialchars(json_encode($qrData), ENT_QUOTES) ?>'>
                                            <i class="fas fa-qrcode"></i>
                                        </button>
                                    </td>
                                    <td style="display:none;"><?= htmlspecialchars($date_controle_brut) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                <!-- FIN DU FORMULAIRE -->
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

<!-- Modal QR Code -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-qrcode"></i> Code QR du contrôle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div class="militaire-info mb-3 text-center">
                    <div><strong>Matricule :</strong> <span id="qrMatricule"></span></div>
                    <div><strong>Noms :</strong> <span id="qrNoms"></span></div>
                    <div><strong>Grade :</strong> <span id="qrGrade"></span></div>
                    <canvas id="qrcodeCanvas" style="width: 300px; height: 300px; margin: 0 auto;"></canvas>
                    <p class="mt-3 text-muted">Scannez ce code pour pré-remplir l’enrôlement mobile du militaire vivant.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="../../assets/js/bootstrap.bundle.min.js"></script>

<!-- Scripts supplémentaires -->
<script src="../../assets/js/xlsx.full.min.js"></script>
<script src="../../assets/js/jspdf.umd.min.js"></script>
<script src="../../assets/js/jspdf.plugin.autotable.min.js"></script>
<script src="../../assets/js/jszip.min.js"></script>
<script src="../../assets/js/qrcode.min.js"></script>

<script>
$(document).ready(function() {
    // État des champs sélectionnés pour l'export
    let selectedExportFields = <?php echo json_encode(array_map(function ($field) {
                                        return $field['enabled'];
                                    }, $export_fields)); ?>;
    const syncEndpoint = <?= json_encode(app_url('api/sync_controles.php')) ?>;
    const testSyncEndpoint = <?= json_encode(app_url('api/test_sync_connection.php')) ?>;
    const syncCsrfToken = <?= json_encode($csrf_token) ?>;

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
                    return data.replace(new RegExp(textContent.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'),
                        textContent.replace(regex, '<mark>$1</mark>'));
                }
                return data;
            }
            return data.replace(regex, '<mark>$1</mark>');
        }
        return data;
    }

    const gradeOrder = ['GENA', 'GAM', 'LTGEN', 'AMR', 'GENMAJ', 'VAM', 'GENBDE', 'CAM', 'COL', 'CPV',
        'LTCOL', 'CPF', 'MAJ', 'CPC', 'CAPT', 'LDV', 'LT', 'EV', 'SLT', '2EV', 'A-C', 'MCP', 'A-1',
        '1MC',
        'ADJ', 'MRC', '1SM', '1MR', 'SM', '2MR', '1SGT', 'MR', 'SGT', 'QMT', 'CPL', '1MT', '1CL', '2MT',
        '2CL', 'MT', 'REC', 'ASK', 'COMD'
    ];

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
        order: [
            [8, 'desc']
        ], // Tri par date (colonne cachée, index 8)
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        scrollX: false,
        scrollY: false,
        scrollCollapse: false,
        paging: true,
        columnDefs: [{
                orderable: false,
                targets: [0, 7]
            }, // checkbox et QR Code non triables
            {
                targets: [8],
                visible: false
            } // colonne date cachée
        ],
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

            // Ajouter l'icône de recherche avant le champ (en dehors du label)
            filterDiv.prepend(
                '<i class="fas fa-search search-icon" style="color: #2e7d32; font-size: 1rem;"></i>'
            );

            const searchLabel = filterDiv.find('label');
            searchLabel.css({
                'display': 'flex',
                'align-items': 'center',
                'margin-bottom': '0',
                'flex': '0 1 auto'
            });

            // Supprimer l'icône existante dans le label (celle de language.search)
            searchLabel.find('i').remove();

            // Supprimer le texte "Rechercher :"
            searchLabel.contents().filter(function() {
                return this.nodeType === 3;
            }).remove();

            // Ajout du bouton "Nouveau contrôle"
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
                $('#table-controles tbody tr').each(function() {
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
        $('#select-all').prop('checked', $('.row-checkbox:checked').length === $('.row-checkbox').length);
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
        if ($('#mention-filter').val()) tags.push(`Mention : ${$('#mention-filter').val()}`);
        if ($('#statut-filter').val()) tags.push(
            `Statut : ${$('#statut-filter').find('option:selected').text()}`);
        if ($('#zone-filter').val()) tags.push(
            `Zone : ${$('#zone-filter').find('option:selected').text()}`);
        if ($('#categorie-filter').val()) tags.push(
            `Catégorie : ${$('#categorie-filter').find('option:selected').text()}`);
        if ($('#garnison-filter').val()) tags.push(
            `Garnison : ${$('#garnison-filter').find('option:selected').text()}`);
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
        if (statutFilter !== '') {
            if (Number(statut) !== Number(statutFilter)) return false;
        }
        if (zoneFilter && zone !== zoneFilter) return false;
        if (categorieFilter && categorie !== categorieFilter) return false;
        if (garnisonFilter && garnison !== garnisonFilter) return false;
        if (dateDebut && dateIso && dateIso < dateDebut) return false;
        if (dateFin && dateIso && dateIso > dateFin) return false;

        return true;
    });

    $('#mention-filter, #statut-filter, #zone-filter, #categorie-filter, #garnison-filter, #date-debut, #date-fin')
        .on('change keyup', function() {
            table.draw();
            updateFilterTags();
        });

    $('#reset-filters').on('click', function() {
        $('#mention-filter, #statut-filter, #zone-filter, #categorie-filter, #garnison-filter, #date-debut, #date-fin')
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

    // Gestion du QR Code avec qrcode-generator
    $(document).on('click', '.btn-qr-code', function() {
        const info = $(this).data('info');

        // Remplir les champs d'informations dans la modal
        $('#qrMatricule').text(info.matricule || '');
        $('#qrNoms').text(info.noms || '');
        $('#qrGrade').text(info.grade || '');
        $('#qrDateControle').text(info.date_controle || '');
        $('#qrMention').text(info.mention || '');

        if (!info) {
            console.error("Aucune donnée trouvée dans data-info");
            return;
        }

        // Construction de la chaîne à encoder pour ENROL.NET / CTR.NET mobile
        const textToEncode = `CTR.NET:${JSON.stringify({
            source: info.source || 'ctr.net-fardc',
            payload_version: info.payload_version || 1,
            matricule: info.matricule || '',
            noms: info.noms || '',
            grade: info.grade || '',
            unite: info.unite || '',
            garnison: info.garnison || '',
            province: info.province || '',
            categorie: info.categorie || '',
            date_controle: info.date_controle || '',
            mention: info.mention || ''
        })}`;

        // Génération du QR code avec qrcode-generator
        const qr = qrcode(0, 'M'); // niveau de correction M
        qr.addData(textToEncode);
        qr.make();

        // Récupérer le canvas
        const canvas = document.getElementById('qrcodeCanvas');
        if (!canvas) {
            console.error("Canvas #qrcodeCanvas introuvable");
            return;
        }

        // Dimensions du canvas (ex: 300x300)
        const size = 300;
        canvas.width = size;
        canvas.height = size;

        const ctx = canvas.getContext('2d');
        const cellSize = size / qr.getModuleCount();

        // Dessiner le QR code
        for (let row = 0; row < qr.getModuleCount(); row++) {
            for (let col = 0; col < qr.getModuleCount(); col++) {
                ctx.fillStyle = qr.isDark(row, col) ? '#000000' : '#ffffff';
                ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
            }
        }

        // Ouvrir le modal
        $('#qrCodeModal').modal('show');
    });

    // Fonctions d'export (inchangées)
    function getZoneFilterLabel(zones) {
        if (zones && zones.length > 0) {
            const validZones = zones.filter(z => z && z !== 'N/A');
            if (validZones.length === 0) return "LISTE DES MILITAIRES CONTROLES";
            if (validZones.length === 1) return `LISTE DES MILITAIRES CONTROLES DE LA ${validZones[0]}`;
            const firstPart = validZones.slice(0, -1).join(', ');
            const last = validZones[validZones.length - 1];
            return `LISTE DES MILITAIRES CONTROLES DE LA ${firstPart} ET ${last}`;
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
            if (zonesList.length === 0) return "LISTE DES MILITAIRES CONTROLES";
            if (zonesList.length === 1) return `LISTE DES MILITAIRES CONTROLES DE LA ${zonesList[0]}`;
            const firstPart = zonesList.slice(0, -1).join(', ');
            const last = zonesList[zonesList.length - 1];
            return `LISTE DES MILITAIRES CONTROLES DE LA ${firstPart} ET ${last}`;
        }
    }

    const fieldOrder = ['serie', 'matricule', 'noms', 'grade', 'ancien_beneficiaire', 'beneficiaire',
        'lien_parente', 'unite', 'garnison', 'province', 'categorie', 'mention', 'observations',
        'zdef', 'date_controle'
    ];

    const fullLabels = {
        'serie': 'SÉRIE',
        'matricule': 'MATRICULE',
        'noms': 'NOMS',
        'grade': 'GRADE',
        'ancien_beneficiaire': 'ANCIEN BÉNÉFICIAIRE',
        'beneficiaire': 'BÉNÉFICIAIRE',
        'lien_parente': 'LIEN PARENTÉ',
        'unite': 'UNITÉ',
        'garnison': 'GARNISON',
        'province': 'PROVINCE',
        'categorie': 'CATÉGORIE',
        'mention': 'MENTION',
        'observations': 'OBSERVATIONS',
        'zdef': 'ZDEF',
        'date_controle': 'DATE CONTRÔLE'
    };

    const abbrLabels = {
        'serie': 'SÉRIE',
        'matricule': 'MATRICULE',
        'noms': 'NOMS',
        'grade': 'GRADE',
        'ancien_beneficiaire': 'ANC. BÉNÉF.',
        'beneficiaire': 'BÉNÉFICIAIRE',
        'lien_parente': 'LIEN',
        'unite': 'UNITÉ',
        'garnison': 'GARNISON',
        'province': 'PROVINCE',
        'categorie': 'CATÉGORIE',
        'mention': 'MENTION',
        'observations': 'OBN.',
        'zdef': 'ZDEF',
        'date_controle': 'DATE'
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

        rowData.matricule = $cells.eq(1).text().trim().toUpperCase();
        rowData.noms = $cells.eq(2).text().trim().toUpperCase();
        rowData.grade = $cells.eq(3).text().trim().toUpperCase();
        rowData.unite = $cells.eq(4).text().trim().toUpperCase();
        let obs = $cells.eq(5).text().trim().toUpperCase();
        obs = (obs === 'NULL') ? '' : obs;
        rowData.observations = obs;
        rowData.zdef = $cells.eq(6).text().trim().toUpperCase();

        rowData.ancien_beneficiaire = $row.attr('data-ancien-beneficiaire') || '';
        rowData.beneficiaire = $row.attr('data-beneficiaire') || '';
        let lien = $row.attr('data-lien-parente') || '';
        if (['FRÈRE', 'SŒUR', 'PÈRE', 'MÈRE'].includes(lien.toUpperCase())) lien = 'TUTEUR';
        rowData.lien_parente = lien;
        rowData.garnison = $row.attr('data-garnison') || '';
        rowData.province = $row.attr('data-province') || '';
        rowData.categorie = $row.attr('data-categorie-libelle') || $row.data('categorie') || '';
        rowData.mention = $row.attr('data-mention') || '';
        rowData.date_controle = $row.attr('data-date-controle') || '';
        rowData.zoneAttr = $row.data('zone') || '';

        return rowData;
    }

    function prepareExportData(useAbbreviatedHeaders) {
        const rows = getFilteredRows();
        if (rows.length === 0) return null;

        const rawRows = rows.map(row => extractRowData(row));

        rawRows.sort((a, b) => {
            const gradeA = a.grade || '';
            const gradeB = b.grade || '';
            const matriculeA = a.matricule || '';
            const matriculeB = b.matricule || '';
            const idxA = getGradeIndex(gradeA);
            const idxB = getGradeIndex(gradeB);
            if (idxA !== idxB) return idxA - idxB;
            return matriculeA.localeCompare(matriculeB);
        });

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

        const zonesSet = new Set();
        rawRows.forEach(row => {
            if (row.zoneAttr && row.zoneAttr !== 'N/A') zonesSet.add(row.zoneAttr);
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
                }
            );
            doc.setFont('times', 'italic');
            doc.text('Emails : infoigfardc2017@gmail.com; igfardc2017@yahoo.fr',
                pageWidth / 2, footerY + 5, {
                    align: 'center'
                }
            );
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

    // --- Export CSV ---
    $('#export-csv').on('click', function() {
        const filters = {
            mention: $('#mention-filter').val(),
            statut: $('#statut-filter').val(),
            zone: $('#zone-filter').val(),
            categorie: $('#categorie-filter').val(),
            garnison: $('#garnison-filter').val(),
            date_debut: $('#date-debut').val(),
            date_fin: $('#date-fin').val()
        };
        $.get('?ajax=log_export', {
            type: 'CSV',
            filtres: JSON.stringify(filters)
        });

        const exportData = prepareExportData(false);
        if (!exportData || exportData.data.length === 0) {
            return Swal.fire('Aucune donnée à exporter', '', 'info');
        }
        const csvHeaderLines = exportData.headerLines.map(line => line[0]);
        const csvContent = [...csvHeaderLines, '', exportData.headers.join(';'), ...exportData.data
            .map(
                r => r.join(';'))
        ].join('\n');
        const blob = new Blob(["\uFEFF" + csvContent], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `controles_${getTimestamp()}.csv`;
        link.click();
    });

    // --- Export Excel ---
    $('#export-excel').on('click', function() {
        const filters = {
            mention: $('#mention-filter').val(),
            statut: $('#statut-filter').val(),
            zone: $('#zone-filter').val(),
            categorie: $('#categorie-filter').val(),
            garnison: $('#garnison-filter').val(),
            date_debut: $('#date-debut').val(),
            date_fin: $('#date-fin').val()
        };
        $.get('?ajax=log_export', {
            type: 'Excel',
            filtres: JSON.stringify(filters)
        });

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
        XLSX.utils.book_append_sheet(wb, ws, 'CONTRÔLES');
        XLSX.writeFile(wb, `controles_${getTimestamp()}.xlsx`);
    });

    // --- Export PDF ---
    $('#export-pdf').on('click', async function() {
        const filters = {
            mention: $('#mention-filter').val(),
            statut: $('#statut-filter').val(),
            zone: $('#zone-filter').val(),
            categorie: $('#categorie-filter').val(),
            garnison: $('#garnison-filter').val(),
            date_debut: $('#date-debut').val(),
            date_fin: $('#date-fin').val()
        };
        $.get('?ajax=log_export', {
            type: 'PDF',
            filtres: JSON.stringify(filters)
        });

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
            link.download = `controles_${getTimestamp()}.pdf`;
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

    // --- Export ZIP ---
    $('#export-zip').on('click', async function() {
        const filters = {
            mention: $('#mention-filter').val(),
            statut: $('#statut-filter').val(),
            zone: $('#zone-filter').val(),
            categorie: $('#categorie-filter').val(),
            garnison: $('#garnison-filter').val(),
            date_debut: $('#date-debut').val(),
            date_fin: $('#date-fin').val()
        };
        $.get('?ajax=log_export', {
            type: 'ZIP',
            filtres: JSON.stringify(filters)
        });

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
            // 1. Fichier CSV
            const csvHeaderLines = exportDataFull.headerLines.map(line => line[0]);
            const csvContent = [
                ...csvHeaderLines,
                '',
                exportDataFull.headers.join(';'),
                ...exportDataFull.data.map(r => r.join(';'))
            ].join('\n');
            const csvBlob = new Blob(["\uFEFF" + csvContent], {
                type: 'text/csv;charset=utf-8;'
            });

            // 2. Fichier Excel (XLSX)
            const worksheetData = [
                ...exportDataFull.headerLines,
                [],
                exportDataFull.headers,
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
            XLSX.utils.book_append_sheet(wb, ws, 'CONTRÔLES');
            const excelBlob = new Blob([XLSX.write(wb, {
                bookType: 'xlsx',
                type: 'array'
            })], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            });

            // 3. Fichier PDF (avec en-têtes abrégés)
            const pdfBlob = await generatePDFBlob(exportDataAbbr);

            // Création de l'archive ZIP
            const zip = new JSZip();
            zip.file("controles.csv", csvBlob);
            zip.file("controles.xlsx", excelBlob);
            zip.file("controles.pdf", pdfBlob);

            const zipBlob = await zip.generateAsync({
                type: "blob"
            });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(zipBlob);
            link.download = `controles_${getTimestamp()}.zip`;
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

    // ========== Synchronisation avec saisie de l'adresse serveur ==========
    function isValidServerAddress(value) {
        const trimmedValue = (value || '').trim();
        if (!trimmedValue) {
            return false;
        }

        const serverAddressPattern = /^(https?:\/\/)?([a-zA-Z0-9.-]+|\[[0-9a-fA-F:]+\])(?::\d+)?(\/.*)?$/;
        return serverAddressPattern.test(trimmedValue);
    }

    function setSyncTestButtonState(button, state) {
        button.removeClass('pending success error');
        button.addClass(state);
    }

    function buildSyncErrorDetails(responseJson) {
        const data = responseJson && responseJson.data ? responseJson.data : {};
        const selection = data.selection || {};
        const details = [];

        const alreadySynced = Array.isArray(selection.already_synced_ids) ? selection.already_synced_ids.length : 0;
        const missingOrInvalid = Array.isArray(selection.missing_or_invalid_ids) ? selection.missing_or_invalid_ids.length : 0;
        const excludedNoMilitaire = Array.isArray(selection.excluded_no_militaire_ids) ? selection.excluded_no_militaire_ids.length : 0;
        const invalidRaw = Array.isArray(selection.invalid_raw_ids) ? selection.invalid_raw_ids.length : 0;

        if (alreadySynced > 0) details.push(`${alreadySynced} déjà synchronisé(s)`);
        if (missingOrInvalid > 0) details.push(`${missingOrInvalid} introuvable(s)/invalide(s)`);
        if (excludedNoMilitaire > 0) details.push(`${excludedNoMilitaire} sans militaire associé`);
        if (invalidRaw > 0) details.push(`${invalidRaw} ID(s) invalide(s)`);

        if (details.length === 0) {
            return '';
        }

        return `Détails: ${details.join(', ')}.`;
    }

    function resolveSyncErrorMessage(responseJson) {
        const errorCode = responseJson && responseJson.error_code ? responseJson.error_code : '';
        const apiMessage = responseJson && responseJson.message ? responseJson.message : '';

        const mappedMessages = {
            INVALID_SELECTION_EMPTY: 'Données invalides: aucun contrôle sélectionné.',
            INVALID_SELECTION_IDS: 'Données invalides: la sélection contient des identifiants non valides.',
            INVALID_SELECTION_NOT_FOUND: 'Données invalides: les contrôles sélectionnés sont introuvables.',
            INVALID_SELECTION_NO_ELIGIBLE: 'Données invalides: les éléments sélectionnés existent déjà ou ne sont pas synchronisables.',
            INVALID_SELECTION_MATRICULES: 'Données invalides: matricules manquants dans la sélection.',
            INVALID_SELECTION_NO_MILITAIRE: 'Données invalides: aucun militaire associé aux contrôles sélectionnés.',
            EMPTY_JSON_BODY: 'Requête invalide: payload JSON vide.',
            INVALID_JSON: 'Requête invalide: JSON mal formé.',
            INVALID_JSON_TYPE: 'Requête invalide: le payload doit être un objet JSON.',
            SERVER_ADDRESS_MISSING: 'Adresse du serveur manquante.',
            SERVER_ADDRESS_INVALID: 'Adresse du serveur invalide.',
            REMOTE_CONNECTION_FAILED: 'Connexion impossible avec le serveur distant.',
            REMOTE_REJECTED: 'Le serveur distant a rejeté la synchronisation.'
        };

        const baseMessage = mappedMessages[errorCode] || apiMessage || 'Impossible de joindre le service de synchronisation.';
        const details = buildSyncErrorDetails(responseJson);

        return details ? `${baseMessage}\n${details}` : baseMessage;
    }

    $('#syncForm').on('submit', function(e) {
        e.preventDefault();
        window.location.href = <?= json_encode(app_url('modules/controles/sync.php')) ?>;
    });
    // ========== Fin synchronisation ==========

    // ========== Auto-refresh après contrôle mobile ==========
    let lastControleId =
        <?php echo intval($pdo->query("SELECT COALESCE(MAX(id),0) FROM controles")->fetchColumn()); ?>;

    function showMobileToast(noms, mention, matricule) {
        const container = document.getElementById('mobile-toast-container');
        if (!container) return;

        let type = 'success';
        let icon = 'fa-check-circle';
        if (mention === 'Favorable') {
            type = 'warning';
            icon = 'fa-thumbs-up';
        } else if (mention === 'Défavorable') {
            type = 'error';
            icon = 'fa-thumbs-down';
        } else {
            icon = 'fa-user-check';
        }

        const toast = document.createElement('div');
        toast.className = `toast-message ${type}`;
        toast.innerHTML = `
            <i class="fas fa-mobile-alt"></i>
            <span><i class="fas ${icon}"></i> <strong>${mention}</strong> — ${noms || matricule} <small>(${matricule})</small></span>
        `;
        container.appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 3500);
    }

    setInterval(function() {
        $.getJSON('../../api/controles_poll.php?since_id=' + lastControleId, function(resp) {
            if (resp.success && resp.count > 0) {
                resp.nouveaux.forEach(function(c) {
                    showMobileToast(
                        $('<span>').text(c.noms || '').html(),
                        $('<span>').text(c.mention || '').html(),
                        $('<span>').text(c.matricule || '').html()
                    );
                });
                lastControleId = resp.max_id;
                // Recharger le DataTable
                location.reload();
            }
        }).fail(function(xhr) {
            console.warn('Poll contrôles échoué:', xhr.status);
        });
    }, 10000);
    // ========== Fin auto-refresh ==========
});
</script>