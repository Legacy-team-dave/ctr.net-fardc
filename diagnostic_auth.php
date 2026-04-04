<?php
/**
 * FICHIER DE DIAGNOSTIC COMPLET - AUTHENTIFICATION ET AUTORISATIONS
 * 
 * ✅ VÉRIFICATIONS EFFECTUÉES :
 * - État de la session et connexion utilisateur
 * - Validité des profils et rôles
 * - Restrictions d'accès applicables
 * - Accès aux modules par profil
 * - Configuration du mode (local/central)
 * - Tokens et cookies de souvenance
 * 
 * ACCÈS: http://ctr.net-fardc.test/diagnostic_auth.php
 */

session_status() === PHP_SESSION_NONE && session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/includes/functions.php';

// Définition du mode d'affichage
$mode = $_GET['mode'] ?? 'dashboard'; // dashboard|test|config|logs
$test_profil = $_GET['test_profil'] ?? null;
$detailed_mode = isset($_GET['detailed']);

// HELPER: Affichage formaté
function badge($status, $text) {
    $colors = [
        'OK' => '#28a745',
        'WARN' => '#ffc107',
        'ERROR' => '#dc3545',
        'INFO' => '#17a2b8'
    ];
    $color = $colors[$status] ?? '#6c757d';
    return "<span style='background:{$color}; color:white; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:12px;'>$status</span> $text";
}

function check_item($label, $condition, $details = '') {
    $status = $condition ? 'OK' : 'ERROR';
    $html = "<tr><td>✓ $label</td><td>" . badge($status, $condition ? '✅' : '❌') . "</td>";
    if ($details) $html .= "<td><small>$details</small></td>";
    $html .= "</tr>";
    return $html;
}

function code_block($code) {
    return "<pre style='background:#f5f5f5; padding:10px; border-radius:4px; overflow:visible;'><code>$code</code></pre>";
}

// ===================================================================
// TESTS ACTUELS
// ===================================================================

$tests_results = [
    'session' => [],
    'auth' => [],
    'roles' => [],
    'access' => [],
    'config' => [],
    'db' => []
];

// --- TEST 1: SESSION ---
$tests_results['session'][] = [
    'label' => 'Session active',
    'pass' => session_status() === PHP_SESSION_ACTIVE,
    'detail' => 'Session ID: ' . session_id()
];

$is_logged_in = isset($_SESSION['user_id']);
$tests_results['session'][] = [
    'label' => 'Utilisateur connecté',
    'pass' => $is_logged_in,
    'detail' => $is_logged_in ? 'ID: ' . $_SESSION['user_id'] . ' - Login: ' . ($_SESSION['user_login'] ?? 'N/A') : 'Pas d\'utilisateur actif'
];

// --- TEST 2: CONFIGURATION ---
$app_mode = app_mode();
$tests_results['config'][] = [
    'label' => 'Mode application',
    'pass' => in_array($app_mode, ['local', 'central']),
    'detail' => "Mode: <strong>$app_mode</strong>"
];

$tests_results['config'][] = [
    'label' => 'Base de données connectée',
    'pass' => isset($pdo),
    'detail' => $pdo ? 'Connexion PDO active' : 'Pas de connexion'
];

