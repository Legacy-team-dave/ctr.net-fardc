<?php

/**
 * Page de synchronisation — Console ADMIN_IG
 * Permet de :
 * - Lancer la synchronisation sélective des contrôles
 * - Lancer la synchronisation des archives
 * - Consulter l'historique des sessions de synchronisation
 * - Voir et résoudre les conflits
 */
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG']);

require_once '../../config/database_central.php';

$page_titre = 'Synchronisation';
$breadcrumb = ['Contrôles' => app_url('modules/controles/liste.php'), 'Synchronisation' => '#'];

// Vérifier si base centrale est accessible
$central_ok = ($pdo_central !== null);

// Historique des sessions (depuis la base centrale)
$sessions = [];
$conflits_pending = 0;
if ($central_ok) {
    try {
        // Les tables sync_sessions, sync_conflits, archives_sync existent déjà dans la base centrale
        // (créées par le SQL de structure ctr_net-fardc-active-web-1.sql)
        // Pas besoin de CREATE TABLE IF NOT EXISTS ici

        $sessions = $pdo->query("SELECT * FROM sync_sessions ORDER BY date_debut DESC LIMIT 20")->fetchAll();
        $conflits_pending = (int)$pdo->query("SELECT COUNT(*) FROM sync_conflits WHERE resolution = 'pending'")->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur sync page: " . $e->getMessage());
    }
}

// Compter les contrôles non synchronisés (terrain)
// L'enum sync_status utilise 'local' (pas 'pending')
$pending_controles = 0;
try {
    $pending_controles = (int)$pdo->query("SELECT COUNT(*) FROM controles WHERE sync_status = 'local'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Erreur comptage controles: " . $e->getMessage());
}

// Compter les archives disponibles
$archives_count = 0;
$backups_dir = realpath(__DIR__ . '/../../backups/');
if ($backups_dir && is_dir($backups_dir)) {
    $archives_count = count(array_filter(scandir($backups_dir), fn($f) => $f !== '.' && $f !== '..' && is_file($backups_dir . DIRECTORY_SEPARATOR . $f)));
}

include '../../includes/header.php';
?>

