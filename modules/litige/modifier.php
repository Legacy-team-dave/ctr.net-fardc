<?php
// modules/litige/modifier.php
require_once '../../includes/functions.php';
require_login();

// ============================
// PARTIE PHP : GESTION EN SESSION
// ============================
$toast_message = $_SESSION['toast_message'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast_message'], $_SESSION['toast_type']);

$page_titre = 'Modifier le litige';

// Récupération de l'ID depuis l'URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: liste.php?error=' . urlencode('ID invalide'));
    exit;
}

// Récupération des données actuelles
$stmt = $pdo->prepare("SELECT * FROM litiges WHERE id = ?");
$stmt->execute([$id]);
$litige = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$litige) {
    header('Location: liste.php?error=' . urlencode('Litige introuvable'));
    exit;
}

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
<style>
/* ===== STYLES IDENTIQUES À AJOUTER.PHP ===== */
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

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
    margin-bottom: 15px;
}

.form-grid {
    grid-template-columns: repeat(3, 1fr);
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
    width: 18px;
}

.detail-label .required {
    color: #dc3545;
}

.detail-item input,
.detail-item textarea,
.detail-item select {
    width: 100%;
    padding: 4px 6px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.8rem;
    transition: all 0.2s;
    background: white;
}

.detail-item input:focus,
.detail-item textarea:focus,
.detail-item select:focus {
    border-color: #2e7d32;
    outline: none;
    box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1);
}

.detail-item textarea {
    resize: vertical;
    min-height: 50px;
}

.auto-date-indicator {
    font-size: 0.7rem;
    color: #2e7d32;
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 3px;
}

