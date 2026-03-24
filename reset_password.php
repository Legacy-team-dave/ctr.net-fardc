<?php
require_once 'includes/functions.php';

$step = $_GET['step'] ?? 'request';

// Pour les étapes autres que 'reset', l'utilisateur doit être connecté en tant qu'admin
if ($step !== 'reset') {
    if (!isset($_SESSION['user_id'])) {
        redirect_with_flash('login.php', 'danger', 'Accès non autorisé. Veuillez vous connecter pour continuer.');
        exit;
    }
}

$error = '';
$message = '';

// -------------------------------------------------------------------
// Étape 1 : Demande de réinitialisation (saisie de l'email/login)
// -------------------------------------------------------------------
if ($step === 'request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');

    if (empty($login)) {
        $error = "Veuillez entrer votre login, nom ou email.";
    } else {
        try {
            // Vérifier si l'utilisateur existe
            $stmt = $pdo->prepare("SELECT id_utilisateur, email, nom_complet FROM utilisateurs WHERE (login = ? OR nom_complet = ? OR email = ?) AND actif = true");
            $stmt->execute([$login, $login, $login]);
            $user = $stmt->fetch();

            if ($user) {
                // Générer un token unique
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Stocker le token et l'expiration dans la table utilisateurs
                $stmt = $pdo->prepare("UPDATE utilisateurs SET reset_token = ?, reset_expires = ? WHERE id_utilisateur = ?");
                $stmt->execute([$token, $expires, $user['id_utilisateur']]);

                // --- AJOUT LOG (même si non connecté) ---
                log_action('DEMANDE_RESET', 'utilisateurs', $user['id_utilisateur'], "Demande de reset pour login: $login");
                // --- FIN LOG ---

                // Simuler l'envoi d'email : en développement, on affiche le lien
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?step=reset&token=" . $token;
                // Ici, en production, on enverrait un email avec SwiftMailer, etc.
                // Pour la démo, on stocke le lien dans un message
                $message = "Un lien de réinitialisation a été généré. (Simulation) <br> <a href='$resetLink' style='color:#1877f2;'>$resetLink</a>";
            } else {
                // Pour des raisons de sécurité, on affiche le même message que succès
                $message = "Si ce compte existe, un email lui a été envoyé.";
            }
        } catch (PDOException $e) {
            error_log("Erreur reset_password (request) : " . $e->getMessage());
            $error = "Erreur technique, veuillez réessayer plus tard.";
        }
    }
}

