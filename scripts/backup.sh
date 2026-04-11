#!/bin/bash

# Variables from environment
DB_HOST=${MYSQL_HOST}
DB_USER=${MYSQL_USER}
DB_PASS=${MYSQL_PASSWORD}
DB_NAME=${MYSQL_DATABASE}
BACKUP_DIR="/backup"
RETENTION_DAYS=${BACKUP_RETENTION_DAYS:-7}
DATE=$(date +%Y%m%d_%H%M%S)
FILENAME="${DB_NAME}_${DATE}.sql.gz"

# Run backup
echo "Starting backup for $DB_NAME..."
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_DIR/$FILENAME"

# Cleanup old backups
echo "Cleaning up backups older than $RETENTION_DAYS days..."
find $BACKUP_DIR -type f -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup complete: $FILENAME"

# Sleep for 24 hours if running in a loop, otherwise exit
# For cron-like behavior via environment variable, logic would go here
sleep infinity