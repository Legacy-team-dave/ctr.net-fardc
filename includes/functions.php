<?php

/**
 * Fichier : includes/functions.php
 * Description : Fonctions utilitaires pour l'application
 * Version : 3.0 avec sauvegarde automatique toutes les 2 minutes incluant les non-vus
 */

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Définir l'encodage par défaut
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

/**
 * ============================================
 * FONCTIONS DE FLASH MESSAGES
 * ============================================
 */

/**
 * Définit un message flash et redirige
 * @param string $url URL de redirection
 * @param string $type Type du message (success, danger, warning, info)
 * @param string $message Texte du message
 */
function redirect_with_flash($url, $type = 'info', $message = '')
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'text' => $message
    ];
    header('Location: ' . $url);
    exit;
}

/**
 * ============================================
 * FONCTIONS DE VÉRIFICATION ET CRÉATION DE TABLES
 * ============================================
 */

/**
 * Vérifie et crée la table logs si elle n'existe pas
 * Structure complète avec clé étrangère vers utilisateurs
 * 
 * @return bool True si la table a été créée, False sinon
 */
function check_logs_table()
{
    global $pdo;
    try {
        // Vérifier si la table logs existe
        $check = $pdo->query("SHOW TABLES LIKE 'logs'");
        if ($check->rowCount() == 0) {
            // Créer la table logs avec la structure appropriée
            $sql = "CREATE TABLE IF NOT EXISTS `logs` (
                `id_log` INT PRIMARY KEY AUTO_INCREMENT,
                `id_utilisateur` INT NULL,
                `action` VARCHAR(100) NOT NULL,
                `table_concernee` VARCHAR(50),
                `id_enregistrement` INT,
                `details` TEXT,
                `ip_address` VARCHAR(45),
                `user_agent` TEXT,
                `date_action` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_utilisateur (id_utilisateur),
                INDEX idx_action (action),
                INDEX idx_date (date_action),
                FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $pdo->exec($sql);
            error_log("✅ Table 'logs' créée avec succès");

            // Optionnel : migrer les données de logs_actions vers logs si elle existe
            migrate_logs_data();

            return true;
        }
    } catch (PDOException $e) {
        error_log("❌ Erreur vérification/création table logs: " . $e->getMessage());
    }
    return false;
}

/**
 * Journalise une action seulement si l'utilisateur est ADMIN_IG ou OPERATEUR
 * Wrapper pratique autour de log_action pour centraliser la règle métier
 *
 * @param string $action
 * @param string|null $table
 * @param int|null $record_id
 * @param string|null $details
 * @return bool
 */
function audit_action($action, $table = null, $record_id = null, $details = null)
{
    // Profils à logger systématiquement
    $profils_a_logger = ['ADMIN_IG', 'OPERATEUR'];
    $user_profil = isset($_SESSION['user_profil']) ? strtoupper(trim($_SESSION['user_profil'])) : '';

    if (in_array($user_profil, $profils_a_logger, true)) {
        return log_action($action, $table, $record_id, $details);
    }

    // Si l'utilisateur n'a pas le profil attendu, ne pas journaliser
    return false;
}

/**
 * Migre les données de l'ancienne table logs_actions vers la nouvelle table logs
 * Évite les doublons en vérifiant si des données existent déjà
 * 
 * @return bool True si migration réussie, False sinon
 */
function migrate_logs_data()
{
    global $pdo;
    try {
        // Vérifier si l'ancienne table logs_actions existe
        $check_old = $pdo->query("SHOW TABLES LIKE 'logs_actions'");
        if ($check_old->rowCount() > 0) {
            // Vérifier si des données existent déjà dans logs pour éviter les doublons
            $count = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();

            if ($count == 0) {
                // Migration des données
                $sql = "INSERT INTO logs (id_utilisateur, action, table_concernee, id_enregistrement, details, ip_address, date_action)
                        SELECT id_utilisateur, action, table_concernee, id_enregistrement, details, adresse_ip, date_action
                        FROM logs_actions";

                $pdo->exec($sql);
                $migrated = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
                error_log("✅ Migration des données de logs_actions vers logs effectuée : $migrated enregistrements migrés");
                return true;
            }
        }
    } catch (PDOException $e) {
        error_log("❌ Erreur lors de la migration des logs: " . $e->getMessage());
    }
    return false;
}

