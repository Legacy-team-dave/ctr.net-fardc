<?php
// ============================================
// TRAITEMENT PHP (avant tout affichage)
// ============================================
require_once '../../includes/functions.php';
require_login();

$error = null;
$profils = ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR', 'ENROLEUR'];

// Récupération de l'ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    redirect_with_flash('liste.php', 'danger', 'ID utilisateur invalide.');
}

// Chargement des données actuelles
$stmt = $pdo->prepare("SELECT id_utilisateur, login, nom_complet, email, profil, actif FROM utilisateurs WHERE id_utilisateur = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect_with_flash('liste.php', 'danger', 'Utilisateur introuvable.');
}

// Génération du jeton CSRF
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Jeton de sécurité invalide.";
    } else {
        $login       = trim($_POST['login']);
        $nom_complet = trim($_POST['nom_complet']);
        $email       = trim($_POST['email']) ?: null;
        $profil      = $_POST['profil'];
        $actif       = isset($_POST['actif']) ? 1 : 0;
        $mot_de_passe = $_POST['mot_de_passe'];
        $confirm     = $_POST['confirm_password'];

        // Validations
        if (empty($login) || empty($nom_complet)) {
            $error = "Le login et le nom complet sont requis.";
        } elseif (!empty($mot_de_passe) && strlen($mot_de_passe) < 6) {
            $error = "Le mot de passe doit faire au moins 6 caractères.";
        } elseif (!empty($mot_de_passe) && $mot_de_passe !== $confirm) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            try {
                if (empty($mot_de_passe)) {
                    // Pas de changement de mot de passe
                    $sql = "UPDATE utilisateurs SET login = ?, nom_complet = ?, email = ?, profil = ?, actif = ? WHERE id_utilisateur = ?";
                    $params = [$login, $nom_complet, $email, $profil, $actif, $id];
                } else {
                    // Avec nouveau mot de passe
                    $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                    $sql = "UPDATE utilisateurs SET login = ?, mot_de_passe = ?, nom_complet = ?, email = ?, profil = ?, actif = ? WHERE id_utilisateur = ?";
                    $params = [$login, $hash, $nom_complet, $email, $profil, $actif, $id];
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                log_action('MODIFICATION_UTILISATEUR', 'utilisateurs', $id, 'Modification utilisateur ' . $login);

                redirect_with_flash('liste.php', 'success', 'Utilisateur modifié avec succès.');
            } catch (PDOException $e) {
                $error = "Erreur : le login existe peut-être déjà.";
            }
        }
    }
}

$page_titre = 'Modifier un utilisateur';
include '../../includes/header.php';
?>

<!-- Ressources (identiques à ajouter) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="/ctr.net-fardc/assets/css/fonts.css">
<script src="../../assets/js/sweetalert2.all.min.js"></script>

