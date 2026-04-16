<?php
require_once '../../../includes/functions.php';

if (isset($_GET['province']) && is_numeric($_GET['province'])) {
    $province_id = $_GET['province'];
    $stmt = $pdo->prepare("
        SELECT v.id_ville, v.denomination 
        FROM villes v
        JOIN communes_territoires c ON v.id_commune_terr = c.id_commune_terr
        WHERE c.id_province = ?
        ORDER BY v.denomination
    ");
    $stmt->execute([$province_id]);
    $villes = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($villes);
}