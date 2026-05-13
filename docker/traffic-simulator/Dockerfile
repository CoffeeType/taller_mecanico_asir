FROM php:8.2-cli-bookworm

ARG JMETER_VERSION=5.6.3

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        openjdk-17-jre-headless \
        wget \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    curl -fsSL "https://archive.apache.org/dist/jmeter/binaries/apache-jmeter-${JMETER_VERSION}.tgz" -o /tmp/apache-jmeter.tgz; \
    curl -fsSL "https://archive.apache.org/dist/jmeter/binaries/apache-jmeter-${JMETER_VERSION}.tgz.sha512" -o /tmp/apache-jmeter.tgz.sha512; \
    JMETER_SHA512="$(awk '{print $1}' /tmp/apache-jmeter.tgz.sha512)"; \
    printf '%s  %s\n' "$JMETER_SHA512" /tmp/apache-jmeter.tgz | sha512sum -c -; \
    tar -xzf /tmp/apache-jmeter.tgz -C /opt; \
    ln -s "/opt/apache-jmeter-${JMETER_VERSION}" /opt/apache-jmeter; \
    rm -f /tmp/apache-jmeter.tgz /tmp/apache-jmeter.tgz.sha512

WORKDIR /opt/traffic-simulator

COPY scripts/simulate_traffic.php scripts/run_jmeter_traffic.php scripts/traffic_simulator_lib.php ./scripts/
COPY docker/traffic-simulator/control_router.php ./control_router.php
COPY docker/traffic-simulator/entrypoint.sh /entrypoint.sh

RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

ENV SIM_LOG_DIR=/var/www/html/logs \
    SIMULATOR_CONTROL_PORT=8085 \
    JMETER_HOME=/opt/apache-jmeter \
    SIM_JMETER_BIN=/opt/apache-jmeter/bin/jmeter \
    SIM_JMETER_WORK_DIR=/var/www/html/logs/jmeter \
    SIM_JMETER_HEAP="-Xms128m -Xmx256m -XX:MaxMetaspaceSize=128m" \
    PATH="/opt/apache-jmeter/bin:${PATH}"

EXPOSE 8085

ENTRYPOINT ["/entrypoint.sh"]
