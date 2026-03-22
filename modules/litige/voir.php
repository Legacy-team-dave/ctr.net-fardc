<?php
// modules/litige/voir.php
require_once '../../includes/functions.php';
require_login();

$page_titre = 'Détails du litige';
include '../../includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_error'] = "ID de litige invalide.";
    header('Location: liste.php');
    exit;
}

// Récupération des données du litige
try {
    $sql = "SELECT * FROM litiges WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $litige = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$litige) {
        $_SESSION['flash_error'] = "Litige introuvable.";
        header('Location: liste.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = "Erreur lors de la récupération des données.";
    header('Location: liste.php');
    exit;
}

$zone = getZdefValue($litige['province'] ?? '');
$zdef_class = '';
if ($zone['code'] === '1ZDEF') $zdef_class = 'zdef-badge-1';
elseif ($zone['code'] === '2ZDEF') $zdef_class = 'zdef-badge-2';
elseif ($zone['code'] === '3ZDEF') $zdef_class = 'zdef-badge-3';
?>

<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
<style>
/* ===== STYLES AGRANDIS (comme ajouter.php) ===== */
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

/* Badge zone de défense */
.zdef-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 0.8rem;
    background: #2e7d32;
    color: white;
    text-transform: uppercase;
    margin-left: 10px;
}

.zdef-badge-1 {
    background: #0072B5;
}

.zdef-badge-2 {
    background: #FFD700;
    color: #000;
}

.zdef-badge-3 {
    background: #CE1126;
}

/* Grille de détails */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.detail-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px;
    border-left: none;
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
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.detail-label i {
    color: #2e7d32;
    font-size: 0.9rem;
    width: 18px;
}

.detail-value {
    font-size: 0.95rem;
    color: #333;
    font-weight: 500;
    word-break: break-word;
}

/* Valeur nulle */
.null-value {
    color: #6c757d;
    font-weight: 400;
    font-style: italic;
    text-transform: none;
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

.btn-edit {
    background: #ffc107;
    color: #333;
}

.btn-edit:hover {
    background: #e0a800;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
}

.btn-back {
    background: #6c757d;
    color: white;
}

.btn-back:hover {
    background: #545b62;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
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

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.85);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    visibility: hidden;
    opacity: 0;
    transition: all 0.3s;
}

.loading-overlay.show {
    visibility: visible;
    opacity: 1;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #2e7d32;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }

    100% {
        transform: rotate(360deg);
    }
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

<!-- Loading overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<div class="container">
    <h2><i class="fas fa-eye"></i> Détails du litige #<?= $litige['id'] ?></h2>

    <!-- SECTION 1 : INFORMATIONS MILITAIRES -->
    <div class="section-title">
        <i class="fas fa-shield-alt"></i> Informations militaires
        <?php if ($zdef_class): ?>
        <span class="zdef-badge <?= $zdef_class ?>"><?= $zone['code'] ?></span>
        <?php endif; ?>
    </div>
    <div class="detail-grid">
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-id-card"></i> Matricule</div>
            <div class="detail-value"><?= htmlspecialchars($litige['matricule'] ?? '') ?></div>
        </div>

        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-user"></i> Noms</div>
            <div class="detail-value"><?= htmlspecialchars($litige['noms'] ?? '') ?></div>
        </div>

        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-star"></i> Grade</div>
            <div class="detail-value"><?= htmlspecialchars($litige['grade'] ?? '') ?: 'Non renseigné' ?></div>
        </div>

        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-map-pin"></i> Garnison</div>
            <div class="detail-value"><?= htmlspecialchars($litige['garnison'] ?? '') ?: 'Non renseignée' ?></div>
        </div>

        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-map-marked-alt"></i> Province</div>
            <div class="detail-value"><?= htmlspecialchars($litige['province'] ?? '') ?: 'Non renseignée' ?></div>
        </div>
    </div>

    <!-- SECTION 2 : INFORMATIONS BÉNÉFICIAIRES -->
    <div class="section-title">
        <i class="fas fa-users"></i> Informations bénéficiaires
    </div>
    <div class="detail-grid">
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-user-tie"></i> Bénéficiaire</div>
            <div class="detail-value"><?= htmlspecialchars($litige['nom_beneficiaire'] ?? '') ?: 'Non renseigné' ?>
            </div>
        </div>

        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-link"></i> Lien parenté</div>
            <div class="detail-value"><?= htmlspecialchars($litige['lien_parente'] ?? '') ?: 'Non renseigné' ?></div>
        </div>
    </div>

    <!-- SECTION 3 : INFORMATIONS DE CONTRÔLE -->
    <div class="section-title">
        <i class="fas fa-clipboard-check"></i> Informations de contrôle
    </div>
    <div class="detail-grid">
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-clipboard-list"></i> Type contrôle</div>
            <div class="detail-value"><?= htmlspecialchars($litige['type_controle'] ?? '') ?: 'Non renseigné' ?></div>
        </div>

        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-calendar-check"></i> Date contrôle</div>
            <div class="detail-value"><?= date('d/m/Y', strtotime($litige['date_controle'])) ?></div>
        </div>

        <div class="detail-item full-width">
            <div class="detail-label"><i class="fas fa-comment"></i> Observations</div>
            <div class="detail-value" style="white-space: pre-wrap;">
                <?= nl2br(htmlspecialchars($litige['observations'] ?? '')) ?: 'Aucune observation' ?>
            </div>
        </div>
    </div>

    <!-- Barre de boutons -->
    <div class="button-bar">
        <a href="modifier.php?id=<?= $litige['id'] ?>" class="btn btn-edit">
            <i class="fas fa-edit"></i> Modifier
        </a>
        <a href="liste.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
        <a href="liste.php" class="btn btn-list">
            <i class="fas fa-list"></i> Liste
        </a>
    </div>
</div>

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

<?php if (isset($_SESSION['flash_success'])): ?>
showNotification('<?= addslashes($_SESSION['flash_success']) ?>', 'success');
<?php unset($_SESSION['flash_success']); ?>
<?php elseif (isset($_SESSION['flash_error'])): ?>
showNotification('<?= addslashes($_SESSION['flash_error']) ?>', 'error');
<?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>