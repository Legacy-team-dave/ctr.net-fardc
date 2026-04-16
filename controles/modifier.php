<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_SIG', 'GESTIONNAIRE']);

$id_bien = $_GET['id'] ?? 0;
if (!$id_bien) {
    $_SESSION['error_message'] = 'ID de bien manquant.';
    header('Location: liste.php');
    exit;
}

// Récupérer les données actuelles
$stmt = $pdo->prepare("SELECT * FROM biens_immobiliers WHERE id_bien = ?");
$stmt->execute([$id_bien]);
$bien = $stmt->fetch();

if (!$bien) {
    $_SESSION['error_message'] = 'Bien introuvable.';
    header('Location: liste.php');
    exit;
}

// --- TRAITEMENT DU FORMULAIRE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'type_bien'            => $_POST['type_bien'],
        'denomination'         => trim($_POST['denomination']),
        'id_province'          => $_POST['id_province'],
        'id_ville'             => $_POST['id_ville'],
        'adresse'              => $_POST['adresse'] ?: null,
        'date_construction'    => !empty($_POST['date_construction']) ? $_POST['date_construction'] : null,
        'mode_acquisition'     => $_POST['mode_acquisition'] ?: null,
        'situation_juridique'  => $_POST['situation_juridique'] ?: null,
        'bornage'              => isset($_POST['bornage']) ? 1 : 0,
        'delimitation'         => $_POST['delimitation'] ?: null,
        'superficie_l'         => !empty($_POST['superficie_l']) ? $_POST['superficie_l'] : null,
        'superficie_w'         => !empty($_POST['superficie_w']) ? $_POST['superficie_w'] : null,
        'nombre_batiments'     => $_POST['nombre_batiments'] ?: 0,
        'nombre_maisons'       => $_POST['nombre_maisons'] ?: 0,
        'observations'         => $_POST['observations'] ?: null
    ];

    // Gestion de la géométrie
    $geojson = !empty($_POST['geojson']) ? $_POST['geojson'] : null;

    // Vérification de la longueur du type_bien
    $max_length_type_bien = 50;
    if (strlen($data['type_bien']) > $max_length_type_bien) {
        $error = "Le type de bien ne doit pas dépasser $max_length_type_bien caractères.";
    } else {
        $sql = "UPDATE biens_immobiliers SET 
                type_bien = ?, denomination = ?, id_province = ?, id_ville = ?, adresse = ?, 
                date_construction = ?, mode_acquisition = ?, situation_juridique = ?, bornage = ?, 
                delimitation = ?, superficie_l = ?, superficie_w = ?, nombre_batiments = ?, 
                nombre_maisons = ?, observations = ?, geometrie = ST_GeomFromGeoJSON(?)
                WHERE id_bien = ?";

        $stmt = $pdo->prepare($sql);
        $params = [
            $data['type_bien'],
            $data['denomination'],
            $data['id_province'],
            $data['id_ville'],
            $data['adresse'],
            $data['date_construction'],
            $data['mode_acquisition'],
            $data['situation_juridique'],
            $data['bornage'],
            $data['delimitation'],
            $data['superficie_l'],
            $data['superficie_w'],
            $data['nombre_batiments'],
            $data['nombre_maisons'],
            $data['observations'],
            $geojson,
            $id_bien
        ];

        if ($stmt->execute($params)) {
            log_action('MODIFICATION', 'biens_immobiliers', $id_bien, 'Modification du bien');
            
            // ===================================================
            // REDIRECTION AVEC MESSAGE DE SUCCÈS EN SESSION
            // ===================================================
            $_SESSION['success_message'] = 'Bien modifié avec succès.';
            header('Location: liste.php');
            exit;
        } else {
            $error = "Erreur lors de la modification.";
        }
    }
}

// --- AFFICHAGE DE LA PAGE ---
$page_titre = 'Modifier le bien : ' . $bien['denomination'];
include '../../includes/header.php';

$provinces = $pdo->query("SELECT id_province, denomination FROM provinces ORDER BY denomination")->fetchAll();
$types = ['Maison_de_l\'Etat', 'Concession', 'Ferme', 'Etat_Major_des_Unités', 'Camp_Militaire', 'Base_Militaire'];

// Ne pas récupérer les villes ici - elles seront chargées dynamiquement par AJAX
$villes = [];
?>

<!-- Inclusion des CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* Style de la carte améliorée - IDENTIQUE À LA PAGE D'AJOUT */
.modern-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

.modern-card .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 15px 25px;
    border: none;
}

.modern-card .card-header h3 {
    color: white;
    margin: 0;
    font-weight: 600;
    font-size: 1.3rem;
}

