-- Actividad HTTP autenticada persistida para métricas app_users_active (Prometheus).
-- Ejecutar una vez en despliegues existentes.

ALTER TABLE users_login
    ADD COLUMN last_seen_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Última petición HTTP con usuario logueado (actualización acotada en app)' AFTER rol;

CREATE INDEX idx_users_login_last_seen_at ON users_login (last_seen_at);
