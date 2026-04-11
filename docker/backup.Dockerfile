FROM alpine:latest

# Install MySQL client and bash
RUN apk add --no-cache mysql-client bash

# Copy the backup script (see below)
COPY docker/backup.sh /backup.sh
RUN chmod +x /backup.sh

# Create backup directory
RUN mkdir -p /backup

CMD ["/bin/sh", "-c", "/backup.sh"]