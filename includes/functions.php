<?php

/**
 * Fichier : includes/functions.php
 * Description : Fonctions utilitaires pour l'application
 */

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';

// Définir l'encodage par défaut
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

/**
 * Retourne le chemin de base web de l'application.
 * Exemple: /ctr.net-fardc
 *
 * @return string
 */
function app_base_path()
{
    static $base_path = null;

    if ($base_path !== null) {
        return $base_path;
    }

    $base_path = '/' . basename(dirname(__DIR__));
    return $base_path;
}

/**
 * Construit une URL relative à la racine web de l'application.
 *
 * @param string $path
 * @return string
 */
function app_url($path = '')
{
    $base_path = app_base_path();
    $path = ltrim((string) $path, '/');

    return $path === '' ? $base_path : $base_path . '/' . $path;
}

/**
 * Retourne toutes les garnisons filtrées depuis la session ou les préférences utilisateur.
 */
function preferred_garnison_labels(?int $user_id = null): array
{
    $labels = [];
    $collect = static function (array $garnisons) use (&$labels): void {
        foreach ($garnisons as $garnison) {
            $garnison = trim((string) $garnison);
            if ($garnison !== '' && !in_array($garnison, $labels, true)) {
                $labels[] = $garnison;
            }
        }
    };

    $sessionGarnisons = $_SESSION['filtres']['garnisons'] ?? [];
    if (is_array($sessionGarnisons)) {
        $collect($sessionGarnisons);
    }

    if (!empty($labels)) {
        return $labels;
    }

    if ($user_id === null && !empty($_SESSION['user_id'])) {
        $user_id = (int) $_SESSION['user_id'];
    }

    if (!empty($user_id)) {
        $preferences = get_user_preferences((int) $user_id);
        $storedGarnisons = $preferences['garnisons'] ?? [];
        if (is_array($storedGarnisons)) {
            $collect($storedGarnisons);
        }
    }

    return $labels;
}

/**
 * Retourne la première garnison filtrée depuis la session ou les préférences utilisateur.
 */
function preferred_garnison_label(?int $user_id = null): string
{
    $labels = preferred_garnison_labels($user_id);
    return $labels[0] ?? '';
}

/**
 * Marque la session courante comme ayant des données locales à synchroniser.
 */
function mark_sync_dirty(?string $table = null, ?int $record_id = null): bool
{
    $_SESSION['sync_dirty'] = true;
    $_SESSION['sync_dirty_at'] = date('c');

    if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
        return true;
    }

    $tableName = strtolower(trim((string) $table));
    if ($tableName === '' || $record_id === null || $record_id <= 0) {
        return true;
    }

    if (!in_array($tableName, ['controles', 'equipes', 'militaires', 'enrollements_vivants'], true)) {
        return true;
    }

    try {
        $columns = $GLOBALS['pdo']->query("SHOW COLUMNS FROM `{$tableName}`")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (!in_array('sync_status', $columns, true)) {
            return true;
        }

        $sets = ["sync_status = 'local'"];
        if (in_array('sync_date', $columns, true)) {
            $sets[] = 'sync_date = NULL';
        }
        if (in_array('sync_version', $columns, true)) {
            $sets[] = 'sync_version = COALESCE(sync_version, 0) + 1';
        }

        $stmt = $GLOBALS['pdo']->prepare("UPDATE `{$tableName}` SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute([(int) $record_id]);
    } catch (Throwable $e) {
        error_log('⚠️ mark_sync_dirty: ' . $e->getMessage());
    }

    return true;
}

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

/*
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
    $profils_a_logger = ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR', 'ENROLEUR'];
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

/*
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

/* * Vérifie si l'utilisateur a un rôle spécifique
 *
 * @param string $role Rôle à vérifier (ex: 'ADMIN_IG', 'UTILISATEUR', 'CHEF', etc.)
 * @return bool True si l'utilisateur a le rôle, False sinon
 */
function has_role($role)
{
    if (!isset($_SESSION['user_profil'])) {
        return false;
    }

    $profil = strtoupper(trim((string) $_SESSION['user_profil']));
    if (is_central_mode()) {
        return $profil === 'ADMIN_IG' && strtoupper(trim((string) $role)) === 'ADMIN_IG';
    }

    return $profil === strtoupper(trim((string) $role));
}

/**
 * Indique si le profil est réservé aux applications mobiles terrain.
 * Les profils CONTROLEUR et ENROLEUR ne doivent pas accéder au web.
 */
