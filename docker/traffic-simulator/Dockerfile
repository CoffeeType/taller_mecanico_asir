FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        wget \
        ca-certificates \
        libcurl4-openssl-dev \
        pkg-config \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/traffic-simulator

COPY scripts/simulate_traffic.php scripts/traffic_simulator_lib.php ./scripts/
COPY docker/traffic-simulator/control_router.php ./control_router.php
COPY docker/traffic-simulator/entrypoint.sh /entrypoint.sh

RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

ENV SIM_LOG_DIR=/var/www/html/logs \
    SIMULATOR_CONTROL_PORT=8085

EXPOSE 8085

ENTRYPOINT ["/entrypoint.sh"]
