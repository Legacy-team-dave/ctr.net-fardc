<?php
// ============================================
// TRAITEMENT PHP (avant tout affichage)
// ============================================
require_once '../../includes/functions.php';
require_login();

// Seul ADMIN_IG peut supprimer un utilisateur
if ($_SESSION['profil'] !== 'ADMIN_IG') {
    redirect_with_flash('liste.php', 'danger', 'Accès non autorisé.');
}

$error = null;
$user = null;

// Récupération de l'ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    redirect_with_flash('liste.php', 'danger', 'ID utilisateur invalide.');
}

// Empêcher l'auto-suppression
if ($id == $_SESSION['user_id']) {
    redirect_with_flash('liste.php', 'danger', 'Vous ne pouvez pas supprimer votre propre compte.');
}

// Chargement des données
$stmt = $pdo->prepare("SELECT id, login, nom_complet, profil FROM utilisateurs WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect_with_flash('liste.php', 'danger', 'Utilisateur introuvable.');
}

// Génération du jeton CSRF
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Jeton de sécurité invalide.";
    } else {
        try {
            // Suppression définitive
            $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
            $stmt->execute([$id]);
            log_action('SUPPRESSION_UTILISATEUR', 'utilisateurs', $id, 'Suppression utilisateur ' . $user['login']);

            redirect_with_flash('liste.php', 'success', 'Utilisateur supprimé avec succès.');
        } catch (PDOException $e) {
            $error = "Erreur lors de la suppression. L'utilisateur est peut-être lié à d'autres enregistrements.";
        }
    }
}

$page_titre = 'Supprimer un utilisateur';
include '../../includes/header.php';
?>

<!-- Ressources -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="/ctr.net-fardc/assets/css/fonts.css">
<script src="../../assets/js/sweetalert2.all.min.js"></script>

<style>
/* Même CSS que précédemment, mais on peut l'alléger */
:root {
    --primary: #2e7d32;
    --primary-dark: #1b5e20;
    --danger: #dc3545;
    --gray: #6c757d;
    --light: #f8f9fa;
}

body {
    font-family: 'Barlow', sans-serif;
    background: #f4f6f9;
}

.container-fluid {
    padding: 15px;
}

.register-wrapper {
    max-width: 600px;
    margin: 0 auto;
    animation: fadeInUp 0.4s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(15px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card-modern {
    border: none;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
    overflow: hidden;
    background: white;
}

.card-body {
    padding: 30px 25px;
}

.form-header {
    text-align: center;
    margin-bottom: 20px;
}

.form-header .title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--danger);
    margin-bottom: 5px;
}

.form-header .subtitle {
    font-size: 0.95rem;
    color: var(--gray);
}

.alert-modern {
    border-radius: 6px;
    border: none;
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-modern.error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid var(--danger);
}

.alert-modern.warning {
    background: #fff3cd;
    color: #856404;
    border-left: 4px solid #ffc107;
}

.user-details {
    background: var(--light);
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #e9ecef;
}

.user-details p {
    margin: 8px 0;
    font-size: 1rem;
}

.user-details strong {
    color: var(--primary-dark);
    min-width: 100px;
    display: inline-block;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 25px;
}

.btn-modern {
    border-radius: 6px;
    padding: 10px 20px;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-width: 140px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    text-decoration: none;
    color: white;
}

.btn-danger-modern {
    background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
}

.btn-danger-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(220, 53, 69, 0.3);
}

.btn-secondary-modern {
    background: linear-gradient(135deg, var(--gray) 0%, #5a6268 100%);
}

.btn-secondary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(108, 117, 125, 0.3);
}

@media (max-width: 576px) {
    .action-buttons {
        flex-direction: column;
    }

    .btn-modern {
        width: 100%;
    }
}
</style>

<div class="container-fluid py-4">
    <div class="register-wrapper">
        <div class="card-modern">
            <div class="card-body">
                <div class="form-header">
                    <div class="title"><i class="fas fa-exclamation-triangle"></i> Confirmer la suppression</div>
                    <div class="subtitle">Cette action est irréversible</div>
                </div>

                <?php if ($error): ?>
                <div class="alert-modern error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '<?= addslashes($error) ?>',
                    confirmButtonColor: '#2e7d32'
                });
                </script>
                <?php endif; ?>

                <div class="alert-modern warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Vous êtes sur le point de supprimer définitivement l'utilisateur suivant :
                </div>

                <div class="user-details">
                    <p><strong>Login :</strong> <?= htmlspecialchars($user['login']) ?></p>
                    <p><strong>Nom complet :</strong> <?= htmlspecialchars($user['nom_complet']) ?></p>
                    <p><strong>Profil :</strong> <?= htmlspecialchars($user['profil']) ?></p>
                </div>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="confirm" value="yes">

                    <div class="action-buttons">
                        <button type="submit" class="btn-modern btn-danger-modern" id="deleteBtn">
                            <i class="fas fa-trash-alt"></i> Oui, supprimer
                        </button>
                        <a href="liste.php" class="btn-modern btn-secondary-modern" id="cancelBtn">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Confirmation avant soumission
document.getElementById('deleteBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Supprimer définitivement ?',
        html: `Êtes-vous sûr de vouloir supprimer <strong><?= addslashes($user['login']) ?></strong> ?<br><br>Cette action est irréversible.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            const btn = document.getElementById('deleteBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression...';
            document.querySelector('form').submit();
        }
    });
});

// Alerte si tentative de quitter sans confirmer (pour le lien Annuler, c'est direct)
</script>

<?php include '../../includes/footer.php'; ?>