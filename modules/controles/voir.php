<?php
require_once '../../includes/functions.php';
require_login();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID du contrôle manquant.";
    header('Location: liste.php');
    exit;
}

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT c.*, 
           m.noms as nom_militaire, 
           m.statut as militaire_statut, 
           m.categorie as militaire_categorie, 
           m.province as militaire_province,
           m.garnison as militaire_garnison
    FROM controles c
    LEFT JOIN militaires m ON c.matricule = m.matricule
    WHERE c.id = ?
");
$stmt->execute([$id]);
$controle = $stmt->fetch();

if (!$controle) {
    $_SESSION['error_message'] = "Contrôle introuvable.";
    header('Location: liste.php');
    exit;
}

// Liste des liens de parenté à remplacer par "Tuteur"
$liens_tuteur = ['Frère', 'Sœur', 'Père', 'Mère'];

function formatLienParente($lien)
{
    global $liens_tuteur;
    if (empty($lien)) return null;
    if (in_array($lien, $liens_tuteur)) {
        return 'Tuteur';
    }
    return $lien;
}

function getZoneDefense($province)
{
    if (empty($province)) return '';

    $province = strtoupper(trim($province));
    $province = str_replace(
        ['É', 'È', 'Ê', 'Ë', 'Â', 'Ä', 'Î', 'Ï', 'Ô', 'Ö', 'Û', 'Ü', 'Ç'],
        ['E', 'E', 'E', 'E', 'A', 'A', 'I', 'I', 'O', 'O', 'U', 'U', 'C'],
        $province
    );

    $zone1 = [
        'KWILU',
        'KWANGO',
        'MAI-NDOMBE',
        'MAI NDOMBE',
        'MAINDOMBE',
        'KONGO-CENTRAL',
        'KONGO CENTRAL',
        'KONGOCENTRAL',
        'KINSHASA',
        'EQUATEUR',
        'ÉQUATEUR',
        'MONGALA',
        'NORD-UBANGI',
        'NORD UBANGI',
        'NORDUBANGI',
        'SUD-UBANGI',
        'SUD UBANGI',
        'SUDUBANGI',
        'TSHUAPA'
    ];

    $zone2 = [
        'HAUT-KATANGA',
        'HAUT KATANGA',
        'HAUTKATANGA',
        'HAUT-LOMAMI',
        'HAUT LOMAMI',
        'HAUTLOMAMI',
        'LUALABA',
        'TANGANYIKA',
        'KASAI',
        'KASAÏ',
        'KASAI-CENTRAL',
        'KASAI CENTRAL',
        'KASAICENTRAL',
        'KASAÏ-CENTRAL',
        'KASAI-ORIENTAL',
        'KASAI ORIENTAL',
        'KASAIORIENTAL',
        'KASAÏ-ORIENTAL',
        'SANKURU',
        'LOMAMI'
    ];

    $zone3 = [
        'HAUT-UELE',
        'HAUT UELE',
        'HAUTUELE',
        'BAS-UELE',
        'BAS UELE',
        'BASUELE',
        'ITURI',
        'TSHOPO',
        'NORD-KIVU',
        'NORD KIVU',
        'NORDKIVU',
        'SUD-KIVU',
        'SUD KIVU',
        'SUDKIVU',
        'MANIEMA'
    ];

    if (in_array($province, $zone1)) return '1ZDef';
    if (in_array($province, $zone2)) return '2ZDef';
    if (in_array($province, $zone3)) return '3ZDef';
    return '';
}

$lien_parente_formate = !empty($controle['lien_parente']) ? formatLienParente($controle['lien_parente']) : null;
$zdef = getZoneDefense($controle['militaire_province'] ?? '');

$zdef_class = '';
if ($zdef === '1ZDef') $zdef_class = 'zdef-badge-1';
elseif ($zdef === '2ZDef') $zdef_class = 'zdef-badge-2';
elseif ($zdef === '3ZDef') $zdef_class = 'zdef-badge-3';

$categories_list = [
    'ACTIF' => 'Actif',
    'RETRAITES' => 'Retraité',
    'INTEGRES' => 'Intégré',
    'DCD_AV_BIO' => 'Décédé avant Bio',
    'DCD_AP_BIO' => 'Décédé après Bio'
];
$categorie = $controle['militaire_categorie'] ?? '';
$categorie_libelle = $categories_list[$categorie] ?? $categorie;