.modern-card .card-header h3 i {
    margin-right: 8px;
}

.modern-card .card-body {
    padding: 25px;
}

/* Grille réorganisée pour meilleure visibilité */
.form-row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -10px;
    margin-left: -10px;
}

.form-col {
    flex: 1;
    padding: 0 10px;
    min-width: 250px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
    display: block;
    font-size: 0.9rem;
}

.form-group label i {
    color: #667eea;
    margin-right: 5px;
    width: 16px;
}

/* Hauteur standard pour tous les champs - IDENTIQUE À LA PAGE D'AJOUT */
.form-control,
.form-select {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 10px 12px;
    height: auto;
    min-height: 42px;
    line-height: 1.5;
    transition: all 0.3s;
    font-size: 0.9rem;
    width: 100%;
}

/* Hauteur spécifique pour les select */
select.form-control,
.form-select {
    padding: 9px 12px;
    min-height: 42px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23495057' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 14px;
    padding-right: 35px;
}

/* Hauteur spécifique pour les textareas */
textarea.form-control {
    min-height: 70px;
    padding: 10px 12px;
    resize: vertical;
}

/* Hauteur spécifique pour les petits textareas */
textarea[name="adresse"],
textarea[name="delimitation"] {
    min-height: 60px;
}

/* Hauteur spécifique pour les grands textareas */
textarea[name="observations"],
textarea[name="geojson"] {
    min-height: 80px;
}

/* Style pour les champs avec placeholder */
.form-control::placeholder,
.form-select::placeholder {
    color: #adb5bd;
    opacity: 1;
    font-size: 0.85rem;
}

.form-control:focus,
.form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    outline: none;
}

/* Checkbox personnalisée - plus compacte */
.form-check {
    display: flex;
    align-items: center;
    margin: 10px 0 15px 0;
    min-height: 36px;
    padding-left: 0;
}

.form-check-input {
    width: 18px;
    height: 18px;
    margin: 0 8px 0 0;
    cursor: pointer;
    border: 2px solid #e0e0e0;
    border-radius: 4px;
}

.form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.form-check-label {
    display: flex;
    align-items: center;
    margin: 0;
    line-height: 1.4;
    font-size: 0.9rem;
    cursor: pointer;
}

.form-check-label i {
    margin-right: 5px;
    color: #28a745;
    font-size: 0.9rem;
}

/* Badge personnalisé - plus compact */
.badge-modern {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 500;
    min-height: 32px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.85rem;
    backdrop-filter: blur(5px);
}

/* BOUTONS RÉDUITS ET ALIGNÉS - IDENTIQUE À LA PAGE D'AJOUT */
.card-footer {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    padding: 15px 25px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.action-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
    align-items: center;
}

.btn-modern {
    border-radius: 8px;
    padding: 8px 18px;
    font-weight: 500;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    min-height: 38px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 100px;
    text-decoration: none;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary-modern:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    color: white;
    text-decoration: none;
}

.btn-secondary-modern {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
}

.btn-secondary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
    color: white;
    text-decoration: none;
}

.btn-warning-modern {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: white;
}

.btn-warning-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
    color: white;
    text-decoration: none;
}

.btn-modern i {
    font-size: 0.9rem;
}

/* Message d'aide */
.small.text-muted {
    font-size: 0.75rem;
    margin-top: 3px;
    margin-bottom: 0;
    display: block;
    line-height: 1.3;
}

