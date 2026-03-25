<?php
require_once '../../includes/functions.php';
require_login();

// MODIFICATION : Vérifier que seuls les profils autorisés accèdent à cette page
check_profil(['OPERATEUR']);

// --- AJAX : recherche en temps réel ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search' && isset($_GET['q'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q']);
    if (strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT matricule, noms, grade, unite, garnison, province, statut, categorie,
                                  EXISTS(SELECT 1 FROM controles c WHERE c.matricule = militaires.matricule) AS deja_controle
                           FROM militaires 
                           WHERE matricule LIKE ? OR noms LIKE ? 
                           ORDER BY 
                               CASE 
                                   WHEN matricule = ? THEN 0 
                                   WHEN matricule LIKE ? THEN 1 
                                   WHEN noms LIKE ? THEN 2 
                                   ELSE 3 
                               END, noms 
                           LIMIT 10");
    $searchTerm = "%$q%";
    $exact = $q;
    $stmt->execute([$searchTerm, $searchTerm, $exact, "$q%", "$q%"]);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($resultats);
    exit;
}
// --- Fin AJAX ---

// Fonction pour calculer l'âge à partir du matricule (2e et 3e chiffres)
function calculerAge($matricule)
{
    if (strlen($matricule) >= 3 && is_numeric($matricule)) {
        $yy = intval(substr($matricule, 1, 2)); // positions 1 et 2 (0-based)
        $currentYear = date('Y');
        $currentTwoDigits = intval(date('y')); // deux derniers chiffres de l'année (ex: 26 pour 2026)

        // Si les deux chiffres sont <= année courante, on est dans les années 2000, sinon 1900
        if ($yy <= $currentTwoDigits) {
            $anneeNaissance = 2000 + $yy;
        } else {
            $anneeNaissance = 1900 + $yy;
        }
        $age = $currentYear - $anneeNaissance;
        // Vérifier la plausibilité (âge entre 0 et 120)
        return ($age >= 0 && $age <= 120) ? $age : null;
    }
    return null;
}

// Récupération des paramètres
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$action = $_GET['action'] ?? '';
$matricule = $_GET['matricule'] ?? '';
$beneficiaire = $_GET['beneficiaire'] ?? '';  // bénéficiaire existant
$new_beneficiaire = $_GET['new_beneficiaire'] ?? '';  // nouveau bénéficiaire
$lien = $_GET['lien'] ?? '';
$mention = $_GET['mention'] ?? '';
$observations = $_GET['observations'] ?? '';
$statut_vivant = isset($_GET['statut_vivant']);
$statut_decede = isset($_GET['statut_decede']);

