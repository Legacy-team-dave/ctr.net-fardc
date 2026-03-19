<?php
// modules/rapports/index.php
require_once '../../includes/functions.php';
require_login();

$page_titre = 'Générer Rapport - Effectifs et Contrôles';
include '../../includes/header.php';

// ============================================
// Configuration et initialisation
// ============================================
$traductions_categories = [
    'ACTIF' => 'Actif',
    'DCD_AP_BIO' => 'Décédé Après Bio',
    'INTEGRES' => 'Intégré',
    'RETRAITES' => 'Retraité',
    'DCD_AV_BIO' => 'Décédé Avant Bio'
];

$ordre_categories = ['ACTIF', 'DCD_AP_BIO', 'INTEGRES', 'RETRAITES', 'DCD_AV_BIO'];

$couleurs_css = [
    'ACTIF' => 'categorie-actif',
    'DCD_AP_BIO' => 'categorie-decede-apres',
    'INTEGRES' => 'categorie-integre',
    'RETRAITES' => 'categorie-retraite',
    'DCD_AV_BIO' => 'categorie-decede-avant'
];

function traduireCategorie($code)
{
    global $traductions_categories;
    return $traductions_categories[$code] ?? $code;
}

// ============================================
// Récupération des filtres
// ============================================
$province_filter = $_GET['province'] ?? '';
$garnisons_filter = $_SESSION['filtres']['garnisons'] ?? [];
$categories_filter = $_SESSION['filtres']['categories'] ?? [];

$where_clause_filtre = " WHERE 1=1";
$params_filtre = [];

if (!empty($province_filter)) {
    $where_clause_filtre .= " AND m.province = ?";
    $params_filtre[] = $province_filter;
}
if (!empty($garnisons_filter)) {
    $placeholders = implode(',', array_fill(0, count($garnisons_filter), '?'));
    $where_clause_filtre .= " AND m.garnison IN ($placeholders)";
    $params_filtre = array_merge($params_filtre, $garnisons_filter);
}
if (!empty($categories_filter)) {
    $placeholders = implode(',', array_fill(0, count($categories_filter), '?'));
    $where_clause_filtre .= " AND m.categorie IN ($placeholders)";
    $params_filtre = array_merge($params_filtre, $categories_filter);
}

// ============================================
// Statistiques générales (filtrées)
// ============================================
$total_effectifs_filtre = $pdo->prepare("SELECT COUNT(*) FROM militaires m" . $where_clause_filtre);
$total_effectifs_filtre->execute($params_filtre);
$total_effectifs_filtre = $total_effectifs_filtre->fetchColumn();

$total_controles_filtre = $pdo->prepare("SELECT COUNT(DISTINCT c.matricule) FROM controles c JOIN militaires m ON c.matricule = m.matricule" . $where_clause_filtre);
$total_controles_filtre->execute($params_filtre);
$total_controles_filtre = $total_controles_filtre->fetchColumn();

$total_non_vus_filtre = $total_effectifs_filtre - $total_controles_filtre;

// ============================================
// TABLEAU 1 : Effectifs filtrés par catégorie (sans %)
// ============================================
$sql_tableau1 = "
    SELECT m.categorie,
           COUNT(*) as total_effectifs,
           SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) as total_controles,
           SUM(CASE WHEN c.id IS NULL THEN 1 ELSE 0 END) as total_non_vus
    FROM militaires m
    LEFT JOIN controles c ON m.matricule = c.matricule
    " . $where_clause_filtre . "
    GROUP BY m.categorie
    ORDER BY FIELD(m.categorie, 'ACTIF', 'DCD_AP_BIO', 'INTEGRES', 'RETRAITES', 'DCD_AV_BIO')
";
$stmt = $pdo->prepare($sql_tableau1);
$stmt->execute($params_filtre);
$tableau1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_t1_effectifs = $total_t1_controles = $total_t1_non_vus = 0;
foreach ($tableau1 as &$cat) {
    $total_t1_effectifs += $cat['total_effectifs'];
    $total_t1_controles += $cat['total_controles'];
    $total_t1_non_vus += $cat['total_non_vus'];
    $cat['libelle'] = traduireCategorie($cat['categorie']);
}