/* Grille en 3 colonnes pour les premières lignes */
.row-three-cols {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

/* Grille en 4 colonnes */
.row-four-cols {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

/* Grille en 2 colonnes */
.row-two-cols {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

/* Animation de chargement */
.btn-loading {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

.btn-loading i {
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Media queries pour mobile */
@media (max-width: 768px) {
    .modern-card .card-body {
        padding: 15px;
    }

    .row-three-cols,
    .row-four-cols,
    .row-two-cols {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .action-buttons {
        flex-direction: column;
        width: 100%;
    }

    .btn-modern {
        width: 100%;
        min-width: auto;
    }

    .card-footer {
        padding: 15px;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    .row-four-cols {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Style pour les champs obligatoires */
.text-danger {
    color: #dc3545;
    font-size: 0.85rem;
    margin-left: 2px;
}

/* Style pour le conteneur GeoJSON */
.geojson-container {
    margin-top: 15px;
}

/* Amélioration des tooltips */
[title] {
    cursor: help;
}
</style>

<div class="container-fluid py-3">
    <div class="row">
        <div class="col-12">
            <div class="card modern-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">
                        <i class="fas fa-edit"></i>Modifier le bien : <?= h($bien['denomination']) ?>
                    </h3>
                    <span class="badge-modern">
                        <i class="fas fa-building"></i> ID: <?= $id_bien ?>
                    </span>
                </div>

                <form method="post" id="editForm" onsubmit="return validateForm(event)">
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                        <script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: '<?= addslashes($error) ?>',
                            confirmButtonColor: '#3085d6',
                            confirmButtonText: 'Compris'
                        });
                        </script>
                        <?php endif; ?>

                        <!-- PREMIÈRE LIGNE : Type et Dénomination (2 colonnes) -->
                        <div class="row-two-cols">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-building"></i>
                                    Type de bien <span class="text-danger">*</span>
                                </label>
                                <select name="type_bien" class="form-control" required>
                                    <option value="" disabled>Sélectionner un type</option>
                                    <?php foreach ($types as $type): ?>
                                    <option value="<?= $type ?>" <?= $bien['type_bien'] == $type ? 'selected' : '' ?>>
                                        <?= $type ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-tag"></i>
                                    Dénomination <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="denomination" class="form-control"
                                    value="<?= h($bien['denomination']) ?>" placeholder="Nom du bien" required
                                    maxlength="100">
                                <small class="text-muted">Nom unique du bien immobilier</small>
                            </div>
                        </div>

                        <!-- DEUXIÈME LIGNE : Province et Ville (2 colonnes) -->
                        <div class="row-two-cols">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-map-marker-alt"></i>
                                    Province <span class="text-danger">*</span>
                                </label>
                                <select name="id_province" id="province" class="form-control" required>
                                    <option value="" disabled>Sélectionner une province</option>
                                    <?php foreach ($provinces as $p): ?>
                                    <option value="<?= $p['id_province'] ?>"
                                        <?= $bien['id_province'] == $p['id_province'] ? 'selected' : '' ?>>
                                        <?= h($p['denomination']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-city"></i>
                                    Ville <span class="text-danger">*</span>
                                </label>
                                <select name="id_ville" id="ville" class="form-control" required>
                                    <option value="" selected disabled>Sélectionner d'abord une province</option>
                                </select>
                            </div>
                        </div>

                        <!-- TROISIÈME LIGNE : Adresse (1 colonne pleine largeur) -->
                        <div class="form-group">
                            <label>
                                <i class="fas fa-address-card"></i>
                                Adresse
                            </label>
                            <textarea name="adresse" class="form-control" rows="2"
                                placeholder="Adresse complète du bien (optionnel)"><?= h($bien['adresse']) ?></textarea>
                        </div>

                        <!-- QUATRIÈME LIGNE : Dates et infos (3 colonnes) -->
                        <div class="row-three-cols">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-calendar"></i>
                                    Date de construction
                                </label>
                                <input type="date" name="date_construction" class="form-control"
                                    value="<?= $bien['date_construction'] ?>">
                                <small class="text-muted">Optionnel</small>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-hand-holding-usd"></i>
                                    Mode d'acquisition
                                </label>
                                <input type="text" name="mode_acquisition" class="form-control"
                                    value="<?= h($bien['mode_acquisition']) ?>" placeholder="Ex: Achat, Don, Héritage">
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-gavel"></i>
                                    Situation juridique
                                </label>
                                <input type="text" name="situation_juridique" class="form-control"
                                    value="<?= h($bien['situation_juridique']) ?>" placeholder="Statut légal">
                            </div>
                        </div>

                        <!-- CINQUIÈME LIGNE : Bornage (checkbox) -->
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" name="bornage" class="form-check-input" id="bornage" value="1"
                                    <?= $bien['bornage'] ? 'checked' : '' ?>>
                                <i class="fas fa-check-circle text-success"></i>
                                Bornage effectué
                            </label>
                            <small class="text-muted d-block mt-1">Cocher si le bornage a été réalisé</small>
                        </div>

                        <!-- SIXIÈME LIGNE : Délimitation -->
                        <div class="form-group">
                            <label>
                                <i class="fas fa-border-all"></i>
                                Délimitation
                            </label>
                            <textarea name="delimitation" class="form-control" rows="2"
                                placeholder="Description des limites du terrain (optionnel)"><?= h($bien['delimitation']) ?></textarea>
                        </div>

                        <!-- SEPTIÈME LIGNE : Dimensions et nombres (4 colonnes) -->
                        <div class="row-four-cols">
                            <div class="form-group">
                                <label>Longueur (m)</label>
                                <input type="number" step="0.01" name="superficie_l" class="form-control"
                                    value="<?= $bien['superficie_l'] ?>" placeholder="0.00" min="0">
                                <small class="text-muted">Optionnel</small>
                            </div>

                            <div class="form-group">
                                <label>Largeur (m)</label>
                                <input type="number" step="0.01" name="superficie_w" class="form-control"
                                    value="<?= $bien['superficie_w'] ?>" placeholder="0.00" min="0">
                                <small class="text-muted">Optionnel</small>
                            </div>

                            <div class="form-group">
                                <label>Nb bâtiments</label>
                                <input type="number" name="nombre_batiments" class="form-control"
                                    value="<?= $bien['nombre_batiments'] ?>" min="0" placeholder="0">
                            </div>

                            <div class="form-group">
                                <label>Nb maisons</label>
                                <input type="number" name="nombre_maisons" class="form-control"
                                    value="<?= $bien['nombre_maisons'] ?>" min="0" placeholder="0">
                            </div>
                        </div>

                        <!-- HUITIÈME LIGNE : Observations -->
                        <div class="form-group">
                            <label>
                                <i class="fas fa-comment"></i>
                                Observations
                            </label>
                            <textarea name="observations" class="form-control" rows="2"
                                placeholder="Remarques particulières... (optionnel)"><?= h($bien['observations']) ?></textarea>
                        </div>

                        <!-- NEUVIÈME LIGNE : Géométrie GeoJSON -->
                        <div class="form-group geojson-container">
                            <label>
                                <i class="fas fa-draw-polygon"></i>
                                Géométrie (GeoJSON)
                                <button type="button" class="btn btn-sm btn-link p-0 ml-2" onclick="showGeojsonHelp()">
                                    <i class="fas fa-question-circle"></i>
                                </button>
                            </label>
                            <textarea name="geojson" class="form-control" rows="2"
                                placeholder='{"type":"Polygon","coordinates":[...]}'><?php
                                // Récupérer la géométrie actuelle en GeoJSON
                                $stmt_geo = $pdo->prepare("SELECT ST_AsGeoJSON(geometrie) as geojson FROM biens_immobiliers WHERE id_bien = ?");
                                $stmt_geo->execute([$id_bien]);
                                $geojson_actuel = $stmt_geo->fetchColumn();
                                if ($geojson_actuel) echo h($geojson_actuel);
                            ?></textarea>
                            <small class="text-muted">
                                Format GeoJSON valide - Optionnel
                            </small>
                        </div>
                    </div>

                    <!-- Footer avec boutons alignés et réduits -->
                    <div class="card-footer">
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary-modern btn-modern" id="submitBtn">
                                <i class="fas fa-save"></i>Mettre à jour
                            </button>
                            <a href="liste.php" class="btn btn-secondary-modern btn-modern" id="cancelBtn">
                                <i class="fas fa-times"></i>Annuler
                            </a>
                            <button type="button" class="btn btn-warning-modern btn-modern" onclick="confirmReset()">
                                <i class="fas fa-undo"></i>Réinitialiser
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction de validation du formulaire
function validateForm(event) {
    event.preventDefault();

    const typeBien = document.querySelector('[name="type_bien"]').value;
    const denomination = document.querySelector('[name="denomination"]').value.trim();
    const province = document.querySelector('[name="id_province"]').value;
    const ville = document.querySelector('[name="id_ville"]').value;

    // Validation des champs obligatoires
    if (!typeBien || !denomination || !province || !ville) {
        Swal.fire({
            icon: 'warning',
            title: 'Formulaire incomplet',
            text: 'Veuillez remplir tous les champs obligatoires.',
            confirmButtonColor: '#3085d6'
        });
        return false;
    }

    // Validation du GeoJSON si présent
    const geojsonField = document.querySelector('[name="geojson"]');
    if (geojsonField.value.trim()) {
        try {
            JSON.parse(geojsonField.value);
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Format GeoJSON invalide',
                text: 'Veuillez corriger le format GeoJSON.',
                confirmButtonColor: '#3085d6'
            });
            return false;
        }
    }

    // Confirmation avec SweetAlert2
    Swal.fire({
        title: 'Confirmer la modification',
        html: `
            <p>Êtes-vous sûr de vouloir modifier ce bien ?</p>
            <div class="alert alert-warning mt-3 p-2">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Cette action modifiera définitivement les données.
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, modifier',
        cancelButtonText: 'Annuler',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Désactiver le bouton et soumettre
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.classList.add('btn-loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner"></i>Traitement...';

            // Soumettre le formulaire
            document.getElementById('editForm').submit();
        }
    });

    return false;
}

// SCRIPT DE CHARGEMENT DES VILLES - IDENTIQUE À AJOUTER.PHP
document.getElementById('province').addEventListener('change', function() {
    var provinceId = this.value;
    var villeSelect = document.getElementById('ville');

    if (!provinceId) {
        villeSelect.innerHTML =
            '<option value="" selected disabled>Sélectionner d\'abord une province</option>';
        villeSelect.disabled = true;
        return;
    }

    villeSelect.innerHTML = '<option value="">Chargement...</option>';
    villeSelect.disabled = true;

    fetch('ajax/get_villes.php?province=' + provinceId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            villeSelect.innerHTML = '<option value="" selected disabled>Sélectionner une ville</option>';
            if (data.length > 0) {
                data.forEach(v => {
                    villeSelect.innerHTML +=
                        `<option value="${v.id_ville}">${escapeHtml(v.denomination)}</option>`;
                });
                villeSelect.disabled = false;
            } else {
                villeSelect.innerHTML = '<option value="" selected disabled>Aucune ville trouvée</option>';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            villeSelect.innerHTML = '<option value="" selected disabled>Erreur de chargement</option>';
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors du chargement des villes',
                confirmButtonColor: '#3085d6'
            });
        });
});

// Fonction d'échappement HTML
function escapeHtml(text) {
    if (!text) return text;
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) {
        return map[m];
    });
}

// Charger les villes initiales si une province est déjà sélectionnée
document.addEventListener('DOMContentLoaded', function() {
    const provinceSelect = document.getElementById('province');
    const villeSelect = document.getElementById('ville');
    const currentProvince = provinceSelect.value;
    const currentVille = <?= $bien['id_ville'] ?: 'null' ?>;

    if (currentProvince) {
        // Charger les villes de la province actuelle
        fetch('ajax/get_villes.php?province=' + currentProvince)
            .then(response => response.json())
            .then(data => {
                villeSelect.innerHTML =
                    '<option value="" selected disabled>Sélectionner une ville</option>';
                if (data.length > 0) {
                    data.forEach(v => {
                        const selected = (v.id_ville == currentVille) ? 'selected' : '';
                        villeSelect.innerHTML +=
                            `<option value="${v.id_ville}" ${selected}>${escapeHtml(v.denomination)}</option>`;
                    });
                    villeSelect.disabled = false;
                } else {
                    villeSelect.innerHTML =
                        '<option value="" selected disabled>Aucune ville trouvée</option>';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                villeSelect.innerHTML = '<option value="" selected disabled>Erreur de chargement</option>';
            });
    } else {
        villeSelect.disabled = true;
    }
});

// Réinitialisation du formulaire
function confirmReset() {
    Swal.fire({
        title: 'Réinitialiser le formulaire',
        text: 'Voulez-vous vraiment annuler toutes les modifications ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, réinitialiser',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Recharger la page pour retrouver les valeurs d'origine
            window.location.reload();
        }
    });
}

// Aide pour le format GeoJSON
function showGeojsonHelp() {
    Swal.fire({
        title: 'Format GeoJSON',
        html: `
            <div class="text-left">
                <p class="mb-3">Format accepté :</p>
                <pre class="bg-light p-3 rounded" style="font-size: 12px;">
{
    "type": "Polygon",
    "coordinates": [[
        [longitude1, latitude1],
        [longitude2, latitude2],
        [longitude3, latitude3],
        [longitude1, latitude1]
    ]]
}</pre>
                <p class="text-muted mt-3 mb-0">
                    <i class="fas fa-info-circle mr-1"></i>
                    Utilisez des coordonnées valides (longitude, latitude)
                </p>
                <p class="text-muted small">
                    Formats supportés : Point, LineString, Polygon, MultiPolygon
                </p>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Compris',
        confirmButtonColor: '#667eea'
    });
}

// Confirmation avant de quitter avec des modifications non sauvegardées
let formModified = false;
document.querySelectorAll('#editForm input, #editForm select, #editForm textarea').forEach(element => {
    element.addEventListener('change', () => formModified = true);
    element.addEventListener('keyup', () => formModified = true);
});

document.getElementById('cancelBtn').addEventListener('click', function(e) {
    if (formModified) {
        e.preventDefault();
        Swal.fire({
            title: 'Modifications non sauvegardées',
            text: 'Vous avez des modifications non enregistrées. Voulez-vous vraiment quitter ?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, quitter',
            cancelButtonText: 'Rester'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'liste.php';
            }
        });
    }
});

// Initialisation : désactiver la liste des villes si pas de province sélectionnée
document.addEventListener('DOMContentLoaded', function() {
    // Déjà géré dans le chargement initial
});
</script>

<?php include '../../includes/footer.php'; ?>