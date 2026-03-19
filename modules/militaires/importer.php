<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG']);

// --- AJOUT LOG : journalisation de la consultation de la page d'import ---
audit_action('CONSULTATION', 'militaires', null, 'Consultation de la page d\'import');
// --- FIN AJOUT LOG ---

$page_titre = 'Importer des militaires';
include '../../includes/header.php';

// Traitement de l'import des données JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_data'])) {
    $data = json_decode($_POST['import_data'], true);
    
    if ($data && is_array($data)) {
        $resultat = importerMilitaires($data);
        $succes = $resultat['message'];
        $erreurs = $resultat['erreurs'] ?? [];
        $stats = $resultat['stats'] ?? [];
        $doublons_details = $resultat['doublons_details'] ?? [];
    } else {
        $erreur = "Données d'import invalides.";
    }
}

/**
 * Insère les données dans la table militaires (inclut désormais beneficiaire)
 */
function importerMilitaires($data) {
    global $pdo;
    $compteur = 0;
    $erreurs = [];
    $stats = [
        'total' => count($data),
        'doublons_base' => 0,
        'doublons_fichier' => 0,
        'champs_manquants' => 0,
        'valides' => 0
    ];
    
    $doublons_details = ['base' => [], 'fichier' => []];
    $obligatoires = ['matricule', 'noms', 'grade', 'dependance', 'unite', 'garnison', 'province', 'categorie', 'statut'];
    // beneficiaire est optionnel
    
    // Récupération des matricules existants
    try {
        $stmt = $pdo->query("SELECT matricule, noms, grade FROM militaires");
        $militairesExistants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $matriculesExistants = array_column($militairesExistants, 'matricule');
        $infosExistants = [];
        foreach ($militairesExistants as $m) {
            $infosExistants[$m['matricule']] = $m['noms'] . ' (' . $m['grade'] . ')';
        }
    } catch (PDOException $e) {
        return [
            'compteur' => 0,
            'erreurs' => ["Erreur de récupération : " . $e->getMessage()],
            'stats' => $stats,
            'doublons_details' => $doublons_details,
            'message' => "0 militaire importé."
        ];
    }
    
    // Validation des données
    $lignesValides = [];
    $matriculesDansFichier = [];
    $indexLigne = 0;
    
    foreach ($data as $index => $ligne) {
        $indexLigne = $index + 2;
        $matricule = trim($ligne['matricule'] ?? '');
        $noms = trim($ligne['noms'] ?? '');
        $grade = trim($ligne['grade'] ?? '');
        
        $ok = true;
        foreach ($obligatoires as $champ) {
            if (!isset($ligne[$champ]) || trim($ligne[$champ]) === '') {
                $erreurs[] = "Ligne $indexLigne : champ '$champ' manquant";
                $ok = false;
                $stats['champs_manquants']++;
                break;
            }
        }
        if (!$ok) continue;
        
        if (empty($matricule)) {
            $erreurs[] = "Ligne $indexLigne : matricule vide";
            $stats['champs_manquants']++;
            continue;
        }
        
        if (in_array($matricule, $matriculesDansFichier)) {
            $doublons_details['fichier'][] = [
                'ligne' => $indexLigne,
                'matricule' => $matricule,
                'noms' => $noms,
                'grade' => $grade
            ];
            $stats['doublons_fichier']++;
            continue;
        }
        
        if (in_array($matricule, $matriculesExistants)) {
            $doublons_details['base'][] = [
                'ligne' => $indexLigne,
                'matricule' => $matricule,
                'noms' => $noms,
                'grade' => $grade,
                'existant' => $infosExistants[$matricule] ?? 'Inconnu'
            ];
            $stats['doublons_base']++;
            continue;
        }
        
        $matriculesDansFichier[] = $matricule;
        $lignesValides[] = $ligne;
        $stats['valides']++;
    }
    
    // Limiter les erreurs affichées
    if (count($erreurs) > 10) {
        $totalErreurs = count($erreurs);
        $erreurs = array_slice($erreurs, 0, 8);
        $erreurs[] = "... et " . ($totalErreurs - 8) . " autre(s) erreur(s).";
    }
    
    if (empty($lignesValides)) {
        return [
            'compteur' => 0,
            'erreurs' => $erreurs,
            'stats' => $stats,
            'doublons_details' => $doublons_details,
            'message' => "Aucune ligne valide à importer."
        ];
    }
    
    // Insertion des données (avec beneficiaire)
    $sql = "INSERT INTO militaires (matricule, noms, grade, dependance, unite, garnison, province, categorie, statut, beneficiaire) 
            VALUES (:matricule, :noms, :grade, :dependance, :unite, :garnison, :province, :categorie, :statut, :beneficiaire)";
    $stmt = $pdo->prepare($sql);
    
    foreach ($lignesValides as $index => $ligne) {
        $indexLigne = array_search($ligne, $data) + 2;
        $params = [
            ':matricule' => trim($ligne['matricule']),
            ':noms' => trim($ligne['noms']),
            ':grade' => trim($ligne['grade']),
            ':dependance' => trim($ligne['dependance']),
            ':unite' => trim($ligne['unite']),
            ':garnison' => trim($ligne['garnison']),
            ':province' => trim($ligne['province']),
            ':categorie' => trim($ligne['categorie']),
            ':statut' => trim($ligne['statut']),
            ':beneficiaire' => isset($ligne['beneficiaire']) ? trim($ligne['beneficiaire']) : null
        ];
        
        try {
            $stmt->execute($params);
            $compteur++;
            
            if (isset($_POST['ajax_progress']) && $_POST['ajax_progress'] === '1') {
                $progress = [
                    'current' => $compteur,
                    'total' => count($lignesValides),
                    'totalInitial' => count($data),
                    'percentage' => round(($compteur / count($lignesValides)) * 100)
                ];
                echo "data: " . json_encode($progress) . "\n\n";
                ob_flush();
                flush();
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $doublons_details['base'][] = [
                    'ligne' => $indexLigne,
                    'matricule' => $ligne['matricule'],
                    'noms' => $ligne['noms'],
                    'grade' => $ligne['grade'],
                    'existant' => 'Contrainte base'
                ];
                $stats['doublons_base']++;
            } else {
                $erreurs[] = "Ligne $indexLigne : erreur SQL - " . $e->getMessage();
            }
        }
    }
    
    if ($compteur > 0) {
        // --- AJOUT LOG ---
        log_action('IMPORT', 'militaires', null, "$compteur militaires importés");
        // --- FIN AJOUT LOG ---
    }
    
    $message = "$compteur militaire(s) importé(s) sur " . count($data) . " ligne(s).";
    
    return [
        'compteur' => $compteur,
        'erreurs' => $erreurs,
        'stats' => $stats,
        'doublons_details' => $doublons_details,
        'message' => $message
    ];
}
?>