.button-bar {
    display: flex;
    gap: 6px;
    justify-content: center;
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

.btn-save {
    background: #2e7d32;
    color: white;
}

.btn-save:hover:not(:disabled) {
    background: #1b5e20;
    transform: translateY(-2px);
    box-shadow: 0 3px 6px rgba(46, 125, 50, 0.3);
}

.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-cancel {
    background: #6c757d;
    color: white;
}

.btn-cancel:hover {
    background: #545b62;
    transform: translateY(-2px);
    box-shadow: 0 3px 6px rgba(108, 117, 125, 0.3);
}

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

/* ===== STYLES POUR LES TOASTS ===== */
:root {
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
}

.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast-message {
    background: linear-gradient(135deg, var(--success) 0%, #218838 100%);
    color: white;
    padding: 15px 25px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(40, 167, 69, 0.3);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    animation: slideIn 0.3s ease-out, fadeOut 0.5s ease-out 2.5s forwards;
    font-weight: 500;
    min-width: 300px;
}

.toast-message i {
    font-size: 1.2rem;
}

.toast-message.error {
    background: linear-gradient(135deg, var(--danger) 0%, #c82333 100%);
    box-shadow: 0 5px 20px rgba(220, 53, 69, 0.3);
}

.toast-message.warning {
    background: linear-gradient(135deg, var(--warning) 0%, #e0a800 100%);
    color: #212529;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }

    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
        transform: translateX(0);
    }

    to {
        opacity: 0;
        transform: translateX(100%);
    }
}

@media (max-width: 768px) {
    .toast-message {
        min-width: 250px;
        padding: 12px 20px;
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- ============================ -->
<!-- PARTIE HTML : CONTENEUR TOAST -->
<!-- ============================ -->
<div class="toast-container" id="toastContainer"></div>

<div class="container">
    <h2><i class="fas fa-edit"></i> Modifier le litige n°<?= htmlspecialchars($id) ?></h2>

    <form id="form">
        <!-- Champ caché pour l'ID -->
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="section-title">
            <i class="fas fa-info-circle"></i> Informations générales
        </div>

        <div class="detail-grid form-grid">
            <?php
            $fields = [
                'matricule' => [
                    'label' => 'Matricule',
                    'required' => true,
                    'placeholder' => 'Ex: 12345',
                    'icon' => 'id-card',
                    'value' => htmlspecialchars($litige['matricule'] ?? '')
                ],
                'noms' => [
                    'label' => 'Noms',
                    'required' => true,
                    'placeholder' => 'Nom complet',
                    'icon' => 'user',
                    'value' => htmlspecialchars($litige['noms'] ?? '')
                ],
                'grade' => [
                    'label' => 'Grade',
                    'required' => false,
                    'placeholder' => 'Grade militaire',
                    'icon' => 'star',
                    'value' => htmlspecialchars($litige['grade'] ?? '')
                ],
                'type_controle' => [
                    'label' => 'Type contrôle',
                    'required' => true,
                    'placeholder' => '',
                    'icon' => 'clipboard',
                    'value' => htmlspecialchars($litige['type_controle'] ?? '')
                ],
                'nom_beneficiaire' => [
                    'label' => 'Nom bénéficiaire',
                    'required' => false,
                    'placeholder' => 'Nom du bénéficiaire',
                    'icon' => 'user-tie',
                    'value' => htmlspecialchars($litige['nom_beneficiaire'] ?? '')
                ],
                'lien_parente' => [
                    'label' => 'Lien parenté',
                    'required' => false,
                    'placeholder' => '',
                    'icon' => 'link',
                    'value' => htmlspecialchars($litige['lien_parente'] ?? '')
                ],
                'garnison' => [
                    'label' => 'Garnison',
                    'required' => false,
                    'placeholder' => 'Garnison',
                    'icon' => 'map-pin',
                    'value' => htmlspecialchars($litige['garnison'] ?? '')
                ],
                'province' => [
                    'label' => 'Province',
                    'required' => false,
                    'placeholder' => 'Province',
                    'icon' => 'map-marked-alt',
                    'value' => htmlspecialchars($litige['province'] ?? '')
                ]
            ];

            foreach ($fields as $name => $f):
                $icon = 'fas fa-' . $f['icon'];
            ?>
            <div class="detail-item">
                <div class="detail-label">
                    <i class="<?= $icon ?>"></i>
                    <?= $f['label'] ?> <?= $f['required'] ? '<span class="required">*</span>' : '' ?>
                </div>
                <?php if ($name === 'type_controle'): ?>
                <select name="type_controle" id="type_controle" required>
                    <option value="">Sélectionnez</option>
                    <option value="Militaire" <?= ($litige['type_controle'] ?? '') == 'Militaire' ? 'selected' : '' ?>>
                        Militaire</option>
                    <option value="Bénéficiaire"
                        <?= ($litige['type_controle'] ?? '') == 'Bénéficiaire' ? 'selected' : '' ?>>Bénéficiaire
                    </option>
                </select>
                <?php elseif ($name === 'lien_parente'): ?>
                <select name="lien_parente" id="lien_parente">
                    <option value="">Sélectionnez</option>
                    <option value="Veuve" <?= ($litige['lien_parente'] ?? '') == 'Veuve' ? 'selected' : '' ?>>Veuve
                    </option>
                    <option value="Veuf" <?= ($litige['lien_parente'] ?? '') == 'Veuf' ? 'selected' : '' ?>>Veuf
                    </option>
                    <option value="Orphelin" <?= ($litige['lien_parente'] ?? '') == 'Orphelin' ? 'selected' : '' ?>>
                        Orphelin</option>
                    <option value="Tuteur" <?= ($litige['lien_parente'] ?? '') == 'Tuteur' ? 'selected' : '' ?>>Tuteur
                    </option>
                </select>
                <?php else: ?>
                <input type="text" name="<?= $name ?>" id="<?= $name ?>" <?= $f['required'] ? 'required' : '' ?>
                    value="<?= $f['value'] ?>" placeholder="<?= $f['placeholder'] ?>" autocomplete="off"
                    <?= ($name === 'nom_beneficiaire' && ($litige['type_controle'] ?? '') == 'Militaire') ? 'disabled' : '' ?>>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="detail-item full-width">
                <div class="detail-label"><i class="fas fa-comment"></i> Observations</div>
                <textarea name="observations" id="observations" rows="2"
                    placeholder="Détails..."><?= htmlspecialchars($litige['observations'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="button-bar">
            <button type="submit" class="btn btn-save" id="btnSubmit">
                <i class="fas fa-save"></i> Mettre à jour
            </button>
            <a href="liste.php" class="btn btn-cancel">
                <i class="fas fa-times"></i> Annuler
            </a>
        </div>
    </form>
</div>

<script>
// Fonction d'affichage des toasts
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) return;

    const toast = document.createElement('div');
    toast.className = `toast-message ${type}`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;

    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Fonction pour afficher les messages stockés en session
function showMessages() {
    <?php if ($toast_message): ?>
    showToast('<?= addslashes($toast_message) ?>', '<?= $toast_type ?>');
    <?php endif; ?>
}

// Gestion dynamique des champs selon le type de contrôle
function toggleBeneficiaireFields() {
    const typeControle = document.getElementById('type_controle').value;
    const nomBeneficiaire = document.getElementById('nom_beneficiaire');
    const lienParente = document.getElementById('lien_parente');

    if (typeControle === 'Militaire') {
        nomBeneficiaire.disabled = true;
        nomBeneficiaire.value = '';
        lienParente.disabled = true;
        lienParente.value = '';
    } else if (typeControle === 'Bénéficiaire') {
        nomBeneficiaire.disabled = false;
        lienParente.disabled = false;
        // On garde les valeurs existantes
    } else {
        nomBeneficiaire.disabled = true;
        nomBeneficiaire.value = '';
        lienParente.disabled = true;
        lienParente.value = '';
    }
}

document.getElementById('form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const matricule = document.getElementById('matricule').value.trim();
    const noms = document.getElementById('noms').value.trim();
    const typeControle = document.getElementById('type_controle').value;
    const nomBeneficiaire = document.getElementById('nom_beneficiaire').value.trim();
    const lienParente = document.getElementById('lien_parente').value;

    if (!matricule || !noms || !typeControle) {
        showToast('Matricule, noms et type de contrôle sont obligatoires', 'error');
        return;
    }

    if (typeControle === 'Bénéficiaire') {
        if (!nomBeneficiaire) {
            showToast('Le nom du bénéficiaire est obligatoire pour un contrôle de type Bénéficiaire',
                'error');
            return;
        }
        if (!lienParente) {
            showToast('Le lien de parenté est obligatoire pour un contrôle de type Bénéficiaire', 'error');
            return;
        }
    }

    const btn = document.getElementById('btnSubmit');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mise à jour...';

    document.getElementById('loadingOverlay').classList.add('show');

    const formData = new FormData(this);

    // Pour le type Militaire, on force l'envoi de valeurs vides (le PHP mettra les defaults)
    if (typeControle === 'Militaire') {
        formData.set('nom_beneficiaire', '');
        formData.set('lien_parente', '');
    }

    try {
        const response = await fetch('../../ajax/litiges.php?action=update', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showToast('Litige mis à jour avec succès !', 'success');
            setTimeout(() => {
                window.location.href = 'liste.php';
            }, 1500);
        } else {
            showToast(result.message || 'Erreur lors de la mise à jour', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showToast('Erreur de communication', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        document.getElementById('loadingOverlay').classList.remove('show');
    }
});

document.getElementById('type_controle').addEventListener('change', toggleBeneficiaireFields);

document.addEventListener('DOMContentLoaded', function() {
    toggleBeneficiaireFields();
    document.getElementById('matricule').focus();

    showMessages();
});
</script>

<?php include '../../includes/footer.php'; ?>