<?php
require_once 'includes/functions.php';

// Si déjà connecté, rediriger vers la page appropriée selon le profil
if (isset($_SESSION['user_id'])) {
    $profil = $_SESSION['user_profil'] ?? null;

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
    } else {
        header('Location: index.php');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE (login = ? OR nom_complet = ? OR email = ?) AND actif = true");
        $stmt->execute([$login, $login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
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

            // Charger les préférences si elles existent
            if (!empty($user['preferences'])) {
                $_SESSION['filtres'] = json_decode($user['preferences'], true);
            }

            // Déterminer l'URL de redirection selon le profil
            $redirectUrl = '';
            if ($user['profil'] === 'OPERATEUR') {
                $redirectUrl = !empty($user['preferences']) ? 'modules/controles/ajouter.php' : 'preferences.php';
            } elseif ($user['profil'] === 'ADMIN_IG') {
                $redirectUrl = 'index.php';
            } else {
                $redirectUrl = 'index.php';
            }

            // Afficher la page de succès avec redirection automatique
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion réussie - IG-FARDC</title>

    <!-- Font Awesome local -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --primary: #2e7d32;
        --primary-dark: #1b5e20;
        --success: #28a745;
        --gray: #6c757d;
    }

    body {
        font-family: 'Barlow', sans-serif;
        /* ... */
        background: linear-gradient(135deg, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.7) 100%),
            url('assets/img/fardc2.png') no-repeat center center fixed;
        /* ... */
        background-size: cover;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
        padding: 5px;
    }

    .success-wrapper {
        width: 100%;
        max-width: 450px;
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

    .login-logo {
        text-align: center;
        margin-bottom: 15px;
    }

    .login-logo .brand-link {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
    }

    .login-logo .brand-image {
        max-height: 50px;
        width: auto;
        margin-bottom: 5px;
        border: 2px solid white;
        border-radius: 50%;
        padding: 3px;
        background: rgba(255, 255, 255, 0.2);
    }

    .login-logo .brand-text {
        font-size: 1.4rem;
        font-weight: 700;
        color: white;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        letter-spacing: 0.3px;
    }

    .success-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        padding: 35px 25px;
        text-align: center;
    }

    .success-icon {
        width: 80px;
        height: 80px;
        background: var(--success);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        margin: 0 auto 20px;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
        }

        70% {
            box-shadow: 0 0 0 15px rgba(40, 167, 69, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
        }
    }

    .success-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-dark);
        margin-bottom: 10px;
    }

    .welcome-message {
        color: var(--gray);
        margin-bottom: 25px;
        font-size: 1.1rem;
        line-height: 1.5;
    }

    .welcome-message strong {
        color: var(--primary);
        font-weight: 600;
    }

    .progress-container {
        width: 100%;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        margin: 25px 0 15px;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), #ffc107);
        width: 100%;
        animation: progress 10s linear forwards;
    }

    @keyframes progress {
        from {
            transform: translateX(0);
        }

        to {
            transform: translateX(100%);
        }
    }

    .redirect-info {
        color: var(--gray);
        font-size: 1rem;
        margin: 20px 0 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .spinner {
        width: 22px;
        height: 22px;
        border: 3px solid rgba(46, 125, 50, 0.2);
        border-radius: 50%;
        border-top-color: var(--primary);
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .countdown {
        font-weight: 700;
        color: var(--primary);
        font-size: 1.2rem;
        background: #e8f5e9;
        padding: 2px 8px;
        border-radius: 20px;
        margin: 0 3px;
    }

    .manual-link {
        margin-top: 20px;
        font-size: 0.9rem;
    }

    .manual-link a {
        color: #ffc107;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
    }

    .manual-link a:hover {
        color: #e0a800;
        text-decoration: underline;
    }

    .login-footer {
        text-align: center;
        margin-top: 15px;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.75rem;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        padding-top: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .login-footer i {
        color: #ffc107;
        font-size: 0.7rem;
    }
    </style>
</head>

<body>
    <div class="success-wrapper">
        <div class="login-logo">
            <a href="index.php" class="brand-link">
                <img src="assets/img/logo-fardc.png" alt="FARDC Logo" class="brand-image"
                    onerror="this.style.display='none'">
                <span class="brand-text">IG-FARDC</span>
            </a>
        </div>

        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>

            <h2 class="success-title">Connexion effectuée !</h2>

            <div class="welcome-message">
                <i class="fas fa-user-circle" style="color: var(--primary); font-size: 1.2rem;"></i><br>
                Bonjour <strong><?= htmlspecialchars($user['nom_complet']) ?></strong>,<br>
                vous êtes maintenant connecté à votre espace.
            </div>

            <div class="progress-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>

            <div class="redirect-info">
                <div class="spinner"></div>
                <span>Redirection dans <span class="countdown" id="countdown">5</span> seconde(s)...</span>
            </div>

            <div class="manual-link">
                <i class="fas fa-arrow-right" style="color: #ffc107;"></i>
                <a href="<?= $redirectUrl ?>" id="manualRedirect">Cliquez ici</a> si la redirection ne fonctionne pas.
            </div>
        </div>

        <div class="login-footer">
            <i class="fas fa-shield-alt"></i>CTR.NET-FARDC v1.0
        </div>
    </div>

    <script>
    // Configuration
    let seconds = 5;
    const countdownEl = document.getElementById('countdown');
    const redirectUrl = '<?= $redirectUrl ?>';

    // Mise à jour du compte à rebours
    const interval = setInterval(() => {
        seconds--;
        countdownEl.textContent = seconds;

        if (seconds <= 0) {
            clearInterval(interval);
            window.location.href = redirectUrl;
        }
    }, 1000);

    // Redirection manuelle
    document.getElementById('manualRedirect').addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = redirectUrl;
    });

    // Animation supplémentaire pour la barre de progression
    const progressBar = document.getElementById('progressBar');
    progressBar.style.animation = 'progress 5s linear forwards';
    </script>
</body>

</html>
<?php
            exit;
        } else {
            $error = "Identifiant ou mot de passe incorrect.";
            audit_action('ECHEC_CONNEXION', null, null, "Tentative avec identifiant: $login");
        }
    } catch (PDOException $e) {
        error_log("Erreur SQL dans login.php : " . $e->getMessage());
        $error = "Erreur technique, veuillez réessayer plus tard.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion - IG-FARDC</title>

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
        --danger: #dc3545;
        --success: #28a745;
    }

    html,
    body {
        height: 100%;
        overflow: hidden;
    }

    body.login-page {
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

    .login-wrapper {
        width: 100%;
        max-width: 380px;
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

    .login-logo {
        text-align: center;
        margin-bottom: 8px;
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
        margin-bottom: 2px;
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
        letter-spacing: 0.3px;
        line-height: 1.2;
    }

    .card-modern {
        background: white;
        border: none;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        padding: 18px 16px 20px;
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
        font-size: 0.9rem;
        transition: all 0.2s;
        font-family: 'Barlow', sans-serif;
        height: 38px;
    }

    .input-group-modern .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.15);
        outline: none;
    }

    /* Positionnement du toggle mot de passe */
    .password-toggle {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: var(--gray);
        z-index: 11;
        font-size: 0.9rem;
        background: transparent;
        border: none;
        padding: 0;
    }

    .password-toggle:hover {
        color: var(--primary);
    }

    /* Options de connexion */
    .login-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 10px 0 16px;
        font-size: 0.8rem;
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

    /* Boutons */
    .btn-login,
    .btn-register {
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

    .btn-login {
        background: #ffc107;
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
        margin-top: 0;
    }

    .btn-register:hover {
        background: var(--primary);
        transform: translateY(-1px);
        color: white;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .separator {
        display: flex;
        align-items: center;
        text-align: center;
        margin: 16px 0;
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

    .alert-modern {
        border-radius: 5px;
        padding: 8px 12px;
        margin-bottom: 12px;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .alert-modern.error {
        background: #fee2e2;
        color: #991b1b;
        border-left: 3px solid var(--danger);
    }

    .login-footer {
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

    .login-footer i {
        color: #ffc107;
        font-size: 0.65rem;
    }
    </style>
</head>

<body class="login-page">
    <div class="login-wrapper">
        <div class="login-logo">
            <a href="" class="brand-link">
                <img src="assets/img/logo-fardc.png" alt="FARDC Logo" class="brand-image"
                    onerror="this.style.display='none'">
                <span class="brand-text">IG-FARDC</span>
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

            <?php if ($error): ?>
            <div class="alert-modern error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="post" id="loginForm">
                <!-- Champ identifiant avec icône -->
                <div class="input-group-modern">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="login" class="form-control" placeholder="Identifiant, email ou nom"
                        value="<?= isset($_POST['login']) ? htmlspecialchars($_POST['login']) : '' ?>" required
                        autofocus>
                </div>

                <!-- Champ mot de passe avec icône et toggle -->
                <div class="input-group-modern" style="position: relative;">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Mot de passe"
                        required>
                    <span class="password-toggle" onclick="togglePasswordVisibility()">
                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                    </span>
                </div>

                <div class="login-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Se souvenir de moi</span>
                    </label>
                    <a href="reset_password.php" class="forgot-link">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn-login" id="submitBtn">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>

            <div class="separator">ou</div>

            <a href="register.php" class="btn-register">
                <i class="fas fa-user-plus"></i> Créer nouveau compte
            </a>
        </div>

        <div class="login-footer">
            <i class="fas fa-shield-alt"></i> CTR.NET-FARDC v1.0
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script>
    // Désactiver le bouton après soumission
    $('#loginForm').on('submit', function() {
        $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Connexion...');
    });

    // Fonction pour afficher/masquer le mot de passe
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