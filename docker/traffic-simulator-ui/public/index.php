<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simulador de tráfico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --sim-bg: #f1f5f9;
            --sim-card: #ffffff;
            --sim-accent: #0d6efd;
            --sim-border: #e2e8f0;
        }
        body { background: var(--sim-bg) !important; }
        .sim-topnav {
            background: var(--sim-card);
            border-bottom: 1px solid var(--sim-border);
            z-index: 1030;
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.04);
        }
        .sim-topnav a {
            color: #334155;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
        }
        .sim-topnav a:hover { background: #e8f0fe; color: var(--sim-accent); }
        .sim-step-card {
            border: 1px solid var(--sim-border);
            border-radius: 12px;
            overflow: hidden;
            background: var(--sim-card);
        }
        .sim-step-card .card-header {
            background: #fafbfc;
            border-bottom: 1px solid var(--sim-border);
            font-weight: 600;
        }
        .sim-status-dot{width:12px;height:12px;border-radius:50%;display:inline-block;}
        .sim-status-dot.running{background:#22c55e;animation:pulse 1.2s infinite;}
        .sim-status-dot.stopped{background:#94a3b8;}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
        .log-preview{font-family:ui-monospace,'Cascadia Code','Courier New',monospace;font-size:.82rem;background:#0f172a;color:#a3e635;border-radius:8px;padding:1rem;max-height:220px;overflow-y:auto;}
        .profile-card{cursor:pointer;border:2px solid transparent;border-radius:10px;transition:border-color .15s, box-shadow .15s;background:#f8fafc;}
        .profile-card:hover,.profile-card.selected{border-color:#0d6efd;background:#e8f0fe;}
        .profile-card:focus-visible{outline:2px solid #0d6efd;outline-offset:2px;}
        .profile-hint { font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }
    </style>
</head>
<body>
<nav class="sim-topnav sticky-top" aria-label="Secciones del simulador">
    <div class="container py-2 d-flex flex-wrap align-items-center gap-1 gap-md-2">
        <span class="small text-muted me-1 d-none d-sm-inline">Ir a:</span>
        <a href="#paso-destino"><span class="text-primary">1</span> Destino</a>
        <span class="text-muted d-none d-md-inline">·</span>
        <a href="#paso-carga"><span class="text-primary">2</span> Carga</a>
        <span class="text-muted d-none d-md-inline">·</span>
        <a href="#paso-ejecucion"><span class="text-primary">3</span> Ejecutar</a>
        <span class="text-muted d-none d-md-inline">·</span>
        <a href="#resultados"><span class="text-primary">4</span> Resultados</a>
        <span class="text-muted d-none d-md-inline">·</span>
        <a href="#enlaces"><span class="text-primary">5</span> Grafana / informes</a>
    </div>
</nav>

<div class="container py-4 pb-5">
    <header class="mb-4">
        <h1 class="h3 mb-2"><i class="bi bi-activity text-primary" aria-hidden="true"></i> Simulador de tráfico JMeter</h1>
        <p class="text-secondary mb-0">Lanza pruebas HTTP desde el contenedor worker y revisa el impacto en los logs compartidos con Prometheus.</p>
    </header>

    <div class="alert alert-secondary small mb-4" role="note">
        Apache JMeter corre en una instancia aparte del sitio público; el control usa un token en servidor. Para URLs fuera de <code>SIM_INTERNAL_HOSTS</code> marca la confirmación y usa solo carga autorizada (intensidad baja recomendada).
    </div>

    <div id="configError" class="alert alert-warning py-2 d-none small mb-3" role="status"></div>
    <div id="pathHint" class="alert alert-danger py-2 d-none small mb-3" role="status"></div>

    <div class="row g-4">
        <div class="col-lg-7">
            <section id="paso-destino" class="card sim-step-card mb-4" aria-labelledby="titulo-paso-1">
                <div class="card-header" id="titulo-paso-1"><span class="badge bg-primary rounded-pill me-2">1</span> Destino de la prueba</div>
                <div class="card-body">
                    <label class="form-label fw-semibold" for="baseUrlInput">URL base</label>
                    <input type="text" class="form-control mb-2" id="baseUrlInput" inputmode="url" autocomplete="url" placeholder="https://ejemplo.com o solo ejemplo.com">
                    <p class="text-muted small mb-3">En Docker Compose suele ser <code>http://web</code> (nombre del servicio de la app en la misma red).</p>
                    <div id="flowStatus" class="alert alert-light border small py-2 mb-3" role="status">Aún no se ha comprobado el destino. Pulsa <strong>Iniciar</strong> (comprueba y arranca) o <strong>Comprobar destino</strong> en el paso 3.</div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmExternal">
                        <label class="form-check-label small" for="confirmExternal">Confirmo enviar solo carga autorizada contra esta URL cuando no sea un host interno listado en <code>SIM_INTERNAL_HOSTS</code></label>
                    </div>
                </div>
            </section>

            <section id="paso-carga" class="card sim-step-card mb-4" aria-labelledby="titulo-paso-2">
                <div class="card-header" id="titulo-paso-2"><span class="badge bg-primary rounded-pill me-2">2</span> Perfil e intensidad</div>
                <div class="card-body">
                    <p class="small text-muted mb-3">El perfil ajusta el comportamiento del plan JMeter. Luego fija usuarios concurrentes y duración.</p>
                    <div class="mb-3">
                        <div class="small fw-semibold text-secondary mb-2">Perfil</div>
                        <div class="row g-2" id="profileCards">
                            <div class="col-md-4">
                                <div class="profile-card text-center p-3 selected" tabindex="0" role="button" data-profile="normal" onclick="selectProfile(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();selectProfile(this);}">
                                    <div class="fs-4" aria-hidden="true">&#128663;</div>
                                    <div class="small fw-semibold">Normal</div>
                                    <div class="profile-hint">Carga equilibrada por defecto</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="profile-card text-center p-3" tabindex="0" role="button" data-profile="burst" onclick="selectProfile(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();selectProfile(this);}">
                                    <div class="fs-4" aria-hidden="true">&#128640;</div>
                                    <div class="small fw-semibold">Burst</div>
                                    <div class="profile-hint">Picos más agresivos</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="profile-card text-center p-3" tabindex="0" role="button" data-profile="idle" onclick="selectProfile(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();selectProfile(this);}">
                                    <div class="fs-4" aria-hidden="true">&#128034;</div>
                                    <div class="small fw-semibold">Idle</div>
                                    <div class="profile-hint">Menos presión, más pausas</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between" for="usersSlider"><span>Usuarios concurrentes</span><strong id="usersVal">3</strong></label>
                        <input type="range" class="form-range" id="usersSlider" min="1" max="20" value="3" oninput="document.getElementById('usersVal').textContent=this.value">
                    </div>
                    <div class="mb-0">
                        <label class="form-label d-flex justify-content-between" for="durationSlider"><span>Duración</span><strong id="durVal">60s</strong></label>
                        <input type="range" class="form-range" id="durationSlider" min="5" max="300" step="5" value="60" oninput="document.getElementById('durVal').textContent=this.value+'s'">
                    </div>
                    <details class="mt-4 border rounded p-3 bg-light">
                        <summary class="fw-semibold small" style="cursor:pointer">Avanzado: rutas JSON opcionales</summary>
                        <p class="small text-muted mt-2 mb-2">Ruta dentro del contenedor del simulador. Déjalo vacío para el plan por defecto.</p>
                        <label class="form-label small mb-1" for="routesFile">Ruta al fichero JSON de rutas</label>
                        <input type="text" class="form-control form-control-sm" id="routesFile" placeholder="/var/www/html/conf/routes.json">
                    </details>
                </div>
            </section>

            <section id="paso-ejecucion" class="card sim-step-card mb-4" aria-labelledby="titulo-paso-3">
                <div class="card-header" id="titulo-paso-3"><span class="badge bg-primary rounded-pill me-2">3</span> Ejecutar o detener</div>
                <div class="card-body">
                    <p class="small text-muted mb-3"><strong>Comprobar destino</strong> valida la URL sin arrancar JMeter. <strong>Iniciar</strong> comprueba y envía la orden al worker. <strong>Detener</strong> solo está activo con simulación en curso.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-outline-primary" id="btnProbe" onclick="probeOnly()" type="button"><i class="bi bi-check2-circle" aria-hidden="true"></i> Comprobar destino</button>
                        <button class="btn btn-primary" id="btnStart" onclick="startSim()" type="button"><i class="bi bi-play-fill" aria-hidden="true"></i> Iniciar prueba</button>
                        <button class="btn btn-danger" id="btnStop" onclick="stopSim()" disabled type="button"><i class="bi bi-stop-fill" aria-hidden="true"></i> Detener</button>
                        <button class="btn btn-outline-secondary" id="btnReset" onclick="resetLogs()" type="button" title="Vacía metrics.log y response_time.log"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Limpiar logs de métricas</button>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section id="resultados" class="mb-4">
                <h2 class="h6 text-uppercase text-muted mb-3">Estado y resultados locales</h2>
                <div class="card sim-step-card shadow-sm mb-3">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <span id="statusDot" class="sim-status-dot stopped" aria-hidden="true"></span>
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

                <div class="card sim-step-card shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold small">Estadísticas (logs locales)</div>
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

                <div class="card sim-step-card shadow-sm mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold small">Últimas líneas (metrics.log)</span><span class="badge bg-secondary" id="logLines"></span>
                    </div>
                    <div class="card-body p-2"><pre class="log-preview mb-0" id="logPreview">—</pre></div>
                </div>
            </section>

            <section id="enlaces" class="card sim-step-card shadow-sm" aria-labelledby="titulo-enlaces">
                <div class="card-header bg-white fw-semibold" id="titulo-enlaces">Monitorización e informes</div>
                <div class="card-body">
                    <p class="small text-muted mb-3">En <strong>Grafana</strong> abre el dashboard principal y baja hasta la fila <strong>Simulador</strong> (métricas con <code>source=simulator</code>). El enlace <strong>Dashboard JMeter</strong> abre el informe HTML nativo cuando JMeter haya terminado de generarlo (puede tardar unos segundos tras finalizar la prueba).</p>
                    <div class="d-grid gap-2" id="monitorLinks"></div>
                </div>
            </section>
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
    el.className = 'alert alert-' + kind + ' border small py-2 mb-3';
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
            document.getElementById('configError').classList.add('d-none');
            document.getElementById('configError').textContent = '';
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
            a.rel = 'noopener noreferrer';
            a.className = 'btn btn-sm ' + (extraClass || 'btn-outline-secondary');
            a.innerHTML = '<i class="bi ' + icon + '" aria-hidden="true"></i> ' + label;
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
    setFlowStatus('Simulación detenida. Puedes volver a <strong>Comprobar destino</strong> e <strong>Iniciar prueba</strong> cuando quieras.', 'secondary');
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