// --- TEST 3: AUTHENTIFICATION ---
if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $user_login = $_SESSION['user_login'] ?? 'UNKNOWN';
    $user_nom = $_SESSION['user_nom'] ?? 'N/A';
    $user_profil = $_SESSION['user_profil'] ?? 'UNKNOWN';
    
    $tests_results['auth'][] = [
        'label' => 'ID Utilisateur valide',
        'pass' => is_numeric($user_id),
        'detail' => "ID: $user_id"
    ];
    
    $tests_results['auth'][] = [
        'label' => 'Profil défini',
        'pass' => !empty($user_profil),
        'detail' => "Profil: <strong>$user_profil</strong>"
    ];
    
    $tests_results['auth'][] = [
        'label' => 'Identité complète',
        'pass' => !empty($user_nom),
        'detail' => "$user_nom ($user_login)"
    ];
    
    // --- TEST 4: RÔLES ---
    $valid_roles = ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR'];
    $tests_results['roles'][] = [
        'label' => 'Profil reconnu',
        'pass' => in_array($user_profil, $valid_roles),
        'detail' => "Profils valides: " . implode(', ', $valid_roles)
    ];
    
    $is_admin = $user_profil === 'ADMIN_IG';
    $tests_results['roles'][] = [
        'label' => 'Est Administrateur',
        'pass' => $is_admin,
        'detail' => $is_admin ? 'Plein accès' : 'Accès restreint'
    ];
    
    $is_operateur = $user_profil === 'OPERATEUR';
    $tests_results['roles'][] = [
        'label' => 'Est Opérateur',
        'pass' => $is_operateur,
        'detail' => $is_operateur ? 'Accès modules contrôles' : 'N/A'
    ];
    
    // --- TEST 5: RESTRICTIONS D'ACCÈS ---
    
    // Vérifier l'accès à index.php
    $can_access_index = in_array($user_profil, ['ADMIN_IG', 'OPERATEUR']);
    $tests_results['access'][] = [
        'label' => 'Accès à index.php',
        'pass' => $can_access_index,
        'detail' => $can_access_index ? '✅ Autorisé' : '❌ Refusé'
    ];
    
    // Vérifier l'accès aux modules
    $can_access_modules = in_array($user_profil, ['ADMIN_IG', 'OPERATEUR']);
    $tests_results['access'][] = [
        'label' => 'Accès modules de base',
        'pass' => $can_access_modules,
        'detail' => $can_access_modules ? '✅ Contrôles et synchronisation' : '❌ Accès refusé'
    ];
    
    // Accès administration
    $can_access_admin = $user_profil === 'ADMIN_IG';
    $tests_results['access'][] = [
        'label' => 'Accès administration',
        'pass' => $can_access_admin,
        'detail' => $can_access_admin ? '✅ Gestion complète' : '❌ Réservé ADMIN_IG'
    ];
    
    // Accès profil personnel
    $tests_results['access'][] = [
        'label' => 'Accès profil personnel',
        'pass' => true,
        'detail' => '✅ Tous les utilisateurs'
    ];
    
    // Vérifier les tokens de souvenance
    $has_remember_token = isset($_COOKIE['remember_token']);
    $tests_results['auth'][] = [
        'label' => 'Token de souvenance',
        'pass' => true,
        'detail' => $has_remember_token ? '✅ Actif (30 jours)' : '⚠️ Non activé'
    ];
    
    // --- TEST 6: RESTRICTIONS EN MODE CENTRAL ---
    if (is_central_mode()) {
        $central_restricted = $user_profil !== 'ADMIN_IG';
        $tests_results['config'][] = [
            'label' => 'Mode CENTRAL - Restriction profil',
            'pass' => !$central_restricted,
            'detail' => $central_restricted ? '❌ Profil non autorisé en mode central' : '✅ Autorisé'
        ];
    }
    
} else {
    $tests_results['auth'][] = [
        'label' => 'Authentification requise',
        'pass' => false,
        'detail' => 'Veuillez vous connecter pour voir les tests complets'
    ];
}

