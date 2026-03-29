<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');

    if ($a === 'config') {
        $db->prepare("UPDATE ia_config SET proveedor=?, api_key=CASE WHEN ?='' THEN api_key ELSE ? END, modelo=?, prompt_sistema=?, activo=?, max_tokens=?, temperatura=? WHERE id=1")
            ->execute([post('proveedor'), post('api_key'), post('api_key'), post('modelo'), post('prompt_sistema'), intval(post('activo')), intval(post('max_tokens',500)), floatval(post('temperatura',0.7))]);
        setFlash('success','Configuracion guardada.');
        header('Location: index.php'); exit;
    }

    if ($a === 'chat') {
        $mensaje = trim($_POST['mensaje'] ?? '');
        $config = $db->query("SELECT * FROM ia_config WHERE id=1")->fetch();

        if (!$config || !$config['activo'] || !$config['api_key']) {
            header('Content-Type: application/json');
            echo json_encode(['error'=>'IA no configurada']);
            exit;
        }

        $historial = json_decode($_POST['historial'] ?? '[]', true) ?: [];
        $messages = [['role'=>'system','content'=>$config['prompt_sistema']]];
        foreach ($historial as $h) $messages[] = $h;
        $messages[] = ['role'=>'user','content'=>$mensaje];

        if ($config['proveedor'] === 'openai') {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer '.$config['api_key']],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $config['modelo'] ?: 'gpt-3.5-turbo',
                    'messages' => $messages,
                    'max_tokens' => intval($config['max_tokens']),
                    'temperature' => floatval($config['temperatura'])
                ])
            ]);
            $resp = curl_exec($ch); curl_close($ch);
            $data = json_decode($resp, true);
            $reply = $data['choices'][0]['message']['content'] ?? 'Error al obtener respuesta.';
        } elseif ($config['proveedor'] === 'anthropic') {
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            $anthropicMsgs = [];
            foreach ($historial as $h) $anthropicMsgs[] = $h;
            $anthropicMsgs[] = ['role'=>'user','content'=>$mensaje];
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: '.$config['api_key'], 'anthropic-version: 2023-06-01'],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $config['modelo'] ?: 'claude-sonnet-4-20250514',
                    'max_tokens' => intval($config['max_tokens']),
                    'system' => $config['prompt_sistema'],
                    'messages' => $anthropicMsgs
                ])
            ]);
            $resp = curl_exec($ch); curl_close($ch);
            $data = json_decode($resp, true);
            $reply = $data['content'][0]['text'] ?? 'Error al obtener respuesta.';
        } else {
            $reply = 'Proveedor no soportado.';
        }

        header('Content-Type: application/json');
        echo json_encode(['reply'=>$reply]);
        exit;
    }
}

$pageTitle = 'IA Conversacional';
require_once __DIR__ . '/../../includes/header.php';
$config = $db->query("SELECT * FROM ia_config WHERE id=1")->fetch();
if (!$config) { $config = ['proveedor'=>'openai','api_key'=>'','modelo'=>'gpt-3.5-turbo','prompt_sistema'=>'','activo'=>0,'max_tokens'=>500,'temperatura'=>0.7]; }
?>

