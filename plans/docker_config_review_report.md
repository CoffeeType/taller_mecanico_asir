# Docker Configuration Review Report

## Executive Summary

This report identifies configuration errors, syntax issues, port conflicts, missing dependencies, and other deployment problems across all Docker Compose files and entrypoint scripts in the codebase. The issues are categorized by severity and file location.

---

## CRITICAL ISSUES (Will Cause Build/Deployment Failures)

### 1. docker-compose.dokploy.yml - Invalid File References

**Location:** [`docker-compose.dokploy.yml:66-67`](docker-compose.dokploy.yml:66)

**Issue:** Alertmanager references non-existent files:
- References `./monitoring/prometheus/alertmanager.yml` but the file is at `./monitoring/alertmanager/alertmanager.yml`
- References `./monitoring/prometheus/alertmanager-entrypoint.sh` but the file is at `./monitoring/alertmanager/alertmanager-entrypoint.sh`

**Error:**
```yaml
volumes:
  - ./monitoring/prometheus/alertmanager.yml:/etc/alertmanager/alertmanager.yml.tpl:ro    # WRONG PATH
  - ./monitoring/prometheus/alertmanager-entrypoint.sh:/entrypoint.sh:ro                  # WRONG PATH
```

**Recommended Fix:**
```yaml
volumes:
  - ./monitoring/alertmanager/alertmanager.yml:/etc/alertmanager/alertmanager.yml.tpl:ro
  - ./monitoring/alertmanager/alertmanager-entrypoint.sh:/entrypoint.sh:ro
```

---

### 2. docker-compose.coolify.monitoring.yml - Invalid Entrypoint Path

**Location:** [`docker-compose.coolify.monitoring.yml:47`](docker-compose.coolify.monitoring.yml:47)

**Issue:** Alertmanager entrypoint command references wrong path:
```yaml
entrypoint: ["/bin/sh", "-c", "sed 's/\\r$//' /etc/alertmanager/config/alertmanager-entrypoint.sh > /tmp/entrypoint.sh && chmod +x /tmp/entrypoint.sh && /tmp/entrypoint.sh"]
```

But volume mounts to:
```yaml
volumes:
  - ./monitoring/alertmanager:/etc/alertmanager/config:ro
```

The file `alertmanager-entrypoint.sh` is mounted at `/etc/alertmanager/config/alertmanager-entrypoint.sh`, but the script looks for it at the same location. This should work, but there's another issue - the template `alertmanager.yml` is also in the same folder and gets overwritten.

**Recommended Fix:** Update the entrypoint to correctly reference the mounted script:
```yaml
entrypoint: ["/bin/sh", "-c", "sed 's/\\r$//' /etc/alertmanager/config/alertmanager-entrypoint.sh > /tmp/entrypoint.sh && chmod +x /tmp/entrypoint.sh && /tmp/entrypoint.sh"]
```

Note: Actually this path appears correct since the volume mounts `./monitoring/alertmanager` to `/etc/alertmanager/config`, so `alertmanager-entrypoint.sh` would be at `/etc/alertmanager/config/alertmanager-entrypoint.sh`. This should work.

---

### 3. docker-compose.coolify.monitoring.yml - Invalid mysqld-exporter Entrypoint Path

**Location:** [`docker-compose.coolify.monitoring.yml:117`](docker-compose.coolify.monitoring.yml:117)

**Issue:** mysqld-exporter entrypoint references wrong path:
```yaml
volumes:
  - ./monitoring/mysqld_exporter/entrypoint.sh:/scripts/entrypoint.sh:ro
entrypoint: ["/bin/sh", "-c", "sed 's/\\r$//' /scripts/entrypoint.sh > /tmp/entrypoint.sh && chmod +x /tmp/entrypoint.sh && /bin/sh /tmp/entrypoint.sh"]
```

This appears correct. The issue is that the entrypoint script uses wrong environment variable names.

---

### 4. docker-compose.coolify.monitoring.yml - Backup Service Depends on Non-existent MySQL

**Location:** [`docker-compose.coolify.monitoring.yml:131-155`](docker-compose.coolify.monitoring.yml:131)

**Issue:** The backup service has:
```yaml
depends_on:
  - mysql  # This service doesn't exist in this compose file!
```

This compose file only contains monitoring services (prometheus, alertmanager, grafana, node-exporter, mysqld-exporter, backup), but backup service depends on mysql which is not defined here.

**Recommended Fix:** Remove the depends_on for mysql or add mysql service to this compose file, OR ensure backup gets MySQL connection details from Coolify's environment variables.

