<?php
require_once '../../includes/functions.php';
require_login();

unset(
    $_SESSION['success_message'],
    $_SESSION['success_type']
);

$user_profil = $_SESSION['user_profil'] ?? '';
$csrf_token = generate_csrf_token();

$page_titre = 'Membres de l\'équipe';
$breadcrumb = ['Membres' => '#'];
include '../../includes/header.php';

if (!function_exists('local_equipe_role_order_sql')) {
    function local_equipe_role_order_sql(string $column = 'role'): string
    {
        return "CASE LOWER(TRIM($column))
            WHEN 'chef d''équipe' THEN 1
            WHEN 'chef d''equipe' THEN 1
            WHEN 'inspecteur' THEN 2
            WHEN 'opérateur pc' THEN 3
            WHEN 'operateur pc' THEN 3
            WHEN 'opérateur' THEN 3
            WHEN 'operateur' THEN 3
            WHEN 'contrôleur' THEN 4
            WHEN 'controleur' THEN 4
            WHEN 'contôleur' THEN 4
            WHEN 'superviseur' THEN 5
            ELSE 99 END";
    }
}

if (!function_exists('local_equipe_role_order_value')) {
    function local_equipe_role_order_value(?string $role): int {
        $normalized = strtolower(trim((string) $role));
        return match ($normalized) {
            "chef d'équipe", "chef d'equipe" => 1,
            'inspecteur' => 2,
            'opérateur pc', 'operateur pc', 'opérateur', 'operateur' => 3,
            'contrôleur', 'controleur', 'contôleur' => 4,
            'superviseur' => 5,
            default => 99,
        };
    }
}

if (!function_exists('local_equipe_role_display_label')) {
    function local_equipe_role_display_label(?string $role): string
    {
        $normalized = strtolower(trim((string) $role));
        return match ($normalized) {
            "chef d'équipe", "chef d'equipe" => "Chef d'équipe",
            'inspecteur' => 'Inspecteur',
            'opérateur pc', 'operateur pc', 'opérateur', 'operateur' => 'Opérateur PC',
            'contrôleur', 'controleur', 'contôleur' => 'Contrôleur',
            'superviseur' => 'Superviseur',
            default => trim((string) $role) !== '' ? trim((string) $role) : 'Non défini',
        };
    }
}

// Fonction pour mettre en majuscules UNIQUEMENT les données du tableau
if (!function_exists('format_upper_table')) {
    function format_upper_table($value, $default = 'NON RENSEIGNÉ') {
        $value = trim((string) $value);
        return empty($value) ? $default : strtoupper($value);
    }
}

// Récupération des équipes avec ordre personnalisé des rôles
try {
    $stmt = $pdo->query("
        SELECT e.*, 
               COALESCE(NULLIF(TRIM(e.noms), ''), NULLIF(TRIM(m.noms), ''), '') AS nom_affiche,
               COALESCE(NULLIF(TRIM(e.grade), ''), NULLIF(TRIM(m.grade), ''), '') AS grade_affiche
        FROM equipes e
        LEFT JOIN militaires m ON e.matricule = m.matricule
        ORDER BY 
            " . local_equipe_role_order_sql('e.role') . ",
            grade_affiche ASC, 
            nom_affiche ASC
    ");
    $equipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $equipes = [];
    error_log("Erreur récupération équipes liste: " . $e->getMessage());
}

$total_equipes = count($equipes);

// Liste des rôles disponibles dans l'ordre d'affichage
$roles_equipe = ["Chef d'équipe", 'Inspecteur', 'Opérateur PC', 'Contrôleur', 'Superviseur'];

$role_counts = array_fill_keys($roles_equipe, 0);
foreach ($equipes as $equipeStat) {
    $roleLabel = local_equipe_role_display_label($equipeStat['role'] ?? '');
    if (isset($role_counts[$roleLabel])) {
        $role_counts[$roleLabel]++;
    }
}
unset($equipeStat);

// Récupérer la liste des unités pour le filtre
$stmt_unites = $pdo->query("SELECT DISTINCT unites FROM equipes WHERE unites IS NOT NULL AND unites != '' ORDER BY unites");
$unites_list = $stmt_unites->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- ========== STYLES SPÉCIFIQUES ========== -->
<style>
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

/* Filtres - Version modernisée (comme le fichier du haut) */
.filters-row {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: flex-end;
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
}

.filter-item {
    flex: 1;
    min-width: 150px;
}

.filter-item .form-label {
    font-weight: 600;
    font-size: 0.85rem;
    color: #2e7d32;
    margin-bottom: 5px;
    display: block;
}

.filter-item .form-label i {
    margin-right: 4px;
}

.filter-item .form-select,
.filter-item .form-control {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 8px 12px;
    font-size: 0.9rem;
    width: 100%;
}

.btn-reset-modern {
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    background: #6c757d;
    color: white;
    font-weight: 500;
}

.btn-reset-modern:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
}

