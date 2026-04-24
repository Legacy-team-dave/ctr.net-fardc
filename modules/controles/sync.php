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

// ── Génération / récupération côté serveur pour le premier affichage ──
$client_id = $_SESSION['sync_client_id'] ?? null;
if (!$client_id || strlen($client_id) < 10) {
    $client_id = bin2hex(random_bytes(16));
    $_SESSION['sync_client_id'] = $client_id;
}
$garnison_label = $_SESSION['sync_garnison_label'] ?? '';

include '../../includes/header.php';
?>

<input type="hidden" id="syncClientId" value="<?= h($client_id) ?>">
<input type="hidden" id="syncGarnisonLabel" value="<?= h($garnison_label) ?>">

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
    background: linear-gradient(135deg, #1565c0, #0d47a1);
}

.sync-stat-icon.sync-pending {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
}

.sync-stat-icon.sync-total {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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

/* Styles pour le rapport de conflits */
.conflict-report {
    margin-top: 20px;
    border-top: 2px solid #dee2e6;
    padding-top: 20px;
}

.conflict-report-header {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.conflict-report-header i {
    font-size: 1.3rem;
}

.conflict-report-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.conflict-summary {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.conflict-summary-item {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.conflict-summary-label {
    font-weight: 600;
    color: #856404;
}

.conflict-summary-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: #856404;
}

.conflict-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.conflict-table th {
    background: #f8f9fa;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    position: sticky;
    top: 0;
}

.conflict-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: top;
}

.conflict-table tr:hover {
    background: #f8f9fa;
}

.conflict-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}

.conflict-badge.equipe {
    background: #cfe2ff;
    color: #084298;
}

.conflict-badge.controle {
    background: #f8d7da;
    color: #721c24;
}

.conflict-badge.autre {
    background: #e2e3e5;
    color: #383d41;
}

.conflict-details-preview {
    max-width: 300px;
    font-size: 0.8rem;
    color: #6c757d;
}

.conflict-actions {
    margin-top: 20px;
    text-align: center;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.btn-view-conflicts {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-view-conflicts:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.conflict-report-container {
    max-height: 500px;
    overflow-y: auto;
}

.auto-redirect-message {
    margin-top: 15px;
    padding: 10px;
    background: #e7f3ff;
    border-radius: 6px;
    text-align: center;
    font-size: 0.85rem;
    color: #004085;
}

.auto-redirect-message i {
    margin-right: 5px;
}

/* SweetAlert personnalisé sans bouton */
.swal2-popup.no-confirm-button .swal2-confirm {
    display: none !important;
}

.swal2-popup.auto-close .swal2-confirm {
    display: none !important;
}
</style>

<div class="sync-simple-page">
    <div class="sync-hero">
        <div class="sync-hero-head">
            <i class="fas fa-network-wired"></i>
            Synchronisation client / serveur
        </div>
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
                    <span><i class="fas fa-clipboard-check"></i> Contrôles : <strong
                            id="syncControlesCount">0</strong></span>
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
                <li>Cliquer sur <strong>Synchroniser maintenant</strong> pour envoyer uniquement les <strong>membres
                        d'équipe</strong> et les <strong>contrôles</strong> encore en attente.</li>
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
const redirectUrl = <?= json_encode(app_url('modules/controles/liste.php')) ?>;

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
            equipes: pendingEquipesCount,
            controles: pendingControlesCount
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

// Fonction pour afficher un toast SweetAlert sans bouton avec auto-fermeture
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
            // Ajouter une classe pour masquer le bouton
            const popup = Swal.getPopup();
            if (popup) {
                popup.classList.add('auto-close');
            }
        },
        willClose: () => {
            if (redirectAfter) {
                window.location.href = redirectUrl;
            }
        }
    });
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

