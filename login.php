<?php
require_once 'includes/functions.php';

$cookieSecure = is_https_request();

// Vérification du cookie "remember_token"
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE remember_token = ? AND remember_token_expires > NOW() AND actif = true");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // En mode central, seul ADMIN_IG est autorisé
            if (is_central_mode() && strtoupper(trim((string)$user['profil'])) !== 'ADMIN_IG') {
                setcookie('remember_token', '', time() - 3600, '/', '', $cookieSecure, true);
            } elseif (is_mobile_only_profile($user['profil'])) {
                setcookie('remember_token', '', time() - 3600, '/', '', $cookieSecure, true);
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id_utilisateur'];
                $_SESSION['user_login'] = $user['login'];
                $_SESSION['user_nom'] = $user['nom_complet'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_profil'] = $user['profil'];
                $_SESSION['user_avatar'] = $user['avatar'];

                if (!empty($user['preferences'])) {
                    $_SESSION['filtres'] = json_decode($user['preferences'], true);
                }

                if ($user['profil'] === 'OPERATEUR') {
                    $redirectUrl = !empty($user['preferences']) ? 'modules/controles/ajouter.php' : 'preferences.php';
                } elseif ($user['profil'] === 'ADMIN_IG') {
                    $redirectUrl = 'index.php';
                } else {
                    $redirectUrl = 'index.php';
                }
                header('Location: ' . $redirectUrl);
                exit;
            }
        } else {
            setcookie('remember_token', '', time() - 3600, '/', '', $cookieSecure, true);
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du token : " . $e->getMessage());
    }
}

