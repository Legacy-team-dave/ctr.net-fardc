<?php
require_once '../../includes/functions.php';
require_login();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID de l'équipe manquant.";
    header('Location: liste.php');
    exit;
}

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT e.*
    FROM equipes e
    WHERE e.id = ?
");
$stmt->execute([$id]);
$equipe = $stmt->fetch();

if (!$equipe) {
    $_SESSION['error_message'] = "Équipe introuvable.";
    header('Location: liste.php');
    exit;
}

$page_titre = 'Détail du membre de l\'équipe';
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
<style>
    /* ===== STYLES AGRANDIS POUR L'AFFICHAGE DETAILLE ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Barlow', sans-serif;
        background: #f0f2f5;
        padding: 12px;
        font-size: 16px;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        padding: 20px;
    }

    h2 {
        color: #2e7d32;
        font-size: 1.6rem;
        margin-bottom: 16px;
        padding-bottom: 8px;
        border-bottom: 2px solid #ffc107;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Titres de sections */
    .section-title {
        color: #2e7d32;
        font-size: 1.2rem;
        margin: 20px 0 12px 0;
        padding-bottom: 6px;
        border-bottom: 1px solid #ddd;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .section-title:first-of-type {
        margin-top: 0;
    }

    .section-title i {
        color: #2e7d32;
        font-size: 1.1rem;
        width: 24px;
    }

    /* Grille de détails */
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .detail-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .detail-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .detail-item.full-width {
        grid-column: 1 / -1;
    }

    .detail-label {
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .detail-label i {
        color: #2e7d32;
        font-size: 1rem;
        width: 20px;
    }

    .detail-value {
        font-size: 1rem;
        color: #333;
        font-weight: 500;
        word-break: break-word;
        line-height: 1.4;
    }

    /* Valeur nulle */
    .null-value {
        color: #6c757d;
        font-weight: 400;
        font-style: italic;
    }

    /* Boutons */
    .button-bar {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 24px;
        padding-top: 16px;
        border-top: 1px solid #eee;
        flex-wrap: wrap;
    }

    .btn {
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s;
        min-width: 110px;
        justify-content: center;
    }

    .btn-list {
        background: #2e7d32;
        color: white;
    }

    .btn-list:hover {
        background: #1b5e20;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(46, 125, 50, 0.3);
    }

    /* Notification */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        padding: 12px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        transform: translateX(400px);
        animation: slideIn 0.3s ease forwards;
        z-index: 10000;
        min-width: 280px;
        max-width: 360px;
        font-size: 0.9rem;
    }

    .notification.success {
        border-left: 5px solid #2e7d32;
    }

    .notification.error {
        border-left: 5px solid #dc3545;
    }

    .notification i {
        font-size: 1.2rem;
    }

    .notification.success i {
        color: #2e7d32;
    }

    .notification.error i {
        color: #dc3545;
    }

    .notification .message {
        flex: 1;
        font-weight: 500;
        color: #333;
    }

    .notification .close-notif {
        cursor: pointer;
        color: #999;
        font-size: 1rem;
        transition: color 0.2s;
    }

    .notification .close-notif:hover {
        color: #333;
    }

    @keyframes slideIn {
        to {
            transform: translateX(0);
        }
    }

    @keyframes slideOut {
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }

    .notification.hide {
        animation: slideOut 0.3s ease forwards;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .container {
            padding: 16px;
        }

        .btn {
            padding: 6px 16px;
            min-width: 90px;
        }
    }
</style>

<div class="container">
    <h2>
        <i class="fas fa-users"></i> 
        Détail du membre de l'équipe #<?= htmlspecialchars($equipe['id']) ?>
    </h2>

    <!-- SECTION : INFORMATIONS DU MEMBRE -->
    <div class="section-title">
        <i class="fas fa-id-card"></i> Informations personnelles
    </div>
    <div class="detail-grid">
        <div class="detail-item">
            <div class="detail-label">
                <i class="fas fa-id-card"></i> Matricule
            </div>
            <div class="detail-value">
                <?= !empty($equipe['matricule']) ? htmlspecialchars($equipe['matricule']) : '<span class="null-value">Non renseigné</span>' ?>
            </div>
        </div>

        <div class="detail-item">
            <div class="detail-label">
                <i class="fas fa-user"></i> Noms complets
            </div>
            <div class="detail-value">
                <?= !empty($equipe['noms']) ? htmlspecialchars($equipe['noms']) : '<span class="null-value">Non renseigné</span>' ?>
            </div>
        </div>

        <div class="detail-item">
            <div class="detail-label">
                <i class="fas fa-user-tag"></i> Grade
            </div>
            <div class="detail-value">
                <?= !empty($equipe['grade']) ? htmlspecialchars($equipe['grade']) : '<span class="null-value">Non renseigné</span>' ?>
            </div>
        </div>

        <div class="detail-item">
            <div class="detail-label">
                <i class="fas fa-building"></i> Unités
            </div>
            <div class="detail-value">
                <?= !empty($equipe['unites']) ? htmlspecialchars($equipe['unites']) : '<span class="null-value">Non renseigné</span>' ?>
            </div>
        </div>

        <div class="detail-item">
            <div class="detail-label">
                <i class="fas fa-briefcase"></i> Rôle
            </div>
            <div class="detail-value">
                <?= !empty($equipe['role']) ? htmlspecialchars($equipe['role']) : '<span class="null-value">Non renseigné</span>' ?>
            </div>
        </div>
    </div>

    <!-- SECTION : INFORMATIONS SYSTÈME (si existantes) -->
    <?php if (isset($equipe['created_at']) || isset($equipe['updated_at'])): ?>
    <div class="section-title">
        <i class="fas fa-info-circle"></i> Informations système
    </div>
    <div class="detail-grid">
        <?php if (isset($equipe['created_at']) && !empty($equipe['created_at'])): ?>
        <div class="detail-item">
            <div class="detail-label">
                <i class="fas fa-calendar-plus"></i> Date de création
            </div>
            <div class="detail-value">
                <?= date('d/m/Y à H:i:s', strtotime($equipe['created_at'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($equipe['updated_at']) && !empty($equipe['updated_at'])): ?>
        <div class="detail-item">
            <div class="detail-label">
                <i class="fas fa-calendar-edit"></i> Dernière modification
            </div>
            <div class="detail-value">
                <?= date('d/m/Y à H:i:s', strtotime($equipe['updated_at'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Bouton retour -->
    <div class="button-bar">
        <a href="liste.php" class="btn btn-list">
            <i class="fas fa-list"></i> Retour à la liste
        </a>
    </div>
</div>

<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script>
    function showNotification(message, type = 'success', duration = 5000) {
        const notif = document.createElement('div');
        notif.className = `notification ${type}`;
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        notif.innerHTML = `
            <i class="fas ${icon}"></i>
            <span class="message">${message}</span>
            <i class="fas fa-times close-notif" onclick="this.parentElement.remove()"></i>
        `;
        document.body.appendChild(notif);
        setTimeout(() => {
            notif.classList.add('hide');
            setTimeout(() => notif.remove(), 300);
        }, duration);
    }

    <?php if (isset($_SESSION['success_message'])): ?>
        showNotification('<?= addslashes($_SESSION['success_message']) ?>', 'success');
        <?php unset($_SESSION['success_message']); ?>
    <?php elseif (isset($_SESSION['error_message'])): ?>
        showNotification('<?= addslashes($_SESSION['error_message']) ?>', 'error');
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
</script>

<?php
include '../../includes/footer.php';
?>