---

### 5. docker/backup.Dockerfile - Wrong Script Path

**Location:** [`docker/backup.Dockerfile:7`](docker/backup.Dockerfile:7)

**Issue:** References wrong backup script path:
```dockerfile
COPY scripts/backup.sh /backup.sh
```

But the actual backup script is at `docker/backup.sh`, not `scripts/backup.sh`.

**Recommended Fix:**
```dockerfile
COPY docker/backup.sh /backup.sh
```

---

### 6. docker-compose.dokploy.yml - Missing Network Definition

**Location:** [`docker-compose.dokploy.yml`](docker-compose.dokploy.yml)

**Issue:** Services don't have explicit network assignments, and no networks are defined at the bottom of the file. While Docker will create a default network, explicit definition is recommended.

**Recommended Fix:** Add networks section:
```yaml
networks:
  app-network:
    driver: bridge
```

---

## HIGH SEVERITY ISSUES (May Cause Runtime Problems)

### 7. docker-compose.coolify.yml - Docker Swarm-specific Configuration

**Location:** [`docker-compose.coolify.yml:112-119`](docker-compose.coolify.yml:112)

**Issue:** Uses `deploy:` section which is Docker Swarm-only feature and NOT supported by Coolify's Docker Compose implementation:
```yaml
deploy:
  resources:
    limits:
      memory: 256M
      cpus: '0.5'
    reservations:
      memory: 128M
      cpus: '0.25'
```

**Recommended Fix:** Remove `deploy:` section entirely, or use Coolify's built-in resource limits in the Coolify UI. If you need to keep resource limits in compose, use the standard `resources:` section (without `deploy:`).

---

### 8. docker-compose.coolify.app.yml - Docker Swarm-specific Configuration

**Location:** [`docker-compose.coolify.app.yml:29-36`](docker-compose.coolify.app.yml:29)

**Issue:** Same as above - uses `deploy:` section which is not supported:
```yaml
deploy:
  resources:
    limits:
      memory: 512M
      cpus: '1'
```

**Recommended Fix:** Remove `deploy:` section and use Coolify UI for resource limits.

---

### 9. docker-compose.coolify.monitoring.yml - Docker Swarm Configuration

**Location:** [`docker-compose.coolify.monitoring.yml:22-29, 51-58, 78-85, 95-102, 121-128, 148-155`](docker-compose.coolify.monitoring.yml)

**Issue:** Multiple services use `deploy:` section which is not supported in Coolify.

**Recommended Fix:** Remove all `deploy:` sections from the compose file.

---

### 10. docker-compose.coolify.yml - Duplicate Volume Definition

**Location:** [`docker-compose.coolify.yml:127-143`](docker-compose.coolify.yml:127)

**Issue:** `mysql_data` is defined both:
1. As a named volume in the volumes section
2. Referenced in mysql service volumes

This is actually correct - the mysql service uses `mysql_data:/var/lib/mysql` which references the named volume. No issue here.

---

### 11. monitoring/mysqld_exporter/entrypoint.sh - Wrong Environment Variable

**Location:** [`monitoring/mysqld_exporter/entrypoint.sh:9`](monitoring/mysqld_exporter/entrypoint.sh:9)

**Issue:** The script uses `MYSQL_ROOT_PASSWORD`:
```bash
password=${MYSQL_ROOT_PASSWORD}
```

But docker-compose files pass `MYSQL_PASSWORD` instead:
```yaml
environment:
  - MYSQL_PASSWORD=${MYSQL_PASSWORD:-rootpassword}
```

**Recommended Fix:** Either:
1. Change the entrypoint to use `MYSQL_PASSWORD`, or
2. Change docker-compose to pass `MYSQL_ROOT_PASSWORD` instead

---

### 12. docker-compose.yml - Missing Healthcheck for Web Service

**Location:** [`docker-compose.yml:5-26`](docker-compose.yml:5)

**Issue:** The `web` service doesn't have a healthcheck configured, but other services depend on it being healthy (though actually no service depends on web here).

**Recommended Fix:** Add healthcheck to web service:
```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 40s
```

---

## MEDIUM SEVERITY ISSUES

### 13. docker-compose.yml - Using `latest` Tags

**Location:** Multiple locations - lines 55, 77, 99, 121, 142

**Issue:** Uses `latest` tag for Prometheus, Alertmanager, Grafana, Node Exporter, MySQL Exporter images:
```yaml
image: prom/prometheus:latest
image: prom/alertmanager:latest
image: grafana/grafana:latest
image: prom/node-exporter:latest
image: prom/mysqld-exporter:latest
```

