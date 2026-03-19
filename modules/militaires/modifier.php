<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG']);

// Vérification de l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect_with_flash('liste.php', 'danger', 'ID de militaire invalide.');
}

$id = (int)$_GET['id'];

// Récupération des données actuelles
$stmt = $pdo->prepare("SELECT * FROM militaires WHERE matricule = ?");
$stmt->execute([$id]);
$militaire = $stmt->fetch();

if (!$militaire) {
    redirect_with_flash('liste.php', 'danger', 'Militaire non trouvé.');
}

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation et mise à jour
    $data = [
        'noms' => trim($_POST['noms']),
        'grade' => trim($_POST['grade']),
        'dependance' => !empty($_POST['dependance']) ? trim($_POST['dependance']) : null,
        'unite' => !empty($_POST['unite']) ? trim($_POST['unite']) : null,
        'beneficiaire' => trim($_POST['beneficiaire']),
        'garnison' => !empty($_POST['garnison']) ? trim($_POST['garnison']) : null,
        'province' => trim($_POST['province']),
        'categorie' => trim($_POST['categorie']),
        'statut' => isset($_POST['statut']) ? (int)$_POST['statut'] : 1
    ];

    $sql = "UPDATE militaires SET 
            noms = ?, grade = ?, dependance = ?, unite = ?, 
            beneficiaire = ?, garnison = ?, province = ?, categorie = ?, statut = ? 
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $params = [
        $data['noms'],
        $data['grade'],
        $data['dependance'],
        $data['unite'],
        $data['beneficiaire'],
        $data['garnison'],
        $data['province'],
        $data['categorie'],
        $data['statut'],
        $id
    ];

    if ($stmt->execute($params)) {
        // --- AJOUT LOG ---
        audit_action('MODIFICATION', 'militaires', $id, 'Modification du militaire ' . $data['noms'] . ' - Matricule: ' . $data['matricule']);
        // --- FIN AJOUT LOG ---

        $_SESSION['success_message'] = 'Militaire modifié avec succès.';
        header('Location: liste.php');
        exit;
    } else {
        $_SESSION['error_message'] = "Erreur lors de la modification du militaire.";
        header('Location: liste.php');
        exit;
    }
}

// Récupération du message de succès depuis la session
$success_message = null;
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$page_titre = 'Modifier un militaire';
$breadcrumb = ['Militaires' => 'modifier.php', 'Modifier' => '#'];
include '../../includes/header.php';
?>

<!-- Inclusion des CSS et JS -->
<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="../../assets/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/sweetalert2.min.css">
<link rel="stylesheet" href="assets/css/fonts.css">

<style>
body {
    font-family: 'Barlow', sans-serif;
    background-color: #f5f5f5;
}

/* Style de la carte améliorée */
.modern-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 30px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.modern-card .card-header {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    padding: 12px 20px;
    border: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.modern-card .card-header h3 {
    color: white;
    margin: 0;
    font-weight: 600;
    font-size: 1.2rem;
}

.modern-card .card-header h3 i {
    margin-right: 6px;
}

.modern-card .card-body {
    padding: 20px;
    background-color: #ffffff;
}

/* Badge personnalisé */
.badge-modern {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 500;
    min-height: 28px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    backdrop-filter: blur(5px);
}

/* Grilles compactes */
.row-two-cols {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

/* Style des champs compact */
.form-group {
    margin-bottom: 0;
}

.form-group label {
    font-weight: 600;
    color: #2e7d32;
    margin-bottom: 4px;
    display: block;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.2px;
}

.form-group label i {
    color: #2e7d32;
    margin-right: 4px;
    width: 14px;
    font-size: 0.8rem;
}

.form-control {
    border-radius: 6px;
    border: 1px solid #e0e0e0;
    padding: 6px 10px;
    height: auto;
    min-height: 32px;
    line-height: 1.3;
    transition: all 0.3s;
    font-size: 0.85rem;
    width: 100%;
    background-color: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.form-control:focus {
    border-color: #2e7d32;
    box-shadow: 0 0 0 0.15rem rgba(46, 125, 50, 0.25);
    outline: none;
}

/* Style pour le select compact */
select.form-control {
    padding: 5px 10px;
    min-height: 32px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%232e7d32' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 12px;
    padding-right: 30px;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}

/* BOUTONS compacts */
.card-footer {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    padding: 15px 20px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.action-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
}

.btn-modern {
    border-radius: 6px;
    padding: 6px 18px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    min-height: 36px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 120px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    text-decoration: none;
    letter-spacing: 0.2px;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
}

.btn-primary-modern:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
    color: white;
}

.btn-secondary-modern {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
}

.btn-secondary-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3);
    color: white;
}

