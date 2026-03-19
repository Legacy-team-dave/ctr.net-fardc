<?php
// Définir l'en-tête UTF-8
header('Content-Type: application/json; charset=utf-8');

require_once '../../includes/functions.php';
require_login();

// Activer le traitement UTF-8 pour PDO
$pdo->exec("SET NAMES 'utf8'");
$pdo->exec("SET CHARACTER SET utf8");

error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $categorie = $_POST['categorie'] ?? '';
    $garnison = $_POST['garnison'] ?? '';
    $province = $_POST['province'] ?? '';
    $zdef = $_POST['zdef'] ?? '';
    $statut = $_POST['statut'] ?? '';
    $search = $_POST['search'] ?? '';
    $selected = $_POST['selected'] ?? [];
    $orderColumn = $_POST['order_column'] ?? 3;
    $orderDir = $_POST['order_dir'] ?? 'asc';

    // Ordre personnalisé des grades
    $grade_order = [
        'GENA', 'GAM', 'LTGEN', 'AMR', 'GENMAJ', 'VAM',
        'GENBDE', 'CAM', 'COL', 'CPV', 'LTCOL', 'CPF',
        'MAJ', 'CPC', 'CAPT', 'LDV', 'LT', 'EV', 'SLT', '2EV',
        'A-C', 'MCP', 'A-1', '1MC', 'ADJ', 'MRC', '1SM', '1MR',
        'SM', '2MR', '1SGT', 'MR', 'SGT', 'QMT', 'CPL', '1MT',
        '1CL', '2MT', '2CL', 'MT', 'REC', 'ASK', 'COMD'
    ];

    // Construction de la requête
    $sql = "SELECT * FROM militaires WHERE 1=1";
    $params = [];

    if (!empty($selected) && is_array($selected) && count($selected) > 0) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $sql .= " AND matricule IN ($placeholders)";
        $params = $selected;
    } else {
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
        
        if (!empty($search)) {
            $sql .= " AND (matricule LIKE ? OR noms LIKE ? OR grade LIKE ? OR unite LIKE ? OR beneficiaire LIKE ? OR garnison LIKE ? OR province LIKE ? OR categorie LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, array_fill(0, 8, $searchTerm));
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $militaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fonction ZDEF
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

    // Libellés des catégories
    $categories_labels = [
        'ACTIF' => 'Actif',
        'RETRAITES' => 'Retraité',
        'INTEGRES' => 'Intégré',
        'DCD_AV_BIO' => 'Décédé avant Bio',
        'DCD_AP_BIO' => 'Décédé après Bio'
    ];

    // Préparer les données
    $headers = ['MATRICULE', 'NOMS', 'GRADE', 'UNITÉ', 'BÉNÉFICIAIRE', 'GARNISON', 'PROVINCE', 'CATÉGORIE', 'ZDEF'];
    $data = [];

    foreach ($militaires as $m) {
        $zdefValue = getZoneDefense($m['province'] ?? '');
        
        if (empty($selected) && $zdef && $zdefValue !== $zdef) continue;
        
        $categorieValue = $m['categorie'] ?? '';
        $categorieLabel = $categories_labels[$categorieValue] ?? $categorieValue;
        
        $data[] = [
            strtoupper($m['matricule'] ?? ''),
            strtoupper($m['noms'] ?? ''),
            strtoupper($m['grade'] ?? ''),
            strtoupper($m['unite'] ?? ''),
            strtoupper($m['beneficiaire'] ?? ''),
            strtoupper($m['garnison'] ?? ''),
            strtoupper($m['province'] ?? ''),
            $categorieLabel,
            $zdefValue ?: '-'
        ];
    }

    // Appliquer le tri personnalisé si nécessaire
    if (empty($selected) && $orderColumn == 3) {
        usort($data, function($a, $b) use ($grade_order, $orderDir) {
            $gradeA = $a[2] ?? '';
            $gradeB = $b[2] ?? '';
            $indexA = array_search($gradeA, $grade_order);
            $indexB = array_search($gradeB, $grade_order);
            
            if ($indexA === false && $indexB === false) return 0;
            if ($indexA === false) return 1;
            if ($indexB === false) return -1;
            
            if ($orderDir === 'asc') {
                return $indexA - $indexB;
            } else {
                return $indexB - $indexA;
            }
        });
    }

    $response = [
        'success' => true,
        'headers' => $headers,
        'data' => $data
    ];

    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'headers' => ['MATRICULE', 'NOMS', 'GRADE', 'UNITÉ', 'BÉNÉFICIAIRE', 'GARNISON', 'PROVINCE', 'CATÉGORIE', 'ZDEF'],
        'data' => []
    ];
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
exit;
?>

