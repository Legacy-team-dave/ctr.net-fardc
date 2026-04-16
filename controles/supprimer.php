<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_SIG', 'GESTIONNAIRE']);

$id_bien = $_GET['id'] ?? 0;
if (!$id_bien) {
    $_SESSION['error_message'] = 'ID de bien manquant.';
    header('Location: liste.php');
    exit;
}

// Vérifier existence
$stmt = $pdo->prepare("SELECT denomination FROM biens_immobiliers WHERE id_bien = ?");
$stmt->execute([$id_bien]);
$bien = $stmt->fetch();

if (!$bien) {
    $_SESSION['error_message'] = 'Bien introuvable.';
    header('Location: liste.php');
    exit;
}

// Supprimer (les clés étrangères en CASCADE supprimeront les sous-types et occupations)
$pdo->prepare("DELETE FROM biens_immobiliers WHERE id_bien = ?")->execute([$id_bien]);
log_action('SUPPRESSION', 'biens_immobiliers', $id_bien, 'Suppression du bien : ' . $bien['denomination']);

// ===================================================
// REDIRECTION AVEC MESSAGE DE SUCCÈS EN SESSION
// ===================================================
$_SESSION['success_message'] = 'Bien supprimé avec succès.';
header('Location: liste.php');
exit;
?>