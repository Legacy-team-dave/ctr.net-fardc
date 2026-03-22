<?php

/**
 * Fichier : includes/functions.php
 * Description : Fonctions utilitaires pour l'application
 * Version : 3.2 – sauvegarde auto CSV/Excel/PDF, logs pour CONTROLEUR
 */

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Définir l'encodage par défaut
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// ============================================
//  INCLUSION DES BIBLIOTHÈQUES EXTERNES (si disponibles)
// ============================================
$use_excel = false;
$use_pdf = false;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $use_excel = true;
    }
    if (class_exists('Dompdf\Dompdf')) {
        $use_pdf = true;
    }
}

/**
 * ============================================
 * FONCTIONS DE FLASH MESSAGES
 * ============================================
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

function check_logs_table()
{
    global $pdo;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'logs'");
        if ($check->rowCount() == 0) {
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
            migrate_logs_data();
            return true;
        }
    } catch (PDOException $e) {
        error_log("❌ Erreur vérification/création table logs: " . $e->getMessage());
    }
    return false;
}

function audit_action($action, $table = null, $record_id = null, $details = null)
{
    $profils_a_logger = ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR']; // Ajout de CONTROLEUR
    $user_profil = isset($_SESSION['user_profil']) ? strtoupper(trim($_SESSION['user_profil'])) : '';

    if (in_array($user_profil, $profils_a_logger, true)) {
        return log_action($action, $table, $record_id, $details);
    }
    return false;
}

function migrate_logs_data()
{
    global $pdo;
    try {
        $check_old = $pdo->query("SHOW TABLES LIKE 'logs_actions'");
        if ($check_old->rowCount() > 0) {
            $count = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
            if ($count == 0) {
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

function check_remember_columns()
{
    global $pdo;
    $created = false;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM utilisateurs LIKE 'remember_token'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN remember_token VARCHAR(255) NULL AFTER preferences");
            error_log("✅ Colonne 'remember_token' créée avec succès");
            $created = true;
        }
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

check_logs_table();
check_preferences_column();
check_remember_columns();

/**
 * ============================================
 * FONCTIONS DE JOURNALISATION (LOGS)
 * ============================================
 */

function log_action($action, $table = null, $record_id = null, $details = null)
{
    global $pdo;

    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    if ($user_agent && strlen($user_agent) > 65535) {
        $user_agent = substr($user_agent, 0, 65535);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO logs 
            (id_utilisateur, action, table_concernee, id_enregistrement, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$user_id, $action, $table, $record_id, $details, $ip_address, $user_agent]);
        if ($result) return true;
    } catch (PDOException $e) {
        try {
            check_logs_actions_table();
            $stmt = $pdo->prepare("INSERT INTO logs_actions 
                (id_utilisateur, action, table_concernee, id_enregistrement, details, adresse_ip) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$user_id, $action, $table, $record_id, $details, $ip_address]);
            if ($result) return true;
        } catch (PDOException $e2) {
            error_log("❌ Erreur log_action (logs et logs_actions): " . $e2->getMessage());
            error_log("   Action: $action, Table: $table, ID: $record_id, User: $user_id");
        }
    }
    return false;
}

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

function has_role($role)
{
    return isset($_SESSION['user_profil']) && $_SESSION['user_profil'] === $role;
}

function is_admin()
{
    return has_role('ADMIN_IG');
}

function check_profil($profils_autorises)
{
    if (!is_array($profils_autorises)) {
        $profils_autorises = [$profils_autorises];
    }
    $profils_autorises = array_map('strtoupper', $profils_autorises);
    if (!isset($_SESSION['user_profil'])) {
        redirect_with_flash('index.php', 'danger', 'Session invalide. Veuillez vous reconnecter.');
    }
    $user_profil = trim(strtoupper($_SESSION['user_profil']));
    if (!in_array($user_profil, $profils_autorises)) {
        redirect_with_flash('index.php', 'danger', 'Accès refusé. Veuillez contacter l\'administrateur si vous pensez avoir les droits nécessaires.');
    }
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

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
    $inactive_time = 3600;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive_time)) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

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