async function streamSyncRequest(serverIp, clientId, garnisonLabel) {
    const formData = new FormData();
    formData.append('server_ip', serverIp);
    formData.append('client_id', clientId);
    formData.append('garnison_label', garnisonLabel);
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
                const syncState = eventData.data && eventData.data.sync_state;
                const isNoDataState = syncState === 'no_data';
                const isAlreadySyncedState = syncState === 'already_synced';
                const isConflictsPendingState = syncState === 'conflicts_pending';

                if (isNoDataState) {
                    hideSyncProgress();
                } else {
                    updateSyncProgress({
                        percentage: 100,
                        step: isConflictsPendingState ?
                            'Synchronisation transmise. Génération du rapport de conflits...' :
                            (isAlreadySyncedState ?
                                'Données déjà présentes sur le serveur. Mise à jour locale terminée.' :
                                'Synchronisation finalisée avec succès.'),
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

function buildConflictReportHtml(conflicts) {
    if (!conflicts || conflicts.length === 0) {
        return '';
    }

    let html = '<div class="conflict-report">';
    html += '<div class="conflict-report-header">';
    html += '<i class="fas fa-exclamation-triangle"></i>';
    html += '<h4>Rapport des conflits de synchronisation</h4>';
    html += '</div>';

    const equipeConflicts = conflicts.filter(c => c.type === 'equipe' || c.type === 'team' || c.type === 'membre');
    const controleConflicts = conflicts.filter(c => c.type === 'controle' || c.type === 'control');

    html += '<div class="conflict-summary">';
    html +=
        '<div class="conflict-summary-item"><span class="conflict-summary-label">Total des conflits :</span><span class="conflict-summary-value">' +
        conflicts.length + '</span></div>';
    if (equipeConflicts.length > 0) {
        html +=
            '<div class="conflict-summary-item"><span class="conflict-summary-label">👥 Équipes/Membres :</span><span class="conflict-summary-value">' +
            equipeConflicts.length + '</span></div>';
    }
    if (controleConflicts.length > 0) {
        html +=
            '<div class="conflict-summary-item"><span class="conflict-summary-label">📋 Contrôles :</span><span class="conflict-summary-value">' +
            controleConflicts.length + '</span></div>';
    }
    html += '</div>';

    html += '<div class="conflict-report-container">';
    html += '<table class="conflict-table">';
    html +=
        '<thead><tr><th>Type</th><th>ID / Référence</th><th>Nom / Libellé</th><th>Détails du conflit</th></tr></thead>';
    html += '<tbody>';

    conflicts.forEach(conflict => {
        let typeClass = 'autre';
        let typeLabel = 'Autre';

        if (conflict.type === 'equipe' || conflict.type === 'team' || conflict.type === 'membre') {
            typeClass = 'equipe';
            typeLabel = '👥 Équipe';
        } else if (conflict.type === 'controle' || conflict.type === 'control') {
            typeClass = 'controle';
            typeLabel = '📋 Contrôle';
        }

        const id = conflict.id || conflict.reference || conflict.controle_id || conflict.equipe_id || 'N/A';
        const name = conflict.nom || conflict.name || conflict.libelle || conflict.titre || 'N/A';

        let detailsHtml = '';
        if (conflict.message) {
            detailsHtml += '<div><strong>Message:</strong> ' + escapeHtml(conflict.message) + '</div>';
        }
        if (conflict.details) {
            if (typeof conflict.details === 'object') {
                detailsHtml += '<div><strong>Infos:</strong> <pre style="font-size:0.7rem; margin-top:5px;">' +
                    escapeHtml(JSON.stringify(conflict.details, null, 2)) + '</pre></div>';
            } else {
                detailsHtml += '<div>' + escapeHtml(conflict.details) + '</div>';
            }
        }

        html += `<tr>
                        <td><span class="conflict-badge ${typeClass}">${typeLabel}</span></td>
                        <td><code>${escapeHtml(id)}</code></td>
                        <td><strong>${escapeHtml(name)}</strong></td>
                        <td class="conflict-details-preview">${detailsHtml || '<em class="text-muted">Aucun détail supplémentaire</em>'}</td>
                     </tr>`;
    });

    html += '</tbody></table></div>';
    html += '<div class="conflict-actions">';
    html += `<button class="btn-view-conflicts" onclick="window.open('${appUrl('admin/conflicts.php')}', '_blank')">`;
    html += '<i class="fas fa-external-link-alt"></i> Voir tous les conflits sur le serveur';
    html += '</button></div></div>';
    return html;
}

function buildSyncFeedbackHtml(mode, data, serverIp) {
    const payload = data && data.data ? data.data : {};

    if (mode === 'test') {
        const targetUrl = payload.target_url || data.target_url || serverIp;
        return `
                <div style="text-align:left; line-height:1.6;">
                    <div><strong>IP Serveur :</strong> ${escapeHtml(serverIp)}</div>
                    <div><strong>Point de réception :</strong> ${escapeHtml(targetUrl)}</div>
                    <div style="margin-top:10px;">La connexion avec le serveur distant est disponible.</div>
                </div>
            `;
    }

    const sent = payload.sent || {};
    const pendingConflicts = toSyncCount(payload.pending_conflicts || 0);
    const conflictsList = payload.conflicts_list || payload.conflicts || [];
    const equipesCount = toSyncCount(sent.equipes ?? 0);
    const controlesCount = toSyncCount(sent.controles ?? 0);
    const summary = payload.summary || data.message || 'Opération terminée.';

    let rapportHtml = '';

    if (pendingConflicts > 0 && conflictsList.length > 0) {
        rapportHtml = buildConflictReportHtml(conflictsList);
    } else if (pendingConflicts > 0) {
        rapportHtml = `
                <div class="conflict-report">
                    <div class="conflict-report-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4>⚠️ Conflits détectés</h4>
                    </div>
                    <div class="conflict-summary">
                        <div class="conflict-summary-item">
                            <span class="conflict-summary-label">Nombre de conflits :</span>
                            <span class="conflict-summary-value">${pendingConflicts}</span>
                        </div>
                    </div>
                    <div class="conflict-actions">
                        <button class="btn-view-conflicts" onclick="window.open('${appUrl('admin/conflicts.php')}', '_blank')">
                            <i class="fas fa-external-link-alt"></i> Voir les conflits sur le serveur
                        </button>
                    </div>
                </div>
            `;
    }

    return `
            <div style="text-align:left; line-height:1.6; max-height: 70vh; overflow-y: auto;">
                <div><strong>Résumé :</strong> ${escapeHtml(summary)}</div>
                <div style="margin: 10px 0;">
                    <div><i class="fas fa-users"></i> <strong>Membres d'équipe synchronisés :</strong> ${equipesCount}</div>
                    <div><i class="fas fa-clipboard-check"></i> <strong>Contrôles synchronisés :</strong> ${controlesCount}</div>
                </div>
                ${rapportHtml}
            </div>
        `;
}

function appUrl(path) {
    const baseUrl = window.location.origin + window.location.pathname.replace(/\/modules\/controles\/[^/]*$/, '');
    return baseUrl + path;
}

async function requestSync(mode) {
    if (mode === 'sync' && pendingEquipesCount === 0 && pendingControlesCount === 0) {
        hideSyncProgress();
        stopSyncProgress();
        showAutoCloseToast('info', 'Aucune donnée à synchroniser',
            'Aucun membre d\'équipe ni contrôle n\'est actuellement en attente de synchronisation.', 3000, true);
        return;
    }

    // Récupérer/Créer l'ID client unique
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

    // Récupérer le libellé de garnison déjà saisi ou demander
    let garnisonLabel = window.localStorage.getItem('ctrSyncGarnisonLabel');
    const needGarnison = !garnisonLabel;

    const result = await Swal.fire({
        icon: 'question',
        title: 'Adresse du serveur',
        html: `
                <input type="text" id="swal-server-ip" class="swal2-input" 
                       placeholder="Ex: 192.168.1.107" value="${defaultSavedServerIp}">
                ${needGarnison ? `
                <input type="text" id="swal-garnison" class="swal2-input" 
                       placeholder="Nom de la garnison (ex: KINSHASA)" value="">
                ` : ''}
            `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: mode === 'test' ? 'Tester' : 'Synchroniser',
        cancelButtonText: 'Annuler',
        preConfirm: () => {
            const serverIp = document.getElementById('swal-server-ip').value.trim();
            if (!isValidServerAddress(serverIp)) {
                Swal.showValidationMessage('Veuillez saisir une IP ou une URL valide.');
                return false;
            }
            if (needGarnison) {
                const newLabel = document.getElementById('swal-garnison').value.trim();
                if (!newLabel || newLabel.length < 2) {
                    Swal.showValidationMessage(
                        'Veuillez saisir le nom de la garnison (au moins 2 caractères).');
                    return false;
                }
            }
            return serverIp;
        }
    });

    if (!result.isConfirmed || !result.value) {
        return;
    }

    const serverIp = result.value.trim();
    if (needGarnison) {
        garnisonLabel = document.getElementById('swal-garnison').value.trim();
        window.localStorage.setItem('ctrSyncGarnisonLabel', garnisonLabel);
    }

    storeServerIp(serverIp);

    try {
        if (mode === 'test') {
            setConnectionButtonState('testing');

            Swal.fire({
                title: 'Connexion',
                text: 'Vérification de la connexion au serveur...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const data = await fetchJsonWithCsrf(testSyncEndpoint, {
                server_ip: serverIp,
                client_id: clientId,
                garnison_label: garnisonLabel
            });

            setConnectionButtonState('success');

            const feedbackHtml = buildSyncFeedbackHtml('test', data, serverIp);
            showAutoCloseToast('success', 'Connexion établie', feedbackHtml, 3000, false);
            return;
        }

        resetSyncProgress();
        setSyncButtonsState(true);
        updateSyncProgress({
            percentage: 8,
            step: 'Vérification du point de réception central...',
            sent: {
                equipes: pendingEquipesCount,
                controles: pendingControlesCount
            }
        });

        await fetchJsonWithCsrf(testSyncEndpoint, {
            server_ip: serverIp,
            client_id: clientId,
            garnison_label: garnisonLabel
        });

        updateSyncProgress({
            percentage: 18,
            step: 'Préparation des données locales à synchroniser...',
            sent: {
                equipes: pendingEquipesCount,
                controles: pendingControlesCount
            }
        });

        const data = await streamSyncRequest(serverIp, clientId, garnisonLabel);
        const payload = data && data.data ? data.data : {};
        const syncState = payload.sync_state || '';
        const isNoData = syncState === 'no_data';
        const isAlreadySynced = syncState === 'already_synced';
        const hasConflictsPending = syncState === 'conflicts_pending';
        const pendingConflicts = toSyncCount(payload.pending_conflicts || 0);

        if (isNoData) {
            hideSyncProgress();
        } else {
            await new Promise((resolve) => window.setTimeout(resolve, 2000));
        }

        let icon = 'success';
        let title = 'Synchronisation terminée';
        let duration = 3000;

        if (isNoData) {
            icon = 'info';
            title = 'Aucune donnée à synchroniser';
        } else if (hasConflictsPending && pendingConflicts > 0) {
            icon = 'warning';
            title = 'Rapport de synchronisation - Conflits détectés';
            duration = 5000;
        } else if (isAlreadySynced) {
            icon = 'info';
            title = 'Données déjà synchronisées';
        }

        const feedbackHtml = buildSyncFeedbackHtml('sync', data, serverIp);

        // Afficher le toast avec auto-fermeture et redirection
        showAutoCloseToast(icon, title, feedbackHtml, duration, true);

    } catch (error) {
        if (mode === 'test') {
            setConnectionButtonState('error');
        }

        showAutoCloseToast('error', mode === 'test' ? 'Connexion impossible' : 'Erreur de synchronisation', error
            .message || 'Impossible de joindre le service de synchronisation.', 4000, mode === 'sync');

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