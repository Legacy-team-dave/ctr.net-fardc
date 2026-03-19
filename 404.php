<?php
$page_titre = 'Page non trouvée';
include 'includes/header.php';
?>

<div class="error-page">
    <h2 class="headline text-warning">404</h2>
    <div class="error-content">
        <h3><i class="fas fa-exclamation-triangle text-warning"></i> Oops! Page non trouvée.</h3>
        <p>
            La page que vous recherchez est introuvable.
            Vous pouvez <a href="index.php">retourner au tableau de bord</a>.
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>