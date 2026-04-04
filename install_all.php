<?php
/**
 * Instalador MAESTRO - Ejecuta todos los scripts de instalación
 * URL: https://tinoprop.es/install_all.php
 */

require_once __DIR__ . '/config/database.php';

$scripts = [
    'install.php',
    'install_ajustes.php',
    'install_extras.php',
    'install_tags.php',
    'install_prospectos.php',
    'install_historial_prospectos.php',
    'install_calendario.php',
    'install_custom_fields.php',
    'install_pipelines.php',
    'install_presupuestos.php',
    'install_pagos.php',
    'install_plantillas.php',
    'install_formularios.php',
    'install_encuestas.php',
    'install_funnels.php',
    'install_landing_pages.php',
    'install_campanas.php',
    'install_trigger_links.php',
    'install_reputacion.php',
    'install_social.php',
    'install_conversaciones.php',
    'install_email.php',
    'install_whatsapp.php',
    'install_sms.php',
    'install_chat.php',
    'install_automatizaciones.php',
    'install_workflows.php',
    'install_booking.php',
    'install_marketing_utm.php',
];

$results = [];
$totalOk = 0;
$totalErr = 0;

foreach ($scripts as $script) {
    $path = __DIR__ . '/' . $script;
    if (!file_exists($path)) {
        $results[] = ['script' => $script, 'status' => 'skip', 'msg' => 'Archivo no encontrado'];
        continue;
    }
    try {
        ob_start();
        include $path;
        ob_end_clean();
        $results[] = ['script' => $script, 'status' => 'ok', 'msg' => 'Ejecutado correctamente'];
        $totalOk++;
    } catch (Exception $e) {
        ob_end_clean();
        $results[] = ['script' => $script, 'status' => 'error', 'msg' => $e->getMessage()];
        $totalErr++;
    }
}

if (php_sapi_name() === 'cli') {
    foreach ($results as $r) {
        $icon = $r['status'] === 'ok' ? '✅' : ($r['status'] === 'error' ? '❌' : '⏭️');
        echo "$icon {$r['script']} — {$r['msg']}\n";
    }
    echo "\n✅ $totalOk exitosos | ❌ $totalErr errores | Total: " . count($scripts) . "\n";
    exit($totalErr > 0 ? 1 : 0);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Instalador Maestro — Tinoprop CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #0f172a;
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
        }

        .install-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
        }

        .result-row {
            padding: 8px 16px;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .result-row:last-child {
            border-bottom: none;
        }

        .badge-ok {
            background: #10b981;
        }

        .badge-err {
            background: #ef4444;
        }

        .badge-skip {
            background: #64748b;
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="text-center mb-4">
            <h2><i class="bi bi-database-gear"></i> Instalador Maestro</h2>
            <p class="text-muted">Tinoprop CRM — Todos los módulos</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-center gap-4 mb-4">
                    <div class="text-center">
                        <div class="fs-2 fw-bold text-success"><?= $totalOk ?></div>
                        <small class="text-muted">Exitosos</small>
                    </div>
                    <div class="text-center">
                        <div class="fs-2 fw-bold text-danger"><?= $totalErr ?></div>
                        <small class="text-muted">Errores</small>
                    </div>
                    <div class="text-center">
                        <div class="fs-2 fw-bold text-info"><?= count($scripts) ?></div>
                        <small class="text-muted">Total</small>
                    </div>
                </div>

                <div class="install-card">
                    <?php foreach ($results as $r): ?>
                        <div class="result-row">
                                <?php if ($r['status'] === 'ok'): ?>
                                <span class="badge badge-ok"><i class="bi bi-check-lg"></i></span>
                                <?php elseif ($r['status'] === 'error'): ?>
                                <span class="badge badge-err"><i class="bi bi-x-lg"></i></span>
                                <?php else: ?>
                                <span class="badge badge-skip"><i class="bi bi-skip-forward"></i></span>
                                <?php endif; ?>
                            <strong><?= htmlspecialchars($r['script']) ?></strong>
                            <span class="text-muted ms-auto"><?= htmlspecialchars($r['msg']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalErr === 0): ?>
                    <div class="text-center mt-4">
                        <a href="<?= APP_URL ?>/index.php" class="btn btn-success btn-lg">
                            <i class="bi bi-rocket-takeoff"></i> Ir al CRM
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>