// Traitement de l'enregistrement
if ($action === 'valider' && $matricule && $mention) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM controles WHERE matricule = ?");
        $stmt->execute([$matricule]);

        if ($stmt->fetch()) {
            $_SESSION['swal'] = [
                'icon' => 'error',
                'title' => 'Erreur',
                'text' => 'Ce militaire a déjà été contrôlé.',
                'confirmButtonText' => 'Nouvelle recherche',
                'cancelButtonText' => 'Fermer'
            ];
            header('Location: ?q=' . urlencode($q));
            exit;
        }

        if (empty($lien) && $lien !== 'Militaire lui-même') {
            throw new Exception("Veuillez sélectionner un lien de parenté.");
        }

        if ($lien === 'Militaire lui-même') {
            $beneficiaire = null;
            $new_beneficiaire = null;
        } else {
            // Vérifier qu'au moins un des deux bénéficiaires est renseigné
            if (empty(trim($beneficiaire)) && empty(trim($new_beneficiaire))) {
                throw new Exception("Le nom du bénéficiaire (existant ou nouveau) est obligatoire.");
            }
            // Si le bénéficiaire existant est vide mais le nouveau est renseigné
            if (empty(trim($beneficiaire)) && !empty(trim($new_beneficiaire))) {
                $beneficiaire = null; // On met à null le bénéficiaire existant
            }
        }

        $stmt_militaire = $pdo->prepare("SELECT noms, categorie FROM militaires WHERE matricule = ?");
        $stmt_militaire->execute([$matricule]);
        $militaire_info = $stmt_militaire->fetch();

        $lien_parente = match ($lien) {
            'Epoux' => 'Veuf',
            'Epouse' => 'Veuve',
            'Fils', 'Fille' => 'Orphelin',
            'Père', 'Mère', 'Frère', 'Sœur' => 'Tuteur',
            default => $lien
        };

        // --- MODIFICATION : Transformer "Militaire lui-même" en la catégorie du militaire ---
        if ($lien === 'Militaire lui-même') {
            $lien_parente = $militaire_info['categorie'] ?? 'Militaire lui-même';
        }
        // --- FIN MODIFICATION ---

        $type_controle = 'Bénéficiaire';
        if ($lien === 'Militaire lui-même') {
            $type_controle = 'Militaire';
        } else {
            $statut_vivant = isset($_GET['statut_vivant']);
            $statut_decede = isset($_GET['statut_decede']);
            $cat = $militaire_info['categorie'] ?? '';
            $is_dcd_av_bio = ($cat === 'DCD_AV_BIO');
            if (($statut_vivant && !$statut_decede) || (!$is_dcd_av_bio && !$statut_vivant && !$statut_decede)) {
                $type_controle = 'Militaire';
            }
        }

        // --- MODIFICATION DEMANDÉE ---
        // Si c'est un DCD_AP_BIO et que le statut "vivant" est coché (sans "décédé")
        $cat = $militaire_info['categorie'] ?? ''; // déjà récupéré, mais on le refetch proprement
        if ($cat === 'DCD_AP_BIO' && isset($_GET['statut_vivant']) && !isset($_GET['statut_decede'])) {
            $saisie = trim($observations);
            if ($saisie === '') {
                $observations = 'Mort vivant';
            } else {
                $observations = 'Mort vivant - ' . $saisie;
            }
        }
        // --- FIN MODIFICATION ---

        // Nouvelle requête INSERT avec les champs nom_beneficiaire et new_beneficiaire
        // MODIFICATION : CURDATE() remplacé par NOW() pour stocker date ET heure
        $stmt = $pdo->prepare("INSERT INTO controles (
            matricule, 
            type_controle, 
            nom_beneficiaire, 
            new_beneficiaire, 
            lien_parente, 
            date_controle, 
            mention, 
            observations, 
            cree_le
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW())");

        $stmt->execute([
            $matricule,
            $type_controle,
            $beneficiaire ?: null,  // bénéficiaire existant (peut être null)
            $new_beneficiaire ?: null, // nouveau bénéficiaire (peut être null)
            $lien_parente,
            $mention,
            trim($observations)
        ]);

        // --- AJOUT LOG ---
        $controle_id = $pdo->lastInsertId();
        $details = "Matricule: $matricule, Mention: $mention";
        if (!empty($beneficiaire)) $details .= ", Ancien bénéficiaire: $beneficiaire";
        if (!empty($new_beneficiaire)) $details .= ", Nouveau bénéficiaire: $new_beneficiaire";
        if (!empty($lien_parente)) $details .= ", Lien: $lien_parente";
        if (!empty($observations)) $details .= ", Observations: $observations";
        audit_action('AJOUT', 'controles', $controle_id, $details);
        // --- FIN LOG ---

        // Stocker le message de succès en session pour l'afficher après redirection
        $_SESSION['toast_message'] = "Contrôle enregistré avec succès pour : <strong>" . $militaire_info['noms'] . "</strong>";
        $_SESSION['toast_type'] = 'success';

        header('Location: ajouter.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des messages toast depuis la session
$toast_message = $_SESSION['toast_message'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast_message'], $_SESSION['toast_type']);

// Recherche du militaire
$militaire = null;
if ($q !== '') {
    $stmt = $pdo->prepare("SELECT matricule, noms, grade, unite, garnison, province, statut, beneficiaire, categorie 
                           FROM militaires WHERE matricule = ? OR noms LIKE ? ORDER BY noms LIMIT 1");
    $stmt->execute([$q, "%$q%"]);
    $militaire = $stmt->fetch();

    // Calcul de l'âge si le militaire existe
    if ($militaire) {
        $age = calculerAge($militaire['matricule']);
    }
}

$page_titre = 'Effectuer un contrôle';
include '../../includes/header.php';
?>

<!-- Styles principaux -->
<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="../../assets/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/sweetalert2.min.css">
<link rel="stylesheet" href="../../assets/css/fonts.css">

<style>
    :root {
        --primary: #2e7d32;
        --primary-dark: #1b5e20;
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
        --info: #17a2b8;
        --gray: #6c757d;
        --light: #f8f9fa;
    }

    body {
        font-family: 'Barlow', sans-serif;
        background: #f5f5f5;
        padding: 12px;
        font-size: 16px;
    }

    .card-modern {
        border: none;
        border-radius: 15px;
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        overflow: unset;
    }

    .card-modern .card-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 18px 25px;
        /* augmenté */
        border: none;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .card-modern .card-header h3 {
        margin: 0;
        font-weight: 600;
        font-size: 1.4rem;
        /* augmenté */
    }

    .card-modern .card-header h3 i {
        margin-right: 8px;
    }

    .card-modern .card-body {
        padding: 24px;
        /* augmenté */
    }

    .btn-gradient {
        border-radius: 10px;
        padding: 12px 25px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border: none;
        color: white;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-gradient:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
        color: white;
    }

    .btn-mention {
        border-radius: 30px;
        /* plus arrondi */
        padding: 8px 18px;
        /* augmenté */
        font-weight: 600;
        font-size: 0.9rem;
        /* augmenté */
        border: none;
        cursor: pointer;
        margin: 3px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }

    .btn-mention:hover {
        transform: translateY(-2px);
    }

    .btn-present {
        background: var(--success);
        color: white;
    }

    .btn-present:hover {
        background: #218838;
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
    }

    .btn-favorable {
        background: var(--warning);
        color: #212529;
    }

    .btn-favorable:hover {
        background: #e0a800;
        box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
    }

    .btn-defavorable {
        background: var(--danger);
        color: white;
    }

    .btn-defavorable:hover {
        background: #c82333;
        box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
    }

    .btn-new-search {
        border-radius: 30px;
        padding: 6px 16px;
        background: transparent;
        border: 1px solid white;
        color: white;
        transition: all 0.3s;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.9rem;
    }

    .btn-new-search:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }

    .badge-statut {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 30px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: 10px;
        color: white;
    }

    /* === BADGES GRANDS (pour la fiche militaire) === */
    .badge-actif {
        background: var(--success);
    }

    .badge-decede-av-bio {
        background: #6c757d;
    }

    .badge-dcd-ap-bio {
        background: linear-gradient(135deg, #6f42c1, #6610f2);
    }

    .badge-retraite {
        background: linear-gradient(135deg, #0d6efd, #0a58ca);
    }

    .badge-integre {
        background: linear-gradient(135deg, #dc3545, #b02a37);
    }

    /* === MINI-BADGES (pour les résultats de recherche) === */
    #search-results .badge-statut-mini {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-left: 5px;
        color: white;
    }

    .badge-actif-mini {
        background: var(--success);
    }

    .badge-decede-av-bio-mini {
        background: #6c757d;
    }

    .badge-dcd-ap-bio-mini {
        background: linear-gradient(135deg, #6f42c1, #6610f2);
    }

    .badge-retraite-mini {
        background: linear-gradient(135deg, #0d6efd, #0a58ca);
    }

    .badge-integre-mini {
        background: linear-gradient(135deg, #dc3545, #b02a37);
    }

    /* ===================================================== */

    .militaire-info {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 1.2rem 1.8rem;
        border-radius: 12px;
        margin-bottom: 1.8rem;
        box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
    }

    .militaire-info .info-grid {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1.5rem;
    }

    .militaire-info .info-item {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 1rem;
    }

    .militaire-info .info-label {
        font-weight: 600;
        color: rgba(255, 255, 255, 0.9);
    }

    .militaire-info .info-value {
        font-weight: 500;
    }

    .militaire-info .separator {
        width: 1px;
        height: 20px;
        background: rgba(255, 255, 255, 0.3);
    }

    .statut-temp {
        background: var(--light);
        border-radius: 10px;
        padding: 12px;
        margin: 12px 0;
        border-left: 4px solid var(--primary);
    }

    .statut-temp .form-check-inline {
        margin-right: 1rem;
    }

    .statut-temp .form-check-input {
        width: 1.2rem;
        height: 1.2rem;
        margin-top: 0.2rem;
    }

    .statut-temp .form-check-input:checked {
        background-color: var(--primary);
        border-color: var(--primary);
    }

    .liens-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 12px;
    }

    .lien-groupe {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 10px;
        background: var(--light);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .lien-groupe label {
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 8px;
        display: block;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 5px;
        font-size: 0.9rem;
    }

    .lien-groupe .form-check {
        margin-bottom: 5px;
        font-size: 0.85rem;
    }

    .lien-groupe small {
        color: var(--gray);
        font-size: 0.7rem;
        margin-left: 3px;
    }

    .form-control-modern {
        border-radius: 8px;
        border: 1px solid #ced4da;
        padding: 8px 12px;
        width: 100%;
        transition: all 0.3s;
        font-size: 0.9rem;
    }

    .form-control-modern:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
        outline: none;
    }

    /* Styles pour les cartes bénéficiaires */
    .beneficiaire-card {
        background: white;
        border-radius: 10px;
        padding: 12px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        border: 1px solid #e0e0e0;
        height: 100%;
    }

    .beneficiaire-card.compact {
        padding: 10px 12px;
    }

    .beneficiaire-card .form-control-modern {
        padding: 6px 10px;
        font-size: 0.85rem;
    }

    .beneficiaire-card .bg-light {
        background-color: #f1f3f5 !important;
        padding: 8px 12px;
        font-size: 0.95rem;
        word-break: break-word;
    }

    .observations-group {
        background: var(--light);
        border-radius: 10px;
        padding: 12px;
        margin: 12px 0;
        border: 1px solid #e0e0e0;
    }

    .observations-group textarea {
        min-height: 80px;
        resize: vertical;
        font-family: 'Barlow', sans-serif;
        font-size: 0.9rem;
    }

    .actions-container {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 2px dashed #dee2e6;
    }

    #search-results .list-group-item {
        cursor: pointer;
        border-left: none;
        border-right: none;
        padding: 10px 15px;
        transition: background 0.2s;
        font-size: 0.9rem;
    }

    #search-results .list-group-item.disabled-result {
        cursor: not-allowed;
        opacity: 0.75;
    }

    #search-results .badge-deja-controle-mini {
        background: linear-gradient(135deg, #e65100, #bf360c);
        color: #fff;
    }

    #search-results .list-group-item small {
        font-size: 0.75rem;
    }

    /* Ajustement pour que la liste des résultats s'allonge selon le contenu */
    #search-results {
        max-height: 400px !important;
        overflow-y: auto !important;
    }

    /* Style pour le toast de succès */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }

    .toast-message {
        background: linear-gradient(135deg, var(--success) 0%, #218838 100%);
        color: white;
        padding: 15px 25px;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(40, 167, 69, 0.3);
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        animation: slideIn 0.3s ease-out, fadeOut 0.5s ease-out 3s forwards;
        font-weight: 500;
        min-width: 320px;
        font-size: 0.95rem;
    }

    .toast-message i {
        font-size: 1.2rem;
    }

    .toast-message.error {
        background: linear-gradient(135deg, var(--danger) 0%, #c82333 100%);
        box-shadow: 0 5px 20px rgba(220, 53, 69, 0.3);
    }

    .toast-message.warning {
        background: linear-gradient(135deg, var(--warning) 0%, #e0a800 100%);
        color: #212529;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }

        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }

    /* === STYLES COMPACTS POUR LA CARTE BÉNÉFICIAIRE === */
    .compact-form .liens-grid {
        gap: 8px;
    }

    .compact-form .lien-groupe.compact {
        padding: 8px;
    }

    .compact-form .lien-groupe.compact label {
        font-size: 0.85rem;
        margin-bottom: 5px;
        padding-bottom: 3px;
    }

    .compact-form .lien-groupe.compact .form-check {
        margin-bottom: 3px;
        font-size: 0.8rem;
    }

    .compact-form .lien-groupe.compact .form-check small {
        font-size: 0.7rem;
    }

    .compact-form .observations-group.compact {
        padding: 10px;
        margin: 0;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .compact-form .observations-group.compact textarea {
        min-height: 100px;
        flex: 1;
        font-size: 0.85rem;
    }

    .compact-form .actions-container.compact {
        margin-top: 10px;
        padding-top: 10px;
    }

    /* Pour les écrans étroits, on empile les colonnes */
    @media (max-width: 768px) {
        .compact-form .row {
            flex-direction: column;
        }

        .compact-form .col-md-8,
        .compact-form .col-md-4,
        .compact-form .col-md-6 {
            width: 100%;
        }

        .compact-form .observations-group.compact {
            margin-top: 10px;
        }

        .liens-grid {
            grid-template-columns: 1fr;
        }

        .actions-container {
            flex-direction: column;
        }

        .actions-container .btn-mention {
            width: 100%;
            justify-content: center;
        }

        .militaire-info .info-grid {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .militaire-info .separator {
            display: none;
        }

        .statut-temp .d-flex {
            flex-direction: column;
            gap: 10px;
        }

        .toast-message {
            min-width: 280px;
            padding: 12px 20px;
        }
    }
</style>

</style>

<!-- Conteneur pour les toasts -->
<div class="toast-container" id="toastContainer"></div>

<div class="container-fluid py-3">
    <!-- Recherche en temps réel -->
    <div class="row mb-4" id="search-section" <?= $militaire ? 'style="display:none"' : '' ?>>
        <div class="col-md-8 mx-auto">
            <div class="card-modern">
                <div class="card-header">
                    <h3><i class="fas fa-search"></i> Rechercher un militaire</h3>
                </div>
                <div class="card-body">
                    <div class="position-relative">
                        <input type="text" id="search-input" class="form-control form-control-lg"
                            placeholder="Matricule ou nom complet" autocomplete="off"
                            value="<?= htmlspecialchars($q) ?>">
                        <div id="search-results" class="list-group position-absolute w-100 shadow"
                            style="z-index: 1000; max-height: 400px; overflow-y: auto; display: none;"></div>
                        <div class="mt-2 text-muted small">
                            <i class="fas fa-info-circle"></i> Saisissez au moins 2 caractères
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($militaire):
        $cat = $militaire['categorie'] ?? '';
        $statut = $militaire['statut'];
        $benef_nom = trim($militaire['beneficiaire'] ?? '');
        $nom_militaire = htmlspecialchars($militaire['noms']);

        $type_info = match (true) {
            $statut == 1 => ['ACTIF', 'badge-actif', 'fa-user-check', true, $statut_vivant, $statut_decede],
            $cat === 'DCD_AV_BIO' => ['DÉCÉDÉ AVANT BIO', 'badge-decede-av-bio', 'fa-skull-crossbones', true, false, true],
            $cat === 'DCD_AP_BIO' => ['DÉCÉDÉ APRÈS BIO', 'badge-dcd-ap-bio', 'fa-skull', true, $statut_vivant, $statut_decede],
            $cat === 'RETRAITES' => ['RETRAITÉ', 'badge-retraite', 'fa-user-clock', true, $statut_vivant, $statut_decede],
            $cat === 'INTEGRES' => ['INTÉGRÉ', 'badge-integre', 'fa-user-plus', true, $statut_vivant, $statut_decede],
            default => ['INACTIF', 'badge-secondary', 'fa-question-circle', false, false, false]
        };

        [$type_label, $badge_class, $badge_icon, $show_statut, $vivant_checked, $decede_checked] = $type_info;
        $is_dcd_av_bio = ($cat === 'DCD_AV_BIO');

        // Pour DCD_AP_BIO, on affiche la zone statut par défaut
        // La zone bénéficiaire s'affiche si on coche "Décédé"
        // La zone contrôle vivant s'affiche si on coche "Vivant" (comportement d'avant)
        $est_decede = ($decede_checked && !$vivant_checked);
        $est_vivant = ($vivant_checked && !$decede_checked);

        $show_controle_vivant = $est_vivant; // Pour toutes les catégories, y compris DCD_AP_BIO
        $show_controle_decede = $est_decede;

        // Déterminer si la carte info doit être masquée (lorsqu'un statut est coché)
        $hide_militaire_info = $show_statut && ($vivant_checked || $decede_checked);
    ?>

        <!-- Info militaire -->
        <div class="militaire-info" <?= $hide_militaire_info ? 'style="display:none;"' : '' ?>>
            <div class="d-flex align-items-center gap-3 mb-2">
                <i class="fas fa-user-shield"></i>
                <span class="fw-bold">MILITAIRE CONCERNÉ</span>
                <span class="badge-statut <?= $badge_class ?>">
                    <i class="fas <?= $badge_icon ?>"></i> <?= $type_label ?>
                </span>
            </div>
            <div class="info-grid">
                <div class="info-item"><span class="info-label">Grade :</span> <span
                        class="info-value"><?= htmlspecialchars($militaire['grade'] ?? 'N/A') ?></span></div>
                <span class="separator"></span>
                <div class="info-item"><span class="info-label">Noms :</span> <span
                        class="info-value"><?= $nom_militaire ?></span></div>
                <span class="separator"></span>
                <div class="info-item"><span class="info-label">Matricule :</span> <span
                        class="info-value"><?= htmlspecialchars($militaire['matricule']) ?></span></div>
                <span class="separator"></span>
                <div class="info-item"><span class="info-label">Âge :</span> <span
                        class="info-value"><?= isset($age) && $age ? $age . ' ans' : 'N/A' ?></span></div>
                <span class="separator"></span>
                <div class="info-item"><span class="info-label">Unité :</span> <span
                        class="info-value"><?= htmlspecialchars($militaire['unite'] ?? 'N/A') ?></span></div>
                <span class="separator"></span>
                <div class="info-item"><span class="info-label">Garnison :</span> <span
                        class="info-value"><?= htmlspecialchars($militaire['garnison'] ?? 'N/A') ?></span></div>
                <span class="separator"></span>
                <div class="info-item"><span class="info-label">Province :</span> <span
                        class="info-value"><?= htmlspecialchars($militaire['province'] ?? 'N/A') ?></span></div>
            </div>
        </div>

        <?php if ($show_statut): ?>
            <!-- Statut temporaire - TOUJOURS affiché pour DCD_AP_BIO -->
            <div class="statut-temp">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="fw-bold small"><i class="fas fa-info-circle"></i> Statut :</span>

                    <?php if (!$is_dcd_av_bio): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input statut-checkbox" type="checkbox" id="statut_vivant"
                                <?= $vivant_checked ? 'checked' : '' ?> data-statut="vivant">
                            <label class="form-check-label small" for="statut_vivant">
                                <span class="badge bg-success">Vivant</span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="form-check form-check-inline">
                        <input class="form-check-input statut-checkbox" type="checkbox" id="statut_decede"
                            <?= $decede_checked ? 'checked' : '' ?> data-statut="decede"
                            <?= $is_dcd_av_bio ? 'checked disabled' : '' ?>>
                        <label class="form-check-label small" for="statut_decede">
                            <span class="badge bg-danger">Décédé</span>
                        </label>
                    </div>
                </div>
                <div class="alert alert-info mt-1 mb-0 py-1 small" id="info-message">
                    <i class="fas fa-info-circle"></i>
                    <?= $is_dcd_av_bio ? 'Catégorie DCD_AV_BIO : toujours considéré comme décédé' : ($cat === 'DCD_AP_BIO' ? 'Cochez "Vivant" pour contrôler le militaire ou "Décédé" pour enregistrer un bénéficiaire' : 'Sélectionnez un statut') ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Contrôle avec titre dynamique (incluant le nom du militaire) -->
        <div class="card-modern" id="controle-section"
            style="<?= ($show_controle_vivant || $show_controle_decede) ? '' : 'display:none' ?>">
            <div class="card-header">
                <h3 id="titre-controle">
                    <i class="fas <?= $show_controle_vivant ? 'fa-user' : 'fa-users' ?>" id="titre-icon"></i>
                    <span id="titre-texte">
                        <?= $show_controle_vivant ? 'Contrôle du militaire : ' . $nom_militaire : 'Bénéficiaire(s) du défunt : ' . $nom_militaire ?>
                    </span>
                </h3>
                <button class="btn-new-search" id="btn-new-search"><i class="fas fa-search"></i> Nouvelle recherche</button>
            </div>
            <div class="card-body" id="controle-content">
                <?php if ($show_controle_vivant): ?>
                    <!-- Vivant -->
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="?action=valider&matricule=<?= urlencode($militaire['matricule']) ?>&lien=<?= urlencode('Militaire lui-même') ?>&mention=<?= urlencode('Présent') ?>&q=<?= urlencode($q) ?><?= $statut_vivant ? '&statut_vivant=on' : '' ?>"
                            class="btn-mention btn-present" id="btn-present">
                            <i class="fas fa-check-circle"></i> Présent
                        </a>
                    </div>

                <?php elseif ($show_controle_decede): ?>
                    <!-- Défunt - VERSION COMPACTE AVEC DEUX COLONNES POUR BÉNÉFICIAIRES -->
                    <form method="get" id="form-controle" class="compact-form">
                        <input type="hidden" name="action" value="valider">
                        <input type="hidden" name="matricule" value="<?= htmlspecialchars($militaire['matricule']) ?>">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                        <?php if ($decede_checked): ?>
                            <input type="hidden" name="statut_decede" value="on">
                        <?php endif; ?>

                        <!-- Section bénéficiaires en deux colonnes -->
                        <div class="row g-2 mb-2">
                            <!-- Colonne Bénéficiaire existant -->
                            <div class="col-md-6">
                                <div class="beneficiaire-card compact h-100">
                                    <label class="fw-bold small"><i class="fas fa-user-tie"></i> Bénéficiaire existant</label>
                                    <?php if ($benef_nom): ?>
                                        <div class="fw-bold text-success mt-1" style="font-size:0.95rem;">
                                            <?= htmlspecialchars($benef_nom) ?></div>
                                        <input type="hidden" name="beneficiaire" value="<?= htmlspecialchars($benef_nom) ?>">
                                    <?php else: ?>
                                        <div class="text-muted mt-1" style="font-size:0.95rem;">Aucun bénéficiaire existant</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Colonne Nouveau bénéficiaire -->
                            <div class="col-md-6">
                                <div class="beneficiaire-card compact h-100">
                                    <label class="fw-bold mb-1 small"><i class="fas fa-user-plus"></i> Nouveau
                                        bénéficiaire</label>
                                    <input type="text" name="new_beneficiaire" class="form-control-modern"
                                        placeholder="Nom complet du nouveau bénéficiaire"
                                        value="<?= htmlspecialchars($_GET['new_beneficiaire'] ?? '') ?>">
                                    <small class="text-muted d-block mt-1"><i class="fas fa-info-circle"></i> Remplissez pour
                                        ajouter un nouveau.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Deux colonnes : liens (gauche) et observations (droite) -->
                        <div class="row g-2">
                            <!-- Colonne Liens de parenté (8/12) -->
                            <div class="col-md-8">
                                <div class="liens-grid">
                                    <?php
                                    $groupes = [
                                        'Epouse/Epoux' => [
                                            'Epouse' => 'Veuve',
                                            'Epoux' => 'Veuf'
                                        ],
                                        'Fils/Fille' => [
                                            'Fils' => 'Orphelin',
                                            'Fille' => 'Orphelin'
                                        ],
                                        'Père/Mère' => [
                                            'Père' => 'Tuteur',
                                            'Mère' => 'Tuteur'
                                        ],
                                        'Frère/Sœur' => [
                                            'Frère' => 'Tuteur',
                                            'Sœur' => 'Tuteur'
                                        ]
                                    ];

                                    foreach ($groupes as $groupe => $liens): ?>
                                        <div class="lien-groupe compact">
                                            <label><?= $groupe ?></label>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($liens as $lien => $transformation): ?>
                                                    <div class="form-check form-check-inline" style="margin-right: 0;">
                                                        <input class="form-check-input" type="checkbox" name="lien"
                                                            id="lien_<?= $lien ?>" value="<?= $lien ?>">
                                                        <label class="form-check-label" for="lien_<?= $lien ?>">
                                                            <?= $lien ?> <small class="text-muted">(→ <?= $transformation ?>)</small>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Colonne Observations (4/12) -->
                            <div class="col-md-4">
                                <div class="observations-group compact">
                                    <label class="fw-bold small"><i class="fas fa-pencil-alt"></i> Observations
                                        (optionnel)</label>
                                    <textarea name="observations" class="form-control-modern"
                                        placeholder="Informations complémentaires..."><?= htmlspecialchars($observations) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="actions-container compact">
                            <button type="submit" name="mention" value="Favorable" class="btn-mention btn-favorable">
                                <i class="fas fa-thumbs-up"></i> Favorable
                            </button>
                            <button type="submit" name="mention" value="Défavorable" class="btn-mention btn-defavorable">
                                <i class="fas fa-thumbs-down"></i> Défavorable
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($q !== ''): ?>
        <div class="alert alert-warning text-center">Aucun militaire trouvé pour "<?= htmlspecialchars($q) ?>"</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</div>

<!-- Scripts locaux -->
<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/sweetalert2@11.js"></script>

<script>
    (function() {
        'use strict';
        let clickedMention = null;

        $(document).ready(function() {
            bindStaticHandlers();
            showMessages();
            // Détection tactile pour désactiver certains effets hover
            if ('ontouchstart' in window) {
                $('body').addClass('touch-device');
            }
        });

        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;

            const toast = document.createElement('div');
            toast.className = `toast-message ${type}`;
            toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;

            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3500);
        }

        function bindStaticHandlers() {
            // Gestionnaires statiques - liés UNE SEULE FOIS (jamais re-liés)
            $('.statut-checkbox').on('change', handleStatutChange);
            $('#btn-new-search').on('click', function(e) {
                e.preventDefault();
                $('#search-section').show();
                $('.militaire-info').hide();
                $('.statut-temp').hide();
                $('#controle-section').hide();
                window.history.pushState({}, '', window.location.pathname);
                $('#search-input').val('').focus();
            });

            // Délégation d'événements pour le contenu dynamique (rechargé par AJAX)
            // Ces handlers restent actifs même quand #controle-content est remplacé
            $('#controle-section').on('click', '#btn-present', handlePresentClick);
            $('#controle-section').on('submit', '#form-controle', handleFormSubmit);
            $('#controle-section').on('click', '#form-controle button[type=submit]', function() {
                clickedMention = $(this).val();
            });
            $(document).on('change', 'input[name="lien"]', function() {
                $('input[name="lien"]').not(this).prop('checked', false);
            });
        }

        function handleStatutChange() {
            const $this = $(this);
            const isChecked = $this.is(':checked');
            const statutType = $this.data('statut');

            if (isChecked) {
                if (statutType === 'vivant') {
                    $('#statut_decede').prop('checked', false);
                } else {
                    $('#statut_vivant').prop('checked', false);
                }
            }
            updateStatut();
        }

        function updateStatut() {
            const vivantChecked = $('#statut_vivant').is(':checked');
            const decedeChecked = $('#statut_decede').is(':checked');
            const q = new URLSearchParams(window.location.search).get('q') || '<?= htmlspecialchars($q) ?>';

            $('#loading-icon').removeClass('d-none');
            $('#status-text').text('Mise à jour...');
            $('#controle-section').addClass('loading');

            const params = new URLSearchParams({
                q
            });
            if (vivantChecked) params.set('statut_vivant', 'on');
            if (decedeChecked) params.set('statut_decede', 'on');

            $.ajax({
                url: window.location.pathname + '?' + params.toString(),
                method: 'GET',
                success: function(response) {
                    const doc = new DOMParser().parseFromString(response, 'text/html');
                    const newContent = doc.getElementById('controle-content');
                    const newInfo = doc.getElementById('info-message');

                    if (newContent) $('#controle-content').html(newContent.innerHTML);
                    if (newInfo) $('#info-message').html(newInfo.innerHTML);

                    // Récupérer le nom du militaire depuis l'élément HTML (même s'il est caché)
                    const nomMilitaire = $('.militaire-info .info-item:contains("Noms") .info-value').text()
                        .trim();

                    // Mise à jour de l'affichage en fonction des cases cochées
                    if (vivantChecked && !decedeChecked) {
                        $('#controle-section').show();

                        // Mettre à jour le titre pour "Contrôle du militaire" avec le nom
                        $('#titre-icon').removeClass('fa-users').addClass('fa-user');
                        $('#titre-texte').text('Contrôle du militaire : ' + nomMilitaire);
                    } else if (decedeChecked && !vivantChecked) {
                        $('#controle-section').show();

                        // Mettre à jour le titre pour "Bénéficiaire(s) du défunt" avec le nom
                        $('#titre-icon').removeClass('fa-user').addClass('fa-users');
                        $('#titre-texte').text('Bénéficiaire(s) du défunt : ' + nomMilitaire);
                    } else {
                        $('#controle-section').hide();
                    }

                    // Gestion de l'affichage de la carte info militaire
                    if (vivantChecked || decedeChecked) {
                        $('.militaire-info').hide();
                    } else {
                        $('.militaire-info').show();
                    }

                    window.history.pushState({}, '', window.location.pathname + '?' + params);
                    $('#status-text').text('Modification temps réel');
                },
                error: () => {
                    showToast('Échec de la mise à jour', 'error');
                    $('#status-text').text('Erreur');
                },
                complete: () => {
                    $('#loading-icon').addClass('d-none');
                    $('#controle-section').removeClass('loading');
                }
            });
        }

        function handlePresentClick(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Confirmer',
                text: 'Attribuer la mention "Présent" ?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: 'Oui'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = $(this).attr('href');
            });
        }

        function handleFormSubmit(e) {
            e.preventDefault();
            const $form = $(this);

            if ($form.find('input[name="lien"]:checked').length === 0) {
                return Swal.fire('Attention', 'Sélectionnez un lien de parenté', 'warning');
            }

            const benef = $form.find('input[name="beneficiaire"]').val(); // hidden field, may be undefined
            const newBenef = $form.find('input[name="new_beneficiaire"]').val();

            // Vérifier qu'au moins un bénéficiaire est renseigné
            // (benef peut être présent via hidden, newBenef via input)
            if ((!benef || benef.trim() === '') && (!newBenef || newBenef.trim() === '')) {
                return Swal.fire('Attention', 'Veuillez renseigner un bénéficiaire (existant ou nouveau)', 'warning');
            }

            const lien = $form.find('input[name="lien"]:checked').val();
            const message = `Attribuer la mention "${clickedMention === 'Favorable' ? 'Favorable' : 'Défavorable'}"${
            ['Père','Mère','Frère','Sœur'].includes(lien) ? ' (→ Tuteur)' :
            lien === 'Epoux' ? ' (→ Veuf)' :
            lien === 'Epouse' ? ' (→ Veuve)' :
            ['Fils','Fille'].includes(lien) ? ' (→ Orphelin)' : ''
        } ?`;

            Swal.fire({
                title: 'Confirmation',
                text: message,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: clickedMention === 'Favorable' ? '#ffc107' : '#dc3545',
                confirmButtonText: 'Valider'
            }).then((result) => {
                if (result.isConfirmed) {
                    $form.append($('<input>', {
                        type: 'hidden',
                        name: 'mention',
                        value: clickedMention
                    }));
                    $form[0].submit();
                }
            });
        }

        function showMessages() {
            <?php if ($toast_message): ?>
                showToast('<?= addslashes($toast_message) ?>', '<?= $toast_type ?>');
            <?php endif; ?>

            <?php if (isset($_SESSION['swal'])): ?>
                Swal.fire({
                    icon: '<?= $_SESSION['swal']['icon'] ?>',
                    title: '<?= $_SESSION['swal']['title'] ?>',
                    text: '<?= $_SESSION['swal']['text'] ?>',
                    confirmButtonText: '<?= $_SESSION['swal']['confirmButtonText'] ?? "OK" ?>',
                    cancelButtonText: '<?= $_SESSION['swal']['cancelButtonText'] ?? "Annuler" ?>'
                }).then((result) => {
                    if (result.isConfirmed && '<?= $_SESSION['swal']['confirmButtonText'] ?>' ===
                        'Nouvelle recherche') {
                        window.location.href = window.location.pathname;
                    }
                });
                <?php unset($_SESSION['swal']); ?>
            <?php endif; ?>
        }
    })();
</script>

<!-- Script de recherche en temps réel -->
<script>
    (function() {
        'use strict';
        const searchInput = document.getElementById('search-input');
        const searchResults = document.getElementById('search-results');
        let searchTimeout = null;
        let currentFocus = -1;

        if (!searchInput) return;

        function fetchResults(query) {
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            fetch(`?ajax=search&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => displayResults(data))
                .catch(() => searchResults.style.display = 'none');
        }

        function displayResults(results) {
            searchResults.innerHTML = '';
            if (!results || results.length === 0) {
                searchResults.style.display = 'none';
                return;
            }

            results.forEach((militaire, index) => {
                const isAlreadyControlled = ['1', 'true', 'oui'].includes(String(militaire.deja_controle).toLowerCase());
                const item = document.createElement(isAlreadyControlled ? 'div' : 'a');

                if (!isAlreadyControlled) {
                    item.href = `?q=${encodeURIComponent(militaire.matricule)}`;
                }

                item.className = `list-group-item ${isAlreadyControlled ? 'disabled-result' : 'list-group-item-action'}`;
                item.setAttribute('data-index', index);

                // Déterminer la classe, l'icône et le texte du badge en fonction de la catégorie
                let badgeClass = 'badge-actif-mini';
                let badgeIcon = 'fa-user-check';
                let badgeText = 'ACTIF';

                switch (militaire.categorie) {
                    case 'DCD_AV_BIO':
                        badgeClass = 'badge-decede-av-bio-mini';
                        badgeIcon = 'fa-skull-crossbones';
                        badgeText = 'DÉCÉDÉ AV. BIO';
                        break;
                    case 'DCD_AP_BIO':
                        badgeClass = 'badge-dcd-ap-bio-mini';
                        badgeIcon = 'fa-skull';
                        badgeText = 'DÉCÉDÉ AP. BIO';
                        break;
                    case 'RETRAITES':
                        badgeClass = 'badge-retraite-mini';
                        badgeIcon = 'fa-user-clock';
                        badgeText = 'RETRAITÉ';
                        break;
                    case 'INTEGRES':
                        badgeClass = 'badge-integre-mini';
                        badgeIcon = 'fa-user-plus';
                        badgeText = 'INTÉGRÉ';
                        break;
                    default:
                        // ACTIF ou autre (par défaut)
                        badgeClass = 'badge-actif-mini';
                        badgeIcon = 'fa-user-check';
                        badgeText = 'ACTIF';
                }

                item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <strong>${escapeHtml(militaire.noms)}</strong>
                    <div class="d-inline-flex align-items-center" style="gap:6px;">
                        <span class="badge-statut-mini ${badgeClass}"><i class="fas ${badgeIcon}"></i> ${badgeText}</span>
                        ${isAlreadyControlled ? '<span class="badge-statut-mini badge-deja-controle-mini"><i class="fas fa-exclamation-circle"></i> Déjà contrôlé</span>' : ''}
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 small">
                    <span><i class="fas fa-id-card"></i> ${escapeHtml(militaire.matricule)}</span>
                    <span><i class="fas fa-star"></i> ${escapeHtml(militaire.grade || 'N/A')}</span>
                    <span><i class="fas fa-map-marker-alt"></i> ${escapeHtml(militaire.garnison || 'N/A')}</span>
                </div>
            `;
                searchResults.appendChild(item);
            });

            // Ajuster la hauteur maximale en fonction du nombre de résultats
            const resultCount = results.length;
            let maxHeight = 400; // hauteur maximale par défaut

            if (resultCount <= 3) {
                maxHeight = 200; // hauteur réduite pour peu de résultats
            } else if (resultCount <= 5) {
                maxHeight = 300; // hauteur moyenne
            } else {
                maxHeight = 450; // hauteur maximale pour beaucoup de résultats
            }

            searchResults.style.maxHeight = maxHeight + 'px';
            searchResults.style.display = 'block';
            currentFocus = -1;
        }

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            searchTimeout = setTimeout(() => fetchResults(query), 300);
        });

        searchInput.addEventListener('keydown', function(e) {
            const items = searchResults.querySelectorAll('.list-group-item-action');
            if (items.length === 0) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentFocus = (currentFocus + 1) % items.length;
                updateActiveItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentFocus = (currentFocus - 1 + items.length) % items.length;
                updateActiveItem(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentFocus > -1 && items[currentFocus]) {
                    window.location.href = items[currentFocus].href;
                }
            }
        });

        function updateActiveItem(items) {
            items.forEach((item, idx) => {
                if (idx === currentFocus) item.classList.add('active');
                else item.classList.remove('active');
            });
        }

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    })();
</script>

<style>
    /* Désactiver certains effets hover sur les écrans tactiles */
    body.touch-device .btn-mention:hover {
        transform: none;
        box-shadow: none;
    }
</style>