function is_mobile_only_profile($profil): bool
{
    $profil = strtoupper(trim((string) $profil));
    return in_array($profil, ['CONTROLEUR', 'ENROLEUR'], true);
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

    if (is_central_mode()) {
        $profils_autorises = ['ADMIN_IG'];
    }

    if (!isset($_SESSION['user_profil'])) {
        redirect_with_flash(app_url('login.php'), 'danger', 'Session invalide. Veuillez vous reconnecter.');
    }

    $user_profil = trim(strtoupper($_SESSION['user_profil']));

    if (!in_array($user_profil, $profils_autorises)) {
        redirect_with_flash(app_url('index.php'), 'danger', 'Accès non autorisé : vous n\'avez pas les droits nécessaires pour cette page.');
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
        redirect_with_flash(app_url('login.php'), 'danger', 'Accès non autorisé. Veuillez vous connecter pour continuer.');
    }

    if (is_central_mode()) {
        $profil = strtoupper(trim((string) ($_SESSION['user_profil'] ?? '')));
        if ($profil !== 'ADMIN_IG') {
            session_unset();
            session_destroy();
            redirect_with_flash(app_url('login.php'), 'danger', 'Accès refusé : la plateforme centrale est réservée au profil ADMIN_IG.');
        }
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
        header('Location: ' . app_url('login.php?timeout=1'));
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

/
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
 * Retourne le dossier de sauvegarde en s'assurant qu'il existe.
 *
 * @return string
 */
function get_backup_dir_path()
{
    $backup_dir = dirname(__DIR__, 1) . '/backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    return $backup_dir;
}

function get_backup_state_file_path()
{
    return get_backup_dir_path() . 'backup_state.json';
}

function get_backup_interval_seconds()
{
    return 8 * 3600;
}

function read_backup_state()
{
    $state_file = get_backup_state_file_path();
    $default_state = [
        'last_backup_at' => 0,
        'last_run_at' => 0,
        'last_control_id' => 0,
        'non_vus_snapshot' => []
    ];

    if (!file_exists($state_file)) {
        return $default_state;
    }

    $raw = file_get_contents($state_file);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $default_state;
    }

    return array_merge($default_state, $decoded);
}

function write_backup_state($state)
{
    $state_file = get_backup_state_file_path();
    file_put_contents(
        $state_file,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function fetch_incremental_rows_for_table($table, $id_field, $field_order, $last_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$id_field} > ? ORDER BY {$id_field} ASC");
    $stmt->execute([(int)$last_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $normalized = [];
    foreach ($rows as $row) {
        $line = [];
        foreach ($field_order as $field) {
            $line[] = $row[$field] ?? '';
        }
        $normalized[] = $line;
    }

    return $normalized;
}

function fetch_full_rows_for_table($table, $field_order)
{
    global $pdo;

    $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $normalized = [];
    foreach ($rows as $row) {
        $line = [];
        foreach ($field_order as $field) {
            $line[] = $row[$field] ?? '';
        }
        $normalized[] = $line;
    }

    return $normalized;
}

function get_non_vus_rows_for_backup()
{
    global $pdo;

    $gradeOrder = $GLOBALS['gradeOrder'] ?? [];
    $categorieOrder = $GLOBALS['categorieOrder'] ?? [];
    $traductions_categories = $GLOBALS['traductions_categories'] ?? [];

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
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    usort($rows, function ($a, $b) use ($gradeOrder, $categorieOrder) {
        $gradeA = array_search($a['grade'], $gradeOrder);
        $gradeB = array_search($b['grade'], $gradeOrder);
        if ($gradeA === false) $gradeA = 999;
        if ($gradeB === false) $gradeB = 999;
        if ($gradeA !== $gradeB) return $gradeA - $gradeB;

        $catA = array_search($a['categorie'], $categorieOrder);
        $catB = array_search($b['categorie'], $categorieOrder);
        if ($catA === false) $catA = 999;
        if ($catB === false) $catB = 999;
        if ($catA !== $catB) return $catA - $catB;

        return strcmp($a['noms'] ?? '', $b['noms'] ?? '');
    });

    $normalized = [];
    $serie = 1;
    foreach ($rows as $row) {
        $zdef = getZdefValue($row['province']);
        $normalized[] = [
            $serie,
            $row['matricule'] ?? '',
            $row['noms'] ?? '',
            $row['grade'] ?? '',
            $row['unite'] ?? '',
            $row['beneficiaire'] ?? '',
            $row['garnison'] ?? '',
            $row['province'] ?? '',
            $traductions_categories[$row['categorie']] ?? ($row['categorie'] ?? ''),
            $zdef['value'] ?? ''
        ];
        $serie++;
    }

    return $normalized;
}

function get_non_vus_matricule_snapshot($rows)
{
    $snapshot = [];
    foreach ($rows as $row) {
        $matricule = (string)($row[1] ?? '');
        if ($matricule !== '') {
            $snapshot[] = $matricule;
        }
    }
    sort($snapshot);
    return $snapshot;
}

function filter_new_non_vus_rows($current_rows, $previous_snapshot)
{
    $previous_map = [];
    foreach ((array)$previous_snapshot as $matricule) {
        $previous_map[(string)$matricule] = true;
    }

    $new_rows = [];
    foreach ($current_rows as $row) {
        $matricule = (string)($row[1] ?? '');
        if ($matricule !== '' && !isset($previous_map[$matricule])) {
            $new_rows[] = $row;
        }
    }

    $reindexed = [];
    $index = 1;
    foreach ($new_rows as $row) {
        $row[0] = $index;
        $reindexed[] = $row;
        $index++;
    }

    return $reindexed;
}

function build_csv_content($headers, $rows, $delimiter = ';')
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, "\xEF\xBB\xBF");

    fputcsv($stream, $headers, $delimiter);
    foreach ($rows as $row) {
        fputcsv($stream, $row, $delimiter);
    }

    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    return $csv;
}

function escape_xml_value($value)
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function xlsx_column_letter($column_number)
{
    $letter = '';
    while ($column_number > 0) {
        $mod = ($column_number - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $column_number = (int)(($column_number - $mod) / 26);
    }
    return $letter;
}

function build_xlsx_content($headers, $rows, $sheet_name = 'Donnees')
{
    $sheet_rows = array_merge([$headers], $rows);

    $sheet_xml_rows = '';
    $row_index = 1;
    foreach ($sheet_rows as $row) {
        $sheet_xml_rows .= '<row r="' . $row_index . '">';
        $col_index = 1;
        foreach ($row as $value) {
            $cell_ref = xlsx_column_letter($col_index) . $row_index;
            $value_str = (string)$value;
            if (is_numeric($value_str) && !preg_match('/^0\d+$/', $value_str)) {
                $sheet_xml_rows .= '<c r="' . $cell_ref . '" t="n"><v>' . escape_xml_value($value_str) . '</v></c>';
            } else {
                $sheet_xml_rows .= '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . escape_xml_value($value_str) . '</t></is></c>';
            }
            $col_index++;
        }
        $sheet_xml_rows .= '</row>';
        $row_index++;
    }

    $sheet_name_safe = preg_replace('/[^A-Za-z0-9_\- ]/u', '_', $sheet_name);
    if ($sheet_name_safe === '') {
        $sheet_name_safe = 'Donnees';
    }

    $sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $sheet_xml_rows . '</sheetData>'
        . '</worksheet>';

    $workbook_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . escape_xml_value($sheet_name_safe) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $content_types_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';

    $rels_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook_rels_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        return false;
    }

    $zip->addFromString('[Content_Types].xml', $content_types_xml);
    $zip->addFromString('_rels/.rels', $rels_xml);
    $zip->addFromString('xl/workbook.xml', $workbook_xml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels_xml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet_xml);
    $zip->close();

    $xlsx_content = file_get_contents($tmp);
    @unlink($tmp);
    return $xlsx_content;
}

function create_incremental_backup_archive($datasets)
{
    $backup_dir = get_backup_dir_path();
    $zip_file = $backup_dir . 'backup_consolide_latest.zip';

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        error_log("Impossible de créer l'archive de sauvegarde consolidée.");
        return false;
    }

    $manifest = [
        'updated_at' => date('c'),
        'mode' => 'consolidated_latest',
        'datasets' => []
    ];

    foreach ($datasets as $dataset_key => $dataset) {
        $headers = $dataset['headers'];
        $rows = $dataset['rows'];
        $csv = build_csv_content($headers, $rows, ';');
        $xlsx = build_xlsx_content($headers, $rows, $dataset['sheet_name'] ?? ucfirst($dataset_key));

        $zip->addFromString($dataset_key . '.csv', $csv);
        if ($xlsx !== false) {
            $zip->addFromString($dataset_key . '.xlsx', $xlsx);
        }

        $manifest['datasets'][$dataset_key] = [
            'rows' => count($rows),
            'formats' => $xlsx !== false ? ['csv', 'xlsx'] : ['csv']
        ];
    }

    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $zip->close();

    return $zip_file;
}

/**
 * Purge les archives ZIP de sauvegarde:
 * - supprime les archives identiques (même hash, conserve la plus récente)
 * - supprime les archives de plus de $max_days jours
 * - conserve uniquement les $max_unique_keep archives non identiques les plus récentes
 *
 * @param int $max_unique_keep
 * @param int $max_days Nombre de jours avant suppression automatique (défaut: 60)
 * @return array
 */
function purge_backup_archives($max_unique_keep = 30, $max_days = 60)
{
    $backup_dir = get_backup_dir_path();
    $files = glob($backup_dir . '*.zip') ?: [];

    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    $seen_hashes = [];
    $unique_kept = [];
    $to_delete = [];
    $deleted_duplicates = 0;
    $deleted_overflow = 0;
    $deleted_expired = 0;
    $cutoff_time = time() - ($max_days * 86400);

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        // Supprimer les fichiers de plus de $max_days jours
        if (filemtime($file) < $cutoff_time) {
            $to_delete[] = $file;
            $deleted_expired++;
            continue;
        }

        $hash = @hash_file('sha256', $file);
        if ($hash === false) {
            continue;
        }

        if (isset($seen_hashes[$hash])) {
            $to_delete[] = $file;
            $deleted_duplicates++;
            continue;
        }

        $seen_hashes[$hash] = $file;
        $unique_kept[] = $file;

        if (count($unique_kept) > (int)$max_unique_keep) {
            $to_delete[] = $file;
            $deleted_overflow++;
        }
    }

    $deleted_files = [];
    foreach ($to_delete as $file) {
        if (@unlink($file)) {
            $deleted_files[] = $file;
        }
    }

    return [
        'max_unique_keep' => (int)$max_unique_keep,
        'max_days' => (int)$max_days,
        'scanned' => count($files),
        'unique_kept' => min(count($unique_kept), (int)$max_unique_keep),
        'deleted_total' => count($deleted_files),
        'deleted_duplicates' => $deleted_duplicates,
        'deleted_overflow' => $deleted_overflow,
        'deleted_expired' => $deleted_expired,
        'deleted_files' => $deleted_files
    ];
}

