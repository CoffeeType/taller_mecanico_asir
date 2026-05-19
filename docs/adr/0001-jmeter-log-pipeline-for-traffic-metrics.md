# JMeter and HTTP access logs for traffic metrics

We separate **tráfico de aplicación** from **tráfico simulado** by writing HTTP access lines to metrics logs with `source=app` or `source=simulator`, then scraping them via the PHP exporter into Prometheus and Grafana. JMeter runs in Docker and imports JTL results into the same log format instead of pushing synthetic counters from application code.

**Considered options:** (1) in-app Prometheus counters on every request; (2) Pushgateway for batch jobs; (3) JMeter-only metrics without labeling real user traffic. We chose the log pipeline because it reuses one observability path for both real and load-test traffic, keeps the PHP app free of simulator-specific instrumentation, and matches the PFC focus on ops monitoring with minimal app changes.

**Consequences:** Operators must understand `source=` labels when interpreting dashboards; log volume grows with load tests; separating metrics requires consistent log formatting in `metrics_logger` and the traffic simulator import path.