/**
 * Vérifie et crée la table logs_actions si elle n'existe pas (pour compatibilité)
 * Permet de maintenir la compatibilité avec l'ancienne structure
 * 
 * @return bool True si la table a été créée, False sinon
 */
function check_logs_actions_table()
{
    global $pdo;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'logs_actions'");
        if ($check->rowCount() == 0) {
            $sql = "CREATE TABLE IF NOT EXISTS `logs_actions` (
                `id_log` INT PRIMARY KEY AUTO_INCREMENT,
                `id_utilisateur` INT,
                `action` VARCHAR(100) NOT NULL,
                `table_concernee` VARCHAR(50),
                `id_enregistrement` INT,
                `details` TEXT,
                `adresse_ip` VARCHAR(45),
                `date_action` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_utilisateur (id_utilisateur),
                INDEX idx_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $pdo->exec($sql);
            error_log("✅ Table 'logs_actions' créée avec succès (compatibilité)");
            return true;
        }
    } catch (PDOException $e) {
        error_log("❌ Erreur vérification/création table logs_actions: " . $e->getMessage());
    }
    return false;
}

/**
 * Vérifie et crée la colonne preferences si elle n'existe pas
 * Ajoute la colonne après dernier_acces dans la table utilisateurs
 * 
 * @return bool True si la colonne a été créée, False sinon
 */
function check_preferences_column()
{
    global $pdo;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM utilisateurs LIKE 'preferences'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN preferences TEXT NULL AFTER dernier_acces");
            error_log("✅ Colonne 'preferences' créée avec succès dans la table utilisateurs");
            return true;
        }
    } catch (PDOException $e) {
        error_log("❌ Erreur vérification/création colonne preferences: " . $e->getMessage());
    }
    return false;
}

/**
 * Vérifie et crée les colonnes remember_token et remember_token_expires si elles n'existent pas
 * 
 * @return bool True si au moins une colonne a été créée, False sinon
 */
function check_remember_columns()
{
    global $pdo;
    $created = false;
    try {
        // Vérifier la colonne remember_token
        $check = $pdo->query("SHOW COLUMNS FROM utilisateurs LIKE 'remember_token'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN remember_token VARCHAR(255) NULL AFTER preferences");
            error_log("✅ Colonne 'remember_token' créée avec succès");
            $created = true;
        }

        // Vérifier la colonne remember_token_expires
        $check = $pdo->query("SHOW COLUMNS FROM utilisateurs LIKE 'remember_token_expires'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN remember_token_expires DATETIME NULL AFTER remember_token");
            error_log("✅ Colonne 'remember_token_expires' créée avec succès");
            $created = true;
        }
    } catch (PDOException $e) {
        error_log("❌ Erreur vérification/création des colonnes remember_token: " . $e->getMessage());
    }
    return $created;
}

// Exécuter les vérifications au chargement du fichier
check_logs_table();
check_preferences_column();
check_remember_columns();

/**
 * ============================================
 * FONCTIONS DE JOURNALISATION (LOGS)
 * ============================================
 */

/**
 * Journalise une action dans la table logs ou logs_actions
 * Tente d'abord d'utiliser la table logs, puis logs_actions en fallback
 *
 * @param string $action  Nom de l'action (ex: 'CONNEXION', 'AJOUT', 'MODIFICATION', 'SUPPRESSION')
 * @param string|null $table Table concernée (ex: 'utilisateurs', 'materiel', 'interventions')
 * @param int|null $record_id Identifiant de l'enregistrement concerné
 * @param string|null $details Détails supplémentaires au format texte ou JSON
 * @return bool True si journalisation réussie, False sinon
 */
