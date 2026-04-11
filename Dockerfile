FROM php:8.2-apache

# Defaults to avoid Apache PassEnv warnings when variables are not set.
# (Coolify/Compose will override these at runtime.)
ENV DB_HOST= \
    DB_PORT= \
    DB_NAME= \
    DB_USER= \
    DB_PASS= \
    APP_ENV= \
    APP_DEBUG= \
    DEPLOY_TARGET= \
    MYSQL_HOST= \
    MYSQL_PORT= \
    MYSQL_DATABASE= \
    MYSQL_USER= \
    MYSQL_PASSWORD=

# Instalar extensiones PHP necesarias y mysql-client para entrypoint
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    curl \
    wget \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Configurar Apache para permitir .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Evitar warning de FQDN en logs
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf && a2enconf servername

# Expose environment variables to Apache/PHP
RUN echo "PassEnv DB_HOST DB_PORT DB_NAME DB_USER DB_PASS APP_ENV APP_DEBUG DEPLOY_TARGET MYSQL_HOST MYSQL_PORT MYSQL_DATABASE MYSQL_USER MYSQL_PASSWORD" >> /etc/apache2/conf-enabled/expose-env.conf

# Copiar código de la aplicación
COPY . /var/www/html/

# Crear directorios necesarios y configurar permisos
RUN echo "Creating directories and setting permissions..." && \
    mkdir -p /var/www/html/logs /var/www/html/cache /var/www/html/assets/images \
    && touch /var/www/html/import_log.txt \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html && \
    echo "Directories created and permissions set."

# Copiar endpoint de métricas
COPY monitoring/php-exporter/metrics.php /var/www/html/metrics.php
RUN chown www-data:www-data /var/www/html/metrics.php \
    && chmod 644 /var/www/html/metrics.php

# Copiar y configurar entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
# Convertir CRLF a LF (formato Unix) y asegurar permisos de ejecución
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

# Script de backup (usado por despliegues que ejecutan este contenedor con CMD/entrypoint alternativo)
COPY docker/backup.sh /usr/local/bin/backup.sh
RUN sed -i 's/\r$//' /usr/local/bin/backup.sh && \
    chmod +x /usr/local/bin/backup.sh

# Exponer puerto 80
EXPOSE 80

# Usar entrypoint para esperar a MySQL
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
