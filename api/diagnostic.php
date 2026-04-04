<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/load_config.php';

// Basic security check: ensure the user is logged in and has rights.
require_login();
check_profil(['ADMIN_IG', 'CONTROLEUR_GENERAL']);

function run_diagnostics() {
    $results = [];
    $config = load_app_config();

    // 1. Check App Mode
    $results['app_mode'] = [
        'title' => 'Mode de l\'application',
        'status' => 'info',
        'value' => $config['app']['mode'],
        'message' => 'L\'application est en mode ' . $config['app']['mode'] . '.'
    ];

    // 2. Check Sync Configuration
    $sync_config = $config['sync'];
    $is_central = ($config['app']['mode'] === 'central');
    
    if ($is_central) {
        $results['sync_config'] = [
            'title' => 'Configuration Synchronisation (Mode Central)',
            'status' => 'success',
            'message' => 'Le serveur est en mode central. Il est prêt à recevoir des données.'
        ];
        if (empty($sync_config['shared_token'])) {
             $results['sync_config']['status'] = 'error';
             $results['sync_config']['message'] = 'Le token de synchronisation partagé (SYNC_SHARED_TOKEN) n\'est pas configuré. Les serveurs distants ne pourront pas s\'authentifier.';
        }
    } else {
        $results['sync_config'] = [
            'title' => 'Configuration Synchronisation (Mode Terrain)',
            'status' => 'success',
            'message' => 'La configuration pour l\'envoi des données semble correcte.'
        ];
        if (empty($sync_config['central_url']) || empty($sync_config['shared_token'])) {
            $results['sync_config']['status'] = 'error';
            $results['sync_config']['message'] = 'L\'URL du serveur central (SYNC_CENTRAL_URL) ou le token partagé (SYNC_SHARED_TOKEN) est manquant.';
        } else {
             $results['sync_config']['value'] = 'URL Centrale: ' . $sync_config['central_url'];
        }
    }

    // 3. Test Database Connection
    try {
        $pdo = get_db_connection();
        $results['db_connection'] = [
            'title' => 'Connexion à la base de données',
            'status' => 'success',
            'message' => 'La connexion à la base de données locale a réussi.'
        ];
    } catch (PDOException $e) {
        $results['db_connection'] = [
            'title' => 'Connexion à la base de données',
            'status' => 'error',
            'message' => 'Échec de la connexion à la base de données locale: ' . $e->getMessage()
        ];
        // Stop here if DB is not available
        return $results;
    }

    // 4. Test Central Server Connection (only in terrain mode)
    if (!$is_central && !empty($sync_config['central_url'])) {
        
        require_once __DIR__ . '/server_sync_forwarder.php';
        
        $probe = probe_server_receiver_connection($sync_config['central_url'], 15);
        $test_url = $probe['target_url'] ?? rtrim($sync_config['central_url'], '/');
        $response = $probe['body'] ?? false;
        $http_code = (int)($probe['http_code'] ?? 0);
        $error = trim((string)($probe['transport_error'] ?? ''));

        if (empty($probe['success'])) {
            $results['central_server_connection'] = [
                'title' => 'Connexion au serveur central',
                'status' => 'error',
                'message' => 'Impossible de joindre le point de réception central.',
                'details' => 'URL testée : ' . $test_url . ($error !== '' ? ' | Erreur: ' . $error : '')
            ];
        } elseif ($http_code >= 400) {
             $results['central_server_connection'] = [
                'title' => 'Connexion au serveur central',
                'status' => 'warning',
                'message' => 'Le serveur central a répondu avec un code d\'erreur HTTP ' . $http_code . '.',
                'details' => 'URL testée : ' . $test_url . '. Réponse: ' . substr((string)$response, 0, 200)
            ];
        } else {
            $data = json_decode((string)$response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['mode'])) {
                 $results['central_server_connection'] = [
                    'title' => 'Connexion au serveur central',
                    'status' => ($data['mode'] === 'central') ? 'success' : 'warning',
                    'message' => 'La connexion au serveur central a réussi. Le serveur distant est en mode "' . $data['mode'] . '".',
                    'details' => 'URL testée : ' . $test_url
                ];
                if ($data['mode'] !== 'central') {
                    $results['central_server_connection']['message'] .= ' Il devrait être en mode "central".';
                }
            } else {
                 $results['central_server_connection'] = [
                    'title' => 'Connexion au serveur central',
                    'status' => 'success',
                    'message' => 'Le point de réception central répond correctement.',
                    'details' => 'URL testée : ' . $test_url
                ];
            }
        }
    }

    return $results;
}

$diagnostic_results = run_diagnostics();

echo json_encode([
    'success' => true,
    'timestamp' => date('c'),
    'results' => $diagnostic_results
]);