// Si déjà connecté ET qu'on n'est PAS en mode succès (paramètre success absent), on redirige immédiatement
if (isset($_SESSION['user_id']) && !isset($_GET['success'])) {
    $profil = $_SESSION['user_profil'] ?? null;

    if (is_central_mode() && strtoupper(trim((string)$profil)) !== 'ADMIN_IG') {
        //session_destroy();
        header('Location: login.php');
        exit;
    }

    if ($profil === 'OPERATEUR') {
        try {
            $stmt = $pdo->prepare("SELECT preferences FROM utilisateurs WHERE id_utilisateur = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $preferences = $stmt->fetchColumn();

            if ($preferences) {
                $_SESSION['filtres'] = json_decode($preferences, true);
                header('Location: modules/controles/ajouter.php');
            } else {
                header('Location: preferences.php');
            }
        } catch (PDOException $e) {
            header('Location: modules/controles/ajouter.php');
        }
        exit;
    } elseif ($profil === 'ADMIN_IG') {
        header('Location: index.php');
        exit;
    } elseif (is_mobile_only_profile($profil)) {
        // CONTROLEUR / ENROLEUR réservés au mobile — forcer déconnexion web
        session_destroy();
        header('Location: login.php');
        exit;
    } else {
        header('Location: index.php');
        exit;
    }
}

$error = '';
$login_success = false;
$redirect_url = null;
$flash_message = $_SESSION['flash_message'] ?? null;
if ($flash_message) {
    unset($_SESSION['flash_message']);
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    $rateLimit = check_login_rate_limit('web');
    if (!$rateLimit['allowed']) {
        $retryMinutes = max(1, (int) ceil(($rateLimit['retry_after'] ?? 0) / 60));
        $error = "Trop de tentatives détectées. Réessayez dans {$retryMinutes} minute(s).";
        audit_action('BLOCAGE_SECURITE', null, null, 'Connexion web temporairement bloquée pour IP ' . get_client_ip_address());
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE (login = ? OR nom_complet = ? OR email = ?) AND actif = true");
            $stmt->execute([$login, $login, $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['mot_de_passe'])) {
                // En mode central, seul ADMIN_IG est autorisé
                if (is_central_mode() && strtoupper(trim((string)$user['profil'])) !== 'ADMIN_IG') {
                    audit_action('ECHEC_CONNEXION', 'utilisateurs', $user['id_utilisateur'], 'Tentative connexion hors ADMIN_IG en mode central');
                    $failureState = register_login_failure('web', $login);
                    $error = ($failureState['retry_after'] ?? 0) > 0
                        ? 'Trop de tentatives détectées. Réessayez dans ' . max(1, (int) ceil(($failureState['retry_after'] ?? 0) / 60)) . ' minute(s).'
                        : 'La plateforme centrale est réservée au profil ADMIN_IG.';
                } elseif (is_mobile_only_profile($user['profil'])) {
                    audit_action('ECHEC_CONNEXION', 'utilisateurs', $user['id_utilisateur'], 'Tentative connexion web profil mobile réservé');
                    $failureState = register_login_failure('web', $login);
                    $error = ($failureState['retry_after'] ?? 0) > 0
                        ? 'Trop de tentatives détectées. Réessayez dans ' . max(1, (int) ceil(($failureState['retry_after'] ?? 0) / 60)) . ' minute(s).'
                        : 'Les profils CONTROLEUR et ENROLEUR sont réservés aux applications mobiles.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id_utilisateur'];
                    $_SESSION['user_login'] = $user['login'];
                    $_SESSION['user_nom'] = $user['nom_complet'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_profil'] = $user['profil'];
                    $_SESSION['user_avatar'] = $user['avatar'];

                    $updateStmt = $pdo->prepare("UPDATE utilisateurs SET dernier_acces = NOW() WHERE id_utilisateur = ?");
                    $updateStmt->execute([$user['id_utilisateur']]);

                    audit_action('CONNEXION', 'utilisateurs', $user['id_utilisateur'], 'Connexion réussie');
                    clear_login_rate_limit('web');

                    if (!empty($user['preferences'])) {
                        $_SESSION['filtres'] = json_decode($user['preferences'], true);
                    }

                    // Gestion du "Se souvenir de moi"
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                        $stmtToken = $pdo->prepare("UPDATE utilisateurs SET remember_token = ?, remember_token_expires = ? WHERE id_utilisateur = ?");
                        $stmtToken->execute([$token, $expires, $user['id_utilisateur']]);
                        setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/', '', $cookieSecure, true);
                    } else {
                        $stmtToken = $pdo->prepare("UPDATE utilisateurs SET remember_token = NULL, remember_token_expires = NULL WHERE id_utilisateur = ?");
                        $stmtToken->execute([$user['id_utilisateur']]);
                        setcookie('remember_token', '', time() - 3600, '/', '', $cookieSecure, true);
                    }

                    // Déterminer l'URL de redirection finale
                    if ($user['profil'] === 'OPERATEUR') {
                        $redirect_url = !empty($user['preferences']) ? 'modules/controles/ajouter.php' : 'preferences.php';
                    } elseif ($user['profil'] === 'ADMIN_IG') {
                        $redirect_url = 'index.php';
                    } else {
                        $redirect_url = 'index.php';
                    }

                    // Indiquer que la connexion est réussie et conserver les valeurs saisies pour affichage
                    $login_success = true;
                    // On garde les valeurs postées pour pré-remplir le formulaire
                    $_SESSION['post_data'] = [
                        'login' => $login,
                        'remember' => $remember
                    ];
                } // fin else CONTROLEUR
            } else {
                $failureState = register_login_failure('web', $login);
                $error = ($failureState['retry_after'] ?? 0) > 0
                    ? 'Trop de tentatives détectées. Réessayez dans ' . max(1, (int) ceil(($failureState['retry_after'] ?? 0) / 60)) . ' minute(s).'
                    : 'Identifiant ou mot de passe incorrect.';
                audit_action('ECHEC_CONNEXION', null, null, "Tentative avec identifiant: $login");
                // En cas d'erreur, on garde aussi les valeurs pour les réafficher
                $_SESSION['post_data'] = [
                    'login' => $login,
                    'remember' => $remember
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur SQL dans login.php : " . $e->getMessage());
            $error = "Erreur technique, veuillez réessayer plus tard.";
        }
    }
}

// Récupérer les valeurs à afficher dans le formulaire (priorité : POST > session > vide)
$display_login = '';
$display_remember = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_login = htmlspecialchars($_POST['login'] ?? '');
    $display_remember = isset($_POST['remember']);
} elseif (isset($_SESSION['post_data'])) {
    $display_login = htmlspecialchars($_SESSION['post_data']['login'] ?? '');
    $display_remember = $_SESSION['post_data']['remember'] ?? false;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Connexion - IG-FARDC</title>
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <style>
        /* Reset & variables */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --fb-blue: #1877f2;
            --primary: #2e7d32;
            --primary-dark: #1b5e20;
            --gray: #6c757d;
            --danger: #dc3545;
            --success: #28a745;
            --light-bg: #f8f9fa;
            --card-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        body.login-page {
            font-family: 'Barlow', sans-serif;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.7) 100%),
                url('assets/img/fardc2.png') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 16px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 380px;
            animation: fadeInUp 0.3s ease-out;
        }

        /* DESIGN ORDINATEUR (>= 768px) */
        @media (min-width: 768px) {
            body.login-page {
                padding: 24px;
            }

            .login-wrapper {
                max-width: 480px;
            }

            .card-modern {
                padding: 32px 28px !important;
                border-radius: 16px !important;
                box-shadow: var(--card-shadow) !important;
            }

            .form-header .title {
                font-size: 1.8rem !important;
                margin-bottom: 8px !important;
            }

            .form-header .subtitle {
                font-size: 0.9rem !important;
            }

            .input-group-modern .form-control {
                padding: 12px 12px 12px 40px !important;
                font-size: 1rem !important;
            }

            .input-group-modern .input-icon {
                font-size: 1rem !important;
                left: 14px !important;
            }

            .password-toggle {
                font-size: 1rem !important;
                right: 14px !important;
            }

            .btn-login,
            .btn-register {
                padding: 12px !important;
                font-size: 1rem !important;
            }

            .login-options {
                margin: 16px 0 24px !important;
            }

            .separator {
                margin: 24px 0 !important;
            }
        }

        /* DESIGN MOBILE/TABLETTE (<= 767px) */
        @media (max-width: 767px) {
            .login-wrapper {
                max-width: 100%;
            }

            .card-modern {
                padding: 18px 16px;
                border-radius: 8px;
            }

            .form-header .title {
                font-size: 1.3rem;
            }

            .input-group-modern .form-control {
                padding: 9px 12px 9px 36px;
                font-size: 0.9rem;
            }

            .btn-login,
            .btn-register {
                padding: 10px;
                font-size: 0.9rem;
            }
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

        .login-logo {
            text-align: center;
            margin-bottom: 16px;
        }

        .login-logo .brand-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
        }

        .login-logo .brand-image {
            max-height: 45px;
            width: auto;
            margin-bottom: 5px;
            border: 2px solid white;
            border-radius: 50%;
            padding: 2px;
            background: rgba(255, 255, 255, 0.2);
            transition: transform 0.5s ease;
        }

        .login-logo .brand-link:hover .brand-image {
            transform: rotate(360deg);
        }

        .login-logo .brand-text {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }

        .card-modern {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            padding: 20px 18px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 16px;
        }

        .form-header .title {
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 4px;
        }

        .form-header .subtitle {
            font-size: 0.75rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .input-group-modern {
            margin-bottom: 14px;
            position: relative;
        }

        .input-group-modern .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 0.9rem;
            z-index: 2;
        }

        .input-group-modern .form-control {
            width: 100%;
            border: 1.5px solid #e0e0e0;
            border-radius: 6px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .input-group-modern .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.15);
            outline: none;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray);
            background: transparent;
            border: none;
            font-size: 0.9rem;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 12px 0 18px;
            font-size: 0.8rem;
            flex-wrap: wrap;
            gap: 8px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #606770;
            cursor: pointer;
        }

        .remember-me input {
            width: 14px;
            height: 14px;
            accent-color: var(--fb-blue);
        }

        .forgot-link {
            color: var(--fb-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .btn-login,
        .btn-register {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            font-family: inherit;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 10px;
            font-size: 0.9rem;
        }

        .btn-login {
            background: #ffc107;
            color: #212529;
        }

        .btn-login:hover {
            background: #e0a800;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-login:disabled {
            background: #9cb4d8;
            cursor: not-allowed;
            transform: none;
        }

        .btn-register {
            background: var(--primary-dark);
            color: white;
            margin-top: 8px;
        }

        .btn-register:hover {
            background: var(--primary);
            transform: translateY(-1px);
            color: white;
        }

        .separator {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 18px 0;
            color: #606770;
            font-size: 0.8rem;
        }

        .separator::before,
        .separator::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dadde1;
        }

        .separator::before {
            margin-right: 12px;
        }

        .separator::after {
            margin-left: 12px;
        }

        /* ========= STYLES TOAST ========= */
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
            animation: slideIn 0.3s ease-out, fadeOut 0.5s ease-out 5s forwards;
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

        /* ========================================================= */

        .login-footer {
            text-align: center;
            margin-top: 16px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.7rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 10px;
        }

        @media (max-width: 480px) {
            body.login-page {
                padding: 10px;
            }

            .card-modern {
                padding: 16px;
            }

            .form-header .title {
                font-size: 1.2rem;
            }

            .login-options {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 16px;
            }

            .toast-message {
                min-width: 260px;
                font-size: 0.9rem;
                padding: 12px 16px;
            }
        }
    </style>
</head>

<body class="login-page">
    <!-- Conteneur pour les toasts -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="login-wrapper">
        <div class="login-logo">
            <a href="" class="brand-link">
                <img src="assets/img/logo-fardc.png" alt="FARDC Logo" class="brand-image"
                    onerror="this.style.display='none'">
                <span class="brand-text">CTR.NET - FARDC</span>
            </a>
        </div>

        <div class="card-modern">
            <div class="form-header">
                <div class="title">Connexion</div>
                <div class="subtitle">
                    <i class="fas fa-lock"></i>
                    <span>Accédez à votre espace</span>
                </div>
            </div>

            <form method="post" id="loginForm">
                <div class="input-group-modern">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="login" class="form-control" placeholder="Identifiant, email ou nom"
                        value="<?= $display_login ?>" required autofocus <?= $login_success ? 'disabled' : '' ?>>
                </div>

                <div class="input-group-modern">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Mot de passe"
                        required <?= $login_success ? 'disabled' : '' ?>>
                    <span class="password-toggle" onclick="togglePasswordVisibility()">
                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                    </span>
                </div>

                <div class="login-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" <?= $display_remember ? 'checked' : '' ?>
                            <?= $login_success ? 'disabled' : '' ?>>
                        <span>Se souvenir de moi</span>
                    </label>
                    <a href="reset_password.php" class="forgot-link">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn-login" id="submitBtn" <?= $login_success ? 'disabled' : '' ?>>
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>

            <div class="separator">ou</div>

            <a href="register.php" class="btn-register">
                <i class="fas fa-user-plus"></i> Créer nouveau compte
            </a>
        </div>

        <div class="login-footer">
            <i class="fas fa-shield-alt"></i> IG-FARDC @ 2026
        </div>
    </div>

    <?php if ($login_success && $redirect_url):
        $user_nom = htmlspecialchars($_SESSION['user_nom'] ?? '');
        if (!empty($user_nom)) {
            $toast_message = "Bienvenue <strong>$user_nom</strong> ! Redirection en cours...";
        } else {
            $toast_message = "Connexion réussie ! Redirection en cours...";
        }
    ?>
        <script>
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

                // Disparition après 5 secondes
                setTimeout(() => {
                    toast.remove();
                }, 5000);
            }

            // Afficher le toast de succès
            showToast('<?= addslashes($toast_message) ?>', 'success');

            // Désactiver le bouton et afficher le spinner
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion...';
                submitBtn.disabled = true;
            }

            // Redirection après 5 secondes
            setTimeout(() => {
                window.location.href = '<?= addslashes($redirect_url) ?>';
            }, 5000);
        </script>
    <?php endif; ?>

    <?php if (!empty($flash_message['text'])):
        $flash_type = strtolower((string)($flash_message['type'] ?? 'info'));
        if ($flash_type === 'danger') {
            $flash_type = 'error';
        } elseif ($flash_type !== 'success' && $flash_type !== 'warning') {
            $flash_type = 'warning';
        }
    ?>
        <script>
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
                }, 5000);
            }

            showToast('<?= addslashes($flash_message['text']) ?>', '<?= $flash_type ?>');
        </script>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <script>
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

                // Disparition après 5 secondes
                setTimeout(() => {
                    toast.remove();
                }, 5000);
            }

            // Afficher le toast d'erreur (rouge)
            showToast('<?= addslashes($error) ?>', 'error');
        </script>
    <?php endif; ?>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script>
        $('#loginForm').on('submit', function() {
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Connexion...');
        });

        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>