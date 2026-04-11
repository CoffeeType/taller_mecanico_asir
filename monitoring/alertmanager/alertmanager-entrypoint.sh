#!/bin/sh
set -eu

# Ensure template exists
TEMPLATE_PATH="/etc/alertmanager/config/alertmanager.yml"
OUT_PATH="/alertmanager/alertmanager.yml"

if [ ! -f "$TEMPLATE_PATH" ]; then
  echo "ERROR: Template not found at $TEMPLATE_PATH" >&2
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

# Alertmanager fails if these are empty and used in config
if [ -z "$smtp_from" ] || [ -z "$smtp_auth_username" ] || [ -z "$smtp_auth_password" ]; then
  echo "WARNING: SMTP credentials missing. Email alerts will likely fail." >&2
fi

# Replace placeholders in template
# We use ! as sed delimiter to avoid issues with / in paths or passwords
sed \
  -e "s|__SMTP_SMARTHOST__|$smtp_smarthost|g" \
  -e "s|__SMTP_FROM__|$smtp_from|g" \
  -e "s|__SMTP_AUTH_USERNAME__|$smtp_auth_username|g" \
  -e "s|__SMTP_AUTH_PASSWORD__|$smtp_auth_password|g" \
  -e "s|__ALERT_EMAIL_TO__|$alert_email_to|g" \
  -e "s|__SMTP_REQUIRE_TLS__|$smtp_require_tls|g" \
  "$TEMPLATE_PATH" > "$OUT_PATH"

echo "Configuration generated at $OUT_PATH"

# Start Alertmanager
exec /bin/alertmanager \
  --config.file="$OUT_PATH" \
  --storage.path=/alertmanager \
  --web.listen-address=:9093