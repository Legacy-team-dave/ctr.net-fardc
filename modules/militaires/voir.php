<?php
require_once '../../includes/functions.php';
require_login();

$matricule = $_GET['matricule'] ?? '';
if (!$matricule) {
    redirect_with_flash('liste.php', 'danger', 'Matricule du militaire manquant.');
}

// Récupérer les détails du militaire
$stmt = $pdo->prepare("SELECT * FROM militaires WHERE matricule = ?");
$stmt->execute([$matricule]);
$militaire = $stmt->fetch();

if (!$militaire) {
    redirect_with_flash('liste.php', 'danger', 'Militaire introuvable.');
}

// Fonction pour convertir le statut tinyint en libellé (1=Actif, 0=Inactif)
function getStatutLibelle($statut)
{
    if ($statut == 1) {
        return ['libelle' => 'Actif', 'class' => 'actif'];
    } else {
        return ['libelle' => 'Inactif', 'class' => 'inactif'];
    }
}

$statutInfo = getStatutLibelle($militaire['statut'] ?? 0);

// --- AJOUT LOG : journalisation de la consultation du détail ---
audit_action('CONSULTATION', 'militaires', $matricule, 'Consultation du détail du militaire');
// --- FIN AJOUT LOG ---

$page_titre = 'Détail du militaire : ' . $militaire['noms'];
$breadcrumb = ['Militaires' => 'liste.php', 'Détail' => '#'];
include '../../includes/header.php';
?>

<!-- Inclusion des styles -->
<link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="../../assets/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/sweetalert2.min.css">
<link rel="stylesheet" href="assets/css/fonts.css">

<style>
body {
    font-family: 'Barlow', sans-serif;
    background-color: #f5f5f5;
}

.modern-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

.modern-card .card-header {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    padding: 15px 25px;
    border: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
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

/* Style des badges */
.badge-categorie {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.9rem;
    display: inline-block;
    margin-bottom: 10px;
}

.badge-statut {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.badge-statut.actif {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
}

.badge-statut.inactif {
    background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%);
    color: white;
}

/* Style des boutons d'action */
.card-tools {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
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
    text-decoration: none;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.btn-warning-modern {
    background: white;
    color: #ffc107;
    border: 1px solid #ffc107;
}

.btn-warning-modern:hover {
    background: #ffc107;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
}

.btn-danger-modern {
    background: white;
    color: #dc3545;
    border: 1px solid #dc3545;
}

.btn-danger-modern:hover {
    background: #dc3545;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
}

.btn-primary-modern {
    background: white;
    color: #2e7d32;
    border: 1px solid #2e7d32;
}

.btn-primary-modern:hover {
    background: #2e7d32;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.4);
}

/* Style du tableau d'informations */
.info-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
}

.info-table tr {
    background: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
    border-radius: 10px;
    transition: all 0.3s;
}

.info-table tr:hover {
    background: #f8f9fa;
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.1);
}

.info-table th {
    width: 35%;
    padding: 12px 15px;
    font-weight: 600;
    color: #2e7d32;
    border-radius: 10px 0 0 10px;
    font-size: 0.9rem;
}

.info-table td {
    width: 65%;
    padding: 12px 15px;
    border-radius: 0 10px 10px 0;
    font-size: 0.9rem;
}

.info-table td i {
    color: #2e7d32;
    margin-right: 5px;
}

/* Style pour les messages "aucune donnée" */
.empty-message {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 30px;
    text-align: center;
    color: #6c757d;
    margin: 20px 0;
}

.empty-message i {
    font-size: 2rem;
    color: #2e7d32;
    margin-bottom: 10px;
    display: block;
}

/* Style pour les valeurs particulières */
.grade-badge {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-block;
}

/* Media queries pour mobile */
@media (max-width: 768px) {
    .modern-card .card-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .card-tools {
        width: 100%;
        justify-content: flex-start;
    }

    .info-table th {
        width: 40%;
    }

    .info-table td {
        width: 60%;
    }
}

/* Styles pour les cartes d'information */
.info-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 15px;
    height: 100%;
    transition: transform 0.3s ease;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.2);
}

.info-card i {
    font-size: 1.5rem;
    color: #2e7d32;
    margin-bottom: 10px;
}