function log_action($action, $table = null, $record_id = null, $details = null)
{
    global $pdo;

    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Tronquer l'user agent s'il est trop long pour éviter les erreurs
    if ($user_agent && strlen($user_agent) > 65535) {
        $user_agent = substr($user_agent, 0, 65535);
    }

    try {
        // Essayer d'abord avec la table 'logs' (nouvelle structure)
        $stmt = $pdo->prepare("INSERT INTO logs 
            (id_utilisateur, action, table_concernee, id_enregistrement, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$user_id, $action, $table, $record_id, $details, $ip_address, $user_agent]);

        if ($result) {
            return true;
        }
    } catch (PDOException $e) {
        // Si la table 'logs' n'existe pas, essayer avec 'logs_actions'
        try {
            // Vérifier si la table logs_actions existe, sinon la créer
            check_logs_actions_table();

            $stmt = $pdo->prepare("INSERT INTO logs_actions 
                (id_utilisateur, action, table_concernee, id_enregistrement, details, adresse_ip) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$user_id, $action, $table, $record_id, $details, $ip_address]);

            if ($result) {
                return true;
            }
        } catch (PDOException $e2) {
            // Si les deux échouent, logger dans error_log
            error_log("❌ Erreur log_action (logs et logs_actions): " . $e2->getMessage());
            error_log("   Action: $action, Table: $table, ID: $record_id, User: $user_id");
        }
    }

    return false;
}

/**
 * Récupère les logs avec filtres optionnels
 * Permet de filtrer par utilisateur, action, période et limite
 *
 * @param array $filtres Filtres disponibles :
 *                      - id_utilisateur : int
 *                      - action : string
 *                      - date_debut : string (format Y-m-d H:i:s)
 *                      - date_fin : string (format Y-m-d H:i:s)
 *                      - limite : int
 * @return array Liste des logs avec informations utilisateur
 */
function get_logs($filtres = [])
{
    global $pdo;

    $sql = "SELECT l.*, u.nom_complet, u.login, u.avatar 
            FROM logs l 
            LEFT JOIN utilisateurs u ON l.id_utilisateur = u.id_utilisateur 
            WHERE 1=1";
    $params = [];

    if (!empty($filtres['id_utilisateur'])) {
        $sql .= " AND l.id_utilisateur = ?";
        $params[] = $filtres['id_utilisateur'];
    }

    if (!empty($filtres['action'])) {
        $sql .= " AND l.action = ?";
        $params[] = $filtres['action'];
    }

    if (!empty($filtres['date_debut'])) {
        $sql .= " AND l.date_action >= ?";
        $params[] = $filtres['date_debut'];
    }

    if (!empty($filtres['date_fin'])) {
        $sql .= " AND l.date_action <= ?";
        $params[] = $filtres['date_fin'];
    }

    if (!empty($filtres['table_concernee'])) {
        $sql .= " AND l.table_concernee = ?";
        $params[] = $filtres['table_concernee'];
    }

    $sql .= " ORDER BY l.date_action DESC";

    if (!empty($filtres['limite'])) {
        $sql .= " LIMIT ?";
        $params[] = (int)$filtres['limite'];
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("❌ Erreur get_logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Nettoie les anciens logs (plus de 3 mois par défaut)
 * À exécuter périodiquement via cron ou manuellement
 *
 * @param int $jours Nombre de jours à conserver (défaut: 90)
 * @return int Nombre de logs supprimés
 */
function nettoyer_anciens_logs($jours = 90)
{
    global $pdo;
    try {
        $date_limite = date('Y-m-d H:i:s', strtotime("-$jours days"));
        $stmt = $pdo->prepare("DELETE FROM logs WHERE date_action < ?");
        $stmt->execute([$date_limite]);
        $count = $stmt->rowCount();

        if ($count > 0) {
            error_log("🧹 $count logs plus vieux que $jours jours ont été supprimés");

            // Optimiser la table après nettoyage
            $pdo->exec("OPTIMIZE TABLE logs");
        }

        return $count;
    } catch (PDOException $e) {
        error_log("❌ Erreur nettoyage logs: " . $e->getMessage());
        return 0;
    }
}

/**
 * ============================================
 * FONCTIONS DE GESTION DES UTILISATEURS ET RÔLES
 * ============================================
 */

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 *
 * @param string $role Rôle à vérifier (ex: 'ADMIN_IG', 'UTILISATEUR', 'CHEF', etc.)
 * @return bool True si l'utilisateur a le rôle, False sinon
 */
function has_role($role)
{
    return isset($_SESSION['user_profil']) && $_SESSION['user_profil'] === $role;
}

/**
 * Vérifie si l'utilisateur est admin (profil = 'ADMIN_IG')
 *
 * @return bool True si l'utilisateur est admin, False sinon
 */
function is_admin()
{
    return has_role('ADMIN_IG');
}

/**
 * Vérifie que l'utilisateur connecté a un profil autorisé
 * Redirige vers index.php avec un message flash si non autorisé
 *
 * @param array|string $profils_autorises
 * @return void
 */
function check_profil($profils_autorises)
{
    if (!is_array($profils_autorises)) {
        $profils_autorises = [$profils_autorises];
    }

    // Nettoyer et uniformiser la casse
    $profils_autorises = array_map('strtoupper', $profils_autorises);

    if (!isset($_SESSION['user_profil'])) {
        redirect_with_flash('index.php', 'danger', 'Session invalide. Veuillez vous reconnecter.');
    }

    $user_profil = trim(strtoupper($_SESSION['user_profil']));

    if (!in_array($user_profil, $profils_autorises)) {
        redirect_with_flash('index.php', 'danger', 'Accès refusé. Veuillez contacter l\'administrateur si vous pensez avoir les droits nécessaires.');
    }
}

/**
 * Redirige vers la page de connexion si l'utilisateur n'est pas authentifié
 *
 * @return void
 */
function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

/**
 * Récupère un utilisateur par son ID
 *
 * @param int $user_id ID de l'utilisateur (id_utilisateur)
 * @return array|false Tableau des informations utilisateur ou False si erreur
 */
function get_user_by_id($user_id)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT 
            id_utilisateur, login, nom_complet, email, avatar, 
            profil, actif, dernier_acces, created_at, preferences 
            FROM utilisateurs WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("❌ Erreur get_user_by_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si un utilisateur est actif
 *
 * @param int $user_id ID de l'utilisateur
 * @return bool True si actif, False sinon
 */
function is_user_active($user_id)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT actif FROM utilisateurs WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("❌ Erreur is_user_active: " . $e->getMessage());
        return false;
    }
}

function check_session_timeout()
{
    $inactive_time = 3600; // 1 heure

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive_time)) {
        // Session expirée
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }

    // Mettre à jour le dernier accès
    $_SESSION['last_activity'] = time();
}

