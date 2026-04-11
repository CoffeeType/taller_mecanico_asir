<?php
// admin/traffic-simulator.php - Traffic Simulator Admin UI
require_once '../config/database.php';
require_once '../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    redirect('../index.php');
}

require_once '../includes/header.php';
?>

<style>
.sim-card        { border-radius: 12px; }
.sim-status-dot  { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
.sim-status-dot.running  { background: #22c55e; box-shadow: 0 0 8px #22c55e; animation: pulse 1.2s infinite; }
.sim-status-dot.stopped  { background: #94a3b8; }
@keyframes pulse { 0%,100% { opacity:1 } 50% { opacity:.4 } }
.log-preview  { font-family: 'Courier New', monospace; font-size: .82rem; background: #0f172a; color: #a3e635; border-radius: 8px; padding: 1rem; max-height: 220px; overflow-y: auto; }
.profile-card { cursor: pointer; border: 2px solid transparent; transition: all .2s; border-radius: 10px; }
.profile-card:hover, .profile-card.selected { border-color: #0d6efd; background: #e8f0fe; }
.profile-card.selected .profile-icon { color: #0d6efd; }
.profile-icon  { font-size: 2rem; }
.stat-badge    { font-size: .85rem; border-radius: 20px; padding: .3rem .75rem; }
</style>

<div class="container py-4">

    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-activity me-2 text-primary"></i>Simulador de Tráfico</h1>
            <p class="text-muted small mb-0">Genera tráfico HTTP simulado para visualizar métricas en Grafana</p>
        </div>
    </div>

    <div class="row g-4">

        <!-- ==== LEFT COLUMN: Controls ==== -->
        <div class="col-lg-6">

            <!-- Status Card -->
            <div class="card sim-card shadow-sm mb-4">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <span id="statusDot" class="sim-status-dot stopped"></span>
                        <div>
                            <div class="fw-semibold" id="statusLabel">Detenido</div>
                            <small class="text-muted" id="statusSub">Sin simulación en marcha</small>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold fs-4 text-primary" id="totalRequests">0</div>
                        <small class="text-muted">requests totales</small>
                    </div>
                </div>
            </div>

            <!-- Profile Selector -->
            <div class="card sim-card shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-sliders me-2"></i>Perfil de Tráfico</div>
                <div class="card-body">
                    <div class="row g-3" id="profileCards">
                        <div class="col-4">
                            <div class="profile-card text-center p-3 selected" data-profile="normal" onclick="selectProfile(this)">
                                <div class="profile-icon">🚗</div>
                                <div class="fw-semibold mt-2">Normal</div>
                                <small class="text-muted">1–3 req/s</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="profile-card text-center p-3" data-profile="burst" onclick="selectProfile(this)">
                                <div class="profile-icon">🚀</div>
                                <div class="fw-semibold mt-2">Burst</div>
                                <small class="text-muted">~10 req/s</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="profile-card text-center p-3" data-profile="idle" onclick="selectProfile(this)">
                                <div class="profile-icon">🐢</div>
                                <div class="fw-semibold mt-2">Idle</div>
                                <small class="text-muted">~0.2 req/s</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sliders -->
            <div class="card sim-card shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-people me-2"></i>Parámetros</div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label d-flex justify-content-between">
                            <span>Usuarios simultáneos</span>
                            <strong id="usersVal">3</strong>
                        </label>
                        <input type="range" class="form-range" id="usersSlider" min="1" max="20" value="3"
                               oninput="document.getElementById('usersVal').textContent=this.value">
                        <div class="d-flex justify-content-between text-muted small"><span>1</span><span>20</span></div>
                    </div>
                    <div>
                        <label class="form-label d-flex justify-content-between">
                            <span>Duración</span>
                            <strong id="durVal">60s</strong>
                        </label>
                        <input type="range" class="form-range" id="durationSlider" min="5" max="300" step="5" value="60"
                               oninput="document.getElementById('durVal').textContent=this.value+'s'">
                        <div class="d-flex justify-content-between text-muted small"><span>5s</span><span>300s</span></div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-3">
                <button id="btnStart" class="btn btn-primary flex-grow-1 py-2" onclick="startSim()">
                    <i class="bi bi-play-fill me-2"></i>Iniciar Simulación
                </button>
                <button id="btnStop" class="btn btn-danger py-2 px-4" onclick="stopSim()" disabled>
                    <i class="bi bi-stop-fill me-2"></i>Detener
                </button>
                <button id="btnReset" class="btn btn-outline-secondary py-2 px-3" onclick="resetLogs()" title="Resetear logs">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>

        </div>

        <!-- ==== RIGHT COLUMN: Stats + Log Preview ==== -->
        <div class="col-lg-6">

            <!-- Stats -->
            <div class="card sim-card shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-bar-chart me-2"></i>Estadísticas de Logs</div>
                <div class="card-body">
                    <div class="row g-3 text-center mb-3">
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-success bg-opacity-10">
                                <div class="fs-4 fw-bold text-success" id="statOk">0</div>
                                <small class="text-muted">Respuestas 2xx</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-danger bg-opacity-10">
                                <div class="fs-4 fw-bold text-danger" id="statErr">0</div>
                                <small class="text-muted">Errores (4xx/5xx)</small>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center px-2">
                        <span class="text-muted small">Tiempo medio resp.</span>
                        <span class="stat-badge  bg-opacity-10 text-primary fw-semibold" id="statAvg">—</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center px-2 mt-2">
                        <span class="text-muted small">Tiempo máximo resp.</span>
                        <span class="stat-badge bg-warning bg-opacity-10 text-warning fw-semibold" id="statMax">—</span>
                    </div>
                </div>
            </div>

            <!-- Log Preview -->
            <div class="card sim-card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-terminal me-2"></i>Vista previa (metrics.log)</span>
                    <span class="badge bg-secondary" id="logLines">0 líneas</span>
                </div>
                <div class="card-body p-0">
                    <div class="log-preview" id="logPreview">Esperando datos...</div>
                </div>
            </div>

            <!-- Links -->
            <div class="card sim-card shadow-sm">
                <div class="card-body">
                    <p class="text-muted small mb-3">Una vez activa la simulación, las métricas se verán en:</p>
                    <div class="d-grid gap-2">
                        <a href="http://localhost:<?= getenv('PROMETHEUS_PORT') ?: '9090' ?>/graph?g0.expr=app_http_requests_total"
                           target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-graph-up me-2"></i>Prometheus — app_http_requests_total
                        </a>
                        <a href="http://localhost:<?= getenv('GRAFANA_PORT') ?: '3000' ?>"
                           target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-display me-2"></i>Grafana Dashboard
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /row -->
</div><!-- /container -->

<script>
let selectedProfile = 'normal';
let pollingInterval = null;

function selectProfile(el) {
    document.querySelectorAll('.profile-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedProfile = el.dataset.profile;
}

function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed bottom-0 end-0 m-4 shadow-lg`;
    el.style.zIndex = 9999;
    el.innerHTML = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

function setRunning(running) {
    const dot  = document.getElementById('statusDot');
    const lbl  = document.getElementById('statusLabel');
    const sub  = document.getElementById('statusSub');
    const btnS = document.getElementById('btnStart');
    const btnT = document.getElementById('btnStop');

    if (running) {
        dot.className  = 'sim-status-dot running';
        lbl.textContent = 'En ejecución';
        sub.textContent = 'Enviando peticiones al sitio…';
        btnS.disabled  = true;
        btnT.disabled  = false;
    } else {
        dot.className  = 'sim-status-dot stopped';
        lbl.textContent = 'Detenido';
        sub.textContent = 'Sin simulación en marcha';
        btnS.disabled  = false;
        btnT.disabled  = true;
    }
}

function updateStats(data) {
    document.getElementById('totalRequests').textContent = data.stats.total_requests ?? 0;

    const statuses = data.stats.statuses ?? {};
    let ok = 0, err = 0;
    for (const [key, count] of Object.entries(statuses)) {
        const code = parseInt(key.split(' ')[1]);
        if (code >= 200 && code < 400) ok += count;
        else err += count;
    }
    document.getElementById('statOk').textContent  = ok;
    document.getElementById('statErr').textContent = err;

    const avg = data.stats.avg_response_time;
    const max = data.stats.max_response_time;
    document.getElementById('statAvg').textContent = avg ? avg + 's' : '—';
    document.getElementById('statMax').textContent = max ? max + 's' : '—';
}

function fetchStatus() {
    fetch('../api/simulate.php')
        .then(r => r.json())
        .then(data => {
            setRunning(data.running);
            updateStats(data);
        })
        .catch(() => {});
}

function fetchLogPreview() {
    fetch('../api/simulate.php?action=log_preview')
        .then(r => r.json())
        .then(data => {
            if (data.lines) {
                const box = document.getElementById('logPreview');
                box.textContent = data.lines.join('\n') || 'Sin datos aún';
                box.scrollTop = box.scrollHeight;
                document.getElementById('logLines').textContent = (data.total ?? 0) + ' líneas';
            }
        })
        .catch(() => {});
}

async function startSim() {
    const users    = parseInt(document.getElementById('usersSlider').value);
    const duration = parseInt(document.getElementById('durationSlider').value);

    const res  = await fetch('../api/simulate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start', users, duration, profile: selectedProfile }),
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'warning');
    if (data.success) {
        setRunning(true);
        startPolling();
    }
}

async function stopSim() {
    const res  = await fetch('../api/simulate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'stop' }),
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'info' : 'warning');
    if (data.success) setRunning(false);
}

async function resetLogs() {
    if (!confirm('¿Seguro que quieres resetear los logs de métricas? Esto borrará todos los datos acumulados.')) return;
    const res  = await fetch('../api/simulate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reset' }),
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'danger');
    if (data.success) {
        document.getElementById('totalRequests').textContent = '0';
        document.getElementById('statOk').textContent  = '0';
        document.getElementById('statErr').textContent = '0';
        document.getElementById('statAvg').textContent = '—';
        document.getElementById('statMax').textContent = '—';
        document.getElementById('logPreview').textContent = 'Logs reseteados.';
        document.getElementById('logLines').textContent = '0 líneas';
    }
}

function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(() => {
        fetchStatus();
        fetchLogPreview();
    }, 2000);
}

// Initial load
fetchStatus();
fetchLogPreview();
startPolling();
</script>

<?php require_once '../includes/footer.php'; ?>
