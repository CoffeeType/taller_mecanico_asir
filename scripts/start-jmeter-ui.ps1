param(
    [int]$TimeoutSec = 600,
    [switch]$NoBuild,
    [switch]$NoOpenBrowser
)

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot = Resolve-Path (Join-Path $ScriptDir "..")
Set-Location $RepoRoot

function Write-Step([string]$Message) {
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Read-EnvValue([string]$Key, [string]$Default = "") {
    $envFile = Join-Path $RepoRoot ".env"
    if (-not (Test-Path $envFile)) {
        return $Default
    }
    foreach ($line in Get-Content $envFile) {
        $clean = $line.Trim()
        if ($clean -eq "" -or $clean.StartsWith("#")) {
            continue
        }
        if ($clean.StartsWith("$Key=")) {
            $value = $clean.Substring($Key.Length + 1).Trim()
            if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
                $value = $value.Substring(1, $value.Length - 2)
            }
            return $value
        }
    }
    return $Default
}

function Test-DockerReady {
    try {
        $psi = [System.Diagnostics.ProcessStartInfo]::new()
        $psi.FileName = "docker"
        $psi.Arguments = "info"
        $psi.RedirectStandardOutput = $true
        $psi.RedirectStandardError = $true
        $psi.UseShellExecute = $false
        $psi.CreateNoWindow = $true
        $process = [System.Diagnostics.Process]::Start($psi)
        $process.WaitForExit()
        return $process.ExitCode -eq 0
    } catch {
        return $false
    }
}

function Start-DockerDesktopIfNeeded {
    if (Test-DockerReady) {
        Write-Host "Docker ya esta disponible." -ForegroundColor Green
        return
    }

    if ($env:OS -ne "Windows_NT") {
        throw "Docker no responde. Arranca el servicio Docker y vuelve a ejecutar este script."
    }

    $candidates = @(@(
        (Join-Path ${env:ProgramFiles} "Docker\Docker\Docker Desktop.exe"),
        (Join-Path ${env:LOCALAPPDATA} "Docker\Docker Desktop\Docker Desktop.exe"),
        (Join-Path ${env:LOCALAPPDATA} "Docker\Docker\Docker Desktop.exe")
    ) | Where-Object { $_ -and (Test-Path $_) })

    if ($candidates.Count -eq 0) {
        throw "Docker Desktop no esta instalado o no se encontro en Program Files/LocalAppData."
    }

    $service = Get-Service -Name "com.docker.service" -ErrorAction SilentlyContinue
    if ($service -and $service.Status -ne "Running") {
        try {
            Write-Host "Arrancando servicio com.docker.service..." -ForegroundColor Yellow
            Start-Service -Name "com.docker.service" -ErrorAction Stop
        } catch {
            Write-Host "No se pudo arrancar com.docker.service automaticamente: $($_.Exception.Message)" -ForegroundColor Yellow
        }
    }

    Write-Host "Arrancando Docker Desktop..." -ForegroundColor Yellow
    Start-Process -FilePath $candidates[0] | Out-Null

    $deadline = (Get-Date).AddSeconds($TimeoutSec)
    while ((Get-Date) -lt $deadline) {
        Start-Sleep -Seconds 5
        if (Test-DockerReady) {
            Write-Host "Docker Desktop listo." -ForegroundColor Green
            return
        }
        Write-Host "Esperando Docker Desktop..." -ForegroundColor DarkGray
    }

    throw "Docker Desktop no estuvo listo tras ${TimeoutSec}s. Si aparece una ventana de Docker Desktop, acepta sus prompts o revisa que WSL2/virtualizacion esten habilitados; despues vuelve a ejecutar este mismo script."
}

function Wait-HttpOk([string]$Url) {
    $deadline = (Get-Date).AddSeconds($TimeoutSec)
    while ((Get-Date) -lt $deadline) {
        try {
            $response = Invoke-WebRequest -UseBasicParsing -TimeoutSec 5 -Uri $Url
            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400) {
                return
            }
        } catch {
            # Service still starting.
        }
        Start-Sleep -Seconds 3
        Write-Host "Esperando UI JMeter en $Url ..." -ForegroundColor DarkGray
    }

    throw "La UI JMeter no respondio en $Url tras ${TimeoutSec}s."
}

Write-Step "Preparando .env"
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "Creado .env desde .env.example." -ForegroundColor Green
} else {
    Write-Host ".env existente: no se modifica." -ForegroundColor Green
}

Write-Step "Comprobando Docker"
Start-DockerDesktopIfNeeded

$uiPort = Read-EnvValue "TRAFFIC_SIMULATOR_UI_PORT" ""
if ($uiPort -eq "") {
    $uiPort = Read-EnvValue "TRAFFIC_SIMULATOR_UI_HOST_PORT" "8890"
}

Write-Step "Levantando app + Apache JMeter UI"
$composeArgs = @("--env-file", ".env", "--profile", "traffic", "up", "-d")
if (-not $NoBuild) {
    $composeArgs += "--build"
}
$composeArgs += @("web", "mysql", "traffic-simulator", "traffic-simulator-ui")

& docker compose @composeArgs
if ($LASTEXITCODE -ne 0) {
    throw "docker compose fallo con codigo $LASTEXITCODE"
}

$uiUrl = "http://localhost:$uiPort"
$healthUrl = "$uiUrl/health.php"

Write-Step "Esperando UI web"
Wait-HttpOk $healthUrl

Write-Host ""
Write-Host "UI Apache JMeter lista: $uiUrl" -ForegroundColor Green
Write-Host "Desde ahi puedes iniciar/parar simulaciones y abrir el Dashboard JMeter generado." -ForegroundColor Green

if (-not $NoOpenBrowser) {
    Start-Process $uiUrl
}
