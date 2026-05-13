$ErrorActionPreference = 'Stop'
# Actualiza el PFC DOCX: diagrama JMeter, índice de tablas, redacción, anexo C (sin romper OOXML).
$utf8 = New-Object System.Text.UTF8Encoding $false
$docx = (Resolve-Path (Join-Path $PSScriptRoot '..\docs\PFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells_con_diagramas.docx')).Path

function Read-DocXml([string]$Path) {
  Add-Type -AssemblyName System.IO.Compression.FileSystem
  $z = [IO.Compression.ZipFile]::OpenRead($Path)
  try {
    $r = New-Object IO.StreamReader($z.GetEntry('word/document.xml').Open(), $utf8, $false)
    $t = $r.ReadToEnd()
    $r.Close()
    return $t
  }
  finally { $z.Dispose() }
}

function Write-DocXml([string]$Path, [string]$Xml) {
  Add-Type -AssemblyName System.IO.Compression.FileSystem
  $z = [IO.Compression.ZipFile]::Open($Path, 'Update')
  try {
    $e = $z.GetEntry('word/document.xml')
    $b = $utf8.GetBytes($Xml)
    $s = $e.Open()
    try { $s.SetLength(0); $s.Write($b, 0, $b.Length) }
    finally { $s.Dispose() }
  }
  finally { $z.Dispose() }
}

$xml = Read-DocXml $docx

# --- Figura JMeter (sustituir diagrama con uiSimulador si existe) ---
if ($xml.IndexOf('uiSimulador') -ge 0) {
  $u = $xml.IndexOf('uiSimulador')
  $m = $xml.LastIndexOf('<w:p>', $xml.LastIndexOf('```mermaid', $u))
  $c = $xml.IndexOf('```</w:t>', $u)
  $end = $xml.IndexOf('</w:p>', $c) + 6
  $Lp = '<w:p><w:r><w:rPr><w:rFonts w:ascii="Courier New" w:hAnsi="Courier New" /><w:sz w:val="18" /></w:rPr><w:t xml:space="preserve">'
  $Rp = '</w:t></w:r></w:p>'
  $lines = @(
    'flowchart TD',
    'operador["Operador técnico"] --&gt; preparar["Preparar plan JMX"]',
    'preparar --&gt; rutas["Rutas de la aplicación"]',
    'rutas --&gt; jmeter["Apache JMeter CLI"]',
    'jmeter --&gt; appWeb["Aplicación PHP Apache"]',
    'appWeb --&gt; accessLogs["logs de peticiones y tiempos"]',
    '    jmeter --&gt; jtl["results.jtl y jmeter.log"]',
    'jtl --&gt; metricsFile["metrics.log con origen de prueba"]',
    '    accessLogs --&gt; prometheus["Prometheus lee metrics.php"]; metricsFile --&gt; prometheus',
    'prometheus --&gt; grafana["Grafana muestra efecto de la prueba"]'
  )
  $newBlock = ($lines | ForEach-Object { $Lp + $_ + $Rp }) -join ''
  $xml = $xml.Substring(0, $m) + $newBlock + $xml.Substring($end)
  Write-Host 'Diagrama JMeter (Fig. carga): actualizado.'
}

$xml = $xml.Replace(
  'Fuente: elaboración propia a partir del funcionamiento del contenedor traffic-simulator.',
  'Fuente: elaboración propia a partir del flujo de pruebas con Apache JMeter.'
)

# --- Índice de tablas: títulos 9-12 ---
$xml = $xml.Replace('<w:t xml:space="preserve">Tabla 9. Recursos utilizados.</w:t>', '<w:t xml:space="preserve">Tabla 9. Requisitos no funcionales.</w:t>')
$xml = $xml.Replace('<w:t xml:space="preserve">Tabla 10. Presupuesto anual orientativo.</w:t>', '<w:t xml:space="preserve">Tabla 10. Checklist de despliegue.</w:t>')
$xml = $xml.Replace('<w:t xml:space="preserve">Tabla 11. Grado de consecución de objetivos.</w:t>', '<w:t xml:space="preserve">Tabla 11. Comparativa de escenarios de despliegue.</w:t>')
$xml = $xml.Replace('<w:t xml:space="preserve">Tabla 12. Problemas encontrados y resolución.</w:t>', '<w:t xml:space="preserve">Tabla 12. Plan de mantenimiento preventivo.</w:t>')

# --- Índice: sustituir el párrafo completo de Tabla 13 por 8 párrafos (13-20), clonando el de Tabla 8 ---
$i8 = $xml.IndexOf('Tabla 8. M')
$i13 = $xml.IndexOf('Tabla 13. Propuestas de mejora.')
if ($i8 -ge 0 -and $i13 -ge 0) {
  $s8 = $xml.LastIndexOf('<w:p>', $i8)
  $e8 = $xml.IndexOf('</w:p>', $i8) + 6
  $tpl = $xml.Substring($s8, $e8 - $s8)
  $gt = $tpl.IndexOf('>', $tpl.IndexOf('<w:t')) + 1
  $lt = $tpl.IndexOf('</w:t>')
  $oldText = $tpl.Substring($gt, $lt - $gt)
  $s13 = $xml.LastIndexOf('<w:p>', $i13)
  $e13 = $xml.IndexOf('</w:p>', $i13) + 6
  $titles = @(
    'Tabla 13. Recursos utilizados.',
    'Tabla 14. Presupuesto anual orientativo.',
    'Tabla 15. Matriz de pruebas funcionales y técnicas.',
    'Tabla 16. Guion de demostración.',
    'Tabla 17. Grado de consecución de objetivos.',
    'Tabla 18. Problemas encontrados y resolución.',
    'Tabla 19. Propuestas de mejora.',
    'Tabla 20. Inventario de artefactos del repositorio.'
  )
  $newBlock = ($titles | ForEach-Object { $tpl.Replace($oldText, $_) }) -join ''
  $xml = $xml.Substring(0, $s13) + $newBlock + $xml.Substring($e13)
  Write-Host 'TOC Tabla 13 bloque: reemplazado por tablas 13-20.'
}

