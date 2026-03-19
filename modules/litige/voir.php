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

// Fonction de détermination de la zone de défense
function getZdefValue($province)
{
    if (empty($province)) return ['value' => 'N/A', 'code' => 'N/A'];

    $province = strtoupper(trim($province));

    $groupe_2zdef = ['HAUT-KATANGA', 'HAUT-LOMAMI', 'LUALABA', 'TANGANYIKA', 'KASAI', 'KASAI-CENTRAL', 'KASAI-ORIENTAL', 'SANKURU', 'LOMAMI'];
    if (in_array($province, $groupe_2zdef)) {
        return ['value' => '2ZDEF', 'code' => '2ZDEF'];
    }

    $groupe_1zdef = ['EQUATEUR', 'MONGALA', 'NORD-UBANGI', 'SUD-UBANGI', 'TSHUAPA', 'KWILU', 'KWANGO', 'MAI-NDOMBE', 'KONGO-CENTRAL', 'KINSHASA'];
    if (in_array($province, $groupe_1zdef)) {
        return ['value' => '1ZDEF', 'code' => '1ZDEF'];
    }

    $groupe_3zdef = ['HAUT-UELE', 'BAS-UELE', 'ITURI', 'TSHOPO', 'NORD-KIVU', 'SUD-KIVU', 'MANIEMA'];
    if (in_array($province, $groupe_3zdef)) {
        return ['value' => '3ZDEF', 'code' => '3ZDEF'];
    }

    return ['value' => 'AUTRE', 'code' => 'AUTRE'];
}

$zone = getZdefValue($litige['province'] ?? '');
$zdef_class = '';
if ($zone['code'] === '1ZDEF') $zdef_class = 'zdef-badge-1';
elseif ($zone['code'] === '2ZDEF') $zdef_class = 'zdef-badge-2';
elseif ($zone['code'] === '3ZDEF') $zdef_class = 'zdef-badge-3';
?>

<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
<style>
/* ===== STYLES COMPACTS REPRIS DE modules/controle/voir.php ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Barlow', sans-serif;
    background: #f0f2f5;
    padding: 8px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    padding: 12px;
}

h2 {
    color: #2e7d32;
    font-size: 1.2rem;
    margin-bottom: 10px;
    padding-bottom: 4px;
    border-bottom: 2px solid #ffc107;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Titres de sections */
.section-title {
    color: #2e7d32;
    font-size: 1rem;
    margin: 15px 0 8px 0;
    padding-bottom: 3px;
    border-bottom: 1px solid #ddd;
    display: flex;
    align-items: center;
    gap: 6px;
}

.section-title:first-of-type {
    margin-top: 0;
}

.section-title i {
    color: #2e7d32;
    font-size: 0.95rem;
    width: 18px;
}

/* Badge zone de défense */
.zdef-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 16px;
    font-weight: 700;
    font-size: 0.7rem;
    background: #2e7d32;
    color: white;
    text-transform: uppercase;
    margin-left: 8px;
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
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
    margin-bottom: 15px;
}