<!-- Scripts locaux -->
<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/jquery.dataTables.min.js"></script>
<script src="../../assets/js/dataTables.bootstrap4.min.js"></script>
<script src="../../assets/js/sweetalert2.all.min.js"></script>
<script src="../../assets/js/xlsx.full.min.js"></script>

<style>
:root {
    --primary: #2e7d32;
    --primary-dark: #1b5e20;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --info: #17a2b8;
    --light: #f8f9fa;
}

body {
    background: #f5f5f5;
    font-family: 'Barlow', sans-serif;
}

.modern-card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    background: white;
    margin: 20px;
}

.modern-card .card-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 8px 8px 0 0;
}

.modern-card .card-header h3 {
    color: white;
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.modern-card .card-header h3 i {
    margin-right: 8px;
}

.modern-card .card-body {
    padding: 20px;
}

.badge-admin {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
}

.alert-modern {
    border-radius: 6px;
    padding: 10px 15px;
    margin-bottom: 15px;
    font-size: 0.9rem;
    border-left: 4px solid;
}

.alert-success-modern {
    background: #d4edda;
    color: #155724;
    border-left-color: var(--success);
}

.alert-warning-modern {
    background: #fff3cd;
    color: #856404;
    border-left-color: var(--warning);
}

.alert-danger-modern {
    background: #f8d7da;
    color: #721c24;
    border-left-color: var(--danger);
}

.alert-info-modern {
    background: #d1ecf1;
    color: #0c5460;
    border-left-color: var(--info);
}

.file-zone {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--light);
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 5px;
    margin-bottom: 10px;
}