// -------------------------------------------------------------------
// Étape 2 : Réinitialisation avec le token (accessible sans être admin,
// car le lien est envoyé par email à l'utilisateur concerné)
// -------------------------------------------------------------------
if ($step === 'reset') {
    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        $error = "Token manquant.";
    } else {
        try {
            // Vérifier le token dans la table utilisateurs
            $stmt = $pdo->prepare("SELECT id_utilisateur, email FROM utilisateurs WHERE reset_token = ? AND reset_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "Token invalide ou expiré.";
            } else {
                // Token valide : afficher le formulaire de nouveau mot de passe
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $password = trim($_POST['password'] ?? '');
                    $confirm  = trim($_POST['confirm_password'] ?? '');

                    if (empty($password) || strlen($password) < 4) {
                        $error = "Le mot de passe doit contenir au moins 4 caractères.";
                    } elseif ($password !== $confirm) {
                        $error = "Les mots de passe ne correspondent pas.";
                    } else {
                        // Hacher le nouveau mot de passe
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        // Mettre à jour le mot de passe de l'utilisateur
                        $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ?, reset_token = NULL, reset_expires = NULL WHERE id_utilisateur = ?");
                        $stmt->execute([$hashedPassword, $user['id_utilisateur']]);

                        // --- AJOUT LOG (même si non connecté) ---
                        log_action('RESET_MDP', 'utilisateurs', $user['id_utilisateur'], "Mot de passe réinitialisé");
                        // --- FIN LOG ---

                        $message = "Mot de passe mis à jour. <a href='login.php' style='color:#1877f2;'>Connectez-vous</a>.";
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur reset_password (reset) : " . $e->getMessage());
            $error = "Erreur technique, veuillez réessayer plus tard.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Réinitialisation mot de passe - IG-FARDC</title>

    <link rel="stylesheet" href="assets/css/fonts.css">

    <!-- Font Awesome local -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <style>
        /* Police globale pour cette page */
        body {
            font-family: 'Barlow', sans-serif;
            font-size: 14px;
        }

        /* Style ultra-compact avec fond image et logo */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --fb-blue: #1877f2;
            --fb-blue-hover: #166fe5;
            --fb-green: #42b72a;
            --fb-green-hover: #36a420;
            --primary: #2e7d32;
            --primary-dark: #1b5e20;
            --gray: #6c757d;
            --light: #f8f9fa;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        body.reset-page {
            /* Fond avec image et superposition sombre */
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.7) 100%),
                url('assets/img/fardc2.png') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Barlow', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 5px;
        }

        .reset-wrapper {
            width: 100%;
            max-width: 360px;
            /* compact */
            animation: fadeInUp 0.3s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reset-logo {
            text-align: center;
            margin-bottom: 8px;
        }

        .reset-logo .brand-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
        }

        .reset-logo .brand-image {
            max-height: 45px;
            width: auto;
            margin-bottom: 2px;
            border: 2px solid white;
            border-radius: 50%;
            padding: 2px;
            background: rgba(255, 255, 255, 0.2);
            transition: transform 0.5s ease;
        }

        .reset-logo .brand-link:hover .brand-image {
            transform: rotate(360deg);
        }

        .reset-logo .brand-text {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            letter-spacing: 0.3px;
            line-height: 1.2;
        }

        .card-modern {
            background: white;
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            padding: 16px 14px 18px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 12px;
        }

        .form-header .title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 2px;
            line-height: 1.2;
        }

        .form-header .subtitle {
            font-size: 0.75rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }

        .input-group-modern {
            margin-bottom: 12px;
            position: relative;
        }

        .input-group-modern .input-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            z-index: 10;
            font-size: 0.8rem;
        }

        .input-group-modern .form-control {
            width: 100%;
            padding: 10px 10px 10px 32px;
            /* espace pour icône */
            border: 1.5px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.85rem;
            transition: all 0.2s;
            font-family: 'Barlow', sans-serif;
            height: 38px;
        }

        .input-group-modern .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.15);
            outline: none;
        }

        /* Validation pour mot de passe */
        .validation-icon {
            position: absolute;
            right: 35px;
            /* à gauche du toggle */
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.8rem;
            pointer-events: none;
            z-index: 10;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray);
            z-index: 11;
            font-size: 0.8rem;
            background: transparent;
            padding: 0;
        }

        .password-strength {
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
            padding-left: 32px;
        }

        .strength-bar {
            flex: 1;
            height: 4px;
            background-color: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
        }

        .strength-text {
            min-width: 35px;
            font-size: 0.65rem;
            color: var(--gray);
            text-align: right;
        }

        .field-error {
            color: #dc3545;
            font-size: 0.65rem;
            margin-top: 2px;
            padding-left: 32px;
        }

        /* Boutons avec icônes */
        .btn-reset,
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            border-radius: 5px;
            padding: 10px 12px;
            font-weight: 600;
            color: white;
            width: 100%;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            font-family: 'Barlow', sans-serif;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-reset {
            background: #991b1b;
        }

        .btn-reset:hover {
            background: #dc3545;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-reset:disabled {
            background: #9cb4d8;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #60757d;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #6c757d;
            transform: translateY(-1px);
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .alert-modern {
            border-radius: 5px;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .alert-modern.success {
            background: #d4edda;
            color: #155724;
            border-left: 3px solid #28a745;
        }

        .alert-modern.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 3px solid #dc3545;
        }

        .reset-footer {
            text-align: center;
            margin-top: 12px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.7rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }

        .reset-footer i {
            color: #ffc107;
            font-size: 0.65rem;
        }

        /* Lien dans les messages */
        .alert-modern a {
            color: #1877f2;
            text-decoration: none;
            font-weight: 500;
        }

        .alert-modern a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body class="reset-page">
    <div class="reset-wrapper">
        <div class="reset-logo">
            <a href="index.php" class="brand-link">
                <img src="assets/img/logo-fardc.png" alt="FARDC Logo" class="brand-image"
                    onerror="this.style.display='none'">
                <span class="brand-text">Réinitialisation</span>
            </a>
        </div>

        <div class="card-modern">
            <?php if ($step === 'request'): ?>
                <!-- Étape 1 : Demande -->
                <div class="form-header">
                    <div class="title">Mot de passe oublié ?</div>
                    <div class="subtitle">
                        <i class="fas fa-envelope"></i>
                        <span>Entrez le login ou email de l'utilisateur</span>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert-modern success">
                        <i class="fas fa-check-circle"></i>
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert-modern error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="input-group-modern">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="login" class="form-control" placeholder="Login, nom ou email" required>
                    </div>
                    <button type="submit" class="btn-reset" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Envoyer le lien
                    </button>
                </form>

                <a href="login.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour à la connexion
                </a>

            <?php elseif ($step === 'reset'): ?>
                <!-- Étape 2 : Nouveau mot de passe (accessible via le lien envoyé, sans admin) -->
                <div class="form-header">
                    <div class="title">Nouveau mot de passe</div>
                    <div class="subtitle">
                        <i class="fas fa-key"></i>
                        <span>Choisissez un mot de passe</span>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert-modern success">
                        <i class="fas fa-check-circle"></i>
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert-modern error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($user) && !$error && !$message): ?>
                    <form method="post" id="resetForm">
                        <div class="input-group-modern" style="position: relative;">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" id="password" class="form-control"
                                placeholder="Nouveau mot de passe (min. 4)" required>
                            <span class="password-toggle" onclick="togglePasswordVisibility('password', this)">
                                <i class="far fa-eye"></i>
                            </span>
                            <span id="passwordStrengthIcon" class="validation-icon"></span>
                            <div class="password-strength">
                                <span class="strength-bar">
                                    <span id="strengthBarFill" class="strength-bar-fill"></span>
                                </span>
                                <span id="strengthText" class="strength-text">Faible</span>
                            </div>
                        </div>

                        <div class="input-group-modern" style="position: relative;">
                            <i class="fas fa-check-circle input-icon"></i>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                                placeholder="Confirmer le mot de passe" required>
                            <span class="password-toggle" onclick="togglePasswordVisibility('confirm_password', this)">
                                <i class="far fa-eye"></i>
                            </span>
                            <span id="confirmIcon" class="validation-icon"></span>
                            <div id="confirmError" class="field-error"></div>
                        </div>

                        <button type="submit" class="btn-reset" id="submitBtn">
                            <i class="fas fa-save"></i> Réinitialiser
                        </button>
                    </form>
                <?php else: ?>
                    <?php if (!$message): ?>
                        <div class="alert-modern error">
                            <i class="fas fa-exclamation-circle"></i> Token invalide ou expiré.
                        </div>
                        <a href="reset_password.php" class="btn-secondary">Nouvelle demande</a>
                    <?php endif; ?>
                <?php endif; ?>

                <a href="login.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour à la connexion
                </a>
            <?php endif; ?>
        </div>

        <div class="reset-footer">
            <i class="fas fa-shield-alt"></i>
            <span>IG - FARDC v1.1.0</span>
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script>
        // Désactiver le bouton après soumission
        $('form').on('submit', function() {
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Traitement...');
        });

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

        // Validation en temps réel du mot de passe (uniquement si présent sur la page)
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const confirmIcon = document.getElementById('confirmIcon');
        const confirmError = document.getElementById('confirmError');
        const strengthBarFill = document.getElementById('strengthBarFill');
        const strengthText = document.getElementById('strengthText');
        const passwordStrengthIcon = document.getElementById('passwordStrengthIcon');

        if (passwordInput) {
            function evaluatePasswordStrength(password) {
                let strength = 0;
                if (password.length >= 4) strength += 1;
                if (password.length >= 6) strength += 1;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
                return Math.min(strength, 5);
            }

            function updatePasswordStrength() {
                const password = passwordInput.value;
                const strength = evaluatePasswordStrength(password);
                let fillPercent = 0;
                let text = '';
                let color = '#e0e0e0';
                let iconHtml = '';

                if (password.length === 0) {
                    fillPercent = 0;
                    text = '';
                    color = '#e0e0e0';
                    iconHtml = '';
                } else if (strength <= 2) {
                    fillPercent = 20;
                    text = 'Faible';
                    color = '#dc3545';
                    iconHtml = '<i class="fas fa-exclamation-triangle" style="color:#dc3545;"></i>';
                } else if (strength <= 3) {
                    fillPercent = 50;
                    text = 'Moyen';
                    color = '#f7b731';
                    iconHtml = '<i class="fas fa-exclamation-circle" style="color:#f7b731;"></i>';
                } else if (strength <= 4) {
                    fillPercent = 75;
                    text = 'Bon';
                    color = '#17a2b8';
                    iconHtml = '<i class="fas fa-check-circle" style="color:#17a2b8;"></i>';
                } else {
                    fillPercent = 100;
                    text = 'Fort';
                    color = '#28a745';
                    iconHtml = '<i class="fas fa-check-circle" style="color:#28a745;"></i>';
                }

                strengthBarFill.style.width = fillPercent + '%';
                strengthBarFill.style.backgroundColor = color;
                strengthText.textContent = text;
                strengthText.style.color = color;
                passwordStrengthIcon.innerHTML = iconHtml;
            }

            function updateConfirmation() {
                const password = passwordInput.value;
                const confirm = confirmInput.value;

                if (password.length === 0 && confirm.length === 0) {
                    confirmIcon.innerHTML = '';
                    confirmError.textContent = '';
                    return;
                }

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

            passwordInput.addEventListener('input', function() {
                updatePasswordStrength();
                updateConfirmation();
            });
            confirmInput.addEventListener('input', updateConfirmation);

            // Initialisation
            updatePasswordStrength();
            updateConfirmation();
        }
    </script>
</body>

</html>