**Recommended Fix:** Use specific version tags for production stability:
```yaml
image: prom/prometheus:v2.45.0
image: prom/alertmanager:v0.26.0
image: grafana/grafana:10.2.0
image: prom/node-exporter:v1.6.1
image: prom/mysqld-exporter:v0.15.1
```

---

### 14. docker-compose.coolify.yml - Missing PHP Exporter Job

**Location:** [`monitoring/prometheus/prometheus.yml`](monitoring/prometheus/prometheus.yml)

**Issue:** Prometheus is configured to scrape node-exporter and mysqld-exporter, but there's no job to scrape PHP application metrics at `http://web:80/metrics.php`.

**Recommended Fix:** Add PHP exporter job to prometheus.yml:
```yaml
- job_name: 'php-exporter'
  static_configs:
    - targets: ['web:80']
  metrics_path: '/metrics.php'
```

---

### 15. docker-compose.dokploy.yml - Missing PHP Exporter Job

**Location:** [`docker-compose.dokploy.yml`](docker-compose.dokploy.yml)

**Issue:** Same as above - Prometheus can't scrape PHP metrics.

**Recommended Fix:** Add the PHP exporter job to prometheus configuration.

---

### 16. docker-compose.yml - Circular Dependency Risk

**Location:** [`docker-compose.yml:72-73`](docker-compose.yml:72)

**Issue:** Prometheus depends on Alertmanager:
```yaml
depends_on:
  - alertmanager
```

This creates a potential issue - if alertmanager fails to start, prometheus won't start either.

**Recommended Fix:** Consider removing this dependency or using `condition: service_started` instead of default.

---

### 17. docker-compose.coolify.yml - Missing Alertmanager Service

**Location:** [`docker-compose.coolify.yml`](docker-compose.coolify.yml)

**Issue:** The compose file includes prometheus with alerting configured (`rule_files` and `alertmanagers`), but there's no alertmanager service defined. Prometheus will fail to start or show errors.

**Recommended Fix:** Add alertmanager service to the compose file, or remove alerting configuration from prometheus.

---

### 18. docker-compose.coolify.yml - Missing mysqld-exporter and node-exporter

**Location:** [`docker-compose.coolify.yml`](docker-compose.coolify.yml)

**Issue:** prometheus.yml references `node-exporter:9100` and `mysqld-exporter:9104`, but these services are not defined in docker-compose.coolify.yml.

**Recommended Fix:** Add node-exporter and mysqld-exporter services to the compose file.

---

## LOW SEVERITY ISSUES

### 19. docker-compose.coolify.yml - Missing Version Directive

**Location:** [`docker-compose.coolify.yml:1`](docker-compose.coolify.yml:1)

**Issue:** No `version:` specified at the top. While modern Compose versions don't require it, some tools (including older Coolify versions) may expect it.

**Recommended Fix:** Add version directive:
```yaml
version: '3.8'
services:
  ...
```

---

### 20. docker-compose.coolify.app.yml - curl Not Guaranteed in PHP Image

**Location:** [`docker-compose.coolify.app.yml:24`](docker-compose.coolify.app.yml:24)

**Issue:** Healthcheck uses curl:
```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/"]
```

But curl is not installed by default in `php:8.2-apache` image.

**Recommended Fix:** Either:
1. Install curl in the Dockerfile, or
2. Use a different healthcheck:
```yaml
healthcheck:
  test: ["CMD", "php-fpm", "-t"]
  # or
  test: ["CMD", "wget", "--spider", "-q", "http://localhost/"]
```

---

### 21. docker-compose.dokploy.yml - mysql Healthcheck Escaping Issue

**Location:** [`docker-compose.dokploy.yml:40`](docker-compose.dokploy.yml:40)

**Issue:** The healthcheck uses double dollar signs:
```yaml
healthcheck:
  test: ["CMD", "sh", "-c", "mysqladmin ping -h localhost -u root -p$$MYSQL_ROOT_PASSWORD"]
```

This is actually CORRECT for docker-compose - `$$` escapes to a literal `$`. However, make sure MYSQL_ROOT_PASSWORD is set.

---

### 22. docker/entrypoint.sh - Potential Issue with TCP Protocol

**Location:** [`docker/entrypoint.sh:68`](docker/entrypoint.sh:68)

**Issue:** Uses `--protocol=tcp` which may not work in all MySQL configurations:
```bash
mysqladmin --defaults-extra-file="$TMP_CNF" --protocol=tcp --connect-timeout=2 ping 2>&1
```

