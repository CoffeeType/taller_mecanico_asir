# PowerShell script to verify Docker builds for all services

$composeFiles = @(
    "docker-compose.yml",
    "docker-compose.coolify.yml",
    "docker-compose.coolify.monitoring.yml",
    "docker-compose.coolify.app.yml"
)

$allPassed = $true

Write-Host "Starting build verification..." -ForegroundColor Cyan

foreach ($file in $composeFiles) {
    if (Test-Path $file) {
        Write-Host "Verifying builds in $file..." -ForegroundColor Yellow
        # We use --quiet to reduce noise, but you can remove it if you want more detail
        docker compose -f $file build --pull
        
        if ($LASTEXITCODE -ne 0) {
            Write-Host "FAILED: Build verification for $file failed." -ForegroundColor Red
            $allPassed = $false
        } else {
            Write-Host "PASSED: Build verification for $file succeeded." -ForegroundColor Green
        }
    } else {
        Write-Host "Skipping $file (not found)." -ForegroundColor Gray
    }
}

if ($allPassed) {
    Write-Host "`nAll builds verified successfully!" -ForegroundColor Green
    exit 0
} else {
    Write-Host "`nSome builds failed verification." -ForegroundColor Red
    exit 1
}
