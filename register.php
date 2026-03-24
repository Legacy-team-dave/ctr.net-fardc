<?php
require_once 'includes/functions.php';
/* check_profil(['ADMIN_IG']); */ // Seuls les ADMIN_IG peuvent accéder à cette page
$error = null;

// Profils disponibles (fixes)
$profils = [
    'ADMIN_IG'      => 'ADMIN_IG',
    'OPERATEUR'     => 'OPERATEUR'
];

// Configuration de l'upload d'avatar
$uploadDir = 'assets/uploads/avatars/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5 Mo

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login       = trim($_POST['login'] ?? '');
    $nom_complet = trim($_POST['nom_complet'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    $profil      = $_POST['profil'] ?? '';
    $avatarPath  = null;

    // Validations de base
    if (empty($login) || empty($nom_complet) || empty($email) || empty($password) || empty($profil)) {
        $error = "Tous les champs sauf avatar sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'email n'est pas valide.";
    } elseif (strlen($password) < 4) {
        $error = "Le mot de passe doit contenir au moins 4 caractères.";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!array_key_exists($profil, $profils)) {
        $error = "Le profil sélectionné n'est pas valide.";
    } else {
        // Traitement de l'avatar
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['avatar'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = "Erreur lors de l'upload de l'avatar. Code : " . $file['error'];
            } elseif ($file['size'] > $maxSize) {
                $error = "L'avatar ne doit pas dépasser 5 Mo.";
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowedTypes)) {
                    $error = "Le fichier avatar doit être une image (JPEG, PNG, GIF ou WEBP).";
                } else {
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $newFilename = uniqid('avatar_', true) . '.' . $extension;
                    $destination = $uploadDir . $newFilename;
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $avatarPath = $destination;
                    } else {
                        $error = "Impossible de sauvegarder l'avatar. Vérifiez les permissions du dossier.";
                    }
                }
            }
        }

        if (!$error) {
            try {
                // Vérification unicité login/email
                $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE login = ? OR email = ?");
                $stmt->execute([$login, $email]);
                if ($stmt->fetch()) {
                    $error = "Ce login ou cet email est déjà utilisé.";
                } else {
                    // Hachage sécurisé du mot de passe
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // Insertion avec actif = 0 (compte inactif en attente d'activation)
                    $stmt = $pdo->prepare("INSERT INTO utilisateurs 
                        (login, nom_complet, email, mot_de_passe, avatar, profil, actif, reset_token, reset_expires, dernier_acces, preferences, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 0, NULL, NULL, NULL, NULL, NOW())");
                    $stmt->execute([$login, $nom_complet, $email, $hashedPassword, $avatarPath, $profil]);

                    $newUserId = $pdo->lastInsertId();

                    // --- AJOUT LOG : inscription d'un nouvel utilisateur ---
                    audit_action('INSCRIPTION', 'utilisateurs', $newUserId, "Nouvel utilisateur inscrit : $login (profil : $profil)");
                    // --- FIN LOG ---

                    $_SESSION['flash_message'] = [
                        'type' => 'success',
                        'text' => 'Inscription réussie. Votre compte est en attente d\'activation par un administrateur. Vous pourrez vous connecter une fois activé.'
                    ];
                    header('Location: login.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log("Erreur register.php : " . $e->getMessage());
                $error = "Erreur technique, veuillez réessayer plus tard.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscription - IG-FARDC</title>

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
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        body.register-page {
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

        .register-wrapper {
            width: 100%;
            max-width: 340px;
            /* encore plus compact */
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

        .register-logo {
            text-align: center;
            margin-bottom: 8px;
            flex-shrink: 0;
        }

        .register-logo .brand-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
        }

        .register-logo .brand-image {
            max-height: 40px;
            /* logo plus petit */
            width: auto;
            margin-bottom: 2px;
            border: 2px solid white;
            border-radius: 50%;
            padding: 2px;
            background: rgba(255, 255, 255, 0.2);
            transition: transform 0.5s ease;
        }

        .register-logo .brand-link:hover .brand-image {
            transform: rotate(360deg);
        }

        .register-logo .brand-text {
            font-size: 1.2rem;
            /* réduit */
            font-weight: 700;
            color: white;
            /* blanc pour contraster sur fond sombre */
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            letter-spacing: 0.3px;
            line-height: 1.2;
        }

        .card-modern {
            background: white;
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            padding: 12px 10px 14px;
            /* encore plus compact */
        }

        .form-header {
            text-align: center;
            margin-bottom: 8px;
        }

        .form-header .title {
            font-size: 1.3rem;
            /* réduit */
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0;
            line-height: 1.2;
        }

        .form-header .subtitle {
            font-size: 0.7rem;
            /* plus petit */
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }

        .input-group-modern {
            margin-bottom: 8px;
            /* réduit */
            position: relative;
        }

        .input-group-modern .form-control,
        .input-group-modern select {
            width: 100%;
            padding: 8px 10px;
            /* moins de padding */
            border: 1.5px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.8rem;
            /* plus petit */
            transition: all 0.2s;
            font-family: 'Barlow', sans-serif;
            background: white;
        }

        .input-group-modern .form-control:focus,
        .input-group-modern select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.15);
            outline: none;
        }

        /* Champ fichier */
        .input-group-modern input[type="file"] {
            padding: 5px 10px;
            font-size: 0.75rem;
        }

        /* Icônes de validation */
        .validation-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.8rem;
            pointer-events: none;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 2;
            font-size: 0.8rem;
            background: transparent;
            padding: 0;
        }

        /* Barre de force du mot de passe ultra-compacte */
        .password-strength {
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .strength-bar {
            flex: 1;
            height: 3px;
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
            /* tout petit */
            color: #6c757d;
            text-align: right;
        }

        .field-error {
            color: #dc3545;
            font-size: 0.65rem;
            margin-top: 1px;
            padding-left: 2px;
        }

        /* Boutons avec icônes - compacts */
        .btn-register,
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            border: none;
            border-radius: 5px;
            padding: 8px 10px;
            /* réduit */
            font-weight: 600;
            color: white;
            width: 100%;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            font-family: 'Barlow', sans-serif;
            text-decoration: none;
            font-size: 0.85rem;
            /* plus petit */
            margin-top: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-register {
            background: #1b5e20;
        }

        .btn-register:hover {
            background: #2e7d32;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-register:disabled {
            background: #9cb4d8;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #60757d;
            margin-top: 10px;
            /* légèrement plus d'espace */
        }

        .btn-secondary:hover {
            background: #6c757d;
            transform: translateY(-1px);
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .alert-modern {
            border-radius: 5px;
            padding: 6px 10px;
            margin-bottom: 8px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .alert-modern.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 3px solid #dc3545;
        }

        .alert-modern.success {
            background: #d4edda;
            color: #155724;
            border-left: 3px solid #28a745;
        }

        .register-footer {
            text-align: center;
            margin-top: 8px;
            color: rgba(255, 255, 255, 0.8);
            /* blanc pour fond sombre */
            font-size: 0.65rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }

        .register-footer i {
            color: #ffc107;
            font-size: 0.6rem;
        }

        /* Petit texte sous le champ fichier */
        .input-group-modern small {
            display: block;
            margin-top: 2px;
            color: #6c757d;
            font-size: 0.6rem;
            padding-left: 2px;
        }
    </style>
</head>

<body class="register-page">
    <div class="register-wrapper">
        <div class="register-logo">
            <a href="index.php" class="brand-link">
                <img src="assets/img/logo-fardc.png" alt="FARDC Logo" class="brand-image"
                    onerror="this.style.display='none'">
                <span class="brand-text">IG-FARDC</span>
            </a>
        </div>

        <div class="card-modern">
            <div class="form-header">
                <div class="title">Inscription</div>
                <div class="subtitle">
                    <i class="fas fa-user-plus"></i>
                    <span>Rejoindre le système</span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert-modern error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" id="registerForm" enctype="multipart/form-data">
                <div class="input-group-modern">
                    <input type="text" name="login" id="login" class="form-control" placeholder="Nom d'utilisateur"
                        value="<?= isset($_POST['login']) ? htmlspecialchars($_POST['login']) : '' ?>" required>
                </div>

                <div class="input-group-modern">
                    <input type="text" name="nom_complet" id="nom_complet" class="form-control"
                        placeholder="Nom complet"
                        value="<?= isset($_POST['nom_complet']) ? htmlspecialchars($_POST['nom_complet']) : '' ?>"
                        required>
                </div>

                <div class="input-group-modern">
                    <input type="email" name="email" id="email" class="form-control" placeholder="Adresse email"
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                </div>

                <div class="input-group-modern">
                    <select name="profil" id="profil" class="form-control" required>
                        <option value="">Sélectionner un profil</option>
                        <?php foreach ($profils as $code => $libelle): ?>
                            <option value="<?= htmlspecialchars($code) ?>"
                                <?= (isset($_POST['profil']) && $_POST['profil'] == $code) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($libelle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group-modern" style="position: relative;">
                    <input type="password" name="password" id="password" class="form-control"
                        placeholder="Mot de passe (min. 4)" required>
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
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                        placeholder="Confirmer le mot de passe" required>
                    <span class="password-toggle" onclick="togglePasswordVisibility('confirm_password', this)">
                        <i class="far fa-eye"></i>
                    </span>
                    <span id="confirmIcon" class="validation-icon"></span>
                    <div id="confirmError" class="field-error"></div>
                </div>

                <div class="input-group-modern">
                    <input type="file" name="avatar" id="avatar" class="form-control"
                        accept="image/jpeg,image/png,image/gif,image/webp">
                    <small>Optionnel - 5 Mo max (JPG, PNG, GIF, WEBP)</small>
                </div>

                <button type="submit" class="btn-register" id="submitBtn">
                    <i class="fas fa-user-plus"></i> S'inscrire
                </button>
            </form>

            <a href="login.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la connexion
            </a>
        </div>

        <div class="register-footer">
            <i class="fas fa-shield-alt"></i>CTR.NET-FARDC v1.1.0
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script>
        $('#registerForm').on('submit', function() {
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Inscription...');
        });

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

        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const confirmIcon = document.getElementById('confirmIcon');
        const confirmError = document.getElementById('confirmError');
        const strengthBarFill = document.getElementById('strengthBarFill');
        const strengthText = document.getElementById('strengthText');
        const passwordStrengthIcon = document.getElementById('passwordStrengthIcon');

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

        updatePasswordStrength();
        updateConfirmation();
    </script>
</body>

</html>