// ============================================
// TABLEAU 2 : Récapitulatif par grade (ordre personnalisé)
// ============================================
$gradeOrder = [
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

$fieldList = "'" . implode("','", $gradeOrder) . "'";

$sql_tableau2 = "
    SELECT COALESCE(m.grade, 'Sans grade') as grade,
           COUNT(*) as total_effectifs,
           SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) as total_controles,
           SUM(CASE WHEN c.id IS NULL THEN 1 ELSE 0 END) as total_non_vus
    FROM militaires m
    LEFT JOIN controles c ON m.matricule = c.matricule
    GROUP BY grade
    ORDER BY 
        CASE 
            WHEN grade = 'Sans grade' THEN 9999
            ELSE FIELD(grade, $fieldList)
        END
";
$stmt2 = $pdo->query($sql_tableau2);
$tableau2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$total_t2_effectifs = $total_t2_controles = $total_t2_non_vus = 0;
foreach ($tableau2 as &$grade) {
    $total_t2_effectifs  += $grade['total_effectifs'];
    $total_t2_controles  += $grade['total_controles'];
    $total_t2_non_vus    += $grade['total_non_vus'];
}

// ============================================
// Filtres actifs
// ============================================
$filtres_actifs = [];
if (!empty($province_filter)) $filtres_actifs[] = "Province: $province_filter";
if (!empty($garnisons_filter)) $filtres_actifs[] = "Garnisons: " . implode(', ', $garnisons_filter);
if (!empty($categories_filter)) {
    $libelles = array_map('traduireCategorie', $categories_filter);
    $filtres_actifs[] = "Catégories: " . implode(', ', $libelles);
}

$date_rapport = date('d/m/Y H:i:s');
?>

<!-- Inclusion locale de Font Awesome -->
<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">

<style>
/* Styles généraux */
body {
    font-family: 'Barlow', sans-serif;
    background: #f5f5f5;
    padding: 20px;
}

.rapport-container {
    max-width: 1400px;
    margin: 0 auto;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    padding: 20px;
    position: relative;
}

.watermark {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-45deg);
    opacity: 0.1;
    z-index: -1;
    width: 300px;
}

.rapport-header {
    text-align: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #2e7d32;
}

.header-logo {
    text-align: left;
    margin-bottom: 10px;
}

.header-logo img {
    height: 60px;
    width: auto;
}

.rapport-header .sous-titre {
    color: #2e7d32;
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.rapport-header h1 {
    color: #333;
    font-weight: 600;
    font-size: 1.8rem;
    margin: 0 0 5px;
}

.rapport-header .date {
    color: #666;
    font-size: 0.9rem;
}

.rapport-footer {
    margin-top: 30px;
    padding-top: 15px;
    border-top: 2px solid #2e7d32;
    text-align: center;
    position: relative;
    display: flex;
    flex-direction: column;
}

.qr-code-footer {
    position: absolute;
    bottom: 5px;
    right: 0;
    width: 35px;
    height: 35px;
    z-index: 10;
}

.qr-code-footer img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.drapeau-rdc {
    display: flex;
    height: 3px;
    width: 100%;
    margin-bottom: 5px;
}

.drapeau-rdc .bleu {
    flex: 1;
    background: #0033A0;
}

.drapeau-rdc .jaune {
    flex: 1;
    background: #FFD100;
}

.drapeau-rdc .rouge {
    flex: 1;
    background: #CE1126;
}

.footer-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    min-height: 40px;
}

.footer-text {
    font-size: 0.65rem;
    color: #666;
    line-height: 1.3;
    text-align: center;
    flex: 1;
    padding: 0 40px;
}

.filtre-info {
    background: linear-gradient(135deg, #2e7d32, #1b5e20);
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.filtres-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 5px;
}

.filtre-tag {
    background: rgba(255, 255, 255, 0.2);
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    border: 1px solid #ffc107;
}

/* Statistiques globales - 3 cartes */
.stats-globales {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 15px 10px;
    text-align: center;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
}

.stat-card .stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    color: white;
    font-size: 1.3rem;
}

