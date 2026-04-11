<?php
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DEBUG: Log access attempt for diagnosis
error_log('[DEBUG] test-alert-email.php accessed. Session data: ' . json_encode($_SESSION));

if (!isAdmin()) {
    error_log('[DEBUG] isAdmin() returned false. User role: ' . ($_SESSION['user_role'] ?? 'not set'));
    redirect('../index.php');
}

function sendAlertmanagerTestAlert(array $payload) {
    $url = 'http://alertmanager:9093/api/v2/alerts';
    $json = json_encode([$payload], JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return [false, 'No se pudo serializar el JSON del test.'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            return [false, "Error enviando alerta (cURL): $err"];
        }

        if ($code < 200 || $code >= 300) {
            return [false, "Alertmanager respondió HTTP $code: " . trim($body)];
        }

        return [true, null];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $json,
            'timeout' => 10,
        ]
    ]);

    $body = @file_get_contents($url, false, $context);
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('/^HTTP\\/\\S+\\s+(\\d{3})/', $h, $m)) {
                $code = (int)$m[1];
                break;
            }
        }
    }

    if ($body === false) {
        return [false, 'No se pudo contactar con Alertmanager (sin ext-curl).'];
    }
    if ($code < 200 || $code >= 300) {
        return [false, "Alertmanager respondió HTTP $code: " . trim($body)];
    }
    return [true, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlashMessage('error', 'CSRF token inválido. Recarga la página e inténtalo de nuevo.');
    } else {
        $to = trim($_POST['to'] ?? '');
        $severity = trim($_POST['severity'] ?? 'warning');
        if ($severity !== 'warning' && $severity !== 'critical') {
            $severity = 'warning';
        }

        if ($to !== '' && !validarEmail($to)) {
            setFlashMessage('error', 'Email inválido.');
        } else {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $payload = [
                'labels' => [
                    'alertname' => 'ManualTestEmail',
                    'severity' => $severity,
                    'service' => 'taller-mecanico',
                    'component' => 'monitoring',
                    'instance' => 'manual',
                ],
                'annotations' => [
                    'summary' => 'Email de prueba (manual)',
                    'description' => 'Alerta de prueba enviada manualmente desde el panel de administración.',
                ],
                'startsAt' => $now->format(DATE_RFC3339),
                'endsAt' => $now->modify('+10 minutes')->format(DATE_RFC3339),
            ];

            if ($to !== '') {
                $payload['labels']['alert_to'] = $to;
            }

            [$ok, $err] = sendAlertmanagerTestAlert($payload);
            if ($ok) {
                setFlashMessage('success', $to !== ''
                    ? "Alerta de prueba enviada. Deberías recibir un email en: $to"
                    : "Alerta de prueba enviada. Deberías recibir un email en el destinatario configurado en Alertmanager.");
            } else {
                setFlashMessage('error', $err ?: 'No se pudo enviar la alerta de prueba.');
            }
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><i class="bi bi-envelope-check me-2"></i>Test de Notificaciones (Alertmanager)</h1>
        <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    </div>

    <?php $flashSuccess = getFlashMessage('success'); $flashError = getFlashMessage('error'); ?>
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted mb-3">
                Esto envía una alerta sintética directamente a Alertmanager para comprobar el envío de emails.
                Si dejas el email vacío, se usará el destinatario configurado en `.env` / Alertmanager.
            </p>

            <form method="POST" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <div class="col-md-6">
                    <label class="form-label">Email destino (opcional)</label>
                    <input type="email" name="to" class="form-control" placeholder="tuemail@dominio.com">
                    <div class="form-text">Requiere que Alertmanager esté configurado para usar el label <code>alert_to</code>.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Severidad</label>
                    <select name="severity" class="form-select">
                        <option value="warning" selected>warning</option>
                        <option value="critical">critical</option>
                    </select>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Enviar email de prueba
                    </button>
                    <a class="btn btn-outline-secondary ms-2" target="_blank" href="http://localhost:9093">
                        Abrir Alertmanager
                    </a>
                    <a class="btn btn-outline-secondary ms-2" target="_blank" href="http://localhost:9090/alerts">
                        Abrir Prometheus Alerts
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