<div class="row g-4">
    <!-- Config -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-gear"></i> Configuracion IA</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?><input type="hidden" name="accion" value="config">
                    <div class="mb-3"><label class="form-label">Proveedor</label>
                        <select name="proveedor" class="form-select"><option value="openai" <?= $config['proveedor']==='openai'?'selected':'' ?>>OpenAI</option><option value="anthropic" <?= $config['proveedor']==='anthropic'?'selected':'' ?>>Anthropic (Claude)</option></select>
                    </div>
                    <div class="mb-3"><label class="form-label">API Key</label><input type="password" name="api_key" class="form-control" placeholder="<?= $config['api_key']?'Configurada':'No configurada' ?>"></div>
                    <div class="mb-3"><label class="form-label">Modelo</label><input type="text" name="modelo" class="form-control" value="<?= sanitize($config['modelo']) ?>"><small class="text-muted">OpenAI: gpt-4o, gpt-3.5-turbo | Anthropic: claude-sonnet-4-20250514</small></div>
                    <div class="mb-3"><label class="form-label">Prompt del sistema</label><textarea name="prompt_sistema" class="form-control" rows="4"><?= sanitize($config['prompt_sistema']) ?></textarea></div>
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label">Max tokens</label><input type="number" name="max_tokens" class="form-control" value="<?= $config['max_tokens'] ?>"></div>
                        <div class="col-md-4"><label class="form-label">Temperatura</label><input type="number" name="temperatura" class="form-control" value="<?= $config['temperatura'] ?>" step="0.1" min="0" max="2"></div>
                        <div class="col-md-4"><label class="form-label">Activo</label><select name="activo" class="form-select"><option value="0" <?= !$config['activo']?'selected':'' ?>>No</option><option value="1" <?= $config['activo']?'selected':'' ?>>Si</option></select></div>
                    </div>
                    <button class="btn btn-primary mt-3 w-100">Guardar</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Chat test -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-robot"></i> Chat de prueba</h6></div>
            <div class="card-body" style="height:400px;overflow-y:auto" id="chatArea">
                <div class="text-center text-muted py-5" id="chatEmpty"><i class="bi bi-robot fs-1 d-block mb-2"></i>Prueba tu asistente IA aqui</div>
            </div>
            <div class="card-footer bg-white">
                <div class="d-flex gap-2">
                    <input type="text" id="chatInput" class="form-control" placeholder="Escribe un mensaje..." <?= !$config['activo']?'disabled':'' ?>>
                    <button class="btn btn-primary" id="chatSend" <?= !$config['activo']?'disabled':'' ?>><i class="bi bi-send"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let historial = [];
const chatArea = document.getElementById('chatArea');
const chatInput = document.getElementById('chatInput');

document.getElementById('chatSend')?.addEventListener('click', sendMsg);
chatInput?.addEventListener('keypress', e => { if(e.key==='Enter') sendMsg(); });

async function sendMsg() {
    const msg = chatInput.value.trim();
    if (!msg) return;
    document.getElementById('chatEmpty')?.remove();

    chatArea.innerHTML += `<div class="d-flex justify-content-end mb-2"><div style="max-width:70%;background:#10b981;color:#fff;padding:8px 12px;border-radius:12px">${escHtml(msg)}</div></div>`;
    chatInput.value = '';

    historial.push({role:'user',content:msg});

    chatArea.innerHTML += `<div class="d-flex mb-2" id="typing"><div style="background:#f1f5f9;padding:8px 12px;border-radius:12px"><i class="bi bi-three-dots"></i> Pensando...</div></div>`;
    chatArea.scrollTop = chatArea.scrollHeight;

    const formData = new FormData();
    formData.append('accion','chat');
    formData.append('mensaje',msg);
    formData.append('historial',JSON.stringify(historial));
    formData.append('csrf_token','<?= csrfToken() ?>');

    try {
        const resp = await fetch('index.php', {method:'POST',body:formData});
        const data = await resp.json();
        document.getElementById('typing')?.remove();
        const reply = data.reply || data.error || 'Error';
        chatArea.innerHTML += `<div class="d-flex mb-2"><div style="max-width:70%;background:#f1f5f9;padding:8px 12px;border-radius:12px">${escHtml(reply)}</div></div>`;
        historial.push({role:'assistant',content:reply});
    } catch(e) {
        document.getElementById('typing')?.remove();
        chatArea.innerHTML += `<div class="text-danger small">Error de conexion</div>`;
    }
    chatArea.scrollTop = chatArea.scrollHeight;
}

function escHtml(s) { const d=document.createElement('div');d.textContent=s;return d.innerHTML; }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