<style>
    /* ===== DESIGN AGRANDI (identique à ajouter.php) ===== */
    :root {
        --primary: #2e7d32;
        --primary-dark: #1b5e20;
        --success: #28a745;
        --danger: #dc3545;
        --warning: #ffc107;
        --gray: #6c757d;
        --light: #f8f9fa;
    }

    body {
        font-family: 'Barlow', sans-serif;
        background: #f4f6f9;
        padding: 12px;
        font-size: 16px;
    }

    .container-fluid {
        padding: 8px 15px;
    }

    .register-wrapper {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 0 8px;
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
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
        overflow: hidden;
        background: white;
        width: 100%;
    }

    .card-modern .card-body {
        padding: 20px 20px 12px;
    }

    .form-header {
        text-align: center;
        margin-bottom: 16px;
    }

    .form-header .title {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary-dark);
        margin-bottom: 6px;
    }

    .form-header .subtitle {
        font-size: 0.9rem;
        color: var(--gray);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .form-header .subtitle i {
        color: var(--primary);
        font-size: 0.85rem;
    }

    .row-two-cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }

    .input-group-modern {
        margin-bottom: 16px;
        position: relative;
    }

    .input-group-modern .input-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary);
        z-index: 10;
        font-size: 1rem;
    }

    .input-group-modern .form-control,
    .input-group-modern .form-select {
        width: 100%;
        padding: 10px 30px 10px 36px;
        border: 1.5px solid #e0e0e0;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.3s;
        font-family: 'Barlow', sans-serif;
        height: 44px;
        background-color: white;
    }

    .input-group-modern .form-control:focus,
    .input-group-modern .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.2);
        outline: none;
    }

    .input-group-modern .form-select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%232e7d32' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 14px;
        padding-right: 32px;
    }

    .validation-icon {
        position: absolute;
        right: 30px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1rem;
        pointer-events: none;
        z-index: 10;
    }

    .validation-icon.valid {
        color: var(--success);
    }

    .validation-icon.invalid {
        color: var(--danger);
    }

    .password-toggle {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: var(--gray);
        z-index: 10;
        background: white;
        padding: 4px;
        border-radius: 50%;
        transition: all 0.2s;
        font-size: 0.9rem;
    }

    .password-strength {
        margin-top: 6px;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .strength-bar {
        flex: 1;
        height: 5px;
        background-color: #e0e0e0;
        border-radius: 3px;
        overflow: hidden;
    }

    .strength-bar-fill {
        height: 100%;
        width: 0%;
        transition: width 0.3s, background-color 0.3s;
    }

    .strength-text {
        min-width: 50px;
        text-align: right;
        color: var(--gray);
    }

    .field-error {
        color: var(--danger);
        font-size: 0.75rem;
        margin-top: 4px;
        padding-left: 36px;
    }

    .form-check-modern {
        display: flex;
        align-items: center;
        margin: 12px 0 6px 0;
        padding-left: 0;
    }

    .form-check-modern input[type="checkbox"] {
        width: 20px;
        height: 20px;
        margin-right: 8px;
        cursor: pointer;
        accent-color: var(--primary);
    }

    .form-check-modern label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.9rem;
        cursor: pointer;
        color: #495057;
    }

    .form-check-modern label i {
        color: var(--primary);
    }

    .info-card {
        background: var(--light);
        border-left: 5px solid var(--primary);
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 20px;
        font-size: 0.85rem;
    }

    .info-card strong {
        color: var(--primary-dark);
    }

    .info-card .profile-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 20px;
        background: #e0e0e0;
        font-weight: 600;
        margin: 3px 0;
    }

    .action-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 20px;
    }

    .btn-modern {
        border-radius: 30px;
        padding: 8px 18px;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-width: 110px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        color: white;
    }

    .btn-modern i {
        font-size: 0.85rem;
    }

    .btn-primary-modern {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    }

    .btn-primary-modern:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(46, 125, 50, 0.3);
    }

    .btn-secondary-modern {
        background: linear-gradient(135deg, var(--gray) 0%, #5a6268 100%);
    }

    .btn-secondary-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
    }

    .btn-warning-modern {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        color: #212529;
    }

    .btn-warning-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
    }

    .btn-modern:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .alert-modern {
        border-radius: 8px;
        border: none;
        padding: 8px 12px;
        margin-bottom: 16px;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .alert-modern.error {
        background: #fee2e2;
        color: #991b1b;
        border-left: 4px solid var(--danger);
    }

    .alert-modern.success {
        background: #d4edda;
        color: #155724;
        border-left: 4px solid var(--success);
    }

    @media (max-width: 576px) {
        .row-two-cols {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .action-buttons {
            flex-direction: column;
            gap: 8px;
        }

        .btn-modern {
            width: 100%;
            min-width: auto;
        }

        .card-modern .card-body {
            padding: 16px;
        }
    }
</style>

<div class="container-fluid py-3">
    <div class="register-wrapper">
        <div class="card-modern">
            <div class="card-body">
                <div class="form-header">
                    <div class="title">Modifier l'utilisateur</div>
                    <div class="subtitle">
                        <i class="fas fa-user-edit"></i>
                        <span>Modifiez les informations</span>
                    </div>
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

                <div class="info-card">
                    <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                    <strong>Modification en cours :</strong> <?= htmlspecialchars($user['login']) ?>
                    (<?= htmlspecialchars($user['nom_complet']) ?>)
                </div>

                <form method="post" id="userForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                    <!-- Ligne 1 : Login et Profil -->
                    <div class="row-two-cols">
                        <div class="input-group-modern">
                            <i class="fas fa-tag input-icon"></i>
                            <input type="text" name="login" id="login" class="form-control" placeholder="Login *"
                                value="<?= htmlspecialchars($user['login']) ?>" required maxlength="50"
                                pattern="[a-zA-Z0-9._-]+" title="Lettres, chiffres, points, tirets et underscores">
                        </div>
                        <div class="input-group-modern">
                            <i class="fas fa-user-tag input-icon"></i>
                            <select name="profil" id="profil" class="form-select" required>
                                <option value="" disabled>Profil *</option>
                                <?php foreach ($profils as $p): ?>
                                    <option value="<?= $p ?>" <?= $user['profil'] == $p ? 'selected' : '' ?>>
                                        <?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Ligne 2 : Mot de passe (optionnel) et confirmation -->
                    <div class="row-two-cols">
                        <div class="input-group-modern">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="mot_de_passe" id="password" class="form-control"
                                placeholder="Nouveau mot de passe (laisser vide pour conserver)">
                            <span class="password-toggle" onclick="togglePasswordVisibility('password', this)">
                                <i class="fas fa-eye"></i>
                            </span>
                            <span id="passwordStrengthIcon" class="validation-icon"></span>
                            <div class="password-strength">
                                <span class="strength-bar">
                                    <span id="strengthBarFill" class="strength-bar-fill"></span>
                                </span>
                                <span id="strengthText" class="strength-text"></span>
                            </div>
                        </div>
                        <div class="input-group-modern">
                            <i class="fas fa-check-circle input-icon"></i>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                                placeholder="Confirmer le mot de passe">
                            <span class="password-toggle" onclick="togglePasswordVisibility('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </span>
                            <span id="confirmIcon" class="validation-icon"></span>
                            <div id="confirmError" class="field-error"></div>
                        </div>
                    </div>

                    <!-- Ligne 3 : Nom complet -->
                    <div class="input-group-modern">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="nom_complet" id="nom_complet" class="form-control"
                            placeholder="Nom complet *" value="<?= htmlspecialchars($user['nom_complet']) ?>" required>
                    </div>

                    <!-- Ligne 4 : Email et Actif -->
                    <div class="row-two-cols">
                        <div class="input-group-modern">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" id="email" class="form-control"
                                placeholder="Email (optionnel)" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                        <div class="form-check-modern">
                            <input type="checkbox" name="actif" id="actif" class="form-check-input"
                                <?= $user['actif'] ? 'checked' : '' ?>>
                            <label for="actif">
                                <i class="fas fa-check-circle"></i> Compte actif
                            </label>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn-modern btn-primary-modern" id="submitBtn">
                            <i class="fas fa-save"></i> Mettre à jour
                        </button>
                        <a href="liste.php" class="btn-modern btn-secondary-modern" id="cancelBtn">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                        <button type="button" class="btn-modern btn-warning-modern" onclick="resetForm()">
                            <i class="fas fa-undo"></i> Réinitialiser
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Fonctions identiques à ajouter.php
    function togglePasswordVisibility(fieldId, element) {
        const input = document.getElementById(fieldId);
        const icon = element.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function evaluatePasswordStrength(password) {
        let strength = 0;
        if (password.length >= 6) strength += 1;
        if (password.length >= 8) strength += 1;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 1;
        if (/[0-9]/.test(password)) strength += 1;
        if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
        return strength;
    }

    function updatePasswordStrength() {
        const password = document.getElementById('password').value;
        const strength = evaluatePasswordStrength(password);
        const barFill = document.getElementById('strengthBarFill');
        const strengthText = document.getElementById('strengthText');
        const iconSpan = document.getElementById('passwordStrengthIcon');
        let fillPercent = 0;
        let text = '';
        let color = '#e0e0e0';

        if (password.length === 0) {
            fillPercent = 0;
            text = '';
            color = '#e0e0e0';
            iconSpan.innerHTML = '';
        } else if (strength <= 2) {
            fillPercent = 20;
            text = 'Faible';
            color = '#dc3545';
            iconSpan.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#dc3545;"></i>';
        } else if (strength <= 3) {
            fillPercent = 50;
            text = 'Moyen';
            color = '#ffc107';
            iconSpan.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#ffc107;"></i>';
        } else if (strength <= 4) {
            fillPercent = 75;
            text = 'Bon';
            color = '#17a2b8';
            iconSpan.innerHTML = '<i class="fas fa-check-circle" style="color:#17a2b8;"></i>';
        } else {
            fillPercent = 100;
            text = 'Fort';
            color = '#28a745';
            iconSpan.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745;"></i>';
        }

        barFill.style.width = fillPercent + '%';
        barFill.style.backgroundColor = color;
        strengthText.textContent = text;
        strengthText.style.color = color;
    }

    function updateConfirmation() {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const confirmIcon = document.getElementById('confirmIcon');
        const confirmError = document.getElementById('confirmError');

        if (confirm.length === 0) {
            confirmIcon.innerHTML = '';
            confirmError.textContent = '';
            return;
        }

        if (password === confirm) {
            confirmIcon.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745;"></i>';
            confirmError.textContent = '';
        } else {
            confirmIcon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545;"></i>';
            confirmError.textContent = 'Les mots de passe ne correspondent pas.';
        }
    }

    function validateForm(event) {
        event.preventDefault();

        const login = document.getElementById('login').value.trim();
        const nomComplet = document.getElementById('nom_complet').value.trim();
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const profil = document.getElementById('profil').value;

        if (!login || !nomComplet || !profil) {
            Swal.fire({
                icon: 'warning',
                title: 'Champs manquants',
                text: 'Veuillez remplir tous les champs obligatoires.',
                confirmButtonColor: '#2e7d32'
            });
            return false;
        }

        const loginRegex = /^[a-zA-Z0-9._-]+$/;
        if (!loginRegex.test(login)) {
            Swal.fire({
                icon: 'error',
                title: 'Login invalide',
                text: 'Le login ne peut contenir que des lettres, chiffres, points, tirets et underscores.',
                confirmButtonColor: '#2e7d32'
            });
            return false;
        }

        if (password !== '' && password.length < 6) {
            Swal.fire({
                icon: 'warning',
                title: 'Mot de passe trop court',
                text: 'Le mot de passe doit contenir au moins 6 caractères.',
                confirmButtonColor: '#2e7d32'
            });
            return false;
        }

        if (password !== confirm) {
            Swal.fire({
                icon: 'error',
                title: 'Mots de passe différents',
                text: 'La confirmation ne correspond pas.',
                confirmButtonColor: '#2e7d32'
            });
            return false;
        }

        const email = document.getElementById('email').value.trim();
        if (email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Email invalide',
                    text: 'Veuillez saisir une adresse email valide.',
                    confirmButtonColor: '#2e7d32'
                });
                return false;
            }
        }

        Swal.fire({
            title: 'Confirmer la modification',
            html: `<p>Mettre à jour <strong>${escapeHtml(login)}</strong> ?</p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2e7d32',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, modifier',
            cancelButtonText: 'Annuler',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
                document.getElementById('userForm').submit();
            }
        });

        return false;
    }

    function escapeHtml(text) {
        if (!text) return text;
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function resetForm() {
        Swal.fire({
            title: 'Réinitialiser',
            text: 'Voulez-vous vraiment effacer les modifications ?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, réinitialiser',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.reload();
            }
        });
    }

    let formModified = false;
    document.querySelectorAll('#userForm input, #userForm select').forEach(el => {
        el.addEventListener('change', () => formModified = true);
        el.addEventListener('keyup', () => formModified = true);
    });

    document.getElementById('cancelBtn').addEventListener('click', function(e) {
        if (formModified) {
            e.preventDefault();
            Swal.fire({
                title: 'Modifications non sauvegardées',
                text: 'Voulez-vous vraiment quitter ?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Oui, quitter',
                cancelButtonText: 'Rester'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = this.href;
                }
            });
        }
    });

    document.getElementById('login').addEventListener('input', function(e) {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9._-]/g, '');
    });

    document.getElementById('password').addEventListener('input', function() {
        updatePasswordStrength();
        updateConfirmation();
    });
    document.getElementById('confirm_password').addEventListener('input', updateConfirmation);

    updatePasswordStrength();
    updateConfirmation();

    document.getElementById('userForm').addEventListener('submit', validateForm);
</script>

<?php include '../../includes/footer.php'; ?>