.table-equipes {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
    min-width: 800px;
}

.table-equipes thead th {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
    font-weight: 700;
    font-size: 0.85rem;
    padding: 10px 12px;
    border: none;
    text-align: left;
    vertical-align: middle;
}

.table-equipes thead th:first-child {
    border-radius: 10px 0 0 10px;
}

.table-equipes thead th:last-child {
    border-radius: 0 10px 10px 0;
}

.dataTables_wrapper table.dataTable thead tr:not(:first-child) {
    visibility: collapse !important;
    height: 0 !important;
}

.dataTables_wrapper table.dataTable thead tr:not(:first-child) th,
.dataTables_wrapper table.dataTable thead tr:not(:first-child) td {
    padding: 0 !important;
    height: 0 !important;
    line-height: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

.dataTables_wrapper table.dataTable thead .dataTables_sizing {
    height: 0 !important;
    min-height: 0 !important;
    overflow: hidden !important;
    padding: 0 !important;
    margin: 0 !important;
}

.table-equipes tbody tr {
    background: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border-radius: 10px;
    transition: all 0.3s;
}

.table-equipes tbody tr:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(46, 125, 50, 0.15);
}

.table-equipes tbody td {
    padding: 10px 12px;
    border: none;
    font-size: 0.85rem;
    vertical-align: middle;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.table-equipes tbody td:first-child {
    border-radius: 10px 0 0 10px;
}

.table-equipes tbody td:last-child {
    border-radius: 0 10px 10px 10px;
}

/* Style pour les données en majuscules DANS LE TABLEAU UNIQUEMENT */
.table-equipes tbody td {
    text-transform: uppercase;
}

.matricule-with-eye {
    display: flex;
    align-items: center;
    gap: 8px;
}

.matricule-with-eye i {
    color: #2e7d32;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
}

.matricule-with-eye i:hover {
    color: #ffc107;
    transform: scale(1.1);
}

.dataTables_wrapper {
    width: 100%;
    padding: 0;
    position: relative;
    clear: both;
}

.dataTables_wrapper .dataTables_length {
    float: left;
    margin-bottom: 20px;
}

.dataTables_wrapper .dataTables_length select {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 6px 12px;
    font-size: 0.9rem;
}

.dataTables_wrapper .dataTables_filter {
    float: right;
    margin-bottom: 20px;
}

.dataTables_wrapper .dataTables_filter label {
    font-weight: 500;
    color: #2e7d32;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
}

.dataTables_wrapper .dataTables_filter input {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 6px 12px;
    width: 250px;
    font-size: 0.9rem;
}

.dataTables_wrapper .dataTables_info {
    float: left;
    margin-top: 20px;
    font-size: 0.9rem;
    color: #2e7d32;
}

.dataTables_wrapper .dataTables_paginate {
    float: right;
    margin-top: 20px;
    font-size: 0.9rem;
}

.dataTables_scrollBody {
    overflow: visible !important;
    border-radius: 10px;
}

/* Styles des cartes statistiques - Version modernisée (comme le fichier du haut) */
.stats-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.stat-card:hover {
    /* Suppression de l'animation de survol : cartes fixes */
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.8rem;
    box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
}

/* Couleurs spécifiques pour chaque type de carte */
.stat-icon.total-card {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
}

.stat-icon.role-chef-card {
    background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
}

.stat-icon.role-inspecteur-card {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
}

.stat-icon.role-operateur-card {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
}

.stat-icon.role-controleur-card {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

.stat-icon.role-superviseur-card {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
}

.stat-info {
    flex: 1;
}

.stat-info h4 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #2e7d32;
}

.stat-info p {
    margin: 0;
    font-size: 0.9rem;
    color: #6c757d;
}

/* Couleurs des valeurs */
.stat-info h4.total-value {
    color: #2e7d32;
}

.stat-info h4.role-chef-value {
    color: #f57c00;
}

.stat-info h4.role-inspecteur-value {
    color: #138496;
}

.stat-info h4.role-operateur-value {
    color: #0a58ca;
}

.stat-info h4.role-controleur-value {
    color: #c82333;
}

.stat-info h4.role-superviseur-value {
    color: #5a6268;
}

.filtre-tags {
    margin-top: 10px;
}

.filtre-tag {
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.8rem;
    display: inline-block;
    margin-right: 5px;
}

@media (max-width: 992px) {
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .stat-card {
        padding: 15px 20px;
        gap: 15px;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }

    .stat-info h4 {
        font-size: 1.5rem;
    }

    .dataTables_wrapper .dataTables_filter {
        width: 100%;
    }

    .dataTables_wrapper .dataTables_filter label {
        width: 100%;
    }

    .dataTables_wrapper .dataTables_filter input {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .modern-card .card-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .filters-row {
        flex-direction: column;
    }

    .stat-card {
        padding: 12px 18px;
    }

    .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
    }

    .stat-info h4 {
        font-size: 1.3rem;
    }

    .stat-info p {
        font-size: 0.8rem;
    }

    .filter-item .form-label {
        font-size: 0.8rem;
    }

    .filter-item .form-select,
    .filter-item .form-control {
        padding: 6px 10px;
        font-size: 0.85rem;
    }

    .btn-reset-modern {
        padding: 6px 12px;
        font-size: 0.85rem;
    }
}
</style>

<div class="container-fluid py-3">

    <!-- Statistiques - Version modernisée (3 colonnes) -->
    <div class="stats-container">
        <!-- Total membres -->
        <div class="stat-card">
            <div class="stat-icon total-card"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h4 class="total-value"><?= number_format($total_equipes, 0, ',', ' ') ?></h4>
                <p>Total membres</p>
            </div>
        </div>

        <!-- Chef d'équipe -->
        <div class="stat-card">
            <div class="stat-icon role-chef-card"><i class="fas fa-user-tie"></i></div>
            <div class="stat-info">
                <h4 class="role-chef-value"><?= number_format($role_counts["Chef d'équipe"] ?? 0, 0, ',', ' ') ?></h4>
                <p>Chef d'équipe</p>
            </div>
        </div>

        <!-- Inspecteur -->
        <div class="stat-card">
            <div class="stat-icon role-inspecteur-card"><i class="fas fa-user-secret"></i></div>
            <div class="stat-info">
                <h4 class="role-inspecteur-value"><?= number_format($role_counts['Inspecteur'] ?? 0, 0, ',', ' ') ?>
                </h4>
                <p>Inspecteur</p>
            </div>
        </div>

        <!-- Opérateur PC -->
        <div class="stat-card">
            <div class="stat-icon role-operateur-card"><i class="fas fa-desktop"></i></div>
            <div class="stat-info">
                <h4 class="role-operateur-value"><?= number_format($role_counts['Opérateur PC'] ?? 0, 0, ',', ' ') ?>
                </h4>
                <p>Opérateur PC</p>
            </div>
        </div>

        <!-- Contrôleur -->
        <div class="stat-card">
            <div class="stat-icon role-controleur-card"><i class="fas fa-clipboard-check"></i></div>
            <div class="stat-info">
                <h4 class="role-controleur-value"><?= number_format($role_counts['Contrôleur'] ?? 0, 0, ',', ' ') ?>
                </h4>
                <p>Contrôleur</p>
            </div>
        </div>

        <!-- Superviseur -->
        <div class="stat-card">
            <div class="stat-icon role-superviseur-card"><i class="fas fa-user-shield"></i></div>
            <div class="stat-info">
                <h4 class="role-superviseur-value"><?= number_format($role_counts['Superviseur'] ?? 0, 0, ',', ' ') ?>
                </h4>
                <p>Superviseur</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card modern-card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-users"></i> Membres de l'équipe</h3>
                    <span class="total-badge"><i class="fas fa-database"></i> Total : <?= count($equipes) ?></span>
                </div>
                <div class="card-body">
                    <!-- Filtres - Version modernisée -->
                    <div class="filters-row">
                        <div class="filter-item">
                            <label class="form-label"><i class="fas fa-user-tag"></i> Rôle</label>
                            <select id="role-filter" class="form-select">
                                <option value="">Tous</option>
                                <?php foreach ($roles_equipe as $role): ?>
                                <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label"><i class="fas fa-building"></i> Unités</label>
                            <select id="unites-filter" class="form-select">
                                <option value="">Toutes</option>
                                <?php foreach ($unites_list as $unite): ?>
                                <option value="<?= htmlspecialchars($unite) ?>"><?= htmlspecialchars($unite) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label"><i class="fas fa-fist-raised"></i> Grade</label>
                            <select id="grade-filter" class="form-select">
                                <option value="">Tous</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" id="reset-filters" class="btn-reset-modern w-100"><i
                                    class="fas fa-undo-alt"></i> Réinitialiser</button>
                        </div>
                    </div>

                    <!-- Tags des filtres actifs -->
                    <div class="filtre-tags mb-3" style="display: none;"></div>

                    <!-- Tableau -->
                    <table id="table-equipes" class="table-equipes" style="width:100%">
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Noms</th>
                                <th>Grade</th>
                                <th>Unités</th>
                                <th>Rôle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipes as $e): ?>
                            <tr data-role="<?= htmlspecialchars(local_equipe_role_display_label($e['role'] ?? '')) ?>"
                                data-role-order="<?= local_equipe_role_order_value($e['role'] ?? '') ?>"
                                data-unites="<?= htmlspecialchars($e['unites'] ?? '') ?>"
                                data-grade="<?= htmlspecialchars($e['grade_affiche'] ?? '') ?>">
                                <td>
                                    <div class="matricule-with-eye">
                                        <i class="fas fa-eye"
                                            onclick="window.location.href='voir.php?id=<?= urlencode($e['id']) ?>'"></i>
                                        <strong><?= htmlspecialchars(format_upper_table($e['matricule'] ?? '')) ?></strong>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars(format_upper_table($e['nom_affiche'] ?? '')) ?></td>
                                <td><?= htmlspecialchars(format_upper_table($e['grade_affiche'] ?? '')) ?></td>
                                <td><?= htmlspecialchars(format_upper_table($e['unites'] ?? '')) ?></td>
                                <td><?= htmlspecialchars(local_equipe_role_display_label($e['role'] ?? '')) ?></td>
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