.detail-item {
    background: #f8f9fa;
    border-radius: 5px;
    padding: 6px 8px;
    border-left: none;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-label {
    font-weight: 600;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #6c757d;
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    gap: 3px;
}

.detail-label i {
    color: #2e7d32;
    font-size: 0.75rem;
    width: 14px;
}

.detail-value {
    font-size: 0.85rem;
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

/* Boutons compacts */
.button-bar {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    margin-top: 15px;
    padding-top: 8px;
    border-top: 1px solid #eee;
    flex-wrap: wrap;
}

.btn {
    padding: 4px 12px;
    border-radius: 18px;
    font-weight: 600;
    font-size: 0.75rem;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none;
    transition: all 0.2s;
    min-width: 80px;
    justify-content: center;
}

.btn-edit {
    background: #ffc107;
    color: #333;
}

.btn-edit:hover {
    background: #e0a800;
    transform: translateY(-2px);
    box-shadow: 0 3px 6px rgba(255, 193, 7, 0.3);
}

.btn-back {
    background: #6c757d;
    color: white;
}

.btn-back:hover {
    background: #545b62;
    transform: translateY(-2px);
    box-shadow: 0 3px 6px rgba(108, 117, 125, 0.3);
}

.btn-list {
    background: #2e7d32;
    color: white;
}

.btn-list:hover {
    background: #1b5e20;
    transform: translateY(-2px);
    box-shadow: 0 3px 6px rgba(46, 125, 50, 0.3);
}

/* Notification compacte */
.notification {
    position: fixed;
    top: 15px;
    right: 15px;
    background: white;
    border-radius: 6px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
    padding: 8px 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    transform: translateX(400px);
    animation: slideIn 0.3s ease forwards;
    z-index: 10000;
    min-width: 250px;
    max-width: 320px;
    font-size: 0.8rem;
}

.notification.success {
    border-left: 4px solid #2e7d32;
}

.notification.error {
    border-left: 4px solid #dc3545;
}

.notification i {
    font-size: 1rem;
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
    font-size: 0.9rem;
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

/* Loading overlay (inactif mais conservé) */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.8);
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
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #2e7d32;
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
    }
}
</style>

<!-- Loading overlay (inactif mais présent) -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<div class="container">
    <h2><i class="fas fa-eye"></i> Détails du litige #<?= $litige['id'] ?></h2>

    <!-- SECTION 1 : INFORMATIONS MILITAIRES (avec badge zone) -->
    <div class="section-title">
        <i class="fas fa-shield-alt"></i> Informations militaires
        <?php if ($zdef_class): ?>
        <span class="zdef-badge <?= $zdef_class ?>"><?= $zone['code'] ?></span>
        <?php endif; ?>
    </div>
    <div class="detail-grid">
        <!-- Matricule -->
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-id-card"></i> Matricule</div>
            <div class="detail-value"><?= htmlspecialchars($litige['matricule'] ?? '') ?></div>
        </div>

        <!-- Noms -->
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-user"></i> Noms</div>
            <div class="detail-value"><?= htmlspecialchars($litige['noms'] ?? '') ?></div>
        </div>

        <!-- Grade (nouveau) -->
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-star"></i> Grade</div>
            <div class="detail-value"><?= htmlspecialchars($litige['grade'] ?? '') ?: 'Non renseigné' ?></div>
        </div>

        <!-- Garnison -->
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-map-pin"></i> Garnison</div>
            <div class="detail-value"><?= htmlspecialchars($litige['garnison'] ?? '') ?: 'Non renseignée' ?></div>
        </div>

        <!-- Province -->
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
        <!-- Bénéficiaire -->
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-user-tie"></i> Bénéficiaire</div>
            <div class="detail-value"><?= htmlspecialchars($litige['nom_beneficiaire'] ?? '') ?: 'Non renseigné' ?>
            </div>
        </div>

        <!-- Lien parenté -->
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
        <!-- Type contrôle -->
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-clipboard-list"></i> Type contrôle</div>
            <div class="detail-value"><?= htmlspecialchars($litige['type_controle'] ?? '') ?: 'Non renseigné' ?></div>
        </div>

        <!-- Date contrôle -->
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-calendar-check"></i> Date contrôle</div>
            <div class="detail-value"><?= date('d/m/Y', strtotime($litige['date_controle'])) ?></div>
        </div>

        <!-- Observations (pleine largeur) -->
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
// Script minimal pour les notifications éventuelles
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

// Si un message flash est présent, on l'affiche
<?php if (isset($_SESSION['flash_success'])): ?>
showNotification('<?= addslashes($_SESSION['flash_success']) ?>', 'success');
<?php unset($_SESSION['flash_success']); ?>
<?php elseif (isset($_SESSION['flash_error'])): ?>
showNotification('<?= addslashes($_SESSION['flash_error']) ?>', 'error');
<?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>