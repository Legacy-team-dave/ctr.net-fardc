<?php
require_once 'includes/functions.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Récupérer les informations actuelles de l'utilisateur
try {
    $stmt = $pdo->prepare("SELECT login, nom_complet, email, avatar, mot_de_passe FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Erreur profil - récupération : " . $e->getMessage());
    $error = "Erreur technique, veuillez réessayer plus tard.";
}

// Configuration de l'upload d'avatar (identique à register.php)
$uploadDir = 'assets/uploads/avatars/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5 Mo

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_complet = trim($_POST['nom_complet'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $avatar_url  = trim($_POST['avatar'] ?? '');   // champ URL (optionnel)
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Gestion de l'avatar uploadé (prioritaire sur l'URL)
    $avatar_filename = $user['avatar']; // conserver l'ancien par défaut

    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar_file'];
        // Vérification des erreurs d'upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "Erreur lors de l'upload de l'avatar. Code : " . $file['error'];
        } elseif ($file['size'] > $maxSize) {
            $error = "L'avatar ne doit pas dépasser 5 Mo.";
        } else {
            // Vérification du type MIME réel
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowedTypes)) {
                $error = "Le fichier avatar doit être une image (JPEG, PNG, GIF ou WEBP).";
            } else {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFilename = uniqid('avatar_', true) . '.' . $extension;
                $destination = $uploadDir . $newFilename;

                // Créer le dossier s'il n'existe pas
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Supprimer l'ancien avatar si c'est un fichier local
                    if (!empty($user['avatar']) && file_exists($user['avatar']) && strpos($user['avatar'], $uploadDir) === 0) {
                        unlink($user['avatar']);
                    }
                    $avatar_filename = $destination;
                } else {
                    $error = "Impossible de sauvegarder l'avatar. Vérifiez les permissions du dossier.";
                }
            }
        }
    } elseif (!empty($avatar_url)) {
        // Si pas de fichier mais une URL saisie, on l'utilise
        $avatar_filename = $avatar_url;
    }

    // Validation simple
    if (empty($nom_complet) || empty($email)) {
        $error = "Le nom et l'email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'email n'est pas valide.";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!empty($new_password)) {
        // Vérification de l'ancien mot de passe
        if (empty($old_password)) {
            $error = "Veuillez saisir votre mot de passe actuel pour changer le mot de passe.";
        } elseif (!password_verify($old_password, $user['mot_de_passe'])) {
            $error = "L'ancien mot de passe est incorrect.";
        }
    }

    if (empty($error)) {
        try {
            // Vérifier que l'email n'est pas déjà utilisé par un autre compte
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = ? AND id_utilisateur != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = "Cet email est déjà utilisé par un autre compte.";
            } else {
                // Construction de la requête de mise à jour
                if (!empty($new_password)) {
                    // Hacher le nouveau mot de passe
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE utilisateurs SET nom_complet = ?, email = ?, avatar = ?, mot_de_passe = ? WHERE id_utilisateur = ?";
                    $params = [$nom_complet, $email, $avatar_filename, $hashed_password, $user_id];
                } else {
                    $sql = "UPDATE utilisateurs SET nom_complet = ?, email = ?, avatar = ? WHERE id_utilisateur = ?";
                    $params = [$nom_complet, $email, $avatar_filename, $user_id];
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Mettre à jour la session
                $_SESSION['user_nom'] = $nom_complet;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_avatar'] = $avatar_filename;

                // Journalisation
                log_action('MODIFICATION_PROFIL', 'utilisateurs', $user_id, 'Mise à jour du profil');

                $message = "Profil mis à jour avec succès.";
            }
        } catch (PDOException $e) {
            error_log("Erreur profil - mise à jour : " . $e->getMessage());
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
    <title>Mon profil - IG-FARDC</title>

    <link rel="stylesheet" href="assets/css/fonts.css">

    <!-- Font Awesome local -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <style>
        /* Police globale pour cette page */
        body {
            font-family: 'Barlow', sans-serif;
            font-size: 14px;
        }

        /* Style ultra-compact avec fond image et logo - adapté pour le profil */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --fb-blue: #ffc107;
            --fb-blue-hover: #e0a800;
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

        body.profile-page {
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

        .profile-wrapper {
            width: 100%;
            max-width: 500px;
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

        .profile-logo {
            text-align: center;
            margin-bottom: 8px;
        }

        .profile-logo .brand-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
        }

        .profile-logo .brand-image {
            max-height: 45px;
            width: auto;
            margin-bottom: 2px;
            border: 2px solid white;
            border-radius: 50%;
            padding: 2px;
            background: rgba(255, 255, 255, 0.2);
            transition: transform 0.5s ease;
        }

        .profile-logo .brand-link:hover .brand-image {
            transform: rotate(360deg);
        }

        .profile-logo .brand-text {
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
            padding: 12px 12px 14px;
            max-height: 550px;
            display: flex;
            flex-direction: column;
        }

        .card-modern .card-body {
            overflow-y: auto;
            padding-right: 4px;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) var(--light);
        }

        .card-modern .card-body::-webkit-scrollbar {
            width: 4px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 8px;
        }

        .form-header .title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0;
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

        /* Avatar preview cliquable */
        .avatar-preview {
            display: flex;
            justify-content: center;
            margin-bottom: 10px;
            position: relative;
            cursor: pointer;
        }

        .avatar-preview img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: opacity 0.2s;
        }

        .avatar-preview:hover img {
            opacity: 0.8;
        }

        .avatar-preview .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            color: white;
            font-size: 1.5rem;
            opacity: 0;
            transition: opacity 0.2s;
            pointer-events: none;
        }

        .avatar-preview:hover .overlay {
            opacity: 1;
        }

        /* Grille deux colonnes */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .input-group-modern {
            margin-bottom: 0;
            position: relative;
        }

        .input-group-modern .input-icon {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            z-index: 10;
            font-size: 0.75rem;
        }

        .input-group-modern .form-control {
            width: 100%;
            padding: 8px 8px 8px 28px;
            border: 1.5px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.8rem;
            transition: all 0.2s;
            font-family: 'Barlow', sans-serif;
            height: 36px;
        }

        .input-group-modern .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.15);
            outline: none;
        }

        .input-group-modern .form-control[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }

        .input-group-modern small {
            display: block;
            margin-top: 2px;
            color: var(--gray);
            font-size: 0.6rem;
            padding-left: 28px;
        }

        /* Validation pour mot de passe */
        .validation-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.8rem;
            pointer-events: none;
            z-index: 10;
        }

        .password-toggle {
            position: absolute;
            right: 30px;
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
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
            padding-left: 28px;
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
            min-width: 45px;
            font-size: 0.65rem;
            color: var(--gray);
            text-align: right;
        }

        .field-error {
            color: #dc3545;
            font-size: 0.65rem;
            margin-top: 2px;
            padding-left: 28px;
        }

        /* Boutons */
        .btn-primary,
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

        .btn-primary {
            background: #ffc107;
        }

        .btn-primary:hover {
            background: #e0a800;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-primary:disabled {
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
            padding: 6px 10px;
            margin-bottom: 8px;
            font-size: 0.75rem;
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

        .profile-footer {
            text-align: center;
            margin-top: 8px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.7rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }

        .profile-footer i {
            color: #ffc107;
            font-size: 0.65rem;
        }
    </style>
</head>

<body class="profile-page">
    <div class="profile-wrapper">
        <div class="profile-logo">
            <a href="index.php" class="brand-link">
                <img src="assets/img/logo-fardc.png" alt="FARDC Logo" class="brand-image"
                    onerror="this.style.display='none'">
                <span class="brand-text">Mon profil</span>
            </a>
        </div>

        <div class="card-modern">
            <div class="card-body">
                <!-- En-tête du formulaire -->
                <div class="form-header">
                    <div class="title">Informations personnelles</div>
                    <div class="subtitle">
                        <i class="fas fa-user-circle"></i>
                        <span>Modifiez vos données</span>
                    </div>
                </div>

                <!-- Messages d'alerte -->
                <?php if ($message): ?>
                    <div class="alert-modern success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert-modern error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($user)): ?>
                    <!-- Formulaire de modification avec upload d'image ET champ URL (original) -->
                    <form method="post" id="profileForm" enctype="multipart/form-data">
                        <!-- Aperçu de l'avatar cliquable -->
                        <div class="avatar-preview full-width" id="avatarClickArea">
                            <img src="<?= getAvatarUrl($user['avatar']) ?>" alt="Avatar" id="avatarPreview">
                            <div class="overlay">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <!-- Input file caché pour l'avatar -->
                        <input type="file" name="avatar_file" id="avatarFile"
                            accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">

                        <!-- Grille deux colonnes -->
                        <div class="form-grid">
                            <!-- Login (non modifiable) -->
                            <div class="input-group-modern">
                                <i class="fas fa-tag input-icon"></i>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['login']) ?>"
                                    readonly>
                                <small>(Login)</small>
                            </div>

                            <!-- Nom complet -->
                            <div class="input-group-modern">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" name="nom_complet" class="form-control" placeholder="Nom complet"
                                    value="<?= htmlspecialchars($user['nom_complet']) ?>" required>
                            </div>

                            <!-- Email -->
                            <div class="input-group-modern">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" name="email" class="form-control" placeholder="Email"
                                    value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>

                            <!-- Avatar (URL) - maintenant en deuxième colonne à côté de l'email -->
                            <div class="input-group-modern">
                                <i class="fas fa-image input-icon"></i>
                                <input type="text" name="avatar" id="avatarUrl" class="form-control"
                                    placeholder="URL de l'avatar" value="<?= htmlspecialchars($user['avatar'] ?: '') ?>">
                                <small>ou upload ci-dessus</small>
                            </div>

                            <!-- Ancien mot de passe (full width) -->
                            <div class="input-group-modern full-width" style="position: relative;">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="old_password" id="old_password" class="form-control"
                                    placeholder="Ancien mot de passe">
                                <span class="password-toggle" onclick="togglePasswordVisibility('old_password', this)">
                                    <i class="far fa-eye"></i>
                                </span>
                                <small>(requis pour changer le mot de passe)</small>
                            </div>

                            <!-- Nouveau mot de passe (colonne gauche) -->
                            <div class="input-group-modern" style="position: relative;">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="new_password" id="new_password" class="form-control"
                                    placeholder="Nouveau mot de passe">
                                <span class="password-toggle" onclick="togglePasswordVisibility('new_password', this)">
                                    <i class="far fa-eye"></i>
                                </span>
                                <span id="passwordStrengthIcon" class="validation-icon"></span>
                                <div class="password-strength">
                                    <span class="strength-bar">
                                        <span id="strengthBarFill" class="strength-bar-fill"></span>
                                    </span>
                                    <span id="strengthText" class="strength-text"></span>
                                </div>
                            </div>

                            <!-- Confirmation (colonne droite) -->
                            <div class="input-group-modern" style="position: relative;">
                                <i class="fas fa-check-circle input-icon"></i>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                                    placeholder="Confirmer le mot de passe">
                                <span class="password-toggle" onclick="togglePasswordVisibility('confirm_password', this)">
                                    <i class="far fa-eye"></i>
                                </span>
                                <span id="confirmIcon" class="validation-icon"></span>
                                <div id="confirmError" class="field-error"></div>
                            </div>
                        </div> <!-- fin form-grid -->

                        <!-- Bouton d'enregistrement -->
                        <button type="submit" class="btn-primary full-width" id="submitBtn">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </form>

                    <!-- Bouton retour -->
                    <a href="index.php" class="btn-secondary full-width">
                        <i class="fas fa-arrow-left"></i> Retour à l'accueil
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="profile-footer">
            <i class="fas fa-shield-alt"></i>
            <span>IG - FARDC v1.0</span>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script>
        // Désactiver le bouton après soumission
        $('#profileForm').on('submit', function() {
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enregistrement...');
        });

        // Clic sur l'avatar pour déclencher l'upload
        $('#avatarClickArea').on('click', function() {
            $('#avatarFile').click();
        });

        // Mise à jour de l'aperçu et du champ URL après sélection d'un fichier
        $('#avatarFile').on('change', function() {
            const file = this.files[0];
            if (file) {
                // Afficher le nom du fichier dans le champ URL (pour information)
                $('#avatarUrl').val(file.name);

                // Aperçu de l'image
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#avatarPreview').attr('src', e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });

        // Mise à jour de l'aperçu en temps réel si l'URL est modifiée
        $('#avatarUrl').on('input', function() {
            var url = $(this).val();
            if (url) {
                $('#avatarPreview').attr('src', url);
            } else {
                // Revenir à l'avatar actuel (stocké)
                $('#avatarPreview').attr('src', '<?= getAvatarUrl($user['avatar']) ?>');
            }
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

        // Validation en temps réel du mot de passe (harmonisée avec register.php)
        const passwordInput = document.getElementById('new_password');
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
                color = '#dc3545'; // rouge
                iconHtml = '<i class="fas fa-exclamation-triangle" style="color:#dc3545;"></i>';
            } else if (strength <= 3) {
                fillPercent = 50;
                text = 'Moyen';
                color = '#f7b731'; // orange
                iconHtml = '<i class="fas fa-exclamation-circle" style="color:#f7b731;"></i>';
            } else if (strength <= 4) {
                fillPercent = 75;
                text = 'Bon';
                color = '#17a2b8'; // bleu
                iconHtml = '<i class="fas fa-check-circle" style="color:#17a2b8;"></i>';
            } else {
                fillPercent = 100;
                text = 'Fort';
                color = '#28a745'; // vert
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

        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                updatePasswordStrength();
                updateConfirmation();
            });
        }
        if (confirmInput) {
            confirmInput.addEventListener('input', updateConfirmation);
        }

        // Initialisation
        updatePasswordStrength();
        updateConfirmation();
    </script>
</body>

</html>