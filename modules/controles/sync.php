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

// ── ID client ──
 $client_id = $_SESSION['sync_client_id'] ?? null;
if (!$client_id || strlen($client_id) < 10) {
    $client_id = bin2hex(random_bytes(16));
    $_SESSION['sync_client_id'] = $client_id;
}

include '../../includes/header.php';
?>

<input type="hidden" id="syncClientId" value="<?= h($client_id) ?>">

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
    margin-bottom: 25px;
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
    background: linear-gradient(135deg, #1565c0, #0d47a1);
}

.sync-stat-icon.sync-pending {
    background: linear-gradient(135deg, #2e7d32, #1b5e20);
}

.sync-stat-icon.sync-total {
    background: linear-gradient(135deg, #dc3545, #c82333);
}

.sync-stat-icon.sync-history {
    background: linear-gradient(135deg, #6c757d, #495057);
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

.sync-stat-icon.sync-team+.sync-stat-info .sync-stat-value {
    color: #1565c0;
}

.sync-stat-icon.sync-pending+.sync-stat-info .sync-stat-value {
    color: #2e7d32;
}

.sync-stat-icon.sync-total+.sync-stat-info .sync-stat-value {
    color: #dc3545;
}

.sync-stat-icon.sync-history+.sync-stat-info .sync-stat-value {
    color: #6c757d;
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
    background: linear-gradient(135deg, #f1b50f, #e1b231);
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

.swal2-popup.auto-close .swal2-confirm {
    display: none !important;
}
</style>

<div class="sync-simple-page">
    <div class="sync-hero">
        <div class="sync-hero-head"><i class="fas fa-network-wired"></i> Synchronisation client / serveur</div>
        <div class="sync-hero-body">
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="sync-stat-card">
                        <div class="sync-stat-icon sync-team"><i class="fas fa-users"></i></div>
                        <div class="sync-stat-info">
                            <div class="sync-stat-value"><?= (int) $pending_equipes ?></div>
                            <div class="sync-stat-label">Membres d'équipe à envoyer</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="sync-stat-card">
                        <div class="sync-stat-icon sync-pending"><i class="fas fa-clipboard-list"></i></div>
                        <div class="sync-stat-info">
                            <div class="sync-stat-value"><?= (int) $pending_controles ?></div>
                            <div class="sync-stat-label">Contrôles à envoyer</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="sync-stat-card">
                        <div class="sync-stat-icon sync-total"><i class="fas fa-sync-alt"></i></div>
                        <div class="sync-stat-info">
                            <div class="sync-stat-value"><?= (int) $pending_equipes + (int) $pending_controles ?></div>
                            <div class="sync-stat-label">Total à synchroniser</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
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
                <button type="button" class="sync-btn-primary" id="start-sync-btn"><i
                        class="fas fa-cloud-upload-alt"></i> Synchroniser maintenant</button>
                <a href="<?= htmlspecialchars(app_url('modules/controles/liste.php')) ?>" class="sync-btn-neutral"><i
                        class="fas fa-list"></i> Retour à la liste</a>
            </div>
            <div id="progressContainer" class="progress-container">
                <div class="progress-header">
                    <div class="progress-title"><i class="fas fa-spinner fa-pulse"></i><span
                            id="progressPhase">Synchronisation en cours...</span></div>
                    <div class="progress-stats" id="progressStats">0%</div>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="progressBar">0%</div>
                </div>
                <div class="progress-details">
                    <span><i class="fas fa-users"></i> Équipes : <strong id="syncEquipesCount">0</strong></span>
                    <span><i class="fas fa-clipboard-check"></i> Contrôles : <strong
                            id="syncControlesCount">0</strong></span>
                    <span><i class="fas fa-clock"></i> <span id="progressTime">--</span></span>
                </div>
            </div>
        </div>
    </div>
    <div class="sync-panel">
        <div class="sync-panel-head"><i class="fas fa-list-check"></i> Fonctionnement</div>
        <div class="sync-panel-body">
            <ol class="sync-steps">
                <li>Les garnisons sont lues automatiquement depuis vos préférences utilisateur.</li>
                <li>Cliquer sur <strong>Synchroniser maintenant</strong> pour envoyer les données.</li>
                <li>Chaque PC client a son propre carte sur le serveur central.</li>
            </ol>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(document).ready(function() {
    $('[data-widget="pushmenu"]').off('click').on('click', function(e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-collapse');
    });
    $(window).on('resize', function() {});

    $('#start-sync-btn').off('click').on('click', async function() {
        if (pendingEquipesCount === 0 && pendingControlesCount === 0) {
            hideSyncProgress();
            showAutoCloseToast('info', 'Aucune donnée à synchroniser',
                'Aucun membre d\'équipe ni contrôle n\'est en attente.', 3000, true);
            return;
        }

        let clientId = window.localStorage.getItem('ctrSyncClientId');
        if (!clientId) {
            clientId = crypto.randomUUID ? crypto.randomUUID() :
                'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
                    let r = Math.random() * 16 | 0,
                        v = c == 'x' ? r : (r & 0x3 | 0x8);
                    return v.toString(16);
                });
            window.localStorage.setItem('ctrSyncClientId', clientId);
        }

        // ── Récupérer l'ID du fichier temp système ──
        let machineId = null;
        try {
            const resp = await fetch('sync_get_instance_id.php', {
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': syncCsrfToken
                }
            });
            const data = await resp.json();
            if (data.success && data.instance_id) {
                machineId = data.instance_id;
            }
        } catch (e) {
            console.warn('Impossible de récupérer l\'ID machine depuis sync_get_instance_id.php:',
                e);
        }

        if (!machineId) {
            machineId = clientId.substring(0, 16);
        }

        // ── Construire le source_instance ──
        const sourceInstance = machineId;
        const serverIp = defaultSavedServerIp;

        // ── Lancer la synchronisation sans popup de saisie ──
        resetSyncProgress();
        setSyncButtonsState(true);
        updateSyncProgress({
            percentage: 10,
            step: 'Connexion au serveur distant et envoi des données...',
            sent: {
                equipes: pendingEquipesCount,
                controles: pendingControlesCount
            }
        });

        try {
            await fetchJsonWithCsrf(testSyncEndpoint, {
                server_ip: serverIp,
                client_id: clientId
            });
        } catch (testError) {
            throw new Error('Serveur injoignable : ' + testError.message);
        }

        updateSyncProgress({
            percentage: 18,
            step: 'Préparation des données locales...',
            sent: {
                equipes: pendingEquipesCount,
                controles: pendingControlesCount
            }
        });

        try {
            await streamSyncRequest(serverIp, clientId);
        } catch (syncError) {
            showAutoCloseToast('error', 'Erreur de synchronisation', syncError.message ||
                'Impossible de joindre le serveur.', 4000, true);
        } finally {
            stopSyncProgress();
            setSyncButtonsState(false);
        }
    });
});

function isValidServerAddress(value) {
    const trimmed = (value || '').trim();
    if (!trimmed) return false;
    return /^(https?:\/\/)?([a-zA-Z0-9.-]+|\[[0-9a-fA-F:]+\])(?::\d+)?(\/.*)?$/.test(trimmed);
}

function escapeHtml(value) {
    return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g,
        '&quot;').replace(/'/g, '&#039;');
}

function toSyncCount(value) {
    if (typeof value === 'number' && Number.isFinite(value)) return value;
    const parsed = parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : 0;
}

function storeServerIp(value) {
    try {
        if (value && value.trim() !== '') window.localStorage.setItem('ctrSyncServerIp', value.trim());
    } catch (e) {}
}

function resetSyncProgress() {
    const container = document.getElementById('progressContainer');
    if (!container) return;
    container.classList.add('active');
    syncStartTime = new Date();
    if (syncTimer) {
        window.clearInterval(syncTimer);
        syncTimer = null;
    }
    syncTimer = window.setInterval(updateElapsedTime, 1000);
    updateSyncProgress({
        percentage: 0,
        step: 'Initialisation...',
        sent: {
            equipes: pendingEquipesCount,
            controles: pendingControlesCount
        }
    });
}

function hideSyncProgress() {
    const container = document.getElementById('progressContainer');
    if (container) container.classList.remove('active');
}

function stopSyncProgress() {
    if (syncTimer) {
        window.clearInterval(syncTimer);
        syncTimer = null;
    }
    updateElapsedTime();
}

function updateElapsedTime() {
    const timeNode = document.getElementById('progressTime');
    if (!timeNode || !syncStartTime) return;
    const s = Math.max(0, Math.floor((Date.now() - syncStartTime.getTime()) / 1000));
    timeNode.textContent = s > 0 ? s + 'm ' + (s % 60) + 's' : s + 's';
}

function updateSyncProgress(progress) {
    const progressBar = document.getElementById('progressBar');
    const progressStats = document.getElementById('progressStats');
    const progressPhase = document.getElementById('progressPhase');
    const equipesNode = document.getElementById('syncEquipesCount');
    const controlesNode = document.getElementById('syncControlesCount');
    if (!progressBar || !progressStats || !progressPhase) return;
    const percentage = Math.max(0, Math.min(100, toSyncCount(progress.percentage)));
    progressBar.style.width = percentage + '%';
    progressBar.textContent = percentage + '%';
    progressStats.textContent = percentage + '%';
    progressBar.classList.toggle('is-complete', percentage >= 100);
    if (progress.step) progressPhase.textContent = progress.step;
    const sent = progress.sent || {};
    if (equipesNode && Object.prototype.hasOwnProperty.call(sent, 'equipes')) equipesNode.textContent = toSyncCount(sent
        .equipes);
    if (controlesNode && Object.prototype.hasOwnProperty.call(sent, 'controles')) controlesNode.textContent =
        toSyncCount(sent.controles);
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
    if (!response.ok || !data.success) throw new Error(data.message || 'Erreur de connexion.');
    return data;
}

async function fetchSyncGetClientId() {
    try {
        const resp = await fetch('sync_get_instance_id.php', {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': syncCsrfToken
            }
        });
        const data = await resp.json();
        if (data.success && data.instance_id) return data.instance_id;
        return null;
    } catch (e) {
        return null;
    }
}

// =====================================================================
// streamSyncRequest — AVEC TIMEOUT 60s + INACTIVITÉ 30s
// =====================================================================
async function streamSyncRequest(serverIp, clientId) {
    const formData = new FormData();
    formData.append('server_ip', serverIp);
    formData.append('client_id', clientId);
    formData.append('ajax_progress', '1');

    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 60000);

    let response;
    try {
        response = await fetch(syncEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'text/event-stream, application/json',
                'X-CSRF-Token': syncCsrfToken
            },
            body: formData,
            signal: controller.signal
        });
    } catch (error) {
        window.clearTimeout(timeoutId);
        if (error.name === 'AbortError') {
            throw new Error('La synchronisation a mis trop de temps à répondre (60s). Serveur indisponible.');
        }
        throw new Error('Impossible de joindre le service : ' + error.message);
    }
    window.clearTimeout(timeoutId);

    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json') && !contentType.includes('text/event-stream')) {
        const fallback = await response.json().catch(() => ({}));
        if (!response.ok || !fallback.success) throw new Error(fallback.message ||
            'Le serveur a rejeté la synchronisation.');
        return fallback;
    }

    const reader = response.body ? response.body.getReader() : null;
    if (!reader) {
        const fallback = await response.json().catch(() => ({}));
        if (!response.ok || !fallback.success) throw new Error(fallback.message ||
        'Impossible de lire la réponse.');
        return fallback;
    }

    const decoder = new TextDecoder();
    let buffer = '';
    let finalPayload = null;
    let lastActivity = Date.now();
    const inactivityTimeout = 30000;

    const consumeLines = (textChunk) => {
        lastActivity = Date.now();
        buffer += textChunk;
        const lines = buffer.split(/\r?\n/);
        buffer = lines.pop() || '';
        for (const rawLine of lines) {
            const line = rawLine.trim();
            if (!line.startsWith('data:')) continue;
            const jsonText = line.substring(5).trim();
            if (!jsonText) continue;
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
                const syncState = eventData.data && eventData.data.sync_state;
                if (syncState === 'no_data') hideSyncProgress();
                else {
                    updateSyncProgress({
                        percentage: 100,
                        step: syncState === 'conflicts_pending' ?
                            'Synchronisation transmise avec conflits.' : (syncState ===
                                'already_synced' ? 'Données déjà présentes.' :
                                'Synchronisation finalisée.'),
                        sent: (eventData.data && eventData.data.sent) || {
                            equipes: pendingEquipesCount,
                            controles: pendingControlesCount
                        }
                    });
                }
                continue;
            }
            if (eventData.event === 'error') throw new Error(eventData.message ||
                'Erreur rapportée par le serveur.');
        }
    };

    while (true) {
        if (Date.now() - lastActivity > inactivityTimeout) throw new Error(
            'Le serveur a cessé de répondre pendant plus de 30 secondes.');
        const result = await reader.read();
        if (result.done) break;
        consumeLines(decoder.decode(result.value, {
            stream: true
        }));
    }

    if (buffer.trim().startsWith('{')) {
        try {
            const fallbackJson = JSON.parse(buffer.trim());
            if (!fallbackJson.success) throw new Error(fallbackJson.message || 'Erreur.');
            return fallbackJson;
        } catch (error) {
            if (error.message.startsWith('Le serveur')) throw error;
        }
    }

    if (finalPayload) return finalPayload;
    if (!response.ok) throw new Error('La synchronisation a été interrompue (code HTTP ' + response.status + ').');
    return {
        success: true,
        message: 'Synchronisation terminée.'
    };
}

function showAutoCloseToast(icon, title, html, duration = 3000, redirectAfter = true) {
    Swal.fire({
        icon: icon,
        title: title,
        html: html,
        timer: duration,
        timerProgressBar: true,
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            const popup = Swal.getPopup();
            if (popup) popup.classList.add('auto-close');
        },
        willClose: () => {
            if (redirectAfter) window.location.href = redirectUrl;
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>