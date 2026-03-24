<?php
if (session_status() === PHP_SESSION_NONE) {
    // Configuration des cookies de session pour éviter les problèmes de redirection
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => false,   // passer à true si HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_nom = $_SESSION['user_nom'] ?? 'Utilisateur';
$user_profil = $_SESSION['user_profil'] ?? '';
$user_avatar = $_SESSION['user_avatar'] ?? null;
$current_script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$global_toast_excluded_pages = [
    '/ctr.net-fardc/modules/controles/ajouter.php',
    '/ctr.net-fardc/modules/litige/ajouter.php'
];
$disable_global_access_toast = in_array($current_script_path, $global_toast_excluded_pages, true);

// Construction de l'URL de l'avatar
$avatarUrl = '/ctr.net-fardc/assets/uploads/avatars/default-avatar.jpg';
if (!empty($user_avatar)) {
    if (strpos($user_avatar, '/') === 0) {
        $avatarUrl = $user_avatar;
    } else {
        $avatarUrl = '/ctr.net-fardc/' . ltrim($user_avatar, '/');
    }
}

// ------------------------------------------------------------
// FONCTIONS UTILITAIRES POUR LA GESTION DES ACCÈS
// ------------------------------------------------------------

/**
 * Vérifie si l'utilisateur courant a au moins un des rôles requis.
 * Si non, redirige vers la page précédente avec un message d'erreur.
 * À appeler au début de chaque page protégée.
 *
 * @param array|string $roles_requis Un ou plusieurs rôles autorisés
 * @param string $message_erreur Message personnalisé (optionnel)
 * @return void
 */
function verifier_acces($roles_requis, $message_erreur = null)
{
    global $user_profil;
    if (!is_array($roles_requis)) {
        $roles_requis = [$roles_requis];
    }
    if (!in_array($user_profil, $roles_requis)) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => $message_erreur ?? 'Accès non autorisé : vous n\'avez pas les droits nécessaires pour cette page.'
        ];
        $referer = $_SERVER['HTTP_REFERER'] ?? '/ctr.net-fardc/index.php';
        header('Location: ' . $referer);
        exit;
    }
}

/**
 * Génère un lien HTML avec l'attribut data-required-role pour la protection côté client.
 *
 * @param string $url URL du lien
 * @param string $texte Texte du lien
 * @param array|string $roles_requis Rôle(s) autorisé(s) pour accéder à cette page
 * @param array $attributs Attributs HTML supplémentaires (ex: class, id)
 * @return string
 */
function lien_protege($url, $texte, $roles_requis, $attributs = [])
{
    $roles = is_array($roles_requis) ? implode(',', $roles_requis) : $roles_requis;
    $attr_str = '';
    foreach ($attributs as $key => $value) {
        $attr_str .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
    }
    return '<a href="' . htmlspecialchars($url) . '" data-required-role="' . htmlspecialchars($roles) . '"' . $attr_str . '>' . htmlspecialchars($texte) . '</a>';
}

