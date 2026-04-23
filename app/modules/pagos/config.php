<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

if (!isAdmin()) {
    setFlash('danger', 'Solo administradores pueden acceder a la configuracion de pagos.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $campos = ['empresa_nombre','empresa_cif','empresa_direccion','empresa_email','empresa_telefono','empresa_logo_url','moneda','prefijo_factura'];
    $sets = [];
    $params = [];
    foreach ($campos as $c) {
        $sets[] = "$c = ?";
        $params[] = trim(post($c, ''));
    }
    $sets[] = "iva_defecto = ?";
    $params[] = floatval(post('iva_defecto', 21));

    // Stripe keys - solo actualizar si no estan vacios
    foreach (['stripe_public_key','stripe_secret_key','stripe_webhook_secret'] as $sk) {
        $val = trim(post($sk, ''));
        if (!empty($val)) {
            $sets[] = "$sk = ?";
            $params[] = $val;
        }
    }

    $db->prepare("UPDATE configuracion_pagos SET " . implode(', ', $sets) . " WHERE id = 1")->execute($params);
    setFlash('success', 'Configuracion guardada.');
    header('Location: config.php');
    exit;
}

$pageTitle = 'Configuracion de Pagos';
require_once __DIR__ . '/../../includes/header.php';

$config = $db->query("SELECT * FROM configuracion_pagos WHERE id = 1")->fetch();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
    <a href="webhook_status.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-shield-check"></i> Estado webhook</a>
</div>

<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-building"></i> Datos de la empresa</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre empresa</label>
                        <input type="text" name="empresa_nombre" class="form-control" value="<?= sanitize($config['empresa_nombre'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CIF/NIF</label>
                        <input type="text" name="empresa_cif" class="form-control" value="<?= sanitize($config['empresa_cif'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Direccion</label>
                        <textarea name="empresa_direccion" class="form-control" rows="2"><?= sanitize($config['empresa_direccion'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="empresa_email" class="form-control" value="<?= sanitize($config['empresa_email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefono</label>
                            <input type="text" name="empresa_telefono" class="form-control" value="<?= sanitize($config['empresa_telefono'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">URL del logo</label>
                        <input type="url" name="empresa_logo_url" class="form-control" value="<?= sanitize($config['empresa_logo_url'] ?? '') ?>" placeholder="https://...">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-gear"></i> Facturacion</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Prefijo factura</label>
                            <input type="text" name="prefijo_factura" class="form-control" value="<?= sanitize($config['prefijo_factura'] ?? 'FAC-') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">IVA por defecto %</label>
                            <input type="number" name="iva_defecto" class="form-control" value="<?= $config['iva_defecto'] ?? 21 ?>" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Moneda</label>
                            <select name="moneda" class="form-select">
                                <option value="EUR" <?= ($config['moneda'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                                <option value="USD" <?= ($config['moneda'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                                <option value="GBP" <?= ($config['moneda'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-credit-card"></i> Stripe (opcional)</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Public Key</label>
                        <input type="text" name="stripe_public_key" class="form-control" placeholder="pk_..." value="">
                        <?php if (!empty($config['stripe_public_key'])): ?><small class="text-success"><i class="bi bi-check"></i> Configurada</small><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Secret Key</label>
                        <input type="password" name="stripe_secret_key" class="form-control" placeholder="sk_...">
                        <?php if (!empty($config['stripe_secret_key'])): ?><small class="text-success"><i class="bi bi-check"></i> Configurada</small><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Webhook Secret</label>
                        <input type="password" name="stripe_webhook_secret" class="form-control" placeholder="whsec_...">
                        <?php if (!empty($config['stripe_webhook_secret'])): ?><small class="text-success"><i class="bi bi-check"></i> Configurado</small><?php endif; ?>
                    </div>
                    <small class="text-muted">Deja en blanco para mantener los valores actuales.</small>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Configuracion</button>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