.btn-warning-modern {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: white;
}

.btn-warning-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
    color: white;
}

.btn-modern i {
    font-size: 0.9rem;
}

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* Message d'aide compact */
.small.text-muted {
    font-size: 0.7rem;
    margin-top: 3px;
    margin-bottom: 0;
    display: block;
    line-height: 1.3;
    color: #6c757d !important;
    font-style: italic;
}

/* Champs obligatoires */
.text-danger {
    color: #dc3545;
    font-size: 0.8rem;
    margin-left: 2px;
}

/* Animation de chargement */
.btn-loading {
    position: relative;
    pointer-events: none;
    opacity: 0.8;
}

.btn-loading i {
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Style pour l'alerte de confirmation SweetAlert compact */
.swal2-popup {
    font-family: 'Barlow', sans-serif;
    border-radius: 12px !important;
    padding: 1rem !important;
    font-size: 0.85rem !important;
}

.swal2-title {
    color: #2e7d32 !important;
    font-weight: 600 !important;
    font-size: 1.2rem !important;
    margin: 0.5rem 0 !important;
}

.swal2-confirm {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%) !important;
    border-radius: 6px !important;
    font-weight: 600 !important;
    padding: 8px 20px !important;
    font-size: 0.85rem !important;
}

.swal2-cancel {
    border-radius: 6px !important;
    font-weight: 600 !important;
    padding: 8px 20px !important;
    font-size: 0.85rem !important;
}

/* Media queries pour mobile */
@media (max-width: 768px) {
    .modern-card .card-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 10px 15px;
    }

    .modern-card .card-body {
        padding: 12px;
    }

    .row-two-cols {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 12px;
    }

    .action-buttons {
        flex-direction: column;
        width: 100%;
        gap: 8px;
    }

    .btn-modern {
        width: 100%;
        min-width: auto;
        padding: 8px 15px;
    }

    .card-footer {
        padding: 12px;
    }
}

/* Style pour les écrans larges */
@media (min-width: 1400px) {
    .modern-card {
        max-width: 1000px;
    }

    .row-two-cols {
        gap: 20px;
        margin-bottom: 18px;
    }
}

/* Style pour les tooltips */
[title] {
    cursor: help;
}

/* Style pour les icônes dans les labels */
.form-group label i {
    font-size: 0.85rem;
}

/* Amélioration de la visibilité des champs */
.container-fluid {
    padding-left: 15px;
    padding-right: 15px;
}

.row {
    margin-left: -5px;
    margin-right: -5px;
}

.col-12 {
    padding-left: 5px;
    padding-right: 5px;
}

/* Style pour les champs en lecture seule */
.form-control[readonly] {
    background-color: #f8f9fa;
    cursor: not-allowed;
}

/* Style pour les champs désactivés */
.form-control:disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
}

/* Style pour les champs obligatoires avec astérisque */
label .text-danger {
    font-weight: bold;
}

/* Badge d'information pour l'ID */
.id-badge {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 10px;
}

/* Style pour l'aperçu des informations */
.info-preview {
    background: #f8f9fa;
    border-left: 4px solid #2e7d32;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 0.85rem;
}

.info-preview p {
    margin: 5px 0;
    color: #495057;
}

.info-preview strong {
    color: #2e7d32;
}
</style>

