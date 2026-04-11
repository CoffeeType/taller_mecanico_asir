#!/bin/bash
set -e

# MySQL Backup Script
# This script creates backups of the MySQL database and stores them in /backup

echo "Starting MySQL backup at $(date)"

# Configuration
BACKUP_DIR="/backup"
MYSQL_HOST="${MYSQL_HOST:-mysql}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-rootpassword}"
MYSQL_DATABASE="${MYSQL_DATABASE:-trabajo_final_php}"
RETENTION_DAYS=7

# Create backup directory if it doesn't exist
mkdir -p "${BACKUP_DIR}"

# Generate backup filename with timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/backup_${MYSQL_DATABASE}_${TIMESTAMP}.sql.gz"

# Create temporary credentials file to avoid exposing password in process list
TMP_CNF=$(mktemp)
trap "rm -f ${TMP_CNF}" EXIT

cat > "${TMP_CNF}" <<EOF
[client]
host=${MYSQL_HOST}
port=${MYSQL_PORT}
user=${MYSQL_USER}
password=${MYSQL_PASSWORD}
ssl=0
EOF

chmod 600 "${TMP_CNF}"

echo "Backing up database: ${MYSQL_DATABASE}"
echo "Backup file: ${BACKUP_FILE}"

# Perform backup
if mysqldump --defaults-extra-file="${TMP_CNF}" --single-transaction --routines --triggers "${MYSQL_DATABASE}" | gzip > "${BACKUP_FILE}"; then
    echo "Backup completed successfully"
    echo "Backup size: $(du -h ${BACKUP_FILE} | cut -f1)"
    
    # Verify backup
    if gunzip -t "${BACKUP_FILE}" 2>/dev/null; then
        echo "Backup verified: OK"
    else
        echo "ERROR: Backup verification failed" >&2
        rm -f "${BACKUP_FILE}"
        exit 1
    fi
else
    echo "ERROR: Backup failed" >&2
    exit 1
fi

# Clean up old backups (keep last 7 days)
echo "Cleaning up backups older than ${RETENTION_DAYS} days..."
find "${BACKUP_DIR}" -name "backup_*.sql.gz" -type f -mtime +${RETENTION_DAYS} -delete 2>/dev/null || true

echo "Backup completed at $(date)"
echo "Current backups:"
ls -lh "${BACKUP_DIR}"/backup_*.sql.gz 2>/dev/null || echo "No backups found"

exit 0