$xml = $xml.Replace(
  'En AWS se publican solo en loopback y se accede por túnel o proxy seguro.',
  'Con MONITORING_UI_HOST_BIND=127.0.0.1 las UIs quedan solo en loopback (túnel SSH o proxy). Con el compose AWS de ejemplo (bind 0.0.0.0) deben protegerse con Security Group o proxy; no basta con asumir loopback.'
)
$xml = $xml.Replace('pruebas de carga sintética controladas', 'pruebas de carga controladas')
$xml = $xml.Replace('scripts/ (despliegue AWS, verificacion, JMeter, DB)', 'scripts/ (despliegue AWS, verificación, JMeter y base de datos)')
$xml = $xml.Replace('scripts/ (AWS deploy, verify, JMeter, DB tools)', 'scripts/ (despliegue AWS, verificación, JMeter y base de datos)')

$patRow = '<w:tr>(?:(?!</w:tr>).)*?<w:t xml:space="preserve">JMETER_VERSION, SIM_JMETER_HEAP, SIM_SSL_VERIFY</w:t>(?:(?!</w:tr>).)*?</w:tr>'
$xml = [regex]::Replace($xml, $patRow, '', 1)
$xml = $xml.Replace('JMETER_VERSION, SIM_JMETER_HEAP, SIM_JMETER_WORK_DIR', 'JMETER_VERSION, SIM_JMETER_HEAP, SIM_JMETER_WORK_DIR, SIM_SSL_VERIFY')
$xml = $xml.Replace(
  'Parámetros de JMeter: versión, memoria de Java y directorio de trabajo para resultados.',
  'Parámetros de JMeter: versión, memoria de Java, directorio de trabajo, verificación TLS y entorno de pruebas controladas.'
)

# --- Tras Tabla 18 (sección 8.2): dificultades SMTP y CI/CD (solo cuerpo, no índice) ---
$marker82 = '>8.2. Problemas encontrados</w:t>'
$ix82 = $xml.IndexOf($marker82)
if ($ix82 -ge 0 -and $xml.IndexOf('8.2.1. Dificultades técnicas (SMTP externo y CI/CD)') -lt 0) {
  $tblTitle = 'Tabla 18. Problemas encontrados'
  $t0 = $xml.IndexOf($tblTitle, $ix82)
  if ($t0 -ge 0) {
    $tblOpen = $xml.IndexOf('<w:tbl>', $t0)
    if ($tblOpen -ge 0) {
      $tblClose = $xml.IndexOf('</w:tbl>', $tblOpen)
      if ($tblClose -ge 0) {
        $insAt = $tblClose + '</w:tbl>'.Length
        $pRun24 = '<w:rPr><w:rFonts w:ascii="Arial" w:cs="Arial" w:eastAsia="Arial" w:hAnsi="Arial" /><w:sz w:val="24" /><w:szCs w:val="24" /></w:rPr>'
        $pHead = '<w:p><w:pPr><w:spacing w:after="120" w:before="120" w:line="360" /><w:jc w:val="left" /></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Arial" w:cs="Arial" w:eastAsia="Arial" w:hAnsi="Arial" /><w:b /><w:bCs /><w:sz w:val="26" /><w:szCs w:val="26" /></w:rPr><w:t xml:space="preserve">8.2.1. Dificultades técnicas (SMTP externo y CI/CD)</w:t></w:r></w:p>'
        $pPrBoth = '<w:pPr><w:spacing w:after="120" w:before="80" w:line="360" /><w:jc w:val="both" /></w:pPr>'
        $bodySmtp = 'La integración de notificaciones por correo con un SMTP externo (Alertmanager) presentó fricción operativa: muchos proveedores obligan a STARTTLS en el puerto 587; en Amazon SES las credenciales SMTP no coinciden con las claves de acceso IAM y hace falta verificar remitente y destinatarios mientras la cuenta siga en sandbox. Alertmanager no ofrece un botón de prueba en su interfaz web, por lo que la validación exige disparar una alerta sintética o usar el panel admin de la aplicación cuando el stack está levantado. Si faltan variables obligatorias, el entrypoint puede arrancar en modo noop para no bloquear el despliegue, lo que a veces confunde hasta rellenar ALERT_EMAIL_TO, SMTP_SMARTHOST y SMTP_FROM.'
        $bodyCi = 'Respecto al CI/CD con GitHub Actions, los workflows de verificación de builds añadían coste de mantenimiento (imágenes base, Buildx en runners, secretos para despliegue) y fallos intermitentes por red o límites de tiempo. El repositorio ha retirado la integración con GitHub Actions; la comprobación de imágenes pasa a hacerse en local con docker compose build según el fichero compose que corresponda al entorno.'
        $pSmtp = '<w:p>' + $pPrBoth + '<w:r>' + $pRun24 + '<w:t xml:space="preserve">' + $bodySmtp + '</w:t></w:r></w:p>'
        $pCi = '<w:p>' + $pPrBoth + '<w:r>' + $pRun24 + '<w:t xml:space="preserve">' + $bodyCi + '</w:t></w:r></w:p>'
        $insert = $pHead + $pSmtp + $pCi
        $xml = $xml.Insert($insAt, $insert)
        Write-Host 'Apartado 8.2.1 (SMTP y CI/CD): insertado tras Tabla 18.'
      }
    }
  }
}

Write-DocXml $docx $xml
Write-Host "Listo: $docx"