/**
 * Récupère les préférences de l'utilisateur
 *
 * @param int $user_id ID de l'utilisateur (id_utilisateur)
 * @return array|null Tableau des préférences ou null si aucune/erreur
 */
function get_user_preferences($user_id)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT preferences FROM utilisateurs WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $prefs = $stmt->fetchColumn();
        return $prefs ? json_decode($prefs, true) : null;
    } catch (PDOException $e) {
        error_log("❌ Erreur get_user_preferences: " . $e->getMessage());
        return null;
    }
}

/**
 * Met à jour les préférences d'un utilisateur
 *
 * @param int $user_id ID de l'utilisateur
 * @param array $preferences Tableau des préférences à sauvegarder
 * @return bool True si succès, False sinon
 */
function update_user_preferences($user_id, $preferences)
{
    global $pdo;
    try {
        $json_prefs = json_encode($preferences, JSON_UNESCAPED_UNICODE);
        $stmt = $pdo->prepare("UPDATE utilisateurs SET preferences = ? WHERE id_utilisateur = ?");
        return $stmt->execute([$json_prefs, $user_id]);
    } catch (PDOException $e) {
        error_log("❌ Erreur update_user_preferences: " . $e->getMessage());
        return false;
    }
}

/**
 * ============================================
 * FONCTIONS DE SÉCURITÉ
 * ============================================
 */

/**
 * Sécurise une chaîne pour l'affichage HTML
 * Alias pour htmlspecialchars avec UTF-8
 *
 * @param string $string Chaîne à échapper
 * @return string Chaîne échappée
 */
function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Échappe les données pour un affichage HTML sécurisé
 * Alias pour e() - Conservation pour compatibilité
 *
 * @param string $str Chaîne à échapper
 * @return string Chaîne échappée
 */
function h($str)
{
    return e($str);
}

/**
 * Génère un token CSRF pour protéger les formulaires
 *
 * @return string Token CSRF
 */
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le token CSRF
 *
 * @param string $token Token à vérifier
 * @return bool True si valide, False sinon
 */
function verify_csrf_token($token)
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ============================================
 * FONCTIONS DE FORMATAGE
 * ============================================
 */

