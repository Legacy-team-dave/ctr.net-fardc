<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/server_sync_forwarder.php';

require_login();
check_profil(['ADMIN_IG', 'OPERATEUR']);

if (is_central_mode()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Action non autorisée en mode central.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode POST requise.']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$serverIp = trim((string) ($input['server_ip'] ?? ''));

if ($serverIp === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Adresse du serveur manquante.']);
    exit;
}

$_SESSION['sync_server_ip'] = $serverIp;

try {
    $probe = probe_server_receiver_connection($serverIp, 5);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
    exit;
}

if ($probe['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Connexion établie avec le serveur distant.',
        'remote_http_code' => $probe['http_code'],
        'target_url' => $probe['target_url']
    ]);
    exit;
}

http_response_code(502);
echo json_encode([
    'success' => false,
    'message' => 'Connexion impossible avec le serveur distant.',
    'remote_http_code' => $probe['http_code'],
    'target_url' => $probe['target_url'],
    'transport_error' => $probe['transport_error']
]);