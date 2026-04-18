<?php
$pageTitle = 'Configuración WhatsApp';
require_once __DIR__ . '/../../includes/header.php';

$db     = getDB();
$userId = (int) currentUserId();

// ── Helpers de configuración ─────────────────────────────────────────────────
function waLoadConfig(PDO $db): array {
    $cfg = [
        'access_token'        => getenv('META_WA_ACCESS_TOKEN')        ?: '',
        'phone_number_id'     => getenv('META_WA_PHONE_NUMBER_ID')     ?: '',
        'business_account_id' => getenv('META_WA_BUSINESS_ACCOUNT_ID') ?: '',
        'webhook_verify_token'=> getenv('META_WA_VERIFY_TOKEN')        ?: '',
        'phone_display'       => '',
        'source'              => 'env',
    ];
    if (!$cfg['access_token'] || !$cfg['phone_number_id']) {
        try {
            $stmt = $db->query("SELECT * FROM whatsapp_config WHERE activo = 1 ORDER BY id DESC LIMIT 1");
            $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($row) {
                $cfg['access_token']         = $cfg['access_token']         ?: ($row['access_token']         ?? '');
                $cfg['phone_number_id']      = $cfg['phone_number_id']      ?: ($row['phone_number_id']      ?? '');
                $cfg['business_account_id']  = $cfg['business_account_id']  ?: ($row['business_account_id']  ?? '');
                $cfg['webhook_verify_token'] = $cfg['webhook_verify_token'] ?: ($row['webhook_verify_token'] ?? '');
                $cfg['phone_display']        = $row['phone_display'] ?? '';
                $cfg['source']               = 'db';
            }
        } catch (Throwable $e) {}
    }
    return $cfg;
}

function waTestConnection(string $accessToken, string $phoneNumberId): array {
    if (!$accessToken || !$phoneNumberId) {
        return ['ok' => false, 'msg' => 'Faltan credenciales.'];
    }
    $url = "https://graph.facebook.com/v20.0/{$phoneNumberId}?fields=display_phone_number,verified_name,quality_rating";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) return ['ok' => false, 'msg' => 'Error de red: ' . $curlErr];
    $data = json_decode($body, true);
    if ($httpCode === 200 && !empty($data['display_phone_number'])) {
        return [
            'ok'           => true,
            'phone'        => $data['display_phone_number'],
            'name'         => $data['verified_name'] ?? '',
            'quality'      => $data['quality_rating'] ?? 'GREEN',
            'msg'          => 'Conexión exitosa',
        ];
    }
    $errMsg = $data['error']['message'] ?? ('Error HTTP ' . $httpCode);
    return ['ok' => false, 'msg' => $errMsg];
}

// ── Acciones AJAX ────────────────────────────────────────────────────────────
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    header('Content-Type: application/json; charset=utf-8');
    $accionAjax = post('accion_ajax');

    if ($accionAjax === 'test') {
        $cfg    = waLoadConfig($db);
        $result = waTestConnection($cfg['access_token'], $cfg['phone_number_id']);
        echo json_encode($result);
        exit;
    }

    if ($accionAjax === 'generar_token') {
        $newToken = 'wa_' . bin2hex(random_bytes(16));
        echo json_encode(['success' => true, 'token' => $newToken]);
        exit;
    }

    echo json_encode(['success' => false, 'msg' => 'Acción no reconocida']);
    exit;
}

