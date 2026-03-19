<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG']);

$page_titre = 'Gestion des utilisateurs';

// Récupération du message de succès depuis la session
$success_message = null;
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Supprime après lecture
}

$breadcrumb = ['Administration' => '#', 'Utilisateurs' => '#'];
include '../../includes/header.php';

$utilisateurs = $pdo->query("SELECT id_utilisateur, login, nom_complet, email, profil, actif, dernier_acces FROM utilisateurs ORDER BY nom_complet")->fetchAll();
?>

<!-- Styles supplémentaires -->
<link rel="stylesheet" href="/ctr.net-fardc/assets/css/fonts.css">

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
}

/* Import unified table styles */
@import url('../../assets/css/tables-unified.css');

.card-modern {
    border: none;
    border-radius: 15px;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
    overflow: hidden;
}

.card-modern .card-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    padding: 15px 25px;
    border: none;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.card-modern .card-header h3 {
    color: white;
    margin: 0;
    font-weight: 600;
    font-size: 1.3rem;
}

.card-modern .card-header h3 i {
    margin-right: 8px;
}

.card-modern .card-body {
    padding: 25px;
}

.btn-export {
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    color: white;
}

.btn-export.csv {
    background: #28a745;
}

.btn-export.csv:hover {
    background: #218838;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
}

.btn-export.excel {
    background: #1e7e34;
}

.btn-export.excel:hover {
    background: #19692c;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(30, 126, 52, 0.3);
}

.btn-export.pdf {
    background: #dc3545;
}

.btn-export.pdf:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
}

.filter-item .btn-reset {
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.filter-item .btn-reset:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
}

/* Style des boutons d'action dans l'en-tête */
.card-tools {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* ===== STYLES EXTRAITS POUR LES BOUTONS ===== */
.btn-modern {
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-weight: 500;
    text-decoration: none;
    white-space: nowrap;
}

.btn-modern i {
    font-size: 0.9rem;
}

.btn-modern:hover {
    transform: translateY(-2px);
    text-decoration: none;
}

/* Bouton Nouvel utilisateur (Jaune) */
.btn-primary-modern {
    background: #ffc107;
    color: #333;
    box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
}

.btn-primary-modern:hover {
    background: #ffb300;
    color: #333;
    box-shadow: 0 6px 15px rgba(255, 193, 7, 0.4);
}

/* Bouton Importer (Rouge) */
.btn-secondary-modern {
    background: #dc3545;
    color: white;
    box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
}

.btn-secondary-modern:hover {
    background: #c82333;
    color: white;
    box-shadow: 0 6px 15px rgba(220, 53, 69, 0.4);
}

.action-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-shrink: 0;
}

/* ===== FIN DES STYLES EXTRAITS ===== */

.btn-mention {
    border-radius: 20px;
    padding: 5px 15px;
    font-weight: 600;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    margin: 3px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
    border-radius: 20px;
    padding: 5px 15px;
    background: transparent;
    border: 1px solid white;
    color: white;
    transition: all 0.3s;
}

.btn-new-search:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
}

/* Style des badges pour le profil utilisateur */
.badge-profil {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-block;
}

.badge-profil-admin {
    background: linear-gradient(135deg, var(--danger) 0%, #c82333 100%);
}

.badge-profil-user {
    background: linear-gradient(135deg, var(--success) 0%, #218838 100%);
}

.badge-statut {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
}

.badge-statut-actif {
    background: var(--success);
}

.badge-statut-inactif {
    background: var(--gray);
}

.badge-actif {
    background: var(--success);
}

.badge-decede {
    background: var(--danger);
}

.badge-decede-av-bio {
    background: var(--danger);
    background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.1) 50%);
    background-size: 10px 10px;
}

