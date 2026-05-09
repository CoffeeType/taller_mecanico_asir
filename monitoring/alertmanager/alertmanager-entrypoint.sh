#!/bin/sh
set -eu

OUT_PATH="/alertmanager/alertmanager.yml"

# Resolve template (compose.aws may mount at config path or legacy .tpl path)
TEMPLATE_PATH=""
for cand in /etc/alertmanager/config/alertmanager.yml /etc/alertmanager/alertmanager.yml.tpl; do
  if [ -f "$cand" ]; then
    TEMPLATE_PATH="$cand"
    break
  fi
done

# Minimal valid config when email is not configured (Alertmanager rejects empty email `to`, missing SMTP, etc.)
write_noop_config() {
  cat > "$OUT_PATH" <<'EOF'
route:
  receiver: 'noop'
  group_wait: 30s
  group_interval: 5m
  repeat_interval: 1h
receivers:
  - name: 'noop'
EOF
}

# Email path only when recipient + minimum SMTP globals are set (avoids config validation errors on first boot)
email_ready() {
  [ -n "${ALERT_EMAIL_TO:-}" ] && [ -n "${SMTP_SMARTHOST:-}" ] && [ -n "${SMTP_FROM:-}" ]
}

if ! email_ready; then
  if [ -z "${ALERT_EMAIL_TO:-}" ]; then
    echo "INFO: ALERT_EMAIL_TO unset; using noop Alertmanager config (no email)." >&2
  else
    echo "WARNING: Email alerts requested but SMTP_SMARTHOST/SMTP_FROM incomplete; using noop config until configured." >&2
  fi
  write_noop_config
  echo "Configuration written at $OUT_PATH"
  set -- /bin/alertmanager \
    --config.file="$OUT_PATH" \
    --storage.path=/alertmanager \
    --web.listen-address=:9093
  if [ -n "${ALERTMANAGER_EXTERNAL_URL:-}" ]; then
    set -- "$@" \
      --web.external-url="${ALERTMANAGER_EXTERNAL_URL}" \
      --web.route-prefix="${ALERTMANAGER_ROUTE_PREFIX:-/}"
  fi
  exec "$@"
fi

if [ -z "$TEMPLATE_PATH" ] || [ ! -f "$TEMPLATE_PATH" ]; then
  echo "ERROR: Template not found (expected /etc/alertmanager/config/alertmanager.yml or alertmanager.yml.tpl)" >&2
  exit 1
fi

# Function to escape special characters for sed
escape_sed_repl() {
  printf '%s' "$1" | sed -e 's/[\\/&]/\\&/g'
}

# Values from environment variables
smtp_smarthost=$(escape_sed_repl "${SMTP_SMARTHOST:-smtp.gmail.com:587}")
smtp_from=$(escape_sed_repl "${SMTP_FROM:-}")
smtp_auth_username=$(escape_sed_repl "${SMTP_AUTH_USERNAME:-}")
smtp_auth_password=$(escape_sed_repl "${SMTP_AUTH_PASSWORD:-}")
smtp_require_tls="${SMTP_REQUIRE_TLS:-true}"
alert_email_to=$(escape_sed_repl "${ALERT_EMAIL_TO:-}")

if [ -z "$smtp_from" ] || [ -z "$smtp_auth_username" ] || [ -z "$smtp_auth_password" ]; then
  echo "WARNING: SMTP auth credentials partly missing; email delivery may fail until .env is complete." >&2
fi

# Replace placeholders in template (use ! as sed delimiter)
sed \
  -e "s|__SMTP_SMARTHOST__|$smtp_smarthost|g" \
  -e "s|__SMTP_FROM__|$smtp_from|g" \
  -e "s|__SMTP_AUTH_USERNAME__|$smtp_auth_username|g" \
  -e "s|__SMTP_AUTH_PASSWORD__|$smtp_auth_password|g" \
  -e "s|__ALERT_EMAIL_TO__|$alert_email_to|g" \
  -e "s|__SMTP_REQUIRE_TLS__|$smtp_require_tls|g" \
  "$TEMPLATE_PATH" > "$OUT_PATH"

echo "Configuration generated at $OUT_PATH"

set -- /bin/alertmanager \
  --config.file="$OUT_PATH" \
  --storage.path=/alertmanager \
  --web.listen-address=:9093
if [ -n "${ALERTMANAGER_EXTERNAL_URL:-}" ]; then
  set -- "$@" \
    --web.external-url="${ALERTMANAGER_EXTERNAL_URL}" \
    --web.route-prefix="${ALERTMANAGER_ROUTE_PREFIX:-/}"
fi
exec "$@"