<div class="container-fluid py-2">
    <!-- Messages de succès -->
    <?php if ($success_message): ?>
    <script>
    Swal.fire({
        icon: 'success',
        title: 'Succès',
        text: '<?= addslashes($success_message) ?>',
        timer: 1500,
        showConfirmButton: false,
        position: 'top-end',
        toast: true,
        background: '#28a745',
        color: '#ffffff',
        iconColor: '#ffffff',
        timerProgressBar: true,
    });
    </script>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card modern-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0">
                            <i class="fas fa-edit"></i> Modifier un militaire
                        </h3>
                        <span class="id-badge">
                            <i class="fas fa-hashtag"></i> ID: <?= $id ?>
                        </span>
                    </div>
                    <span class="badge-modern">
                        <i class="fas fa-pen"></i> Modification
                    </span>
                </div>

                <!-- Aperçu des informations actuelles -->
                <div class="info-preview">
                    <p><strong><i class="fas fa-id-card"></i> Matricule actuel :</strong>
                        <?= htmlspecialchars($militaire['matricule']) ?></p>
                    <p><strong><i class="fas fa-user"></i> Nom actuel :</strong>
                        <?= htmlspecialchars($militaire['noms']) ?></p>
                    <p><strong><i class="fas fa-star"></i> Grade actuel :</strong>
                        <?= htmlspecialchars($militaire['grade']) ?></p>
                </div>

                <form method="post" id="modifMilitaireForm" onsubmit="return validateForm(event)">
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                        <script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: '<?= addslashes($error) ?>',
                            confirmButtonColor: '#3085d6',
                            confirmButtonText: 'Compris'
                        });
                        </script>
                        <?php endif; ?>

                        <!-- LIGNE 1 : Matricule et Noms -->
                        <div class="row-two-cols">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-id-card"></i>
                                    Matricule <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="matricule" class="form-control" required maxlength="50"
                                    placeholder="Ex : IG-FARDC-12345"
                                    value="<?= htmlspecialchars($militaire['matricule']) ?>">
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-user"></i>
                                    Noms complets <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="noms" class="form-control" required maxlength="100"
                                    placeholder="Ex : Dave Akwas" value="<?= htmlspecialchars($militaire['noms']) ?>">
                            </div>
                        </div>

                        <!-- LIGNE 2 : Grade et Dépendance -->
                        <div class="row-two-cols">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-star"></i>
                                    Grade <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="grade" class="form-control" required maxlength="50"
                                    placeholder="Ex : Général de brigade"
                                    value="<?= htmlspecialchars($militaire['grade']) ?>">
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-sitemap"></i>
                                    Dépendance
                                </label>
                                <input type="text" name="dependance" class="form-control" maxlength="100"
                                    placeholder="Ex : État-major"
                                    value="<?= htmlspecialchars($militaire['dependance'] ?? '') ?>">
                                <small class="text-muted">Optionnel</small>
                            </div>
                        </div>

                        <!-- LIGNE 3 : Unité et Bénéficiaire -->
                        <div class="row-two-cols">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-flag"></i>
                                    Unité
                                </label>
                                <input type="text" name="unite" class="form-control" maxlength="100"
                                    placeholder="Ex : 1ère brigade"
                                    value="<?= htmlspecialchars($militaire['unite'] ?? '') ?>">
                                <small class="text-muted">Optionnel</small>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-users"></i>
                                    Bénéficiaire <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="beneficiaire" class="form-control" required maxlength="100"
                                    placeholder="Ex : Ready Mwenge"
                                    value="<?= htmlspecialchars($militaire['beneficiaire']) ?>">
                            </div>
                        </div>

                        <!-- LIGNE 4 : Garnison et Province -->
                        <div class="row-two-cols">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-map-pin"></i>
                                    Garnison
                                </label>
                                <input type="text" name="garnison" class="form-control" maxlength="100"
                                    placeholder="Ex : Kinshasa"
                                    value="<?= htmlspecialchars($militaire['garnison'] ?? '') ?>">
                                <small class="text-muted">Optionnel</small>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-map-marker-alt"></i>
                                    Province <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="province" class="form-control" required maxlength="100"
                                    placeholder="Ex : Kinshasa" value="<?= htmlspecialchars($militaire['province']) ?>">
                            </div>
                        </div>

                        <!-- LIGNE 5 : Catégorie et Statut -->
                        <div class="row-two-cols">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-layer-group"></i>
                                    Catégorie <span class="text-danger">*</span>
                                </label>
                                <select name="categorie" class="form-control" required>
                                    <option value="">Sélectionnez une catégorie</option>
                                    <option value="ACTIF" <?= $militaire['categorie'] == 'ACTIF' ? 'selected' : '' ?>>
                                        Actif</option>
                                    <option value="RETRAITES"
                                        <?= $militaire['categorie'] == 'RETRAITES' ? 'selected' : '' ?>>Retraité
                                    </option>
                                    <option value="INTEGRES"
                                        <?= $militaire['categorie'] == 'INTEGRES' ? 'selected' : '' ?>>Intégré</option>
                                    <option value="DCD_AV_BIO"
                                        <?= $militaire['categorie'] == 'DCD_AV_BIO' ? 'selected' : '' ?>>Décédé Avant
                                        Bio</option>
                                    <option value="DCD_AP_BIO"
                                        <?= $militaire['categorie'] == 'DCD_AP_BIO' ? 'selected' : '' ?>>Décédé Après
                                        Bio</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-toggle-on"></i>
                                    Statut
                                </label>
                                <select name="statut" class="form-control">
                                    <option value="1" <?= $militaire['statut'] == 1 ? 'selected' : '' ?>>Actif (1)
                                    </option>
                                    <option value="0" <?= $militaire['statut'] == 0 ? 'selected' : '' ?>>Inactif (0)
                                    </option>
                                </select>
                                <small class="text-muted">1 = Actif, 0 = Inactif</small>
                            </div>
                        </div>
                    </div>

                    <!-- Footer avec boutons -->
                    <div class="card-footer">
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary-modern btn-modern" id="submitBtn">
                                <i class="fas fa-save"></i> Mettre à jour
                            </button>
                            <a href="liste.php" class="btn btn-secondary-modern btn-modern" id="cancelBtn">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="button" class="btn btn-warning-modern btn-modern" onclick="resetToOriginal()">
                                <i class="fas fa-undo"></i> Réinitialiser
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Scripts locaux -->
<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/sweetalert2.all.min.js"></script>