/**
 * Exécute la sauvegarde consolidée (8h) :
 * - seulement si intervalle atteint (sauf force=true)
 * - met à jour un ZIP unique contenant l'ensemble des données à jour
 */
function maybe_create_backup($force = false)
{
    global $pdo;

    $state = read_backup_state();
    $now = time();
    $interval = get_backup_interval_seconds();

    if (!$force && ($now - (int)$state['last_backup_at']) < $interval) {
        return [
            'created' => false,
            'reason' => 'interval_not_elapsed',
            'next_run_in_seconds' => $interval - ($now - (int)$state['last_backup_at'])
        ];
    }

    $control_fields = ['id', 'matricule', 'type_controle', 'nom_beneficiaire', 'new_beneficiaire', 'lien_parente', 'date_controle', 'mention', 'observations', 'cree_le'];
    $non_vus_fields = ['SERIE', 'MATRICULE', 'NOMS', 'GRADE', 'UNITE', 'BENEFICIAIRE', 'GARNISON', 'PROVINCE', 'CATEGORIE', 'ZDEF'];

    $all_controles = fetch_full_rows_for_table('controles', $control_fields);
    $current_non_vus = get_non_vus_rows_for_backup();
    $current_non_vus_snapshot = get_non_vus_matricule_snapshot($current_non_vus);

    $datasets = [
        'controles' => [
            'headers' => $control_fields,
            'rows' => $all_controles,
            'sheet_name' => 'Controles'
        ],
        'non_vus' => [
            'headers' => $non_vus_fields,
            'rows' => $current_non_vus,
            'sheet_name' => 'NonVus'
        ]
    ];

    $zip_file = create_incremental_backup_archive($datasets);
    if ($zip_file === false) {
        return [
            'created' => false,
            'reason' => 'zip_creation_failed'
        ];
    }

    $max_control_id = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM controles")->fetchColumn();

    $state['last_backup_at'] = $now;
    $state['last_run_at'] = $now;
    $state['last_control_id'] = $max_control_id;
    $state['non_vus_snapshot'] = $current_non_vus_snapshot;
    write_backup_state($state);

    return [
        'created' => true,
        'reason' => 'backup_updated',
        'file' => $zip_file,
        'counts' => [
            'controles' => count($all_controles),
            'non_vus' => count($current_non_vus)
        ]
    ];
}

