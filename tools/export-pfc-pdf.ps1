$ErrorActionPreference = 'Stop'
$docx = 'c:\Users\anton\Documents\GitHub\taller_mecanico_asir\docs\PFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells_con_diagramas.docx'
$pdf = 'c:\Users\anton\Documents\GitHub\taller_mecanico_asir\docs\PFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells.pdf'
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