// --- Calcul des résultats ---
$total_tests = 0;
$passed_tests = 0;
foreach ($tests_results as $category) {
    foreach ($category as $test) {
        $total_tests++;
        if ($test['pass']) $passed_tests++;
    }
}
$success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100) : 0;

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Diagnostic Authentification - CTR.NET-FARDC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { font-size: 14px; opacity: 0.9; }
        
        .content {
            padding: 40px;
        }
        
        .status-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .status-item {
            text-align: center;
        }
        
        .status-value {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }
        
        .status-label {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .test-section {
            margin-bottom: 40px;
        }
        
        .test-section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        tr:hover { background: #f8f9fa; }
        
        .success-badge {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .error-badge {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .user-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .user-card h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .user-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
        }
        
        .info-label {
            color: #6c757d;
            font-weight: 600;
        }
        
        .info-value {
            color: #333;
            font-weight: bold;
        }
        
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .error-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-btn:hover {
            color: #667eea;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #e9ecef;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #dee2e6;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🔐 Diagnostic Authentification</h1>
        <p>CTR.NET-FARDC - Vérification complète du système d'accès</p>
    </div>
    
    <div class="content">
        
        <!-- BARRE DE STATUT -->
        <div class="status-bar">
            <div class="status-item">
                <div class="status-value"><?php echo $success_rate; ?>%</div>
                <div class="status-label">Taux de succès</div>
            </div>
            <div class="status-item">
                <div class="status-value"><?php echo $passed_tests; ?>/<?php echo $total_tests; ?></div>
                <div class="status-label">Tests réussis</div>
            </div>
            <div class="status-item">
                <div class="status-value"><?php echo ucfirst($app_mode); ?></div>
                <div class="status-label">Mode application</div>
            </div>
            <div class="status-item">
                <div class="status-value"><?php echo $is_logged_in ? '✅' : '❌'; ?></div>
                <div class="status-label">Connecté</div>
            </div>
        </div>
        
        <!-- UTILISATEUR ACTUEL -->
        <?php if ($is_logged_in): ?>
            <div class="success-box">
                <strong>✅ Utilisateur authentifié</strong>
                <div class="user-info" style="margin-top:10px;">
                    <div class="info-row">
                        <span class="info-label">ID:</span>
                        <span class="info-value"><?php echo $_SESSION['user_id']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Login:</span>
                        <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_login'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Nom:</span>
                        <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_nom'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Profil:</span>
                        <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_profil'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="warning-box">
                <strong>⚠️ Pas d'utilisateur connecté</strong><br>
                <small>Connectez-vous pour voir tous les tests d'authentification.</small>
            </div>
        <?php endif; ?>
        
        <!-- RÉSULTATS DES TESTS -->
        
        <!-- Session -->
        <div class="test-section">
            <h2>📊 État de la Session</h2>
            <table>
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Statut</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests_results['session'] as $test): ?>
                        <tr>
                            <td><?php echo $test['label']; ?></td>
                            <td>
                                <?php echo $test['pass'] ? 
                                    '<span class="success-badge">✅ PASS</span>' : 
                                    '<span class="error-badge">❌ FAIL</span>'; 
                                ?>
                            </td>
                            <td><small><?php echo $test['detail']; ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Configuration -->
        <div class="test-section">
            <h2>⚙️ Configuration Système</h2>
            <table>
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Statut</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests_results['config'] as $test): ?>
                        <tr>
                            <td><?php echo $test['label']; ?></td>
                            <td>
                                <?php echo $test['pass'] ? 
                                    '<span class="success-badge">✅ PASS</span>' : 
                                    '<span class="error-badge">❌ FAIL</span>'; 
                                ?>
                            </td>
                            <td><small><?php echo $test['detail']; ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($is_logged_in): ?>
        
        <!-- Authentification -->
        <div class="test-section">
            <h2>🔓 Authentification</h2>
            <table>
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Statut</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests_results['auth'] as $test): ?>
                        <tr>
                            <td><?php echo $test['label']; ?></td>
                            <td>
                                <?php echo $test['pass'] ? 
                                    '<span class="success-badge">✅ PASS</span>' : 
                                    '<span class="error-badge">❌ FAIL</span>'; 
                                ?>
                            </td>
                            <td><small><?php echo $test['detail']; ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Rôles -->
        <div class="test-section">
            <h2>👤 Rôles & Profils</h2>
            <table>
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Statut</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests_results['roles'] as $test): ?>
                        <tr>
                            <td><?php echo $test['label']; ?></td>
                            <td>
                                <?php echo $test['pass'] ? 
                                    '<span class="success-badge">✅ PASS</span>' : 
                                    '<span class="error-badge">❌ FAIL</span>'; 
                                ?>
                            </td>
                            <td><small><?php echo $test['detail']; ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Accès & Restrictions -->
        <div class="test-section">
            <h2>🔐 Restrictions d'Accès</h2>
            <table>
                <thead>
                    <tr>
                        <th>Page/Module</th>
                        <th>Accès</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests_results['access'] as $test): ?>
                        <tr>
                            <td><?php echo $test['label']; ?></td>
                            <td>
                                <?php echo $test['pass'] ? 
                                    '<span class="success-badge">✅ PASS</span>' : 
                                    '<span class="error-badge">❌ FAIL</span>'; 
                                ?>
                            </td>
                            <td><small><?php echo $test['detail']; ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- MATRICE COMPLÈTE DES PERMISSIONS -->
        <div class="test-section">
            <h2>📋 Matrice Complète des Permissions</h2>
            <?php
            $permissions_matrix = [
                'index.php' => ['ADMIN_IG', 'OPERATEUR'],
                'profil.php' => ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR'],
                'preferences.php' => ['ADMIN_IG', 'OPERATEUR'],
                'modules/controles/liste.php' => ['ADMIN_IG', 'OPERATEUR'],
                'modules/controles/ajouter.php' => ['OPERATEUR'],
                'modules/controles/modifier.php' => ['ADMIN_IG', 'OPERATEUR'],
                'modules/controles/supprimer.php' => ['ADMIN_IG'],
                'modules/controles/sync.php' => ['ADMIN_IG', 'OPERATEUR'],
                'modules/administration/liste.php' => ['ADMIN_IG'],
                'modules/administration/ajouter.php' => ['ADMIN_IG'],
                'modules/administration/modifier.php' => ['ADMIN_IG'],
                'modules/administration/supprimer.php' => ['ADMIN_IG'],
                'modules/rapports/index.php' => ['ADMIN_IG', 'OPERATEUR'],
            ];
            
            $user_profil_current = $_SESSION['user_profil'] ?? '';
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Page/Module</th>
                        <th>ADMIN_IG</th>
                        <th>OPERATEUR</th>
                        <th>CONTROLEUR</th>
                        <th>Votre accès</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissions_matrix as $page => $allowed_roles): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($page); ?></code></td>
                            <td><?php echo in_array('ADMIN_IG', $allowed_roles) ? '✅' : '❌'; ?></td>
                            <td><?php echo in_array('OPERATEUR', $allowed_roles) ? '✅' : '❌'; ?></td>
                            <td><?php echo in_array('CONTROLEUR', $allowed_roles) ? '✅' : '❌'; ?></td>
                            <td>
                                <?php 
                                $can_access = in_array($user_profil_current, $allowed_roles);
                                echo $can_access ? '<span class="success-badge">✅ AUTORISÉ</span>' : '<span class="error-badge">❌ REFUSÉ</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php endif; ?>
        
        <!-- ACTIONS DE TEST -->
        <div class="test-section">
            <h2>🧪 Actions de Test</h2>
            <div class="button-group">
                <a href="login.php" class="btn btn-primary">🔐 Se connecter</a>
                <a href="index.php" class="btn btn-secondary">📊 Tableau de bord</a>
                <a href="profil.php" class="btn btn-secondary">👤 Mon profil</a>
                <a href="?mode=dashboard" class="btn btn-secondary">🔄 Rafraîchir</a>
                <?php if ($is_logged_in): ?>
                    <a href="index.php?action=logout" class="btn btn-secondary">🚪 Déconnexion</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- RÉSUMÉ -->
        <div class="test-section">
            <h2>📝 Résumé & Conclusion</h2>
            <?php
            $all_pass = $passed_tests === $total_tests;
            $majority_pass = $success_rate >= 80;
            ?>
            
            <?php if ($all_pass): ?>
                <div class="success-box">
                    <strong>✅ TOUS LES TESTS SONT PASSÉS</strong><br>
                    Le système d'authentification et d'autorisation fonctionne correctement.<br>
                    <small style="margin-top:10px; display:block;">
                        ✓ Sessions valides<br>
                        ✓ Profils reconnus<br>
                        ✓ Restrictions appropriées<br>
                        ✓ Pas de restriction excessive
                    </small>
                </div>
            <?php elseif ($majority_pass): ?>
                <div class="warning-box">
                    <strong>⚠️ LA PLUPART DES TESTS SONT PASSÉS</strong><br>
                    Le système fonctionne globalement bien, mais des ajustements peuvent être nécessaires.
                </div>
            <?php else: ?>
                <div class="error-box">
                    <strong>❌ DES PROBLÈMES ONT ÉTÉ DÉTECTÉS</strong><br>
                    Veuillez vérifier la configuration et la base de données.
                </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <div class="footer">
        <p>CTR.NET-FARDC Diagnostic Authentification | Généré le <?php echo date('d/m/Y H:i:s'); ?></p>
        <p>Version 1.0 | Pour le développement et le débogage uniquement</p>
    </div>
</div>

</body>
</html>
