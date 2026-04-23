<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $proveedor = post('proveedor','twilio');
    $telefono = trim(post('telefono_remitente'));
    $activo = post('activo') ? 1 : 0;
    $sets = "proveedor=?, telefono_remitente=?, activo=?";
    $params = [$proveedor, $telefono, $activo];
    $sid = trim(post('api_sid'));
    $token = trim(post('api_token'));
    if ($sid) { $sets .= ", api_sid=?"; $params[] = $sid; }
    if ($token) { $sets .= ", api_token=?"; $params[] = $token; }
    $db->prepare("UPDATE sms_config SET $sets WHERE id = 1")->execute($params);
    setFlash('success', 'Configuracion SMS guardada.');
    header('Location: config.php');
    exit;
}

$pageTitle = 'Configuracion SMS';
require_once __DIR__ . '/../../includes/header.php';
$cfg = $db->query("SELECT * FROM sms_config WHERE id = 1")->fetch();
?>

<div class="d-flex mb-4"><a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a></div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-phone"></i> Proveedor SMS</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Proveedor</label>
                        <select name="proveedor" class="form-select">
                            <option value="twilio" <?= ($cfg['proveedor']??'') === 'twilio' ? 'selected' : '' ?>>Twilio</option>
                            <option value="vonage" <?= ($cfg['proveedor']??'') === 'vonage' ? 'selected' : '' ?>>Vonage (Nexmo)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API SID / Key</label>
                        <input type="text" name="api_sid" class="form-control" placeholder="Dejar vacio para mantener actual">
                        <?php if (!empty($cfg['api_sid'])): ?><small class="text-success"><i class="bi bi-check"></i> Configurado</small><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Token / Secret</label>
                        <input type="password" name="api_token" class="form-control" placeholder="Dejar vacio para mantener actual">
                        <?php if (!empty($cfg['api_token'])): ?><small class="text-success"><i class="bi bi-check"></i> Configurado</small><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefono remitente</label>
                        <input type="text" name="telefono_remitente" class="form-control" value="<?= sanitize($cfg['telefono_remitente'] ?? '') ?>" placeholder="+34...">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="activo" class="form-check-input" value="1" <?= ($cfg['activo'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label">SMS activo</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
