<?php
// modules/litige/ajouter.php
require_once '../../includes/functions.php';
require_login();

// ============================
// PARTIE PHP : GESTION EN SESSION
// ============================
$toast_message = $_SESSION['toast_message'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast_message'], $_SESSION['toast_type']);

$page_titre = 'Ajouter un litige';
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
<style>
/* ===== STYLES COMPACTS ===== */
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
    width: 14px;
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

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-2px);
    box-shadow: 0 3px 6px rgba(40, 167, 69, 0.3);
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

.quick-actions {
    margin-top: 12px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
    display: none;
    border-left: 4px solid #2e7d32;
}

.quick-actions.show {
    display: block;
    animation: fadeIn 0.5s ease;
}

.quick-actions h4 {
    color: #2e7d32;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.quick-actions-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.quick-btn {
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 0.75rem;
    border: 1px solid #2e7d32;
    background: white;
    color: #2e7d32;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.quick-btn:hover {
    background: #2e7d32;
    color: white;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
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
    <h2><i class="fas fa-gavel"></i> Nouveau litige</h2>

    <form id="form">
        <div class="section-title">
            <i class="fas fa-info-circle"></i> Informations générales
        </div>

        <div class="detail-grid form-grid">
            <?php
            $fields = [
                'matricule'        => ['Matricule', 'required', '172134576144'],
                'noms'             => ['Noms', 'required', 'Dave Akwas'],
                'grade'            => ['Grade', '', 'GENMAJ'],
                'type_controle'    => ['Type contrôle', 'required', ''],
                'nom_beneficiaire' => ['Nom bénéficiaire', '', 'Reedy Mwenge'],
                'lien_parente'     => ['Lien parenté', '', ''],
                'garnison'         => ['Garnison', '', 'Garnison de Boma'],
                'province'         => ['Province', '', 'Kongo-Central']
            ];

            foreach ($fields as $name => $f):
                $icon = 'fas fa-' . match($name) {
                    'matricule' => 'id-card',
                    'noms' => 'user',
                    'grade' => 'star',
                    'type_controle' => 'clipboard',
                    'nom_beneficiaire' => 'user-tie',
                    'lien_parente' => 'link',
                    'garnison' => 'map-pin',
                    'province' => 'map-marked-alt',
                    default => 'circle'
                };
            ?>
            <div class="detail-item">
                <div class="detail-label">
                    <i class="<?= $icon ?>"></i>
                    <?= $f[0] ?> <?= $f[1] === 'required' ? '<span class="required">*</span>' : '' ?>
                </div>
                <?php if ($name === 'type_controle'): ?>
                <select name="type_controle" id="type_controle" required>
                    <option value="">Sélectionnez</option>
                    <option value="Militaire">Militaire</option>
                    <option value="Bénéficiaire">Bénéficiaire</option>
                </select>
                <?php elseif ($name === 'lien_parente'): ?>
                <select name="lien_parente" id="lien_parente">
                    <option value="">Sélectionnez</option>
                    <option value="Veuve">Veuve</option>
                    <option value="Veuf">Veuf</option>
                    <option value="Orphelin">Orphelin</option>
                    <option value="Tuteur">Tuteur</option>
                </select>
                <?php else: ?>
                <input type="text" name="<?= $name ?>" id="<?= $name ?>" <?= $f[1] === 'required' ? 'required' : '' ?>
                    value="" placeholder="<?= $f[2] ?>" autocomplete="off">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="detail-item full-width">
                <div class="detail-label"><i class="fas fa-comment"></i> Observations</div>
                <textarea name="observations" id="observations" rows="2" placeholder="Détails..."></textarea>
            </div>
        </div>

        <div class="auto-date-indicator">
            <i class="fas fa-calendar-check"></i> Date contrôle automatique : aujourd'hui
        </div>

        <div class="button-bar">
            <button type="submit" class="btn btn-save" id="btnSubmit">
                <i class="fas fa-save"></i> Enregistrer
            </button>
            <!-- MODIFICATION : Redirection dans le même onglet -->
            <button type="button" class="btn btn-success" onclick="window.location.href='liste.php'">
                <i class="fas fa-list"></i> Liste
            </button>
            <button type="button" class="btn btn-cancel" onclick="resetForm()">
                <i class="fas fa-eraser"></i> Effacer
            </button>
        </div>
    </form>

    <!-- Quick actions après enregistrement -->
    <div class="quick-actions" id="quickActions">
        <h4><i class="fas fa-check-circle"></i> Litige enregistré !</h4>
        <div class="quick-actions-buttons">
            <button class="quick-btn" onclick="addAnother()">
                <i class="fas fa-plus"></i> Ajouter un autre
            </button>
            <button class="quick-btn" onclick="viewList()">
                <i class="fas fa-eye"></i> Voir dans la liste
            </button>
        </div>
    </div>
</div>

<script>
let lastInsertedId = null;

// Fonction pour afficher les toasts
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
    } else {
        nomBeneficiaire.disabled = true;
        nomBeneficiaire.value = '';
        lienParente.disabled = true;
        lienParente.value = '';
    }
}

function resetForm() {
    document.getElementById('form').reset();
    toggleBeneficiaireFields();
    document.getElementById('matricule').focus();
    document.getElementById('quickActions').classList.remove('show');
}

function addAnother() {
    resetForm();
    showToast('Prêt pour un nouveau litige', 'success');
}

// MODIFICATION : Redirection dans le même onglet avec passage éventuel de paramètres
function viewList() {
    if (lastInsertedId) {
        window.location.href = 'liste.php?toast=' + encodeURIComponent('Nouveau litige ajouté') + '&new_id=' +
            lastInsertedId;
    } else {
        window.location.href = 'liste.php';
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
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';

    document.getElementById('loadingOverlay').classList.add('show');

    const formData = new FormData(this);
    const today = new Date().toISOString().split('T')[0];
    formData.append('date_controle', today);

    // Pour le type Militaire, on force l'envoi de valeurs vides (le PHP mettra les defaults)
    if (typeControle === 'Militaire') {
        formData.set('nom_beneficiaire', '');
        formData.set('lien_parente', '');
    }

    try {
        const response = await fetch('../../ajax/litiges.php?action=create', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            lastInsertedId = result.id;
            showToast('Litige ajouté avec succès !', 'success');
            document.getElementById('quickActions').classList.add('show');
            resetForm();
        } else {
            showToast(result.message || 'Erreur lors de l\'enregistrement', 'error');
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

    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            document.getElementById('form').dispatchEvent(new Event('submit'));
        }
    });

    showMessages();
});

window.addEventListener('beforeunload', function(e) {
    const form = document.getElementById('form');
    const hasValues = Array.from(form.elements).some(el =>
        el.type !== 'button' && el.value && el.value.trim() !== ''
    );

    if (hasValues && !document.getElementById('quickActions').classList.contains('show')) {
        e.preventDefault();
        e.returnValue = 'Données non enregistrées. Quitter ?';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>