/* Couleurs des cartes */
.stat-card:nth-child(1) {
    border-left: none;
}

.stat-card:nth-child(1) .stat-icon {
    background: linear-gradient(135deg, #3498db, #2980b9);
}

.stat-card:nth-child(1) .stat-valeur {
    color: #3498db;
}

.stat-card:nth-child(2) {
    border-left: none;
}

.stat-card:nth-child(2) .stat-icon {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
}

.stat-card:nth-child(2) .stat-valeur {
    color: #2ecc71;
}

.stat-card:nth-child(3) {
    border-left: none;
}

.stat-card:nth-child(3) .stat-icon {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
}

.stat-card:nth-child(3) .stat-valeur {
    color: #e74c3c;
}

.stat-card .stat-valeur {
    font-size: 1.8rem;
    font-weight: 700;
    /* passage en gras */
    line-height: 1.2;
}

.stat-card .stat-label {
    color: #666;
    font-size: 0.8rem;
    text-transform: none;
}

.section-title {
    margin: 25px 0 10px;
    padding-bottom: 8px;
    border-bottom: 2px solid #ffc107;
    color: #2e7d32;
    font-weight: 600;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
}

.section-title i {
    margin-right: 8px;
    font-size: 1.2rem;
}

.table-rapport {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
    font-size: 0.9rem;
}

.table-rapport thead {
    background: linear-gradient(135deg, #2e7d32, #1b5e20);
    color: white;
}

.table-rapport th {
    padding: 10px 8px;
    font-weight: 600;
    font-size: 0.9rem;
    text-align: center;
}

.table-rapport th:first-child {
    text-align: left;
}

.table-rapport td {
    padding: 8px;
    border-bottom: 1px solid #e0e0e0;
    color: #000000;
    text-align: center;
    font-weight: 400;
}

.table-rapport td:first-child {
    text-align: left;
}

.table-rapport tfoot td {
    padding: 8px;
    border-top: 2px solid #2e7d32;
    color: #000000;
    text-align: center;
    font-weight: 700;
}

.table-rapport tfoot td:first-child {
    text-align: left;
}

.table-rapport td .categorie-badge {
    text-align: left;
    display: inline-block;
    font-weight: 400;
}

/* Badges catégories - nouvelles couleurs */
.categorie-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 400;
}

.categorie-actif {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
    border: 1px solid #28a745;
}

.categorie-decede-apres {
    background: rgba(111, 66, 193, 0.1);
    color: #6f42c1;
    border: 1px solid #6f42c1;
}

.categorie-integre {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    border: 1px solid #dc3545;
}

.categorie-retraite {
    background: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
    border: 1px solid #0d6efd;
}

.categorie-decede-avant {
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
    border: 1px solid #6c757d;
}

.valeur-positive,
.valeur-negative,
.valeur-neutre,
.valeur-info {
    color: #000000 !important;
    font-weight: 400;
}

.actions-impression {
    margin-top: 25px;
    display: flex;
    gap: 6px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-print,
.btn-pdf,
.btn-excel,
.btn-csv,
.btn-back {
    padding: 6px 12px;
    border: none;
    border-radius: 5px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    white-space: nowrap;
}

.btn-print {
    background: linear-gradient(135deg, #2e7d32, #1b5e20);
    color: white;
}

.btn-pdf {
    background: linear-gradient(135deg, #dc3545, #bd2130);
    color: white;
}

.btn-excel {
    background: linear-gradient(135deg, #28a745, #1e7e34);
    color: white;
}

.btn-csv {
    background: linear-gradient(135deg, #17a2b8, #138496);
    color: white;
}

.btn-back {
    background: linear-gradient(135deg, #6c757d, #545b62);
    color: white;
}

.btn-print:hover,
.btn-pdf:hover,
.btn-excel:hover,
.btn-csv:hover,
.btn-back:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
}

@media print {
    body {
        background: white;
        padding: 0;
    }

    .rapport-container {
        box-shadow: none;
        padding: 15px;
    }

    .actions-impression,
    .no-print {
        display: none;
    }

    .stat-card {
        border: 1px solid #ddd;
    }

    .table-rapport thead {
        background: #2e7d32 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .table-rapport td,
    .table-rapport tfoot td,
    .table-rapport tfoot td strong,
    .valeur-positive,
    .valeur-negative,
    .valeur-neutre,
    .valeur-info {
        color: #000000 !important;
    }
}

@media (max-width: 768px) {
    .stats-globales {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .actions-impression {
        gap: 4px;
    }

    .btn-print,
    .btn-pdf,
    .btn-excel,
    .btn-csv,
    .btn-back {
        padding: 5px 8px;
        font-size: 0.7rem;
    }

    .footer-text {
        padding: 0 35px;
        font-size: 0.55rem;
    }

    .qr-code-footer {
        width: 30px;
        height: 30px;
    }
}
</style>

<div class="rapport-container">
    <div class="rapport-header">
        <div class="header-logo">
            <img src="../../assets/img/new-logo-ig-fardc.png" alt="Logo IG FARDC">
        </div>
        <div class="sous-titre">RÉPUBLIQUE DÉMOCRATIQUE DU CONGO</div>
        <h4>RAPPORT DES EFFECTIFS ET CONTRÔLES</h4>
        <div class="date">Kinshasa, le <?= $date_rapport ?></div>
    </div>

    <img src="../../assets/img/filigrane_logo_ig_fardc.png" class="watermark" alt="Filigrane">

    <?php if (!empty($filtres_actifs)): ?>
    <div class="filtre-info">
        <div class="filtres-list">
            <?php foreach ($filtres_actifs as $filtre): ?>
            <span class="filtre-tag"><i class="fas fa-tag"></i> <?= htmlspecialchars($filtre) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="stats-globales">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-valeur"><?= number_format($total_effectifs_filtre, 0, ',', ' ') ?></div>
            <div class="stat-label">Effectifs Prévus</div>
        </div>
        <div class="stat-card">
            <!-- Icône changée pour clipboard-list -->
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-valeur"><?= number_format($total_controles_filtre, 0, ',', ' ') ?></div>
            <div class="stat-label">Total Contrôlés</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-eye-slash"></i></div>
            <div class="stat-valeur"><?= number_format($total_non_vus_filtre, 0, ',', ' ') ?></div>
            <div class="stat-label">Non-vus</div>
        </div>
    </div>

    <!-- TABLEAU 1 : Effectifs par catégorie -->
    <div class="section-title"><i class="fas fa-chart-pie"></i> Effectifs par catégorie</div>
    <table class="table-rapport" id="tableau1">
        <thead>
            <tr>
                <th>Catégorie</th>
                <th>Effectifs Prévus</th>
                <th>Contrôlés</th>
                <th>Non-vus</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tableau1 as $row): ?>
            <tr>
                <td><span class="categorie-badge <?= $couleurs_css[$row['categorie']] ?>"><?= $row['libelle'] ?></span>
                </td>
                <td class="valeur-positive"><?= number_format($row['total_effectifs'], 0, ',', ' ') ?></td>
                <td class="valeur-positive"><?= number_format($row['total_controles'], 0, ',', ' ') ?></td>
                <td class="valeur-negative"><?= number_format($row['total_non_vus'], 0, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAUX</td>
                <td class="valeur-positive"><?= number_format($total_t1_effectifs, 0, ',', ' ') ?></td>
                <td class="valeur-positive"><?= number_format($total_t1_controles, 0, ',', ' ') ?></td>
                <td class="valeur-negative"><?= number_format($total_t1_non_vus, 0, ',', ' ') ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- TABLEAU 2 : Récapitulatif par grade -->
    <div class="section-title"><i class="fas fa-ranking-star"></i> Récapitulatif par grade</div>
    <table class="table-rapport" id="tableau2">
        <thead>
            <tr>
                <th>Grade</th>
                <th>Effectifs Prévus</th>
                <th>Contrôlés</th>
                <th>Non-vus</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tableau2 as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['grade']) ?></td>
                <td class="valeur-positive"><?= number_format($row['total_effectifs'], 0, ',', ' ') ?></td>
                <td class="valeur-positive"><?= number_format($row['total_controles'], 0, ',', ' ') ?></td>
                <td class="valeur-negative"><?= number_format($row['total_non_vus'], 0, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAUX</td>
                <td class="valeur-positive"><?= number_format($total_t2_effectifs, 0, ',', ' ') ?></td>
                <td class="valeur-positive"><?= number_format($total_t2_controles, 0, ',', ' ') ?></td>
                <td class="valeur-negative"><?= number_format($total_t2_non_vus, 0, ',', ' ') ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="rapport-footer">
        <div class="drapeau-rdc">
            <div class="bleu"></div>
            <div class="jaune"></div>
            <div class="rouge"></div>
        </div>
        <div class="footer-content">
            <div class="footer-text">
                <p>Inspectorat Général FARDC, Avenue des écuries, N°54, Quartier Joli Parc, Commune de NGALIEMA</p>
                <p>Emails : infoigfardc2017@gmail.com; igfardc2017@yahoo.fr</p>
            </div>
            <div class="qr-code-footer">
                <img src="../../assets/img/qr-code-ig-fardc.png" alt="QR Code IG FARDC">
            </div>
        </div>
    </div>

    <div class="actions-impression no-print">
        <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Imprimer</button>
        <button id="export-pdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
        <button id="export-excel" class="btn-excel"><i class="fas fa-file-excel"></i> Excel</button>
        <button id="export-csv" class="btn-csv"><i class="fas fa-file-csv"></i> CSV</button>
        <a href="javascript:history.back()" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
</div>

<!-- Scripts avec CDN -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function() {
    const getTimestamp = () => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}_${String(d.getHours()).padStart(2,'0')}h${String(d.getMinutes()).padStart(2,'0')}`;
    };

    const getTableData = (tableId, headers) => ({
        headers,
        rows: Array.from(document.querySelectorAll(`#${tableId} tbody tr`)).map(row =>
            Array.from(row.cells).map((cell, i) => {
                if (i === 0) return cell.querySelector('.categorie-badge')?.textContent.trim() ||
                    cell.textContent.trim();
                return cell.textContent.trim().replace(/\s/g, '');
            })
        ),
        footers: Array.from(document.querySelectorAll(`#${tableId} tfoot tr`)).map(row =>
            Array.from(row.cells).map(cell => cell.textContent.trim().replace(/\s/g, ''))
        )
    });

    const getRapport = () => ({
        date: document.querySelector('.rapport-header .date')?.textContent.replace('Kinshasa, le ', '') ||
            new Date().toLocaleString('fr-FR'),
        filtres: Array.from(document.querySelectorAll('.filtre-tag')).map(t => t.textContent.trim()),
        stats: Array.from(document.querySelectorAll('.stat-card')).map(c => [
            c.querySelector('.stat-label')?.textContent.trim() || '',
            c.querySelector('.stat-valeur')?.textContent.trim() || ''
        ]),
        t1: getTableData('tableau1', ['CATÉGORIE', 'EFFECTIFS PRÉVUS', 'CONTRÔLÉS', 'NON-VUS']),
        t2: getTableData('tableau2', ['GRADE', 'EFFECTIFS PRÉVUS', 'CONTRÔLÉS', 'NON-VUS'])
    });

    const loadImage = async (relativePath) => {
        const baseUrl = window.location.origin + window.location.pathname.split('/').slice(0, -3).join('/');
        const fullUrl = baseUrl + '/' + relativePath.replace(/^\.\.\/\.\.\//, '');
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);
        try {
            const resp = await fetch(fullUrl, {
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            if (!resp.ok) throw new Error(`HTTP ${resp.status} - ${resp.url}`);
            const blob = await resp.blob();
            const dataURL = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
            const img = new Image();
            img.src = dataURL;
            await new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = reject;
            });
            return {
                dataURL,
                width: img.width,
                height: img.height
            };
        } catch (err) {
            clearTimeout(timeoutId);
            console.warn('Échec chargement image:', fullUrl, err);
            return null;
        }
    };

    $('#export-csv').click(() => {
        const r = getRapport();
        const lines = [
            'RAPPORT DES EFFECTIFS ET CONTRÔLES',
            `Kinshasa, le ${r.date}`,
            r.filtres.length ? `Filtres: ${r.filtres.join('; ')}` : '',
            '',
            'STATISTIQUES',
            'Indicateur;Valeur',
            ...r.stats.map(s => s.join(';')),
            '',
            'TABLEAU 1;' + r.t1.headers.join(';'),
            ...r.t1.rows.map(r => r.join(';')),
            'TOTAUX;' + r.t1.footers[0].join(';'),
            '',
            'TABLEAU 2;' + r.t2.headers.join(';'),
            ...r.t2.rows.map(r => r.join(';')),
            'TOTAUX;' + r.t2.footers[0].join(';')
        ].filter(Boolean).join('\n');

        const blob = new Blob(['\uFEFF' + lines], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `rapport_effectifs_${getTimestamp()}.csv`;
        link.click();
    });

    $('#export-excel').click(() => {
        const r = getRapport();
        const wb = XLSX.utils.book_new();

        XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet([
            ['RAPPORT DES EFFECTIFS ET CONTRÔLES'],
            [`Kinshasa, le ${r.date}`],
            [],
            ...(r.filtres.length ? [
                ['Filtres actifs:', r.filtres.join(', ')],
                []
            ] : []),
            ['STATISTIQUES GLOBALES'],
            ['Indicateur', 'Valeur'],
            ...r.stats
        ]), 'Résumé');

        XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet([
            r.t1.headers, ...r.t1.rows, ['TOTAUX', ...r.t1.footers[0].slice(1)]
        ]), 'Par catégorie');

        XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet([
            r.t2.headers, ...r.t2.rows, ['TOTAUX', ...r.t2.footers[0].slice(1)]
        ]), 'Par grade');

        XLSX.writeFile(wb, `rapport_effectifs_${getTimestamp()}.xlsx`);
    });

    $('#export-pdf').on('click', async () => {
        const r = getRapport();

        Swal.fire({
            title: 'Préparation du PDF...',
            text: 'Chargement des ressources',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const [logo, qrCode, watermark] = await Promise.all([
            loadImage('../../assets/img/new-logo-ig-fardc.png'),
            loadImage('../../assets/img/qr-code-ig-fardc.png'),
            loadImage('../../assets/img/filigrane_logo_ig_fardc.png')
        ]);

        Swal.close();

        const {
            jsPDF
        } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });
        const m = 10,
            pw = doc.internal.pageSize.width,
            ph = doc.internal.pageSize.height;

        let isFirstPage = true,
            page = 1;

        const headerFooter = () => {
            if (watermark) {
                doc.saveGraphicsState();
                doc.setGState(new doc.GState({
                    opacity: 0.1
                }));
                const ww = 70,
                    wh = (watermark.height / watermark.width) * ww;
                doc.addImage(watermark.dataURL, 'PNG', pw / 2 - ww / 2, ph / 2 - wh / 2, ww, wh);
                doc.restoreGraphicsState();
            }

            if (isFirstPage) {
                if (logo) {
                    const lh = 20,
                        lw = (logo.width / logo.height) * lh;
                    doc.addImage(logo.dataURL, 'PNG', m, 5, lw, lh);
                }
                doc.setFontSize(7);
                doc.setTextColor(100);
                doc.text(r.date, pw - m, 10, {
                    align: 'right'
                });
                doc.setFontSize(10);
                doc.setTextColor(46, 125, 50);
                doc.text('RÉPUBLIQUE DÉMOCRATIQUE DU CONGO - FARDC', pw / 2, 28, {
                    align: 'center'
                });
                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(0, 0, 0);
                doc.text('RAPPORT DES EFFECTIFS ET CONTRÔLES', pw / 2, 36, {
                    align: 'center'
                });
                doc.setFont(undefined, 'normal');
                if (r.filtres.length) {
                    doc.setFontSize(7);
                    doc.setTextColor(100);
                    doc.text('Filtres: ' + r.filtres.join(' • '), pw / 2, 42, {
                        align: 'center'
                    });
                }
                doc.setDrawColor(46, 125, 50);
                doc.setLineWidth(0.3);
                doc.line(m, 45, pw - m, 45);
                isFirstPage = false;
            }

            const fy = ph - 18,
                ly = fy + 2,
                seg = (pw - 2 * m) / 3,
                qs = 8;
            if (qrCode) {
                doc.addImage(qrCode.dataURL, 'PNG', pw - m - qs - 2, ly - 2, qs, qs);
            }
            doc.setDrawColor(0, 51, 160);
            doc.line(m, ly, m + seg, ly);
            doc.setDrawColor(255, 209, 0);
            doc.line(m + seg, ly, m + 2 * seg, ly);
            doc.setDrawColor(206, 17, 38);
            doc.line(m + 2 * seg, ly, m + 3 * seg, ly);
            doc.setFontSize(4.5);
            doc.setTextColor(100);
            doc.text(
                'Inspectorat Général FARDC, Avenue des écuries, N°54, Quartier Joli Parc, Commune de NGALIEMA',
                pw / 2, ly + 3, {
                    align: 'center'
                });
            doc.text('Emails : infoigfardc2017@gmail.com; igfardc2017@yahoo.fr',
                pw / 2, ly + 5.5, {
                    align: 'center'
                });
            doc.setFontSize(7);
            doc.text(`Page ${page}`, pw - m - qs - 5, ly + 9, {
                align: 'right'
            });
            page++;
        };

        doc.autoTable({
            startY: 50,
            head: [
                ['Indicateur', 'Valeur']
            ],
            body: r.stats,
            theme: 'grid',
            styles: {
                fontSize: 8,
                cellPadding: 2,
                textColor: [0, 0, 0]
            },
            headStyles: {
                fillColor: [46, 125, 50],
                textColor: [255, 255, 255]
            },
            margin: {
                left: m,
                right: m
            },
            didDrawPage: headerFooter
        });

        let y = doc.lastAutoTable.finalY + 8;

        doc.setFontSize(10);
        doc.setTextColor(46, 125, 50);
        doc.text('Effectifs par catégorie', m, y);
        doc.autoTable({
            startY: y + 4,
            head: [r.t1.headers],
            body: r.t1.rows,
            foot: [
                ['TOTAUX', ...r.t1.footers[0].slice(1)]
            ],
            theme: 'grid',
            styles: {
                fontSize: 7,
                cellPadding: 1.5,
                textColor: [0, 0, 0]
            },
            headStyles: {
                fillColor: [46, 125, 50],
                textColor: [255, 255, 255]
            },
            footStyles: {
                fillColor: [232, 245, 233],
                textColor: [0, 0, 0],
                fontStyle: 'bold'
            },
            margin: {
                left: m,
                right: m
            },
            didDrawPage: headerFooter
        });

        y = doc.lastAutoTable.finalY + 8;
        doc.setFontSize(10);
        doc.setTextColor(46, 125, 50);
        doc.text('Récapitulatif par grade', m, y);
        doc.autoTable({
            startY: y + 4,
            head: [r.t2.headers],
            body: r.t2.rows,
            foot: [
                ['TOTAUX', ...r.t2.footers[0].slice(1)]
            ],
            theme: 'grid',
            styles: {
                fontSize: 7,
                cellPadding: 1.5,
                textColor: [0, 0, 0]
            },
            headStyles: {
                fillColor: [46, 125, 50],
                textColor: [255, 255, 255]
            },
            footStyles: {
                fillColor: [232, 245, 233],
                textColor: [0, 0, 0],
                fontStyle: 'bold'
            },
            margin: {
                left: m,
                right: m
            },
            didDrawPage: headerFooter
        });

        doc.save(`rapport_effectifs_${getTimestamp()}.pdf`);
    });
})();
</script>

<?php include '../../includes/footer.php'; ?>