.file-btn {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
    border-radius: 4px;
    padding: 8px 16px;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.file-name {
    color: #666;
    font-size: 0.9rem;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-input {
    display: none;
}

.form-text {
    font-size: 0.85rem;
    color: #666;
    margin: 5px 0;
}

.form-text strong {
    color: var(--primary);
}

.preview-container {
    margin-top: 20px;
    padding: 15px;
    background: var(--light);
    border: 1px solid #ddd;
    border-radius: 6px;
    display: none;
}

.preview-container h5 {
    margin: 0 0 10px 0;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

.table-responsive {
    overflow-x: auto;
    border-radius: 4px;
}

#previewTable {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    background: white;
}

#previewTable th {
    background: #e9ecef;
    padding: 8px;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    text-transform: uppercase;
}

#previewTable td {
    padding: 6px 8px;
    border-bottom: 1px solid #dee2e6;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.row-counter {
    margin-top: 10px;
    padding: 5px 12px;
    background: white;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.85rem;
}

.action-buttons {
    margin-top: 15px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-modern {
    border-radius: 20px;
    padding: 8px 20px;
    font-size: 0.9rem;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.btn-info-modern {
    background: var(--info);
    color: white;
}

.btn-primary-modern {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.btn-secondary-modern {
    background: var(--danger);
    color: white;
}

.progress-container {
    background: var(--light);
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
    display: none;
}

.progress-container.active {
    display: block;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.progress-title {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #333;
}

.progress-stats {
    background: white;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
    color: var(--primary);
}

.progress-bar-container {
    height: 20px;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--warning), #e0a800);
    border-radius: 10px;
    transition: width 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #212529;
    font-size: 0.8rem;
    font-weight: 600;
    width: 0%;
}

.progress-details {
    display: flex;
    gap: 15px;
    font-size: 0.85rem;
    color: #666;
}

.doublons-details {
    margin-top: 10px;
    padding: 10px;
    background: white;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.doublons-section {
    margin-bottom: 15px;
}

.doublons-section h6 {
    margin: 0 0 8px 0;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

.doublons-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
    border: 1px solid #dee2e6;
}

.doublons-table th {
    background: #e9ecef;
    padding: 6px;
    text-align: left;
}

.doublons-table td {
    padding: 5px 6px;
    border-bottom: 1px solid #dee2e6;
}

.badge-doublon {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: 600;
}

.badge-doublon-fichier {
    background: #fff3cd;
    color: #856404;
}

.badge-doublon-base {
    background: #f8d7da;
    color: #721c24;
}

.fade-in {
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .action-buttons {
        flex-direction: column;
    }

    .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .file-zone {
        flex-direction: column;
    }

    .file-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="container-fluid">
    <div class="modern-card">
        <div class="card-header">
            <h3><i class="fas fa-upload"></i> Importer des militaires</h3>
            <span class="badge-admin"><i class="fas fa-shield-alt"></i> ADMIN_IG</span>
        </div>
        <div class="card-body">
            <!-- Messages -->
            <?php if (isset($succes)): ?>
            <div class="alert-modern alert-success-modern"><i class="fas fa-check-circle"></i> <?= $succes ?></div>
            <?php endif; ?>

            <?php if (!empty($erreurs)): ?>
            <div class="alert-modern alert-warning-modern">
                <strong><i class="fas fa-exclamation-triangle"></i> Erreurs :</strong>
                <ul class="mb-0 mt-2"><?php foreach ($erreurs as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <?php if (isset($stats) && ($stats['doublons_base'] > 0 || $stats['doublons_fichier'] > 0)): ?>
            <div class="alert-modern alert-info-modern">
                <i class="fas fa-info-circle"></i>
                <strong>Résumé :</strong> <?= $stats['doublons_base'] ?> doublon(s) base,
                <?= $stats['doublons_fichier'] ?> doublon(s) fichier, <?= $stats['champs_manquants'] ?> ligne(s)
                incomplète(s)

                <?php if (!empty($doublons_details['base']) || !empty($doublons_details['fichier'])): ?>
                <div class="doublons-details">
                    <?php if (!empty($doublons_details['fichier'])): ?>
                    <div class="doublons-section">
                        <h6><i class="fas fa-file-excel"></i> Doublons fichier
                            (<?= count($doublons_details['fichier']) ?>)</h6>
                        <table class="doublons-table">
                            <thead>
                                <tr>
                                    <th>Ligne</th>
                                    <th>Matricule</th>
                                    <th>Noms</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doublons_details['fichier'] as $d): ?>
                                <tr>
                                    <td><?= $d['ligne'] ?></td>
                                    <td><strong><?= htmlspecialchars($d['matricule']) ?></strong></td>
                                    <td><?= htmlspecialchars($d['noms']) ?></td>
                                    <td><?= htmlspecialchars($d['grade']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($doublons_details['base'])): ?>
                    <div class="doublons-section">
                        <h6><i class="fas fa-database"></i> Doublons base (<?= count($doublons_details['base']) ?>)</h6>
                        <table class="doublons-table">
                            <thead>
                                <tr>
                                    <th>Ligne</th>
                                    <th>Matricule</th>
                                    <th>Noms</th>
                                    <th>Grade</th>
                                    <th>Existant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doublons_details['base'] as $d): ?>
                                <tr>
                                    <td><?= $d['ligne'] ?></td>
                                    <td><strong><?= htmlspecialchars($d['matricule']) ?></strong></td>
                                    <td><?= htmlspecialchars($d['noms']) ?></td>
                                    <td><?= htmlspecialchars($d['grade']) ?></td>
                                    <td><?= htmlspecialchars($d['existant']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($erreur)): ?>
            <div class="alert-modern alert-danger-modern"><i class="fas fa-times-circle"></i> <?= $erreur ?></div>
            <?php endif; ?>

            <!-- Progression -->
            <div id="progressContainer" class="progress-container">
                <div class="progress-header">
                    <div class="progress-title"><i class="fas fa-spinner fa-pulse"></i> <span>Importation en
                            cours...</span>
                    </div>
                    <div class="progress-stats" id="progressStats">0%</div>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="progressBar">0%</div>
                </div>
                <div class="progress-details">
                    <span><i class="fas fa-check-circle"></i> <span id="importedCount">0</span>/<span
                            id="validCount">0</span></span>
                    <span><i class="fas fa-list"></i> Total : <span id="totalCount">0</span></span>
                    <span><i class="fas fa-clock"></i> <span id="progressTime">--</span></span>
                </div>
            </div>

            <!-- Formulaire -->
            <form method="post" id="importForm">
                <input type="hidden" name="import_data" id="importData">

                <div class="file-zone">
                    <input type="file" id="fichier" class="file-input" accept=".csv,.xls,.xlsx" required>
                    <button type="button" class="file-btn" onclick="document.getElementById('fichier').click()">
                        <i class="fas fa-folder-open"></i> Parcourir
                    </button>
                    <span class="file-name" id="fileName">Aucun fichier sélectionné</span>
                </div>

                <div class="form-text">
                    <i class="fas fa-info-circle"></i>
                    <strong>Champs requis :</strong> Matricule, Noms, Grade, Dependance, Unite, Garnison, Province,
                    Categorie, Statut <br>
                    <strong>Champ optionnel :</strong> Beneficiaire
                </div>

                <!-- Aperçu -->
                <div id="previewContainer" class="preview-container">
                    <h5><i class="fas fa-table"></i> Aperçu (10 premières lignes)</h5>
                    <div class="table-responsive">
                        <table id="previewTable">
                            <thead id="previewHeader"></thead>
                            <tbody id="previewBody"></tbody>
                        </table>
                    </div>
                    <div class="row-counter" id="rowCount"><i class="fas fa-database"></i> <span>Chargement...</span>
                    </div>
                </div>

                <!-- Boutons -->
                <div class="action-buttons">
                    <button type="button" class="btn-modern btn-info-modern" id="previewBtn">
                        <i class="fas fa-eye"></i> Aperçu
                    </button>
                    <button type="button" class="btn-modern btn-primary-modern" id="importBtn" style="display:none;">
                        <i class="fas fa-check-circle"></i> Importer
                    </button>
                    <a href="liste.php" class="btn-modern btn-secondary-modern" id="cancelBtn">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Éléments DOM
    const fileInput = document.getElementById('fichier');
    const fileName = document.getElementById('fileName');
    const previewBtn = document.getElementById('previewBtn');
    const previewContainer = document.getElementById('previewContainer');
    const previewHeader = document.getElementById('previewHeader');
    const previewBody = document.getElementById('previewBody');
    const rowCount = document.getElementById('rowCount');
    const importBtn = document.getElementById('importBtn');
    const importData = document.getElementById('importData');
    const importForm = document.getElementById('importForm');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressStats = document.getElementById('progressStats');
    const importedCount = document.getElementById('importedCount');
    const validCount = document.getElementById('validCount');
    const totalCount = document.getElementById('totalCount');
    const progressTime = document.getElementById('progressTime');

    let currentData = null;
    let currentHeaders = null;
    let startTime = null;
    let abortController = null;

    // Nom du fichier
    fileInput.addEventListener('change', function() {
        fileName.textContent = this.files[0]?.name || 'Aucun fichier sélectionné';
        previewContainer.style.display = 'none';
        importBtn.style.display = 'none';
        previewBtn.style.display = 'inline-flex';
        currentData = null;
    });

    // Aperçu
    previewBtn.addEventListener('click', function() {
        const file = fileInput.files[0];
        if (!file) {
            Swal.fire('Attention', 'Sélectionnez un fichier', 'warning');
            return;
        }

        previewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyse...';
        previewBtn.disabled = true;

        const ext = file.name.split('.').pop().toLowerCase();
        if (ext === 'csv') lireCSV(file);
        else if (['xls', 'xlsx'].includes(ext)) lireExcel(file);
        else {
            Swal.fire('Erreur', 'Format non supporté', 'error');
            resetPreviewButton();
        }
    });

    function lireCSV(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const lines = e.target.result.split('\n').filter(l => l.trim());
            if (lines.length < 2) {
                Swal.fire('Erreur', 'Fichier trop court', 'error');
                resetPreviewButton();
                return;
            }

            const sep = lines[0].includes(';') ? ';' : ',';
            const headers = lines[0].split(sep).map(h => h.trim());
            const data = [];

            for (let i = 1; i < lines.length; i++) {
                const values = lines[i].split(sep).map(v => v.trim());
                if (values.length === headers.length) {
                    const row = {};
                    headers.forEach((h, idx) => row[h] = values[idx]);
                    data.push(row);
                }
            }
            afficherApercu(headers, data);
        };
        reader.readAsText(file, 'UTF-8');
    }

    function lireExcel(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const wb = XLSX.read(new Uint8Array(e.target.result), {
                type: 'array'
            });
            const ws = wb.Sheets[wb.SheetNames[0]];
            const json = XLSX.utils.sheet_to_json(ws, {
                header: 1
            });

            if (json.length < 2) {
                Swal.fire('Erreur', 'Fichier trop court', 'error');
                resetPreviewButton();
                return;
            }

            const headers = json[0].map(h => String(h).trim());
            const data = json.slice(1).map(row => {
                const obj = {};
                headers.forEach((h, idx) => obj[h] = row[idx] !== undefined ? String(row[idx])
                    .trim() : '');
                return obj;
            }).filter(row => Object.values(row).some(v => v));

            afficherApercu(headers, data);
        };
        reader.readAsArrayBuffer(file);
    }

    function afficherApercu(headers, data) {
        currentHeaders = headers;
        currentData = data;

        const required = ['matricule', 'noms', 'grade', 'dependance', 'unite', 'garnison', 'province',
            'categorie', 'statut'
        ];
        const missing = required.filter(f => !headers.includes(f));

        if (missing.length) {
            Swal.fire('Champs manquants', missing.join(', '), 'error');
            previewContainer.style.display = 'none';
            importBtn.style.display = 'none';
            previewBtn.style.display = 'inline-flex';
            resetPreviewButton();
            return;
        }

        // Affichage des en-têtes en majuscules
        previewHeader.innerHTML = '<tr>' + headers.map(h => `<th>${escapeHtml(h).toUpperCase()}</th>`).join(
            '') + '</tr>';
        previewBody.innerHTML = data.slice(0, 10).map(row =>
            '<tr>' + headers.map(h =>
                `<td title="${escapeHtml(row[h] || '')}">${escapeHtml(row[h] || '')}</td>`).join('') +
            '</tr>'
        ).join('');

        rowCount.innerHTML = `<i class="fas fa-database"></i> <span>Total: ${data.length} ligne(s)</span>`;
        previewContainer.style.display = 'block';
        importBtn.style.display = 'inline-flex';
        previewBtn.style.display = 'none'; // Cacher le bouton Aperçu
        resetPreviewButton();
    }

    function resetPreviewButton() {
        previewBtn.innerHTML = '<i class="fas fa-eye"></i> Aperçu';
        previewBtn.disabled = false;
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [m];
        });
    }

    // Import
    importBtn.addEventListener('click', function() {
        if (!currentData) {
            Swal.fire('Attention', 'Faites un aperçu d\'abord', 'warning');
            return;
        }

        Swal.fire({
            title: 'Confirmation',
            text: `Importer ${currentData.length} militaire(s) ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Oui, importer'
        }).then(result => {
            if (!result.isConfirmed) return;

            previewContainer.style.display = 'none';
            previewBtn.style.display = 'none';
            importBtn.style.display = 'none';
            progressContainer.classList.add('active');

            totalCount.textContent = currentData.length;
            validCount.textContent = '?';
            importedCount.textContent = '0';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            progressStats.textContent = '0%';
            startTime = new Date();

            previewBtn.disabled = true;
            importBtn.disabled = true;
            fileInput.disabled = true;

            abortController = new AbortController();

            const formData = new FormData();
            formData.append('import_data', JSON.stringify(currentData));
            formData.append('ajax_progress', '1');

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    signal: abortController.signal
                })
                .then(response => {
                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();

                    function read() {
                        reader.read().then(({
                            done,
                            value
                        }) => {
                            if (done) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Import réussi !',
                                    text: 'Redirection...',
                                    timer: 1500,
                                    showConfirmButton: false,
                                    willClose: () => window.location
                                        .reload()
                                });
                                return;
                            }

                            decoder.decode(value).split('\n').forEach(line => {
                                if (line.startsWith('data: ')) {
                                    try {
                                        const p = JSON.parse(line.substring(
                                            6));
                                        updateProgress(p);
                                    } catch (e) {}
                                }
                            });
                            read();
                        }).catch(error => {
                            if (error.name !== 'AbortError') handleError(error);
                        });
                    }
                    read();
                })
                .catch(error => {
                    if (error.name !== 'AbortError') handleError(error);
                });
        });
    });

    function updateProgress(p) {
        importedCount.textContent = p.current;
        validCount.textContent = p.total;
        const percent = p.percentage;
        progressBar.style.width = percent + '%';
        progressBar.textContent = percent + '%';
        progressStats.textContent = percent + '%';

        if (startTime && p.current > 0) {
            const elapsed = (new Date() - startTime) / 1000;
            const speed = p.current / elapsed;
            const remaining = (p.total - p.current) / speed;
            progressTime.textContent = remaining > 60 ? `~${Math.round(remaining/60)}min` :
                `~${Math.round(remaining)}s`;
        }
    }

    function handleError(error) {
        console.error(error);
        Swal.fire('Erreur', "Échec de l'import", 'error');
        resetInterface();
    }

    function resetInterface() {
        previewBtn.disabled = false;
        importBtn.disabled = false;
        fileInput.disabled = false;
        progressContainer.classList.remove('active');

        if (currentData) {
            previewBtn.style.display = 'none';
            importBtn.style.display = 'inline-flex';
            previewContainer.style.display = 'block';
        } else {
            previewBtn.style.display = 'inline-flex';
            importBtn.style.display = 'none';
            previewContainer.style.display = 'none';
        }
    }

    // Annulation
    document.getElementById('cancelBtn').addEventListener('click', function(e) {
        e.preventDefault();
        if (progressContainer.classList.contains('active')) {
            Swal.fire({
                title: 'Arrêter ?',
                text: 'Import en cours. Arrêter ?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Oui, arrêter'
            }).then(result => {
                if (result.isConfirmed) {
                    if (abortController) abortController.abort();
                    setTimeout(() => window.location.href = 'liste.php', 500);
                }
            });
        } else {
            window.location.href = 'liste.php';
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>