.badge-dcd-ap-bio {
    background: linear-gradient(135deg, #6f42c1, #6610f2);
}

.badge-retraite {
    background: linear-gradient(135deg, #fd7e14, #dc3545);
}

.badge-integre {
    background: linear-gradient(135deg, #20c997, #0dcaf0);
}

/* Style du tableau */
.table-modern {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
    margin-top: 10px;
}

.table-modern thead th {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 12px 15px;
    border: none;
    text-align: left;
}

.table-modern thead th:first-child {
    border-radius: 10px 0 0 10px;
}

.table-modern thead th:last-child {
    border-radius: 0 10px 10px 0;
}

.table-modern tbody tr {
    background: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border-radius: 10px;
    transition: all 0.3s;
}

.table-modern tbody tr:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(46, 125, 50, 0.15);
}

.table-modern tbody td {
    padding: 15px;
    border: none;
    font-size: 0.9rem;
    vertical-align: middle;
}

.table-modern tbody td:first-child {
    border-radius: 10px 0 0 10px;
}

.table-modern tbody td:last-child {
    border-radius: 0 10px 10px 0;
}

.militaire-info {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
}

.militaire-info .info-label {
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
}

.militaire-info .separator {
    width: 1px;
    height: 24px;
    background: rgba(255, 255, 255, 0.3);
}

.statut-temp {
    background: var(--light);
    border-radius: 10px;
    padding: 15px;
    margin: 15px 0;
    border-left: 4px solid var(--primary);
}

.statut-temp .form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.lien-groupe {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px;
    background: var(--light);
}

.lien-groupe label {
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 10px;
    display: block;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 5px;
}

.lien-groupe small {
    color: var(--gray);
    font-size: 0.75rem;
}

.form-control-modern {
    border-radius: 8px;
    border: 1px solid #ced4da;
    padding: 10px 15px;
    width: 100%;
    transition: all 0.3s;
}

.form-control-modern:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
    outline: none;
}

.beneficiaire-section {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border: 1px solid #e0e0e0;
}

.observations-group {
    background: var(--light);
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #e0e0e0;
}

.actions-container {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px dashed #dee2e6;
}

.info-transfo {
    background: var(--light);
    border-radius: 10px;
    padding: 15px;
    border-left: 4px solid var(--info);
    margin-top: 20px;
    animation: fadeIn 0.3s ease;
}

.info-transfo-item {
    background: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.info-transfo-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.info-transfo-item i {
    color: var(--info);
    font-size: 1.2rem;
    margin-right: 8px;
}

.info-transfo-item strong {
    color: var(--primary-dark);
    display: block;
    margin-bottom: 5px;
}

.info-transfo-item span {
    color: var(--gray);
    font-size: 0.9rem;
}

/* Style des boutons d'action dans le tableau */
.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

/* Boutons d'action réduits */
.btn-edit,
.btn-delete {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    font-size: 0.8rem;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.btn-edit {
    background: linear-gradient(135deg, var(--warning) 0%, #ff9800 100%);
    color: white;
}

.btn-delete {
    background: linear-gradient(135deg, var(--danger) 0%, #c82333 100%);
    color: white;
}

.btn-edit:hover,
.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

/* Style pour les dates */
.date-value {
    font-weight: 500;
    color: var(--primary);
    font-size: 0.85rem;
}

.text-muted-date {
    color: #adb5bd;
    font-style: italic;
    font-size: 0.85rem;
}

/* Badge pour le nombre total */
.total-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    backdrop-filter: blur(5px);
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }

    100% {
        transform: rotate(360deg);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Personnalisation de DataTables */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin-bottom: 20px;
}

.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 8px 12px;
    min-height: 38px;
    font-size: 0.9rem;
}

.dataTables_wrapper .dataTables_length select:focus,
.dataTables_wrapper .dataTables_filter input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
    outline: none;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 8px;
    padding: 8px 15px;
    margin: 0 3px;
    border: 1px solid #e0e0e0;
    background: white;
    color: var(--primary) !important;
    transition: all 0.3s;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white !important;
    border-color: transparent;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white !important;
    border-color: transparent;
}

.dataTables_wrapper .dataTables_info {
    color: var(--gray);
    font-size: 0.9rem;
    padding-top: 15px;
}

/* Style pour le surlignage des résultats de recherche */
mark {
    background: linear-gradient(120deg, #ffeb3b 0%, #ffd700 100%) !important;
    padding: 2px 4px !important;
    border-radius: 3px !important;
    font-weight: 500 !important;
    color: #333 !important;
    box-shadow: 0 2px 5px rgba(255, 193, 7, 0.3) !important;
    transition: all 0.2s ease;
}

/* Animation subtile au survol */
mark:hover {
    background: linear-gradient(120deg, #ffd700 0%, #ffc107 100%) !important;
    box-shadow: 0 4px 8px rgba(255, 193, 7, 0.4) !important;
}

/* Pour les badges et éléments stylisés */
.badge-profil mark,
.badge-statut mark {
    background: rgba(255, 255, 255, 0.3) !important;
    color: white !important;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2) !important;
}

/* Pour le texte dans les cellules */
.table-modern tbody td mark {
    background: #ffeb3b !important;
    color: #333 !important;
}

/* Style pour les liens email */
a[href^="mailto:"] {
    color: var(--primary) !important;
    text-decoration: none;
    transition: all 0.3s;
}

a[href^="mailto:"]:hover {
    color: var(--primary-dark) !important;
    text-decoration: underline;
}

/* Media queries pour mobile */
@media (max-width: 768px) {
    .card-modern .card-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .card-tools {
        width: 100%;
        justify-content: flex-start;
    }

    .table-modern thead {
        display: none;
    }

    .table-modern tbody tr {
        display: block;
        margin-bottom: 15px;
    }

    .table-modern tbody td {
        display: block;
        text-align: right;
        padding: 10px 15px;
        position: relative;
    }

    .table-modern tbody td:before {
        content: attr(data-label);
        float: left;
        font-weight: 600;
        color: var(--primary);
    }

    .table-modern tbody td:first-child {
        border-radius: 10px 10px 0 0;
    }

    .table-modern tbody td:last-child {
        border-radius: 0 0 10px 10px;
    }

    .action-buttons {
        justify-content: flex-end;
    }

    /* Ajustement de la taille des boutons sur mobile */
    .btn-edit,
    .btn-delete {
        width: 20px;
        height: 20px;
        font-size: 0.7rem;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    .table-modern {
        font-size: 0.85rem;
    }

    .table-modern tbody td {
        padding: 12px 10px;
    }
}
</style>

<div class="container-fluid py-3">
    <div class="row">
        <div class="col-12">
            <div class="card-modern">
                <div class="card-header">
                    <h3 class="mb-0">
                        <i class="fas fa-users"></i> Gestion des utilisateurs
                    </h3>
                    <div class="d-flex align-items-center gap-3">
                        <span class="total-badge">
                            <i class="fas fa-database"></i> Total : <?= count($utilisateurs) ?>
                        </span>
                        <div class="card-tools">
                            <a href="ajouter_utilisateur.php" class="btn-modern btn-primary-modern">
                                <i class="fas fa-plus-circle"></i> Nouvel utilisateur
                            </a>
                            <!-- Bouton Importer (visible uniquement pour ADMIN_IG, mais la page l'est déjà) -->
                            <a href="importer.php" class="btn-modern btn-secondary-modern">
                                <i class="fas fa-file-import"></i> Importer
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table id="table-utilisateurs" class="table-modern">
                        <thead>
                            <tr>
                                <!-- Colonne ID supprimée -->
                                <th>Login</th>
                                <th>Nom complet</th>
                                <th>Email</th>
                                <th>Profil</th>
                                <th>Statut</th>
                                <th>Dernier accès</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utilisateurs as $u): ?>
                            <tr>
                                <!-- Cellule ID supprimée -->
                                <td data-label="Login"><strong><?= h($u['login']) ?></strong></td>
                                <td data-label="Nom complet"><?= h($u['nom_complet']) ?></td>
                                <td data-label="Email">
                                    <a href="mailto:<?= h($u['email']) ?>">
                                        <?= h($u['email']) ?>
                                    </a>
                                </td>
                                <td data-label="Profil">
                                    <?php
                                        $profil_class = '';
                                        if ($u['profil'] === 'ADMIN_IG') {
                                            $profil_class = 'badge-profil-admin';
                                        } elseif ($u['profil'] === 'OPERATEUR') {
                                            $profil_class = 'badge-profil-operateur';
                                        }
                                        ?>
                                    <span class="badge-profil <?= $profil_class ?>">
                                        <?= $u['profil'] ?>
                                    </span>
                                </td>
                                <td data-label="Statut">
                                    <span
                                        class="badge-statut <?= $u['actif'] ? 'badge-statut-actif' : 'badge-statut-inactif' ?>">
                                        <i class="fas <?= $u['actif'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                        <?= $u['actif'] ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </td>
                                <td data-label="Dernier accès">
                                    <?php if ($u['dernier_acces']): ?>
                                    <span class="date-value">
                                        <i class="far fa-calendar-alt"></i>
                                        <?= date('d/m/Y H:i', strtotime($u['dernier_acces'])) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted-date">
                                        <i class="far fa-clock"></i> Jamais connecté
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <a href="modifier_utilisateur.php?id=<?= $u['id_utilisateur'] ?>"
                                            class="btn btn-edit" title="Modifier cet utilisateur">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0)" class="btn btn-delete delete-btn"
                                            title="Supprimer cet utilisateur" data-id="<?= $u['id_utilisateur'] ?>"
                                            data-nom="<?= h($u['nom_complet']) ?>" data-login="<?= h($u['login']) ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Scripts supplémentaires (exports) -->
<script src="../../assets/js/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    // AFFICHAGE DU MESSAGE DE SUCCÈS
    <?php if ($success_message): ?>
    Swal.fire({
        icon: 'success',
        title: 'Succès',
        text: '<?= addslashes($success_message) ?>',
        timer: 2000,
        showConfirmButton: false,
        position: 'top-end',
        toast: true,
        background: '#28a745',
        color: '#ffffff',
        iconColor: '#ffffff',
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    <?php endif; ?>

    // Fonction pour surligner les termes de recherche
    function highlightSearchTerm(data, searchTerm) {
        if (!searchTerm || !data) return data;

        const searchRegex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');

        if (typeof data === 'string') {
            if (data.indexOf('<') !== -1 && data.indexOf('>') !== -1) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data;
                const textContent = tempDiv.textContent || tempDiv.innerText;

                if (textContent.match(searchRegex)) {
                    return data.replace(new RegExp(textContent.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'),
                        textContent.replace(searchRegex, '<mark>$1</mark>'));
                }
                return data;
            } else {
                return data.replace(searchRegex, '<mark>$1</mark>');
            }
        }
        return data;
    }

    let searchTerm = '';

    // Initialisation de DataTables
    const table = $('#table-utilisateurs').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json',
            search: "Rechercher :",
            lengthMenu: "Afficher _MENU_ éléments par page",
            info: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
            infoEmpty: "Aucun élément à afficher",
            infoFiltered: "(filtré de _MAX_ éléments au total)",
            zeroRecords: "Aucun élément correspondant trouvé",
            paginate: {
                first: "Premier",
                previous: "Précédent",
                next: "Suivant",
                last: "Dernier"
            }
        },
        order: [
            [1, 'asc']
        ], // Tri par nom complet (maintenant index 1)
        pageLength: 10,
        responsive: true,
        columnDefs: [{
            orderable: false,
            targets: 6 // La colonne Actions est maintenant la 7e colonne (index 6)
        }],

        // Callback après chaque rendu de ligne
        createdRow: function(row, data, dataIndex) {
            if (searchTerm) {
                $(row).find('td').each(function() {
                    const $td = $(this);
                    const originalHtml = $td.html();

                    // Ne pas surligner la colonne des actions (index 6)
                    if ($td.index() === 6) return;

                    // Appliquer le surlignage
                    $td.html(highlightSearchTerm(originalHtml, searchTerm));
                });
            }
        }
    });

    // Capturer le terme de recherche à chaque frappe
    $('.dataTables_filter input').on('keyup search input', function() {
        searchTerm = $(this).val();

        setTimeout(() => {
            if (searchTerm) {
                $('#table-utilisateurs tbody tr').each(function() {
                    $(this).find('td').each(function() {
                        const $td = $(this);
                        if ($td.index() === 6) return;

                        const originalHtml = $td.html();
                        if (!originalHtml.includes('<mark')) {
                            $td.html(highlightSearchTerm(originalHtml,
                                searchTerm));
                        }
                    });
                });
            } else {
                table.rows().invalidate().draw(false);
            }
        }, 100);
    });

    // Gestion de la suppression avec SweetAlert2
    $('.delete-btn').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        const id = btn.data('id');
        const nom = btn.data('nom');
        const login = btn.data('login');

        Swal.fire({
            title: 'Confirmation de suppression',
            html: `Êtes-vous sûr de vouloir supprimer l'utilisateur :<br><strong>${nom}</strong> (${login}) ?<br><br><span class="text-danger">Cette action est irréversible.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler',
            reverseButtons: true,
            focusCancel: true,
            customClass: {
                confirmButton: 'btn btn-danger mx-2',
                cancelButton: 'btn btn-secondary mx-2'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                // Afficher un chargement
                Swal.fire({
                    title: 'Suppression en cours...',
                    text: 'Veuillez patienter',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Rediriger vers la page de suppression
                window.location.href = `supprimer_utilisateur.php?id=${id}`;
            }
        });
    });

    // Afficher les messages SweetAlert stockés en session (pour les autres opérations)
    <?php if (isset($_SESSION['swal'])): ?>
    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        timer: <?= $_SESSION['swal']['timer'] ?? 3000 ?>,
        showConfirmButton: <?= isset($_SESSION['swal']['showConfirmButton']) ? $_SESSION['swal']['showConfirmButton'] : 'false' ?>,
        timerProgressBar: true
    });
    <?php unset($_SESSION['swal']); ?>
    <?php endif; ?>
});
</script>