.info-card h6 {
    color: #495057;
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-card p {
    color: #212529;
    font-weight: 500;
    margin-bottom: 0;
    font-size: 1rem;
}

/* Style pour l'en-tête avec matricule */
.matricule-header {
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    color: white;
    margin-left: 15px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.matricule-header i {
    font-size: 0.85rem;
}

/* Style pour le résumé */
.summary-item {
    text-align: center;
    padding: 10px;
}

.summary-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #2e7d32;
    margin-bottom: 5px;
}

.summary-label {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Style pour les icônes */
.text-primary {
    color: #2e7d32 !important;
}

/* Style pour les bordures */
.border-primary {
    border-color: #2e7d32 !important;
}
</style>

<div class="container-fluid py-3">
    <div class="row">
        <!-- Colonne de gauche : Informations générales -->
        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <h3 class="mb-0">
                            <i class="fas fa-user-shield"></i>FICHE MILITAIRE
                        </h3>
                        <span class="matricule-header">
                            <i class="fas fa-id-card"></i> <?= h($militaire['matricule']) ?>
                        </span>
                    </div>
                    <div class="card-tools">
                        <a href="modifier.php?matricule=<?= urlencode($matricule) ?>"
                            class="btn btn-warning-modern btn-modern">
                            <i class="fas fa-edit"></i> MODIFIER
                        </a>
                        <a href="supprimer.php?matricule=<?= urlencode($matricule) ?>"
                            class="btn btn-danger-modern btn-modern delete-btn" data-matricule="<?= h($matricule) ?>"
                            data-noms="<?= h($militaire['noms']) ?>">
                            <i class="fas fa-trash"></i> SUPPRIMER
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge-categorie">
                            <i class="fas fa-tag"></i> <?= h($militaire['categorie'] ?? 'NON CATÉGORISÉ') ?>
                        </span>
                        <span class="badge-statut <?= $statutInfo['class'] ?>">
                            <i class="fas fa-circle"></i> <?= strtoupper($statutInfo['libelle']) ?>
                        </span>
                    </div>

                    <table class="info-table">
                        <tr>
                            <th><i class="fas fa-user"></i> NOMS COMPLETS</th>
                            <td><strong><?= h($militaire['noms']) ?></strong></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-star"></i> GRADE</th>
                            <td><span class="grade-badge"><?= h($militaire['grade'] ?? 'NON SPÉCIFIÉ') ?></span></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-building"></i> DÉPENDANCE</th>
                            <td><?= h($militaire['dependance'] ?? 'NON DÉFINIE') ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-sitemap"></i> UNITÉ</th>
                            <td><?= h($militaire['unite'] ?? 'NON DÉFINIE') ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-hand-holding-heart"></i> BÉNÉFICIAIRE</th>
                            <td>
                                <?php if (!empty($militaire['beneficiaire'])): ?>
                                <i class="fas fa-user-check text-success"></i> <?= h($militaire['beneficiaire']) ?>
                                <?php else: ?>
                                <span class="text-muted"><i class="fas fa-minus-circle"></i> AUCUN BÉNÉFICIAIRE</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-fort-awesome"></i> GARNISON</th>
                            <td><?= h($militaire['garnison'] ?? 'NON DÉFINIE') ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-map-marker-alt"></i> PROVINCE</th>
                            <td><?= h($militaire['province'] ?? 'NON DÉFINIE') ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Colonne de droite : Informations complémentaires -->
        <div class="col-md-6">
            <!-- Affectations et unités -->
            <div class="modern-card">
                <div class="card-header">
                    <h3 class="mb-0">
                        <i class="fas fa-sitemap"></i>AFFECTATIONS
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="info-card">
                                <i class="fas fa-building"></i>
                                <h6>DÉPENDANCE</h6>
                                <p><?= h($militaire['dependance'] ?? 'NON DÉFINIE') ?></p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-card">
                                <i class="fas fa-sitemap"></i>
                                <h6>UNITÉ</h6>
                                <p><?= h($militaire['unite'] ?? 'NON DÉFINIE') ?></p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-card">
                                <i class="fas fa-fort-awesome"></i>
                                <h6>GARNISON</h6>
                                <p><?= h($militaire['garnison'] ?? 'NON DÉFINIE') ?></p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-card">
                                <i class="fas fa-map-marker-alt"></i>
                                <h6>PROVINCE</h6>
                                <p><?= h($militaire['province'] ?? 'NON DÉFINIE') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations complémentaires -->
            <div class="modern-card">
                <div class="card-header">
                    <h3 class="mb-0">
                        <i class="fas fa-info-circle"></i>INFORMATIONS COMPLÉMENTAIRES
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="info-card">
                                <i class="fas fa-folder-open"></i>
                                <h6>CATÉGORIE</h6>
                                <p><?= h($militaire['categorie'] ?? 'NON SPÉCIFIÉE') ?></p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-card">
                                <i class="fas fa-clock"></i>
                                <h6>STATUT</h6>
                                <p>
                                    <span class="badge-statut <?= $statutInfo['class'] ?>">
                                        <?= strtoupper($statutInfo['libelle']) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <?php if (!empty($militaire['beneficiaire'])): ?>
                        <div class="col-md-12">
                            <div class="info-card">
                                <i class="fas fa-hand-holding-heart"></i>
                                <h6>BÉNÉFICIAIRE</h6>
                                <p><?= h($militaire['beneficiaire']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Résumé rapide -->
            <div class="modern-card">
                <div class="card-header">
                    <h3 class="mb-0">
                        <i class="fas fa-chart-pie"></i>RÉSUMÉ
                    </h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-around text-center">
                        <div class="summary-item">
                            <div class="summary-value"><?= h($militaire['grade'] ?? '-') ?></div>
                            <div class="summary-label">GRADE</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?= h($militaire['unite'] ?? '-') ?></div>
                            <div class="summary-label">UNITÉ</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value">
                                <span class="badge-statut <?= $statutInfo['class'] ?>"
                                    style="font-size: 1rem; padding: 4px 12px;">
                                    <?= strtoupper($statutInfo['libelle']) ?>
                                </span>
                            </div>
                            <div class="summary-label">STATUT</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts locaux -->
<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    // Suppression avec SweetAlert2
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const matricule = btn.data('matricule');
        const noms = btn.data('noms');

        Swal.fire({
            title: 'CONFIRMATION DE SUPPRESSION',
            html: `Êtes-vous sûr de vouloir supprimer le militaire : <strong>${noms}</strong> (${matricule}) ?<br><br><span class="text-danger">Cette action est irréversible.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'OUI, SUPPRIMER',
            cancelButtonText: 'ANNULER',
            reverseButtons: true,
            focusCancel: true,
            customClass: {
                confirmButton: 'btn btn-danger mx-2',
                cancelButton: 'btn btn-secondary mx-2'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Suppression en cours...',
                    text: 'Veuillez patienter',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
                window.location.href =
                    `supprimer.php?matricule=${encodeURIComponent(matricule)}`;
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>