<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simulador de tráfico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .sim-status-dot{width:12px;height:12px;border-radius:50%;display:inline-block;}
        .sim-status-dot.running{background:#22c55e;animation:pulse 1.2s infinite;}
        .sim-status-dot.stopped{background:#94a3b8;}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
        .log-preview{font-family:'Courier New',monospace;font-size:.82rem;background:#0f172a;color:#a3e635;border-radius:8px;padding:1rem;max-height:220px;overflow-y:auto;}
        .profile-card{cursor:pointer;border:2px solid transparent;border-radius:10px;transition:border-color .15s;background:#fafafa;}
        .profile-card:hover,.profile-card.selected{border-color:#0d6efd;background:#e8f0fe;}
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
        <div class="flex-grow-1">
            <h1 class="h3 mb-1"><i class="bi bi-activity text-primary"></i> Simulador de tráfico JMeter</h1>
            <p class="text-muted mb-2 small">Apache JMeter en instancia aparte del sitio público · control servidor con token · no garantiza ausencia de límites o bloqueos en webs ajenas</p>
            <div id="configError" class="alert alert-warning py-2 d-none small mb-0" role="status"></div>
            <div id="pathHint" class="alert alert-danger py-2 d-none small mb-0 mt-2" role="status"></div>
        </div>
    </div>

    <div class="alert alert-info small">
        Para dominios fuera de <code>SIM_INTERNAL_HOSTS</code> marca la casilla de confirmación antes de iniciar (uso responsable, baja intensidad recomendada).
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm mb-3">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <span id="statusDot" class="sim-status-dot stopped"></span>
                        <div>
                            <div class="fw-semibold" id="statusLabel">Detenido</div>
                            <small class="text-muted" id="statusSub"></small>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold fs-4 text-primary" id="totalRequests">0</div>
                        <small class="text-muted">requests (log)</small>
                    </div>
                </div>
            </div>

            <label class="form-label fw-semibold">URL destino base</label>
            <input type="text" class="form-control mb-2" id="baseUrlInput" inputmode="url" autocomplete="url" placeholder="https://ejemplo.com o solo ejemplo.com">
            <small class="text-muted d-block mb-1">Ej. dentro de Docker contra la web: <code>http://web</code></small>
            <div id="flowStatus" class="alert alert-light border small py-2 mb-3 mb-md-3" role="status">Aún no se ha comprobado el destino. Pulsa <strong>Iniciar</strong> (comprueba y arranca) o <strong>Comprobar destino</strong>.</div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="confirmExternal">
                <label class="form-check-label" for="confirmExternal">Confirmo enviar solo carga autorizada contra esta URL (cuando no sea red interna listada)</label>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white small fw-semibold">Perfil</div>
                <div class="card-body">
                    <div class="row g-2" id="profileCards">
                        <div class="col-4"><div class="profile-card text-center p-3 selected" data-profile="normal" onclick="selectProfile(this)"><div class="fs-4">&#128663;</div><div class="small fw-semibold">Normal</div></div></div>
                        <div class="col-4"><div class="profile-card text-center p-3" data-profile="burst" onclick="selectProfile(this)"><div class="fs-4">&#128640;</div><div class="small fw-semibold">Burst</div></div></div>
                        <div class="col-4"><div class="profile-card text-center p-3" data-profile="idle" onclick="selectProfile(this)"><div class="fs-4">&#128034;</div><div class="small fw-semibold">Idle</div></div></div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label d-flex justify-content-between"><span>Usuarios</span><strong id="usersVal">3</strong></label>
                <input type="range" class="form-range" id="usersSlider" min="1" max="20" value="3" oninput="document.getElementById('usersVal').textContent=this.value">
            </div>
            <div class="mb-4">
                <label class="form-label d-flex justify-content-between"><span>Duración</span><strong id="durVal">60s</strong></label>
                <input type="range" class="form-range" id="durationSlider" min="5" max="300" step="5" value="60" oninput="document.getElementById('durVal').textContent=this.value+'s'">
            </div>

            <div class="mb-3">
                <label class="form-label small">Ruta JSON rutas opcional (dentro del contenedor simulador)</label>
                <input type="text" class="form-control form-control-sm" id="routesFile" placeholder="/var/www/html/conf/routes.json">
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" id="btnProbe" onclick="probeOnly()" type="button"><i class="bi bi-check2-circle"></i> Comprobar destino</button>
                <button class="btn btn-primary" id="btnStart" onclick="startSim()" type="button"><i class="bi bi-play-fill"></i> Iniciar</button>
                <button class="btn btn-danger" id="btnStop" onclick="stopSim()" disabled type="button"><i class="bi bi-stop-fill"></i> Detener</button>
                <button class="btn btn-outline-secondary" id="btnReset" onclick="resetLogs()" title="Vacía metrics/response logs" type="button"><i class="bi bi-arrow-counterclockwise"></i></button>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Estadísticas (logs locales)</div>
                <div class="card-body">
                    <div class="row text-center g-2">
                        <div class="col-6"><div class="p-3 bg-success bg-opacity-10 rounded"><span class="fs-4 fw-bold text-success" id="statOk">0</span><div class="small text-muted">Éxitos (2xx–3xx)</div></div></div>
                        <div class="col-6"><div class="p-3 bg-danger bg-opacity-10 rounded"><span class="fs-4 fw-bold text-danger" id="statErr">0</span><div class="small text-muted">Errores (4xx–5xx)</div></div></div>
                    </div>
                    <div class="small text-muted mt-2 px-2" id="statRecentHint"></div>
                    <div class="d-flex justify-content-between mt-3 small px-2"><span>Media respuesta</span><span id="statAvg">&mdash;</span></div>
                    <div class="d-flex justify-content-between small px-2"><span>Máximo</span><span id="statMax">&mdash;</span></div>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Últimas líneas (metrics.log)</span><span class="badge bg-secondary" id="logLines"></span>
                </div>
                <div class="card-body p-2"><pre class="log-preview mb-0" id="logPreview">—</pre></div>
            </div>
            <div class="d-grid gap-2" id="monitorLinks"></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedProfile = 'normal';
let polling = null;
let prevRunning = null;
let suppressFinishToast = false;

function selectProfile(el) {
    document.querySelectorAll('.profile-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedProfile = el.dataset.profile;
}

function toast(msg, type='success', ms=5000) {
    const el = document.createElement('div');
    el.className = 'alert alert-'+type+' position-fixed bottom-0 end-0 m-4 shadow';
    el.style.zIndex = '9999';
    el.style.maxWidth = 'min(420px, 92vw)';
    el.innerHTML = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), ms);
}

function setFlowStatus(html, kind='light') {
    const el = document.getElementById('flowStatus');
    el.className = 'alert alert-' + kind + ' border small py-2 mb-3 mb-md-3';
    el.innerHTML = html;
}

async function runProbe(showToastOnOk) {
    const body = {
        action: 'probe',
        base_url: document.getElementById('baseUrlInput').value.trim(),
        confirm_external: document.getElementById('confirmExternal').checked
    };
    const res = await fetch('api.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
    let j = {};
    try { j = await res.json(); } catch(_) {}
    if (res.status === 503) {
        const t = j.message || 'Servicio no configurado (token o API)';
        setFlowStatus(t, 'warning');
        if (showToastOnOk !== false) toast(t, 'warning');
        return null;
    }
    if (res.status === 428 || j.code === 'NEED_CONFIRM_EXTERNAL') {
        setFlowStatus('Marca la casilla de confirmación para comprobar URLs externas.', 'warning');
        toast('Marca la casilla de confirmación para URLs externas.', 'warning');
        return null;
    }
    if (j.success !== true) {
        const t = (j.message || 'No se pudo comprobar el destino');
        setFlowStatus('<strong>Sin respuesta o URL inválida:</strong> ' + t, 'danger');
        if (showToastOnOk !== false) toast(t, 'danger', 7000);
        return null;
    }
    const code = j.http_code != null ? j.http_code : '—';
    const msg = j.message || 'OK';
    setFlowStatus('<strong>Destino comprobado</strong> · HTTP <code>' + code + '</code> · ' + msg + ' <span class="text-muted">(' + new Date().toLocaleString('es') + ')</span>', 'success');
    if (showToastOnOk !== false) toast('<strong>Destino OK</strong> · HTTP ' + code + ' — ' + msg, 'success');
    return j;
}

function mapStats(s) {
    let ok = 0, err = 0;
    Object.entries((s.statuses||{})).forEach(([k,v]) => {
        const m = k.match(/\s(\d{3})$/);
        const code = m ? +m[1] : 0;
        if (code >= 200 && code < 400) ok += v;
        else if (code >= 400) err += v;
    });
    return { ok, err, avg: s.avg_response_time, max: s.max_response_time };
}

async function poll() {
    try {
        const r = await fetch('api.php');
        const data = await r.json();
        if (data.error) {
            document.getElementById('configError').classList.remove('d-none');
            document.getElementById('configError').textContent = data.error;
            document.getElementById('btnStart').disabled = true;
            document.getElementById('btnProbe').disabled = true;
        } else {
            document.getElementById('btnStart').disabled = !!data.running;
            document.getElementById('btnProbe').disabled = !!data.running;
        }
        document.getElementById('btnStop').disabled = !data.running;

        document.getElementById('totalRequests').textContent = (data.stats && data.stats.total_requests) || 0;
        const mapped = mapStats(data.stats || {});
        const okVal = (data.stats && typeof data.stats.success_requests === 'number') ? data.stats.success_requests : mapped.ok;
        const errVal = (data.stats && typeof data.stats.error_requests === 'number') ? data.stats.error_requests : mapped.err;
        document.getElementById('statOk').textContent = okVal;
        document.getElementById('statErr').textContent = errVal;
        const rw = (data.stats && data.stats.recent_window) ? data.stats.recent_window : 20;
        const rs = (data.stats && typeof data.stats.recent_success === 'number') ? data.stats.recent_success : null;
        const re = (data.stats && typeof data.stats.recent_errors === 'number') ? data.stats.recent_errors : null;
        const hint = document.getElementById('statRecentHint');
        if (rs !== null && re !== null) {
            hint.textContent = 'Últimas ' + rw + ' líneas (válidas): ' + rs + ' éxito, ' + re + ' error';
        } else hint.textContent = '';
        document.getElementById('statAvg').textContent = (data.stats&&data.stats.avg_response_time)||0 ? data.stats.avg_response_time+' s' : '—';
        document.getElementById('statMax').textContent = (data.stats&&data.stats.max_response_time) ? data.stats.max_response_time+' s' : '—';

        const dot = document.getElementById('statusDot');
        if (data.running) {
            dot.className='sim-status-dot running';
            document.getElementById('statusLabel').textContent='En marcha';
            const wd = data.worker_detail;
            let sub = 'JMeter activo';
            if (wd && wd.logs) {
                sub = 'JMeter · metrics.log ' + wd.logs.metrics_log_lines + ' líneas · dir ' + (wd.logs.dir_writable ? 'escribible' : 'no escribible');
                if (wd.jmeter && wd.jmeter.imported_samples != null) {
                    sub += ' · muestras JTL ' + wd.jmeter.imported_samples;
                }
            }
            document.getElementById('statusSub').textContent = sub;
        } else {
            dot.className='sim-status-dot stopped';
            document.getElementById('statusLabel').textContent='Detenido';
            const jm = data.jmeter || {};
            document.getElementById('statusSub').textContent = jm.imported_samples ? ('Última ejecución JMeter · ' + jm.imported_samples + ' muestras') : 'Sin simulación';
        }

        const ph = document.getElementById('pathHint');
        const hints = [];
        if (data.diagnostics) {
            const d = data.diagnostics;
            if (!d.logs_dir_exists) hints.push('No existe carpeta logs: ' + d.logs_dir);
            if (!d.logs_dir_writable) hints.push('Carpeta logs no escribible desde la UI');
            if (!d.metrics_log_readable && d.metrics_log_size > 0) hints.push('metrics.log no legible (permisos)');
        }
        if (data.running && data.worker_detail && data.worker_detail.logs
            && data.worker_detail.logs.metrics_log_lines === 0
            && (data.stats && (data.stats.total_requests || 0) === 0)) {
            hints.push('Worker en marcha pero sin líneas en metrics.log: ¿URL base alcanzable? (p. ej. http://web). Ver logs: docker compose exec traffic-simulator tail -50 /tmp/traffic_simulator.log');
        }
        if (hints.length) {
            ph.classList.remove('d-none');
            ph.textContent = hints.join(' · ');
        } else {
            ph.classList.add('d-none');
        }

        if (prevRunning === true && data.running === false && !suppressFinishToast) {
            toast('<strong>Simulación finalizada</strong> a las ' + new Date().toLocaleTimeString('es') + ' (duración agotada o detención manual).', 'info', 7000);
        }
        if (suppressFinishToast) {
            suppressFinishToast = false;
        }
        prevRunning = data.running;

        if (data.default_base_url && !document.getElementById('baseUrlInput').dataset.touched) {
            document.getElementById('baseUrlInput').value = data.default_base_url;
        }
        const links = document.getElementById('monitorLinks');
        links.innerHTML = '';
        function addLink(href, label, icon, extraClass) {
            if (!href) return;
            const a = document.createElement('a');
            a.href = href;
            a.target = '_blank';
            a.className = 'btn btn-sm ' + (extraClass || 'btn-outline-secondary');
            a.innerHTML = '<i class="bi ' + icon + '"></i> ' + label;
            links.appendChild(a);
        }
        if (data.monitoring) {
            if (data.monitoring.prometheus) {
                addLink(data.monitoring.prometheus, 'Prometheus', 'bi-graph-up');
            }
            if (data.monitoring.grafana) {
                addLink(data.monitoring.grafana, 'Grafana', 'bi-display');
            }
        }
        if (data.jmeter && data.jmeter.enabled) {
            if (data.jmeter.report_url && data.jmeter.report_ready) {
                addLink(data.jmeter.report_url, 'Dashboard JMeter', 'bi-speedometer2', 'btn-outline-primary');
            }
            addLink(data.jmeter.results_jtl_url, 'JTL', 'bi-filetype-csv');
            addLink(data.jmeter.jmeter_log_url, 'jmeter.log', 'bi-file-text');
        }
        const rp = await fetch('api.php?action=log_preview');
        const jp = await rp.json();
        document.getElementById('logLines').textContent = (jp.total||0) + ' líneas';
        document.getElementById('logPreview').textContent = (jp.lines&&jp.lines.length) ? jp.lines.join('\n') : '(vacío)';
    } catch(e) {
        console.error(e);
    }
}

document.getElementById('baseUrlInput').addEventListener('input', () => document.getElementById('baseUrlInput').dataset.touched='1');

async function probeOnly() {
    const b = document.getElementById('btnProbe');
    b.disabled = true;
    setFlowStatus('Comprobando destino…', 'secondary');
    try {
        await runProbe(true);
    } finally {
        b.disabled = false;
    }
}

async function startSim() {
    const btn = document.getElementById('btnStart');
    const btnProbe = document.getElementById('btnProbe');
    btn.disabled = true;
    btnProbe.disabled = true;
    setFlowStatus('Paso 1/2: comprobando que el destino responde…', 'secondary');
    const pr = await runProbe(false);
    if (pr == null) {
        btn.disabled = false;
        btnProbe.disabled = false;
        return;
    }

    setFlowStatus('Paso 2/2: enviando orden de inicio al worker…', 'primary');
    toast('Destino verificado. <strong>Iniciando simulación</strong>…', 'success', 3500);

    const body = {
        action:'start',
        users: +document.getElementById('usersSlider').value,
        duration: +document.getElementById('durationSlider').value,
        profile: selectedProfile,
        base_url: document.getElementById('baseUrlInput').value.trim(),
        confirm_external: document.getElementById('confirmExternal').checked
    };
    const rf = document.getElementById('routesFile').value.trim();
    if (rf) body.routes_file = rf;

    const res = await fetch('api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    let j = {};
    try { j = await res.json(); } catch(_) {}
    if (res.status === 428 || j.code === 'NEED_CONFIRM_EXTERNAL') {
        toast('Marca la casilla de confirmación para URLs externas.', 'warning');
        btn.disabled = false;
        btnProbe.disabled = false;
        return;
    }
    if (!j.success) {
        toast(j.message || 'Error', 'danger', 7000);
        setFlowStatus('<strong>No se pudo iniciar:</strong> ' + (j.message || 'Error'), 'danger');
        btn.disabled = false;
        btnProbe.disabled = false;
        return;
    }
    const t0 = new Date().toLocaleString('es');
    setFlowStatus('<strong>Simulación en curso</strong> · Orden aceptada a las ' + t0 + '. El worker puede tardar unos segundos en registrar la primera petición.', 'success');
    toast('<strong>Simulación iniciada</strong> a las ' + new Date().toLocaleTimeString('es') + '. ' + (j.message || ''), 'success', 6500);
    poll();
    btnProbe.disabled = false;
}

async function stopSim() {
    const res = await fetch('api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'stop'})});
    let j = {};
    try { j = await res.json(); } catch(_) {}
    if (j.success !== false && res.ok) {
        suppressFinishToast = true;
    }
    setFlowStatus('Simulación detenida. Puedes volver a <strong>Comprobar destino</strong> e <strong>Iniciar</strong> cuando quieras.', 'secondary');
    toast(j.message || ('HTTP '+res.status), (j.success !== undefined ? j.success : res.ok) ? 'success' : 'danger', 6000);
    poll();
}

async function resetLogs() {
    const res = await fetch('api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'reset'})});
    const j = await res.json().catch(()=>({}));
    toast(j.message || '', j.success?'success':'warning');
    poll();
}

polling = setInterval(poll, 2000);
poll();
</script>
</body>
</html>