<script>
$(document).ready(function() {
    // Récupérer les grades uniques pour le filtre
    const grades = [...new Set($('#table-equipes tbody tr').map(function() {
        return $(this).data('grade');
    }).get())].filter(g => g).sort();

    grades.forEach(grade => {
        $('#grade-filter').append(`<option value="${grade}">${grade}</option>`);
    });

    let searchTerm = '';

    function highlightSearchTerm(data, term) {
        if (!term || !data) return data;
        const regex = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        if (typeof data === 'string') {
            if (data.indexOf('<') !== -1 && data.indexOf('>') !== -1) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data;
                const textContent = tempDiv.textContent || tempDiv.innerText;
                if (textContent.match(regex)) {
                    return data.replace(new RegExp(textContent.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'),
                        textContent.replace(regex, '<mark>$1</mark>'));
                }
                return data;
            }
            return data.replace(regex, '<mark>$1</mark>');
        }
        return data;
    }

    function cleanupDuplicateHeaderRows() {
        const $wrapper = $('#table-equipes').closest('.dataTables_wrapper');
        $wrapper.find('table.dataTable thead tr').each(function(index) {
            if (index > 0) {
                $(this).css({
                    display: 'none',
                    height: 0,
                    visibility: 'collapse'
                });
            }
        });

        $wrapper.find('thead .dataTables_sizing').each(function() {
            $(this).css({
                height: 0,
                minHeight: 0,
                overflow: 'hidden',
                padding: 0,
                margin: 0
            });
        });
    }

    const table = $('#table-equipes').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json',
            search: '<i class="fas fa-search"></i>',
            lengthMenu: "Afficher _MENU_ éléments",
            info: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
            infoEmpty: "Affichage de 0 à 0 sur 0 élément",
            infoFiltered: "(filtré sur _MAX_ éléments au total)",
            zeroRecords: "Aucun enregistrement correspondant",
            paginate: {
                first: "Premier",
                previous: "Précédent",
                next: "Suivant",
                last: "Dernier"
            }
        },
        dom: 'rt<"datatable-bottom d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3"ip>',
        order: [
            [4, 'asc']
        ],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        autoWidth: true,
        orderCellsTop: true,
        paging: true,
        columnDefs: [{
            targets: 4,
            orderData: [4],
            type: 'html'
        }],
        createdRow: function(row, data, dataIndex) {
            if (searchTerm) {
                $(row).find('td').each(function() {
                    const $td = $(this);
                    $td.html(highlightSearchTerm($td.html(), searchTerm));
                });
            }
        },
        initComplete: function() {
            $('.datatable-bottom').css({
                'row-gap': '10px'
            });

            cleanupDuplicateHeaderRows();
        }
    });

    table.on('draw.dt column-sizing.dt responsive-resize.dt', function() {
        cleanupDuplicateHeaderRows();
    });

    cleanupDuplicateHeaderRows();

    $('.dataTables_filter input').on('keyup search input', function() {
        searchTerm = $(this).val();
        setTimeout(() => {
            if (searchTerm) {
                $('#table-equipes tbody tr').each(function() {
                    $(this).find('td').each(function() {
                        const $td = $(this);
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

    function updateFilterTags() {
        const tags = [];
        if ($('#role-filter').val()) tags.push(`Rôle : ${$('#role-filter').val()}`);
        if ($('#unites-filter').val()) tags.push(
            `Unités : ${$('#unites-filter').find('option:selected').text()}`);
        if ($('#grade-filter').val()) tags.push(`Grade : ${$('#grade-filter').find('option:selected').text()}`);

        const $tagsContainer = $('.filtre-tags');
        $tagsContainer.empty();
        tags.forEach(tag => $tagsContainer.append(`<span class="filtre-tag me-2">${tag}</span>`));
        $tagsContainer.toggle(tags.length > 0);
    }

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'table-equipes') return true;

        const row = table.row(dataIndex);
        const rowNode = row.node();
        const $row = $(rowNode);

        const role = $row.data('role') || '';
        const unites = $row.data('unites') || '';
        const grade = $row.data('grade') || '';

        const roleFilter = $('#role-filter').val();
        const unitesFilter = $('#unites-filter').val();
        const gradeFilter = $('#grade-filter').val();

        if (roleFilter && role !== roleFilter) return false;
        if (unitesFilter && unites !== unitesFilter) return false;
        if (gradeFilter && grade !== gradeFilter) return false;

        return true;
    });

    $('#role-filter, #unites-filter, #grade-filter').on('change keyup', function() {
        table.draw();
        updateFilterTags();
    });

    $('#reset-filters').on('click', function() {
        $('#role-filter, #unites-filter, #grade-filter').val('');
        table.draw();
        updateFilterTags();
    });
});
</script>