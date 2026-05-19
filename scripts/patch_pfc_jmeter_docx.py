#!/usr/bin/env python3
"""
Inserta en la memoria PFC (COPIAPFC*.docx) el subapartado operativo 5.6 antes del
parrafo que comienza con 'Apache JMeter no es una...'.

Uso (desde la raiz del repo):
  python scripts/patch_pfc_jmeter_docx.py

Crea backup *.bak-jmeter-guide y reescribe el .docx objetivo.
"""
from __future__ import annotations

import secrets
import shutil
import sys
import zipfile
from pathlib import Path


def pid() -> str:
    return secrets.token_hex(4).upper()


def esc(s: str) -> str:
    return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def build_insert() -> str:
    blocks = [
        "Procedimiento de uso.",
        "1) Acceder a la interfaz web del simulador en el equipo host (puerto 8890 por defecto; variables TRAFFIC_SIMULATOR_UI_PORT en desarrollo local con docker-compose.yml y TRAFFIC_SIMULATOR_UI_HOST_PORT en EC2).",
        "2) Destino: indicar la URL base de la prueba (http://web dentro de la red Docker, o la URL HTTP/HTTPS pública de la aplicación desplegada).",
        "3) Carga: elegir el perfil (Normal, Burst o Idle), el número de usuarios concurrentes y la duración en segundos.",
        "4) Ejecución: validar el destino y arrancar la prueba desde la interfaz; el worker ejecuta Apache JMeter en modo CLI y convierte el fichero results.jtl al formato de logs compartidos con la aplicación.",
        "5) Resultados: revisar el estado de la ejecución, la proporción de respuestas correctas y erróneas y la vista previa de metrics.log en la propia interfaz.",
        "6) Observabilidad: abrir Grafana (perfil monitoring), el dashboard principal y la fila Simulador para las series con source=simulator. El tráfico sintético utiliza el mismo recorrido que el tráfico real (métricas expuestas vía metrics.php, Prometheus y Grafana), diferenciado por la etiqueta source respecto de source=app.",
        "7) Informe HTML: al finalizar, si está habilitada la generación del informe, usar el enlace Dashboard JMeter enlazado desde la interfaz (directorio html-report bajo el volumen de logs).",
    ]
    return "".join(
        f'<w:p w14:paraId="{pid()}" w14:textId="77777777" w:rsidR="00E6580A" w:rsidRDefault="00E6580A" w:rsidP="004A512A">'
        f'<w:pPr><w:spacing w:before="80" w:after="120"/></w:pPr>'
        f'<w:r><w:t xml:space="preserve">{esc(b)}</w:t></w:r></w:p>'
        for b in blocks
    )


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    docs = root / "docs"
    doc = docs / "COPIAPFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells_con_diagramas.docx"
    if not doc.is_file():
        print(f"ERROR: no existe {doc}", file=sys.stderr)
        return 1

    work = docs / "_copiapfc_jmeter_edit"
    if work.exists():
        shutil.rmtree(work)
    work.mkdir(parents=True)

    with zipfile.ZipFile(doc) as z:
        z.extractall(work)

    xml_path = work / "word" / "document.xml"
    text = xml_path.read_text(encoding="utf-8")

    marker = "<w:r><w:t>Apache JMeter no es una"
    idx = text.find(marker)
    if idx < 0:
        marker = "<w:r><w:t xml:space=\"preserve\">Apache JMeter no es una"
        idx = text.find(marker)
    if idx < 0:
        shutil.rmtree(work)
        print("ERROR: no se encontro el ancla 'Apache JMeter no es una' en document.xml", file=sys.stderr)
        return 1

    start_p = text.rfind("<w:p ", 0, idx)
    if start_p < 0:
        shutil.rmtree(work)
        print("ERROR: no se pudo localizar el <w:p de apertura", file=sys.stderr)
        return 1

    insert = build_insert()
    window = text[max(0, start_p - 12000) : start_p]
    if "Procedimiento de uso." in window and "TRAFFIC_SIMULATOR_UI_HOST_PORT en EC2" in window:
        shutil.rmtree(work)
        print("WARN: bloque 'Procedimiento de uso' ya presente antes del ancla; no se duplica.")
        return 0

    backup = doc.with_suffix(doc.suffix + ".bak-jmeter-guide")
    shutil.copy2(doc, backup)
    print(f"OK: backup -> {backup.name}")
    xml_path.write_text(new_text, encoding="utf-8")

    tmp = docs / "_copiapfc_jmeter_edit_out.docx"
    if tmp.exists():
        tmp.unlink()

    with zipfile.ZipFile(doc) as zin, zipfile.ZipFile(tmp, "w", compression=zipfile.ZIP_DEFLATED) as zout:
        for name in zin.namelist():
            fp = work / name
            if fp.is_file():
                zout.write(fp, arcname=name)

    shutil.move(tmp, doc)
    shutil.rmtree(work, ignore_errors=True)
    print(f"OK: actualizado {doc.name}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