// ------------------------------------------------------------
// DÉFINITION DU MENU AVEC RÔLES
// ------------------------------------------------------------
$menuItems = [
    [
        'title' => 'Tableau de bord',
        'icon'  => 'fas fa-tachometer-alt',
        'url'   => '/ctr.net-fardc/index.php',
        'roles' => ['ADMIN_IG', 'OPERATEUR']
    ],
    [
        'title' => 'Administration',
        'icon'  => 'fas fa-cogs',
        'roles' => ['ADMIN_IG'],
        'submenu' => [
            ['title' => 'Gestion des utilisateurs', 'url' => '/ctr.net-fardc/modules/administration/liste.php', 'icon' => 'fas fa-user-cog', 'roles' => ['ADMIN_IG']],
            ['title' => 'Logs',                      'url' => '/ctr.net-fardc/modules/administration/logs.php',   'icon' => 'fas fa-history', 'roles' => ['ADMIN_IG']]
        ]
    ],
    [
        'title' => 'Rapports',
        'icon'  => 'fas fa-chart-pie',
        'roles' => ['ADMIN_IG'],
        'submenu' => [
            ['title' => 'Rapports généraux', 'url' => '/ctr.net-fardc/modules/rapports/index.php',       'icon' => 'fas fa-chart-pie', 'roles' => ['ADMIN_IG']],
            ['title' => 'Statistiques',      'url' => '/ctr.net-fardc/modules/rapports/statistiques.php', 'icon' => 'fas fa-chart-bar', 'roles' => ['ADMIN_IG']]
        ]
    ],
    [
        'title' => 'Militaires',
        'icon'  => 'fas fa-user-shield',
        'roles' => ['ADMIN_IG'],
        'submenu' => [
            ['title' => 'Liste des militaires', 'url' => '/ctr.net-fardc/modules/militaires/liste.php',   'icon' => 'fas fa-list', 'roles' => ['ADMIN_IG']],
            ['title' => 'Militaires actifs',    'url' => '/ctr.net-fardc/modules/militaires/actifs.php',  'icon' => 'fas fa-user-check', 'roles' => ['ADMIN_IG']],
            ['title' => 'Militaires inactifs',  'url' => '/ctr.net-fardc/modules/militaires/inactifs.php', 'icon' => 'fas fa-user-times', 'roles' => ['ADMIN_IG']]
        ]
    ],
    [
        'title' => 'Contrôles',
        'icon'  => 'fas fa-clipboard-list',
        'roles' => ['ADMIN_IG', 'OPERATEUR'],
        'submenu' => [
            ['title' => 'Liste des contrôlés',   'url' => '/ctr.net-fardc/modules/controles/liste.php',   'icon' => 'fas fa-list', 'roles' => ['ADMIN_IG', 'OPERATEUR']],
            ['title' => 'Effectuer un contrôle', 'url' => '/ctr.net-fardc/modules/controles/ajouter.php', 'icon' => 'fas fa-plus', 'roles' => ['ADMIN_IG', 'OPERATEUR']]
        ]
    ],
    [
        'title' => 'Litige',
        'icon'  => 'fas fa-gavel',
        'roles' => ['ADMIN_IG', 'OPERATEUR'],
        'submenu' => [
            ['title' => 'Liste des litiges', 'url' => '/ctr.net-fardc/modules/litige/liste.php',   'icon' => 'fas fa-list', 'roles' => ['ADMIN_IG', 'OPERATEUR']],
            ['title' => 'Ajouter un litige', 'url' => '/ctr.net-fardc/modules/litige/ajouter.php', 'icon' => 'fas fa-plus', 'roles' => ['ADMIN_IG', 'OPERATEUR']]
        ]
    ]
];

// Charger le mapping centralisé des routes protégées
// Ce fichier est partagé entre le serveur et le client (généré en JSON pour JS)
$protectedRouteRoles = include_once dirname(__DIR__) . '/config/protected_routes.php';
if (!is_array($protectedRouteRoles)) {
    $protectedRouteRoles = [];
}

