<?php
// api/toast.php
// API simple pour stocker et récupérer un message toast (notification) côté web

// Fichier temporaire pour stocker le toast (par utilisateur, ici version simple globale)
$toast_file = __DIR__ . '/toast_message.json';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Le mobile envoie un toast à afficher côté web
    $input = json_decode(file_get_contents('php://input'), true);
    $message = trim($input['message'] ?? '');
    $type = $input['type'] ?? 'success';
    if ($message !== '') {
        file_put_contents($toast_file, json_encode([
            'message' => $message,
            'type' => $type,
            'time' => time()
        ]));
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Message vide']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Le web demande s'il y a un toast à afficher
    if (file_exists($toast_file)) {
        $data = json_decode(file_get_contents($toast_file), true);
        // On supprime le toast après lecture (affichage unique)
        unlink($toast_file);
        echo json_encode(['toast' => $data]);
        exit;
    }
    echo json_encode(['toast' => null]);
    exit;
}

echo json_encode(['error' => 'Méthode non supportée']);
exit;
