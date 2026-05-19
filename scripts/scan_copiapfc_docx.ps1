# Comprobaciones rapidas del COPIAPFC (rutas admin/, ortografia basica).
$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path $PSScriptRoot -Parent
$docx = Join-Path $repoRoot 'docs\COPIAPFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells_con_diagramas.docx'
if (-not (Test-Path -LiteralPath $docx)) { throw "No existe: $docx" }

Add-Type -AssemblyName System.IO.Compression.FileSystem
$z = [IO.Compression.ZipFile]::OpenRead($docx)
$sr = New-Object IO.StreamReader($z.GetEntry('word/document.xml').Open(), [Text.Encoding]::UTF8, $true)
$xml = $sr.ReadToEnd()
$sr.Close()
$z.Dispose()

$bad = @()
if ($xml -notmatch 'admin/citas\.php') { $bad += 'Falta admin/citas.php' }
if ($xml -notmatch 'admin/consejos\.php') { $bad += 'Falta admin/consejos.php' }
if ($xml -match 'indiciado') { $bad += 'Queda indiciado' }
if ($xml -match '(?<![a-zA-Z])configuracion(?![a-zA-Z])') { $bad += 'Queda configuracion sin tilde' }

if ($bad.Count) {
  $bad | ForEach-Object { Write-Host "ERROR: $_" -ForegroundColor Red }
  exit 1
}
Write-Host "COPIAPFC OK: $docx" -ForegroundColor Green
exit 0