$nom_militaire = !empty($controle['nom_militaire']) ? strtoupper($controle['nom_militaire']) : '';
$statut_militaire = isset($controle['militaire_statut']) ? ($controle['militaire_statut'] ? 'ACTIF' : 'INACTIF') : '';
$categorie_affichage = !empty($categorie_libelle) ? strtoupper($categorie_libelle) : '';
$province_militaire = !empty($controle['militaire_province']) ? strtoupper($controle['militaire_province']) : '';
$garnison_militaire = !empty($controle['militaire_garnison']) ? strtoupper($controle['militaire_garnison']) : '';
$nom_beneficiaire = !empty($controle['nom_beneficiaire']) ? strtoupper($controle['nom_beneficiaire']) : '';
$lien_parente_affichage = !empty($lien_parente_formate) ? strtoupper($lien_parente_formate) : '';
// Nouveau champ : bénéficiaire alternatif (affiché avant le lien de parenté)
$new_beneficiaire = !empty($controle['new_beneficiaire']) ? mb_strtoupper($controle['new_beneficiaire'], 'UTF-8') : '';

$mention_affichage = !empty($controle['mention']) ? mb_strtoupper($controle['mention'], 'UTF-8') : '';

$mention_class = '';
if ($mention_affichage === 'DÉFAVORABLE' || $mention_affichage === 'DEFAVORABLE') {
    $mention_class = 'defavorable';
} elseif ($mention_affichage === 'PRÉSENT' || $mention_affichage === 'PRESENT') {
    $mention_class = 'present';
} elseif ($mention_affichage === 'FAVORABLE') {
    $mention_class = 'favorable';
}

$statut_class = '';
if ($statut_militaire === 'ACTIF') $statut_class = 'statut-actif';
elseif ($statut_militaire === 'INACTIF') $statut_class = 'statut-inactif';

$categorie_class = '';
switch ($categorie) {
    case 'ACTIF':
        $categorie_class = 'categorie-actif';
        break;
    case 'RETRAITES':
        $categorie_class = 'categorie-retraite';
        break;
    case 'INTEGRES':
        $categorie_class = 'categorie-integre';
        break;
    case 'DCD_AV_BIO':
        $categorie_class = 'categorie-dcd-av';
        break;
    case 'DCD_AP_BIO':
        $categorie_class = 'categorie-dcd-ap';
        break;
}

function getCategorieIcon($categorie)
{
    switch ($categorie) {
        case 'ACTIF':
            return 'fa-user-check';
        case 'RETRAITES':
            return 'fa-user-clock';
        case 'INTEGRES':
            return 'fa-user-plus';
        case 'DCD_AP_BIO':
            return 'fa-skull';
        case 'DCD_AV_BIO':
            return 'fa-skull-crossbones';
        default:
            return 'fa-tag';
    }
}
$categorie_icon = getCategorieIcon($categorie);