function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function h($str)
{
    return e($str);
}

function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

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

function format_filesize($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

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

function upload_avatar($file, $user_id)
{
    $target_dir = dirname(__DIR__, 2) . '/assets/uploads/avatars/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 2 * 1024 * 1024;
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
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
    $target_file = $target_dir . $filename;
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

function is_ajax_request()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

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
    'INTEGRES',
    'RETRAITES',
    'DCD_AV_BIO',
    'DCD_AP_BIO',
    'ACTIF'
];

$traductions_categories = [
    'ACTIF' => 'Actif',
    'DCD_AP_BIO' => 'Décédé Après Bio',
    'INTEGRES' => 'Intégré',
    'RETRAITES' => 'Retraité',
    'DCD_AV_BIO' => 'Décédé Avant Bio'
];

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
 * Construit un CSV avec des lignes de titre et des données
 */
function build_csv_with_titles($headerLines, $headers, $rows)
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, "\xEF\xBB\xBF");
    foreach ($headerLines as $line) {
        fputcsv($stream, $line);
    }
    fputcsv($stream, []);
    fputcsv($stream, $headers);
    foreach ($rows as $row) {
        fputcsv($stream, $row);
    }
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);
    return $content;
}

/**
 * Génère un fichier Excel (XLSX) à partir des données
 */
/**
 * Génère un fichier Excel (XLSX) à partir des données
 * Utilise PhpSpreadsheet – version robuste avec setCellValue()
 */
function generate_excel_from_data($headerLines, $headers, $rows, $sheetTitle)
{
    global $use_excel;
    if (!$use_excel) return null;

    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        $rowIndex = 1;

        // Lignes de titre (en gras)
        foreach ($headerLines as $line) {
            $colIndex = 1;
            foreach ($line as $cellValue) {
                $cellCoord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
                $sheet->setCellValue($cellCoord, $cellValue);
                $sheet->getStyle($cellCoord)->getFont()->setBold(true);
                $colIndex++;
            }
            $rowIndex++;
        }

        $rowIndex++; // ligne vide

        // En‑têtes des colonnes
        $colIndex = 1;
        foreach ($headers as $header) {
            $cellCoord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
            $sheet->setCellValue($cellCoord, $header);
            $sheet->getStyle($cellCoord)->getFont()->setBold(true);
            $colIndex++;
        }
        $rowIndex++;

        // Données
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($row as $cellValue) {
                $cellCoord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
                $sheet->setCellValue($cellCoord, $cellValue);
                $colIndex++;
            }
            $rowIndex++;
        }

        // Ajustement automatique des colonnes
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    } catch (Exception $e) {
        error_log("Erreur génération Excel : " . $e->getMessage());
        return null;
    }
}

/**
 * Récupère les données des non-vus formatées pour l'export
 */
function get_non_vus_data_for_export()
{
    global $pdo;

    // Valeurs par défaut robustes, même si les globales ne sont pas définies
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
        'INTEGRES',
        'RETRAITES',
        'DCD_AV_BIO',
        'DCD_AP_BIO',
        'ACTIF'
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

    $rows = [];
    foreach ($non_vus_raw as $index => $m) {
        $zdef = getZdefValue($m['province']);
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
            $zdef['value']
        ];
    }
    return ['rows' => $rows, 'raw' => $non_vus_raw];
}
/**
 * Génère le contenu CSV des militaires non contrôlés (non‑vus) – conservé pour compatibilité
 */
