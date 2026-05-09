# Verificación local: compose, JSON Grafana, sintaxis PHP y tests (Docker PHP CLI).
# Opcional: smoke HTTP si el servicio web está publicado en el host.
# Uso: desde la raíz del repo:  .\scripts\verify_local.ps1
#      o:  pwsh -File scripts/verify_local.ps1

$ErrorActionPreference = "Continue"
$script:Failed = $false

function Fail([string]$Msg) {
    Write-Host "FAIL: $Msg" -ForegroundColor Red
    $script:Failed = $true
}

function Pass([string]$Msg) {
    Write-Host "PASS: $Msg" -ForegroundColor Green
}

function Info([string]$Msg) {
    Write-Host "INFO: $Msg" -ForegroundColor Cyan
}

function Warn([string]$Msg) {
    Write-Host "WARN: $Msg" -ForegroundColor Yellow
}

$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path

Push-Location $RepoRoot
try {
    Write-Host "`n=== verify_local (repo: $RepoRoot) ===`n" -ForegroundColor Cyan

    # 1) docker compose config
    Info "docker compose config"
    docker compose config --quiet 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        docker compose config 2>&1 | Out-Host
        Fail "docker compose config"
    } else {
        Pass "docker compose config"
    }

    # 2) Grafana JSON
    Info "Grafana dashboard JSON"
    $dashFiles = @(
        "monitoring/grafana/dashboards/taller-mecanico-dashboard.json",
        "monitoring/grafana/dashboards_backup/aplicacion.json",
        "monitoring/grafana/dashboards_backup/negocio.json"
    )
    foreach ($df in $dashFiles) {
        $p = Join-Path $RepoRoot $df
        if (-not (Test-Path $p)) {
            Fail "missing $df"
            continue
        }
        try {
            $null = Get-Content -Raw $p | ConvertFrom-Json
            Pass "parse JSON $df"
        } catch {
            Fail "invalid JSON $df : $_"
        }
    }

    # 3) Docker daemon + PHP en contenedor efímero
    Info "Docker daemon"
    docker info 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Warn "Docker no responde; se omiten php -l y tests PHP. Arranca Docker Desktop y vuelve a ejecutar."
    } else {
        Pass "Docker daemon OK"

        $phpFiles = @(
            "metrics.php",
            "monitoring/php-exporter/metrics.php",
            "includes/functions.php",
            "includes/footer.php",
            "login.php",
            "health.php",
            "index.php",
            "tests/test_traffic_simulator_lib.php"
        )
        $vol = "${RepoRoot}:/var/www/html"
        Info "php -l (php:8.2-cli)"
        $lintOk = $true
        foreach ($rel in $phpFiles) {
            docker run --rm -v $vol -w /var/www/html php:8.2-cli php -l $rel
            if ($LASTEXITCODE -ne 0) {
                $lintOk = $false
                Fail "php -l $rel"
            }
        }
        if ($lintOk) { Pass "php -l" }

        if (-not $script:Failed) {
            Info "tests/test_traffic_simulator_lib.php"
            docker run --rm -v $vol -w /var/www/html php:8.2-cli php tests/test_traffic_simulator_lib.php
            if ($LASTEXITCODE -ne 0) {
                Fail "traffic_simulator_lib tests"
            } else {
                Pass "traffic_simulator_lib tests"
            }
        }
    }

    # 4) Smoke HTTP opcional (requiere web escuchando en host)
    $webPort = "8081"
    $envPath = Join-Path $RepoRoot ".env"
    if (Test-Path $envPath) {
        foreach ($line in Get-Content $envPath) {
            if ($line -match '^\s*WEB_PORT\s*=\s*(\d+)') {
                $webPort = $Matches[1]
                break
            }
        }
    }
    $base = "http://127.0.0.1:$webPort"
    Info "Smoke HTTP opcional ($base)"
    try {
        $m = Invoke-WebRequest -Uri "$base/metrics.php" -TimeoutSec 8 -UseBasicParsing -ErrorAction Stop
        if ($m.Content -match "app_users_active") {
            Pass "GET /metrics.php contiene app_users_active"
        } else {
            Warn "GET /metrics.php OK pero sin texto app_users_active (¿migración last_seen_at / BD?)"
        }
    } catch {
        Warn "No se alcanzó $base (¿`docker compose up -d`?). Omitido smoke HTTP."
    }

    try {
        $h = Invoke-WebRequest -Uri "$base/health.php" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
        if ($h.Content -match "ok") {
            Pass "GET /health.php"
        } else {
            Warn "GET /health.php respuesta inesperada"
        }
    } catch {
        Warn "GET /health.php omitido (servicio no arriba)"
    }

    $bookingUrl = "$base/api/citas_api.php?year=$(Get-Date -Format 'yyyy')&month=$(Get-Date -Format 'M')"
    try {
        $api = Invoke-WebRequest -Uri $bookingUrl -TimeoutSec 10 -UseBasicParsing -ErrorAction Stop
        $j = $api.Content | ConvertFrom-Json -ErrorAction SilentlyContinue
        if ($null -ne $j -and $j.PSObject.Properties.Name -contains "booked") {
            Pass "GET citas_api (JSON booked)"
        } else {
            Warn "citas_api respuesta sin clave booked"
        }
    } catch {
        Warn "Smoke citas_api omitido (servicio no arriba o API error)"
    }

    Write-Host ""
    if ($script:Failed) {
        Write-Host "RESULTADO: fallos en verificaciones obligatorias." -ForegroundColor Red
        exit 1
    }
    Write-Host "RESULTADO: verificación completada." -ForegroundColor Green
    exit 0
} finally {
    Pop-Location
}
