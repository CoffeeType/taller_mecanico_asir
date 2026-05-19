#!/usr/bin/env bash
# Guia operativa breve: simulador Apache JMeter (Traffic UI + Grafana).
# Uso: print-jmeter-usage.sh [URL_TRAFFIC_UI] [URL_GRAFANA_OPCIONAL]
# Ejemplo EC2: print-jmeter-usage.sh "http://ec2-1-2-3-4.compute.amazonaws.com:8890" "http://ec2-1-2-3-4.compute.amazonaws.com:3000"
# Documentacion completa: docs/TRAFFIC_SIMULATOR.md

set -euo pipefail

TRAFFIC_UI="${1:-http://localhost:8890}"
GRAFANA_UI="${2:-}"

echo ""
echo "================================================================"
echo "  Pruebas de carga (Apache JMeter) — guia rapida del operador"
echo "================================================================"
echo ""
echo "  Documentacion: docs/TRAFFIC_SIMULATOR.md (seccion guia rapida)"
echo ""
echo "  URL Traffic UI:  ${TRAFFIC_UI}"
if [[ -n "${GRAFANA_UI}" ]]; then
  echo "  URL Grafana:     ${GRAFANA_UI}"
fi
echo ""
echo "  Pasos en la interfaz web (misma numeracion que la UI):"
echo "    1) Destino: URL base (p. ej. http://web en Docker, o la URL publica de la app)."
echo "    2) Carga: perfil Normal / Burst / Idle; usuarios y duracion."
echo "    3) Ejecutar: Comprobar destino; Iniciar prueba; Detener si hace falta."
echo "    4) Resultados: estado, exitos/errores, vista previa de metrics.log."
echo "    5) Grafana: dashboard principal, fila Simulador (source=simulator)."
echo "       Tras terminar: enlace Dashboard JMeter (informe HTML) si esta habilitado."
echo ""
echo "  Comprobar lineas JMeter en metrics.log (desde host con compose en el repo):"
echo "    docker compose --env-file .env -f docker-compose.aws.yml exec -T traffic-simulator sh -lc \\"
echo "      'grep -c source=simulator \"\${SIM_LOG_DIR:-/var/www/html/logs}/metrics.log\" 2>/dev/null || echo 0'"
echo ""
echo "  Requisito para metricas como en produccion: perfil monitoring activo y"
echo "  volumen ./logs compartido entre web, traffic-simulator y traffic-simulator-ui."
echo "================================================================"
echo ""
