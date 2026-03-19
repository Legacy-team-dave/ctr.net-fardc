<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG']);

header('Content-Type: application/json');

$search = $_POST['search'] ?? '';
$order_column = $_POST['order_column'] ?? 1;  // par défaut nom complet
$order_dir = $_POST['order_dir'] ?? 'asc';

// Mapper les colonnes (index du tableau côté client)
$columns = ['login', 'nom_complet', 'email', 'profil', 'actif', 'dernier_acces'];

$sql = "SELECT login, nom_complet, email, profil, actif, dernier_acces FROM utilisateurs";
$params = [];

// Recherche
if (!empty($search)) {
    $sql .= " WHERE login LIKE :search OR nom_complet LIKE :search OR email LIKE :search OR profil LIKE :search";
    $params[':search'] = "%$search%";
}

// Tri
if (isset($columns[$order_column])) {
    $sql .= " ORDER BY " . $columns[$order_column] . " " . ($order_dir === 'asc' ? 'ASC' : 'DESC');
} else {
    $sql .= " ORDER BY nom_complet ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Préparer les données pour l'export
$headers = ['Login', 'Nom complet', 'Email', 'Profil', 'Statut', 'Dernier accès'];
$data = [];

foreach ($rows as $row) {
    $statut = $row['actif'] ? 'Actif' : 'Inactif';
    $dernier_acces = $row['dernier_acces'] ? date('d/m/Y H:i', strtotime($row['dernier_acces'])) : 'Jamais connecté';
    $data[] = [
        $row['login'],
        $row['nom_complet'],
        $row['email'],
        $row['profil'],
        $statut,
        $dernier_acces
    ];
}

echo json_encode(['headers' => $headers, 'data' => $data]);