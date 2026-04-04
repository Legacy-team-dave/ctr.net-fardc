<?php
// ============================================
// TRAITEMENT PHP (avant tout affichage)
// ============================================
require_once '../../includes/functions.php';
require_login();

// === MODIFICATION : ajout des profils mobiles CONTROLEUR / ENROLEUR ===
$profils = ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR', 'ENROLEUR'];
$error = null;

// Génération du jeton CSRF (valable pour le formulaire)
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Jeton de sécurité invalide.";
    } else {
        $login       = trim($_POST['login']);
        $nom_complet = trim($_POST['nom_complet']);
        $email       = trim($_POST['email']) ?: null;
        $profil      = $_POST['profil'];
        $actif       = strtoupper(trim((string) $profil)) === 'ADMIN_IG' ? 1 : 0;
        $mot_de_passe = $_POST['mot_de_passe'];

        if (empty($login) || empty($nom_complet) || empty($mot_de_passe)) {
            $error = "Les champs login, nom et mot de passe sont requis.";
        } elseif (strlen($mot_de_passe) < 6) {
            $error = "Le mot de passe doit faire au moins 6 caractères.";
        } else {
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (login, mot_de_passe, nom_complet, email, profil, actif) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$login, $hash, $nom_complet, $email, $profil, $actif]);
                log_action('AJOUT_UTILISATEUR', 'utilisateurs', $pdo->lastInsertId(), 'Ajout utilisateur ' . $login . ' (actif=' . $actif . ')');

                // Message flash et redirection
                $message = $actif === 1
                    ? 'Utilisateur ajouté avec succès et activé automatiquement.'
                    : 'Utilisateur ajouté avec succès. Le compte reste inactif en attente d\'activation.';
                redirect_with_flash('liste.php', 'success', $message);
            } catch (PDOException $e) {
                $error = "Erreur : le login existe peut-être déjà.";
            }
        }
    }
}

$page_titre = 'Ajouter un utilisateur';
include '../../includes/header.php';
?>

<!-- ============================================
     RESSOURCES EXTERNES (si non chargées dans header)
     ============================================ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="/ctr.net-fardc/assets/css/fonts.css">
<script src="../../assets/js/sweetalert2.all.min.js"></script>

