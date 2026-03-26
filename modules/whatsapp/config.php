<?php
$pageTitle = 'Configuracion WhatsApp';
require_once __DIR__ . '/../../includes/header.php';

if (!isAdmin()) {
    setFlash('danger', 'No tienes permisos para acceder a esta seccion.');
    header('Location: ' . APP_URL . '/modules/whatsapp/index.php');
    exit;
}

$db = getDB();

// Guardar configuracion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $phoneNumberId = post('phone_number_id');
    $accessToken = post('access_token');
    $webhookVerifyToken = post('webhook_verify_token');
    $activo = post('activo') ? 1 : 0;

    // Verificar si ya existe configuracion
    $existing = $db->query("SELECT id FROM whatsapp_config LIMIT 1")->fetch();

    if ($existing) {
        $stmt = $db->prepare("
            UPDATE whatsapp_config SET
                phone_number_id = ?,
                access_token = ?,
                webhook_verify_token = ?,
                activo = ?,
                updated_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$phoneNumberId, $accessToken, $webhookVerifyToken, $activo, currentUserId(), $existing['id']]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO whatsapp_config (phone_number_id, access_token, webhook_verify_token, activo, updated_by, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$phoneNumberId, $accessToken, $webhookVerifyToken, $activo, currentUserId()]);
    }

    registrarActividad('actualizar', 'whatsapp_config', null, 'Configuracion de WhatsApp actualizada');
    setFlash('success', 'Configuracion de WhatsApp guardada correctamente.');
    header('Location: ' . APP_URL . '/modules/whatsapp/config.php');
    exit;
}

// Obtener configuracion actual
$config = $db->query("SELECT * FROM whatsapp_config LIMIT 1")->fetch();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= APP_URL ?>/modules/whatsapp/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver a WhatsApp
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-whatsapp"></i> Configuracion de WhatsApp Business API</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Phone Number ID <span class="text-danger">*</span></label>
                        <input type="text" name="phone_number_id" class="form-control" value="<?= sanitize($config['phone_number_id'] ?? '') ?>" placeholder="Ej: 1234567890">
                        <small class="text-muted">El ID del numero de telefono de WhatsApp Business.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Access Token <span class="text-danger">*</span></label>
                        <textarea name="access_token" class="form-control" rows="3" placeholder="Token de acceso permanente"><?= sanitize($config['access_token'] ?? '') ?></textarea>
                        <small class="text-muted">Token de acceso permanente de la API de WhatsApp Cloud.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Webhook Verify Token <span class="text-danger">*</span></label>
                        <input type="text" name="webhook_verify_token" class="form-control" value="<?= sanitize($config['webhook_verify_token'] ?? '') ?>" placeholder="Token de verificacion personalizado">
                        <small class="text-muted">Token personalizado para verificar el webhook. Puedes usar cualquier cadena segura.</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activo" value="1" id="activoSwitch" <?= !empty($config['activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activoSwitch">Integracion activa</label>
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label fw-bold">URL del Webhook</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light" value="<?= APP_URL ?>/api/whatsapp_webhook.php" readonly id="webhookUrl">
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('webhookUrl').value); this.innerHTML='<i class=\'bi bi-check\'></i> Copiado'; setTimeout(() => this.innerHTML='<i class=\'bi bi-clipboard\'></i> Copiar', 2000);">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </div>
                        <small class="text-muted">Configura esta URL como webhook en tu cuenta de WhatsApp Business API.</small>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Instrucciones de configuracion</h6>
                        <ol class="mb-0 small">
                            <li>Accede a <a href="https://developers.facebook.com" target="_blank">Meta for Developers</a> y crea una aplicacion de tipo Business.</li>
                            <li>En la seccion de WhatsApp, configura un numero de telefono de prueba o produccion.</li>
                            <li>Copia el <strong>Phone Number ID</strong> y el <strong>Access Token</strong> permanente en los campos anteriores.</li>
                            <li>En la configuracion del Webhook, introduce la URL indicada arriba y el <strong>Verify Token</strong> que hayas definido.</li>
                            <li>Suscribete a los eventos <code>messages</code> del webhook.</li>
                            <li>Activa la integracion con el interruptor y guarda la configuracion.</li>
                        </ol>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i> Guardar Configuracion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
