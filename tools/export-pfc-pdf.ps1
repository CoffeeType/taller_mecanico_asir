$ErrorActionPreference = 'Stop'
# Exporta a PDF el COPIAPFC (fuente maestra en docs/). Requiere Microsoft Word instalado.
param(
  [string] $DocxPath = '',
  [string] $PdfPath = ''
)
$repoDocs = Join-Path $PSScriptRoot '..\docs'
$defaultDocx = Join-Path $repoDocs 'COPIAPFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells_con_diagramas.docx'
$defaultPdf = Join-Path $repoDocs 'COPIAPFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells.pdf'
$docx = if ($DocxPath) { (Resolve-Path -LiteralPath $DocxPath).Path } else { (Resolve-Path -LiteralPath $defaultDocx).Path }
$pdf = if ($PdfPath) { $PdfPath } else { $defaultPdf }
$w = New-Object -ComObject Word.Application
$w.Visible = $false
try {
  $d = $w.Documents.Open($docx)
  try {
    if (Test-Path $pdf) { Remove-Item -Force $pdf }
    $d.ExportAsFixedFormat($pdf, 17)
  }
  finally { $d.Close($false) }
}
finally {
  $w.Quit()
  [Runtime.InteropServices.Marshal]::ReleaseComObject($w) | Out-Null
}
Write-Host "PDF: $pdf"
