<?php
require_once 'includes/functions.php';
require_login();
check_profil(['ADMIN_IG', 'OPERATEUR']);

// --- AJAX : journalisation des exports (non-vus) ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'log_export') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? '';
    $filtres = $_GET['filtres'] ?? '';
    if ($type) {
        $details = "Export non-vus $type" . ($filtres ? " avec filtres: $filtres" : "");
        audit_action('EXPORT', 'non_vus', null, $details);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
// --- Fin AJAX ---

// ===== Récupération du profil utilisateur =====
$user_profil = $_SESSION['user_profil'] ?? 'NON DEFINI';
$is_admin_ig = ($user_profil === 'ADMIN_IG');

// ===== Définition du titre de la page =====
$page_titre = $is_admin_ig ? 'Tableau de bord Administrateur' : 'Tableau de bord';

// Classe CSS pour les cartes : toujours 3 par ligne sur md et plus
$card_col_class = 'col-12 col-sm-6 col-md-4';

include 'includes/header.php';
verifier_acces(['ADMIN_IG', 'OPERATEUR']); // pour une page admin

// ============================================
// Table de traduction des catégories
// ============================================
$traductions_categories = [
    'ACTIF' => 'Actif',
    'DCD_AP_BIO' => 'Décédé Après Bio',
    'INTEGRES' => 'Intégré',
    'RETRAITES' => 'Retraité',
    'DCD_AV_BIO' => 'Décédé Avant Bio'
];

$couleurs_categories = [
    'Actif' => '#28a745 ',
    'Décédé Après Bio' => '#6f42c1',
    'Intégré' => '#dc3545',
    'Retraité' => '#0d6efd',
    'Décédé Avant Bio' => '#6c757d'
];

$ordre_categories = ['Actif', 'Intégré', 'Retraité', 'Décédé Après Bio', 'Décédé Avant Bio'];

// Ordre hiérarchique des grades (du plus élevé au moins élevé)
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

// Ordre des catégories (du premier à afficher au dernier) - basé sur les codes
$categorieOrder = [
    'INTEGRES',      // Intégré
    'RETRAITES',     // Retraité
    'DCD_AV_BIO',    // Décédé Avant Bio
    'DCD_AP_BIO',    // Décédé Après Bio
    'ACTIF'          // Actif
];

function traduireCategorie($code)
{
    global $traductions_categories;
    return $traductions_categories[$code] ?? $code;
}

function getCouleurCategorie($categorie_libelle)
{
    global $couleurs_categories;
    return $couleurs_categories[$categorie_libelle] ?? '#17a2b8';
}

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

// ============================================
// MAPPING DES ZONES DE DÉFENSE (pour export non-vus)
// ============================================
$zones_defense_libelles = [
    '1ZDEF' => '1ZDef',
    '2ZDEF' => '2ZDef',
    '3ZDEF' => '3ZDef'
];
// ============================================
// Récupération des préférences utilisateur
// ============================================
$mode_filtre = false;
$filtres_actifs = [];

if (isset($_SESSION['user_id'])) {
    try {
        if (isset($_SESSION['filtres'])) {
            $filtres = $_SESSION['filtres'];
            $garnisons_filtre = $filtres['garnisons'] ?? [];
            $categories_filtre = $filtres['categories'] ?? [];

            if (!empty($garnisons_filtre) || !empty($categories_filtre)) {
                $mode_filtre = true;
                $filtres_actifs = [
                    'garnisons' => $garnisons_filtre,
                    'categories' => $categories_filtre
                ];
            }
        } else {
            $stmt = $pdo->prepare("SELECT preferences FROM utilisateurs WHERE id_utilisateur = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $preferences = $stmt->fetchColumn();

            if ($preferences) {
                $filtres = json_decode($preferences, true);
                $_SESSION['filtres'] = $filtres;
                $garnisons_filtre = $filtres['garnisons'] ?? [];
                $categories_filtre = $filtres['categories'] ?? [];

                if (!empty($garnisons_filtre) || !empty($categories_filtre)) {
                    $mode_filtre = true;
                    $filtres_actifs = [
                        'garnisons' => $garnisons_filtre,
                        'categories' => $categories_filtre
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur chargement préférences: " . $e->getMessage());
    }
}

// ============================================
// Construction des requêtes selon le mode
// ============================================
$stats = [];
$stats_detaillees = [];
$stats_par_categorie_controles = [];

if ($mode_filtre) {
    $garnisons_filtre = $filtres_actifs['garnisons'] ?? [];
    $categories_filtre = $filtres_actifs['categories'] ?? [];

    $garnison_placeholders = !empty($garnisons_filtre) ? implode(',', array_fill(0, count($garnisons_filtre), '?')) : '';
    $categorie_placeholders = !empty($categories_filtre) ? implode(',', array_fill(0, count($categories_filtre), '?')) : '';

    $params = array_merge($garnisons_filtre, $categories_filtre);

    $where_clause = [];
    if (!empty($garnison_placeholders)) {
        $where_clause[] = "m.garnison IN ($garnison_placeholders)";
    }
    if (!empty($categorie_placeholders)) {
        $where_clause[] = "m.categorie IN ($categorie_placeholders)";
    }
    $where_sql = !empty($where_clause) ? "WHERE " . implode(' AND ', $where_clause) : '';

    // --- Total effectifs prévus ---
    $sql_militaires = "SELECT COUNT(*) FROM militaires m";
    if (!empty($where_clause)) {
        $where_militaires = str_replace('m.', '', $where_sql);
        $sql_militaires .= " " . $where_militaires;
    }

    if (!empty($params)) {
        $stmt = $pdo->prepare($sql_militaires);
        $stmt->execute($params);
        $stats['militaires'] = $stmt->fetchColumn();
    } else {
        $stats['militaires'] = $pdo->query("SELECT COUNT(*) FROM militaires")->fetchColumn();
    }

    // --- Actifs / Inactifs (statut = '1' pour actif, '0' pour inactif) ---
    // Actifs
    $sql_actifs = "SELECT COUNT(*) FROM militaires m";
    if (!empty($where_clause)) {
        $sql_actifs .= " " . $where_sql . " AND m.statut = '1'";
    } else {
        $sql_actifs .= " WHERE m.statut = '1'";
    }
    if (!empty($params)) {
        $stmt = $pdo->prepare($sql_actifs);
        $stmt->execute($params);
        $stats['actifs'] = (int)$stmt->fetchColumn();
    } else {
        $stats['actifs'] = (int)$pdo->query("SELECT COUNT(*) FROM militaires WHERE statut = '1'")->fetchColumn();
    }

    // Inactifs
    $sql_inactifs = "SELECT COUNT(*) FROM militaires m";
    if (!empty($where_clause)) {
        $sql_inactifs .= " " . $where_sql . " AND m.statut = '0'";
    } else {
        $sql_inactifs .= " WHERE m.statut = '0'";
    }
    if (!empty($params)) {
        $stmt = $pdo->prepare($sql_inactifs);
        $stmt->execute($params);
        $stats['inactifs'] = (int)$stmt->fetchColumn();
    } else {
        $stats['inactifs'] = (int)$pdo->query("SELECT COUNT(*) FROM militaires WHERE statut = '0'")->fetchColumn();
    }

    // ---- BÉNÉFICIAIRES : avec / sans (champ beneficiaire de type VARCHAR : rempli = a des bénéficiaires) ----
    if (!empty($params)) {
        // Il y a des filtres : on utilise $where_sql qui contient déjà WHERE ...
        $sql_benef = "SELECT COUNT(*) FROM militaires m " . $where_sql . " AND m.beneficiaire != '' AND m.beneficiaire IS NOT NULL";
        $stmt = $pdo->prepare($sql_benef);
        $stmt->execute($params);
        $stats['avec_beneficiaires'] = (int)$stmt->fetchColumn();
    } else {
        // Pas de filtres
        $stats['avec_beneficiaires'] = (int)$pdo->query("SELECT COUNT(*) FROM militaires WHERE beneficiaire != '' AND beneficiaire IS NOT NULL")->fetchColumn();
    }
    $stats['sans_beneficiaires'] = $stats['militaires'] - $stats['avec_beneficiaires'];

    $sql_categories_filtrees = "SELECT m.categorie, COUNT(*) as total FROM militaires m";
    if (!empty($where_clause)) {
        $sql_categories_filtrees .= " " . $where_sql;
    }
    $sql_categories_filtrees .= " AND m.categorie IS NOT NULL AND m.categorie != '' GROUP BY m.categorie ORDER BY total DESC";

    if (!empty($params)) {
        $stmt = $pdo->prepare($sql_categories_filtrees);
        $stmt->execute($params);
        $stats_detaillees['par_categorie'] = $stmt->fetchAll();
    } else {
        $stats_detaillees['par_categorie'] = $pdo->query($sql_categories_filtrees)->fetchAll();
    }

    $sql_controles_par_categorie = "SELECT m.categorie, COUNT(DISTINCT c.matricule) as total_controles FROM controles c JOIN militaires m ON c.matricule = m.matricule";
    if (!empty($where_clause)) {
        $sql_controles_par_categorie .= " " . $where_sql;
    }
    $sql_controles_par_categorie .= " AND m.categorie IS NOT NULL AND m.categorie != '' GROUP BY m.categorie";

    if (!empty($params)) {
        $stmt = $pdo->prepare($sql_controles_par_categorie);
        $stmt->execute($params);
        $controles_par_categorie = $stmt->fetchAll();
    } else {
        $controles_par_categorie = $pdo->query($sql_controles_par_categorie)->fetchAll();
    }

    foreach ($controles_par_categorie as $stat) {
        $categorie_libelle = traduireCategorie($stat['categorie']);
        $stats_par_categorie_controles[$categorie_libelle] = $stat['total_controles'];
    }

    foreach ($stats_detaillees['par_categorie'] as $stat) {
        $categorie_libelle = traduireCategorie($stat['categorie']);
        if (!isset($stats_par_categorie_controles[$categorie_libelle])) {
            $stats_par_categorie_controles[$categorie_libelle] = 0;
        }
    }

    if (!empty($where_clause)) {
        $sql_controles_filtres = "SELECT COUNT(DISTINCT c.matricule) FROM controles c JOIN militaires m ON c.matricule = m.matricule $where_sql";
    } else {
        $sql_controles_filtres = "SELECT COUNT(DISTINCT matricule) FROM controles";
    }

    if (!empty($params)) {
        $stmt = $pdo->prepare($sql_controles_filtres);
        $stmt->execute($params);
        $stats['controles_filtres'] = $stmt->fetchColumn();
    } else {
        $stats['controles_filtres'] = $pdo->query($sql_controles_filtres)->fetchColumn();
    }

    $total_controles_global = $pdo->query("SELECT COUNT(DISTINCT matricule) FROM controles")->fetchColumn();
    $stats['controles_hors_filtre'] = $total_controles_global - $stats['controles_filtres'];
    $stats['non_vus'] = $stats['militaires'] - $stats['controles_filtres'];

    if (!empty($garnisons_filtre)) {
        $sql_garnisons = "SELECT m.garnison, COUNT(*) as total FROM militaires m WHERE m.garnison IN ($garnison_placeholders) GROUP BY m.garnison ORDER BY m.garnison";
        $stmt = $pdo->prepare($sql_garnisons);
        $stmt->execute($garnisons_filtre);
        $stats_detaillees['par_garnison'] = $stmt->fetchAll();
    } else {
        $stats_detaillees['par_garnison'] = [];
    }

    // ---- Derniers militaires (5) ----
    if (!empty($where_clause)) {
        $sql_derniers_militaires = "SELECT noms, unite, garnison
                                    FROM militaires m $where_sql 
                                    ORDER BY m.matricule DESC LIMIT 5";
    } else {
        $sql_derniers_militaires = "SELECT noms, unite, garnison
                                    FROM militaires 
                                    ORDER BY matricule DESC LIMIT 5";
    }
    if (!empty($params)) {
        $stmt = $pdo->prepare($sql_derniers_militaires);
        $stmt->execute($params);
        $stats_detaillees['derniers_militaires'] = $stmt->fetchAll();
    } else {
        $stats_detaillees['derniers_militaires'] = $pdo->query($sql_derniers_militaires)->fetchAll();
    }

    // ---- Derniers contrôles (5) ----
    if (!empty($where_clause)) {
        $sql_controles = "SELECT c.date_controle, c.type_controle, c.mention, c.observations, 
                                 m.noms, m.grade, m.unite, m.matricule, m.categorie 
                          FROM controles c JOIN militaires m ON c.matricule = m.matricule 
                          $where_sql 
                          ORDER BY c.date_controle DESC LIMIT 5";
    } else {
        $sql_controles = "SELECT c.date_controle, c.type_controle, c.mention, c.observations, 
                                 m.noms, m.grade, m.unite, m.matricule, m.categorie 
                          FROM controles c JOIN militaires m ON c.matricule = m.matricule 
                          ORDER BY c.date_controle DESC LIMIT 5";
    }
    if (!empty($params)) {
        $stmt = $pdo->prepare($sql_controles);
        $stmt->execute($params);
        $stats_detaillees['derniers_controles'] = $stmt->fetchAll();
    } else {
        $stats_detaillees['derniers_controles'] = $pdo->query($sql_controles)->fetchAll();
    }

    if (!empty($where_clause)) {
        $sql_types = "SELECT c.type_controle, COUNT(*) as total FROM controles c JOIN militaires m ON c.matricule = m.matricule $where_sql GROUP BY c.type_controle ORDER BY total DESC";
    } else {
        $sql_types = "SELECT c.type_controle, COUNT(*) as total FROM controles c JOIN militaires m ON c.matricule = m.matricule GROUP BY c.type_controle ORDER BY total DESC";
    }

    if (!empty($params)) {
        $stmt = $pdo->prepare($sql_types);
        $stmt->execute($params);
        $stats_detaillees['controles_par_type'] = $stmt->fetchAll();
    } else {
        $stats_detaillees['controles_par_type'] = $pdo->query($sql_types)->fetchAll();
    }

    if (!empty($where_clause)) {
        $sql_mentions = "SELECT c.mention, COUNT(*) as total FROM controles c JOIN militaires m ON c.matricule = m.matricule $where_sql AND c.mention IS NOT NULL AND c.mention != '' GROUP BY c.mention ORDER BY total DESC";
    } else {
        $sql_mentions = "SELECT c.mention, COUNT(*) as total FROM controles c JOIN militaires m ON c.matricule = m.matricule WHERE c.mention IS NOT NULL AND c.mention != '' GROUP BY c.mention ORDER BY total DESC";
    }

    if (!empty($params)) {
        $stmt = $pdo->prepare($sql_mentions);
        $stmt->execute($params);
        $stats_detaillees['mentions_stats'] = $stmt->fetchAll();
    } else {
        $stats_detaillees['mentions_stats'] = $pdo->query($sql_mentions)->fetchAll();
    }
} else {
    // ===== Mode non filtré (global) =====
    $stats = [
        'militaires' => $pdo->query("SELECT COUNT(*) FROM militaires")->fetchColumn(),
        'controles'  => $pdo->query("SELECT COUNT(*) FROM controles")->fetchColumn(),
        'unites'     => $pdo->query("SELECT COUNT(DISTINCT unite) FROM militaires")->fetchColumn(),
        'grades'     => $pdo->query("SELECT COUNT(DISTINCT grade) FROM militaires")->fetchColumn(),
    ];

    // ---- Actifs / Inactifs ----
    $stats['actifs']   = (int)$pdo->query("SELECT COUNT(*) FROM militaires WHERE statut = '1'")->fetchColumn();
    $stats['inactifs'] = (int)$pdo->query("SELECT COUNT(*) FROM militaires WHERE statut = '0'")->fetchColumn();

    // ---- BÉNÉFICIAIRES : avec / sans ----
    $stats['avec_beneficiaires'] = (int)$pdo->query("SELECT COUNT(*) FROM militaires WHERE beneficiaire != '' AND beneficiaire IS NOT NULL")->fetchColumn();
    $stats['sans_beneficiaires'] = $stats['militaires'] - $stats['avec_beneficiaires'];

    $stats_detaillees['par_categorie'] = $pdo->query("SELECT categorie, COUNT(*) as total FROM militaires WHERE categorie IS NOT NULL AND categorie != '' GROUP BY categorie ORDER BY total DESC")->fetchAll();

    $controles_par_categorie = $pdo->query("SELECT m.categorie, COUNT(DISTINCT c.matricule) as total_controles FROM controles c JOIN militaires m ON c.matricule = m.matricule WHERE m.categorie IS NOT NULL AND m.categorie != '' GROUP BY m.categorie")->fetchAll();

    foreach ($controles_par_categorie as $stat) {
        $categorie_libelle = traduireCategorie($stat['categorie']);
        $stats_par_categorie_controles[$categorie_libelle] = $stat['total_controles'];
    }

    $effectifs_par_categorie = [];
    foreach ($stats_detaillees['par_categorie'] as $stat) {
        $categorie_libelle = traduireCategorie($stat['categorie']);
        $effectifs_par_categorie[$categorie_libelle] = $stat['total'];
        if (!isset($stats_par_categorie_controles[$categorie_libelle])) {
            $stats_par_categorie_controles[$categorie_libelle] = 0;
        }
    }

    $militaires_avec_controle = $pdo->query("SELECT COUNT(DISTINCT matricule) FROM controles")->fetchColumn();
    $militaires_sans_controle = $pdo->query("SELECT COUNT(*) FROM militaires m LEFT JOIN controles c ON m.matricule = c.matricule WHERE c.id IS NULL")->fetchColumn();

    $stats['controles_filtres'] = $militaires_avec_controle;
    $stats['controles_hors_filtre'] = 0;
    $stats['non_vus'] = $militaires_sans_controle;

    // Données pour le template
    $stats_detaillees['par_garnison'] = $pdo->query("SELECT garnison, COUNT(*) as total FROM militaires WHERE garnison IS NOT NULL AND garnison != '' GROUP BY garnison ORDER BY total DESC LIMIT 5")->fetchAll();

    $stats_detaillees['derniers_militaires'] = $pdo->query("SELECT noms, unite, garnison FROM militaires ORDER BY matricule DESC LIMIT 5")->fetchAll();

    $stats_detaillees['derniers_controles'] = $pdo->query("SELECT c.date_controle, c.type_controle, c.mention, m.noms, m.grade, m.unite, m.categorie FROM controles c JOIN militaires m ON c.matricule = m.matricule ORDER BY c.date_controle DESC LIMIT 5")->fetchAll();

    $stats_detaillees['controles_par_type'] = $pdo->query("SELECT type_controle, COUNT(*) as total FROM controles GROUP BY type_controle ORDER BY total DESC")->fetchAll();

    $stats_detaillees['mentions_stats'] = $pdo->query("SELECT mention, COUNT(*) as total FROM controles WHERE mention IS NOT NULL AND mention != '' GROUP BY mention ORDER BY total DESC")->fetchAll();
}

// ============================================
// Récupération de la liste des non-vus (pour export OPERATEUR)
// ============================================
$non_vus_list = [];
$non_vus_zones = [];

if (!$is_admin_ig) {
    // Construction de la requête avec les filtres actifs
    $sql_non_vus = "SELECT 
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
                    WHERE c.id IS NULL";

    $params_non_vus = [];

    if ($mode_filtre) {
        if (!empty($garnisons_filtre)) {
            $garnison_placeholders = implode(',', array_fill(0, count($garnisons_filtre), '?'));
            $sql_non_vus .= " AND m.garnison IN ($garnison_placeholders)";
            $params_non_vus = array_merge($params_non_vus, $garnisons_filtre);
        }
        if (!empty($categories_filtre)) {
            $categorie_placeholders = implode(',', array_fill(0, count($categories_filtre), '?'));
            $sql_non_vus .= " AND m.categorie IN ($categorie_placeholders)";
            $params_non_vus = array_merge($params_non_vus, $categories_filtre);
        }
    }

    // Pas de ORDER BY SQL, on trie en PHP pour un contrôle total
    if (!empty($params_non_vus)) {
        $stmt = $pdo->prepare($sql_non_vus);
        $stmt->execute($params_non_vus);
        $non_vus_raw = $stmt->fetchAll();
    } else {
        $non_vus_raw = $pdo->query($sql_non_vus)->fetchAll();
    }

    // Fonction de tri personnalisée
    usort($non_vus_raw, function ($a, $b) use ($gradeOrder, $categorieOrder) {
        // 1. Tri par grade (ordre hiérarchique)
        $gradeA = array_search($a['grade'], $gradeOrder);
        $gradeB = array_search($b['grade'], $gradeOrder);
        if ($gradeA === false) $gradeA = 999;
        if ($gradeB === false) $gradeB = 999;

        if ($gradeA != $gradeB) {
            return $gradeA - $gradeB; // plus petit index = grade plus élevé
        }

        // 2. Tri par catégorie (ordre spécifique basé sur les codes)
        $catA = array_search($a['categorie'], $categorieOrder);
        $catB = array_search($b['categorie'], $categorieOrder);
        if ($catA === false) $catA = 999;
        if ($catB === false) $catB = 999;

        if ($catA != $catB) {
            return $catA - $catB;
        }

        // 3. Tri par nom (alphabétique)
        return strcmp($a['noms'] ?? '', $b['noms'] ?? '');
    });

    // Ajout du calcul de la ZDEF pour chaque ligne
    foreach ($non_vus_raw as $row) {
        $zdef = getZdefValue($row['province']);
        $row['zdef'] = $zdef['value'];
        $non_vus_list[] = $row;
        if ($zdef['code'] !== 'N/A' && $zdef['code'] !== 'AUTRE') {
            $non_vus_zones[$zdef['code']] = true;
        }
    }
    $non_vus_zones = array_keys($non_vus_zones);
    sort($non_vus_zones);
}

// ============================================
// Préparation des données pour les cartes de mentions
// ============================================
$mentions_totals = [
    'favorable' => 0,
    'defavorable' => 0,
    'present' => 0
];
foreach ($stats_detaillees['mentions_stats'] as $m) {
    $mention_lower = strtolower($m['mention']);
    if ($mention_lower == 'favorable') {
        $mentions_totals['favorable'] = $m['total'];
    } elseif ($mention_lower == 'défavorable' || $mention_lower == 'defavorable') {
        $mentions_totals['defavorable'] = $m['total'];
    } elseif ($mention_lower == 'présent' || $mention_lower == 'present') {
        $mentions_totals['present'] = $m['total'];
    }
}

// ============================================
// Récupération des données pour la carte
// ============================================
$provinceStats = [];

try {
    $sqlProv = "SELECT province, 
                SUM(CASE WHEN statut = '1' THEN 1 ELSE 0 END) as actifs, 
                SUM(CASE WHEN statut = '0' THEN 1 ELSE 0 END) as inactifs 
                FROM militaires 
                WHERE province IS NOT NULL AND province != ''
                GROUP BY province";
    $stmtProv = $pdo->query($sqlProv);
    while ($row = $stmtProv->fetch()) {
        // Normalisation pour la correspondance avec les noms du GeoJSON
        $provinceName = strtoupper(trim($row['province']));
        // Supprimer les accents et caractères spéciaux
        $provinceName = strtr($provinceName, [
            'É' => 'E',
            'È' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'À' => 'A',
            'Â' => 'A',
            'Ä' => 'A',
            'Ï' => 'I',
            'Î' => 'I',
            'Ì' => 'I',
            'Ö' => 'O',
            'Ô' => 'O',
            'Ò' => 'O',
            'Ü' => 'U',
            'Û' => 'U',
            'Ù' => 'U',
            'Ç' => 'C'
        ]);

        $provinceStats[$provinceName] = [
            'actifs' => (int)$row['actifs'],
            'inactifs' => (int)$row['inactifs'],
            'nom_original' => $row['province']
        ];
    }

    // Ajouter un mapping manuel pour les cas spécifiques
    $provinceMapping = [
        'HAUT-KATANGA' => 'HAUT KATANGA',
        'HAUT-LOMAMI' => 'HAUT LOMAMI',
        'HAUT-UELE' => 'HAUT UELE',
        'KASAI' => 'KASAI',
        'KASAI-CENTRAL' => 'KASAI-CENTRAL',
        'KASAI-ORIENTAL' => 'KASAI-ORIENTAL',
        'KINSHASA' => 'KINSHASA',
        'KONGO-CENTRAL' => 'KONGO CENTRAL',
        'KWANGO' => 'KWANGO',
        'KWILU' => 'KWILU',
        'LOMAMI' => 'LOMAMI',
        'LUALABA' => 'LUALABA',
        'MAI-NDOMBE' => 'MAI NDOMBE',
        'MANIEMA' => 'MANIEMA',
        'MONGALA' => 'MONGALA',
        'NORD-KIVU' => 'NORD KIVU',
        'NORD-UBANGI' => 'NORD UBANGI',
        'SANKURU' => 'SANKURU',
        'SUD-KIVU' => 'SUD KIVU',
        'SUD-UBANGI' => 'SUD UBANGI',
        'TANGANYIKA' => 'TANGANYIKA',
        'TSHOPO' => 'TSHOPO',
        'TSHUAPA' => 'TSHUAPA',
        'BAS-UELE' => 'BAS UELE',
        'EQUATEUR' => 'EQUATEUR',
        'ITURI' => 'ITURI'
    ];

    // Créer une version avec les deux formats pour la correspondance
    $provinceStatsComplete = [];
    foreach ($provinceStats as $key => $value) {
        $provinceStatsComplete[$key] = $value;
        if (isset($provinceMapping[$key])) {
            $provinceStatsComplete[$provinceMapping[$key]] = $value;
        }
    }
    $provinceStats = $provinceStatsComplete;
} catch (Exception $e) {
    error_log("Erreur chargement stats provinces: " . $e->getMessage());
    $provinceStats = [];
}

$provinceStatsJson = json_encode($provinceStats);

// Pour passer les filtres actifs au JavaScript
$garnisons_filtre_js = $filtres_actifs['garnisons'] ?? [];
$categories_filtre_js = $filtres_actifs['categories'] ?? [];
?>

<style>
    /* Police Barlow */
    body,
    html,
    .wrapper,
    .main-header,
    .content-wrapper,
    .main-footer,
    h1,
    h2,
    h3,
    h4,
    h5,
    h6,
    p,
    span,
    a,
    div,
    table,
    th,
    td,
    .card-title,
    .small-box,
    .small-box-footer,
    .inner,
    .icon,
    .btn,
    .form-control,
    .nav-link,
    .breadcrumb,
    .alert {
        font-family: 'Barlow', sans-serif !important;
    }

    :root {
        --primary: #2e7d32;
        --primary-dark: #1b5e20;
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
        --info: #17a2b8;
        --gray: #6c757d;
        --light: #f8f9fa;
        --primary-military: #2e7d32;
        --border-military: #1b5e20;
    }

    .content-wrapper {
        background: #f5f5f5;
    }

    h2.mb-3 {
        font-size: 1.5rem;
        font-weight: 600;
    }

    h3.card-title {
        font-weight: 600;
        font-size: 1.25rem;
    }

    .small-box.bg-warning .inner,
    .small-box.bg-warning .small-box-footer,
    .small-box.bg-warning .inner h3,
    .small-box.bg-warning .inner p {
        color: #ffffff !important;
        font-weight: 500;
    }

    .table {
        font-weight: 400;
    }

    .table thead th {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .small-box .inner h3 {
        font-weight: 700;
        font-size: 2.2rem;
    }

    .small-box .inner p {
        font-weight: 500;
        font-size: 1rem;
    }

    .small-box-footer {
        font-weight: 500;
    }

    /* Info boxes modernes */
    .info-box-modern {
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
        color: white;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        transition: all 0.3s;
        min-height: 120px;
    }

    .info-box-modern:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    }

    .info-box-modern .info-box-icon {
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        color: white !important;
    }

    .info-box-modern .info-box-icon i {
        color: white !important;
    }

    .info-box-modern .info-box-content {
        flex: 1;
    }

    .info-box-modern .info-box-text {
        font-size: 1rem;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 5px;
        font-weight: 500;
        color: white !important;
    }

    .info-box-modern .info-box-number {
        font-size: 2.2rem;
        font-weight: 700;
        line-height: 1.2;
        color: white !important;
    }

    /* Stats cards */
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
        margin-bottom: 25px;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(46, 125, 50, 0.15);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
    }

    .stat-icon.total-effectifs {
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    }

    .stat-icon.present {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    }

    .stat-icon.favorable {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    }

    .stat-icon.defavorable {
        background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%);
    }

    .stat-icon.actif {
        background: linear-gradient(135deg, #28a745, #1e7e34);
    }

    .stat-icon.inactif {
        background: linear-gradient(135deg, #6c757d, #545b62);
    }

    .stat-value {
        font-weight: 700;
        font-size: 2.2rem;
        line-height: 1.2;
        margin: 0;
    }

    .stat-value.total-effectifs {
        color: #2e7d32;
    }

    .stat-value.present {
        color: #28a745;
    }

    .stat-value.favorable {
        color: #ffc107;
    }

    .stat-value.defavorable {
        color: #dc3545;
    }

    .stat-value.actif {
        color: #28a745;
    }

    .stat-value.inactif {
        color: #6c757d;
    }

    .quick-action-btn:nth-child(1) i {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .quick-action-btn:nth-child(2) i {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .quick-action-btn:nth-child(3) i {
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .quick-action-btn:nth-child(4) i {
        background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .categories-stats-container {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
        flex-wrap: wrap;
        width: 100%;
    }

    .categorie-stat-card {
        flex: 1;
        min-width: 180px;
        background: white;
        border-radius: 15px;
        padding: 20px 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 15px;
        cursor: default;
        position: relative;
        overflow: hidden;
    }

    .categorie-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
    }

    .categorie-stat-card .categorie-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        flex-shrink: 0;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .categorie-stat-card .categorie-info {
        flex: 1;
    }

    .categorie-stat-card .categorie-info h4 {
        margin: 0 0 5px 0;
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .categorie-stat-card .categorie-info p {
        margin: 0;
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 500;
    }

    .categorie-stat-card .categorie-progress {
        margin-top: 8px;
        height: 4px;
        border-radius: 2px;
        background: rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .categorie-stat-card .categorie-progress-bar {
        height: 100%;
        border-radius: 2px;
    }

    .categorie-stat-card.actif .categorie-icon {
        background: linear-gradient(135deg, #28a745, #1e7e34);
    }

    .categorie-stat-card.actif h4 {
        color: #2e7d32;
    }

    .categorie-stat-card.integre .categorie-icon {
        background: linear-gradient(135deg, #dc3545, #bd2130);
    }

    .categorie-stat-card.integre h4 {
        color: #dc3545;
    }

    .categorie-stat-card.retraite .categorie-icon {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    }

    .categorie-stat-card.retraite h4 {
        color: #0d6efd;
    }

    .categorie-stat-card.decede-apres .categorie-icon {
        background: linear-gradient(135deg, #6f42c1, #5a32a3);
    }

    .categorie-stat-card.decede-apres h4 {
        color: #6f42c1;
    }

    .categorie-stat-card.decede-avant .categorie-icon {
        background: linear-gradient(135deg, #6c757d, #545b62);
    }

    .categorie-stat-card.decede-avant h4 {
        color: #495057;
    }

    .card-modern {
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
        overflow: hidden;
        transition: all 0.3s;
    }

    .card-modern:hover {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .card-modern .card-header {
        padding: 15px 20px;
        border: none;
    }

    .card-modern .card-header i {
        color: white !important;
    }

    .card-modern .card-body {
        padding: 20px;
    }

    .table-modern {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
    }

    .table-modern th {
        padding: 12px;
        background: var(--light);
        color: var(--primary-dark);
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
    }

    .table-modern td {
        padding: 12px;
        background: white;
        border: none;
        border-bottom: 1px solid #e0e0e0;
    }

    .progress {
        background: #e0e0e0;
        border-radius: 10px;
        margin: 5px 0;
        overflow: hidden;
    }

    .progress-bar {
        transition: width 0.6s ease;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin: 0 0 30px 0;
    }

    .quick-action-btn {
        border-radius: 15px;
        padding: 15px 10px;
        text-align: center;
        transition: all 0.3s;
        text-decoration: none;
        color: #333;
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: white;
    }

    .quick-action-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(46, 125, 50, 0.15);
        text-decoration: none;
        color: #333;
        border-color: rgba(46, 125, 50, 0.2);
    }

    .quick-action-btn i {
        font-size: 2rem;
        margin-bottom: 8px;
        display: block;
    }

    .quick-action-btn span {
        font-weight: 600;
        font-size: 1rem;
        display: block;
        margin-bottom: 3px;
        color: #333;
        line-height: 1.2;
    }

    .quick-action-btn small {
        font-size: 0.75rem;
        opacity: 0.7;
        color: #6c757d;
        display: block;
        line-height: 1.2;
    }

    .badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.85rem;
        line-height: 1.5;
    }

    .badge.bg-success,
    .mention-favorable {
        background: #ffc107 !important;
        color: #212529 !important;
    }

    .badge.bg-danger,
    .mention-defavorable {
        background: #dc3545 !important;
        color: white !important;
    }

    .badge.bg-info,
    .mention-present {
        background: #28a745 !important;
        color: white !important;
    }

    .badge.bg-secondary,
    .mention-autre {
        background: #6c757d !important;
        color: white !important;
    }

    .mention-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .garnison-tag {
        background: rgba(46, 125, 50, 0.1);
        color: #1b5e20;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
        border: 1px solid #2e7d32;
    }

    .categorie-tag {
        background: rgba(23, 162, 184, 0.1);
        color: #0c5460;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
        border: 1px solid #17a2b8;
    }

    .filter-summary {
        background-color: #e6f3e6 !important;
        border: 2px solid #2e7d32 !important;
        border-radius: 12px;
        color: #1b5e20;
        padding: 15px 20px;
        box-shadow: 0 2px 8px rgba(46, 125, 50, 0.1);
    }

    .filter-summary i {
        color: #2e7d32 !important;
        font-size: 1.2rem;
    }

    .filter-summary strong {
        color: #1b5e20;
        font-weight: 700;
    }

    .filter-summary .garnison-tag,
    .filter-summary .categorie-tag {
        background: #ffffff;
        border: 1px solid #2e7d32;
        color: #1b5e20;
        font-weight: 600;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .filter-summary .garnison-tag i,
    .filter-summary .categorie-tag i {
        color: #2e7d32 !important;
    }

    .section-title {
        margin-bottom: 15px;
        font-size: 1.3rem;
        font-weight: 600;
        color: #000000;
        display: flex;
        align-items: center;
    }

    .section-title i {
        margin-right: 10px;
        color: #2e7d32;
        font-size: 1.5rem;
    }

    .main-footer {
        padding: 15px 30px;
        color: var(--gray);
        font-size: 0.95rem;
        background: white;
        border-top: 3px solid var(--primary);
    }

    #drcMap {
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid var(--primary);
        height: 540px !important;
        min-height: 540px;
        width: 100% !important;
        display: block;
        background: #ffffff;
    }

    #drcMap .leaflet-container {
        width: 100% !important;
        height: 100% !important;
        background: #ffffff;
    }

    #map-loader {
        background: rgba(255, 255, 255, 0.95);
        z-index: 1000;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .map-info-box {
        background: linear-gradient(135deg, #2e7d32, #1b5e20);
        color: white;
        padding: 12px 18px;
        border-radius: 10px;
        border: 2px solid #ffc107;
        font-size: 13px;
        min-width: 220px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        font-family: 'Barlow', sans-serif;
        transition: all 0.2s ease;
    }

    .map-info-box h5 {
        margin: 0 0 8px 0;
        color: #ffc107;
        font-size: 15px;
        border-bottom: 2px solid #ffc107;
        padding-bottom: 5px;
        font-weight: 600;
    }

    .map-info-box .stats-mini div {
        display: flex;
        justify-content: space-between;
        margin: 6px 0;
        padding: 2px 0;
    }

    .map-info-box .stats-mini i {
        margin-right: 8px;
        color: white;
        width: 20px;
    }

    .map-info-box .total {
        margin-top: 10px;
        border-top: 2px solid #ffc107;
        padding-top: 8px;
        font-size: 14px;
        font-weight: bold;
    }

    .map-info-box span {
        color: rgba(255, 255, 255, 0.9);
    }

    .custom-popup .leaflet-popup-content-wrapper {
        background: white;
        border-radius: 12px;
        border: 2px solid #2e7d32;
        padding: 0;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.25);
    }

    .custom-popup .leaflet-popup-tip {
        background: #2e7d32;
    }

    .custom-popup .leaflet-popup-close-button {
        color: #2e7d32 !important;
        font-size: 18px !important;
        font-weight: bold !important;
        padding: 5px !important;
    }

    .custom-popup .leaflet-popup-close-button:hover {
        color: #1b5e20 !important;
    }

    .popup-content {
        padding: 18px;
        min-width: 240px;
    }

    .popup-content h4 {
        margin: 0 0 12px 0;
        color: #2e7d32;
        font-size: 1.2rem;
        text-align: center;
        border-bottom: 2px solid #ffc107;
        padding-bottom: 8px;
        font-weight: 600;
    }

    .popup-content .stats {
        margin: 12px 0;
    }

    .popup-content .stat-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 12px;
        margin: 4px 0;
        border-radius: 6px;
        font-size: 14px;
    }

    .popup-content .stat-item.actif {
        background: rgba(46, 125, 50, 0.1);
    }

    .popup-content .stat-item.inactif {
        background: rgba(108, 117, 125, 0.1);
    }

    .popup-content .stat-item.total {
        background: rgba(46, 125, 50, 0.2);
        margin-top: 12px;
        font-weight: bold;
        font-size: 15px;
    }

    .popup-content .btn-view {
        display: block;
        text-align: center;
        background: linear-gradient(135deg, #2e7d32, #1b5e20);
        color: white;
        padding: 10px 15px;
        border-radius: 6px;
        text-decoration: none;
        margin-top: 15px;
        transition: all 0.3s ease;
        font-weight: 500;
        border: 1px solid #ffc107;
    }

    .popup-content .btn-view:hover {
        background: #1b5e20;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
    }

    .popup-content .btn-view i {
        margin-right: 5px;
    }

    .map-legend {
        background: white;
        padding: 10px 12px;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.14);
        font-size: 11px;
        border: 2px solid #2e7d32;
        font-family: 'Barlow', sans-serif;
        min-width: 165px;
    }

    .map-legend h6 {
        margin: 0 0 6px 0;
        color: #2e7d32;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        border-bottom: 1px solid #ffc107;
        padding-bottom: 4px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        margin: 5px 0;
        font-size: 11px;
    }

    .legend-item .color-box {
        width: 14px;
        height: 14px;
        border-radius: 3px;
        margin-right: 8px;
        box-shadow: none;
    }

    .legend-item span:last-child {
        font-weight: 500;
        color: #333;
    }

    .legend-item .color-box.selected {
        background: #1b5e20;
        border: 2px solid #ffc107;
    }

    .province-hover-tooltip {
        background: transparent !important;
        border: none !important;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 12px;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8);
        box-shadow: none !important;
        font-family: 'Barlow', sans-serif;
        white-space: nowrap;
        pointer-events: none;
    }

    .province-hover-tooltip::before {
        display: none !important;
    }

    .leaflet-interactive {
        transition: all 0.15s ease;
        will-change: transform;
    }

    .tooltip-inner {
        background: linear-gradient(145deg, #2e7d32, #1b5e20) !important;
        color: white !important;
        font-family: 'Barlow', sans-serif;
        font-size: 0.9rem;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 8px 8px 8px 0;
        /* Pointe cassée en bas à gauche */
        border: none;

        text-transform: uppercase;
        letter-spacing: 0.5px;
        animation: tooltip-grow 0.2s ease-out;
    }

    @keyframes tooltip-grow {
        from {
            opacity: 0;
            transform: scale(0.9);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .leaflet-interactive {
            transition: none;
        }
    }

    @media (max-width: 768px) {
        .quick-actions {
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .quick-action-btn {
            padding: 12px 8px;
        }

        .quick-action-btn i {
            font-size: 1.8rem;
        }

        .quick-action-btn span {
            font-size: 0.9rem;
        }

        .quick-action-btn small {
            font-size: 0.7rem;
        }

        .info-box-modern {
            padding: 15px;
        }

        .info-box-modern .info-box-number {
            font-size: 1.8rem;
        }

        .info-box-modern .info-box-icon {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }

        .stat-card {
            width: 100%;
            margin-bottom: 15px;
        }

        .stat-info h4 {
            font-size: 1.5rem;
        }

        .categories-stats-container {
            flex-direction: column;
        }

        .categorie-stat-card {
            width: 100%;
        }

        #drcMap {
            height: 400px !important;
        }

        .map-info-box {
            min-width: 180px;
            padding: 10px 15px;
            font-size: 12px;
        }

        .popup-content {
            padding: 12px;
            min-width: 200px;
        }

        .popup-content h4 {
            font-size: 1rem;
        }

        .popup-content .btn-view {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
    }

    @media (max-width: 480px) {
        .quick-actions {
            grid-template-columns: 1fr;
        }

        #drcMap {
            height: 350px !important;
        }

        .map-info-box {
            min-width: 160px;
            padding: 8px 12px;
        }
    }

    @media print {
        .no-print {
            display: none;
        }
    }
</style>

<!-- Dépendances Leaflet pour la carte -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">

<div class="" style=" background: #f5f5f5;">
    <div class="content-header">
        <div class="container-fluid">
            <?php if ($mode_filtre && (!empty($filtres_actifs['garnisons']) || !empty($filtres_actifs['categories'])) && !$is_admin_ig): ?>
                <div class="row mb-2">
                    <div class="col-12">
                        <div class="alert filter-summary"
                            style="display: flex; align-items: center; flex-wrap: wrap; gap: 15px;">
                            <?php if (!empty($filtres_actifs['garnisons'])): ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-users-cog"></i>
                                    <strong>Équipe :</strong>
                                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                        <?php foreach ($filtres_actifs['garnisons'] as $garnison): ?>
                                            <span class="garnison-tag" style="display: inline-flex; align-items: center;">
                                                <i class="fas fa-map-pin mr-1" style="font-size: 0.7rem;"></i>
                                                <?= htmlspecialchars($garnison) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($filtres_actifs['categories'])): ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-tags"></i>
                                    <strong>Catégories :</strong>
                                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                        <?php foreach ($filtres_actifs['categories'] as $categorie): ?>
                                            <span class="categorie-tag" style="display: inline-flex; align-items: center;">
                                                <i class="fas fa-tag mr-1" style="font-size: 0.7rem;"></i>
                                                <?= htmlspecialchars(traduireCategorie($categorie)) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- ===== ACTIONS RAPIDES ===== -->
            <div class="row">
                <div class="col-12">
                    <div class="section-title">
                        <i class="fas fa-bolt"></i>
                        <span>Actions rapides</span>
                    </div>
                </div>
            </div>
            <div class="quick-actions no-print">
                <!-- Boutons communs à tous les profils -->
                <a href="modules/controles/ajouter.php" class="quick-action-btn" data-toggle="tooltip"
                    data-placement="top" title="Créer un nouveau contrôle / pointage">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nouveau contrôle</span>
                    <small>Effectuer un pointage</small>
                </a>
                <a href="modules/controles/liste.php" class="quick-action-btn" data-toggle="tooltip"
                    data-placement="top" title="Consulter l'historique complet des contrôles">
                    <i class="fas fa-list"></i>
                    <span>Liste des contrôles</span>
                    <small>Historique complet</small>
                </a>
                <?php
                // Boutons réservés à ADMIN_IG
                if ($is_admin_ig):
                ?>
                    <a href="modules/rapports/statistiques.php" class="quick-action-btn" data-toggle="tooltip"
                        data-placement="top" title="Accéder aux analyses statistiques détaillées">
                        <i class="fas fa-chart-bar"></i>
                        <span>Statistiques</span>
                        <small>Analyses détaillées</small>
                    </a>
                    <a href="modules/rapports/index.php" class="quick-action-btn" data-toggle="tooltip" data-placement="top"
                        title="Générer des rapports personnalisés (PDF, Excel)">
                        <i class="fas fa-file-pdf"></i>
                        <span>Générer rapport</span>
                        <small>Export PDF/Excel</small>
                    </a>
                <?php else: ?>
                    <!-- Boutons pour OPERATEUR : export des non-vus -->
                    <button class="quick-action-btn" id="export-nonvus-csv"
                        style="border: none; background: white; cursor: pointer;" data-toggle="tooltip" data-placement="top"
                        title="Exporter la liste des militaires non contrôlés au format CSV">
                        <i class="fas fa-file-csv"
                            style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                        <span>CSV Non-vus</span>
                        <small>Exporter la liste</small>
                    </button>
                    <button class="quick-action-btn" id="export-nonvus-pdf"
                        style="border: none; background: white; cursor: pointer;" data-toggle="tooltip" data-placement="top"
                        title="Exporter la liste des militaires non contrôlés au format PDF avec en-tête officiel">
                        <i class="fas fa-file-pdf"
                            style="background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                        <span>PDF Non-vus</span>
                        <small>Exporter la liste</small>
                    </button>
                    <!-- Nouveaux boutons Excel et ZIP -->
                    <button class="quick-action-btn" id="export-nonvus-excel"
                        style="border: none; background: white; cursor: pointer;" data-toggle="tooltip" data-placement="top"
                        title="Exporter la liste des militaires non contrôlés au format Excel avec mise en forme">
                        <i class="fas fa-file-excel"
                            style="background: linear-gradient(135deg, #1e7e34 0%, #155724 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                        <span>Excel Non-vus</span>
                        <small>Exporter la liste</small>
                    </button>
                    <button class="quick-action-btn" id="export-nonvus-zip"
                        style="border: none; background: white; cursor: pointer;" data-toggle="tooltip" data-placement="top"
                        title="Télécharger une archive ZIP contenant les trois formats (CSV, Excel, PDF)">
                        <i class="fas fa-file-archive"
                            style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                        <span>ZIP Non-vus</span>
                        <small>CSV + Excel + PDF</small>
                    </button>
                <?php endif; ?>
            </div>

            <!-- ===== STATISTIQUES GÉNÉRALES ===== -->
            <div class="row">
                <div class="col-12">
                    <div class="section-title">
                        <i class="fas fa-chart-line"></i>
                        <span>Statistiques générales des effectifs (Bloqués)</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <!-- Total Effectifs Prévus -->
                <div class="col-12 col-sm-6 <?= $card_col_class ?>">
                    <div class="stat-card">
                        <div class="stat-icon total-effectifs"><i class="fas fa-users"></i></div>
                        <div class="stat-info">
                            <h4 class="stat-value total-effectifs">
                                <?= number_format($stats['militaires'] ?? 0, 0, ',', ' ') ?>
                            </h4>
                            <p>Total Effectifs Prévus</p>
                        </div>
                    </div>
                </div>

                <!-- Total Actifs -->
                <div class="col-12 col-sm-6 <?= $card_col_class ?>">
                    <div class="stat-card">
                        <div class="stat-icon actif"><i class="fas fa-user-check"></i></div>
                        <div class="stat-info">
                            <h4 class="stat-value actif">
                                <?= number_format($stats['actifs'] ?? 0, 0, ',', ' ') ?>
                            </h4>
                            <p>Total Militaires Actifs</p>
                        </div>
                    </div>
                </div>

                <!-- Total Inactifs -->
                <div class="col-12 col-sm-6 <?= $card_col_class ?>">
                    <div class="stat-card">
                        <div class="stat-icon inactif"><i class="fas fa-user-slash"></i></div>
                        <div class="stat-info">
                            <h4 class="stat-value inactif">
                                <?= number_format($stats['inactifs'] ?? 0, 0, ',', ' ') ?>
                            </h4>
                            <p>Total Militaires Inactifs</p>
                        </div>
                    </div>
                </div>

                <!-- Total Contrôlés -->
                <div class="col-12 col-sm-6 <?= $card_col_class ?>">
                    <div class="stat-card">
                        <div class="stat-icon present"><i class="fas fa-clipboard-list"></i></div>
                        <div class="stat-info">
                            <h4 class="stat-value present">
                                <?= number_format($stats['controles_filtres'] ?? 0, 0, ',', ' ') ?></h4>
                            <p><?= $is_admin_ig ? 'Total Contrôlés' : 'Total Contrôlés (Prévus)' ?></p>
                        </div>
                    </div>
                </div>

                <?php if (!$is_admin_ig): ?>
                    <!-- Contrôlés hors effectif (uniquement pour opérateur) -->
                    <div class="col-12 col-sm-6 <?= $card_col_class ?>">
                        <div class="stat-card">
                            <div class="stat-icon favorable"><i class="fas fa-external-link-alt"></i></div>
                            <div class="stat-info">
                                <h4 class="stat-value favorable">
                                    <?= number_format($stats['controles_hors_filtre'] ?? 0, 0, ',', ' ') ?></h4>
                                <p>Contrôlés (Hors Garnisons)</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Non-vus au contrôle -->
                <div class="col-12 col-sm-6 <?= $card_col_class ?>">
                    <div class="stat-card">
                        <div class="stat-icon defavorable"><i class="fas fa-eye-slash"></i></div>
                        <div class="stat-info">
                            <h4 class="stat-value defavorable"><?= number_format($stats['non_vus'] ?? 0, 0, ',', ' ') ?>
                            </h4>
                            <p>Non-vus au Contrôle</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== STATISTIQUES BÉNÉFICIAIRES ===== -->
            <div class="row">
                <div class="col-12">
                    <div class="section-title" style="margin-top: 0;">
                        <i class="fas fa-heart"></i>
                        <span>Répartition des bénéficiaires</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon present">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-info">
                            <h4 class="stat-value present">
                                <?= number_format($stats['avec_beneficiaires'] ?? 0, 0, ',', ' ') ?>
                            </h4>
                            <p>Avec Bénéficiaires</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon defavorable">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="stat-info">
                            <h4 class="stat-value defavorable">
                                <?= number_format($stats['sans_beneficiaires'] ?? 0, 0, ',', ' ') ?>
                            </h4>
                            <p>Sans Bénéficiaires</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== STATISTIQUES DES MENTIONS (3 PRINCIPALES) ===== -->
            <div class="row">
                <div class="col-12">
                    <div class="section-title" style="margin-top: 0;">
                        <i class="fas fa-star"></i>
                        <span>Statistiques des mentions</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <!-- Présent -->
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon present">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h4 class="stat-value present">
                                <?= number_format($mentions_totals['present'] ?? 0, 0, ',', ' ') ?>
                            </h4>
                            <p>Présents</p>
                        </div>
                    </div>
                </div>
                <!-- Favorable -->
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon favorable">
                            <i class="fas fa-thumbs-up"></i>
                        </div>
                        <div class="stat-info">
                            <h4 class="stat-value favorable">
                                <?= number_format($mentions_totals['favorable'] ?? 0, 0, ',', ' ') ?>
                            </h4>
                            <p>Favorables</p>
                        </div>
                    </div>
                </div>
                <!-- Défavorable -->
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon defavorable">
                            <i class="fas fa-thumbs-down"></i>
                        </div>
                        <div class="stat-info">
                            <h4 class="stat-value defavorable">
                                <?= number_format($mentions_totals['defavorable'] ?? 0, 0, ',', ' ') ?>
                            </h4>
                            <p>Défavorables</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== STATISTIQUES PAR CATÉGORIE ===== -->
            <div class="row">
                <div class="col-12">
                    <div class="section-title">
                        <i class="fas fa-tag"></i>
                        <span>Militaires contrôlés par catégorie (effectifs prévus)</span>
                    </div>
                </div>
            </div>
            <div class="categories-stats-container">
                <?php foreach ($ordre_categories as $cat_libelle):
                    $effectif_total = 0;
                    foreach ($stats_detaillees['par_categorie'] as $stat) {
                        if (traduireCategorie($stat['categorie']) == $cat_libelle) {
                            $effectif_total = $stat['total'];
                            break;
                        }
                    }
                    $controles = isset($stats_par_categorie_controles[$cat_libelle]) ? $stats_par_categorie_controles[$cat_libelle] : 0;
                    $pourcentage = $effectif_total > 0 ? round(($controles / $effectif_total) * 100) : 0;
                    $classe_css = '';
                    $icone = getIconeCategorie($cat_libelle);
                    switch ($cat_libelle) {
                        case 'Actif':
                            $classe_css = 'actif';
                            break;
                        case 'Intégré':
                            $classe_css = 'integre';
                            break;
                        case 'Retraité':
                            $classe_css = 'retraite';
                            break;
                        case 'Décédé Après Bio':
                            $classe_css = 'decede-apres';
                            break;
                        case 'Décédé Avant Bio':
                            $classe_css = 'decede-avant';
                            break;
                    }
                ?>
                    <div class="categorie-stat-card <?= $classe_css ?>">
                        <div class="categorie-icon"><i class="fas <?= $icone ?>"></i></div>
                        <div class="categorie-info">
                            <h4><?= number_format($controles, 0, ',', ' ') ?></h4>
                            <p><?= $cat_libelle ?></p>
                            <?php if ($effectif_total > 0): ?>
                                <div class="categorie-progress">
                                    <div class="categorie-progress-bar"
                                        style="width: <?= $pourcentage ?>%; background: <?= getCouleurCategorie($cat_libelle) ?>;">
                                    </div>
                                </div>
                                <small style="font-size: 0.7rem; color: #6c757d;">
                                    <?= $pourcentage ?>% de l'effectif (<?= number_format($effectif_total, 0, ',', ' ') ?>)
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ===== RÉPARTITION DES CONTRÔLÉS PAR MENTION ===== -->
            <div class="row">
                <div class="col-12">
                    <div class="section-title">
                        <i class="fas fa-star"></i>
                        <span>Répartition des contrôlés par mention</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="card-modern">
                        <div class="card-header"
                            style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                            <h3 class="card-title" style="color: white; font-weight: 600;">
                                <i class="fas fa-star mr-2"></i> Mentions attribuées
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-7">
                                    <canvas id="mentionsPieChart"
                                        style="min-height: 280px; height: 280px; max-height: 280px; max-width: 100%;"></canvas>
                                </div>
                                <div class="col-md-5">
                                    <div class="table-responsive">
                                        <table class="table table-modern">
                                            <thead>
                                                <th>Mention</th>
                                                <th>Nombre</th>
                                                <th>%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $total_mentions = 0;
                                                // Définition des couleurs harmonisées avec les cartes des mentions
                                                $mention_colors = [
                                                    'favorable' => '#ffc107',
                                                    'défavorable' => '#dc3545',
                                                    'defavorable' => '#dc3545',
                                                    'présent' => '#28a745',
                                                    'present' => '#28a745',
                                                    'absent' => '#6c757d',
                                                    'autre' => '#6c757d'
                                                ];
                                                $mention_icons = [
                                                    'favorable' => 'fa-thumbs-up',
                                                    'défavorable' => 'fa-thumbs-down',
                                                    'defavorable' => 'fa-thumbs-down',
                                                    'présent' => 'fa-check-circle',
                                                    'present' => 'fa-check-circle',
                                                    'absent' => 'fa-question-circle',
                                                    'autre' => 'fa-tag'
                                                ];
                                                $mentions_data = $stats_detaillees['mentions_stats'] ?? [];
                                                foreach ($mentions_data as $m):
                                                    $total_mentions += $m['total'];
                                                endforeach;
                                                foreach ($mentions_data as $m):
                                                    $mention = $m['mention'];
                                                    $mention_lower = strtolower($mention);
                                                    $couleur = $mention_colors[$mention_lower] ?? '#6c757d';
                                                    $icone = $mention_icons[$mention_lower] ?? 'fa-tag';
                                                    $pourcentage = $total_mentions > 0 ? round(($m['total'] / $total_mentions) * 100) : 0;
                                                ?>
                                                    <tr>
                                                        <td><i class="fas <?= $icone ?>"
                                                                style="color: <?= $couleur ?>; margin-right: 8px;"></i>
                                                            <?= htmlspecialchars($mention) ?></td>
                                                        <td><?= number_format($m['total'] ?? 0, 0, ',', ' ') ?></td>
                                                        <td><?= $pourcentage ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== ANALYSES DÉTAILLÉES ===== -->
            <div class="row">
                <div class="col-12">
                    <div class="section-title">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analyses détaillées des effectifs</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <?php if (!empty($stats_detaillees['par_garnison'])): ?>
                    <div class="col-md-6">
                        <div class="card-modern">
                            <div class="card-header"
                                style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                                <h3 class="card-title" style="color: white; font-weight: 600;">
                                    <i class="fas fa-map-pin mr-2"></i> Effectifs par garnison
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="chart"><canvas id="garnisonChart"
                                        style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                                </div>
                                <div class="table-responsive mt-4">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th>Garnison</th>
                                                <th>Effectif</th>
                                                <th>Pourcentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats_detaillees['par_garnison'] as $stat): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($stat['garnison']) ?></strong></td>
                                                    <td><?= number_format($stat['total'] ?? 0, 0, ',', ' ') ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 8px; border-radius: 10px;">
                                                            <div class="progress-bar bg-primary"
                                                                style="width: <?= round(($stat['total'] / ($stats['militaires'] ?? 1)) * 100) ?>%; border-radius: 10px;">
                                                            </div>
                                                        </div>
                                                        <small
                                                            class="text-muted"><?= round(($stat['total'] / ($stats['militaires'] ?? 1)) * 100) ?>%</small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($stats_detaillees['par_categorie'])): ?>
                    <div class="col-md-6">
                        <div class="card-modern">
                            <div class="card-header"
                                style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                                <h3 class="card-title" style="color: white; font-weight: 600;">
                                    <i class="fas fa-tag mr-2"></i> Effectifs par catégorie
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="chart"><canvas id="categorieChart"
                                        style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                                </div>
                                <div class="table-responsive mt-4">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th>Catégorie</th>
                                                <th>Effectif</th>
                                                <th>Pourcentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats_detaillees['par_categorie'] as $stat):
                                                $categorie_libelle = traduireCategorie($stat['categorie']);
                                                $couleur = getCouleurCategorie($categorie_libelle);
                                                $icone = getIconeCategorie($categorie_libelle);
                                            ?>
                                                <tr>
                                                    <td><i class="fas <?= $icone ?>"
                                                            style="color: <?= $couleur ?>; margin-right: 8px;"></i>
                                                        <strong><?= htmlspecialchars($categorie_libelle) ?></strong>
                                                    </td>
                                                    <td><?= number_format($stat['total'] ?? 0, 0, ',', ' ') ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 8px; border-radius: 10px;">
                                                            <div class="progress-bar"
                                                                style="background: <?= $couleur ?>; width: <?= round(($stat['total'] / ($stats['militaires'] ?? 1)) * 100) ?>%; border-radius: 10px;">
                                                            </div>
                                                        </div>
                                                        <small
                                                            class="text-muted"><?= round(($stat['total'] / ($stats['militaires'] ?? 1)) * 100) ?>%</small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($stats_detaillees['par_categorie'])):
                $labels_categories = array_map('traduireCategorie', array_column($stats_detaillees['par_categorie'], 'categorie'));
                $data_categories = array_column($stats_detaillees['par_categorie'], 'total');
                $couleurs_categories_graph = [];
                foreach ($labels_categories as $label) {
                    $couleurs_categories_graph[] = getCouleurCategorie($label);
                }
            ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card-modern">
                            <div class="card-header"
                                style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                                <h3 class="card-title" style="color: white; font-weight: 600;">
                                    <i class="fas fa-tag mr-2"></i> Répartition par catégorie
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8"><canvas id="categoriePieChart"
                                            style="min-height: 300px; height: 300px; max-height: 300px; max-width: 100%;"></canvas>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="table-responsive">
                                            <table class="table table-modern">
                                                <thead>
                                                    <tr>
                                                        <th>Catégorie</th>
                                                        <th>Effectif</th>
                                                        <th>%</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($stats_detaillees['par_categorie'] as $stat):
                                                        $categorie_libelle = traduireCategorie($stat['categorie']);
                                                        $couleur = getCouleurCategorie($categorie_libelle);
                                                        $icone = getIconeCategorie($categorie_libelle);
                                                        $pourcentage = round(($stat['total'] / array_sum($data_categories)) * 100);
                                                    ?>
                                                        <tr>
                                                            <td><i class="fas <?= $icone ?>"
                                                                    style="color: <?= $couleur ?>; margin-right: 8px;"></i>
                                                                <?= htmlspecialchars($categorie_libelle) ?></td>
                                                            <td><?= number_format($stat['total'] ?? 0, 0, ',', ' ') ?></td>
                                                            <td><?= $pourcentage ?>%</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ===== ACTIVITÉS RÉCENTES ===== -->
            <div class="row">
                <div class="col-12">
                    <div class="section-title">
                        <i class="fas fa-history"></i>
                        <span>Activités récentes</span>
                    </div>
                </div>
            </div>
            <div class="row align-items-stretch">
                <!-- Derniers militaires -->
                <div class="col-md-6 d-flex">
                    <div class="card-modern h-100 w-100">
                        <div class="card-header"
                            style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                            <h3 class="card-title" style="color: white; font-weight: 600;">
                                <i class="fas fa-users mr-2"></i> Derniers militaires
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Noms</th>
                                        <th>Unité</th>
                                        <th>Garnison</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $derniers_militaires = $stats_detaillees['derniers_militaires'] ?? [];
                                    foreach ($derniers_militaires as $row):
                                    ?>
                                        <tr>
                                            <td><?= h($row['noms']) ?></td>
                                            <td><?= h($row['unite'] ?? 'N/A') ?></td>
                                            <td><?= h($row['garnison'] ?? 'N/A') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Derniers contrôles (sans colonne Type) -->
                <div class="col-md-6 d-flex">
                    <div class="card-modern h-100 w-100">
                        <div class="card-header"
                            style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                            <h3 class="card-title" style="color: white; font-weight: 600;">
                                <i class="fas fa-clipboard-list mr-2"></i> Derniers contrôles
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Militaire</th>
                                        <th>Mention</th>
                                        <!-- Colonne Type supprimée -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Tableau de correspondance mention -> icône
                                    $mentionIcons = [
                                        'favorable' => 'fa-thumbs-up',
                                        'défavorable' => 'fa-thumbs-down',
                                        'defavorable' => 'fa-thumbs-down',
                                        'présent' => 'fa-check-circle',
                                        'present' => 'fa-check-circle'
                                    ];
                                    foreach ($stats_detaillees['derniers_controles'] as $log):
                                        $mention = $log['mention'] ?? '';
                                        $mentionLower = strtolower($mention);
                                        $mentionClass = '';
                                        switch ($mentionLower) {
                                            case 'favorable':
                                                $mentionClass = 'success';
                                                break;
                                            case 'défavorable':
                                            case 'defavorable':
                                                $mentionClass = 'danger';
                                                break;
                                            case 'présent':
                                            case 'present':
                                                $mentionClass = 'info';
                                                break;
                                            default:
                                                $mentionClass = 'secondary';
                                        }
                                        $mentionIcon = $mentionIcons[$mentionLower] ?? 'fa-tag';
                                    ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($log['date_controle'])) ?></td>
                                            <td><?= h($log['noms']) ?><br><small><?= h($log['grade'] ?? '') ?>
                                                    <?= !empty($log['unite']) ? '- ' . h($log['unite']) : '' ?></small></td>
                                            <td><span class="badge bg-<?= $mentionClass ?>"><i
                                                        class="fas <?= $mentionIcon ?> mr-1"></i>
                                                    <?= h($mention ?: 'Non spécifié') ?></span></td>
                                            <!-- Cellule type supprimée -->
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== CARTE INTERACTIVE OPTIMISÉE DE LA RDC ===== -->
            <div class="row">
                <div class="col-12">
                    <div class="section-title">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Carte interactive de la RDC par province</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card-modern">
                        <div class="card-header" style="background: linear-gradient(135deg, #2e7d32, #1b5e20);">
                            <h5 class="card-title mb-0" style="color: white; font-weight: 600;">
                                <i class="fas fa-map-marked-alt mr-2"></i> Visualisation Géographique des Données
                                (Provinces)
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div id="drcMap" style="height: 540px; width: 100%;">
                                <div id="map-loader" class="d-flex justify-content-center align-items-center"
                                    style="height:100%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Bootstrap JS -->
<script src="../../assets/js/bootstrap.bundle.min.js"></script>

<!-- Scripts supplémentaires (chemins corrigés) -->
<script src="assets/js/xlsx.full.min.js"></script>
<script src="assets/js/jspdf.umd.min.js"></script>
<script src="assets/js/jspdf.plugin.autotable.min.js"></script>
<script src="assets/js/jszip.min.js"></script>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
    // Table de traduction des catégories injectée depuis PHP
    const categorieTraductions = <?= json_encode($traductions_categories) ?>;

    $(document).ready(function() {
        // Initialisation des tooltips sans modification du contenu des titres
        $('[data-toggle="tooltip"]').each(function() {
            var title = $(this).attr('title');
            if (title) {
                // Met la première lettre en majuscule, le reste en minuscule
                title = title.charAt(0) + title.slice(1).toLowerCase();
                $(this).attr('title', title);
            }
        });
        $('[data-toggle="tooltip"]').tooltip();

        // ===== GRAPHIQUES (UNIFIÉS) =====
        <?php if (!empty($stats_detaillees['par_garnison'])): ?>
            try {
                new Chart(document.getElementById('garnisonChart'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($stats_detaillees['par_garnison'], 'garnison')) ?>,
                        datasets: [{
                            label: 'Effectifs ',
                            data: <?= json_encode(array_column($stats_detaillees['par_garnison'], 'total')) ?>,
                            backgroundColor: '#2e7d32',
                            borderRadius: 8,
                            barPercentage: 0.7,
                            categoryPercentage: 0.8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: '#333',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#2e7d32',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.05)',
                                    drawBorder: false
                                },
                                ticks: {
                                    font: {
                                        family: 'Barlow',
                                        size: 12
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        family: 'Barlow',
                                        size: 12
                                    }
                                }
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Erreur graphique garnison:', e);
            }
        <?php endif; ?>

        <?php if (!empty($stats_detaillees['par_categorie'])):
            $labels_categories = array_map('traduireCategorie', array_column($stats_detaillees['par_categorie'], 'categorie'));
            $couleurs_categories_graph = [];
            foreach ($labels_categories as $label) {
                $couleurs_categories_graph[] = getCouleurCategorie($label);
            }
            $data_categories = array_column($stats_detaillees['par_categorie'], 'total');
        ?>
            try {
                new Chart(document.getElementById('categorieChart'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($labels_categories) ?>,
                        datasets: [{
                            label: 'Effectifs ',
                            data: <?= json_encode($data_categories) ?>,
                            backgroundColor: <?= json_encode($couleurs_categories_graph) ?>,
                            borderRadius: 8,
                            barPercentage: 0.7,
                            categoryPercentage: 0.8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: '#333',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#28a745',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.05)',
                                    drawBorder: false
                                },
                                ticks: {
                                    font: {
                                        family: 'Barlow',
                                        size: 12
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        family: 'Barlow',
                                        size: 12
                                    }
                                }
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Erreur graphique catégorie:', e);
            }

            try {
                new Chart(document.getElementById('categoriePieChart'), {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($labels_categories) ?>,
                        datasets: [{
                            data: <?= json_encode($data_categories) ?>,
                            backgroundColor: <?= json_encode($couleurs_categories_graph) ?>,
                            borderColor: '#ffffff',
                            borderWidth: 2,
                            borderRadius: 8
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
                                    font: {
                                        family: 'Barlow',
                                        size: 12
                                    },
                                    boxWidth: 15,
                                    boxHeight: 15,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                backgroundColor: '#333',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                titleFont: {
                                    family: 'Barlow',
                                    size: 13,
                                    weight: '600'
                                },
                                bodyFont: {
                                    family: 'Barlow',
                                    size: 12
                                },
                                padding: 12,
                                cornerRadius: 8,
                                borderWidth: 1
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Erreur graphique en anneau:', e);
            }
        <?php endif; ?>

        // ===== GRAPHIQUE DES MENTIONS =====
        <?php if (!empty($stats_detaillees['mentions_stats'])):
            $mentions_labels = array_column($stats_detaillees['mentions_stats'], 'mention');
            $mentions_data = array_column($stats_detaillees['mentions_stats'], 'total');
            $mentions_colors = [];
            foreach ($mentions_labels as $mention) {
                $m = strtolower($mention);
                if ($m === 'favorable') $mentions_colors[] = '#ffc107';
                elseif ($m === 'défavorable' || $m === 'defavorable') $mentions_colors[] = '#dc3545';
                elseif ($m === 'présent' || $m === 'present') $mentions_colors[] = '#28a745';
                else $mentions_colors[] = '#6c757d';
            }
        ?>
            try {
                new Chart(document.getElementById('mentionsPieChart'), {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($mentions_labels) ?>,
                        datasets: [{
                            data: <?= json_encode($mentions_data) ?>,
                            backgroundColor: <?= json_encode($mentions_colors) ?>,
                            borderColor: '#ffffff',
                            borderWidth: 2,
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
                                    font: {
                                        family: 'Barlow',
                                        size: 12
                                    },
                                    boxWidth: 15,
                                    boxHeight: 15,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                backgroundColor: '#333',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                titleFont: {
                                    family: 'Barlow',
                                    size: 13,
                                    weight: '600'
                                },
                                bodyFont: {
                                    family: 'Barlow',
                                    size: 12
                                },
                                padding: 12,
                                cornerRadius: 8
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Erreur graphique mentions:', e);
            }
        <?php endif; ?>

        // ===== CARTE =====
        try {
            initOptimizedMap();
        } catch (e) {
            console.error('Erreur initialisation carte:', e);
            document.getElementById('map-loader').innerHTML =
                '<div class="alert alert-danger">Impossible de charger la carte.</div>';
        }

        // ===== FIX PUSH MENU =====
        // Supprime les éventuels gestionnaires existants (AdminLTE) et ajoute notre propre bascule
        $('[data-widget="pushmenu"]').off('click').on('click', function(e) {
            e.preventDefault();
            $('body').toggleClass('sidebar-collapse');
            refreshMapLayout();
        });

        $(window).on('resize', refreshMapLayout);
    });

    // ===== CARTE OPTIMISÉE =====
    const provinceData = <?php echo $provinceStatsJson; ?>;

    const mapConfig = {
        center: [-2.5, 23.5],
        zoom: 5,
        maxBounds: [
            [-15, 12],
            [5, 32]
        ],
        maxBoundsViscosity: 1.0,
        fadeAnimation: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        zoomAnimation: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        markerZoomAnimation: !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    };

    let geoJsonCache = null;
    let map = null;
    let geojsonLayer = null;
    let selectedProvince = null;
    let selectedLayer = null;
    let hoverTimeout = null;
    let currentHoverLayer = null;

    function refreshMapLayout() {
        if (!map) return;

        window.setTimeout(() => {
            try {
                map.invalidateSize(true);
            } catch (error) {
                console.warn('Actualisation carte ignorée :', error);
            }
        }, 120);
    }

    function initOptimizedMap() {
        const mapEl = document.getElementById('drcMap');
        if (!mapEl) return;

        map = L.map('drcMap', {
            ...mapConfig,
            zoomControl: true,
            attributionControl: false,
            dragging: true,
            scrollWheelZoom: true,
            doubleClickZoom: true,
            boxZoom: true,
            keyboard: true,
            touchZoom: true,
            tap: true,
            zoomSnap: 0.1,
            zoomDelta: 0.25
        });

        const infoControl = createOptimizedInfoControl();
        infoControl.addTo(map);

        loadOptimizedGeoJSON(infoControl);
        addPerformanceControls();
        refreshMapLayout();
        window.setTimeout(refreshMapLayout, 300);
    }

    function createOptimizedInfoControl() {
        const info = L.control({
            position: 'topright'
        });
        let lastUpdate = 0;
        const throttleTime = 50;

        info.onAdd = function() {
            this._div = L.DomUtil.create('div', 'map-info-box');
            this.clearInfo();
            return this._div;
        };

        info.clearInfo = function() {
            this._div.innerHTML = '<span>Survolez une province</span>';
        };

        info.updateWithStats = function(provinceName, stats) {
            if (!provinceName) {
                this.clearInfo();
                return;
            }
            const total = (stats.actifs || 0) + (stats.inactifs || 0);
            this._div.innerHTML = `
            <h5>${provinceName}</h5>
            <div class="stats-mini">
                <div><i class="fas fa-user-check"></i> <span>Actifs :</span> <strong>${stats.actifs || 0}</strong></div>
                <div><i class="fas fa-user-slash"></i> <span>Inactifs :</span> <strong>${stats.inactifs || 0}</strong></div>
                <div class="total"><i class="fas fa-users"></i> <span>Total :</span> <strong>${total}</strong></div>
            </div>
        `;
        };

        info.update = function(props) {
            if (!props) {
                this.clearInfo();
                return;
            }
            const now = Date.now();
            if (now - lastUpdate < throttleTime) return;
            lastUpdate = now;
            const name = props.PROVINCENAME || props.name || "Inconnue";
            const stats = findProvinceStats(name);
            this.updateWithStats(name, stats);
        };
        return info;
    }

    function findProvinceStats(provinceName) {
        if (!provinceName) return {
            actifs: 0,
            inactifs: 0
        };
        if (!provinceData || typeof provinceData !== 'object') {
            console.warn('provinceData invalide');
            return {
                actifs: 0,
                inactifs: 0
            };
        }

        let withoutAccents = provinceName.toUpperCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '');

        const variants = [
            provinceName.toUpperCase(),
            provinceName,
            withoutAccents,
            withoutAccents.replace(/[-\s]/g, ''),
            withoutAccents.replace(/-/g, ' '),
            provinceName.toUpperCase().replace(/[-\s]/g, ''),
            provinceName.toUpperCase().replace(/-/g, ' '),
            provinceName.replace(/-/g, ' ').toUpperCase(),
            provinceName.split('-').map(word =>
                word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
            ).join(' ')
        ];

        for (let variant of variants) {
            if (provinceData[variant]) return provinceData[variant];
        }
        return {
            actifs: 0,
            inactifs: 0
        };
    }

    function loadOptimizedGeoJSON(infoControl) {
        const loader = document.getElementById('map-loader');
        loader.style.display = 'flex';

        if (geoJsonCache) {
            renderGeoJSON(geoJsonCache, infoControl);
            return;
        }

        fetch('assets/data/drc_provinces.json', {
                cache: 'force-cache'
            })
            .then(response => {
                if (!response.ok) throw new Error('Erreur réseau');
                return response.json();
            })
            .then(data => {
                geoJsonCache = data;
                renderGeoJSON(data, infoControl);
            })
            .catch(err => {
                console.error('Erreur chargement GeoJSON:', err);
                loader.innerHTML = `
                <div class="alert alert-warning m-3">
                    <i class="fas fa-exclamation-triangle"></i> Erreur de chargement de la carte.
                    <button onclick="location.reload()" class="btn btn-sm btn-primary ml-2">
                        <i class="fas fa-sync-alt"></i> Réessayer
                    </button>
                </div>
            `;
            });
    }

    function renderGeoJSON(data, infoControl) {
        const loader = document.getElementById('map-loader');
        if (!map) return;

        const styles = {
            default: {
                fillColor: '#2e7d32',
                weight: 1,
                opacity: 0.8,
                color: '#1b5e20',
                fillOpacity: 0.6,
                smoothFactor: 1
            },
            hover: {
                weight: 2,
                color: '#ffc107',
                fillOpacity: 0.8,
                smoothFactor: 1
            },
            selected: {
                weight: 3,
                color: '#ffc107',
                fillColor: '#1b5e20',
                fillOpacity: 0.9
            }
        };

        geojsonLayer = L.geoJson(data, {
            style: styles.default,
            smoothFactor: 1,
            onEachFeature: (feature, layer) => {
                const provinceName = feature.properties.PROVINCENAME || feature.properties.name || "Inconnue";
                const stats = findProvinceStats(provinceName);

                layer.bindPopup(createPopupContent(provinceName, stats), {
                    maxWidth: 280,
                    className: 'custom-popup',
                    autoPan: true
                });

                layer.bindTooltip(provinceName, {
                    permanent: false,
                    direction: 'center',
                    className: 'province-hover-tooltip',
                    opacity: 0.9
                });

                layer.on('mouseover', function(e) {
                    if (selectedProvince === provinceName) return;
                    if (hoverTimeout) {
                        clearTimeout(hoverTimeout);
                        hoverTimeout = null;
                    }
                    if (currentHoverLayer && currentHoverLayer !== e.target) {
                        currentHoverLayer.setStyle(styles.default);
                    }
                    e.target.setStyle(styles.hover);
                    e.target.bringToFront();
                    currentHoverLayer = e.target;
                    infoControl.updateWithStats(provinceName, stats);
                });

                layer.on('mouseout', function(e) {
                    if (selectedProvince === provinceName) return;
                    if (currentHoverLayer === e.target) {
                        hoverTimeout = setTimeout(() => {
                            e.target.setStyle(styles.default);
                            currentHoverLayer = null;
                            if (!selectedProvince) infoControl.clearInfo();
                            hoverTimeout = null;
                        }, 100);
                    }
                });

                layer.on('click', function(e) {
                    if (hoverTimeout) clearTimeout(hoverTimeout);
                    if (selectedLayer && selectedLayer !== e.target) {
                        selectedLayer.setStyle(styles.default);
                        selectedLayer.closePopup();
                    }
                    if (selectedProvince === provinceName) {
                        selectedLayer.setStyle(styles.default);
                        selectedLayer.closePopup();
                        selectedProvince = null;
                        selectedLayer = null;
                        infoControl.clearInfo();
                    } else {
                        selectedProvince = provinceName;
                        selectedLayer = e.target;
                        e.target.setStyle(styles.selected);
                        e.target.bringToFront();
                        e.target.openPopup();
                        infoControl.clearInfo();
                    }
                });
            }
        }).addTo(map);

        const drcBounds = geojsonLayer.getBounds();

        map.fitBounds(drcBounds, {
            padding: [20, 20],
            maxZoom: 6,
            animate: false
        });

        map.setMaxBounds(drcBounds.pad(0.08));
        map.setMinZoom(4.8);
        map.setMaxZoom(7.5);
        refreshMapLayout();
        window.setTimeout(refreshMapLayout, 200);
        window.setTimeout(refreshMapLayout, 600);

        loader.style.display = 'none';
        addLegend();
        refreshMapLayout();
    }

    function createPopupContent(provinceName, stats) {
        const total = stats.actifs + stats.inactifs;
        const provinceForLink = stats.nom_original || null;
        let linkHtml = '';
        if (provinceForLink) {
            linkHtml =
                `<a href="modules/militaires/liste.php?province=${encodeURIComponent(provinceForLink)}" class="btn-view"><i class="fas fa-list"></i> Voir la liste</a>`;
        }
        return `
        <div class="popup-content">
            <h4>${provinceName}</h4>
            <div class="stats">
                <div class="stat-item actif"><span><i class="fas fa-user-check"></i> Actifs</span><strong>${stats.actifs}</strong></div>
                <div class="stat-item inactif"><span><i class="fas fa-user-slash"></i> Inactifs</span><strong>${stats.inactifs}</strong></div>
                <div class="stat-item total"><span><i class="fas fa-users"></i> Total</span><strong>${total}</strong></div>
            </div>
            ${linkHtml}
        </div>
    `;
    }

    function addPerformanceControls() {
        if (!map) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            map.options.fadeAnimation = false;
            map.options.zoomAnimation = false;
            map.options.markerZoomAnimation = false;
        }
        map.on('moveend', () => {
            if (map.getZoom() < 5 && geojsonLayer) {
                geojsonLayer.eachLayer(layer => layer.setStyle({
                    weight: 0.5
                }));
            }
        });
    }

    function addLegend() {
        if (!map) return;
        const legend = L.control({
            position: 'bottomright'
        });
        legend.onAdd = function() {
            const div = L.DomUtil.create('div', 'map-legend');
            div.innerHTML = `
            <h6><i class="fas fa-map-signs" style="margin-right: 6px;"></i> Légende</h6>
            <div class="legend-item">
                <span class="color-box" style="background: #2e7d32;"></span>
                <span>État normal</span>
            </div>
            <div class="legend-item">
                <span class="color-box" style="background: #1b5e20; border: 2px solid #ffc107;"></span>
                <span>Survol</span>
            </div>
            <div class="legend-item">
                <span class="color-box" style="background: #1b5e20; border: 4px solid #ffc107; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.3);"></span>
                <span>Sélectionné</span>
            </div>
            <div style="margin-top: 10px; font-size: 11px; color: #6c757d; border-top: 1px dashed #ced4da; padding-top: 6px;">
                <i class="fas fa-info-circle"></i> Cliquez sur une province pour plus de détails
            </div>
        `;
            return div;
        };
        legend.addTo(map);
    }

    // ===== FONCTIONS D'EXPORT DES NON-VUS (pour OPERATEUR) =====
    <?php if (!$is_admin_ig): ?>
        const nonVusData = <?= json_encode($non_vus_list) ?>;
        const nonVusZones = <?= json_encode($non_vus_zones) ?>;
        const filteredGarnisons = <?= json_encode($garnisons_filtre_js) ?>;
        const filteredCategories = <?= json_encode($categories_filtre_js) ?>;

        function getTimestamp() {
            const now = new Date();
            return `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}_${String(now.getHours()).padStart(2,'0')}h${String(now.getMinutes()).padStart(2,'0')}`;
        }

        // Fonction pour formater une liste avec "et"
        function formatListWithAnd(list) {
            if (!list || list.length === 0) return '';
            if (list.length === 1) return list[0];
            if (list.length === 2) return `${list[0]} et ${list[1]}`;
            const allButLast = list.slice(0, -1).join(', ');
            return `${allButLast} et ${list[list.length - 1]}`;
        }

        function getExportTitle() {
            let garnisonStr = '';
            let zoneStr = '';

            if (filteredGarnisons.length > 0) {
                garnisonStr = formatListWithAnd(filteredGarnisons);
            }
            if (nonVusZones.length > 0) {
                zoneStr = formatListWithAnd(nonVusZones);
            }

            let suffix = '';
            if (garnisonStr && zoneStr) {
                suffix = `${garnisonStr} - ${zoneStr}`;
            } else if (garnisonStr) {
                suffix = garnisonStr;
            } else if (zoneStr) {
                suffix = zoneStr;
            }

            if (suffix) {
                return `LISTE DES MILITAIRES NON-VUS AU CONTROLE (${suffix})`;
            } else {
                return "LISTE DES MILITAIRES NON-VUS AU CONTROLE";
            }
        }

        function getFileBaseName() {
            let base = "non_vus";
            if (filteredGarnisons.length > 0) {
                let garnisonStr = filteredGarnisons.slice(0, 2).join('_');
                if (garnisonStr.length > 30) garnisonStr = garnisonStr.substring(0, 30);
                base += `_${garnisonStr}`;
            }
            if (nonVusZones.length > 0) {
                let zonesStr = nonVusZones.join('_');
                if (zonesStr.length > 20) zonesStr = zonesStr.substring(0, 20);
                base += `_${zonesStr}`;
            }
            base += `_${getTimestamp()}`;
            return base.replace(/[^a-z0-9_\-]/gi, '_');
        }

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

        // --- CSV ---
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
            fetch('?ajax=log_export&type=CSV&filtres=' + encodeURIComponent(JSON.stringify({
                zones: nonVusZones,
                garnisons: filteredGarnisons
            })));
            const headerLines = [
                ['MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS'],
                ['INSPECTORAT GENERAL DES FARDC'],
                [getExportTitle()]
            ];
            const headers = ['SERIE', 'MATRICULE', 'NOMS', 'GRADE', 'UNITE', 'BENEFICIAIRE', 'GARNISON', 'PROVINCE',
                'CATEGORIE', 'ZDEF'
            ];
            const rows = nonVusData.map((m, index) => [
                index + 1,
                m.matricule,
                m.noms,
                m.grade,
                m.unite,
                m.beneficiaire,
                m.garnison,
                m.province,
                categorieTraductions[m.categorie] || m.categorie,
                m.zdef
            ]);
            const csvContent = [
                ...headerLines.map(line => line[0]),
                '',
                headers.join(';'),
                ...rows.map(r => r.join(';'))
            ].join('\n');
            const blob = new Blob(["\uFEFF" + csvContent], {
                type: 'text/csv;charset=utf-8;'
            });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${getFileBaseName()}.csv`;
            link.click();
            Swal.fire({
                icon: 'success',
                title: 'Export réussi',
                text: 'Liste des non-vus (CSV) générée.',
                timer: 1500,
                toast: true,
                position: 'top-end'
            });
        }

        // --- PDF ---
        async function generateNonVusPDFBlob() {
            if (typeof window.jspdf === 'undefined') throw new Error('jsPDF non chargé');
            const {
                jsPDF
            } = window.jspdf;
            if (typeof jsPDF !== 'function') throw new Error('jsPDF non initialisé');

            const headerLines = [
                'MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS',
                'INSPECTORAT GENERAL DES FARDC',
                getExportTitle()
            ];
            const headers = ['SERIE', 'MATRICULE', 'NOMS', 'GRADE', 'UNITE', 'BENEFICIAIRE', 'GARNISON', 'PROVINCE',
                'CATEGORIE', 'ZDEF'
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
                categorieTraductions[m.categorie] || m.categorie,
                m.zdef
            ]);

            let logo, qrCode, watermark;
            try {
                [logo, qrCode, watermark] = await Promise.all([
                    loadImage('assets/img/new-logo-ig-fardc.png'),
                    loadImage('assets/img/qr-code-ig-fardc.png'),
                    loadImage('assets/img/filigrane_logo_ig_fardc.png')
                ]);
            } catch (imgErr) {
                console.warn('Images non trouvées, poursuite sans', imgErr);
                logo = qrCode = watermark = {
                    dataURL: null,
                    width: 100,
                    height: 100
                };
            }

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
            headers.forEach((_, i) => {
                columnStyles[i] = {
                    halign: i === 0 ? 'center' : 'left'
                };
            });

            let headerAdded = false;

            function addWatermark() {
                if (!watermark.dataURL) return;
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
            }

            function addFirstPageHeader() {
                if (logo.dataURL) {
                    const logoHeight = 18;
                    const logoWidth = (logo.width / logo.height) * logoHeight;
                    doc.addImage(logo.dataURL, 'PNG', margin, 8, logoWidth, logoHeight);
                }
                doc.setFontSize(9);
                doc.setTextColor(100);
                doc.setFont('helvetica', 'normal');
                const dateStr = 'Kinshasa, le ' + new Date().toLocaleDateString('fr-FR');
                doc.text(dateStr, pageWidth - rightMargin, 12, {
                    align: 'right'
                });
                doc.setFontSize(12);
                doc.setTextColor(0);
                doc.setFont('helvetica', 'bold');
                doc.text(headerLines[0], pageWidth / 2, 25, {
                    align: 'center'
                });
                doc.setFontSize(11);
                doc.text(headerLines[1], pageWidth / 2, 32, {
                    align: 'center'
                });
                doc.setFontSize(14);
                doc.setTextColor(255, 0, 0);
                doc.text(headerLines[2], pageWidth / 2, 42, {
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
                    const qrSize = 8;
                    const qrX = pageWidth - rightMargin - qrSize;
                    const qrY = lineY - qrSize;
                    doc.addImage(qrCode.dataURL, 'PNG', qrX, qrY, qrSize, qrSize);
                }
                doc.setFontSize(7);
                doc.setTextColor(100);
                doc.setFont('helvetica', 'normal');
                doc.text('Inspectorat Général des FARDC, Avenue des écuries, N°54, Quartier Joli Parc, Commune de NGALIEMA',
                    pageWidth / 2, footerY, {
                        align: 'center'
                    });
                doc.setFont('times', 'italic');
                doc.text('Emails : infoigfardc2017@gmail.com; igfardc2017@yahoo.fr', pageWidth / 2, footerY + 5, {
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
                body: body,
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
                    if (data.pageNumber === 1 && !headerAdded) addFirstPageHeader();
                    addFooter(data.pageNumber);
                }
            });

            return doc.output('blob');
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
            fetch('?ajax=log_export&type=PDF&filtres=' + encodeURIComponent(JSON.stringify({
                zones: nonVusZones,
                garnisons: filteredGarnisons
            })));
            Swal.fire({
                title: 'Préparation du PDF...',
                text: 'Génération de la liste des non-vus',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            try {
                const pdfBlob = await generateNonVusPDFBlob();
                const link = document.createElement('a');
                link.href = URL.createObjectURL(pdfBlob);
                link.download = `${getFileBaseName()}.pdf`;
                link.click();
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

        // --- Excel ---
        function exportNonVusExcel() {
            if (typeof XLSX === 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Bibliothèque XLSX non disponible.'
                });
                return;
            }
            if (!nonVusData || nonVusData.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Aucun non-vu',
                    text: 'Tous les militaires ont été contrôlés.',
                    timer: 2000
                });
                return;
            }
            fetch('?ajax=log_export&type=Excel&filtres=' + encodeURIComponent(JSON.stringify({
                zones: nonVusZones,
                garnisons: filteredGarnisons
            })));
            const headerLines = [
                ['MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS'],
                ['INSPECTORAT GENERAL DES FARDC'],
                [getExportTitle()]
            ];
            const headers = ['SERIE', 'MATRICULE', 'NOMS', 'GRADE', 'UNITE', 'BENEFICIAIRE', 'GARNISON', 'PROVINCE',
                'CATEGORIE', 'ZDEF'
            ];
            const data = nonVusData.map((m, index) => [
                index + 1,
                m.matricule,
                m.noms,
                m.grade,
                m.unite,
                m.beneficiaire,
                m.garnison,
                m.province,
                categorieTraductions[m.categorie] || m.categorie,
                m.zdef
            ]);
            const worksheetData = [...headerLines, [], headers, ...data];
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
                        c: headers.length - 1
                    }
                });
            }
            XLSX.utils.book_append_sheet(wb, ws, 'Non_vus');
            XLSX.writeFile(wb, `${getFileBaseName()}.xlsx`);
            Swal.fire({
                icon: 'success',
                title: 'Export réussi',
                text: 'Fichier Excel des non-vus généré.',
                timer: 1500,
                toast: true,
                position: 'top-end'
            });
        }

        // --- ZIP ---
        async function exportNonVusZIP() {
            if (typeof JSZip === 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Bibliothèque JSZip non disponible.'
                });
                return;
            }
            if (!nonVusData || nonVusData.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Aucun non-vu',
                    text: 'Tous les militaires ont été contrôlés.',
                    timer: 2000
                });
                return;
            }
            fetch('?ajax=log_export&type=ZIP&filtres=' + encodeURIComponent(JSON.stringify({
                zones: nonVusZones,
                garnisons: filteredGarnisons
            })));
            Swal.fire({
                title: 'Génération du ZIP...',
                text: 'Préparation des fichiers (CSV, Excel, PDF)',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            try {
                const headerLines = [
                    ['MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS'],
                    ['INSPECTORAT GENERAL DES FARDC'],
                    [getExportTitle()]
                ];
                const headers = ['SERIE', 'MATRICULE', 'NOMS', 'GRADE', 'UNITE', 'BENEFICIAIRE', 'GARNISON', 'PROVINCE',
                    'CATEGORIE', 'ZDEF'
                ];
                const rows = nonVusData.map((m, index) => [
                    index + 1,
                    m.matricule,
                    m.noms,
                    m.grade,
                    m.unite,
                    m.beneficiaire,
                    m.garnison,
                    m.province,
                    categorieTraductions[m.categorie] || m.categorie,
                    m.zdef
                ]);

                // CSV
                const csvContent = [
                    ...headerLines.map(line => line[0]),
                    '',
                    headers.join(';'),
                    ...rows.map(r => r.join(';'))
                ].join('\n');
                const csvBlob = new Blob(["\uFEFF" + csvContent], {
                    type: 'text/csv;charset=utf-8;'
                });

                // Excel
                const worksheetData = [...headerLines, [], headers, ...rows];
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
                            c: headers.length - 1
                        }
                    });
                }
                XLSX.utils.book_append_sheet(wb, ws, 'Non_vus');
                const excelBlob = new Blob([XLSX.write(wb, {
                    bookType: 'xlsx',
                    type: 'array'
                })], {
                    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                });

                // PDF
                const pdfBlob = await generateNonVusPDFBlob();

                const zip = new JSZip();
                zip.file("non_vus.csv", csvBlob);
                zip.file("non_vus.xlsx", excelBlob);
                zip.file("non_vus.pdf", pdfBlob);

                const zipBlob = await zip.generateAsync({
                    type: "blob"
                });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(zipBlob);
                link.download = `${getFileBaseName()}.zip`;
                link.click();

                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: 'Export réussi',
                    text: 'Archive ZIP des non-vus générée.',
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
                    text: 'Impossible de générer l\'archive ZIP.',
                    timer: 2000
                });
            }
        }

        // Attachement des événements
        document.getElementById('export-nonvus-csv').addEventListener('click', exportNonVusCSV);
        document.getElementById('export-nonvus-pdf').addEventListener('click', exportNonVusPDF);
        document.getElementById('export-nonvus-excel').addEventListener('click', exportNonVusExcel);
        document.getElementById('export-nonvus-zip').addEventListener('click', exportNonVusZIP);
    <?php endif; ?>
</script>

<?php
// Journaliser l'accès au tableau de bord
/* audit_action('CONSULTATION', 'dashboard', null, 'Accès au tableau de bord'); */

include 'includes/footer.php';
?>