<?php
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT preferences FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $preferences = $stmt->fetchColumn();

    if ($preferences) {
        $_SESSION['filtres'] = json_decode($preferences, true);
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Erreur vérification préférences: " . $e->getMessage());
}

$error = null;

try {
    $garnisons = $pdo->query("SELECT garnison, COUNT(*) as total FROM militaires WHERE garnison IS NOT NULL AND garnison != '' GROUP BY garnison ORDER BY garnison")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $garnisons = [];
    error_log("Erreur récupération garnisons: " . $e->getMessage());
}

$traductions_categories = [
    'ACTIF' => 'Actif',
    'DCD_AP_BIO' => 'Décédé Après Bio',
    'INTEGRES' => 'Intégré',
    'RETRAITES' => 'Retraité',
    'DCD_AV_BIO' => 'Décédé Avant Bio'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_garnisons = $_POST['garnisons'] ?? [];
    $selected_categories = $_POST['categories'] ?? [];

    if (empty($selected_garnisons)) {
        $error = "Sélectionnez au moins une garnison.";
    } elseif (empty($selected_categories)) {
        $error = "Sélectionnez au moins une catégorie.";
    } else {
        $_SESSION['filtres'] = [
            'garnisons' => $selected_garnisons,
            'categories' => $selected_categories
        ];

        try {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET preferences = ? WHERE id_utilisateur = ?");
            $stmt->execute([json_encode($_SESSION['filtres']), $_SESSION['user_id']]);

            // --- REMPLACEMENT audit_action() PAR log_action() ---
            log_action('PREFERENCES', 'utilisateurs', $_SESSION['user_id'], 'Définition des filtres');
            // --- FIN REMPLACEMENT ---

            header('Location: equipes.php');
            exit;
        } catch (PDOException $e) {
            error_log("Erreur sauvegarde préférences: " . $e->getMessage());
            $error = "Erreur lors de la sauvegarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuration - FARDC</title>

    <link rel="stylesheet" href="assets/css/fonts.css">

    <!-- Font Awesome 7 (local) -->
    <script src="assets/fontawesome/js/all.min.js"></script>
    <!-- AdminLTE 3 (local) -->
    <link rel="stylesheet" href="assets/css/adminlte.min.css">

    <style>
        /* Police globale */
        body {
            font-family: 'Barlow', sans-serif;
            font-size: 13px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background:
                linear-gradient(135deg, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.6) 100%),
                url('assets/img/fardc2.png') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
        }

        .pref-card {
            width: 950px;
            max-width: 100%;
            max-height: 98vh;
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .pref-header {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            padding: 8px 12px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            flex-shrink: 0;
        }

        .pref-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .pref-body {
            padding: 12px;
            background: #f8f9fa;
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .info-line {
            background: white;
            border-left: 4px solid #2e7d32;
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #6c757d;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }

        .row {
            display: flex;
            gap: 12px;
            margin-bottom: 10px;
            flex: 1;
            min-height: 0;
        }

        .col {
            flex: 1;
            min-width: 0;
            display: flex;
        }

        .box {
            background: white;
            border-radius: 12px;
            padding: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e0e0e0;
            width: 100%;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .box h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1b5e20;
            margin: 0 0 6px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid #f8f9fa;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }

        .box h4 span {
            margin-left: auto;
            font-size: 0.75rem;
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 20px;
            color: #1b5e20;
            font-weight: 500;
        }

        /* Barre de recherche */
        .search-box {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 30px;
            padding: 4px 10px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .search-box i {
            color: #9e9e9e;
            font-size: 0.8rem;
        }

        .search-box input {
            border: none;
            background: transparent;
            width: 100%;
            font-size: 0.8rem;
            outline: none;
            color: #1e293b;
        }

        .grid {
            display: grid;
            /* 3 colonnes pour les garnisons et catégories */
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
            overflow-y: auto;
            padding-right: 2px;
            flex: 1;
            min-height: 0;
        }

        /* Personnalisation de la scrollbar */
        .grid::-webkit-scrollbar {
            width: 4px;
        }

        .grid::-webkit-scrollbar-track {
            background: #f8f9fa;
            border-radius: 10px;
        }

        .grid::-webkit-scrollbar-thumb {
            background: #2e7d32;
            border-radius: 10px;
        }

        .item {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 2px 4px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            /* Augmenté de 0.7rem à 0.8rem */
        }

        .item:hover {
            background: #e8f5e9;
            border-color: #2e7d32;
            transform: translateY(-1px);
        }

        .item input[type="checkbox"] {
            width: 12px;
            height: 12px;
            margin-right: 3px;
            accent-color: #2e7d32;
            cursor: pointer;
            flex-shrink: 0;
        }

        .item-name {
            flex: 1;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
            min-width: 0;
            /* permet la troncature */
        }

        /* Mise en gras des noms de garnisons */
        .garnison-name {
            font-weight: regular;
        }

        /* Mise en gras des noms de catégories */
        .categorie-name {
            font-weight: regular;
        }

        .item-count {
            background: white;
            color: #1b5e20;
            padding: 2px 5px;
            /* Augmenté de 1px 3px à 2px 5px */
            border-radius: 20px;
            font-size: 0.9rem;
            /* Augmenté de 0.6rem à 0.7rem */
            font-weight: 600;
            margin-left: 3px;
            border: 1px solid #2e7d32;
            flex-shrink: 0;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 6px;
            padding-top: 4px;
            border-top: 1px dashed #e0e0e0;
            flex-shrink: 0;
        }

        .counter {
            background: #e8f5e9;
            color: #1b5e20;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-group {
            display: flex;
            gap: 4px;
        }

        .btn-sm {
            padding: 3px 10px;
            border: none;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .btn-select {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: white;
        }

        .btn-select:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(46, 125, 50, 0.3);
        }

        .btn-deselect {
            background: #6c757d;
            color: white;
        }

        .btn-deselect:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        .summary {
            background: white;
            border-radius: 8px;
            padding: 6px 12px;
            margin: 8px 0;
            font-size: 0.8rem;
            border: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        .summary div {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .summary i {
            color: #2e7d32;
            font-size: 0.85rem;
        }

        .btn-submit {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            border: none;
            border-radius: 8px;
            padding: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            width: 100%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 125, 50, 0.4);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            background: #fee2e2;
            color: #991b1b;
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border-left: 4px solid #dc3545;
            flex-shrink: 0;
        }

        .loading {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #2e7d32;
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

        /* Adaptation mobile : une seule colonne sur très petit écran */
        @media (max-width: 700px) {
            .row {
                flex-direction: column;
            }

            .grid {
                max-height: 180px;
            }
        }

        @media (max-width: 450px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
                gap: 6px;
            }

            .btn-group {
                width: 100%;
            }

            .btn-sm {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="pref-card">
        <div class="pref-header">
            <h2><i class="fas fa-cog fa-spin"></i> Configuration initiale</h2>
            <div class="user-badge"><i class="fas fa-user-circle"></i>
                <?= htmlspecialchars($_SESSION['user_nom'] ?? 'Utilisateur') ?></div>
        </div>

        <div class="pref-body">
            <div class="info-line"><i class="fas fa-info-circle"></i> Sélectionnez les garnisons et catégories à
                afficher</div>

            <?php if ($error): ?>
                <div class="alert"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" id="preferencesForm"
                style="display: flex; flex-direction: column; flex: 1; min-height: 0;">
                <div class="row">
                    <div class="col">
                        <div class="box">
                            <h4><i class="fas fa-map-pin
"></i> Garnisons <span id="garnisonTotal"><?= count($garnisons) ?></span></h4>
                            <!-- Barre de recherche -->
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchGarnison" placeholder="Rechercher une garnison..."
                                    autocomplete="off">
                            </div>
                            <div class="grid" id="garnisonsContainer">
                                <?php foreach ($garnisons as $g): ?>
                                    <label class="item">
                                        <input type="checkbox" name="garnisons[]"
                                            value="<?= htmlspecialchars($g['garnison']) ?>" class="garnison-input">
                                        <!-- Ajout de la classe garnison-name pour mettre en gras -->
                                        <span class="item-name garnison-name"><?= htmlspecialchars($g['garnison']) ?></span>
                                        <span class="item-count"><?= $g['total'] ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="actions">
                                <span class="counter"><i class="fas fa-check-circle"></i> <span
                                        id="garnisonSelected">0</span>/<?= count($garnisons) ?></span>
                                <div class="btn-group">
                                    <button type="button" class="btn-sm btn-select" id="selectAllGarnisons"><i
                                            class="fas fa-check-double"></i> Tout</button>
                                    <button type="button" class="btn-sm btn-deselect" id="deselectAllGarnisons"><i
                                            class="fas fa-times"></i> Rien</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="box">
                            <h4><i class="fas fa-tags"></i> Catégories <span id="categorieTotal">0</span></h4>
                            <div class="grid" id="categoriesContainer">
                                <div style="text-align: center; padding: 15px; color: #6c757d;"><i
                                        class="fas fa-map-pin"></i><br>Sélectionnez des garnisons</div>
                            </div>
                            <div class="actions">
                                <span class="counter"><i class="fas fa-check-circle"></i> <span
                                        id="categorieSelected">0</span>/<span id="categorieTotalCount">0</span></span>
                                <div class="btn-group">
                                    <button type="button" class="btn-sm btn-select" id="selectAllCategories" disabled><i
                                            class="fas fa-check-double"></i> Tout</button>
                                    <button type="button" class="btn-sm btn-deselect" id="deselectAllCategories"
                                        disabled><i class="fas fa-times"></i> Rien</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="summary">
                    <div><i class="fas fa-map-pin"></i> <span id="garnisonSummary">Aucune garnison</span></div>
                    <div><i class="fas fa-tags"></i> <span id="categorieSummary">Aucune catégorie</span></div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn" disabled><i class="fas fa-check-circle"></i>
                    Valider</button>
            </form>
        </div>
    </div>

    <!-- Scripts locaux -->
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/sweetalert2.all.min.js"></script>

    <script>
        const traductions = {
            'ACTIF': 'Actif',
            'DCD_AP_BIO': 'Décédés Après Bio',
            'INTEGRES': 'Intégrés',
            'RETRAITES': 'Retraités',
            'DCD_AV_BIO': 'Décédés Avant Bio'
        };

        let categoriesData = [];

        function loadCategories(garnisons) {
            const container = document.getElementById('categoriesContainer');
            const categorieTotal = document.getElementById('categorieTotal');
            const selectAllBtn = document.getElementById('selectAllCategories');
            const deselectAllBtn = document.getElementById('deselectAllCategories');
            const categorieTotalCount = document.getElementById('categorieTotalCount');

            if (garnisons.length === 0) {
                container.innerHTML =
                    '<div style="text-align: center; padding: 15px; color: #6c757d;"><i class="fas fa-map-pin mb-1"></i><br>Sélectionnez des garnisons</div>';
                categorieTotal.textContent = '0';
                categorieTotalCount.textContent = '0';
                selectAllBtn.disabled = true;
                deselectAllBtn.disabled = true;
                document.getElementById('categorieSelected').textContent = '0';
                return;
            }

            container.innerHTML =
                '<div style="text-align: center; padding: 15px; color: #6c757d;"><div class="loading mb-1"></div><br>Chargement...</div>';

            fetch('ajax/get_categories.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        garnisons: garnisons
                    })
                })
                .then(res => res.json())
                .then(data => {
                    categoriesData = data;
                    if (data.length === 0) {
                        container.innerHTML =
                            '<div style="text-align: center; padding: 15px; color: #6c757d;"><i class="fas fa-info-circle mb-1"></i><br>Aucune catégorie</div>';
                    } else {
                        let html = '';
                        data.forEach(cat => {
                            // Ajout de la classe categorie-name pour mettre le nom en gras
                            html +=
                                `<label class="item"><input type="checkbox" name="categories[]" value="${cat.categorie}" class="categorie-input"><span class="item-name categorie-name">${traductions[cat.categorie] || cat.categorie}</span><span class="item-count">${cat.total}</span></label>`;
                        });
                        container.innerHTML = html;
                        document.querySelectorAll('.categorie-input').forEach(cb => cb.addEventListener('change',
                            function() {
                                updateCategorieCount();
                                updateSummary();
                            }));
                    }
                    categorieTotal.textContent = data.length;
                    categorieTotalCount.textContent = data.length;
                    selectAllBtn.disabled = data.length === 0;
                    deselectAllBtn.disabled = data.length === 0;
                    updateCategorieCount();
                })
                .catch(err => {
                    console.error(err);
                    container.innerHTML =
                        '<div style="text-align: center; padding: 15px; color: #dc3545;"><i class="fas fa-exclamation-circle mb-1"></i><br>Erreur</div>';
                });
        }

        function updateCounters() {
            const gChecked = document.querySelectorAll('.garnison-input:checked').length;
            document.getElementById('garnisonSelected').textContent = gChecked;
            updateSummary();
            const cChecked = document.querySelectorAll('.categorie-input:checked').length;
            document.getElementById('submitBtn').disabled = gChecked === 0 || cChecked === 0;
        }

        function updateCategorieCount() {
            const cChecked = document.querySelectorAll('.categorie-input:checked').length;
            document.getElementById('categorieSelected').textContent = cChecked;
            updateSummary();
            const gChecked = document.querySelectorAll('.garnison-input:checked').length;
            document.getElementById('submitBtn').disabled = gChecked === 0 || cChecked === 0;
        }

        function updateSummary() {
            const gChecked = document.querySelectorAll('.garnison-input:checked');
            const gNames = Array.from(gChecked).map(cb => cb.closest('.item').querySelector('.item-name').textContent);
            document.getElementById('garnisonSummary').textContent = gNames.length ? gNames.slice(0, 3).join(', ') + (gNames
                .length > 3 ? ` +${gNames.length-3}` : '') : 'Aucune garnison';

            const cChecked = document.querySelectorAll('.categorie-input:checked');
            const cNames = Array.from(cChecked).map(cb => cb.closest('.item').querySelector('.item-name').textContent);
            document.getElementById('categorieSummary').textContent = cNames.length ? cNames.slice(0, 3).join(', ') + (
                cNames.length > 3 ? ` +${cNames.length-3}` : '') : 'Aucune catégorie';
        }

        // Fonction de filtrage des garnisons
        function filterGarnisons(searchTerm) {
            const items = document.querySelectorAll('#garnisonsContainer .item');
            const lowerSearch = searchTerm.toLowerCase();
            items.forEach(item => {
                const name = item.querySelector('.item-name').textContent.toLowerCase();
                if (name.includes(lowerSearch)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Attacher les événements après le chargement initial
        document.querySelectorAll('.garnison-input').forEach(cb => {
            cb.addEventListener('change', function() {
                const selected = Array.from(document.querySelectorAll('.garnison-input:checked')).map(c => c
                    .value);
                loadCategories(selected);
                updateCounters();
            });
        });

        document.getElementById('selectAllGarnisons')?.addEventListener('click', function() {
            document.querySelectorAll('.garnison-input').forEach(cb => cb.checked = true);
            const selected = Array.from(document.querySelectorAll('.garnison-input:checked')).map(c => c.value);
            loadCategories(selected);
            updateCounters();
        });

        document.getElementById('deselectAllGarnisons')?.addEventListener('click', function() {
            document.querySelectorAll('.garnison-input').forEach(cb => cb.checked = false);
            loadCategories([]);
            updateCounters();
        });

        document.getElementById('selectAllCategories')?.addEventListener('click', function() {
            document.querySelectorAll('.categorie-input').forEach(cb => cb.checked = true);
            updateCategorieCount();
            updateSummary();
        });

        document.getElementById('deselectAllCategories')?.addEventListener('click', function() {
            document.querySelectorAll('.categorie-input').forEach(cb => cb.checked = false);
            updateCategorieCount();
            updateSummary();
        });

        // Écouteur pour la recherche
        document.getElementById('searchGarnison')?.addEventListener('input', function(e) {
            filterGarnisons(e.target.value);
        });

        document.getElementById('preferencesForm')?.addEventListener('submit', function(e) {
            if (document.querySelectorAll('.garnison-input:checked').length === 0 || document.querySelectorAll(
                    '.categorie-input:checked').length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Sélectionnez garnisons et catégories',
                    confirmButtonColor: '#2e7d32'
                });
                return;
            }
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
        });
    </script>
</body>

</html>