<?php
// Définir l'en-tête UTF-8
header('Content-Type: application/json; charset=utf-8');

require_once '../../includes/functions.php';
require_login();

// Activer le traitement UTF-8 pour PDO
$pdo->exec("SET NAMES 'utf8'");
$pdo->exec("SET CHARACTER SET utf8");

ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_clean();

try {
    $response = ['success' => false, 'message' => ''];

    if (!isset($_FILES['file'])) {
        throw new Exception('Aucun fichier uploadé');
    }

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par PHP',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload du fichier'
        ];
        $errorMsg = $uploadErrors[$_FILES['file']['error']] ?? 'Erreur inconnue lors de l\'upload';
        throw new Exception('Erreur d\'upload : ' . $errorMsg);
    }

    $file = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileSize = $_FILES['file']['size'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if ($fileSize > 10 * 1024 * 1024) {
        throw new Exception('Le fichier ne doit pas dépasser 10 Mo');
    }
    
    if (!in_array($extension, ['csv'])) {
        throw new Exception('Format de fichier non supporté. Veuillez utiliser le format CSV (séparateur point-virgule)');
    }
    
    // Lire le fichier CSV avec détection UTF-8
    $data = [];
    $lineCount = 0;
    $headers = [];
    
    if (($handle = fopen($file, 'r')) !== false) {
        // Détecter le séparateur
        $firstLine = fgets($handle);
        rewind($handle);
        
        // Vérifier si le fichier a un BOM UTF-8 et le supprimer
        $bom = "\xEF\xBB\xBF";
        if (substr($firstLine, 0, 3) === $bom) {
            $firstLine = substr($firstLine, 3);
        }
        
        $separator = ';';
        if (strpos($firstLine, ',') !== false && strpos($firstLine, ';') === false) {
            $separator = ',';
        }
        
        // Lire l'en-tête
        $headers = fgetcsv($handle, 0, $separator);
        if (!$headers) {
            throw new Exception('Impossible de lire l\'en-tête du fichier CSV');
        }
        
        // Nettoyer les en-têtes et convertir en UTF-8 si nécessaire
        $headers = array_map(function($h) {
            // Supprimer le BOM UTF-8 s'il est présent
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            // Convertir en UTF-8 si ce n'est pas déjà le cas
            if (!mb_check_encoding($h, 'UTF-8')) {
                $h = utf8_encode($h);
            }
            $h = trim($h);
            return strtolower($h);
        }, $headers);
        
        // Vérifier les colonnes requises
        $requiredColumns = ['matricule', 'noms', 'grade', 'dependance', 'unite', 'beneficiaire', 'garnison', 'province', 'categorie', 'statut'];
        $missingColumns = array_diff($requiredColumns, $headers);
        if (!empty($missingColumns)) {
            throw new Exception('Colonnes manquantes : ' . implode(', ', $missingColumns));
        }
        
        // Lire les données
        $rowNumber = 1;
        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            $rowNumber++;
            
            if (count(array_filter($row)) === 0) {
                continue;
            }
            
            if (count($row) !== count($headers)) {
                throw new Exception("Ligne $rowNumber : nombre de colonnes incorrect (" . count($row) . " au lieu de " . count($headers) . ")");
            }
            
            $rowData = array_combine($headers, $row);
            
            // Convertir chaque valeur en UTF-8 si nécessaire
            foreach ($rowData as $key => $value) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = utf8_encode($value);
                }
                $rowData[$key] = trim($value);
            }
            
            if (empty($rowData['matricule'])) {
                throw new Exception("Ligne $rowNumber : le matricule est obligatoire");
            }
            
            $data[] = $rowData;
            $lineCount++;
        }
        fclose($handle);
    } else {
        throw new Exception('Impossible d\'ouvrir le fichier CSV');
    }
    
    if (empty($data)) {
        throw new Exception('Aucune donnée valide à importer');
    }
    
    // Démarrer la transaction
    $pdo->beginTransaction();
    
    $inserted = 0;
    $updated = 0;
    $errors = 0;
    $errorDetails = [];
    
    $stmt = $pdo->prepare("
        INSERT INTO militaires (matricule, noms, grade, dependance, unite, beneficiaire, garnison, province, categorie, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        noms = VALUES(noms),
        grade = VALUES(grade),
        dependance = VALUES(dependance),
        unite = VALUES(unite),
        beneficiaire = VALUES(beneficiaire),
        garnison = VALUES(garnison),
        province = VALUES(province),
        categorie = VALUES(categorie),
        statut = VALUES(statut)
    ");
    
    foreach ($data as $index => $row) {
        try {
            $matricule = strtoupper(trim($row['matricule'] ?? ''));
            $noms = strtoupper(trim($row['noms'] ?? ''));
            $grade = strtoupper(trim($row['grade'] ?? ''));
            $dependance = strtoupper(trim($row['dependance'] ?? ''));
            $unite = strtoupper(trim($row['unite'] ?? ''));
            $beneficiaire = strtoupper(trim($row['beneficiaire'] ?? ''));
            $garnison = strtoupper(trim($row['garnison'] ?? ''));
            $province = strtoupper(trim($row['province'] ?? ''));
            $categorie = strtoupper(trim($row['categorie'] ?? ''));
            $statut = isset($row['statut']) ? intval($row['statut']) : 1;
            
            if (empty($matricule)) {
                throw new Exception("Matricule vide à la ligne " . ($index + 2));
            }
            
            $stmt->execute([
                $matricule,
                $noms ?: null,
                $grade ?: null,
                $dependance ?: null,
                $unite ?: null,
                $beneficiaire ?: null,
                $garnison ?: null,
                $province ?: null,
                $categorie ?: null,
                $statut
            ]);
            
            if ($stmt->rowCount() === 1) {
                $inserted++;
            } else {
                $updated++;
            }
            
        } catch (Exception $e) {
            $errors++;
            $errorDetails[] = "Ligne " . ($index + 2) . " : " . $e->getMessage();
        }
    }
    
    if ($errors > 0 && $errors > count($data) * 0.2) {
        $pdo->rollBack();
        throw new Exception("Trop d'erreurs détectées (" . $errors . "). Import annulé.\n" . implode("\n", array_slice($errorDetails, 0, 5)));
    }
    
    $pdo->commit();
    
    $message = "$inserted militaires importés, $updated mis à jour";
    if ($errors > 0) {
        $message .= ", $errors erreurs ignorées";
        if (!empty($errorDetails)) {
            $message .= ".\nDétails : " . implode('; ', array_slice($errorDetails, 0, 3));
            if (count($errorDetails) > 3) {
                $message .= "... (" . (count($errorDetails) - 3) . " autres erreurs)";
            }
        }
    }
    
    $response['success'] = true;
    $response['message'] = $message;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>