// ------------------------------------------------------------
// DÉTERMINATION DES CLASSES DU BODY
// ------------------------------------------------------------
$bodyClass = 'hold-transition';
$bodyClass .= ' sidebar-mini layout-fixed';
if (isset($_SESSION['user_id'])) {
    $bodyClass .= ' user-logged-in';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTL.NET - FARDC</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- AdminLTE 3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Import local font bundle -->
    <link rel="stylesheet" href="assets/css/fonts.css">

    <!-- Styles personnalisés -->
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/css/custom.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* === Typographie globale : Barlow === */
        body,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        p,
        a,
        span,
        div,
        button,
        input,
        select,
        textarea,
        table,
        th,
        td,
        .nav-link {
            font-family: 'Barlow', sans-serif !important;
        }

        html {
            font-size: 14px;
        }

        /* === NOUVELLES COULEURS : VERT KAKI MILITAIRE === */
        .main-header.navbar {
            background: linear-gradient(135deg, #5C7A4D 0%, #3F5A2E 100%);
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .main-header .navbar-nav .nav-link {
            color: white !important;
            transition: all 0.3s;
        }

        .main-header .navbar-nav .nav-link:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        /* === SIDEBAR : même vert kaki === */
        .main-sidebar {
            background: linear-gradient(135deg, #5C7A4D 0%, #3F5A2E 100%);
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-dark-primary {
            background: transparent !important;
        }

        .brand-link {
            height: 55px;
            padding: 0 15px;
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.15);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            gap: 10px;
        }

        .brand-link .brand-image {
            max-height: 35px;
            width: auto;
            border: 2px solid white;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.3);
            transition: all 0.3s;
        }

        .brand-link:hover .brand-image {
            transform: rotate(360deg);
        }

        .brand-link .brand-text {
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        /* Navigation sidebar */
        .nav-sidebar .nav-item .nav-link {
            color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            margin: 2px 10px;
            transition: all 0.3s;
        }

        .nav-sidebar .nav-item .nav-link:hover {
            background: #3F5A2E;
            transform: translateX(5px);
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .nav-sidebar .nav-item .nav-link.active {
            background: white;
            color: #5C7A4D !important;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .nav-treeview {
            background: rgba(0, 0, 0, 0.15);
            border-radius: 10px;
            margin: 5px 10px !important;
            padding: 5px 0 !important;
        }

        /* Content Wrapper */
        .content-wrapper {
            background: #f8f9fa;
        }

        /* Content Header */
        .content-header {
            background: white;
            border-bottom: 1px solid #5C7A4D;
            padding: 15px 0;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .content-header h1 {
            color: #495057;
            font-weight: 600;
            font-size: 1.8rem;
            margin: 0;
            background: linear-gradient(135deg, #5C7A4D 0%, #3F5A2E 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Breadcrumb */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
        }

        .breadcrumb-item a {
            color: #5C7A4D;
            text-decoration: none;
            transition: all 0.3s;
        }

        .breadcrumb-item a:hover {
            color: #3F5A2E;
            text-decoration: underline;
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

        .breadcrumb-item+.breadcrumb-item::before {
            color: #5C7A4D;
        }

        /* Alertes modernes */
        .alert {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #5C7A4D 0%, #3F5A2E 100%);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #3F5A2E 0%, #5C7A4D 100%);
        }

        /* Avatar dans le header (rond) */
        .navbar-nav .nav-link .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 5px;
            border: 2px solid white;
        }

        /* === STYLES AMÉLIORÉS POUR LE SIDEBAR === */
        .sidebar-user-profile {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 15px 10px 15px;
            margin: 10px 15px 5px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .sidebar-user-profile:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .sidebar-user-profile .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid white;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .sidebar-user-profile:hover .profile-avatar {
            transform: scale(1.05);
            border-color: rgba(255, 255, 255, 0.9);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .sidebar-user-profile .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }

        .sidebar-user-profile .profile-info {
            text-align: center;
            width: 100%;
        }

        .sidebar-user-profile .profile-info .profile-name {
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 4px;
            text-decoration: none;
            display: block;
            transition: all 0.3s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .sidebar-user-profile .profile-info .profile-name:hover {
            text-decoration: underline;
            opacity: 0.9;
        }

        .sidebar-user-profile .profile-info .profile-role {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            font-weight: 500;
            display: block;
            background: rgba(0, 0, 0, 0.2);
            padding: 4px 10px;
            border-radius: 20px;
            margin-top: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            backdrop-filter: blur(5px);
        }

        /* Ligne de séparation améliorée */
        .sidebar-divider {
            border: none;
            height: 2px;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.3), transparent);
            margin: 5px 20px 15px 20px;
            position: relative;
        }

        .sidebar-divider::after {
            content: '';
            position: absolute;
            top: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 4px;
            background: white;
            border-radius: 2px;
            opacity: 0.3;
        }

        /* Style pour le bouton de déconnexion direct */
        .navbar-nav .nav-link.logout-link {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            margin-left: 10px;
            transition: all 0.3s;
        }

        .navbar-nav .nav-link.logout-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .navbar-nav .nav-link.logout-link i {
            font-size: 1.1rem;
        }

        /* Responsive */
        @media (max-width: 767px) {
            .sidebar-user-profile .profile-avatar {
                width: 70px;
                height: 70px;
            }

            .sidebar-user-profile .profile-info .profile-name {
                font-size: 0.95rem;
            }

            .sidebar-user-profile .profile-info .profile-role {
                font-size: 0.75rem;
            }

            .content-header h1 {
                font-size: 1.5rem;
            }

            .navbar-nav .nav-link.logout-link span {
                display: none;
            }

            .navbar-nav .nav-link.logout-link {
                padding: 8px 10px;
            }
        }

        /* Effet de vague - RÉTABLI (état initial) */
        .main-sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(92, 122, 77, 0.8) 0%, rgba(63, 90, 46, 0.8) 100%);
            pointer-events: none;
            z-index: -1;
        }

        .nav-icon {
            transition: all 0.3s;
        }

        .nav-item:hover .nav-icon {
            transform: scale(1.1);
        }

        .nav-link {
            position: relative;
            overflow: hidden;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .nav-link:hover::after {
            width: 200px;
            height: 200px;
        }

        .dropdown-menu {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .dropdown-item {
            transition: all 0.3s;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, #5C7A4D 0%, #3F5A2E 100%);
            color: white;
            padding-left: 25px;
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

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .toast-notification {
            transition: all 0.3s;
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            z-index: 10001;
            animation: slideIn 0.3s ease-out;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10050;
        }

        .toast-message {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
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
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            box-shadow: 0 5px 20px rgba(220, 53, 69, 0.3);
        }

        .toast-message.warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
            box-shadow: 0 5px 20px rgba(255, 193, 7, 0.3);
        }

        @media (max-width: 480px) {
            .toast-message {
                min-width: 260px;
                font-size: 0.9rem;
                padding: 12px 16px;
            }
        }
    </style>

    <script>
        // Rôle de l'utilisateur injecté depuis PHP
        window.userProfil = <?= json_encode($user_profil) ?>;
        window.disableGlobalAccessToast = <?= $disable_global_access_toast ? 'true' : 'false' ?>;
        window.protectedRouteRoles = <?= json_encode($protectedRouteRoles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function showLoginStyleToast(message, type = 'success') {
            if (!message || window.disableGlobalAccessToast) return;

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

        // Personnalisation de SweetAlert pour les toasts
        (function() {
            if (typeof Swal === 'undefined' || !Swal.fire) {
                return;
            }
            const _fire = Swal.fire.bind(Swal);
            Swal.fire = function(arg1, arg2, arg3) {
                try {
                    if (typeof arg1 === 'string') {
                        const opts = {
                            title: arg1,
                            text: arg2 || '',
                            icon: arg3 || null,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000,
                            background: arg3 === 'success' ? '#28a745' : (arg3 === 'error' ? '#dc3545' :
                                '#6c757d'),
                            color: '#ffffff',
                            iconColor: '#ffffff'
                        };
                        return _fire(opts);
                    }
                    const opts = arg1;
                    if (opts && typeof opts === 'object' && !opts.showCancelButton && (opts.timer || opts
                            .showConfirmButton === false)) {
                        const toastOpts = Object.assign({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: opts.timer || 2000,
                            background: opts.icon === 'success' ? '#28a745' : (opts.icon === 'error' ?
                                '#dc3545' : '#6c757d'),
                            color: '#ffffff',
                            iconColor: '#ffffff'
                        }, opts);
                        return _fire(toastOpts);
                    }
                } catch (e) {
                    return _fire(arg1);
                }
                return _fire(arg1);
            };
        })();

        document.addEventListener('DOMContentLoaded', function() {
            function normalizePath(path) {
                if (!path) return '/';
                let clean = path.toLowerCase();
                clean = clean.replace(/\/+$/, '');
                return clean === '' ? '/' : clean;
            }

            function getRequiredRolesForLink(link) {
                const explicitRoles = (link.getAttribute('data-required-role') || '').trim();
                if (explicitRoles) {
                    return explicitRoles.split(',').map(r => r.trim()).filter(Boolean);
                }

                const href = link.getAttribute('href') || '';
                if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') ||
                    href.startsWith('tel:')) {
                    return null;
                }

                const parser = document.createElement('a');
                parser.href = link.href;

                if ((parser.origin || '') !== window.location.origin) {
                    return null;
                }

                const normalizedPath = normalizePath(parser.pathname);
                const routeMap = window.protectedRouteRoles || {};
                for (const route in routeMap) {
                    if (!Object.prototype.hasOwnProperty.call(routeMap, route)) continue;
                    if (normalizePath(route) === normalizedPath) {
                        return routeMap[route] || [];
                    }
                }

                return null;
            }

            // Gestion de la déconnexion
            document.querySelectorAll('.logout-link').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const logoutUrl = link.getAttribute('href') || '/ctr.net-fardc/logout.php';

                    if (typeof Swal !== 'undefined' && Swal.fire) {
                        Swal.fire({
                            title: 'Confirmation de déconnexion',
                            text: 'Êtes-vous sûr de vouloir vous déconnecter ?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Oui, déconnecter',
                            cancelButtonText: 'Annuler'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = logoutUrl;
                            }
                        });
                        return;
                    }

                    if (window.confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
                        window.location.href = logoutUrl;
                    }
                });
            });

            // Interception des clics sur les liens protégés (data-required-role)
            document.addEventListener('click', function(e) {
                const link = e.target.closest('a[href]');
                if (!link) return;
                if (link.classList.contains('logout-link')) return;
                if (link.hasAttribute('download')) return;
                if ((link.getAttribute('target') || '').toLowerCase() === '_blank') return;

                const rolesAllowed = getRequiredRolesForLink(link);
                if (!Array.isArray(rolesAllowed) || rolesAllowed.length === 0) return;

                if (!rolesAllowed.includes(window.userProfil)) {
                    e.preventDefault();
                    showLoginStyleToast('Accès non autorisé : vous n’avez pas les droits nécessaires pour cette page.',
                        'error');
                }
            });
        });
    </script>
</head>

<body class="<?= $bodyClass ?>">
    <?php if (!$disable_global_access_toast): ?>
        <div class="toast-container" id="toastContainer"></div>
    <?php endif; ?>
    <div class="wrapper">

        <!-- Navbar (HEADER) -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                    </li>
                    <li class="nav-item">
                        <a href="/ctr.net-fardc/index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Tableau de
                            bord</a>
                    </li>
                    <?php if ($user_profil === 'ADMIN_IG'): ?>
                        <li class="nav-item">
                            <a href="/ctr.net-fardc/modules/rapports/statistiques.php" class="nav-link"><i
                                    class="fas fa-chart-bar"></i> Statistiques</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="/ctr.net-fardc/modules/controles/liste.php" class="nav-link"><i class="fas fa-list"></i>
                            Liste</a>
                    </li>
                    <li class="nav-item">
                        <a href="/ctr.net-fardc/modules/controles/ajouter.php" class="nav-link"><i class="fas fa-plus"></i>
                            Nouveau</a>
                    </li>
            </ul>

            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= $avatarUrl ?>" class="user-avatar" alt="User Image">
                        <span><?= htmlspecialchars($user_nom) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link logout-link" href="/ctr.net-fardc/logout.php" title="Déconnexion">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="d-none d-lg-inline ml-1">Déconnexion</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Sidebar -->\n            <aside class=\"main-sidebar sidebar-dark-primary elevation-4\">", "oldString": "        <!-- Sidebar -->\n        <?php if ($user_profil !== 'CONTROLEUR'): ?>\n            <aside class=\"main-sidebar sidebar-dark-primary elevation-4\">"
                <a href="/ctr.net-fardc/index.php" class="brand-link">
                    <img src="/ctr.net-fardc/assets/img/logo-fardc.png" alt="FARDC Logo"
                        class="brand-image img-circle elevation-3" style="opacity: .8">
                    <span class="brand-text font-weight-bold">CTL EFF MIL - FARDC</span>
                </a>
                <div class="sidebar">
                    <div class="sidebar-user-profile">
                        <div class="profile-avatar">
                            <img src="<?= $avatarUrl ?>" alt="User Avatar">
                        </div>
                        <div class="profile-info">
                            <a href="/ctr.net-fardc/profil.php" class="profile-name"><?= htmlspecialchars($user_nom) ?></a>
                            <span class="profile-role"><?= htmlspecialchars($user_profil) ?></span>
                        </div>
                    </div>
                    <hr class="sidebar-divider">
                    <nav class="mt-2">
                        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                            data-accordion="true">
                            <?php foreach ($menuItems as $item): ?>
                                <?php if (in_array($user_profil, $item['roles'])): ?>
                                    <?php if (isset($item['submenu'])): ?>
                                        <li class="nav-item has-treeview">
                                            <a href="#" class="nav-link">
                                                <i class="nav-icon <?= $item['icon'] ?>"></i>
                                                <p><?= $item['title'] ?> <i class="right fas fa-angle-left"></i></p>
                                            </a>
                                            <ul class="nav nav-treeview">
                                                <?php foreach ($item['submenu'] as $sub): ?>
                                                    <li class="nav-item">
                                                        <a href="<?= $sub['url'] ?>" class="nav-link"
                                                            data-required-role="<?= implode(',', $sub['roles'] ?? $item['roles']) ?>">
                                                            <i class="<?= $sub['icon'] ?>"></i> <?= $sub['title'] ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </li>
                                    <?php else: ?>
                                        <li class="nav-item">
                                            <a href="<?= $item['url'] ?>" class="nav-link"
                                                data-required-role="<?= implode(',', $item['roles']) ?>">
                                                <i class="nav-icon <?= $item['icon'] ?>"></i>
                                                <p><?= $item['title'] ?></p>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                </div>
            </aside>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1><?= $page_titre ?? 'Tableau de bord' ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="/ctr.net-fardc/index.php">Accueil</a></li>
                                <?php if (isset($breadcrumb)): ?>
                                    <?php foreach ($breadcrumb as $libelle => $lien): ?>
                                        <li class="breadcrumb-item"><a href="<?= $lien ?>"><?= $libelle ?></a></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <li class="breadcrumb-item active"><?= $page_titre ?? '' ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <!-- Affichage des messages flash (SweetAlert) -->
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            <?php if (isset($_SESSION['flash_message'])): ?>
                                const flashMsg = <?php echo json_encode($_SESSION['flash_message']); ?>;
                                const toastTypeMap = {
                                    'success': 'success',
                                    'danger': 'error',
                                    'warning': 'warning',
                                    'info': 'warning'
                                };
                                showLoginStyleToast(flashMsg.text || 'Action effectuée.', toastTypeMap[flashMsg.type] ||
                                    'warning');
                                <?php unset($_SESSION['flash_message']); ?>
                            <?php endif; ?>
                        });
                    </script>
                    <?php if (isset($_SESSION['flash'])): ?>
                        <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <?= $_SESSION['flash']['message'] ?>
                        </div>
                        <?php unset($_SESSION['flash']); ?>
                    <?php endif; ?>