<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG', 'OPERATEUR']);

$page_titre = 'Synchronisation';
$breadcrumb = ['Contrôles' => app_url('modules/controles/liste.php'), 'Synchronisation' => '#'];
$csrf_token = generate_csrf_token();
$saved_server_ip = trim((string) ($_SESSION['sync_server_ip'] ?? ''));

$pending_controles = (int) $pdo->query("SELECT COUNT(*) FROM controles WHERE COALESCE(sync_status, 'local') <> 'synced'")->fetchColumn();
$pending_equipes = 0;

try {
    $pending_equipes = (int) $pdo->query("SELECT COUNT(*) FROM equipes WHERE COALESCE(sync_status, 'local') <> 'synced'")->fetchColumn();
} catch (Throwable $e) {
    $pending_equipes = 0;
}

$last_syncs = [];
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'synchronisation'");
    if ($tableExists && $tableExists->rowCount() > 0) {
        $last_syncs = $pdo->query("SELECT * FROM synchronisation ORDER BY cree_le DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $last_syncs = [];
}

include '../../includes/header.php';
?>

<style>
    .sync-simple-page {
        padding: 0 6px;
    }

    .sync-hero,
    .sync-panel {
        background: #fff;
        border: none;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .sync-hero-head,
    .sync-panel-head {
        background: linear-gradient(135deg, #2e7d32, #1b5e20);
        color: #fff;
        padding: 16px 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sync-hero-body,
    .sync-panel-body {
        padding: 20px;
    }

    .sync-stat-card {
        background: #fff;
        border-radius: 15px;
        padding: 20px 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        height: 100%;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .sync-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(46, 125, 50, 0.15);
    }

    .sync-stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.4rem;
        box-shadow: 0 4px 10px rgba(46, 125, 50, 0.25);
        flex-shrink: 0;
    }

    .sync-stat-icon.sync-team {
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    }

    .sync-stat-icon.sync-pending {
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    }

    .sync-stat-icon.sync-history {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    }

    .sync-stat-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .sync-stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #1b5e20;
        line-height: 1;
        margin: 0;
    }

    .sync-stat-label {
        color: #6c757d;
        font-size: 0.88rem;
        font-weight: 600;
    }

    .sync-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .sync-btn-primary,
    .sync-btn-secondary,
    .sync-btn-neutral {
        border: none;
        border-radius: 8px;
        min-height: 34px;
        min-width: auto;
        padding: 0 10px;
        font-weight: 600;
        font-size: 0.82rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        white-space: nowrap;
    }

    .sync-btn-primary {
        background: linear-gradient(135deg, #2e7d32, #1b5e20);
        color: #fff;
    }

    .sync-btn-secondary {
        background: linear-gradient(135deg, #1565c0, #0d47a1);
        color: #fff;
    }

    .sync-btn-neutral {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: #fff;
    }

    .sync-btn-primary:hover {
        background: linear-gradient(135deg, #1b5e20, #145a18);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(46, 125, 50, 0.22);
        color: #fff;
        text-decoration: none;
    }

    .sync-btn-secondary:hover {
        background: linear-gradient(135deg, #0d47a1, #0a3d8f);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(13, 71, 161, 0.24);
        color: #fff;
        text-decoration: none;
    }

    .sync-btn-secondary.is-testing {
        background: linear-gradient(135deg, #1565c0, #0d47a1);
        box-shadow: 0 8px 20px rgba(13, 71, 161, 0.24);
    }

    .sync-btn-secondary.is-success {
        background: linear-gradient(135deg, #2e7d32, #1b5e20);
        box-shadow: 0 8px 20px rgba(46, 125, 50, 0.22);
    }

    .sync-btn-secondary.is-success:hover {
        background: linear-gradient(135deg, #1b5e20, #145a18);
        color: #fff;
    }

    .sync-btn-secondary.is-danger {
        background: linear-gradient(135deg, #dc3545, #b02a37);
        box-shadow: 0 8px 20px rgba(220, 53, 69, 0.24);
    }

    .sync-btn-secondary.is-danger:hover {
        background: linear-gradient(135deg, #b02a37, #8f1f2b);
        color: #fff;
    }

    .sync-btn-neutral:hover {
        background: linear-gradient(135deg, #545b62, #3f464d);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(108, 117, 125, 0.24);
        color: #fff;
        text-decoration: none;
    }

    .sync-steps {
        margin: 0;
        padding-left: 18px;
        color: #555;
    }

    .sync-steps li {
        margin-bottom: 6px;
    }

    .progress-container {
        display: none;
        margin-top: 16px;
        padding: 14px 16px;
        background: #f8f9fa;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .progress-container.active {
        display: block;
    }

    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }

    .progress-title {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #333;
        font-weight: 600;
    }

    .progress-stats {
        background: #fff;
        padding: 2px 10px;
        border-radius: 12px;
        font-weight: 700;
        color: #1b5e20;
    }

    .progress-bar-container {
        height: 20px;
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        margin: 10px 0;
    }

    .progress-bar-fill {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #ffc107, #e0a800);
        border-radius: 10px;
        transition: width 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #212529;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .progress-bar-fill.is-complete {
        background: linear-gradient(90deg, #28a745, #1e7e34);
        color: #fff;
    }

    .progress-details {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        font-size: 0.85rem;
        color: #666;
    }

    .sync-history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.88rem;
    }

    .sync-history-table th,
    .sync-history-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #edf0f2;
        text-align: left;
    }

    .sync-history-table th {
        background: #f8f9fa;
        font-size: 0.78rem;
        text-transform: uppercase;
        color: #666;
    }
</style>

<div class="sync-simple-page">
    <div class="sync-hero">
        <div class="sync-hero-head">
            <i class="fas fa-network-wired"></i>
            Synchronisation simple client / serveur
        </div>
        <div class="sync-hero-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="sync-stat-card">
                        <div class="sync-stat-icon sync-team"><i class="fas fa-users"></i></div>
                        <div class="sync-stat-info">
                            <div class="sync-stat-value"><?= (int) $pending_equipes ?></div>
                            <div class="sync-stat-label">Membres d'équipe à envoyer</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sync-stat-card">
                        <div class="sync-stat-icon sync-pending"><i class="fas fa-clipboard-list"></i></div>
                        <div class="sync-stat-info">
                            <div class="sync-stat-value"><?= (int) $pending_controles ?></div>
                            <div class="sync-stat-label">Contrôles à envoyer</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sync-stat-card">
                        <div class="sync-stat-icon sync-history"><i class="fas fa-history"></i></div>
                        <div class="sync-stat-info">
                            <div class="sync-stat-value"><?= count($last_syncs) ?></div>
                            <div class="sync-stat-label">Tentatives enregistrées</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sync-actions">
                <button type="button" class="sync-btn-secondary" id="test-sync-btn">
                    <i class="fas fa-wifi"></i> Tester la connexion IP
                </button>
                <button type="button" class="sync-btn-primary" id="start-sync-btn">
                    <i class="fas fa-cloud-upload-alt"></i> Synchroniser maintenant
                </button>
                <a href="<?= htmlspecialchars(app_url('modules/controles/liste.php')) ?>" class="sync-btn-neutral">
                    <i class="fas fa-list"></i> Retour à la liste
                </a>
            </div>

            <div id="progressContainer" class="progress-container">
                <div class="progress-header">
                    <div class="progress-title">
                        <i class="fas fa-spinner fa-pulse"></i>
                        <span id="progressPhase">Synchronisation en cours...</span>
                    </div>
                    <div class="progress-stats" id="progressStats">0%</div>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="progressBar">0%</div>
                </div>
                <div class="progress-details">
                    <span><i class="fas fa-users"></i> Équipes : <strong id="syncEquipesCount">0</strong></span>
                    <span><i class="fas fa-clipboard-check"></i> Contrôles : <strong id="syncControlesCount">0</strong></span>
                    <span><i class="fas fa-clock"></i> <span id="progressTime">--</span></span>
                </div>
            </div>
        </div>
    </div>

    <div class="sync-panel">
        <div class="sync-panel-head">
            <i class="fas fa-list-check"></i>
            Fonctionnement
        </div>
        <div class="sync-panel-body">
            <ol class="sync-steps">
                <li>Cliquer sur <strong>Tester la connexion IP</strong>.</li>
                <li>Saisir l'adresse IP ou l'URL de la machine serveur.</li>
                <li>Cliquer sur <strong>Synchroniser maintenant</strong> pour envoyer uniquement les <strong>membres d'équipe</strong> et les <strong>contrôles</strong> encore en attente.</li>
            </ol>
        </div>
    </div>

</div>

<script>
    const syncEndpoint = <?= json_encode(app_url('api/sync_controles.php')) ?>;
    const testSyncEndpoint = <?= json_encode(app_url('api/test_sync_connection.php')) ?>;
    const syncCsrfToken = <?= json_encode($csrf_token) ?>;
    const pendingEquipesCount = <?= json_encode((int) $pending_equipes) ?>;
    const pendingControlesCount = <?= json_encode((int) $pending_controles) ?>;
    const defaultSavedServerIp = (() => {
        try {
            return window.localStorage.getItem('ctrSyncServerIp') || <?= json_encode($saved_server_ip) ?> || '';
        } catch (error) {
            return <?= json_encode($saved_server_ip) ?> || '';
        }
    })();

    let syncStartTime = null;
    let syncTimer = null;

    function isValidServerAddress(value) {
        const trimmed = (value || '').trim();
        if (!trimmed) {
            return false;
        }
        return /^(https?:\/\/)?([a-zA-Z0-9.-]+|\[[0-9a-fA-F:]+\])(?::\d+)?(\/.*)?$/.test(trimmed);
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function toSyncCount(value) {
        if (typeof value === 'number' && Number.isFinite(value)) {
            return value;
        }

        const parsed = parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function storeServerIp(value) {
        try {
            if (value && value.trim() !== '') {
                window.localStorage.setItem('ctrSyncServerIp', value.trim());
            }
        } catch (error) {
            // Ignorer si le stockage navigateur n'est pas disponible.
        }
    }

    function setConnectionButtonState(state) {
        const testButton = document.getElementById('test-sync-btn');
        if (!testButton) {
            return;
        }

        testButton.classList.remove('is-testing', 'is-success', 'is-danger');

        if (state === 'testing') {
            testButton.classList.add('is-testing');
            testButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test en cours...';
            return;
        }

        if (state === 'success') {
            testButton.classList.add('is-success');
            testButton.innerHTML = '<i class="fas fa-check-circle"></i> Connexion réussie';
            return;
        }

        if (state === 'error') {
            testButton.classList.add('is-danger');
            testButton.innerHTML = '<i class="fas fa-times-circle"></i> Serveur injoignable';
            return;
        }

        testButton.innerHTML = '<i class="fas fa-wifi"></i> Tester la connexion IP';
    }

    function setSyncButtonsState(disabled) {
        const testButton = document.getElementById('test-sync-btn');
        const syncButton = document.getElementById('start-sync-btn');

        if (testButton) {
            testButton.disabled = disabled;
        }

        if (syncButton) {
            syncButton.disabled = disabled;
        }
    }

    function updateElapsedTime() {
        const timeNode = document.getElementById('progressTime');
        if (!timeNode || !syncStartTime) {
            return;
        }

        const elapsedSeconds = Math.max(0, Math.floor((Date.now() - syncStartTime.getTime()) / 1000));
        const minutes = Math.floor(elapsedSeconds / 60);
        const seconds = elapsedSeconds % 60;
        timeNode.textContent = minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;
    }

    function updateSyncProgress(progress) {
        const progressBar = document.getElementById('progressBar');
        const progressStats = document.getElementById('progressStats');
        const progressPhase = document.getElementById('progressPhase');
        const equipesNode = document.getElementById('syncEquipesCount');
        const controlesNode = document.getElementById('syncControlesCount');

        if (!progressBar || !progressStats || !progressPhase) {
            return;
        }

        const percentage = Math.max(0, Math.min(100, toSyncCount(progress.percentage)));
        progressBar.style.width = `${percentage}%`;
        progressBar.textContent = `${percentage}%`;
        progressStats.textContent = `${percentage}%`;
        progressBar.classList.toggle('is-complete', percentage >= 100);

        if (progress.step) {
            progressPhase.textContent = progress.step;
        }

        const sent = progress.sent || {};
        if (equipesNode && Object.prototype.hasOwnProperty.call(sent, 'equipes')) {
            equipesNode.textContent = toSyncCount(sent.equipes);
        }
        if (controlesNode && Object.prototype.hasOwnProperty.call(sent, 'controles')) {
            controlesNode.textContent = toSyncCount(sent.controles);
        }

        updateElapsedTime();
    }

    function resetSyncProgress() {
        const container = document.getElementById('progressContainer');
        if (!container) {
            return;
        }

        container.classList.add('active');
        syncStartTime = new Date();

        if (syncTimer) {
            window.clearInterval(syncTimer);
        }
        syncTimer = window.setInterval(updateElapsedTime, 1000);

        updateSyncProgress({
            percentage: 0,
            step: 'Initialisation de la synchronisation...',
            sent: {
                equipes: 0,
                controles: 0
            }
        });
    }

    function hideSyncProgress() {
        const container = document.getElementById('progressContainer');
        if (container) {
            container.classList.remove('active');
        }
    }

    function stopSyncProgress() {
        if (syncTimer) {
            window.clearInterval(syncTimer);
            syncTimer = null;
        }
        updateElapsedTime();
    }

    async function fetchJsonWithCsrf(endpoint, payload) {
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': syncCsrfToken
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Impossible de joindre le service demandé.');
        }

        return data;
    }

    async function streamSyncRequest(serverIp) {
        const formData = new FormData();
        formData.append('server_ip', serverIp);
        formData.append('ajax_progress', '1');

        const response = await fetch(syncEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'text/event-stream, application/json',
                'X-CSRF-Token': syncCsrfToken
            },
            body: formData
        });

        const reader = response.body ? response.body.getReader() : null;
        if (!reader) {
            const fallback = await response.json().catch(() => ({}));
            if (!response.ok || !fallback.success) {
                throw new Error(fallback.message || 'Impossible de lancer la synchronisation.');
            }
            return fallback;
        }

        const decoder = new TextDecoder();
        let buffer = '';
        let finalPayload = null;

        const consumeLines = (textChunk) => {
            buffer += textChunk;
            const lines = buffer.split(/\r?\n/);
            buffer = lines.pop() || '';

            for (const rawLine of lines) {
                const line = rawLine.trim();
                if (!line.startsWith('data:')) {
                    continue;
                }

                const jsonText = line.substring(5).trim();
                if (!jsonText) {
                    continue;
                }

                let eventData;
                try {
                    eventData = JSON.parse(jsonText);
                } catch (error) {
                    continue;
                }

                if (eventData.event === 'progress') {
                    updateSyncProgress(eventData);
                    continue;
                }

                if (eventData.event === 'complete') {
                    finalPayload = eventData;
                    const isNoDataState = eventData.data && eventData.data.sync_state === 'no_data';

                    if (isNoDataState) {
                        hideSyncProgress();
                    } else {
                        updateSyncProgress({
                            percentage: 100,
                            step: 'Synchronisation finalisée avec succès.',
                            sent: (eventData.data && eventData.data.sent) || {
                                equipes: pendingEquipesCount,
                                controles: pendingControlesCount
                            }
                        });
                    }
                    continue;
                }

                if (eventData.event === 'error') {
                    throw new Error(eventData.message || 'Une erreur est survenue pendant la synchronisation.');
                }
            }
        };

        while (true) {
            const result = await reader.read();
            if (result.done) {
                break;
            }
            consumeLines(decoder.decode(result.value, {
                stream: true
            }));
        }

        if (buffer.trim().startsWith('{')) {
            const fallbackJson = JSON.parse(buffer.trim());
            if (!fallbackJson.success) {
                throw new Error(fallbackJson.message || 'Une erreur est survenue pendant la synchronisation.');
            }
            return fallbackJson;
        }

        if (finalPayload) {
            return finalPayload;
        }

        if (!response.ok) {
            throw new Error('La synchronisation a été interrompue avant la fin.');
        }

        return {
            success: true,
            message: 'Synchronisation terminée.'
        };
    }

    function buildSyncFeedbackHtml(mode, data, serverIp) {
        const payload = data && data.data ? data.data : {};

        if (mode === 'test') {
            const targetUrl = payload.target_url || data.target_url || serverIp;
            return `
                <div style="text-align:left; line-height:1.6;">
                    <div><strong>Serveur saisi :</strong> ${escapeHtml(serverIp)}</div>
                    <div><strong>Point de réception :</strong> ${escapeHtml(targetUrl)}</div>
                    <div style="margin-top:10px;">La connexion avec le serveur distant est opérationnelle.</div>
                </div>
            `;
        }

        const sent = payload.sent || {};
        const stats = payload.stats || {};
        const equipesCount = toSyncCount(sent.equipes ?? (stats.equipes && stats.equipes.recus) ?? stats.equipes ?? 0);
        const controlesCount = toSyncCount(sent.controles ?? (stats.controles && stats.controles.recus) ?? stats.controles ?? 0);
        const summary = payload.summary || data.message || 'Opération terminée.';

        return `
            <div style="text-align:left; line-height:1.6;">
                <div>${escapeHtml(summary)}</div>
                <ul style="margin:10px 0 0 18px; padding:0;">
                    <li><strong>Membres d'équipe synchronisés :</strong> ${equipesCount}</li>
                    <li><strong>Contrôles synchronisés :</strong> ${controlesCount}</li>
                </ul>
            </div>
        `;
    }

    async function requestSync(mode) {
        if (mode === 'sync' && pendingEquipesCount === 0 && pendingControlesCount === 0) {
            hideSyncProgress();
            stopSyncProgress();
            await Swal.fire({
                icon: 'info',
                title: 'Aucune donnée à synchroniser',
                text: 'Aucun membre d\'équipe ni contrôle n\'est actuellement en attente de synchronisation.',
                confirmButtonText: 'Fermer'
            });
            return;
        }

        const result = await Swal.fire({
            icon: 'question',
            title: 'Adresse du serveur',
            input: 'text',
            inputLabel: 'Saisissez l\'IP ou l\'URL de la machine serveur.',
            inputValue: defaultSavedServerIp,
            inputPlaceholder: 'Ex: http://192.168.1.107/ctr-net-fardc_active_front_web',
            showCancelButton: true,
            confirmButtonText: mode === 'test' ? 'Tester' : 'Synchroniser',
            cancelButtonText: 'Annuler',
            preConfirm: (value) => {
                const serverIp = (value || '').trim();
                if (!isValidServerAddress(serverIp)) {
                    Swal.showValidationMessage('Veuillez saisir une IP ou une URL valide.');
                    return false;
                }
                return serverIp;
            }
        });

        if (!result.isConfirmed || !result.value) {
            return;
        }

        const serverIp = result.value.trim();
        storeServerIp(serverIp);

        try {
            if (mode === 'test') {
                setConnectionButtonState('testing');

                Swal.fire({
                    title: 'Connexion',
                    text: 'Vérification de la connexion au serveur...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                const data = await fetchJsonWithCsrf(testSyncEndpoint, {
                    server_ip: serverIp
                });

                setConnectionButtonState('success');
                Swal.fire({
                    icon: 'success',
                    title: 'Connexion établie',
                    html: buildSyncFeedbackHtml('test', data, serverIp),
                    confirmButtonText: 'Fermer'
                });
                return;
            }

            resetSyncProgress();
            setSyncButtonsState(true);
            updateSyncProgress({
                percentage: 8,
                step: 'Préparation des données locales à synchroniser...',
                sent: {
                    equipes: pendingEquipesCount,
                    controles: pendingControlesCount
                }
            });

            const data = await streamSyncRequest(serverIp);
            const payload = data && data.data ? data.data : {};
            const isNoData = payload.sync_state === 'no_data';

            if (isNoData) {
                hideSyncProgress();
            } else {
                await new Promise((resolve) => window.setTimeout(resolve, 2000));
            }

            Swal.fire({
                icon: isNoData ? 'info' : 'success',
                title: isNoData ? 'Aucune donnée à synchroniser' : 'Synchronisation terminée',
                html: buildSyncFeedbackHtml('sync', data, serverIp),
                confirmButtonText: 'Fermer'
            }).then(() => {
                if (!isNoData) {
                    window.location.reload();
                }
            });
        } catch (error) {
            if (mode === 'test') {
                setConnectionButtonState('error');
            }

            Swal.fire({
                icon: 'error',
                title: mode === 'test' ? 'Connexion impossible' : 'Erreur de synchronisation',
                text: error.message || 'Impossible de joindre le service de synchronisation.'
            });
        } finally {
            if (mode === 'sync') {
                stopSyncProgress();
                setSyncButtonsState(false);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const testButton = document.getElementById('test-sync-btn');
        const syncButton = document.getElementById('start-sync-btn');

        if (testButton) {
            testButton.addEventListener('click', function() {
                requestSync('test');
            });
        }

        if (syncButton) {
            syncButton.addEventListener('click', function() {
                requestSync('sync');
            });
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>