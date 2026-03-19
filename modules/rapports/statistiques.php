<?php
require_once '../../includes/functions.php';
require_login();

check_profil(['ADMIN_IG']);

$page_titre = 'Statistiques';
include '../../includes/header.php';

global $pdo;

// ============================================
// TABLEAU DE TRADUCTION DES CATÉGORIES
// ============================================
$traductions_categories = [
    'ACTIF' => 'Actif',
    'DCD_AP_BIO' => 'Décédé Après Bio',
    'INTEGRES' => 'Intégré',
    'RETRAITES' => 'Retraité',
    'DCD_AV_BIO' => 'Décédé Avant Bio'
];

// ============================================
// ICÔNES PAR CATÉGORIE (pour les cards)
// ============================================
$icones_categorie = [
    'ACTIF'        => 'fa-user-check',
    'RETRAITES'    => 'fa-user-clock',
    'INTEGRES'     => 'fa-user-plus',
    'DCD_AV_BIO'   => 'fa-skull-crossbones',
    'DCD_AP_BIO'   => 'fa-skull'
];

// ============================================
// MAPPING DES ZONES DE DÉFENSE
// ============================================
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

// ============================================
// REQUÊTES STATISTIQUES OPTIMISÉES
// ============================================

// Effectif attendu
$effectif_attendu = $pdo->query("
    SELECT COUNT(*) as total FROM militaires
")->fetchColumn();

// Militaires non vus (comptage)
$stats_non_vus = $pdo->query("
    SELECT 
        COUNT(DISTINCT m.matricule) as total_militaires,
        COUNT(DISTINCT c.matricule) as militaires_controles,
        (COUNT(DISTINCT m.matricule) - COUNT(DISTINCT c.matricule)) as militaires_non_vus
    FROM militaires m
    LEFT JOIN controles c ON m.matricule = c.matricule
")->fetch();

// Statistiques globales
$stats_globales = $pdo->query("
    SELECT 
        COUNT(DISTINCT id) as total_controles,
        COUNT(DISTINCT type_controle) as total_types_controle,
        COUNT(DISTINCT mention) as total_mentions,
        COUNT(CASE WHEN mention = 'Favorable' THEN 1 END) as controles_favorables,
        COUNT(CASE WHEN mention = 'Défavorable' THEN 1 END) as controles_defavorables,
        COUNT(CASE WHEN mention = 'Présent' THEN 1 END) as controles_presents    FROM controles
")->fetch();

// Statistiques par catégorie
$stats_categories = $pdo->query("
    SELECT 
        m.categorie,
        COUNT(DISTINCT m.matricule) as total_militaires,
        COUNT(DISTINCT c.matricule) as militaires_controles,
        (COUNT(DISTINCT m.matricule) - COUNT(DISTINCT c.matricule)) as militaires_non_vus,
        COUNT(CASE WHEN c.mention = 'Favorable' THEN 1 END) as controles_favorables,
        COUNT(CASE WHEN c.mention = 'Présent' THEN 1 END) as controles_presents,
        COUNT(CASE WHEN c.mention = 'Défavorable' THEN 1 END) as controles_defavorables,
        COUNT(c.id) as total_controles,
        ROUND((COUNT(CASE WHEN c.mention = 'Favorable' THEN 1 END) / NULLIF(COUNT(c.id), 0)) * 100, 2) as taux_favorable,
        ROUND((COUNT(CASE WHEN c.mention = 'Présent' THEN 1 END) / NULLIF(COUNT(c.id), 0)) * 100, 2) as taux_present,
        ROUND((COUNT(CASE WHEN c.mention = 'Défavorable' THEN 1 END) / NULLIF(COUNT(c.id), 0)) * 100, 2) as taux_defavorable,
        ROUND((COUNT(DISTINCT c.matricule) / NULLIF(COUNT(DISTINCT m.matricule), 0)) * 100, 2) as taux_couverture
    FROM militaires m
    LEFT JOIN controles c ON m.matricule = c.matricule
    WHERE m.categorie IS NOT NULL AND m.categorie != ''
    GROUP BY m.categorie
    ORDER BY total_militaires DESC
    LIMIT 5
")->fetchAll();

// Statistiques par type de contrôle
$stats_types = $pdo->query("
    SELECT 
        type_controle,
        COUNT(*) as nombre,
        COUNT(DISTINCT matricule) as beneficiaires_distincts,
        COUNT(CASE WHEN mention = 'Favorable' THEN 1 END) as favorable,
        COUNT(CASE WHEN mention = 'Défavorable' THEN 1 END) as defavorable,
        COUNT(CASE WHEN mention = 'Présent' THEN 1 END) as present
    FROM controles
    GROUP BY type_controle
    ORDER BY nombre DESC
")->fetchAll();

// Statistiques par mention
$stats_mentions = $pdo->query("
    SELECT 
        mention,
        COUNT(*) as nombre,
        COUNT(DISTINCT type_controle) as types_concernes,
        GROUP_CONCAT(DISTINCT type_controle) as types_liste
    FROM controles
    WHERE mention IS NOT NULL AND mention != ''
    GROUP BY mention
    ORDER BY 
        CASE mention
            WHEN 'Favorable' THEN 1
            WHEN 'Présent' THEN 2
            WHEN 'Défavorable' THEN 3
            ELSE 4
        END
")->fetchAll();

// Évolution annuelle
$evolution_annuelle = $pdo->query("
    SELECT 
        YEAR(date_controle) as annee,
        COUNT(*) as nb_controles,
        COUNT(DISTINCT matricule) as nb_beneficiaires,
        COUNT(CASE WHEN mention = 'Favorable' THEN 1 END) as favorables,
        COUNT(CASE WHEN mention = 'Présent' THEN 1 END) as presents,
        COUNT(CASE WHEN mention = 'Défavorable' THEN 1 END) as defavorables
    FROM controles
    WHERE date_controle IS NOT NULL
    GROUP BY YEAR(date_controle)
    ORDER BY annee DESC
")->fetchAll();

// Liens de parenté (conservé pour l'affichage)
$stats_parente = $pdo->query("
    SELECT 
        lien_parente,
        COUNT(*) as nombre,
        COUNT(DISTINCT matricule) as beneficiaires_distincts,
        COUNT(CASE WHEN mention = 'Favorable' THEN 1 END) as favorables,
        COUNT(CASE WHEN mention = 'Présent' THEN 1 END) as presents,
        COUNT(CASE WHEN mention = 'Défavorable' THEN 1 END) as defavorables
    FROM controles
    WHERE lien_parente IS NOT NULL AND lien_parente != ''
    GROUP BY lien_parente
    ORDER BY nombre DESC
")->fetchAll();

// Top bénéficiaires (conservé)
$top_beneficiaires = $pdo->query("
    SELECT 
        c.matricule,
        c.nom_beneficiaire,
        COUNT(*) as nb_controles,
        COUNT(DISTINCT c.type_controle) as types_differents,
        COUNT(CASE WHEN c.mention = 'Favorable' THEN 1 END) as favorables,
        COUNT(CASE WHEN c.mention = 'Présent' THEN 1 END) as presents,
        COUNT(CASE WHEN c.mention = 'Défavorable' THEN 1 END) as defavorables
    FROM controles c
    GROUP BY c.matricule, c.nom_beneficiaire
    ORDER BY nb_controles DESC
    LIMIT 10
")->fetchAll();

// Performance par type (conservé)
$performance_types = $pdo->query("
    SELECT 
        type_controle,
        COUNT(*) as total,
        ROUND((COUNT(CASE WHEN mention = 'Favorable' THEN 1 END) / COUNT(*)) * 100, 2) as taux_reussite,
        ROUND((COUNT(CASE WHEN mention = 'Présent' THEN 1 END) / COUNT(*)) * 100, 2) as taux_presence,
        ROUND((COUNT(CASE WHEN mention = 'Défavorable' THEN 1 END) / COUNT(*)) * 100, 2) as taux_echec
    FROM controles
    GROUP BY type_controle
    HAVING total >= 3
    ORDER BY taux_reussite DESC
")->fetchAll();

// Derniers contrôles (sans la colonne Date ajout)
$derniers_controles = $pdo->query("
    SELECT 
        id,
        matricule,
        nom_beneficiaire,
        type_controle,
        mention,
        date_controle
    FROM controles
    ORDER BY cree_le DESC
    LIMIT 5
")->fetchAll();

// Liste des militaires non vus (pour export) - avec les champs supplémentaires
$non_vus_raw = $pdo->query("
    SELECT 
        m.matricule,
        m.noms,
        m.categorie,
        m.grade,
        m.unite,
        m.beneficiaire,
        m.garnison,
        m.province
    FROM militaires m
    LEFT JOIN controles c ON m.matricule = c.matricule
    WHERE c.matricule IS NULL
    ORDER BY m.categorie, m.noms
")->fetchAll();

// Ajout du calcul de la ZDEF pour chaque ligne
$non_vus_list = [];
foreach ($non_vus_raw as $row) {
    $zdef = getZdefValue($row['province']);
    $row['zdef'] = $zdef['value'];
    $non_vus_list[] = $row;
}

// ============================================
// FONCTIONS POUR LES EXPORTS
// ============================================

function getTimestamp()
{
    return date('Y-m-d_H\hi');
}

function loadImageWithDimensions($url)
{
    $imageData = @file_get_contents($url);
    if ($imageData === false) {
        return ['dataURL' => null, 'width' => 0, 'height' => 0];
    }
    $base64 = base64_encode($imageData);
    $dataURL = 'data:image/png;base64,' . $base64;

    $img = @imagecreatefromstring($imageData);
    if ($img === false) {
        return ['dataURL' => $dataURL, 'width' => 100, 'height' => 100];
    }
    $width = imagesx($img);
    $height = imagesy($img);
    imagedestroy($img);

    return ['dataURL' => $dataURL, 'width' => $width, 'height' => $height];
}

/**
 * Prépare les données pour l'export (sans les sections supprimées)
 */
function prepareExportData()
{
    global $effectif_attendu, $stats_globales, $stats_non_vus, $stats_categories, $stats_types, $stats_mentions, $evolution_annuelle, $traductions_categories;

    $data = [];

    // En-tête
    $data[] = ['RAPPORT STATISTIQUE DES CONTRÔLÉS'];
    $data[] = [''];

    // 1. Synthèse générale (fusion des trois premiers tableaux)
    $data[] = ['1. SYNTHÈSE GÉNÉRALE'];
    $data[] = ['Indicateur', 'Valeur'];
    $data[] = ['Total militaires (effectif attendu)', number_format($effectif_attendu ?? 0, 0, ',', ' ')];
    $data[] = ['Total contrôles effectués', $stats_globales['total_controles'] ?? 0];
    $data[] = ['Total types de contrôle', $stats_globales['total_types_controle'] ?? 0];
    $data[] = ['Total mentions', $stats_globales['total_mentions'] ?? 0];
    $data[] = ['Contrôles favorables', $stats_globales['controles_favorables'] ?? 0];
    $data[] = ['Contrôles présents', $stats_globales['controles_presents'] ?? 0];
    $data[] = ['Contrôles défavorables', $stats_globales['controles_defavorables'] ?? 0];
    $data[] = ['Militaires contrôlés', number_format($stats_non_vus['militaires_controles'] ?? 0, 0, ',', ' ')];
    $data[] = ['Militaires non vus', number_format($stats_non_vus['militaires_non_vus'] ?? 0, 0, ',', ' ')];
    $data[] = [''];

    // 2. Types de contrôle
    $data[] = ['2. TYPES DE CONTRÔLE'];
    $data[] = ['Type', 'Nombre', 'Bénéf. distincts', 'Fav.', 'Prés.', 'Déf.'];
    $total_nombre = 0;
    $total_benef = 0;
    $total_fav = 0;
    $total_pres = 0;
    $total_def = 0;
    foreach ($stats_types as $type) {
        $data[] = [
            strtolower($type['type_controle'] ?? 'N/A'),
            $type['nombre'] ?? 0,
            $type['beneficiaires_distincts'] ?? 0,
            $type['favorable'] ?? 0,
            $type['present'] ?? 0,
            $type['defavorable'] ?? 0
        ];
        $total_nombre += $type['nombre'] ?? 0;
        $total_benef += $type['beneficiaires_distincts'] ?? 0;
        $total_fav += $type['favorable'] ?? 0;
        $total_pres += $type['present'] ?? 0;
        $total_def += $type['defavorable'] ?? 0;
    }
    $data[] = ['TOTAL', $total_nombre, $total_benef, $total_fav, $total_pres, $total_def];
    $data[] = [''];

    // 3. Statistiques par catégorie
    $data[] = ['3. STATISTIQUES PAR CATÉGORIE'];
    $data[] = ['Catégorie', 'Effectif', 'Contrôlés', 'Non-vus', 'Taux couv.', 'Total contrôles', 'Fav.', 'Prés.', 'Déf.', '% Fav.', '% Prés.', '% Déf.'];
    $total_effectif = 0;
    $total_controles_cat = 0;
    $total_fav_cat = 0;
    $total_pres_cat = 0;
    $total_def_cat = 0;
    foreach ($stats_categories as $cat) {
        $categorie_originale = $cat['categorie'] ?? 'N/A';
        $categorie_traduite = $traductions_categories[$categorie_originale] ?? $categorie_originale;
        $data[] = [
            $categorie_traduite,
            $cat['total_militaires'] ?? 0,
            $cat['militaires_controles'] ?? 0,
            $cat['militaires_non_vus'] ?? 0,
            ($cat['taux_couverture'] ?? 0) . '%',
            $cat['total_controles'] ?? 0,
            $cat['controles_favorables'] ?? 0,
            $cat['controles_presents'] ?? 0,
            $cat['controles_defavorables'] ?? 0,
            ($cat['taux_favorable'] ?? 0) . '%',
            ($cat['taux_present'] ?? 0) . '%',
            ($cat['taux_defavorable'] ?? 0) . '%'
        ];
        $total_effectif += $cat['total_militaires'] ?? 0;
        $total_controles_cat += $cat['total_controles'] ?? 0;
        $total_fav_cat += $cat['controles_favorables'] ?? 0;
        $total_pres_cat += $cat['controles_presents'] ?? 0;
        $total_def_cat += $cat['controles_defavorables'] ?? 0;
    }
    $data[] = ['TOTAL', $total_effectif, '', '', '', $total_controles_cat, $total_fav_cat, $total_pres_cat, $total_def_cat, '', '', ''];
    $data[] = [''];

    // 4. Statistiques par mention
    $data[] = ['4. STATISTIQUES PAR MENTION'];
    $data[] = ['Mention', 'Nombre', 'Types concernés'];
    $total_nom_mention = 0;
    foreach ($stats_mentions as $mention) {
        $data[] = [$mention['mention'] ?? 'N/A', $mention['nombre'] ?? 0, $mention['types_concernes'] ?? 0];
        $total_nom_mention += $mention['nombre'] ?? 0;
    }
    $data[] = ['TOTAL', $total_nom_mention, ''];
    $data[] = [''];

    // 5. Évolution annuelle
    $data[] = ['5. ÉVOLUTION ANNUELLE'];
    $data[] = ['Année', 'Contrôles', 'Bénéf.', 'Fav.', 'Prés.', 'Déf.'];
    $total_controles_evol = 0;
    $total_benef_evol = 0;
    $total_fav_evol = 0;
    $total_pres_evol = 0;
    $total_def_evol = 0;
    foreach ($evolution_annuelle as $evol) {
        $data[] = [
            $evol['annee'] ?? 'N/A',
            $evol['nb_controles'] ?? 0,
            $evol['nb_beneficiaires'] ?? 0,
            $evol['favorables'] ?? 0,
            $evol['presents'] ?? 0,
            $evol['defavorables'] ?? 0
        ];
        $total_controles_evol += $evol['nb_controles'] ?? 0;
        $total_benef_evol += $evol['nb_beneficiaires'] ?? 0;
        $total_fav_evol += $evol['favorables'] ?? 0;
        $total_pres_evol += $evol['presents'] ?? 0;
        $total_def_evol += $evol['defavorables'] ?? 0;
    }
    $data[] = ['TOTAL', $total_controles_evol, $total_benef_evol, $total_fav_evol, $total_pres_evol, $total_def_evol];
    $data[] = [''];

    return $data;
}
?>

<!-- ============================================ -->
<!-- STYLES CSS COMPLETS (design original) -->
<!-- ============================================ -->
<style>
/* ===== STYLES ===== */
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

.modern-card .card-header h4 {
    color: white;
    margin: 0;
    font-weight: 600;
    font-size: 1.1rem;
}

.modern-card .card-header h4 i {
    margin-right: 8px;
}

.modern-card .card-body {
    padding: 25px;
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
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
}

.btn-export.excel {
    background: #1e7e34;
}

.btn-export.excel:hover {
    background: #19692c;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(30, 126, 52, 0.3);
}

.btn-export.pdf {
    background: #dc3545;
}

.btn-export.pdf:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
}

.btn-print {
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
    background: #6c757d;
}

.btn-print:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
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

/* Grille de statistiques clés - 3 boutons sur une seule ligne */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 15px 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s;
    border: 1px solid rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(46, 125, 50, 0.15);
}

.stat-card::before {
    display: none;
}

.stat-card:nth-child(1) .stat-icon {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
}

.stat-card:nth-child(2) .stat-icon {
    background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
    box-shadow: 0 4px 10px rgba(76, 175, 80, 0.3);
}

.stat-card:nth-child(3) .stat-icon {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-value {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1.2;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #2e7d32;
}

.stat-label {
    margin: 0;
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 500;
    text-transform: none;
    letter-spacing: 0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stat-change {
    font-size: 0.75rem;
    margin-top: 3px;
    display: flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
}

.stat-change.positive {
    color: #28a745;
}

.stat-change.neutral {
    color: #6c757d;
}

.stat-change.warning {
    color: #dc3545;
}

/* Grilles */
.grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

/* Catégories cards - couleurs modifiées */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.category-card {
    background: white;
    border-radius: 15px;
    padding: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    transition: all 0.3s;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.category-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(46, 125, 50, 0.15);
}

.category-title {
    font-size: 1rem;
    font-weight: 600;
    color: #2e7d32;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid #e9ecef;
}

.category-title i {
    margin-right: 8px;
}

.category-stats {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.category-stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
}

.category-stat-label {
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 5px;
}

.category-stat-value {
    font-weight: 600;
}

.category-stat-value.non-vus {
    color: #dc3545;
    font-weight: 700;
}

/* Progress bars */
.progress-bar-container {
    background: #e9ecef;
    border-radius: 10px;
    height: 6px;
    overflow: hidden;
    margin: 5px 0;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #2e7d32, #1b5e20);
    border-radius: 10px;
    transition: width 0.3s ease;
}

/* Badges */
.badge-mention {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    gap: 5px;
    white-space: nowrap;
}

.badge-mention.favorable {
    background: #ffc107;
    color: #856404;
}

.badge-mention.present {
    background: #28a745;
    color: white;
}

.badge-mention.defavorable {
    background: #dc3545;
    color: white;
}

.badge-type {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.75rem;
    background: #e9ecef;
    color: #495057;
    white-space: nowrap;
}

/* Mini cartes */
.mini-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #e9ecef;
    transition: all 0.3s;
}

.mini-card:hover {
    background: white;
    border-color: #2e7d32;
    transform: translateX(5px);
    box-shadow: 0 4px 10px rgba(46, 125, 50, 0.1);
}

.mini-card-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.mini-card-icon i {
    font-size: 1.2rem;
}

.mini-card-content {
    flex: 1;
}

.mini-card-title {
    font-size: 0.85rem;
    color: #6c757d;
}

.mini-card-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2e7d32;
}

/* Tables modernes */
.table-container {
    overflow-x: auto;
    border-radius: 10px;
    margin-top: 15px;
}

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 5px;
    min-width: 800px;
}

.modern-table thead th {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
    font-weight: 700;
    font-size: 0.85rem;
    padding: 12px 15px;
    border: none;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
}

.modern-table thead th:first-child {
    border-radius: 10px 0 0 10px;
}

.modern-table thead th:last-child {
    border-radius: 0 10px 10px 0;
}

.modern-table tbody tr {
    background: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border-radius: 10px;
    transition: all 0.3s;
}

.modern-table tbody tr:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(46, 125, 50, 0.15);
}

.modern-table tbody td {
    padding: 12px 15px;
    border: none;
    font-size: 0.9rem;
    vertical-align: middle;
    white-space: nowrap;
}

.modern-table tbody td:first-child {
    border-radius: 10px 0 0 10px;
}

.modern-table tbody td:last-child {
    border-radius: 0 10px 10px 0;
}

/* Filtres */
.filters-bar {
    background: white;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.filter-badge {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    color: #6c757d;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.filter-badge:hover,
.filter-badge.active {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
}

/* Chart containers */
.chart-container {
    background: white;
    border-radius: 15px;
    padding: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
    height: 300px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

/* Tooltip */
[data-tooltip] {
    position: relative;
    cursor: help;
}

[data-tooltip]:before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 5px 10px;
    background: #333;
    color: white;
    font-size: 0.75rem;
    border-radius: 4px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
    z-index: 1000;
}

[data-tooltip]:hover:before {
    opacity: 1;
    visibility: visible;
    bottom: 120%;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in-up {
    animation: fadeInUp 0.5s ease-out forwards;
}

/* Responsive */
@media (max-width: 1400px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .grid-2 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {

    .stats-grid,
    .categories-grid,
    .grid-2,
    .grid-3 {
        grid-template-columns: 1fr;
    }

    .modern-card .card-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .filters-bar {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-badge {
        justify-content: center;
    }

    .stat-value {
        font-size: 1.3rem;
    }
}

/* Impression */
@media print {
    .no-print {
        display: none !important;
    }

    .modern-card {
        box-shadow: none;
        border: 1px solid #dee2e6;
    }
}
</style>

<!-- ============================================ -->
<!-- BIBLIOTHÈQUES EN CDN -->
<!-- ============================================ -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- ============================================ -->
<!-- CONTENU PRINCIPAL -->
<!-- ============================================ -->
<div class="container-fluid py-3">

    <!-- En-tête avec boutons d'export -->
    <div class="modern-card fade-in-up">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-chart-pie"></i> Statistiques des contrôles</h4>
            <div class="d-flex align-items-center gap-3">
                <span class="total-badge"><i class="fas fa-sync-alt"></i> Mis à jour en temps réel</span>
                <div class="export-buttons">
                    <button class="btn-export csv" id="export-csv"><i class="fas fa-file-csv"></i> CSV</button>
                    <button class="btn-export excel" id="export-excel"><i class="fas fa-file-excel"></i> Excel</button>
                    <button class="btn-export pdf" id="export-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                    <button class="btn-export csv" id="export-nonvus"><i class="fas fa-file-export"></i> CSV
                        Non-vus</button>
                    <button class="btn-export pdf" id="export-nonvus-pdf"><i class="fas fa-file-pdf"></i> PDF
                        Non-vus</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques clés -->
    <div class="stats-grid">
        <div class="stat-card fade-in-up" style="animation-delay: 0.1s;">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($effectif_attendu ?? 0, 0, ',', ' ') ?></div>
                <div class="stat-label">Effectif Attendu</div>
            </div>
        </div>
        <div class="stat-card fade-in-up" style="animation-delay: 0.15s;">
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($stats_globales['total_controles'] ?? 0, 0, ',', ' ') ?></div>
                <div class="stat-label">Contrôles Effectués</div>
            </div>
        </div>
        <div class="stat-card fade-in-up" style="animation-delay: 0.2s;">
            <div class="stat-icon"><i class="fas fa-eye-slash"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($stats_non_vus['militaires_non_vus'] ?? 0, 0, ',', ' ') ?>
                </div>
                <div class="stat-label">Non-Vus</div>
            </div>
        </div>
    </div>

    <!-- Catégories -->
    <?php $couleurs_categorie = ['ACTIF' => '#2e7d32', 'RETRAITES' => '#007bff', 'INTEGRES' => '#dc3545', 'DCD_AV_BIO' => '#6c757d', 'DCD_AP_BIO' => '#9c27b0']; ?>
    <div class="modern-card fade-in-up" style="animation-delay: 0.35s;">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-tag"></i> Statistiques par catégorie</h4>
        </div>
        <div class="card-body">
            <div class="categories-grid">
                <?php foreach (($stats_categories ?: []) as $cat):
                    $categorie_originale = $cat['categorie'] ?? 'N/A';
                    $categorie_traduite = $traductions_categories[$categorie_originale] ?? $categorie_originale;
                    $couleur = $couleurs_categorie[$categorie_originale] ?? '#2e7d32';
                    $icone = $icones_categorie[$categorie_originale] ?? 'fa-tag';
                    $non_vus_categorie = ($cat['total_militaires'] ?? 0) - ($cat['militaires_controles'] ?? 0);
                ?>
                <div class="category-card">
                    <div class="category-title"><i class="fas <?= $icone ?> me-2"
                            style="color: <?= $couleur ?>;"></i><?= htmlspecialchars($categorie_traduite) ?></div>
                    <div class="category-stats">
                        <div class="category-stat-item"><span class="category-stat-label"><i
                                    class="fas fa-users text-muted"></i> Effectif :</span><span
                                class="category-stat-value"><?= number_format($cat['total_militaires'] ?? 0, 0, ',', ' ') ?></span>
                        </div>
                        <div class="category-stat-item"><span class="category-stat-label"><i
                                    class="fas fa-clipboard-list text-success"></i> Contrôlés :</span><span
                                class="category-stat-value"><?= number_format($cat['militaires_controles'] ?? 0, 0, ',', ' ') ?></span>
                        </div>
                        <div class="category-stat-item"><span class="category-stat-label"><i
                                    class="fas fa-eye-slash text-danger"></i> Non-vus :</span><span
                                class="category-stat-value non-vus"><?= number_format($non_vus_categorie, 0, ',', ' ') ?></span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill"
                                style="width: <?= $cat['taux_couverture'] ?? 0 ?>%; background: <?= $couleur ?>;"></div>
                        </div>
                        <?php if ($categorie_traduite != 'Actif'): ?>
                        <div class="d-flex justify-content-between mt-2">
                            <div class="text-center"><span
                                    class="badge-mention favorable"><?= $cat['controles_favorables'] ?? 0 ?></span>
                                <div><small><?= $cat['taux_favorable'] ?? 0 ?>%</small></div>
                            </div>
                            <div class="text-center"><span
                                    class="badge-mention present"><?= $cat['controles_presents'] ?? 0 ?></span>
                                <div><small><?= $cat['taux_present'] ?? 0 ?>%</small></div>
                            </div>
                            <div class="text-center"><span
                                    class="badge-mention defavorable"><?= $cat['controles_defavorables'] ?? 0 ?></span>
                                <div><small><?= $cat['taux_defavorable'] ?? 0 ?>%</small></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="d-flex justify-content-center mt-2">
                            <div class="text-center"><span
                                    class="badge-mention present"><?= $cat['controles_presents'] ?? 0 ?></span>
                                <div><small><?= $cat['taux_present'] ?? 0 ?>%</small></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3 p-3 bg-light rounded">
                <div class="d-flex flex-wrap gap-4">
                    <span><i class="fas fa-circle" style="color: #2e7d32;"></i> Effectif total</span>
                    <span><i class="fas fa-circle text-success"></i> Taux couverture</span>
                    <span><i class="fas fa-circle" style="color: #ffc107;"></i> % Favorable</span>
                    <span><i class="fas fa-circle" style="color: #28a745;"></i> % Présent</span>
                    <span><i class="fas fa-circle" style="color: #dc3545;"></i> % Défavorable</span>
                </div>
                <span class="badge-type">Moyenne couverture:
                    <?= round(array_sum(array_column($stats_categories, 'taux_couverture')) / max(1, count($stats_categories)), 1) ?>%</span>
            </div>
        </div>
    </div>

    <!-- Filtres rapides -->
    <div class="filters-bar no-print">
        <span class="filter-badge active" onclick="filterStats('all', this)"><i class="fas fa-chart-bar"></i> Vue
            d'ensemble</span>
        <span class="filter-badge" onclick="filterStats('types', this)"><i class="fas fa-tags"></i> Par type</span>
        <span class="filter-badge" onclick="filterStats('mentions', this)"><i class="fas fa-star"></i> Par
            mention</span>
        <span class="filter-badge" onclick="filterStats('evolution', this)"><i class="fas fa-chart-line"></i>
            Évolution</span>
        <span class="filter-badge" onclick="refreshStats()"><i class="fas fa-sync-alt"></i> Actualiser</span>
    </div>

    <!-- Première ligne : Graphiques principaux -->
    <div class="grid-2">
        <!-- Types -->
        <div class="modern-card fade-in-up" id="section-types" style="animation-delay: 0.55s;">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-chart-pie"></i> Répartition par type de contrôle</h4>
            </div>
            <div class="card-body">
                <div class="chart-container"><canvas id="chartTypes"></canvas></div>
                <div class="grid-3" style="margin-top: 15px;">
                    <?php foreach (($stats_types ?: []) as $type):
                        $type_lower = strtolower($type['type_controle'] ?? '');
                        $couleur = (strpos($type_lower, 'militaire') !== false) ? '#FF6B6B' : ((strpos($type_lower, 'bénéficiaire') !== false || strpos($type_lower, 'beneficiaire') !== false) ? '#4ECDC4' : '#' . substr(md5($type['type_controle'] ?? ''), 0, 6));
                    ?>
                    <div class="mini-card">
                        <div class="mini-card-icon" style="background: <?= $couleur ?>;"><i
                                class="fas fa-clipboard-list"></i></div>
                        <div class="mini-card-content">
                            <div class="mini-card-title">
                                <?= htmlspecialchars(ucfirst(strtolower($type['type_controle'] ?? ''))) ?></div>
                            <div class="mini-card-value"><?= number_format($type['nombre'] ?? 0, 0, ',', ' ') ?></div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill"
                                    style="width: <?= (($type['nombre'] ?? 0) / max(1, ($stats_globales['total_controles'] ?? 1)) * 100) ?>%; background: <?= $couleur ?>;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Évolution -->
        <div class="modern-card fade-in-up" id="section-evolution" style="animation-delay: 0.6s;">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-chart-line"></i> Évolution des contrôles</h4>
            </div>
            <div class="card-body">
                <div class="chart-container"><canvas id="chartEvolution"></canvas></div>
                <div class="d-flex justify-content-between mt-3">
                    <div class="mini-card">
                        <div class="mini-card-icon" style="color: #28a745;"><i class="fas fa-calendar-alt"></i></div>
                        <div class="mini-card-content">
                            <div class="mini-card-title">Année record</div>
                            <div class="mini-card-value">
                                <?php $record = !empty($evolution_annuelle) ? max($evolution_annuelle) : null;
                                echo $record ? ($record['annee'] ?? '-') : '-'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mini-card">
                        <div class="mini-card-icon" style="color: #28a745;"><i class="fas fa-chart-bar"></i></div>
                        <div class="mini-card-content">
                            <div class="mini-card-title">Total contrôles</div>
                            <div class="mini-card-value">
                                <?= number_format(array_sum(array_column($evolution_annuelle ?: [], 'nb_controles')), 0, ',', ' ') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Deuxième ligne : Mentions + Performance par type -->
    <div class="grid-2">
        <!-- Mentions -->
        <div class="modern-card fade-in-up" id="section-mentions" style="animation-delay: 0.65s;">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-star"></i> Répartition des mentions</h4>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 250px;"><canvas id="chartMentions"></canvas></div>
                <div class="grid-3" style="margin-top: 15px;">
                    <?php foreach (($stats_mentions ?: []) as $mention):
                        $mention_lower = strtolower($mention['mention'] ?? '');
                        if ($mention_lower === 'favorable') {
                            $couleur_mention = '#ffc107';
                            $icone_mention = 'fa-thumbs-up';
                        } elseif ($mention_lower === 'présent' || $mention_lower === 'present') {
                            $couleur_mention = '#28a745';
                            $icone_mention = 'fa-check-circle';
                        } elseif ($mention_lower === 'défavorable' || $mention_lower === 'defavorable') {
                            $couleur_mention = '#dc3545';
                            $icone_mention = 'fa-thumbs-down';
                        } else {
                            $couleur_mention = '#6c757d';
                            $icone_mention = 'fa-star';
                        }
                    ?>
                    <div class="mini-card">
                        <div class="mini-card-icon" style="background: <?= $couleur_mention ?>;"><i
                                class="fas <?= $icone_mention ?>"></i></div>
                        <div class="mini-card-content">
                            <div class="mini-card-title" style="color: <?= $couleur_mention ?>;">
                                <?= htmlspecialchars(ucfirst(strtolower($mention['mention'] ?? ''))) ?></div>
                            <div class="mini-card-value"><?= number_format($mention['nombre'] ?? 0, 0, ',', ' ') ?>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill"
                                    style="width: <?= (($mention['nombre'] ?? 0) / max(1, ($stats_globales['total_controles'] ?? 1)) * 100) ?>%; background: <?= $couleur_mention ?>;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Performance par type (conservée dans la page) -->
        <div class="modern-card fade-in-up" id="section-performance-types" style="animation-delay: 0.7s;">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-chart-bar"></i> Taux de réussite par type</h4>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Total</th>
                                <th>Réussite</th>
                                <th>Répartition</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($performance_types ?: [], 0, 5) as $perf): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars(ucfirst(strtolower($perf['type_controle'] ?? ''))) ?></strong>
                                </td>
                                <td><?= $perf['total'] ?? 0 ?></td>
                                <td><span class="badge-mention favorable"><?= ($perf['taux_reussite'] ?? 0) ?>%</span>
                                </td>
                                <td style="width: 200px;">
                                    <div class="d-flex gap-1" style="height: 8px;">
                                        <?php if (($perf['taux_reussite'] ?? 0) > 0): ?><div class="bg-warning"
                                            style="width: <?= $perf['taux_reussite'] ?? 0 ?>%;"
                                            data-tooltip="Favorable: <?= ($perf['taux_reussite'] ?? 0) ?>%"></div>
                                        <?php endif; ?>
                                        <?php if (($perf['taux_presence'] ?? 0) > 0): ?><div class="bg-success"
                                            style="width: <?= $perf['taux_presence'] ?? 0 ?>%;"
                                            data-tooltip="Présent: <?= ($perf['taux_presence'] ?? 0) ?>%"></div>
                                        <?php endif; ?>
                                        <?php if (($perf['taux_echec'] ?? 0) > 0): ?><div class="bg-danger"
                                            style="width: <?= $perf['taux_echec'] ?? 0 ?>%;"
                                            data-tooltip="Défavorable: <?= ($perf['taux_echec'] ?? 0) ?>%"></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <h6 class="fw-bold" style="color: #2e7d32;">Légende</h6>
                    <div class="d-flex flex-wrap gap-3">
                        <span><i class="fas fa-thumbs-up" style="color: #ffc107;"></i> Favorable</span>
                        <span><i class="fas fa-check-circle" style="color: #28a745;"></i> Présent</span>
                        <span><i class="fas fa-thumbs-down" style="color: #dc3545;"></i> Défavorable</span>
                        <span><i class="fas fa-eye-slash" style="color: #dc3545;"></i> Non-vus</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Troisième ligne : Liens de parenté + Top bénéficiaires (conservés) -->
    <div class="grid-2">
        <!-- Liens de parenté -->
        <div class="modern-card fade-in-up" id="section-parente" style="animation-delay: 0.75s;">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-users"></i> Liens de parenté</h4>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 250px;"><canvas id="chartParente"></canvas></div>
                <div class="table-container mt-3">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Lien</th>
                                <th>Total</th>
                                <th>Fav</th>
                                <th>Prés</th>
                                <th>Déf</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($stats_parente ?: []) as $parente): ?>
                            <tr>
                                <td><?= htmlspecialchars($parente['lien_parente'] ?? '') ?></td>
                                <td><?= number_format($parente['nombre'] ?? 0, 0, ',', ' ') ?></td>
                                <td><span class="badge-mention favorable"><?= $parente['favorables'] ?? 0 ?></span></td>
                                <td><span class="badge-mention present"><?= $parente['presents'] ?? 0 ?></span></td>
                                <td><span class="badge-mention defavorable"><?= $parente['defavorables'] ?? 0 ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top bénéficiaires -->
        <div class="modern-card fade-in-up" id="section-top" style="animation-delay: 0.8s;">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-crown"></i> Top bénéficiaires</h4>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Matricule</th>
                                <th>Bénéficiaire</th>
                                <th>Contrôles</th>
                                <th>Fav</th>
                                <th>Prés</th>
                                <th>Déf</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($top_beneficiaires ?: []) as $index => $benef): ?>
                            <tr>
                                <td><strong>#<?= $index + 1 ?></strong></td>
                                <td><span class="badge-type"><?= htmlspecialchars($benef['matricule'] ?? '') ?></span>
                                </td>
                                <td><?= htmlspecialchars(strtoupper($benef['nom_beneficiaire'] ?? '')) ?></td>
                                <td><span class="badge-type"
                                        style="background: #2e7d32; color: white;"><?= $benef['nb_controles'] ?? 0 ?></span>
                                </td>
                                <td><span class="badge-mention favorable"><?= $benef['favorables'] ?? 0 ?></span></td>
                                <td><span class="badge-mention present"><?= $benef['presents'] ?? 0 ?></span></td>
                                <td><span class="badge-mention defavorable"><?= $benef['defavorables'] ?? 0 ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Derniers contrôles (sans N° et sans Date ajout) -->
    <div class="modern-card fade-in-up" id="section-derniers" style="animation-delay: 0.85s;">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-clock"></i> Derniers contrôles ajoutés</h4>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Matricule</th>
                            <th>Bénéficiaire</th>
                            <th>Type</th>
                            <th>Mention</th>
                            <th>Date contrôle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($derniers_controles ?: []) as $controle): ?>
                        <tr>
                            <td>#<?= $controle['id'] ?? '' ?></td>
                            <td><span
                                    class="badge-type"><?= htmlspecialchars(strtoupper($controle['matricule'] ?? '')) ?></span>
                            </td>
                            <td><?= htmlspecialchars(strtoupper($controle['nom_beneficiaire'] ?? '')) ?></td>
                            <td><?= htmlspecialchars(ucfirst(strtolower($controle['type_controle'] ?? ''))) ?></td>
                            <td>
                                <?php $mention = $controle['mention'] ?? '';
                                    if ($mention == 'Favorable'): ?><span class="badge-mention favorable"><i
                                        class="fas fa-thumbs-up"></i> Favorable</span>
                                <?php elseif ($mention == 'Présent'): ?><span class="badge-mention present"><i
                                        class="fas fa-check-circle"></i> Présent</span>
                                <?php elseif ($mention == 'Défavorable'): ?><span class="badge-mention defavorable"><i
                                        class="fas fa-thumbs-down"></i> Défavorable</span>
                                <?php else: ?><span class="badge-type">-</span><?php endif; ?>
                            </td>
                            <td><?= !empty($controle['date_controle']) ? date('d/m/Y', strtotime($controle['date_controle'])) : '-' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- SCRIPTS POUR LES GRAPHIQUES ET EXPORTS -->
<!-- ============================================ -->
<script>
Chart.defaults.font.family = "'Barlow', sans-serif";
Chart.defaults.color = '#495057';

// Données pour les graphiques
const statsTypes = <?= json_encode($stats_types ?: []) ?>;
const evolutionAnnuelle = <?= json_encode($evolution_annuelle ?: []) ?>;
const statsMentions = <?= json_encode($stats_mentions ?: []) ?>;
const statsParente = <?= json_encode($stats_parente ?: []) ?>;
const totalControles = <?= $stats_globales['total_controles'] ?? 0 ?>;

// Données pour les exports
const exportData = <?= json_encode(prepareExportData()) ?>;

// Données pour l'export des non-vus (avec les champs ajoutés)
const nonVusData = <?= json_encode($non_vus_list) ?>;

// Chemins des images
const logoPath = '../../assets/img/new-logo-ig-fardc.png';
const qrPath = '../../assets/img/qr-code-ig-fardc.png';
const watermarkPath = '../../assets/img/filigrane_logo_ig_fardc.png';

// Couleurs types
const typeColors = {
    'militaire': '#FF6B6B',
    'beneficiaire': '#4ECDC4'
};

document.addEventListener('DOMContentLoaded', function() {
    // Graphique Types
    new Chart(document.getElementById('chartTypes'), {
        type: 'doughnut',
        data: {
            labels: statsTypes.map(t => (t.type_controle || 'Sans type').charAt(0).toUpperCase() + (t
                .type_controle || '').slice(1).toLowerCase()),
            datasets: [{
                data: statsTypes.map(t => t.nombre || 0),
                backgroundColor: statsTypes.map(t => {
                    const type = (t.type_controle || '').toLowerCase();
                    if (type.includes('militaire')) return typeColors.militaire;
                    if (type.includes('bénéficiaire') || type.includes('beneficiaire'))
                        return typeColors.beneficiaire;
                    return '#' + ((t.type_controle || '').split('').reduce((acc,
                            char) => (acc + char.charCodeAt(0)) % 16777215, 0)
                        .toString(16).padStart(6, '0') || '2e7d32');
                }),
                borderColor: 'white',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: {
                            size: 11
                        },
                        generateLabels: function(chart) {
                            const data = chart.data;
                            if (data.labels.length && data.datasets.length) {
                                return data.labels.map((label, i) => {
                                    const value = data.datasets[0].data[i];
                                    const percentage = ((value / totalControles) * 100)
                                        .toFixed(1);
                                    return {
                                        text: `${label} : ${value} (${percentage}%)`,
                                        fillStyle: data.datasets[0].backgroundColor[i],
                                        strokeStyle: data.datasets[0].borderColor,
                                        lineWidth: data.datasets[0].borderWidth,
                                        hidden: false,
                                        index: i
                                    };
                                });
                            }
                            return [];
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) =>
                            `${ctx.label || ''}: ${(ctx.raw || 0).toLocaleString('fr-FR')} contrôles (${((ctx.raw / totalControles) * 100).toFixed(1)}%)`
                    }
                }
            }
        }
    });

    // Graphique Évolution
    new Chart(document.getElementById('chartEvolution'), {
        type: 'line',
        data: {
            labels: (evolutionAnnuelle.length ? evolutionAnnuelle.map(e => e.annee || '').reverse() :
            []),
            datasets: [{
                    label: 'Total',
                    data: evolutionAnnuelle.map(e => e.nb_controles || 0).reverse(),
                    borderColor: '#2e7d32',
                    backgroundColor: 'rgba(46,125,50,0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Favorables',
                    data: evolutionAnnuelle.map(e => e.favorables || 0).reverse(),
                    borderColor: '#ffc107',
                    backgroundColor: 'transparent',
                    tension: 0.4
                },
                {
                    label: 'Présents',
                    data: evolutionAnnuelle.map(e => e.presents || 0).reverse(),
                    borderColor: '#28a745',
                    backgroundColor: 'transparent',
                    tension: 0.4
                },
                {
                    label: 'Défavorables',
                    data: evolutionAnnuelle.map(e => e.defavorables || 0).reverse(),
                    borderColor: '#dc3545',
                    backgroundColor: 'transparent',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Graphique Mentions
    new Chart(document.getElementById('chartMentions'), {
        type: 'bar',
        data: {
            labels: statsMentions.map(m => (m.mention || 'Sans mention').charAt(0).toUpperCase() + (m
                .mention || '').slice(1).toLowerCase()),
            datasets: [{
                label: 'Nombre',
                data: statsMentions.map(m => m.nombre || 0),
                backgroundColor: statsMentions.map(m => {
                    const mention = (m.mention || '').toLowerCase();
                    if (mention === 'favorable') return '#ffc107';
                    if (mention === 'présent' || mention === 'present')
                        return '#28a745';
                    if (mention === 'défavorable' || mention === 'defavorable')
                        return '#dc3545';
                    return '#6c757d';
                }),
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Graphique Parenté
    if (document.getElementById('chartParente')) {
        new Chart(document.getElementById('chartParente'), {
            type: 'pie',
            data: {
                labels: statsParente.map(p => (p.lien_parente || 'Sans lien').charAt(0).toUpperCase() +
                    (p.lien_parente || '').slice(1).toLowerCase()),
                datasets: [{
                    data: statsParente.map(p => p.nombre || 0),
                    backgroundColor: statsParente.map((p, i) =>
                        `hsl(${(i * 360 / Math.max(1, statsParente.length)) % 360}, 70%, 50%)`
                    ),
                    borderColor: 'white',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }

    initExports();
});

// Fonctions d'export
function initExports() {
    document.getElementById('export-csv').addEventListener('click', () => exportToCSV());
    document.getElementById('export-excel').addEventListener('click', () => exportToExcel());
    document.getElementById('export-pdf').addEventListener('click', () => exportToPDF());
    document.getElementById('export-nonvus').addEventListener('click', () => exportNonVusCSV());
    document.getElementById('export-nonvus-pdf').addEventListener('click', () => exportNonVusPDF());
}

function getTimestamp() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}_${String(now.getHours()).padStart(2,'0')}h${String(now.getMinutes()).padStart(2,'0')}`;
}

function loadImageWithDimensions(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'Anonymous';
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            resolve({
                dataURL: canvas.toDataURL('image/png'),
                width: img.width,
                height: img.height
            });
        };
        img.onerror = () => resolve({
            dataURL: null,
            width: 100,
            height: 100
        });
        img.src = url + '?t=' + new Date().getTime();
    });
}

function exportToCSV() {
    if (!exportData || exportData.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Aucune donnée à exporter',
            timer: 2000
        });
        return;
    }
    try {
        const csvContent = exportData.map(row =>
            row.map(cell => {
                if (cell === null || cell === undefined) return '';
                const cellStr = String(cell);
                if (cellStr.includes(';') || cellStr.includes('"') || cellStr.includes('\n')) {
                    return '"' + cellStr.replace(/"/g, '""') + '"';
                }
                return cellStr;
            }).join(';')
        ).join('\n');
        const blob = new Blob(["\uFEFF" + csvContent], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `statistiques_controles_${getTimestamp()}.csv`;
        link.click();
        Swal.fire({
            icon: 'success',
            title: 'Export réussi',
            text: 'Fichier CSV généré',
            timer: 1500,
            toast: true,
            position: 'top-end'
        });
    } catch (error) {
        console.error(error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Échec de l\'export CSV',
            timer: 2000
        });
    }
}

function exportToExcel() {
    if (!exportData || exportData.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Aucune donnée à exporter',
            timer: 2000
        });
        return;
    }
    try {
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(exportData);
        const colWidths = [];
        exportData.forEach(row => {
            row.forEach((cell, colIndex) => {
                const cellLength = String(cell || '').length;
                colWidths[colIndex] = Math.max(colWidths[colIndex] || 0, Math.min(cellLength, 50));
            });
        });
        ws['!cols'] = colWidths.map(width => ({
            wch: width + 2
        }));
        XLSX.utils.book_append_sheet(wb, ws, 'Statistiques');
        XLSX.writeFile(wb, `statistiques_controles_${getTimestamp()}.xlsx`);
        Swal.fire({
            icon: 'success',
            title: 'Export réussi',
            text: 'Fichier Excel généré',
            timer: 1500,
            toast: true,
            position: 'top-end'
        });
    } catch (error) {
        console.error(error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Échec de l\'export Excel',
            timer: 2000
        });
    }
}

async function exportToPDF() {
    if (!exportData || exportData.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Aucune donnée à exporter',
            timer: 2000
        });
        return;
    }
    Swal.fire({
        title: 'Préparation du PDF...',
        text: 'Chargement des images',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    try {
        const [logo, qrCode, watermark] = await Promise.all([
            loadImageWithDimensions(logoPath),
            loadImageWithDimensions(qrPath),
            loadImageWithDimensions(watermarkPath)
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

        const margin = 10;
        const pageWidth = doc.internal.pageSize.width;
        const pageHeight = doc.internal.pageSize.height;
        const logoHeightMm = 15;
        const logoWidthMm = logo.width ? (logo.width / logo.height) * logoHeightMm : 35;
        const qrSizeMm = 8;
        const watermarkOpacity = 0.15;
        const blueColor = [0, 102, 204];
        const yellowColor = [255, 215, 0];
        const redColor = [206, 17, 38];

        let isFirstPage = true;
        let pageNum = 1;

        function addHeader() {
            if (isFirstPage) {
                if (logo.dataURL) doc.addImage(logo.dataURL, 'PNG', margin, 5, logoWidthMm, logoHeightMm);
                doc.setFontSize(8);
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(0, 0, 0);
                const dateStr = 'Kinshasa le, ' + new Date().toLocaleDateString('fr-FR');
                doc.text(dateStr, pageWidth - margin, 10, {
                    align: 'right'
                });
                doc.setFontSize(16);
                doc.setFont('helvetica', 'bold');
                doc.text('RAPPORT STATISTIQUE DES CONTRÔLÉS', pageWidth / 2, 24, {
                    align: 'center'
                });
                doc.setDrawColor(0, 0, 0);
                doc.setLineWidth(0.3);
                doc.line(margin, 27, pageWidth - margin, 27);
                isFirstPage = false;
                return 32;
            }
            return 20;
        }

        function addFooterForCurrentPage(doc, pageNumber) {
            const footerY = pageHeight - 15;
            if (qrCode.dataURL) {
                doc.addImage(qrCode.dataURL, 'PNG', pageWidth - margin - qrSizeMm, footerY - 8, qrSizeMm, qrSizeMm);
            }
            const lineY = footerY;
            const lineWidth = pageWidth - (2 * margin);
            const segmentWidth = lineWidth / 3;
            doc.setDrawColor(blueColor[0], blueColor[1], blueColor[2]);
            doc.setLineWidth(0.5);
            doc.line(margin, lineY, margin + segmentWidth, lineY);
            doc.setDrawColor(yellowColor[0], yellowColor[1], yellowColor[2]);
            doc.line(margin + segmentWidth, lineY, margin + (2 * segmentWidth), lineY);
            doc.setDrawColor(redColor[0], redColor[1], redColor[2]);
            doc.line(margin + (2 * segmentWidth), lineY, margin + (3 * segmentWidth), lineY);
            doc.setFont('helvetica', 'italic');
            doc.setFontSize(5);
            doc.setTextColor(100);
            const footerText = [
                'Inspectorat Général FARDC, Avenue des écuries, N°54, Quartier Joli Parc, Commune de NGALIEMA',
                'Emails : infoigfardc2017@gmail.com; igfardc2017@yahoo.fr'
            ];
            doc.text(footerText[0], pageWidth / 2, lineY + 3, {
                align: 'center'
            });
            doc.text(footerText[1], pageWidth / 2, lineY + 6, {
                align: 'center'
            });
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(150);
            doc.text(`Page ${pageNumber}`, pageWidth - margin, lineY + 9, {
                align: 'right'
            });
        }

        let currentY = addHeader();
        pageNum = 1;

        if (watermark.dataURL) {
            doc.saveGraphicsState();
            doc.setGState(new doc.GState({
                opacity: watermarkOpacity
            }));
            doc.addImage(watermark.dataURL, 'PNG', (pageWidth / 2) - 45, (pageHeight / 2) - 45, 90, 90);
            doc.restoreGraphicsState();
        }

        for (let i = 0; i < exportData.length; i++) {
            const row = exportData[i];
            if (currentY > pageHeight - 35) {
                pageNum++;
                doc.addPage();
                currentY = 20;
                if (watermark.dataURL) {
                    doc.saveGraphicsState();
                    doc.setGState(new doc.GState({
                        opacity: watermarkOpacity
                    }));
                    doc.addImage(watermark.dataURL, 'PNG', (pageWidth / 2) - 45, (pageHeight / 2) - 45, 90, 90);
                    doc.restoreGraphicsState();
                }
            }
            if (row.length === 1 && row[0] && row[0].match(/^\d+\./)) {
                doc.setFontSize(14);
                doc.setFont('helvetica', 'bold');
                doc.setTextColor(0, 0, 0);
                doc.text(row[0], margin, currentY);
                currentY += 8;
                continue;
            }
            if (row.length === 1 && row[0] === '') {
                currentY += 3;
                continue;
            }
            if (row.length > 1) {
                const tableData = [];
                let j = i;
                const headers = [...row];
                tableData.push(headers);
                j = i + 1;
                while (j < exportData.length &&
                    !(exportData[j].length === 1 && exportData[j][0] && exportData[j][0].match(/^\d+\./)) &&
                    exportData[j].length > 1) {
                    tableData.push([...exportData[j]]);
                    j++;
                }
                if (tableData.length > 0) {
                    doc.autoTable({
                        head: [tableData[0]],
                        body: tableData.slice(1),
                        startY: currentY,
                        margin: {
                            left: margin,
                            right: margin,
                            bottom: 20
                        },
                        theme: 'grid',
                        styles: {
                            fontSize: 8,
                            font: 'helvetica',
                            cellPadding: 2,
                            textColor: [0, 0, 0],
                            lineColor: [0, 0, 0],
                            lineWidth: 0.1,
                            overflow: 'linebreak',
                            valign: 'middle'
                        },
                        headStyles: {
                            fillColor: [255, 255, 255],
                            textColor: [0, 0, 0],
                            fontSize: 8,
                            fontStyle: 'bold',
                            halign: 'center',
                            lineColor: [0, 0, 0],
                            lineWidth: 0.1
                        },
                        bodyStyles: {
                            textColor: [0, 0, 0]
                        },
                        didDrawPage: function(data) {
                            if (watermark.dataURL) {
                                doc.saveGraphicsState();
                                doc.setGState(new doc.GState({
                                    opacity: watermarkOpacity
                                }));
                                doc.addImage(watermark.dataURL, 'PNG', (pageWidth / 2) - 45, (
                                    pageHeight / 2) - 45, 90, 90);
                                doc.restoreGraphicsState();
                            }
                            addFooterForCurrentPage(doc, data.pageNumber);
                        },
                        didParseCell: function(data) {
                            if (data.section === 'body') {
                                data.cell.styles.halign = data.column.index === 0 ? 'left' : 'center';
                            }
                        }
                    });
                    currentY = doc.lastAutoTable.finalY + 8;
                    i = j - 1;
                }
            }
        }
        doc.save(`statistiques_controles_${getTimestamp()}.pdf`);
        Swal.fire({
            icon: 'success',
            title: 'Export réussi',
            text: 'Fichier PDF généré',
            timer: 1500,
            toast: true,
            position: 'top-end'
        });
    } catch (error) {
        Swal.close();
        console.error(error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de générer le PDF.',
            timer: 2000
        });
    }
}

function exportNonVusCSV() {
    if (!nonVusData || nonVusData.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Aucun non-vu',
            text: 'Tous les militaires ont été contrôlés.',
            timer: 2000
        });
        return;
    }
    // Ordre des colonnes : Série, Matricule, Noms, Grade, Unité, Bénéficiaire, Garnison, Province, Catégorie, ZDEF
    const rows = [
        ['Série', 'Matricule', 'Noms', 'Grade', 'Unité', 'Bénéficiaire', 'Garnison', 'Province', 'Catégorie',
            'ZDEF'
        ]
    ];
    nonVusData.forEach((m, index) => {
        rows.push([
            index + 1,
            m.matricule,
            m.noms,
            m.grade,
            m.unite,
            m.beneficiaire,
            m.garnison,
            m.province,
            m.categorie,
            m.zdef
        ]);
    });
    const csvContent = rows.map(r => r.join(';')).join('\n');
    const blob = new Blob(["\uFEFF" + csvContent], {
        type: 'text/csv;charset=utf-8;'
    });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `non_vus_${getTimestamp()}.csv`;
    link.click();
    Swal.fire({
        icon: 'success',
        title: 'Export réussi',
        text: 'Liste des non-vus (CSV) générée.',
        timer: 1500,
        toast: true
    });
}

async function exportNonVusPDF() {
    if (!nonVusData || nonVusData.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Aucun non-vu',
            text: 'Tous les militaires ont été contrôlés.',
            timer: 2000
        });
        return;
    }

    Swal.fire({
        title: 'Préparation du PDF...',
        text: 'Génération de la liste des non-vus',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const {
            jsPDF
        } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4'
        });

        const margin = 10;
        const pageWidth = doc.internal.pageSize.width;
        const pageHeight = doc.internal.pageSize.height;

        // Titre
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');
        doc.text('LISTE DES MILITAIRES NON CONTRÔLÉS', pageWidth / 2, 20, {
            align: 'center'
        });
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        doc.text('Généré le : ' + new Date().toLocaleDateString('fr-FR') + ' à ' + new Date().toLocaleTimeString(
            'fr-FR'), pageWidth / 2, 28, {
            align: 'center'
        });

        // Ordre des colonnes : Série, Matricule, Noms, Grade, Unité, Bénéficiaire, Garnison, Province, Catégorie, ZDEF
        const headers = [
            ['Série', 'Matricule', 'Noms', 'Grade', 'Unité', 'Bénéficiaire', 'Garnison', 'Province',
                'Catégorie', 'ZDEF'
            ]
        ];
        const body = nonVusData.map((m, index) => [
            index + 1,
            m.matricule,
            m.noms,
            m.grade,
            m.unite,
            m.beneficiaire,
            m.garnison,
            m.province,
            m.categorie,
            m.zdef
        ]);

        doc.autoTable({
            head: headers,
            body: body,
            startY: 35,
            margin: {
                left: margin,
                right: margin
            },
            styles: {
                fontSize: 8,
                cellPadding: 2,
                overflow: 'linebreak'
            },
            headStyles: {
                fillColor: [46, 125, 50],
                textColor: 255,
                fontStyle: 'bold'
            },
            alternateRowStyles: {
                fillColor: [240, 240, 240]
            },
            didDrawPage: function(data) {
                // Ajout du nombre de pages
                const pageCount = doc.internal.getNumberOfPages();
                for (let i = 1; i <= pageCount; i++) {
                    doc.setPage(i);
                    doc.setFontSize(8);
                    doc.setTextColor(150);
                    doc.text(`Page ${i} / ${pageCount}`, pageWidth - margin, pageHeight - 5, {
                        align: 'right'
                    });
                }
            }
        });

        doc.save(`non_vus_${getTimestamp()}.pdf`);
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Export réussi',
            text: 'PDF des non-vus généré.',
            timer: 1500,
            toast: true,
            position: 'top-end'
        });
    } catch (error) {
        Swal.close();
        console.error(error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de générer le PDF des non-vus.',
            timer: 2000
        });
    }
}

function filterStats(filter, element) {
    document.querySelectorAll('.filter-badge').forEach(b => b.classList.remove('active'));
    element.classList.add('active');

    const sections = {
        types: document.getElementById('section-types'),
        evolution: document.getElementById('section-evolution'),
        mentions: document.getElementById('section-mentions'),
        performanceTypes: document.getElementById('section-performance-types'),
        parente: document.getElementById('section-parente'),
        top: document.getElementById('section-top'),
        derniers: document.getElementById('section-derniers')
    };

    Object.values(sections).forEach(s => {
        if (s) s.style.display = 'none';
    });

    switch (filter) {
        case 'all':
            Object.values(sections).forEach(s => {
                if (s) s.style.display = 'block';
            });
            break;
        case 'types':
            if (sections.types) sections.types.style.display = 'block';
            if (sections.performanceTypes) sections.performanceTypes.style.display = 'block';
            break;
        case 'mentions':
            if (sections.mentions) sections.mentions.style.display = 'block';
            break;
        case 'evolution':
            if (sections.evolution) sections.evolution.style.display = 'block';
            break;
        default:
            Object.values(sections).forEach(s => {
                if (s) s.style.display = 'block';
            });
    }
}

function refreshStats() {
    Swal.fire({
        title: 'Actualisation',
        text: 'Mise à jour en cours...',
        icon: 'info',
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true
    }).then(() => location.reload());
}

// Animation des barres
document.querySelectorAll('.progress-bar-fill').forEach(bar => {
    const w = bar.style.width;
    bar.style.width = '0%';
    setTimeout(() => bar.style.width = w, 100);
});
</script>

<?php include '../../includes/footer.php'; ?>