<style>
    .sync-dashboard {
        padding: 0 5px;
    }

    .sync-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        border: none;
        margin-bottom: 20px;
        overflow: hidden;
    }

    .sync-card-header {
        background: linear-gradient(135deg, #2e7d32, #1b5e20);
        color: white;
        padding: 12px 18px;
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sync-card-body {
        padding: 18px;
    }

    .sync-stat {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 18px;
        text-align: center;
        border: 1px solid #e0e0e0;
        transition: transform 0.15s;
    }

    .sync-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .sync-stat .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #1b5e20;
    }

    .sync-stat .stat-label {
        font-size: 0.8rem;
        color: #666;
        margin-top: 4px;
    }

    .sync-stat.warning .stat-value {
        color: #e65100;
    }

    .sync-stat.danger .stat-value {
        color: #c62828;
    }

    .sync-stat.info .stat-value {
        color: #1565c0;
    }

    .btn-sync {
        background: linear-gradient(135deg, #2e7d32, #1b5e20);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: transform 0.15s, box-shadow 0.15s;
    }

    .btn-sync:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
        color: white;
    }

    .btn-sync:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .btn-sync-archives {
        background: linear-gradient(135deg, #1565c0, #0d47a1);
    }

    .btn-sync-archives:hover {
        box-shadow: 0 4px 15px rgba(21, 101, 192, 0.3);
    }

    .status-badge {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-succes {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .status-partiel {
        background: #fff3e0;
        color: #e65100;
    }

    .status-echec {
        background: #fce4ec;
        color: #c62828;
    }

    .status-en_cours {
        background: #e3f2fd;
        color: #1565c0;
    }

    .session-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .session-table thead th {
        background: #f8f9fa;
        padding: 10px 12px;
        text-align: left;
        font-weight: 600;
        color: #555;
        border-bottom: 2px solid #e0e0e0;
        font-size: 0.78rem;
        text-transform: uppercase;
    }

    .session-table tbody td {
        padding: 8px 12px;
        border-bottom: 1px solid #f0f0f0;
    }

    .session-table tbody tr:hover {
        background: #f8f9fa;
    }

    .central-offline {
        background: #fce4ec;
        border: 1px solid #ef9a9a;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        color: #c62828;
    }

    .central-offline i {
        font-size: 2rem;
        margin-bottom: 10px;
        display: block;
    }

    .info-box-sync {
        background: #e8f5e9;
        border-left: 4px solid #2e7d32;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 0.82rem;
        color: #2e7d32;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>

<div class="sync-dashboard">

    <!-- Bandeau d'info -->
    <div class="info-box-sync">
        <i class="fas fa-info-circle"></i>
        Synchronisation terrain → central. Ordre : Militaires → Contrôles → Litiges (automatique). Seuls les contrôles sélectionnés dans la liste sont synchronisés.
    </div>

    <?php if (!$central_ok): ?>
        <div class="central-offline">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Base centrale inaccessible</strong><br>
            Vérifiez la configuration dans <code>.env</code> (CENTRAL_DB_HOST, CENTRAL_DB_NAME, etc.)
        </div>
    <?php else: ?>

        <!-- Statistiques -->
        <div class="row mb-3">
            <div class="col-sm-6 col-md-3 mb-2">
                <div class="sync-stat">
                    <div class="stat-value"><?= $pending_controles ?></div>
                    <div class="stat-label"><i class="fas fa-clock"></i> Contrôles en attente</div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3 mb-2">
                <div class="sync-stat warning">
                    <div class="stat-value"><?= $conflits_pending ?></div>
                    <div class="stat-label"><i class="fas fa-exclamation-triangle"></i> Conflits non résolus</div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3 mb-2">
                <div class="sync-stat info">
                    <div class="stat-value"><?= $archives_count ?></div>
                    <div class="stat-label"><i class="fas fa-archive"></i> Fichiers d'archive</div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3 mb-2">
                <div class="sync-stat">
                    <div class="stat-value"><?= count($sessions) ?></div>
                    <div class="stat-label"><i class="fas fa-history"></i> Sessions récentes</div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="row mb-3">
            <div class="col-md-6 mb-2">
                <div class="sync-card">
                    <div class="sync-card-header"><i class="fas fa-sync-alt"></i> Synchroniser les contrôles</div>
                    <div class="sync-card-body">
                        <p style="font-size:0.85rem;color:#666;margin-bottom:12px;">
                            Sélectionnez les contrôles à synchroniser depuis la
                            <a href="liste.php" style="color:#2e7d32;font-weight:600;">liste des contrôles</a>,
                            puis lancez la synchronisation.
                        </p>
                        <a href="liste.php" class="btn-sync">
                            <i class="fas fa-list"></i> Aller à la liste des contrôles
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-2">
                <div class="sync-card">
                    <div class="sync-card-header" style="background:linear-gradient(135deg,#1565c0,#0d47a1);">
                        <i class="fas fa-archive"></i> Synchroniser les archives
                    </div>
                    <div class="sync-card-body">
                        <p style="font-size:0.85rem;color:#666;margin-bottom:12px;">
                            Rapatrier les fichiers de sauvegarde (exports SQL, logs) vers le serveur central.
                            <?= $archives_count ?> fichier(s) dans le répertoire source.
                        </p>
                        <form method="post" action="sync_archives.php" onsubmit="return confirm('Lancer la synchronisation des archives ?')">
                            <div style="margin-bottom:8px;">
                                <label style="font-size:0.82rem;cursor:pointer;">
                                    <input type="checkbox" name="force" value="1">
                                    Forcer la re-synchronisation des fichiers déjà présents
                                </label>
                            </div>
                            <button type="submit" class="btn-sync btn-sync-archives" <?= $archives_count === 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-upload"></i> Synchroniser les archives
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historique des sessions -->
        <div class="sync-card">
            <div class="sync-card-header"><i class="fas fa-history"></i> Historique des synchronisations</div>
            <div class="sync-card-body" style="padding:0;">
                <?php if (empty($sessions)): ?>
                    <div style="text-align:center;padding:30px;color:#999;">
                        <i class="fas fa-inbox" style="font-size:2rem;margin-bottom:8px;display:block;"></i>
                        Aucune synchronisation effectuée
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="session-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Type</th>
                                    <th>Début</th>
                                    <th>Fin</th>
                                    <th>Status</th>
                                    <th>Détails</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $s):
                                    $det = json_decode($s['details'] ?? '{}', true);
                                    $det_text = [];
                                    if (isset($det['inserted'])) $det_text[] = $det['inserted'] . ' ins.';
                                    if (isset($det['updated'])) $det_text[] = $det['updated'] . ' maj.';
                                    if (isset($det['conflicts'])) $det_text[] = $det['conflicts'] . ' conf.';
                                    if (isset($det['ok'])) $det_text[] = $det['ok'] . ' ok';
                                    if (isset($det['skipped'])) $det_text[] = $det['skipped'] . ' ign.';
                                    if (isset($det['error'])) $det_text[] = $det['error'] . ' err.';
                                ?>
                                    <tr>
                                        <td><?= (int)$s['id_session'] ?></td>
                                        <td><strong><?= htmlspecialchars($s['type_sync']) ?></strong></td>
                                        <td><?= htmlspecialchars($s['date_debut']) ?></td>
                                        <td><?= $s['date_fin'] ? htmlspecialchars($s['date_fin']) : '<em>—</em>' ?></td>
                                        <td><span class="status-badge status-<?= htmlspecialchars($s['status']) ?>"><?= htmlspecialchars($s['status']) ?></span></td>
                                        <td style="font-size:0.8rem;"><?= implode(', ', $det_text) ?: '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($conflits_pending > 0): ?>
            <!-- Conflits en attente -->
            <div class="sync-card">
                <div class="sync-card-header" style="background:linear-gradient(135deg,#e65100,#bf360c);">
                    <i class="fas fa-exclamation-triangle"></i> Conflits en attente (<?= $conflits_pending ?>)
                </div>
                <div class="sync-card-body" style="padding:0;">
                    <?php
                    $conflits = $pdo->query("SELECT c.*, s.type_sync FROM sync_conflits c 
                JOIN sync_sessions s ON c.id_session = s.id_session 
                WHERE c.resolution = 'pending' ORDER BY c.id_conflit DESC LIMIT 50")->fetchAll();
                    ?>
                    <div style="overflow-x:auto;">
                        <table class="session-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Table</th>
                                    <th>ID Terrain</th>
                                    <th>ID Central</th>
                                    <th>V. Terrain</th>
                                    <th>V. Central</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conflits as $c): ?>
                                    <tr>
                                        <td><?= (int)$c['id_conflit'] ?></td>
                                        <td><strong><?= htmlspecialchars($c['table_concernee']) ?></strong></td>
                                        <td><?= (int)$c['id_record_terrain'] ?></td>
                                        <td><?= $c['id_record_central'] ? (int)$c['id_record_central'] : '—' ?></td>
                                        <td><?= (int)$c['version_terrain'] ?></td>
                                        <td><?= (int)$c['version_central'] ?></td>
                                        <td>
                                            <form method="post" action="sync_resolve.php" style="display:inline;">
                                                <input type="hidden" name="id_conflit" value="<?= (int)$c['id_conflit'] ?>">
                                                <button type="submit" name="resolution" value="terrain_wins" class="btn btn-sm btn-success" title="Garder version terrain" onclick="return confirm('Appliquer la version terrain ?')">
                                                    <i class="fas fa-arrow-up"></i>
                                                </button>
                                                <button type="submit" name="resolution" value="central_wins" class="btn btn-sm btn-primary" title="Garder version centrale" onclick="return confirm('Garder la version centrale ?')">
                                                    <i class="fas fa-arrow-down"></i>
                                                </button>
                                                <button type="submit" name="resolution" value="ignored" class="btn btn-sm btn-secondary" title="Ignorer">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; // central_ok 
    ?>
</div>

<?php include '../../includes/footer.php'; ?>