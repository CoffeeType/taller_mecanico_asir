    </main>
    <footer class="py-5 mt-auto">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <?php $base_path = isset($base_path) ? $base_path : (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? '../' : ''); ?>
                        <img src="<?= $base_path ?>img/logo.png" alt="Logo" width="50" height="50" style="object-fit: contain;"> Taller Mecánico
                    </h5>
                    <p class="small">Expertos en mecánica integral, diagnosis y preparación ITV. Tu confianza es nuestro motor.</p>
                </div>
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3">Contacto</h5>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i> Calle del Motor, 123, Madrid</li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i> +34 91 123 45 67</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i> info@tallermecanico.com</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3">Síguenos</h5>
                    <div class="d-flex gap-3 mb-3">
                        <a href="#" class="fs-5"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="fs-5"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="fs-5"><i class="bi bi-twitter-x"></i></a>
                    </div>
                    <div class="small">
                        <a href="#" class="text-decoration-none me-3">Aviso Legal</a>
                        <a href="#" class="text-decoration-none">Privacidad</a>
                    </div>
                </div>
            </div>
            <hr class="border-secondary my-4" style="opacity: 0.2;">
            <div class="text-center small">
                <p class="mb-0">&copy; <?= date('Y') ?> Taller Mecánico. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
// Registrar métricas del request antes de cerrar
if (function_exists('logCurrentRequestMetrics')) {
    logCurrentRequestMetrics();
}
?>
</body>
</html>