/**
 * Formate une date
 *
 * @param string $date Date à formater
 * @param string $format Format de sortie (par défaut: 'd/m/Y H:i:s')
 * @return string Date formatée ou chaîne vide si erreur
 */
function format_date($date, $format = 'd/m/Y H:i:s')
{
    if (empty($date)) return '';
    try {
        $d = new DateTime($date);
        return $d->format($format);
    } catch (Exception $e) {
        error_log("❌ Erreur format_date: " . $e->getMessage());
        return $date;
    }
}

/**
 * Formate une taille de fichier en affichage lisible
 *
 * @param int $bytes Taille en bytes
 * @param int $precision Nombre de décimales
 * @return string Taille formatée (ex: 1.5 MB)
 */
function format_filesize($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Tronque un texte à une longueur maximale
 *
 * @param string $text Texte à tronquer
 * @param int $length Longueur maximale
 * @param string $append Texte à ajouter à la fin si tronqué
 * @return string Texte tronqué
 */
function truncate_text($text, $length = 100, $append = '...')
{
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length, 'UTF-8') . $append;
}

/**
 * ============================================
 * FONCTIONS DE GESTION DES AVATARS
 * ============================================
 */

/**
 * Récupère l'URL complète de l'avatar
 * Vérifie l'existence physique du fichier
 *
 * @param string|null $avatarFile Nom du fichier avatar (colonne 'avatar')
 * @return string URL complète de l'avatar
 */
function getAvatarUrl($avatarFile)
{
    $baseUrl = '/ctr.net-fardc (v1.0)/assets/uploads/avatars/';
    $default = $baseUrl . 'default-avatar.jpg';

    if (!empty($avatarFile)) {
        $absolutePath = dirname(__DIR__, 2) . '/assets/uploads/avatars/' . $avatarFile;

        if (file_exists($absolutePath)) {
            return $baseUrl . $avatarFile;
        }
    }
    return $default;
}

/**
 * Télécharge et traite un avatar
 *
 * @param array $file $_FILES['avatar']
 * @param int $user_id ID de l'utilisateur
 * @return string|false Nom du fichier ou false si erreur
 */
function upload_avatar($file, $user_id)
{
    $target_dir = dirname(__DIR__, 2) . '/assets/uploads/avatars/';

    // Vérifier que le répertoire existe
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // Extensions autorisées
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB

    // Vérifications
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Erreur upload avatar: " . $file['error']);
        return false;
    }

    if (!in_array($file['type'], $allowed_types)) {
        error_log("Type de fichier non autorisé: " . $file['type']);
        return false;
    }

    if ($file['size'] > $max_size) {
        error_log("Fichier trop volumineux: " . $file['size']);
        return false;
    }

    // Générer un nom unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
    $target_file = $target_dir . $filename;

    // Redimensionner l'image si nécessaire (optionnel)
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $filename;
    }

    return false;
}

/**
 * ============================================
 * FONCTIONS DE GESTION DES MESSAGES FLASH
 * ============================================
 */

/**
 * Affiche et efface le message flash avec auto-fermeture
 * À appeler dans les vues (ex: index.php, dashboard.php)
 *
 * @return void
 */
function display_flash()
{
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = h($_SESSION['flash']['message']);

        $flash_id = 'flash_' . uniqid();

        echo "<div id='$flash_id' class='alert alert-$type alert-dismissible fade show' role='alert' style='position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);'>
                <div class='d-flex align-items-center'>
                    <i class='fas " . get_flash_icon($type) . " me-2'></i>
                    <div>$message</div>
                </div>
                <button type='button' class='btn-close' onclick='document.getElementById(\"$flash_id\").remove()' aria-label='Close'></button>
              </div>";

        echo "<script>
                setTimeout(function() {
                    var flash = document.getElementById('$flash_id');
                    if (flash) {
                        flash.style.transition = 'opacity 0.5s';
                        flash.style.opacity = '0';
                        setTimeout(() => flash.remove(), 500);
                    }
                }, 5000);
              </script>";

        unset($_SESSION['flash']);
    }
}

/**
 * Retourne l'icône Font Awesome correspondant au type de flash
 *
 * @param string $type Type de flash
 * @return string Classe de l'icône
 */