<style>
    /* ===== DESIGN AGRANDI (comme les autres pages) ===== */
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
        /* augmenté */
    }

    /* Wrapper pour occuper toute la largeur */
    .register-wrapper {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 0 8px;
        /* augmenté */
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

    /* Carte principale – plus grande */
    .card-modern {
        border: none;
        border-radius: 12px;
        /* augmenté */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
        overflow: hidden;
        background: white;
        width: 100%;
    }

    .card-modern .card-body {
        padding: 20px 20px 12px;
        /* augmenté de 12px à 20px */
    }

    /* En-tête de formulaire */
    .form-header {
        text-align: center;
        margin-bottom: 16px;
        /* augmenté */
    }

    .form-header .title {
        font-size: 1.6rem;
        /* augmenté de 1.3rem */
        font-weight: 700;
        color: var(--primary-dark);
        margin-bottom: 6px;
        /* augmenté */
    }

    .form-header .subtitle {
        font-size: 0.9rem;
        /* augmenté de 0.75rem */
        color: var(--gray);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        /* augmenté */
    }

    .form-header .subtitle i {
        color: var(--primary);
        font-size: 0.85rem;
        /* augmenté */
    }

    /* Grille 2 colonnes */
    .row-two-cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        /* augmenté de 8px */
        margin-bottom: 16px;
        /* augmenté de 8px */
    }

    /* Groupes de champs avec icône */
    .input-group-modern {
        margin-bottom: 16px;
        /* augmenté de 8px */
        position: relative;
    }

    .input-group-modern .input-icon {
        position: absolute;
        left: 12px;
        /* augmenté de 8px */
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary);
        z-index: 10;
        font-size: 1rem;
        /* augmenté de 0.8rem */
    }

    .input-group-modern .form-control,
    .input-group-modern .form-select {
        width: 100%;
        padding: 10px 30px 10px 36px;
        /* augmenté */
        border: 1.5px solid #e0e0e0;
        border-radius: 8px;
        /* augmenté */
        font-size: 0.95rem;
        /* augmenté */
        transition: all 0.3s;
        font-family: 'Barlow', sans-serif;
        height: 44px;
        /* augmenté de 34px */
        background-color: white;
    }

    .input-group-modern .form-control:focus,
    .input-group-modern .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.2);
        /* plus large */
        outline: none;
    }

    /* Style spécifique pour select */
    .input-group-modern .form-select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%232e7d32' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 14px;
        padding-right: 32px;
    }

    /* Icône de validation à droite */
    .validation-icon {
        position: absolute;
        right: 30px;
        /* augmenté */
        top: 50%;
        transform: translateY(-50%);
        font-size: 1rem;
        /* augmenté */
        pointer-events: none;
        z-index: 10;
    }

    .validation-icon.valid {
        color: var(--success);
    }

    .validation-icon.invalid {
        color: var(--danger);
    }

    /* Toggle pour le mot de passe */
    .password-toggle {
        position: absolute;
        right: 8px;
        /* légèrement ajusté */
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: var(--gray);
        z-index: 10;
        background: white;
        padding: 4px;
        /* augmenté */
        border-radius: 50%;
        transition: all 0.2s;
        font-size: 0.9rem;
        /* augmenté */
    }

    /* Force du mot de passe */
    .password-strength {
        margin-top: 6px;
        /* augmenté */
        font-size: 0.8rem;
        /* augmenté */
        display: flex;
        align-items: center;
        gap: 8px;
        /* augmenté */
    }

    .strength-bar {
        flex: 1;
        height: 5px;
        /* augmenté de 3px */
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
        /* légèrement augmenté */
        text-align: right;
        color: var(--gray);
    }

    /* Message d'erreur sous le champ */
    .field-error {
        color: var(--danger);
        font-size: 0.75rem;
        /* augmenté */
        margin-top: 4px;
        /* augmenté */
        padding-left: 36px;
        /* ajusté */
    }

    /* Checkbox personnalisée */
    .form-check-modern {
        display: flex;
        align-items: center;
        margin: 12px 0 6px 0;
        /* augmenté */
        padding-left: 0;
    }

    .form-check-modern input[type="checkbox"] {
        width: 20px;
        /* augmenté */
        height: 20px;
        /* augmenté */
        margin-right: 8px;
        /* augmenté */
        cursor: pointer;
        accent-color: var(--primary);
    }

    .form-check-modern label {
        display: flex;
        align-items: center;
        gap: 6px;
        /* augmenté */
        font-size: 0.9rem;
        /* augmenté */
        cursor: pointer;
        color: #495057;
    }

    .form-check-modern label i {
        color: var(--primary);
        font-size: 0.85rem;
        /* augmenté */
    }

    /* Carte d'information sur les profils */
    .info-card {
        background: var(--light);
        border-left: 5px solid var(--primary);
        /* plus épais */
        border-radius: 8px;
        /* augmenté */
        padding: 12px 15px;
        /* augmenté */
        margin-bottom: 20px;
        /* augmenté */
        font-size: 0.85rem;
        /* augmenté */
    }

    .info-card strong {
        color: var(--primary-dark);
    }

    .info-card .profile-badge {
        display: inline-block;
        padding: 3px 8px;
        /* augmenté */
        border-radius: 20px;
        /* plus arrondi */
        background: #e0e0e0;
        font-weight: 600;
        margin: 3px 0;
        /* augmenté */
        font-size: 0.75rem;
        /* augmenté */
    }

    /* Boutons d'action */
    .action-buttons {
        display: flex;
        gap: 12px;
        /* augmenté */
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 20px;
        /* augmenté */
    }

    .btn-modern {
        border-radius: 30px;
        /* plus arrondi */
        padding: 8px 18px;
        /* augmenté */
        font-weight: 600;
        font-size: 0.9rem;
        /* augmenté */
        text-transform: uppercase;
        letter-spacing: 0.3px;
        /* légèrement augmenté */
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        /* augmenté */
        min-width: 110px;
        /* augmenté */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        color: white;
    }

    .btn-modern i {
        font-size: 0.85rem;
        /* augmenté */
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

    /* Alerte moderne (pour les erreurs éventuelles) */
    .alert-modern {
        border-radius: 8px;
        /* augmenté */
        border: none;
        padding: 8px 12px;
        /* augmenté */
        margin-bottom: 16px;
        /* augmenté */
        font-size: 0.85rem;
        /* augmenté */
        display: flex;
        align-items: center;
        gap: 8px;
        /* augmenté */
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

    /* Adaptation mobile */
    @media (max-width: 576px) {
        .row-two-cols {
            grid-template-columns: 1fr;
            gap: 12px;
            /* augmenté */
        }

        .action-buttons {
            flex-direction: column;
            gap: 8px;
            /* augmenté */
        }

        .btn-modern {
            width: 100%;
            min-width: auto;
        }

        .card-modern .card-body {
            padding: 16px;
            /* encore plus grand sur mobile */
        }
    }
</style>

<!-- ============================================
     CONTENU PRINCIPAL
     ============================================ -->
<div class="container-fluid py-3"> <!-- augmenté py-2 à py-3 -->
    <div class="register-wrapper">
        <div class="card-modern">
            <div class="card-body">
                <!-- En-tête -->
                <div class="form-header">
                    <div class="title">Créer un utilisateur</div>
                    <div class="subtitle">
                        <i class="fas fa-user-plus"></i>
                        <span>Remplissez les informations</span>
                    </div>
                </div>

                <!-- Affichage d'une erreur éventuelle via SweetAlert et alerte inline -->
                <?php if (isset($error) && $error): ?>
                    <div class="alert-modern error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: '<?= addslashes($error) ?>',
                            confirmButtonColor: '#2e7d32',
                            confirmButtonText: 'Compris'
                        });
                    </script>
                <?php endif; ?>

                <!-- Carte d'information sur les profils (compacte) -->
                <div class="info-card">
                    <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                    <strong>Profils :</strong>
                    <span class="profile-badge" style="background:#2e7d32; color:white;">ADMIN_IG</span> Admin
                    <span class="profile-badge" style="background:#17a2b8; color:white;">OPERATEUR</span> Gestion
                    <span class="profile-badge" style="background:#ffc107; color:#212529;">CONTROLEUR</span> Mobile contrôle
                    <span class="profile-badge" style="background:#6f42c1; color:white;">ENROLEUR</span> Mobile enrôlement
                </div>

                <form method="post" id="userForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                    <!-- Ligne 1 : Login et Profil -->
                    <div class="row-two-cols">
                        <div class="input-group-modern">
                            <i class="fas fa-tag input-icon"></i>
                            <input type="text" name="login" id="login" class="form-control" placeholder="Login *"
                                value="<?= isset($_POST['login']) ? htmlspecialchars($_POST['login']) : '' ?>" required
                                maxlength="50" pattern="[a-zA-Z0-9._-]+"
                                title="Lettres, chiffres, points, tirets et underscores">
                        </div>
                        <div class="input-group-modern">
                            <i class="fas fa-user-tag input-icon"></i>
                            <select name="profil" id="profil" class="form-select" required>
                                <option value="" disabled <?= !isset($_POST['profil']) ? 'selected' : '' ?>>Profil *
                                </option>
                                <?php foreach ($profils as $p): ?>
                                    <option value="<?= $p ?>"
                                        <?= (isset($_POST['profil']) && $_POST['profil'] == $p) ? 'selected' : '' ?>>
                                        <?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Ligne 2 : Mot de passe et confirmation -->
                    <div class="row-two-cols">
                        <div class="input-group-modern">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="mot_de_passe" id="password" class="form-control"
                                placeholder="Mot de passe *" required minlength="6">
                            <span class="password-toggle" onclick="togglePasswordVisibility('password', this)">
                                <i class="fas fa-eye"></i>
                            </span>
                            <span id="passwordStrengthIcon" class="validation-icon"></span>
                            <div class="password-strength">
                                <span class="strength-bar">
                                    <span id="strengthBarFill" class="strength-bar-fill"></span>
                                </span>
                                <span id="strengthText" class="strength-text">Faible</span>
                            </div>
                        </div>
                        <div class="input-group-modern">
                            <i class="fas fa-check-circle input-icon"></i>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                                placeholder="Confirmer *" required>
                            <span class="password-toggle" onclick="togglePasswordVisibility('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </span>
                            <span id="confirmIcon" class="validation-icon"></span>
                            <div id="confirmError" class="field-error"></div>
                        </div>
                    </div>

                    <!-- Ligne 3 : Nom complet (pleine largeur) -->
                    <div class="input-group-modern">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="nom_complet" id="nom_complet" class="form-control"
                            placeholder="Nom complet *"
                            value="<?= isset($_POST['nom_complet']) ? htmlspecialchars($_POST['nom_complet']) : '' ?>"
                            required>
                    </div>

                    <!-- Ligne 4 : Email et Actif (deux colonnes) -->
                    <div class="row-two-cols">
                        <div class="input-group-modern">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" id="email" class="form-control"
                                placeholder="Email (optionnel)"
                                value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        </div>
                        <div class="form-check-modern">
                            <input type="checkbox" name="actif" id="actif" class="form-check-input"
                                <?= (isset($_POST['profil']) && strtoupper((string) $_POST['profil']) === 'ADMIN_IG') ? 'checked' : '' ?> disabled>
                            <label for="actif">
                                <i class="fas fa-check-circle"></i> Actif automatique selon le profil
                            </label>
                        </div>
                        <small id="actifHelp" class="text-muted d-block mt-1">
                            Seul le profil ADMIN_IG est activé à la création.
                        </small>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="action-buttons">
                        <button type="submit" class="btn-modern btn-primary-modern" id="submitBtn">
                            <i class="fas fa-save"></i> Enregistrer
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

