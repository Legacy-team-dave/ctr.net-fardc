<?php
session_start();
require_once '../includes/functions.php';

// Vérifier la connexion
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

// Récupérer les données POST
$input = json_decode(file_get_contents('php://input'), true);
$garnisons = $input['garnisons'] ?? [];

if (empty($garnisons)) {
    echo json_encode([]);
    exit;
}

try {
    // Construire les placeholders
    $placeholders = implode(',', array_fill(0, count($garnisons), '?'));
    
    // Récupérer les catégories uniques pour ces garnisons avec comptage
    $sql = "SELECT categorie, COUNT(*) as total 
            FROM militaires 
            WHERE garnison IN ($placeholders) 
            AND categorie IS NOT NULL AND categorie != ''
            GROUP BY categorie 
            ORDER BY categorie";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($garnisons);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($categories);
    
} catch (PDOException $e) {
    error_log("Erreur get_categories: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}