/**
 * Wrapper de compatibilité avec l'ancien appel.
 */
function generate_backup($include_non_vus = true)
{
    $result = maybe_create_backup(true);
    return (bool)($result['created'] ?? false);
}

/**
 * Nettoie les caches et données temporaires de l'application.
 * Exécutable via cron (includes/cache_cleanup.php) ou manuellement.
 *
 * Cibles nettoyées :
 * - Fichiers temporaires XLSX orphelins (sys_get_temp_dir())
 * - Fichier verrou de sauvegarde obsolète (> 1h)
 * - Tokens "remember me" expirés (table utilisateurs)
 * - Tokens de reset de mot de passe expirés (table utilisateurs)
 * - Logs anciens (> $jours_logs jours)
 *
 * @param int $jours_logs Nombre de jours de logs à conserver (défaut: 90)
 * @return array Rapport détaillé du nettoyage
 */
function nettoyer_caches($jours_logs = 90)
{
    global $pdo;

    $rapport = [
        'timestamp' => date('c'),
        'temp_xlsx_supprimes' => 0,
        'lock_files_supprimes' => 0,
        'remember_tokens_expires' => 0,
        'reset_tokens_expires' => 0,
        'logs_supprimes' => 0,
        'erreurs' => []
    ];

    // 1. Nettoyer les fichiers temporaires XLSX orphelins (> 1h)
    try {
        $tempDir = sys_get_temp_dir();
        $cutoff = time() - 3600;
        $pattern = $tempDir . DIRECTORY_SEPARATOR . 'xlsx_*';
        $tempFiles = glob($pattern) ?: [];
        foreach ($tempFiles as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $rapport['temp_xlsx_supprimes']++;
                }
            }
        }
    } catch (\Exception $e) {
        $rapport['erreurs'][] = 'temp_xlsx: ' . $e->getMessage();
    }

    // 2. Supprimer le fichier verrou de sauvegarde obsolète (> 1h)
    try {
        $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_cron.lock';
        if (is_file($lockFile) && filemtime($lockFile) < (time() - 3600)) {
            if (@unlink($lockFile)) {
                $rapport['lock_files_supprimes']++;
            }
        }
    } catch (\Exception $e) {
        $rapport['erreurs'][] = 'lock_file: ' . $e->getMessage();
    }

    // 3. Purger les remember_tokens expirés
    try {
        $stmt = $pdo->prepare(
            "UPDATE utilisateurs SET remember_token = NULL, remember_token_expires = NULL 
             WHERE remember_token IS NOT NULL AND remember_token_expires < NOW()"
        );
        $stmt->execute();
        $rapport['remember_tokens_expires'] = $stmt->rowCount();
    } catch (\PDOException $e) {
        $rapport['erreurs'][] = 'remember_tokens: ' . $e->getMessage();
    }

    // 4. Purger les reset_tokens expirés
    try {
        $stmt = $pdo->prepare(
            "UPDATE utilisateurs SET reset_token = NULL, reset_expires = NULL 
             WHERE reset_token IS NOT NULL AND reset_expires < NOW()"
        );
        $stmt->execute();
        $rapport['reset_tokens_expires'] = $stmt->rowCount();
    } catch (\PDOException $e) {
        $rapport['erreurs'][] = 'reset_tokens: ' . $e->getMessage();
    }

    // 5. Nettoyer les anciens logs
    try {
        $rapport['logs_supprimes'] = nettoyer_anciens_logs($jours_logs);
    } catch (\Exception $e) {
        $rapport['erreurs'][] = 'logs: ' . $e->getMessage();
    }

    $total = $rapport['temp_xlsx_supprimes']
        + $rapport['lock_files_supprimes']
        + $rapport['remember_tokens_expires']
        + $rapport['reset_tokens_expires']
        + $rapport['logs_supprimes'];

    if ($total > 0) {
        error_log(
            "Nettoyage caches: temp_xlsx=" . $rapport['temp_xlsx_supprimes']
                . " | lock=" . $rapport['lock_files_supprimes']
                . " | remember_tokens=" . $rapport['remember_tokens_expires']
                . " | reset_tokens=" . $rapport['reset_tokens_expires']
                . " | logs=" . $rapport['logs_supprimes']
        );
    }

    return $rapport;
}