// ── Guardar configuración ────────────────────────────────────────────────────
$saved   = false;
$saveErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isAjax) {
    verifyCsrf();
    $accion = post('accion');

    if ($accion === 'guardar') {
        $accessToken        = trim(post('access_token'));
        $phoneNumberId      = trim(post('phone_number_id'));
        $businessAccountId  = trim(post('business_account_id'));
        $webhookVerifyToken = trim(post('webhook_verify_token'));

        if (!$accessToken || !$phoneNumberId || !$webhookVerifyToken) {
            $saveErr = 'Access Token, Phone Number ID y Verify Token son obligatorios.';
        } else {
            // Probar conexión antes de guardar
            $test = waTestConnection($accessToken, $phoneNumberId);
            $phoneDisplay = $test['ok'] ? $test['phone'] : '';

            try {
                $db->exec("UPDATE whatsapp_config SET activo = 0");
                $stmt = $db->prepare("
                    INSERT INTO whatsapp_config
                        (access_token, phone_number_id, business_account_id, webhook_verify_token, phone_display, activo, updated_by, updated_at)
                    VALUES (?, ?, ?, ?, ?, 1, ?, NOW())
                ");
                $stmt->execute([$accessToken, $phoneNumberId, $businessAccountId, $webhookVerifyToken, $phoneDisplay, $userId]);
                registrarActividad('configurar', 'whatsapp', 0, 'Configuración Meta API guardada');
                $saved = true;
            } catch (Throwable $e) {
                $saveErr = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

// ── Cargar config actual ─────────────────────────────────────────────────────
$cfg         = waLoadConfig($db);
$isConfigured = $cfg['access_token'] !== '' && $cfg['phone_number_id'] !== '';
$webhookUrl  = APP_URL . '/api/whatsapp_webhook.php';

// Auto-generar verify token si no hay ninguno
if (empty($cfg['webhook_verify_token'])) {
    $cfg['webhook_verify_token'] = 'wa_' . bin2hex(random_bytes(12));
}
?>

<style>
:root { --wa-green: #25D366; --wa-dark: #075E54; --wa-light: #DCF8C6; }

.wa-config-hero {
    background: linear-gradient(135deg, #075E54 0%, #128C7E 60%, #25D366 100%);
    border-radius: 16px;
    color: #fff;
    padding: 28px 32px;
}
.wa-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    border-radius: 100px;
    font-size: 0.85rem;
    font-weight: 600;
}
.wa-status-ok  { background: rgba(255,255,255,0.2); color: #fff; }
.wa-status-err { background: rgba(255,80,80,0.25);  color: #ffe0e0; }

.wa-step-card {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    margin-bottom: 16px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.wa-step-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }

.wa-step-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 22px;
    cursor: pointer;
    background: #fff;
    border: none;
    width: 100%;
    text-align: left;
}
.wa-step-header:hover { background: #f9fafb; }

.wa-step-num {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.wa-step-num-done    { background: #d1fae5; color: #065f46; }
.wa-step-num-active  { background: #25D366; color: #fff; }
.wa-step-num-pending { background: #f3f4f6; color: #9ca3af; }

.wa-step-title  { font-weight: 600; color: #111827; font-size: 0.98rem; }
.wa-step-sub    { font-size: 0.8rem; color: #6b7280; margin-top: 2px; }
.wa-step-body   { padding: 0 22px 22px; border-top: 1px solid #f3f4f6; background: #fafafa; }

.wa-copy-group { position: relative; }
.wa-copy-group .form-control { padding-right: 44px; font-family: monospace; font-size: 0.85rem; background: #f8fafc; }
.wa-copy-btn {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: #6b7280; cursor: pointer; padding: 4px;
    border-radius: 6px; transition: all 0.2s;
}
.wa-copy-btn:hover { background: #e5e7eb; color: #111827; }

.wa-field-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
    margin-bottom: 6px;
}
.wa-token-input { font-family: monospace; font-size: 0.85rem; }

.wa-quality-GREEN  { color: #16a34a; }
.wa-quality-YELLOW { color: #d97706; }
.wa-quality-RED    { color: #dc2626; }

.wa-info-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 14px 16px;
    font-size: 0.875rem;
}
.wa-info-box ol { margin: 0; padding-left: 18px; }
.wa-info-box li { margin-bottom: 6px; }

.wa-test-btn {
    background: linear-gradient(135deg, #25D366, #128C7E);
    border: none;
    color: #fff;
    border-radius: 10px;
    padding: 10px 24px;
    font-weight: 600;
    transition: opacity 0.2s;
}
.wa-test-btn:hover { opacity: 0.9; color: #fff; }
.wa-test-btn:disabled { opacity: 0.6; }

#testResult { display: none; }
</style>

<!-- Hero / Estado de conexión -->
<div class="wa-config-hero mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-whatsapp fs-4"></i>
                <h4 class="mb-0 fw-bold">WhatsApp Business API</h4>
            </div>
            <p class="mb-0 opacity-75" style="font-size:0.9rem;">
                Meta Cloud API — mensajes directos desde tu número de negocio
            </p>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <?php if ($isConfigured): ?>
                <span class="wa-status-badge wa-status-ok">
                    <i class="bi bi-circle-fill" style="font-size:8px;color:#a7f3d0"></i>
                    Conectado<?= $cfg['phone_display'] ? ' · ' . htmlspecialchars($cfg['phone_display']) : '' ?>
                </span>
            <?php else: ?>
                <span class="wa-status-badge wa-status-err">
                    <i class="bi bi-circle-fill" style="font-size:8px;color:#fca5a5"></i>
                    No configurado
                </span>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/modules/whatsapp/index.php" class="btn btn-sm" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3)">
                <i class="bi bi-arrow-left"></i> Volver a WhatsApp
            </a>
        </div>
    </div>
</div>

<?php if ($saved): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div><strong>¡Configuración guardada!</strong> WhatsApp ya está listo para usar.</div>
    </div>
<?php elseif ($saveErr): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div><?= htmlspecialchars($saveErr) ?></div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">

        <!-- ── PASO 1 ── -->
        <div class="wa-step-card">
            <button class="wa-step-header" type="button" onclick="toggleStep(1)">
                <span class="wa-step-num wa-step-num-done"><i class="bi bi-check-lg"></i></span>
                <div>
                    <div class="wa-step-title">Crear App en Meta for Developers</div>
                    <div class="wa-step-sub">Necesitas una app de Meta con el producto WhatsApp activado</div>
                </div>
                <i class="bi bi-chevron-down ms-auto text-muted" id="chevron1"></i>
            </button>
            <div class="wa-step-body" id="step1" style="display:none;">
                <div class="wa-info-box mt-3">
                    <strong>¿Ya tienes una app de Meta? Sáltate este paso.</strong>
                    <ol class="mt-2">
                        <li>Ve a <strong>developers.facebook.com</strong> e inicia sesión.</li>
                        <li>Crea una nueva app → tipo <strong>"Business"</strong>.</li>
                        <li>En el panel de la app, añade el producto <strong>"WhatsApp"</strong>.</li>
                        <li>En <strong>WhatsApp → Configuración de la API</strong> encontrarás el <em>Phone Number ID</em> y el <em>WhatsApp Business Account ID</em>.</li>
                        <li>Para el <strong>Access Token permanente</strong>: ve a <em>Configuración → Avanzada → Tokens de acceso del sistema</em>.</li>
                    </ol>
                </div>
                <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mt-3">
                    <i class="bi bi-box-arrow-up-right"></i> Abrir Meta for Developers
                </a>
            </div>
        </div>

        <!-- ── PASO 2 ── -->
        <div class="wa-step-card">
            <button class="wa-step-header" type="button" onclick="toggleStep(2)">
                <span class="wa-step-num <?= $isConfigured ? 'wa-step-num-done' : 'wa-step-num-active' ?>">
                    <?= $isConfigured ? '<i class="bi bi-check-lg"></i>' : '2' ?>
                </span>
                <div>
                    <div class="wa-step-title">Introduce tus credenciales</div>
                    <div class="wa-step-sub">Access Token, Phone Number ID y Business Account ID</div>
                </div>
                <i class="bi bi-chevron-down ms-auto text-muted" id="chevron2"></i>
            </button>
            <div class="wa-step-body" id="step2" style="display:<?= (!$isConfigured || $saveErr) ? 'block' : 'none' ?>;">
                <form method="POST" class="mt-3" id="formGuardar">
                    <?= csrfField() ?>
                    <input type="hidden" name="accion" value="guardar">

                    <div class="mb-3">
                        <div class="wa-field-label">Access Token <span class="text-danger">*</span></div>
                        <div class="input-group">
                            <input type="password" name="access_token" id="accessToken"
                                   class="form-control"
                                   placeholder="EAAxxxxxxxxxxxxxxxxxxxxxxx..."
                                   value="<?= $isConfigured ? '••••••••••••••••••••••••' : '' ?>"
                                   autocomplete="new-password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleVer('accessToken', this)" title="Mostrar/ocultar">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Token permanente del sistema de Meta (no el temporal de 24h).</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <div class="wa-field-label">Phone Number ID <span class="text-danger">*</span></div>
                            <input type="text" name="phone_number_id" class="form-control"
                                   placeholder="1234567890123"
                                   value="<?= htmlspecialchars($cfg['phone_number_id']) ?>" required>
                            <small class="text-muted">ID numérico, no el número de teléfono.</small>
                        </div>
                        <div class="col-sm-6">
                            <div class="wa-field-label">WhatsApp Business Account ID</div>
                            <input type="text" name="business_account_id" class="form-control"
                                   placeholder="9876543210987"
                                   value="<?= htmlspecialchars($cfg['business_account_id']) ?>">
                            <small class="text-muted">Opcional, para estadísticas.</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="wa-field-label">Token de Verificación del Webhook <span class="text-danger">*</span></div>
                        <div class="input-group">
                            <input type="text" name="webhook_verify_token" id="verifyToken"
                                   class="form-control wa-token-input"
                                   value="<?= htmlspecialchars($cfg['webhook_verify_token']) ?>" required>
                            <button type="button" class="btn btn-outline-secondary" id="btnGenToken" title="Generar nuevo token">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="copyText('verifyToken')" title="Copiar">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <small class="text-muted">Cadena secreta que introduces en la consola de Meta al configurar el webhook.</small>
                    </div>

                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-floppy"></i> Guardar configuración
                    </button>
                </form>
            </div>
        </div>

        <!-- ── PASO 3 ── -->
        <div class="wa-step-card">
            <button class="wa-step-header" type="button" onclick="toggleStep(3)">
                <span class="wa-step-num <?= $isConfigured ? 'wa-step-num-done' : 'wa-step-num-pending' ?>">
                    <?= $isConfigured ? '<i class="bi bi-check-lg"></i>' : '3' ?>
                </span>
                <div>
                    <div class="wa-step-title">Configurar el Webhook en Meta</div>
                    <div class="wa-step-sub">Para recibir mensajes entrantes en el CRM</div>
                </div>
                <i class="bi bi-chevron-down ms-auto text-muted" id="chevron3"></i>
            </button>
            <div class="wa-step-body" id="step3" style="display:<?= $isConfigured ? 'none' : 'none' ?>;">
                <div class="wa-info-box mt-3">
                    <strong>Pasos en la consola de Meta:</strong>
                    <ol class="mt-2">
                        <li>Ve a tu app → <strong>WhatsApp → Configuración</strong>.</li>
                        <li>En <em>Webhooks</em>, haz clic en <strong>Editar</strong>.</li>
                        <li>Introduce la URL del Webhook y tu Token de Verificación.</li>
                        <li>Haz clic en <strong>Verificar y guardar</strong>.</li>
                        <li>Suscríbete al campo <strong><code>messages</code></strong>.</li>
                    </ol>
                </div>

                <div class="mt-3">
                    <div class="wa-field-label">URL del Webhook</div>
                    <div class="wa-copy-group">
                        <input type="text" class="form-control" id="webhookUrl" value="<?= htmlspecialchars($webhookUrl) ?>" readonly>
                        <button class="wa-copy-btn" onclick="copyText('webhookUrl')" title="Copiar URL">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="wa-field-label">Token de verificación</div>
                    <div class="wa-copy-group">
                        <input type="text" class="form-control wa-token-input" id="verifyTokenDisplay"
                               value="<?= htmlspecialchars($cfg['webhook_verify_token']) ?>" readonly>
                        <button class="wa-copy-btn" onclick="copyText('verifyTokenDisplay')" title="Copiar token">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <small class="text-muted">Este token debe coincidir exactamente con el que guardaste en el Paso 2.</small>
                </div>

                <div class="mt-3">
                    <div class="wa-field-label">Campo a suscribir</div>
                    <div class="wa-copy-group">
                        <input type="text" class="form-control" id="subField" value="messages" readonly>
                        <button class="wa-copy-btn" onclick="copyText('subField')" title="Copiar">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── PASO 4 ── -->
        <div class="wa-step-card">
            <button class="wa-step-header" type="button" onclick="toggleStep(4)">
                <span class="wa-step-num <?= $isConfigured ? 'wa-step-num-active' : 'wa-step-num-pending' ?>">
                    <?= $isConfigured ? '<i class="bi bi-wifi"></i>' : '4' ?>
                </span>
                <div>
                    <div class="wa-step-title">Probar la conexión</div>
                    <div class="wa-step-sub">Verifica que el token y el número están activos</div>
                </div>
                <i class="bi bi-chevron-down ms-auto text-muted" id="chevron4"></i>
            </button>
            <div class="wa-step-body" id="step4" style="display:<?= $isConfigured ? 'block' : 'none' ?>;">
                <div class="mt-3">
                    <button type="button" class="wa-test-btn" id="btnTest">
                        <i class="bi bi-wifi"></i> Probar conexión con Meta
                    </button>

                    <div id="testResult" class="mt-3 p-3 rounded-3" style="border: 1.5px solid transparent;">
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Columna derecha: resumen + ayuda -->
    <div class="col-lg-4">

        <?php if ($isConfigured): ?>
        <!-- Tarjeta número activo -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;overflow:hidden;">
            <div class="card-header border-0 pb-0 pt-3 px-3" style="background:#f0fdf4;">
                <small class="text-success fw-bold text-uppercase" style="font-size:0.72rem;letter-spacing:.05em;">
                    <i class="bi bi-check-circle-fill"></i> Número activo
                </small>
            </div>
            <div class="card-body pt-2" style="background:#f0fdf4;">
                <div class="fw-bold fs-5" style="color:#15803d;">
                    <?= htmlspecialchars($cfg['phone_display'] ?: $cfg['phone_number_id']) ?>
                </div>
                <small class="text-muted">Phone ID: <code><?= htmlspecialchars(substr($cfg['phone_number_id'], 0, 8)) ?>...</code></small>
                <?php if ($cfg['source'] === 'env'): ?>
                    <div class="mt-2"><span class="badge bg-secondary" style="font-size:0.72rem;">via .env</span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Ayuda rápida -->
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-3 pb-0 px-3">
                <span class="fw-semibold text-muted" style="font-size:0.85rem;">
                    <i class="bi bi-question-circle"></i> ¿Dónde encuentro cada dato?
                </span>
            </div>
            <div class="card-body pt-2 pb-3">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <div class="fw-semibold" style="font-size:0.85rem;">📱 Phone Number ID</div>
                        <div class="text-muted" style="font-size:0.8rem;">
                            App de Meta → WhatsApp → Configuración de la API → <em>ID de número de teléfono</em>
                        </div>
                    </div>
                    <div>
                        <div class="fw-semibold" style="font-size:0.85rem;">🏢 Business Account ID</div>
                        <div class="text-muted" style="font-size:0.8rem;">
                            App de Meta → WhatsApp → Configuración de la API → <em>ID de cuenta de WhatsApp Business</em>
                        </div>
                    </div>
                    <div>
                        <div class="fw-semibold" style="font-size:0.85rem;">🔑 Access Token</div>
                        <div class="text-muted" style="font-size:0.8rem;">
                            App de Meta → Configuración → Avanzada → <em>Tokens de acceso del sistema</em>. Usa el <strong>permanente</strong>, no el de 24h.
                        </div>
                    </div>
                    <hr class="my-0">
                    <div>
                        <div class="fw-semibold" style="font-size:0.85rem;">📚 Documentación oficial</div>
                        <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" rel="noopener" class="text-success" style="font-size:0.8rem;">
                            <i class="bi bi-box-arrow-up-right"></i> Meta Cloud API — Getting Started
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// ── Accordion steps ───────────────────────────────────────────────────────────
function toggleStep(n) {
    var body    = document.getElementById('step' + n);
    var chevron = document.getElementById('chevron' + n);
    if (!body) return;
    var isOpen = body.style.display !== 'none';
    body.style.display    = isOpen ? 'none' : 'block';
    chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
}

// ── Copiar al portapapeles ────────────────────────────────────────────────────
function copyText(id) {
    var el  = document.getElementById(id);
    var val = el.value;
    navigator.clipboard.writeText(val).then(function() {
        var allBtns = el.parentElement.querySelectorAll('[onclick*="copyText"], .wa-copy-btn');
        allBtns.forEach(function(btn) {
            var prev = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
            setTimeout(function() { btn.innerHTML = prev; }, 1800);
        });
    });
}

// ── Mostrar/ocultar contraseña ────────────────────────────────────────────────
function toggleVer(id, btn) {
    var inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
        inp.type = 'password';
        btn.innerHTML = '<i class="bi bi-eye"></i>';
    }
}

// ── Generar nuevo verify token ────────────────────────────────────────────────
document.getElementById('btnGenToken')?.addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
    fd.append('accion_ajax', 'generar_token');
    fetch(window.location.href, { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd, credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('verifyToken').value = d.token;
            var disp = document.getElementById('verifyTokenDisplay');
            if (disp) disp.value = d.token;
        }
    })
    .finally(() => { btn.disabled = false; });
});

// ── Test conexión ─────────────────────────────────────────────────────────────
document.getElementById('btnTest')?.addEventListener('click', function() {
    var btn    = this;
    var result = document.getElementById('testResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Conectando...';
    result.style.display = 'none';

    var fd = new FormData();
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
    fd.append('accion_ajax', 'test');

    fetch(window.location.href, { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd, credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
        result.style.display = 'block';
        if (d.ok) {
            var quality = d.quality || 'GREEN';
            var qualityIcon = quality === 'GREEN' ? '🟢' : (quality === 'YELLOW' ? '🟡' : '🔴');
            result.style.borderColor = '#86efac';
            result.style.background  = '#f0fdf4';
            result.innerHTML =
                '<div class="fw-semibold text-success mb-1"><i class="bi bi-check-circle-fill"></i> Conexión exitosa</div>' +
                '<div class="text-muted" style="font-size:.85rem;">' +
                    '<strong>Número:</strong> ' + (d.phone || '—') + '<br>' +
                    '<strong>Nombre:</strong> '  + (d.name  || '—') + '<br>' +
                    '<strong>Calidad:</strong> ' + qualityIcon + ' ' + quality +
                '</div>';
        } else {
            result.style.borderColor = '#fca5a5';
            result.style.background  = '#fff1f2';
            result.innerHTML =
                '<div class="fw-semibold text-danger mb-1"><i class="bi bi-x-circle-fill"></i> Error de conexión</div>' +
                '<div class="text-muted" style="font-size:.85rem;">' + (d.msg || 'Error desconocido') + '</div>';
        }
    })
    .catch(() => {
        result.style.display  = 'block';
        result.style.borderColor = '#fca5a5';
        result.style.background  = '#fff1f2';
        result.innerHTML = '<div class="text-danger"><i class="bi bi-x-circle-fill"></i> Error de red al conectar.</div>';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-wifi"></i> Probar conexión con Meta';
    });
});

// Auto-expandir pasos al cargar
<?php if (!$isConfigured): ?>
    toggleStep(2);
<?php else: ?>
    toggleStep(3);
    toggleStep(4);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
