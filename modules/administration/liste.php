<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG']);

// --- AJOUT LOG : journalisation des exports ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'log_export') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? '';
    $filtres = $_GET['filtres'] ?? '';
    if ($type) {
        $details = "Export $type" . ($filtres ? " avec filtres: $filtres" : "");
        audit_action('EXPORT', 'utilisateurs', null, $details);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
// --- FIN AJOUT LOG ---

$page_titre = 'Gestion des utilisateurs';

// Récupération du message de succès depuis la session
$success_message = null;
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$breadcrumb = ['Administration' => '#', 'Utilisateurs' => '#'];
include '../../includes/header.php';

$utilisateurs = $pdo->query("SELECT id_utilisateur, login, nom_complet, email, profil, actif, dernier_acces FROM utilisateurs ORDER BY profil")->fetchAll();

// Générer un timestamp pour les noms de fichiers
$timestamp = date('Y-m-d_H\hi');
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

    /* Boutons d'export */
    .export-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-export {
        border-radius: 8px;
        padding: 6px 12px;
        font-weight: 500;
        font-size: 0.8rem;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        color: white;
    }

    .btn-export i {
        font-size: 0.9rem;
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

    /* Boutons Nouvel utilisateur / Importer (repris de militaires) */
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

    /* Badges de profil */
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

    .badge-profil-operateur {
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

    /* Tableau */
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

    /* Boutons d'action (modifier/supprimer) */
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

    /* Dates */
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

    /* DataTables - alignement recherche/boutons */
    .dataTables_wrapper .dataTables_filter {
        float: right;
        margin-bottom: 20px;
        display: flex !important;
        align-items: center;
        gap: 10px;
        width: auto;
        max-width: 100%;
    }

    .dataTables_wrapper .dataTables_filter label {
        font-weight: 500;
        color: #2e7d32;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        gap: 5px;
        flex: 0 1 auto;
    }

    .dataTables_wrapper .dataTables_filter input {
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 6px 12px;
        width: 250px;
        min-width: 200px;
    }

    .dataTables_wrapper .dataTables_filter .action-buttons {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
        margin-left: 5px;
    }

    .dataTables_wrapper .dataTables_filter .action-buttons .btn-modern {
        padding: 6px 12px;
        font-size: 0.85rem;
    }

    /* Responsive */
    @media (max-width: 992px) {
        .dataTables_wrapper .dataTables_filter {
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
        }

        .dataTables_wrapper .dataTables_filter label {
            width: 100%;
        }

        .dataTables_wrapper .dataTables_filter input {
            width: 100%;
        }

        .dataTables_wrapper .dataTables_filter .action-buttons {
            width: 100%;
            justify-content: flex-start;
            margin-left: 0;
            margin-top: 5px;
        }
    }

    @media (max-width: 768px) {
        .dataTables_wrapper .dataTables_filter .action-buttons {
            flex-direction: column;
            width: 100%;
        }

        .btn-modern {
            width: 100%;
            justify-content: center;
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
                        <div class="export-buttons">
                            <button class="btn-export csv" id="export-csv"><i class="fas fa-file-csv"></i> CSV</button>
                            <button class="btn-export excel" id="export-excel"><i class="fas fa-file-excel"></i>
                                Excel</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table id="table-utilisateurs" class="table-modern">
                        <thead>
                            <tr>
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

<!-- Scripts supplémentaires -->
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
                search: '<i class="fas fa-search"></i>',
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
            ], // Tri par nom complet
            pageLength: 10,
            responsive: true,
            columnDefs: [{
                orderable: false,
                targets: 6 // Colonne Actions
            }],
            initComplete: function() {
                // Cibler le conteneur de recherche
                const filterDiv = $('.dataTables_filter');

                // Appliquer les styles flex pour aligner les éléments
                filterDiv.css({
                    'display': 'flex',
                    'align-items': 'center',
                    'justify-content': 'flex-end',
                    'gap': '10px',
                    'flex-wrap': 'nowrap'
                });

                // Style pour le label de recherche
                filterDiv.find('label').css({
                    'display': 'flex',
                    'align-items': 'center',
                    'margin-bottom': '0',
                    'flex': '0 1 auto'
                });

                // Ajouter les boutons après le champ de recherche
                filterDiv.append(`
                <div class="action-buttons">
                    <a href="ajouter_utilisateur.php" class="btn-modern btn-primary-modern">
                        <i class="fas fa-plus-circle"></i> Nouvel utilisateur
                    </a>
                    <a href="importer.php" class="btn-modern btn-secondary-modern">
                        <i class="fas fa-file-import"></i> Importer
                    </a>
                </div>
            `);
            },
            // Callback après chaque rendu de ligne pour le surlignage
            createdRow: function(row, data, dataIndex) {
                if (searchTerm) {
                    $(row).find('td').each(function() {
                        const $td = $(this);
                        const originalHtml = $td.html();
                        // Ne pas surligner la colonne des actions (index 6)
                        if ($td.index() === 6) return;
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
                    Swal.fire({
                        title: 'Suppression en cours...',
                        text: 'Veuillez patienter',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });
                    window.location.href = `supprimer_utilisateur.php?id=${id}`;
                }
            });
        });

        // ========== EXPORTS ==========
        function getExportData() {
            return new Promise((resolve, reject) => {
                const searchInput = $('.dataTables_filter input').val();

                $.ajax({
                    url: 'ajax_export_utilisateurs.php',
                    method: 'POST',
                    data: {
                        search: searchInput,
                        order_column: table.order()[0][0],
                        order_dir: table.order()[0][1]
                    },
                    dataType: 'json',
                    success: function(response) {
                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur export:', error);
                        reject(error);
                    }
                });
            });
        }

        function getTimestamp() {
            const d = new Date();
            return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}_${String(d.getHours()).padStart(2,'0')}h${String(d.getMinutes()).padStart(2,'0')}`;
        }

        function escapeCsvValue(value) {
            if (value === null || value === undefined) return '';
            value = String(value);
            if (value.includes(';') || value.includes('"') || value.includes('\n')) {
                return '"' + value.replace(/"/g, '""') + '"';
            }
            return value;
        }

        $('#export-csv').on('click', async function() {
            try {
                // Journalisation
                const filters = {
                    search: $('.dataTables_filter input').val()
                };
                $.get('?ajax=log_export', {
                    type: 'CSV',
                    filtres: JSON.stringify(filters)
                });

                Swal.fire({
                    title: 'Préparation de l\'export...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const {
                    headers,
                    data
                } = await getExportData();

                if (!data || data.length === 0) {
                    Swal.close();
                    Swal.fire('Aucune donnée', 'Aucun utilisateur à exporter.', 'info');
                    return;
                }

                // Formatage CSV
                const csvRows = [];
                csvRows.push(headers.map(h => escapeCsvValue(h)).join(';'));

                // Trier si besoin (ici on garde l'ordre de la table)
                data.forEach(row => {
                    const escapedRow = row.map(cell => escapeCsvValue(cell)).join(';');
                    csvRows.push(escapedRow);
                });

                const csvContent = csvRows.join('\n');

                // Ajouter BOM UTF-8
                const blob = new Blob(["\uFEFF" + csvContent], {
                    type: 'text/csv;charset=utf-8;'
                });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `utilisateurs_${getTimestamp()}.csv`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);

                Swal.close();
            } catch (error) {
                console.error('Erreur export CSV:', error);
                Swal.close();
                Swal.fire('Erreur', 'Une erreur est survenue lors de l\'export CSV', 'error');
            }
        });

        $('#export-excel').on('click', async function() {
            try {
                // Journalisation
                const filters = {
                    search: $('.dataTables_filter input').val()
                };
                $.get('?ajax=log_export', {
                    type: 'Excel',
                    filtres: JSON.stringify(filters)
                });

                Swal.fire({
                    title: 'Préparation de l\'export...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const {
                    headers,
                    data
                } = await getExportData();

                if (!data || data.length === 0) {
                    Swal.close();
                    Swal.fire('Aucune donnée', 'Aucun utilisateur à exporter.', 'info');
                    return;
                }

                const wb = XLSX.utils.book_new();
                const wsData = [headers, ...data];
                const ws = XLSX.utils.aoa_to_sheet(wsData);

                const colWidths = headers.map(h => ({
                    wch: Math.max(h.length, 15)
                }));
                ws['!cols'] = colWidths;

                XLSX.utils.book_append_sheet(wb, ws, 'Utilisateurs');

                const wbout = XLSX.write(wb, {
                    bookType: 'xlsx',
                    type: 'array'
                });

                const blob = new Blob([wbout], {
                    type: 'application/octet-stream'
                });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `utilisateurs_${getTimestamp()}.xlsx`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);

                Swal.close();
            } catch (error) {
                console.error('Erreur export Excel:', error);
                Swal.close();
                Swal.fire('Erreur', 'Une erreur est survenue lors de l\'export Excel', 'error');
            }
        });

        // Afficher les messages SweetAlert stockés en session
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

<?php
// --- AJOUT LOG : journalisation de la consultation de la page ---
audit_action('CONSULTATION', 'utilisateurs', null, 'Consultation de la liste des utilisateurs');
// --- FIN AJOUT LOG ---
?>