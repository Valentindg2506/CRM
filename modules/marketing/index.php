<?php
$pageTitle = 'Marketing';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3"><i class="bi bi-link-45deg fs-1 text-primary"></i></div>
                <h5>Trigger Links</h5>
                <p class="text-muted small">Crea enlaces rastreables que disparan acciones automaticas cuando los clientes hacen clic.</p>
                <a href="trigger_links.php" class="btn btn-primary"><i class="bi bi-arrow-right"></i> Gestionar</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3"><i class="bi bi-star fs-1 text-warning"></i></div>
                <h5>Reputacion</h5>
                <p class="text-muted small">Gestiona solicitudes de resenas y monitorea la reputacion de tu negocio.</p>
                <a href="reputacion.php" class="btn btn-warning text-white"><i class="bi bi-arrow-right"></i> Gestionar</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3"><i class="bi bi-envelope-paper fs-1 text-info"></i></div>
                <h5>Plantillas Email</h5>
                <p class="text-muted small">Crea y gestiona plantillas de email reutilizables con variables dinamicas.</p>
                <a href="<?= APP_URL ?>/modules/email/plantillas.php" class="btn btn-info text-white"><i class="bi bi-arrow-right"></i> Gestionar</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