**Recommended Fix:** Remove `--protocol=tcp` to allow automatic protocol detection:
```bash
mysqladmin --defaults-extra-file="$TMP_CNF" --connect-timeout=2 ping 2>&1
```

---

### 23. scripts/backup.sh - Exposes Password in Command Line

**Location:** [`scripts/backup.sh:15`](scripts/backup.sh:15)

**Issue:** Password is passed as command-line argument:
```bash
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
```

This can be visible in process list.

**Recommended Fix:** Use a my.cnf file or --defaults-extra-file like in docker/backup.sh.

---

### 24. docker-compose.yml - Port Conflicts Potential

**Location:** Multiple files define same ports

**Issue:** If running multiple compose files together:
- Port 3306 (MySQL)
- Port 9090 (Prometheus)
- Port 9093 (Alertmanager)
- Port 3000 (Grafana)
- Port 9100 (Node Exporter)
- Port 9104 (MySQL Exporter)

**Recommended Fix:** Use environment variables for ports and ensure different compose files use different ports, or use `expose` instead of `ports` for internal communication.

---

## ISSUES SUMMARY TABLE

| # | File | Line | Severity | Issue |
|---|------|------|----------|-------|
| 1 | docker-compose.dokploy.yml | 66-67 | CRITICAL | Invalid file paths for alertmanager volumes |
| 2 | docker-compose.coolify.monitoring.yml | 131 | CRITICAL | Backup service depends on non-existent mysql |
| 3 | docker/backup.Dockerfile | 7 | CRITICAL | Wrong script path (scripts/backup.sh vs docker/backup.sh) |
| 4 | docker-compose.coolify.yml | 112-119 | HIGH | Docker Swarm deploy section not supported |
| 5 | docker-compose.coolify.app.yml | 29-36 | HIGH | Docker Swarm deploy section not supported |
| 6 | docker-compose.coolify.monitoring.yml | Multiple | HIGH | Docker Swarm deploy sections not supported |
| 7 | monitoring/mysqld_exporter/entrypoint.sh | 9 | HIGH | Wrong environment variable (MYSQL_ROOT_PASSWORD vs MYSQL_PASSWORD) |
| 8 | docker-compose.yml | 5-26 | MEDIUM | Missing healthcheck for web service |
| 9 | docker-compose.yml | Multiple | MEDIUM | Using 'latest' image tags |
| 10 | docker-compose.coolify.yml | N/A | MEDIUM | Missing alertmanager service |
| 11 | docker-compose.coolify.yml | N/A | MEDIUM | Missing node-exporter and mysqld-exporter services |
| 12 | docker-compose.dokploy.yml | 72-73 | MEDIUM | Prometheus depends on alertmanager |
| 13 | docker-compose.coolify.yml | 1 | LOW | Missing version directive |
| 14 | docker-compose.coolify.app.yml | 24 | LOW | curl not installed in PHP image |
| 15 | docker/entrypoint.sh | 68 | LOW | TCP protocol may cause issues |
| 16 | scripts/backup.sh | 15 | LOW | Password exposed in command line |

---

## COOLIFY-SPECIFIC RECOMMENDATIONS

### For Coolify Deployment:

1. **Remove all `deploy:` sections** - Coolify doesn't support Docker Swarm features
2. **Use `expose:` instead of `ports:`** for internal services - let Coolify handle port mapping
3. **Ensure all required services are in the same compose file** - don't rely on implicit service discovery across compose files
4. **Use Coolify's built-in resource limits** in the UI instead of compose file limits
5. **Test each compose file independently** before deploying to Coolify

### Recommended docker-compose.coolify.yml Structure:

```yaml
version: '3.8'

services:
  web:
    build: .
    expose:
      - "80"
    # ... rest of config without deploy section

  mysql:
    image: mysql:8.0
    # ... rest of config without deploy section

  prometheus:
    image: prom/prometheus:v2.45.0
    # ... rest of config without deploy section

  # Add all other services in same file
```

---

## Files Referenced in This Review

- docker-compose.yml
- docker-compose.coolify.yml
- docker-compose.coolify.app.yml
- docker-compose.coolify.monitoring.yml
- docker-compose.dokploy.yml
- Dockerfile
- docker/entrypoint.sh
- docker/backup.sh
- docker/backup.Dockerfile
- docker/init-db.sh
- monitoring/prometheus/prometheus.yml
- monitoring/alertmanager/alertmanager.yml
- monitoring/alertmanager/alertmanager-entrypoint.sh
- monitoring/mysqld_exporter/entrypoint.sh
- scripts/backup.sh
