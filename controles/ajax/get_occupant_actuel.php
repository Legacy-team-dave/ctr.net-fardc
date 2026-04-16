<?php
require_once '../../includes/functions.php';
require_login();

$id_bien = $_GET['bien'] ?? 0;
if (!$id_bien) {
    echo json_encode(['est_occupe' => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT o.nom, o.postnom, o.prenom, ob.date_debut
    FROM occupation_biens ob
    JOIN occupants o ON ob.id_occupant = o.id_occupant
    WHERE ob.id_bien = ? AND ob.est_actuel = 1
");
$stmt->execute([$id_bien]);
$occupant = $stmt->fetch();

if ($occupant) {
    echo json_encode([
        'est_occupe' => true,
        'occupant_nom' => $occupant['nom'] . ' ' . $occupant['postnom'] . ' ' . $occupant['prenom'],
        'date_debut' => date('d/m/Y', strtotime($occupant['date_debut']))
    ]);
} else {
    echo json_encode(['est_occupe' => false]);
}
?>