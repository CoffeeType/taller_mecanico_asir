FROM php:8.2-apache

ENV SIM_LOG_DIR=/var/www/html/logs

RUN apt-get update \
    && apt-get install -y --no-install-recommends wget libcurl4-openssl-dev pkg-config \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/www/html/logs \
    && chown www-data:www-data /var/www/html/logs

# Shared validation + log stats helpers (copied into image for api.php).
COPY scripts/traffic_simulator_lib.php /opt/inc/traffic_simulator_lib.php

COPY docker/traffic-simulator-ui/public /var/www/html/

RUN chmod -R og+r /var/www/html

EXPOSE 80