<script>
// Données originales pour la réinitialisation
const originalData = {
    matricule: <?= json_encode($militaire['matricule']) ?>,
    noms: <?= json_encode($militaire['noms']) ?>,
    grade: <?= json_encode($militaire['grade']) ?>,
    dependance: <?= json_encode($militaire['dependance'] ?? '') ?>,
    unite: <?= json_encode($militaire['unite'] ?? '') ?>,
    beneficiaire: <?= json_encode($militaire['beneficiaire']) ?>,
    garnison: <?= json_encode($militaire['garnison'] ?? '') ?>,
    province: <?= json_encode($militaire['province']) ?>,
    categorie: <?= json_encode($militaire['categorie']) ?>,
    statut: <?= json_encode($militaire['statut']) ?>
};

// Validation du formulaire
function validateForm(event) {
    event.preventDefault();

    const matricule = document.querySelector('[name="matricule"]').value.trim();
    const noms = document.querySelector('[name="noms"]').value.trim();
    const grade = document.querySelector('[name="grade"]').value.trim();
    const beneficiaire = document.querySelector('[name="beneficiaire"]').value.trim();
    const province = document.querySelector('[name="province"]').value.trim();
    const categorie = document.querySelector('[name="categorie"]').value;

    // Validation des champs obligatoires
    if (!matricule || !noms || !grade || !beneficiaire || !province || !categorie) {
        Swal.fire({
            icon: 'warning',
            title: 'Formulaire incomplet',
            text: 'Veuillez remplir tous les champs obligatoires.',
            confirmButtonColor: '#2e7d32',
            confirmButtonText: 'Compris',
            background: '#fff',
            customClass: {
                confirmButton: 'btn-modern btn-primary-modern'
            }
        });
        return false;
    }

    // Vérifier si des modifications ont été apportées
    let hasChanges = false;
    const currentValues = {
        matricule: matricule,
        noms: noms,
        grade: grade,
        dependance: document.querySelector('[name="dependance"]').value.trim(),
        unite: document.querySelector('[name="unite"]').value.trim(),
        beneficiaire: beneficiaire,
        garnison: document.querySelector('[name="garnison"]').value.trim(),
        province: province,
        categorie: categorie,
        statut: document.querySelector('[name="statut"]').value
    };

    for (let key in originalData) {
        if (originalData[key] != currentValues[key]) {
            hasChanges = true;
            break;
        }
    }

    if (!hasChanges) {
        Swal.fire({
            icon: 'info',
            title: 'Aucune modification',
            text: 'Vous n\'avez apporté aucune modification.',
            confirmButtonColor: '#2e7d32',
            confirmButtonText: 'Compris',
            customClass: {
                confirmButton: 'btn-modern btn-primary-modern'
            }
        });
        return false;
    }

    // Confirmation avec SweetAlert2
    Swal.fire({
        title: 'Confirmer la modification',
        html: `
            <div style="text-align: left; padding: 8px; background: #f8f9fa; border-radius: 8px; margin-top: 8px;">
                <p style="margin: 4px 0; color: #2e7d32; font-weight: 600;">
                    <i class="fas fa-id-card" style="width: 18px;"></i> Matricule: <strong>${escapeHtml(matricule)}</strong>
                </p>
                <p style="margin: 4px 0; color: #2e7d32; font-weight: 600;">
                    <i class="fas fa-user" style="width: 18px;"></i> Nom: <strong>${escapeHtml(noms)}</strong>
                </p>
                <p style="margin: 4px 0; color: #2e7d32; font-weight: 600;">
                    <i class="fas fa-star" style="width: 18px;"></i> Grade: <strong>${escapeHtml(grade)}</strong>
                </p>
            </div>
            <p class="mt-2" style="color: #6c757d; font-size: 0.9rem;">Êtes-vous sûr de vouloir modifier ce militaire ?</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2e7d32',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, modifier',
        cancelButtonText: 'Annuler',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn-modern btn-primary-modern',
            cancelButton: 'btn-modern btn-secondary-modern'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Désactiver le bouton et soumettre
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';

            // Soumettre le formulaire
            document.getElementById('modifMilitaireForm').submit();
        }
    });

    return false;
}

// Fonction d'échappement HTML
function escapeHtml(text) {
    if (!text) return text;
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) {
        return map[m];
    });
}

// Réinitialisation aux valeurs originales
function resetToOriginal() {
    Swal.fire({
        title: 'Réinitialiser le formulaire',
        text: 'Voulez-vous vraiment revenir aux valeurs originales ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, réinitialiser',
        cancelButtonText: 'Annuler',
        customClass: {
            confirmButton: 'btn-modern btn-warning-modern',
            cancelButton: 'btn-modern btn-secondary-modern'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Restaurer toutes les valeurs originales
            document.querySelector('[name="matricule"]').value = originalData.matricule;
            document.querySelector('[name="noms"]').value = originalData.noms;
            document.querySelector('[name="grade"]').value = originalData.grade;
            document.querySelector('[name="dependance"]').value = originalData.dependance;
            document.querySelector('[name="unite"]').value = originalData.unite;
            document.querySelector('[name="beneficiaire"]').value = originalData.beneficiaire;
            document.querySelector('[name="garnison"]').value = originalData.garnison;
            document.querySelector('[name="province"]').value = originalData.province;
            document.querySelector('[name="categorie"]').value = originalData.categorie;
            document.querySelector('[name="statut"]').value = originalData.statut;

            Swal.fire({
                icon: 'success',
                title: 'Réinitialisé',
                text: 'Formulaire réinitialisé aux valeurs originales',
                timer: 1500,
                showConfirmButton: false,
                background: '#28a745',
                color: '#ffffff',
                iconColor: '#ffffff',
                position: 'top-end',
                toast: true,
                timerProgressBar: true,
                customClass: {
                    popup: 'colored-toast'
                }
            });
        }
    });
}

// Confirmation avant de quitter avec des modifications non sauvegardées
let formModified = false;
document.querySelectorAll('#modifMilitaireForm input, #modifMilitaireForm select').forEach(element => {
    element.addEventListener('change', () => formModified = true);
    element.addEventListener('keyup', () => formModified = true);
    element.addEventListener('input', () => formModified = true);
});

document.getElementById('cancelBtn').addEventListener('click', function(e) {
    if (formModified) {
        e.preventDefault();
        Swal.fire({
            title: 'Modifications non sauvegardées',
            text: 'Vous avez des modifications non enregistrées. Voulez-vous vraiment quitter ?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, quitter',
            cancelButtonText: 'Rester',
            customClass: {
                confirmButton: 'btn-modern',
                cancelButton: 'btn-modern btn-secondary-modern'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'liste.php';
            }
        });
    }
});

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Ajouter une classe pour l'animation de chargement
    const style = document.createElement('style');
    style.textContent = `
        .btn-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }
        .btn-loading i {
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .colored-toast.swal2-icon-success {
            background-color: #28a745 !important;
        }
    `;
    document.head.appendChild(style);

    // Masquer les messages de succès après 3 secondes
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-success');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 3000);
});

// Validation en temps réel du matricule (lettres majuscules, chiffres et tirets uniquement)
document.querySelector('[name="matricule"]').addEventListener('input', function(e) {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
});

// Validation en temps réel des noms (lettres et espaces uniquement)
document.querySelector('[name="noms"]').addEventListener('input', function(e) {
    this.value = this.value.toUpperCase().replace(/[^A-Z\s]/g, '');
});

// Validation en temps réel du grade (lettres, espaces et tirets)
document.querySelector('[name="grade"]').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^A-Za-z\s-]/g, '').toUpperCase();
});
</script>

<?php include '../../includes/footer.php'; ?>