<!-- ============================================
     SCRIPTS JAVASCRIPT (inchangés)
     ============================================ -->
<script>
    // Fonction pour afficher/masquer le mot de passe
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

    // Évaluation de la force du mot de passe
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
        let text = 'Faible';
        let color = '#dc3545';

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

    // Validation finale avec SweetAlert
    function validateForm(event) {
        event.preventDefault();

        const login = document.getElementById('login').value.trim();
        const nomComplet = document.getElementById('nom_complet').value.trim();
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const profil = document.getElementById('profil').value;

        // Vérifications simples
        if (!login || !nomComplet || !password || !profil) {
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

        if (password.length < 6) {
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

        // Confirmation avant envoi
        Swal.fire({
            title: 'Confirmer l\'ajout',
            html: `<p>Êtes-vous sûr de vouloir ajouter <strong>${escapeHtml(login)}</strong> ?</p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2e7d32',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, ajouter',
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

    // Réinitialisation du formulaire
    function resetForm() {
        Swal.fire({
            title: 'Réinitialiser',
            text: 'Voulez-vous vraiment effacer tous les champs ?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, réinitialiser',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('userForm').reset();
                document.getElementById('strengthBarFill').style.width = '0%';
                document.getElementById('strengthText').textContent = '';
                document.getElementById('passwordStrengthIcon').innerHTML = '';
                document.getElementById('confirmIcon').innerHTML = '';
                document.getElementById('confirmError').textContent = '';
                Swal.fire({
                    icon: 'success',
                    title: 'Réinitialisé',
                    text: 'Formulaire réinitialisé',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    }

    // Gestionnaire de modifications non sauvegardées
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

    // Initialisation des écouteurs pour la force du mot de passe
    document.getElementById('password').addEventListener('input', function() {
        updatePasswordStrength();
        updateConfirmation();
    });
    document.getElementById('confirm_password').addEventListener('input', updateConfirmation);

    // Auto-formatage du login (minuscules)
    document.getElementById('login').addEventListener('input', function(e) {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9._-]/g, '');
    });

    function syncActifWithProfil() {
        const profil = (document.getElementById('profil').value || '').trim().toUpperCase();
        const actifCheckbox = document.getElementById('actif');
        const actifHelp = document.getElementById('actifHelp');
        const isAdmin = profil === 'ADMIN_IG';

        actifCheckbox.checked = isAdmin;
        actifHelp.textContent = isAdmin
            ? 'Le compte sera créé actif (1) pour le profil ADMIN_IG.'
            : 'Le compte sera créé inactif (0) et devra être activé ultérieurement.';
    }

    document.getElementById('profil').addEventListener('change', syncActifWithProfil);

    // Attacher la validation au formulaire
    document.getElementById('userForm').addEventListener('submit', validateForm);

    // Initialisation au chargement
    updatePasswordStrength();
    updateConfirmation();
    syncActifWithProfil();
</script>

<?php include '../../includes/footer.php'; ?>