function get_non_vus_csv_content()
{
    $data = get_non_vus_data_for_export();
    $headerLines = [
        ['MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS'],
        ['INSPECTORAT GENERAL DES FARDC'],
        ['LISTE DES MILITAIRES NON-VUS AU CONTRÔLE (Sauvegarde du ' . date('Y-m-d_H-i-s') . ')']
    ];
    $headers = ['SERIE', 'MATRICULE', 'NOMS', 'GRADE', 'UNITE', 'BENEFICIAIRE', 'GARNISON', 'PROVINCE', 'CATEGORIE', 'ZDEF'];
    return build_csv_with_titles($headerLines, $headers, $data['rows']);
}

/**
 * Génère une archive ZIP contenant les tables controles, litiges et les non‑vus
 * en formats CSV, XLSX (si PhpSpreadsheet dispo) et PDF (si Dompdf dispo)
 */
function generate_backup($include_non_vus = true)
{
    global $pdo, $use_excel, $use_pdf;
    $backup_dir = dirname(__DIR__, 2) . '/backups/';
    if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);

    $timestamp = date('Y-m-d_H-i-s');
    $zip_file = $backup_dir . 'backup_' . $timestamp . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE) !== true) {
        error_log("Impossible de créer l'archive de sauvegarde.");
        return false;
    }

    $tables = [
        'controles' => [
            'title' => 'LISTE DES CONTRÔLES EFFECTUÉS',
            'fields' => [
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
            ]
        ],
        'litiges' => [
            'title' => 'LISTE DES LITIGES ENREGISTRÉS',
            'fields' => [
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
            ]
        ]
    ];

    foreach ($tables as $table => $config) {
        try {
            $check = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($check->rowCount() == 0) continue;
            $stmt = $pdo->query("SELECT * FROM $table");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) continue;

            $headerLines = [
                ['MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS'],
                ['INSPECTORAT GENERAL DES FARDC'],
                [$config['title'] . ' (Sauvegarde du ' . $timestamp . ')']
            ];
            $headers = $config['fields'];
            $dataRows = [];
            foreach ($rows as $row) {
                $orderedRow = [];
                foreach ($headers as $col) {
                    $orderedRow[] = $row[$col] ?? '';
                }
                $dataRows[] = $orderedRow;
            }

            // CSV
            $csv_content = build_csv_with_titles($headerLines, $headers, $dataRows);
            $zip->addFromString($table . '_' . $timestamp . '.csv', $csv_content);

            // Excel
            if ($use_excel) {
                $excel_content = generate_excel_from_data($headerLines, $headers, $dataRows, $table);
                if ($excel_content !== null) {
                    $zip->addFromString($table . '_' . $timestamp . '.xlsx', $excel_content);
                }
            }

            // PDF
            if ($use_pdf) {
                $pdf_content = generate_pdf_from_data($headerLines, $headers, $dataRows, $config['title']);
                if ($pdf_content !== null) {
                    $zip->addFromString($table . '_' . $timestamp . '.pdf', $pdf_content);
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur export $table : " . $e->getMessage());
        }
    }

    if ($include_non_vus) {
        try {
            $non_vus_data = get_non_vus_data_for_export();
            if (!empty($non_vus_data['rows'])) {
                $headerLines = [
                    ['MINISTERE DE LA DEFENSE NATIONALE ET ANCIENS COMBATTANTS'],
                    ['INSPECTORAT GENERAL DES FARDC'],
                    ['LISTE DES MILITAIRES NON-VUS AU CONTRÔLE (Sauvegarde du ' . $timestamp . ')']
                ];
                $headers = ['SERIE', 'MATRICULE', 'NOMS', 'GRADE', 'UNITE', 'BENEFICIAIRE', 'GARNISON', 'PROVINCE', 'CATEGORIE', 'ZDEF'];
                $dataRows = $non_vus_data['rows'];

                // CSV
                $csv_content = build_csv_with_titles($headerLines, $headers, $dataRows);
                $zip->addFromString('non_vus_' . $timestamp . '.csv', $csv_content);

                // Excel
                if ($use_excel) {
                    $excel_content = generate_excel_from_data($headerLines, $headers, $dataRows, 'non_vus');
                    if ($excel_content !== null) {
                        $zip->addFromString('non_vus_' . $timestamp . '.xlsx', $excel_content);
                    }
                }
                /**
                 * Génère un fichier PDF à partir des données
                 * Utilise Dompdf – version robuste
                 */
                function generate_pdf_from_data($headerLines, $headers, $rows, $title)
                {
                    global $use_pdf;
                    if (!$use_pdf) return null;

                    try {
                        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($title) . '</title>
            <style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; margin: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header p { margin: 2px 0; }
                table { border-collapse: collapse; width: 100%; margin-top: 10px; }
                th, td { border: 1px solid #000; padding: 5px; text-align: left; vertical-align: top; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .footer { margin-top: 20px; font-size: 8pt; text-align: center; }
            </style>
        </head>
        <body>';

                        foreach ($headerLines as $line) {
                            $html .= '<div class="header">';
                            foreach ($line as $text) {
                                $html .= '<p><strong>' . htmlspecialchars($text) . '</strong></p>';
                            }
                            $html .= '</div>';
                        }

                        $html .= '<table><thead><tr>';
                        foreach ($headers as $header) {
                            $html .= '<th>' . htmlspecialchars($header) . '</th>';
                        }
                        $html .= '</tr></thead><tbody>';

                        foreach ($rows as $row) {
                            $html .= '<tr>';
                            foreach ($row as $cell) {
                                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
                            }
                            $html .= '</tr>';
                        }

                        $html .= '</tbody></table>';
                        $html .= '<div class="footer">Généré le ' . date('d/m/Y H:i:s') . '</div>';
                        $html .= '</body></html>';

                        // Initialisation de Dompdf avec des options
                        $options = new \Dompdf\Options();
                        $options->set('defaultFont', 'DejaVu Sans');
                        $options->set('isRemoteEnabled', false);
                        $dompdf = new \Dompdf\Dompdf($options);
                        $dompdf->loadHtml($html);
                        $dompdf->setPaper('A4', 'portrait');
                        $dompdf->render();
                        return $dompdf->output();
                    } catch (Exception $e) {
                        error_log("Erreur génération PDF : " . $e->getMessage());
                        return null;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur export non-vus : " . $e->getMessage());
        }
    }

    $zip->close();
    return $zip_file;
}

/**
 * Récupère l'horodatage de la dernière sauvegarde (non utilisé par le cron, conservé pour compatibilité)
 */
function get_last_backup_time()
{
    $file = dirname(__DIR__, 2) . '/backups/last_backup.txt';
    if (file_exists($file)) return (int) file_get_contents($file);
    return 0;
}

/**
 * Met à jour l'horodatage de la dernière sauvegarde (non utilisé par le cron, conservé pour compatibilité)
 */
function update_last_backup_time()
{
    $file = dirname(__DIR__, 2) . '/backups/last_backup.txt';
    file_put_contents($file, time());
}

/**
 * Vérifie si une sauvegarde doit être effectuée (toutes les 2 minutes) et l'exécute.
 * Cette fonction n'est plus appelée automatiquement ; elle est conservée pour une éventuelle utilisation manuelle.
 */
function maybe_create_backup()
{
    $last_backup = get_last_backup_time();
    $now = time();
    if (($now - $last_backup) >= 120) {
        generate_backup(true);
        update_last_backup_time();
        error_log("Sauvegarde automatique exécutée (intervalle 2 minutes).");
    }
}

// =========================================================================
//  ATTENTION : l'appel à maybe_create_backup() a été désactivé.
//  La sauvegarde automatique ne dépend plus des visites sur le site.
//  Pour la déclencher périodiquement, utilisez le script backup_cron.php
//  via une tâche cron.
// =========================================================================
// maybe_create_backup();

// Programme de nettoyage automatique (1% de chance à chaque chargement)
// if (rand(1, 100) == 1 && is_admin()) { 
//     nettoyer_anciens_logs(90); 
// }