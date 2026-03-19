<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../../includes/functions.php';
require_login();

$pdo->exec("SET NAMES 'utf8'");
$pdo->exec("SET CHARACTER SET utf8");

error_reporting(E_ALL);
ini_set('display_errors', 0);

$draw = $_POST['draw'];
$start = (int)$_POST['start'];
$length = (int)$_POST['length'];
$search = $_POST['search']['value'] ?? '';
$orderColumn = $_POST['order'][0]['column'] ?? 3;      // index DataTables
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';

// Ordre personnalisé des grades
$grade_order = $_POST['grade_order'] ?? [
    'GENA', 'GAM', 'LTGEN', 'AMR', 'GENMAJ', 'VAM',
    'GENBDE', 'CAM', 'COL', 'CPV', 'LTCOL', 'CPF',
    'MAJ', 'CPC', 'CAPT', 'LDV', 'LT', 'EV', 'SLT', '2EV',
    'A-C', 'MCP', 'A-1', '1MC', 'ADJ', 'MRC', '1SM', '1MR',
    'SM', '2MR', '1SGT', 'MR', 'SGT', 'QMT', 'CPL', '1MT',
    '1CL', '2MT', '2CL', 'MT', 'REC', 'ASK', 'COMD'
];

// Filtres personnalisés
$categorie = $_POST['categorie'] ?? '';
$garnison = $_POST['garnison'] ?? '';
$province = $_POST['province'] ?? '';
$zdef = $_POST['zdef'] ?? '';
$statut = $_POST['statut'] ?? '';

// --- Requête COUNT ---
$countSql = "SELECT COUNT(*) FROM militaires WHERE 1=1";
$countParams = [];

if ($categorie) {
    $countSql .= " AND categorie = ?";
    $countParams[] = $categorie;
}
if ($garnison) {
    $countSql .= " AND garnison = ?";
    $countParams[] = $garnison;
}
if ($province) {
    $countSql .= " AND province = ?";
    $countParams[] = $province;
}
if ($statut !== '') {
    $countSql .= " AND statut = ?";
    $countParams[] = $statut;
}
if ($search) {
    $countSql .= " AND (matricule LIKE ? OR noms LIKE ? OR grade LIKE ? OR unite LIKE ? OR beneficiaire LIKE ? OR garnison LIKE ? OR province LIKE ? OR categorie LIKE ?)";
    $searchTerm = "%$search%";
    $countParams = array_merge($countParams, array_fill(0, 8, $searchTerm));
}

$stmt = $pdo->prepare($countSql);
$stmt->execute($countParams);
$totalRecords = $stmt->fetchColumn();

// --- Requête SELECT (toutes les données) ---
$sql = "SELECT * FROM militaires WHERE 1=1";
$params = [];

if ($categorie) {
    $sql .= " AND categorie = ?";
    $params[] = $categorie;
}
if ($garnison) {
    $sql .= " AND garnison = ?";
    $params[] = $garnison;
}
if ($province) {
    $sql .= " AND province = ?";
    $params[] = $province;
}
if ($statut !== '') {
    $sql .= " AND statut = ?";
    $params[] = $statut;
}
if ($search) {
    $sql .= " AND (matricule LIKE ? OR noms LIKE ? OR grade LIKE ? OR unite LIKE ? OR beneficiaire LIKE ? OR garnison LIKE ? OR province LIKE ? OR categorie LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, array_fill(0, 8, $searchTerm));
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fonction ZDEF ---
function getZoneDefense($province) {
    if (empty($province)) return '';
    $province = strtoupper(trim($province));
    $province = str_replace(
        ['É', 'È', 'Ê', 'Ë', 'Â', 'Ä', 'Î', 'Ï', 'Ô', 'Ö', 'Û', 'Ü', 'Ç'],
        ['E', 'E', 'E', 'E', 'A', 'A', 'I', 'I', 'O', 'O', 'U', 'U', 'C'],
        $province
    );
    $zone1 = ['KWILU', 'KWANGO', 'MAI-NDOMBE', 'MAI NDOMBE', 'MAINDOMBE', 'KONGO-CENTRAL', 'KONGO CENTRAL', 'KONGOCENTRAL', 'KINSHASA', 'EQUATEUR', 'ÉQUATEUR', 'MONGALA', 'NORD-UBANGI', 'NORD UBANGI', 'NORDUBANGI', 'SUD-UBANGI', 'SUD UBANGI', 'SUDUBANGI', 'TSHUAPA'];
    $zone2 = ['HAUT-KATANGA', 'HAUT KATANGA', 'HAUTKATANGA', 'HAUT-LOMAMI', 'HAUT LOMAMI', 'HAUTLOMAMI', 'LUALABA', 'TANGANYIKA', 'KASAI', 'KASAÏ', 'KASAI-CENTRAL', 'KASAI CENTRAL', 'KASAICENTRAL', 'KASAÏ-CENTRAL', 'KASAI-ORIENTAL', 'KASAI ORIENTAL', 'KASAIORIENTAL', 'KASAÏ-ORIENTAL', 'SANKURU', 'LOMAMI'];
    $zone3 = ['HAUT-UELE', 'HAUT UELE', 'HAUTUELE', 'BAS-UELE', 'BAS UELE', 'BASUELE', 'ITURI', 'TSHOPO', 'NORD-KIVU', 'NORD KIVU', 'NORDKIVU', 'SUD-KIVU', 'SUD KIVU', 'SUDKIVU', 'MANIEMA'];
    if (in_array($province, $zone1)) return '1ZDef';
    if (in_array($province, $zone2)) return '2ZDef';
    if (in_array($province, $zone3)) return '3ZDef';
    return '';
}

// --- Filtrage ZDEF ---
$filteredData = [];
foreach ($allData as $row) {
    $rowZdef = getZoneDefense($row['province'] ?? '');
    if (!$zdef || $rowZdef === $zdef) {
        $filteredData[] = $row;
    }
}

// --- Tri personnalisé ---
// Mapping des index DataTables (colonne réelle) vers les noms de champs
$columnMap = [
    1 => 'matricule',
    2 => 'noms',
    3 => 'grade',
    4 => 'unite',
    5 => 'beneficiaire',
    6 => 'garnison',
    7 => 'province',
    8 => 'categorie',
    9 => 'province' // pour la colonne ZDEF (on trie par province, ce n'est pas idéal mais acceptable)
];

$columnName = $columnMap[$orderColumn] ?? 'grade';

usort($filteredData, function($a, $b) use ($columnName, $orderDir, $grade_order) {
    $valA = $a[$columnName] ?? '';
    $valB = $b[$columnName] ?? '';

    // Tri spécial pour la colonne 'grade'
    if ($columnName === 'grade') {
        $posA = array_search($valA, $grade_order);
        $posB = array_search($valB, $grade_order);
        $posA = ($posA !== false) ? $posA : 999;
        $posB = ($posB !== false) ? $posB : 999;

        if ($posA == $posB) return 0;
        if ($orderDir === 'asc') {
            return ($posA < $posB) ? -1 : 1;
        } else {
            return ($posA > $posB) ? -1 : 1;
        }
    } else {
        // Tri alphabétique normal
        if ($valA == $valB) return 0;
        if ($orderDir === 'asc') {
            return ($valA < $valB) ? -1 : 1;
        } else {
            return ($valA > $valB) ? -1 : 1;
        }
    }
});

// --- Pagination ---
$data = array_slice($filteredData, $start, $length);

$response = [
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => count($filteredData),
    'data' => $data
];

ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