function get_flash_icon($type)
{
    switch ($type) {
        case 'success':
            return 'fa-check-circle';
        case 'danger':
            return 'fa-exclamation-circle';
        case 'warning':
            return 'fa-exclamation-triangle';
        case 'info':
            return 'fa-info-circle';
        default:
            return 'fa-bell';
    }
}

/**
 * ============================================
 * FONCTIONS DIVERSES
 * ============================================
 */

/**
 * Génère un mot de passe aléatoire sécurisé
 *
 * @param int $length Longueur du mot de passe
 * @return string Mot de passe généré
 */
function generate_random_password($length = 12)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
    $password = '';
    $max = strlen($chars) - 1;

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }

    return $password;
}

/**
 * Vérifie si une requête est de type AJAX
 *
 * @return bool True si requête AJAX, False sinon
 */
function is_ajax_request()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Retourne une réponse JSON et termine le script
 *
 * @param mixed $data Données à encoder en JSON
 * @param int $status_code Code HTTP (défaut: 200)
 * @return void
 */
function json_response($data, $status_code = 200)
{
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ============================================
 * FONCTIONS SPÉCIFIQUES AU TABLEAU DE BORD ET SAUVEGARDE
 * ============================================
 */

// Tableaux globaux pour les tris et catégories
$gradeOrder = [
    'GENA',
    'GAM',
    'LTGEN',
    'AMR',
    'GENMAJ',
    'VAM',
    'GENBDE',
    'CAM',
    'COL',
    'CPV',
    'LTCOL',
    'CPF',
    'MAJ',
    'CPC',
    'CAPT',
    'LDV',
    'LT',
    'EV',
    'SLT',
    '2EV',
    'A-C',
    'MCP',
    'A-1',
    '1MC',
    'ADJ',
    'MRC',
    '1SM',
    '1MR',
    'SM',
    '2MR',
    '1SGT',
    'MR',
    'SGT',
    'QMT',
    'CPL',
    '1MT',
    '1CL',
    '2MT',
    '2CL',
    'MT',
    'REC',
    'ASK',
    'COMD'
];

$categorieOrder = [
    'INTEGRES',      // Intégré
    'RETRAITES',     // Retraité
    'DCD_AV_BIO',    // Décédé Avant Bio
    'DCD_AP_BIO',    // Décédé Après Bio
    'ACTIF'          // Actif
];

$traductions_categories = [
    'ACTIF'        => 'Actif',
    'DCD_AP_BIO'   => 'Décédé Après Bio',
    'INTEGRES'     => 'Intégré',
    'RETRAITES'    => 'Retraité',
    'DCD_AV_BIO'   => 'Décédé Avant Bio'
];

/**
 * Détermine la zone de défense à partir de la province
 * @param string $province
 * @return array ['value' => '1ZDEF'|'2ZDEF'|'3ZDEF'|'AUTRE'|'N/A', 'code' => ...]
 */
function getZdefValue($province)
{
    if (empty($province)) return ['value' => 'N/A', 'code' => 'N/A'];
    $province = strtoupper(trim($province));
    $groupe_2zdef = ['HAUT-KATANGA', 'HAUT-LOMAMI', 'LUALABA', 'TANGANYIKA', 'KASAI', 'KASAI-CENTRAL', 'KASAI-ORIENTAL', 'SANKURU', 'LOMAMI'];
    if (in_array($province, $groupe_2zdef)) return ['value' => '2ZDEF', 'code' => '2ZDEF'];
    $groupe_1zdef = ['EQUATEUR', 'MONGALA', 'NORD-UBANGI', 'SUD-UBANGI', 'TSHUAPA', 'KWILU', 'KWANGO', 'MAI-NDOMBE', 'KONGO-CENTRAL', 'KINSHASA'];
    if (in_array($province, $groupe_1zdef)) return ['value' => '1ZDEF', 'code' => '1ZDEF'];
    $groupe_3zdef = ['HAUT-UELE', 'BAS-UELE', 'ITURI', 'TSHOPO', 'NORD-KIVU', 'SUD-KIVU', 'MANIEMA'];
    if (in_array($province, $groupe_3zdef)) return ['value' => '3ZDEF', 'code' => '3ZDEF'];
    return ['value' => 'AUTRE', 'code' => 'AUTRE'];
}
/**
 * Génère le contenu CSV des militaires non contrôlés (non‑vus)
 * Utilise les mêmes tris que le tableau de bord
 * @return string Contenu CSV avec BOM UTF‑8
 */
function get_non_vus_csv_content()
{
    global $pdo;

    // Définitions internes de secours (au cas où les globales seraient absentes)
    $gradeOrder = $GLOBALS['gradeOrder'] ?? [
        'GENA',
        'GAM',
        'LTGEN',
        'AMR',
        'GENMAJ',
        'VAM',
        'GENBDE',
        'CAM',
        'COL',
        'CPV',
        'LTCOL',
        'CPF',
        'MAJ',
        'CPC',
        'CAPT',
        'LDV',
        'LT',
        'EV',
        'SLT',
        '2EV',
        'A-C',
        'MCP',
        'A-1',
        '1MC',
        'ADJ',
        'MRC',
        '1SM',
        '1MR',
        'SM',
        '2MR',
        '1SGT',
        'MR',
        'SGT',
        'QMT',
        'CPL',
        '1MT',
        '1CL',
        '2MT',
        '2CL',
        'MT',
        'REC',
        'ASK',
        'COMD'
    ];

    $categorieOrder = $GLOBALS['categorieOrder'] ?? [
        'INTEGRES',      // Intégré
        'RETRAITES',     // Retraité
        'DCD_AV_BIO',    // Décédé Avant Bio
        'DCD_AP_BIO',    // Décédé Après Bio
        'ACTIF'          // Actif
    ];

    $traductions_categories = $GLOBALS['traductions_categories'] ?? [
        'ACTIF'        => 'Actif',
        'DCD_AP_BIO'   => 'Décédé Après Bio',
        'INTEGRES'     => 'Intégré',
        'RETRAITES'    => 'Retraité',
        'DCD_AV_BIO'   => 'Décédé Avant Bio'
    ];

    $sql = "SELECT 
                m.matricule,
                m.noms,
                m.categorie,
                m.grade,
                m.unite,
                m.beneficiaire,
                m.garnison,
                m.province
            FROM militaires m
            LEFT JOIN controles c ON m.matricule = c.matricule
            WHERE c.id IS NULL";

    $stmt = $pdo->query($sql);
    $non_vus_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tri personnalisé (grade, catégorie, nom)
    usort($non_vus_raw, function ($a, $b) use ($gradeOrder, $categorieOrder) {
        $gradeA = array_search($a['grade'], $gradeOrder);
        $gradeB = array_search($b['grade'], $gradeOrder);
        if ($gradeA === false) $gradeA = 999;
        if ($gradeB === false) $gradeB = 999;
        if ($gradeA != $gradeB) return $gradeA - $gradeB;

        $catA = array_search($a['categorie'], $categorieOrder);
        $catB = array_search($b['categorie'], $categorieOrder);
        if ($catA === false) $catA = 999;
        if ($catB === false) $catB = 999;
        if ($catA != $catB) return $catA - $catB;

        return strcmp($a['noms'] ?? '', $b['noms'] ?? '');
    });

    // Ajout de la ZDEF
    foreach ($non_vus_raw as &$row) {
        $zdef = getZdefValue($row['province']);
        $row['zdef'] = $zdef['value'];
    }

    $timestamp = date('Y-m-d_H-i-s');
    $title = "LISTE DES MILITAIRES NON-VUS AU CONTROLE (Sauvegarde du $timestamp)";

    $headerLines = [
        ['MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS'],
        ['INSPECTORAT GENERAL DES FARDC'],
        [$title]
    ];

    $headers = ['SERIE', 'MATRICULE', 'NOMS', 'GRADE', 'UNITE', 'BENEFICIAIRE', 'GARNISON', 'PROVINCE', 'CATEGORIE', 'ZDEF'];

    $rows = [];
    foreach ($non_vus_raw as $index => $m) {
        $rows[] = [
            $index + 1,
            $m['matricule'],
            $m['noms'],
            $m['grade'],
            $m['unite'],
            $m['beneficiaire'],
            $m['garnison'],
            $m['province'],
            $traductions_categories[$m['categorie']] ?? $m['categorie'],
            $m['zdef']
        ];
    }

    $stream = fopen('php://temp', 'r+');
    fwrite($stream, "\xEF\xBB\xBF"); // BOM UTF‑8

    foreach ($headerLines as $line) fputcsv($stream, $line);
    fputcsv($stream, []);
    fputcsv($stream, $headers);
    foreach ($rows as $row) fputcsv($stream, $row);

    rewind($stream);
    $csv_content = stream_get_contents($stream);
    fclose($stream);
    return $csv_content;
}
/**
 * Génère une archive ZIP contenant les tables controles, litiges et les non‑vus
 * 
 * @param bool $include_non_vus Inclure le CSV des non‑vus (par défaut true)
 * @return bool True en cas de succès, False sinon
 */
function generate_backup($include_non_vus = true)
{
    global $pdo;
    $backup_dir = dirname(__DIR__, 2) . '/backups/';
    if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);

    $timestamp = date('Y-m-d_H-i-s');
    $zip_file = $backup_dir . 'backup_' . $timestamp . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE) !== true) {
        error_log("Impossible de créer l'archive de sauvegarde.");
        return false;
    }

    $tables = ['controles', 'litiges'];
    $field_order = [
        'controles' => [
            'id',
            'matricule',
            'type_controle',
            'nom_beneficiaire',
            'new_beneficiaire',
            'lien_parente',
            'date_controle',
            'mention',
            'observations',
            'cree_le'
        ],
        'litiges' => [
            'id',
            'matricule',
            'noms',
            'grade',
            'type_controle',
            'nom_beneficiaire',
            'lien_parente',
            'garnison',
            'province',
            'date_controle',
            'observations',
            'cree_le'
        ],
    ];

    foreach ($tables as $table) {
        try {
            $check = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($check->rowCount() == 0) continue;

            $stmt = $pdo->query("SELECT * FROM $table");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) continue;

            $stream = fopen('php://temp', 'r+');
            fwrite($stream, "\xEF\xBB\xBF");

            $ordered_columns = $field_order[$table] ?? array_keys($rows[0]);
            fputcsv($stream, $ordered_columns);
            foreach ($rows as $row) {
                $ordered_row = [];
                foreach ($ordered_columns as $col) {
                    $ordered_row[] = $row[$col] ?? '';
                }
                fputcsv($stream, $ordered_row);
            }

            rewind($stream);
            $csv_content = stream_get_contents($stream);
            fclose($stream);
            $zip->addFromString($table . '_' . $timestamp . '.csv', $csv_content);
        } catch (PDOException $e) {
            error_log("Erreur export $table : " . $e->getMessage());
        }
    }

    if ($include_non_vus) {
        try {
            $non_vus_csv = get_non_vus_csv_content();
            if (!empty($non_vus_csv)) {
                $zip->addFromString('non_vus_' . $timestamp . '.csv', $non_vus_csv);
            }
        } catch (Exception $e) {
            error_log("Erreur export non-vus : " . $e->getMessage());
        }
    }

    $zip->close();
    return true;
}