$page_titre = 'Détail du contrôle';
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
<style>
    /* ===== STYLES AGRANDIS (comme modules/litige/voir.php) ===== */
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

    /* Grille de détails par défaut (auto-fit) */
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    /* Grilles spécifiques : 3 colonnes */
    .military-grid,
    .control-grid {
        grid-template-columns: repeat(3, 1fr);
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

    /* Badges compacts (mais agrandis) */
    .zdef-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 0.8rem;
        background: #2e7d32;
        color: white;
        text-transform: uppercase;
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

    .statut-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
    }

    .statut-actif {
        background: #0d6efd;
        color: white;
    }

    .statut-inactif {
        background: #6c757d;
        color: white;
    }

    .categorie-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        color: white;
    }

    .categorie-actif {
        background: #28a745;
    }

    .categorie-retraite {
        background: linear-gradient(135deg, #0d6efd, #0a58ca);
    }

    .categorie-integre {
        background: linear-gradient(135deg, #dc3545, #b02a37);
    }

    .categorie-dcd-av {
        background: #6c757d;
    }

    .categorie-dcd-ap {
        background: linear-gradient(135deg, #6f42c1, #6610f2);
    }

    .mention-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        min-width: 110px;
        text-align: center;
    }

    .mention-badge.present {
        background: #28a745;
        color: white;
    }

    .mention-badge.favorable {
        background: #ffc107;
        color: #212529;
    }

    .mention-badge.defavorable {
        background: #dc3545;
        color: white;
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

        .detail-grid,
        .military-grid,
        .control-grid {
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
    <h2><i class="fas fa-clipboard-list"></i> Détail du contrôle #<?= htmlspecialchars($controle['id']) ?></h2>

    <!-- SECTION 1 : INFORMATIONS MILITAIRES - Grille 3 colonnes -->
    <div class="section-title">
        <i class="fas fa-shield-alt"></i> Informations militaires
        <?php if ($zdef): ?>
            <span class="zdef-badge <?= $zdef_class ?> ms-2"><?= htmlspecialchars($zdef) ?></span>
        <?php endif; ?>
    </div>
    <div class="detail-grid military-grid">
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-id-card"></i> Matricule</div>
            <div class="detail-value"><?= htmlspecialchars($controle['matricule']) ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-user"></i> Noms</div>
            <div class="detail-value">
                <?= !empty($nom_militaire) ? htmlspecialchars($nom_militaire) : '<span class="null-value">NULL</span>' ?>
            </div>
        </div>
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-user-check"></i> Statut</div>
            <div class="detail-value">
                <?php if (!empty($statut_militaire)): ?>
                    <span class="statut-badge <?= $statut_class ?>">
                        <i class="fas <?= ($statut_militaire === 'ACTIF') ? 'fa-user-check' : 'fa-user-slash' ?> me-1"></i>
                        <?= htmlspecialchars($statut_militaire) ?>
                    </span>
                <?php else: ?>
                    <span class="null-value">NULL</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-tag"></i> Catégorie</div>
            <div class="detail-value">
                <?php if (!empty($categorie_affichage)): ?>
                    <span class="categorie-badge <?= $categorie_class ?>">
                        <i class="fas <?= $categorie_icon ?> me-1"></i>
                        <?= htmlspecialchars($categorie_affichage) ?>
                    </span>
                <?php else: ?>
                    <span class="null-value">NULL</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-map-pin"></i> Garnison</div>
            <div class="detail-value">
                <?= !empty($garnison_militaire) ? htmlspecialchars($garnison_militaire) : '<span class="null-value">NULL</span>' ?>
            </div>
        </div>
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-map-marked-alt"></i> Province</div>
            <div class="detail-value">
                <?= !empty($province_militaire) ? htmlspecialchars($province_militaire) : '<span class="null-value">NULL</span>' ?>
            </div>
        </div>
    </div>

    <!-- SECTION 2 : INFORMATIONS BÉNÉFICIAIRES -->
    <?php if (!empty($nom_beneficiaire) || !empty($lien_parente_affichage) || !empty($new_beneficiaire)): ?>
        <div class="section-title">
            <i class="fas fa-users"></i> Informations bénéficiaires
        </div>
        <div class="detail-grid">
            <?php if (!empty($nom_beneficiaire)): ?>
                <div class="detail-item">
                    <div class="detail-label"><i class="fas fa-user-tie"></i> Bénéficiaire</div>
                    <div class="detail-value"><?= htmlspecialchars($nom_beneficiaire) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($new_beneficiaire)): ?>
                <div class="detail-item">
                    <div class="detail-label"><i class="fas fa-user-plus"></i> Nouveau bénéficiaire</div>
                    <div class="detail-value"><?= htmlspecialchars($new_beneficiaire) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($lien_parente_affichage)): ?>
                <div class="detail-item">
                    <div class="detail-label"><i class="fas fa-link"></i> Lien parenté</div>
                    <div class="detail-value"><?= htmlspecialchars($lien_parente_affichage) ?></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- SECTION 3 : INFORMATIONS DE CONTRÔLE - Grille 3 colonnes -->
    <div class="section-title">
        <i class="fas fa-clipboard-check"></i> Informations de contrôle
    </div>
    <div class="detail-grid control-grid">
        <!-- Carte 1 : Date contrôle (avec modifié le si existant) -->
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-calendar-check"></i> Date contrôle</div>
            <div class="detail-value">
                <?= !empty($controle['date_controle']) ? date('d/m/Y', strtotime($controle['date_controle'])) : '<span class="null-value">NULL</span>' ?>
            </div>
            <?php if (!empty($controle['modifie_le'])): ?>
                <div style="margin-top: 5px; font-size: 0.75rem; color: #6c757d;">
                    <i class="fas fa-pen me-1"></i> Modifié le <?= date('d/m/Y H:i', strtotime($controle['modifie_le'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Carte 2 : Mention -->
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-star"></i> Mention</div>
            <div class="detail-value">
                <?php if (!empty($mention_affichage)): ?>
                    <span class="mention-badge <?= $mention_class ?>">
                        <i class="fas 
                            <?php
                            if ($mention_class === 'present') echo 'fa-check-circle';
                            elseif ($mention_class === 'favorable') echo 'fa-thumbs-up';
                            elseif ($mention_class === 'defavorable') echo 'fa-thumbs-down';
                            else echo 'fa-tag';
                            ?>
                        me-1"></i>
                        <?= htmlspecialchars($mention_affichage) ?>
                    </span>
                <?php else: ?>
                    <span class="null-value">NULL</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Carte 3 : Observations -->
        <div class="detail-item">
            <div class="detail-label"><i class="fas fa-comment"></i> Observations</div>
            <div class="detail-value" style="white-space: pre-wrap;">
                <?= !empty($controle['observations']) ? nl2br(htmlspecialchars($controle['observations'])) : '<span class="null-value">Aucune observation</span>' ?>
            </div>
        </div>
    </div>

    <!-- Boutons -->
    <div class="button-bar">
        <a href="liste.php" class="btn btn-list"><i class="fas fa-list"></i> Liste</a>
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