/**
 * Récupère l'horodatage de la dernière sauvegarde
 * @return int Timestamp Unix
 */
function get_last_backup_time()
{
    $file = dirname(__DIR__, 2) . '/backups/last_backup.txt';
    if (file_exists($file)) return (int) file_get_contents($file);
    return 0;
}

/**
 * Met à jour l'horodatage de la dernière sauvegarde
 */
function update_last_backup_time()
{
    $file = dirname(__DIR__, 2) . '/backups/last_backup.txt';
    file_put_contents($file, time());
}

/**
 * Vérifie si une sauvegarde doit être effectuée (toutes les 2 minutes)
 * et l'exécute si nécessaire.
 */
function maybe_create_backup()
{
    $last_backup = get_last_backup_time();
    $now = time();
    if (($now - $last_backup) >= 120) { // 2 minutes
        generate_backup(true);
        update_last_backup_time();
        error_log("Sauvegarde automatique exécutée (intervalle 2 minutes).");
    }
}

// Appel automatique pour déclencher la sauvegarde si nécessaire
maybe_create_backup();

// Programme de nettoyage automatique (1% de chance à chaque chargement)
// Décommentez si vous voulez activer le nettoyage aléatoire
// if (rand(1, 100) == 1 && is_admin()) { 
//     nettoyer_anciens_logs(90); 
// }