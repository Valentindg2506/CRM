<?php
/**
 * MCP Server PHP — OAuth 2.1 + MCP Streamable HTTP
 * URL: https://tinoprop.es/mcp.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/email.php';

// ── CORS ──────────────────────────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, mcp-session-id');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$db      = getDB();
$baseUrl = 'https://tinoprop.es/mcp.php';
$path    = trim($_GET['path'] ?? '', '/');
$method  = $_SERVER['REQUEST_METHOD'];
$body    = [];
$rawBody = file_get_contents('php://input');
if ($rawBody) $body = json_decode($rawBody, true) ?? [];

// ── HELPERS ────────────────────────────────────────────────────────────────
function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mcpJson(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rpc(mixed $id, mixed $result): array {
    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
}

function rpcErr(mixed $id, int $code, string $msg): array {
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $msg]];
}

// ── AUTENTICACIÓN MCP ──────────────────────────────────────────────────────
function getMcpUser(PDO $db): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) return null;
    $token = trim($m[1]);
    if (strlen($token) < 32) return null;
    $stmt = $db->prepare("SELECT u.* FROM usuarios u JOIN usuario_ajustes ua ON ua.usuario_id = u.id WHERE ua.clave = 'mcp_token' AND ua.valor = ? AND u.activo = 1 LIMIT 1");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ── ROUTING ────────────────────────────────────────────────────────────────

// ── OAuth Discovery ────────────────────────────────────────────────────────
if ($path === '.well-known/oauth-authorization-server' || $path === '.well-known/oauth-protected-resource') {
    if ($path === '.well-known/oauth-protected-resource') {
        jsonOut([
            'resource'                 => $baseUrl,
            'authorization_servers'   => [$baseUrl],
            'bearer_methods_supported' => ['header'],
        ]);
    }
    jsonOut([
        'issuer'                                    => $baseUrl,
        'authorization_endpoint'                    => "$baseUrl?path=authorize",
        'token_endpoint'                            => "$baseUrl?path=token",
        'registration_endpoint'                     => "$baseUrl?path=register",
        'scopes_supported'                          => ['mcp'],
        'response_types_supported'                  => ['code'],
        'grant_types_supported'                     => ['authorization_code'],
        'code_challenge_methods_supported'          => ['S256'],
        'token_endpoint_auth_methods_supported'     => ['client_secret_basic', 'client_secret_post'],
    ]);
}

// ── Dynamic Client Registration ────────────────────────────────────────────
if ($path === 'register' && $method === 'POST') {
    $clientId     = bin2hex(random_bytes(16));
    $clientSecret = bin2hex(random_bytes(32));
    $db->prepare("INSERT INTO usuario_ajustes (usuario_id, clave, valor) VALUES (0, ?, ?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")
       ->execute(["oauth_client_$clientId", json_encode(['secret' => $clientSecret, 'data' => $body, 'created' => time()])]);
    jsonOut([
        'client_id'               => $clientId,
        'client_secret'           => $clientSecret,
        'client_id_issued_at'     => time(),
        'client_secret_expires_at' => 0,
        'redirect_uris'           => $body['redirect_uris'] ?? [],
        'grant_types'             => ['authorization_code'],
        'response_types'          => ['code'],
        'token_endpoint_auth_method' => 'client_secret_post',
        'client_name'             => $body['client_name'] ?? 'MCP Client',
    ], 201);
}

// ── Authorization Form ─────────────────────────────────────────────────────
if ($path === 'authorize' && $method === 'GET') {
    $params = http_build_query($_GET);
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Conectar CRM</title>
    <style>
      body{font-family:system-ui,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f9f9f9;}
      .card{background:white;padding:2rem;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:420px;width:100%;}
      h2{margin-top:0;color:#111;}p{color:#555;font-size:.95rem;}
      input[type=text]{width:100%;padding:10px;margin:10px 0;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;font-family:monospace;font-size:.85rem;}
      button{width:100%;padding:12px;background:#0066cc;color:white;border:none;border-radius:6px;cursor:pointer;font-size:1rem;font-weight:600;}
      button:hover{background:#005bb5;}.hint{font-size:.78rem;color:#999;margin-top:8px;}
    </style></head><body>
    <div class="card">
      <h2>Conectar CRM a Claude</h2>
      <p>Introduce tu token personal del CRM (64 caracteres).<br>
      Encuéntralo en <strong>Ajustes → Conector Claude (MCP)</strong>.</p>
      <form method="POST" action="mcp.php?{$params}">
        <input type="text" name="crm_token" placeholder="f3b1c9d2e8a7..." autocomplete="off" required>
        <button type="submit">Conectar</button>
      </form>
      <p class="hint">Cada usuario del CRM tiene su propio token.</p>
    </div></body></html>
    HTML;
    exit;
}

if ($path === 'authorize' && $method === 'POST') {
    $redirectUri         = $_GET['redirect_uri'] ?? $_POST['redirect_uri'] ?? '';
    $state               = $_GET['state'] ?? $_POST['state'] ?? '';
    $codeChallenge       = $_GET['code_challenge'] ?? '';
    $codeChallengeMethod = $_GET['code_challenge_method'] ?? 'S256';
    $crmToken            = trim($_POST['crm_token'] ?? '');

    if (!$redirectUri || !$crmToken) { http_response_code(400); echo 'Faltan parámetros'; exit; }

    $code = bin2hex(random_bytes(16));
    $db->prepare("INSERT INTO usuario_ajustes (usuario_id, clave, valor) VALUES (0, ?, ?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")
       ->execute(["oauth_code_$code", json_encode([
           'token'     => $crmToken,
           'challenge' => $codeChallenge,
           'method'    => $codeChallengeMethod,
           'expires'   => time() + 300,
       ])]);

    $callbackUrl = $redirectUri . (strpos($redirectUri, '?') !== false ? '&' : '?') . 'code=' . $code . ($state ? '&state=' . urlencode($state) : '');
    header("Location: $callbackUrl");
    exit;
}

// ── Token Endpoint ──────────────────────────────────────────────────────────
if ($path === 'token' && $method === 'POST') {
    $input = $_POST ?: $body;
    $code  = $input['code'] ?? '';
    if (!$code) { jsonOut(['error' => 'invalid_grant'], 400); }

    $stmt = $db->prepare("SELECT valor FROM usuario_ajustes WHERE clave = ? AND usuario_id = 0 LIMIT 1");
    $stmt->execute(["oauth_code_$code"]);
    $row = $stmt->fetchColumn();
    if (!$row) { jsonOut(['error' => 'invalid_grant', 'error_description' => 'Código inválido o expirado'], 400); }

    $data = json_decode($row, true);
    $db->prepare("DELETE FROM usuario_ajustes WHERE clave = ? AND usuario_id = 0")->execute(["oauth_code_$code"]);

    if ($data['expires'] < time()) { jsonOut(['error' => 'invalid_grant', 'error_description' => 'Código expirado'], 400); }

    if (!empty($data['challenge'])) {
        $verifier = $input['code_verifier'] ?? '';
        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        if ($expected !== $data['challenge']) {
            jsonOut(['error' => 'invalid_grant', 'error_description' => 'code_verifier inválido'], 400);
        }
    }

    jsonOut([
        'access_token'  => $data['token'],
        'token_type'    => 'Bearer',
        'expires_in'    => 315360000,
        'scope'         => 'mcp',
        'refresh_token' => bin2hex(random_bytes(32)),
    ]);
}

// ── MCP Endpoint ────────────────────────────────────────────────────────────
if ($path === '' || $path === 'mcp') {

    $user = getMcpUser($db);
    if (!$user) {
        header("WWW-Authenticate: Bearer realm=\"$baseUrl\", scope=\"mcp\"");
        jsonOut(['error' => 'unauthorized', 'message' => 'Token MCP requerido'], 401);
    }

    $isAdmin = ($user['rol'] ?? '') === 'admin';
    $userId  = (int)$user['id'];

    if ($method === 'GET') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
    if ($method !== 'POST') { http_response_code(405); exit; }

    $rpcMethod = $body['method'] ?? '';
    $rpcId     = $body['id'] ?? null;
    $params    = $body['params'] ?? [];

    switch ($rpcMethod) {
        case 'initialize':
            mcpJson(rpc($rpcId, [
                'protocolVersion' => '2024-11-05',
                'capabilities'    => ['tools' => []],
                'serverInfo'      => ['name' => 'crm-connector', 'version' => '3.0.0'],
            ]));

        case 'notifications/initialized':
            http_response_code(202);
            exit;

        case 'tools/list':
            mcpJson(rpc($rpcId, ['tools' => getMcpTools()]));

        case 'tools/call':
            $toolName = $params['name'] ?? '';
            $args     = $params['arguments'] ?? [];
            try {
                $result = callMcpTool($db, $user, $isAdmin, $userId, $toolName, $args);
                mcpJson(rpc($rpcId, ['content' => [['type' => 'text', 'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]]]));
            } catch (Throwable $e) {
                mcpJson(rpc($rpcId, ['content' => [['type' => 'text', 'text' => 'Error: ' . $e->getMessage()]], 'isError' => true]));
            }

        default:
            mcpJson(rpcErr($rpcId, -32601, "Método desconocido: $rpcMethod"));
    }
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);
exit;

// ══════════════════════════════════════════════════════════════════════════════
// TOOLS LIST
// ══════════════════════════════════════════════════════════════════════════════

function getMcpTools(): array {
    return [
        ['name'=>'resumen_dashboard','description'=>'Resumen del CRM: prospectos por etapa, clientes, tareas y contactos de hoy.','inputSchema'=>['type'=>'object','properties'=>new stdClass]],
        ['name'=>'estadisticas','description'=>'Estadísticas de ventas por período (hoy/semana/mes/trimestre/anio).','inputSchema'=>['type'=>'object','properties'=>['periodo'=>['type'=>'string','enum'=>['hoy','semana','mes','trimestre','anio']]]]],
        ['name'=>'buscar','description'=>'Búsqueda global en prospectos, clientes y propiedades.','inputSchema'=>['type'=>'object','properties'=>['q'=>['type'=>'string']],'required'=>['q']]],
        // Prospectos
        ['name'=>'listar_prospectos','description'=>'Lista prospectos con filtros opcionales.','inputSchema'=>['type'=>'object','properties'=>['busqueda'=>['type'=>'string'],'etapa'=>['type'=>'string'],'temperatura'=>['type'=>'string'],'contactar_hoy'=>['type'=>'boolean'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_prospecto','description'=>'Datos completos e historial de un prospecto.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'crear_prospecto','description'=>'Crea un nuevo prospecto en el CRM.','inputSchema'=>['type'=>'object','properties'=>['nombre'=>['type'=>'string'],'telefono'=>['type'=>'string'],'email'=>['type'=>'string'],'tipo_propiedad'=>['type'=>'string'],'precio_estimado'=>['type'=>'number'],'localidad'=>['type'=>'string'],'provincia'=>['type'=>'string'],'notas'=>['type'=>'string'],'etapa'=>['type'=>'string'],'temperatura'=>['type'=>'string']],'required'=>['nombre']]],
        ['name'=>'actualizar_prospecto','description'=>'Actualiza etapa, temperatura, notas o fecha de contacto de un prospecto.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'etapa'=>['type'=>'string'],'temperatura'=>['type'=>'string'],'notas_internas'=>['type'=>'string'],'fecha_proximo_contacto'=>['type'=>'string'],'proxima_accion'=>['type'=>'string']],'required'=>['id']]],
        ['name'=>'programar_contacto','description'=>'Cambia fecha de próximo contacto y/o próxima acción (acepta id o ids separados por coma).','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'ids'=>['type'=>'string'],'fecha'=>['type'=>'string'],'proxima_accion'=>['type'=>'string'],'temperatura'=>['type'=>'string'],'etapa'=>['type'=>'string']]]],
        ['name'=>'mover_etapas','description'=>'Mueve uno o varios prospectos a una etapa del pipeline.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'ids'=>['type'=>'string'],'etapa'=>['type'=>'string','enum'=>['nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado']]],'required'=>['etapa']]],
        ['name'=>'anadir_nota','description'=>'Añade una nota al historial de un prospecto.','inputSchema'=>['type'=>'object','properties'=>['prospecto_id'=>['type'=>'number'],'contenido'=>['type'=>'string'],'tipo'=>['type'=>'string']],'required'=>['prospecto_id','contenido']]],
        ['name'=>'convertir_a_cliente','description'=>'Convierte un prospecto en cliente.','inputSchema'=>['type'=>'object','properties'=>['prospecto_id'=>['type'=>'number'],'tipo'=>['type'=>'string']],'required'=>['prospecto_id']]],
        ['name'=>'informe_prospectos','description'=>'Informe completo del embudo de captación.','inputSchema'=>['type'=>'object','properties'=>new stdClass]],
        // Clientes
        ['name'=>'listar_clientes','description'=>'Lista clientes con filtros opcionales.','inputSchema'=>['type'=>'object','properties'=>['busqueda'=>['type'=>'string'],'tipo'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_cliente','description'=>'Datos completos de un cliente.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'crear_cliente','description'=>'Crea un nuevo cliente.','inputSchema'=>['type'=>'object','properties'=>['nombre'=>['type'=>'string'],'apellidos'=>['type'=>'string'],'tipo'=>['type'=>'string'],'email'=>['type'=>'string'],'telefono'=>['type'=>'string'],'localidad'=>['type'=>'string'],'notas'=>['type'=>'string']],'required'=>['nombre']]],
        ['name'=>'actualizar_cliente','description'=>'Actualiza datos de un cliente.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'nombre'=>['type'=>'string'],'apellidos'=>['type'=>'string'],'tipo'=>['type'=>'string'],'email'=>['type'=>'string'],'telefono'=>['type'=>'string'],'localidad'=>['type'=>'string'],'notas'=>['type'=>'string']],'required'=>['id']]],
        ['name'=>'anadir_nota_cliente','description'=>'Añade una nota a un cliente.','inputSchema'=>['type'=>'object','properties'=>['cliente_id'=>['type'=>'number'],'contenido'=>['type'=>'string'],'tipo'=>['type'=>'string']],'required'=>['cliente_id','contenido']]],
        // Tareas
        ['name'=>'listar_tareas','description'=>'Lista tareas del usuario con filtros opcionales.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'solo_hoy'=>['type'=>'boolean']]]],
        ['name'=>'crear_tarea','description'=>'Crea una nueva tarea.','inputSchema'=>['type'=>'object','properties'=>['titulo'=>['type'=>'string'],'tipo'=>['type'=>'string'],'fecha_vencimiento'=>['type'=>'string'],'descripcion'=>['type'=>'string'],'prioridad'=>['type'=>'string'],'cliente_id'=>['type'=>'number'],'propiedad_id'=>['type'=>'number']],'required'=>['titulo','tipo']]],
        ['name'=>'completar_tarea','description'=>'Marca una tarea como completada.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'actualizar_tarea','description'=>'Actualiza título, estado, prioridad o fecha de vencimiento de una tarea.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'titulo'=>['type'=>'string'],'estado'=>['type'=>'string'],'prioridad'=>['type'=>'string'],'fecha_vencimiento'=>['type'=>'string'],'descripcion'=>['type'=>'string']],'required'=>['id']]],
        ['name'=>'cancelar_tarea','description'=>'Cancela una tarea.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        // Propiedades
        ['name'=>'listar_propiedades','description'=>'Busca propiedades en el catálogo.','inputSchema'=>['type'=>'object','properties'=>['busqueda'=>['type'=>'string'],'estado'=>['type'=>'string'],'precio_maximo'=>['type'=>'number'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_propiedad','description'=>'Datos completos de una propiedad.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'crear_propiedad','description'=>'Crea una nueva propiedad en el catálogo.','inputSchema'=>['type'=>'object','properties'=>['titulo'=>['type'=>'string'],'tipo'=>['type'=>'string'],'estado'=>['type'=>'string'],'precio'=>['type'=>'number'],'localidad'=>['type'=>'string'],'provincia'=>['type'=>'string'],'descripcion'=>['type'=>'string'],'habitaciones'=>['type'=>'number'],'banos'=>['type'=>'number'],'metros'=>['type'=>'number']],'required'=>['titulo']]],
        ['name'=>'actualizar_propiedad','description'=>'Actualiza estado, precio o descripción de una propiedad.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'estado'=>['type'=>'string'],'precio'=>['type'=>'number'],'notas'=>['type'=>'string']],'required'=>['id']]],
        // Visitas
        ['name'=>'listar_visitas','description'=>'Lista visitas programadas.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'solo_hoy'=>['type'=>'boolean'],'limite'=>['type'=>'number']]]],
        ['name'=>'crear_visita','description'=>'Programa una visita a una propiedad (fecha: YYYY-MM-DD HH:MM).','inputSchema'=>['type'=>'object','properties'=>['propiedad_id'=>['type'=>'number'],'cliente_id'=>['type'=>'number'],'fecha'=>['type'=>'string'],'duracion_min'=>['type'=>'number'],'notas'=>['type'=>'string']],'required'=>['propiedad_id','fecha']]],
        ['name'=>'actualizar_visita','description'=>'Actualiza el estado de una visita (programada/realizada/cancelada/no_presentado).','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'estado'=>['type'=>'string']],'required'=>['id','estado']]],
        // Presupuestos
        ['name'=>'listar_presupuestos','description'=>'Lista presupuestos del CRM.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_presupuesto','description'=>'Datos completos de un presupuesto.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'crear_presupuesto','description'=>'Crea un nuevo presupuesto.','inputSchema'=>['type'=>'object','properties'=>['titulo'=>['type'=>'string'],'cliente_id'=>['type'=>'number'],'total'=>['type'=>'number'],'detalles'=>['type'=>'string'],'validez_dias'=>['type'=>'number'],'notas'=>['type'=>'string']],'required'=>['total']]],
        // Facturas
        ['name'=>'listar_facturas','description'=>'Lista facturas del CRM.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_factura','description'=>'Datos completos de una factura.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        // Contratos
        ['name'=>'listar_contratos','description'=>'Lista contratos del CRM.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_contrato','description'=>'Datos completos de un contrato.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'enviar_contrato','description'=>'Envía un contrato por email al cliente para que lo firme.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        // Comunicación
        ['name'=>'enviar_whatsapp','description'=>'Envía un WhatsApp a un prospecto via Twilio.','inputSchema'=>['type'=>'object','properties'=>['prospecto_id'=>['type'=>'number'],'mensaje'=>['type'=>'string']],'required'=>['prospecto_id','mensaje']]],
        ['name'=>'enviar_email','description'=>'Envía un email a un prospecto.','inputSchema'=>['type'=>'object','properties'=>['prospecto_id'=>['type'=>'number'],'asunto'=>['type'=>'string'],'cuerpo_html'=>['type'=>'string']],'required'=>['prospecto_id','asunto','cuerpo_html']]],
        // Automatizaciones
        ['name'=>'listar_automatizaciones','description'=>'Lista automatizaciones disponibles.','inputSchema'=>['type'=>'object','properties'=>new stdClass]],
        ['name'=>'iniciar_automatizacion','description'=>'Inicia una automatización sobre un prospecto.','inputSchema'=>['type'=>'object','properties'=>['automatizacion_id'=>['type'=>'number'],'prospecto_id'=>['type'=>'number']],'required'=>['automatizacion_id','prospecto_id']]],
        // Finanzas
        ['name'=>'listar_finanzas','description'=>'Lista registros financieros/comisiones del CRM.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'tipo'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'crear_finanza','description'=>'Crea un registro financiero o comisión.','inputSchema'=>['type'=>'object','properties'=>['concepto'=>['type'=>'string'],'importe'=>['type'=>'number'],'tipo'=>['type'=>'string'],'iva'=>['type'=>'number'],'fecha'=>['type'=>'string'],'estado'=>['type'=>'string'],'propiedad_id'=>['type'=>'number'],'cliente_id'=>['type'=>'number'],'notas'=>['type'=>'string']],'required'=>['concepto','importe']]],
        ['name'=>'marcar_cobrado','description'=>'Marca un registro financiero como cobrado.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'informe_finanzas','description'=>'Informe financiero de los últimos 6 meses.','inputSchema'=>['type'=>'object','properties'=>new stdClass]],
        // Calendario
        ['name'=>'ver_calendario','description'=>'Lista eventos del calendario en un rango de fechas.','inputSchema'=>['type'=>'object','properties'=>['desde'=>['type'=>'string'],'hasta'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'crear_evento','description'=>'Crea un evento en el calendario.','inputSchema'=>['type'=>'object','properties'=>['titulo'=>['type'=>'string'],'fecha_inicio'=>['type'=>'string'],'fecha_fin'=>['type'=>'string'],'tipo'=>['type'=>'string'],'descripcion'=>['type'=>'string'],'ubicacion'=>['type'=>'string'],'todo_dia'=>['type'=>'boolean'],'cliente_id'=>['type'=>'number'],'propiedad_id'=>['type'=>'number']],'required'=>['titulo','fecha_inicio']]],
        // Otros
        ['name'=>'listar_campanas','description'=>'Lista campañas de marketing del CRM.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'listar_documentos','description'=>'Lista documentos de un cliente o propiedad.','inputSchema'=>['type'=>'object','properties'=>['cliente_id'=>['type'=>'number'],'propiedad_id'=>['type'=>'number'],'limite'=>['type'=>'number']]]],
        ['name'=>'pipeline_kanban','description'=>'Vista Kanban de un pipeline de ventas.','inputSchema'=>['type'=>'object','properties'=>['pipeline_id'=>['type'=>'number']]]],
        ['name'=>'portales_propiedad','description'=>'Muestra en qué portales está publicada una propiedad.','inputSchema'=>['type'=>'object','properties'=>['propiedad_id'=>['type'=>'number']],'required'=>['propiedad_id']]],
        ['name'=>'publicar_portal','description'=>'Publica o retira una propiedad de un portal inmobiliario.','inputSchema'=>['type'=>'object','properties'=>['propiedad_id'=>['type'=>'number'],'portal_id'=>['type'=>'number'],'accion'=>['type'=>'string','enum'=>['publicar','retirar']],'url'=>['type'=>'string'],'notas'=>['type'=>'string']],'required'=>['propiedad_id','portal_id']]],
    ];
}

// ══════════════════════════════════════════════════════════════════════════════
// TOOL DISPATCH
// ══════════════════════════════════════════════════════════════════════════════

function callMcpTool(PDO $db, array $user, bool $isAdmin, int $userId, string $tool, array $args): mixed {
    $map = [
        // Dashboard
        'resumen_dashboard'      => ['resumen',              'GET',  []],
        'estadisticas'           => ['estadisticas',         'GET',  ['periodo' => $args['periodo'] ?? 'mes']],
        'buscar'                 => ['buscar',               'GET',  ['q' => $args['q'] ?? '']],
        // Prospectos
        'listar_prospectos'      => ['prospectos',           'GET',  ['q' => $args['busqueda'] ?? '', 'etapa' => $args['etapa'] ?? '', 'temperatura' => $args['temperatura'] ?? '', 'contactar_hoy' => ($args['contactar_hoy'] ?? false) ? '1' : '', 'limit' => $args['limite'] ?? 20]],
        'ver_prospecto'          => ['prospecto',            'GET',  ['id' => $args['id'] ?? 0]],
        'crear_prospecto'        => ['crear_prospecto',      'POST', []],
        'actualizar_prospecto'   => ['actualizar_prospecto', 'POST', []],
        'programar_contacto'     => ['programar_contacto',   'POST', []],
        'mover_etapas'           => ['mover_etapas',         'POST', []],
        'anadir_nota'            => ['anadir_nota',          'POST', []],
        'convertir_a_cliente'    => ['convertir_cliente',    'POST', []],
        'informe_prospectos'     => ['informe_prospectos',   'GET',  []],
        // Clientes
        'listar_clientes'        => ['clientes',             'GET',  ['q' => $args['busqueda'] ?? '', 'tipo' => $args['tipo'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'ver_cliente'            => ['cliente',              'GET',  ['id' => $args['id'] ?? 0]],
        'crear_cliente'          => ['crear_cliente',        'POST', []],
        'actualizar_cliente'     => ['actualizar_cliente',   'POST', []],
        'anadir_nota_cliente'    => ['anadir_nota_cliente',  'POST', []],
        // Tareas
        'listar_tareas'          => ['tareas',               'GET',  ['estado' => $args['estado'] ?? '', 'solo_hoy' => ($args['solo_hoy'] ?? false) ? '1' : '0']],
        'crear_tarea'            => ['crear_tarea',          'POST', []],
        'completar_tarea'        => ['completar_tarea',      'POST', []],
        'actualizar_tarea'       => ['actualizar_tarea',     'POST', []],
        'cancelar_tarea'         => ['cancelar_tarea',       'POST', []],
        // Propiedades
        'listar_propiedades'     => ['propiedades',          'GET',  ['q' => $args['busqueda'] ?? '', 'estado' => $args['estado'] ?? '', 'max' => $args['precio_maximo'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'ver_propiedad'          => ['propiedad',            'GET',  ['id' => $args['id'] ?? 0]],
        'crear_propiedad'        => ['crear_propiedad',      'POST', []],
        'actualizar_propiedad'   => ['actualizar_propiedad', 'POST', []],
        // Visitas
        'listar_visitas'         => ['visitas',              'GET',  ['estado' => $args['estado'] ?? '', 'solo_hoy' => ($args['solo_hoy'] ?? false) ? '1' : '0', 'limit' => $args['limite'] ?? 20]],
        'crear_visita'           => ['crear_visita',         'POST', []],
        'actualizar_visita'      => ['actualizar_visita',    'POST', []],
        // Presupuestos
        'listar_presupuestos'    => ['presupuestos',         'GET',  ['estado' => $args['estado'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'ver_presupuesto'        => ['presupuesto',          'GET',  ['id' => $args['id'] ?? 0]],
        'crear_presupuesto'      => ['crear_presupuesto',    'POST', []],
        // Facturas
        'listar_facturas'        => ['facturas',             'GET',  ['estado' => $args['estado'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'ver_factura'            => ['factura',              'GET',  ['id' => $args['id'] ?? 0]],
        // Contratos
        'listar_contratos'       => ['contratos',            'GET',  ['estado' => $args['estado'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'ver_contrato'           => ['contrato',             'GET',  ['id' => $args['id'] ?? 0]],
        'enviar_contrato'        => ['enviar_contrato',      'POST', []],
        // Comunicación
        'enviar_whatsapp'        => ['enviar_whatsapp',      'POST', []],
        'enviar_email'           => ['enviar_email',         'POST', []],
        // Automatizaciones
        'listar_automatizaciones'=> ['automatizaciones',     'GET',  []],
        'iniciar_automatizacion' => ['iniciar_automatizacion','POST',[]],
        // Finanzas
        'listar_finanzas'        => ['finanzas',             'GET',  ['estado' => $args['estado'] ?? '', 'tipo' => $args['tipo'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'crear_finanza'          => ['crear_finanza',        'POST', []],
        'marcar_cobrado'         => ['marcar_cobrado',       'POST', []],
        'informe_finanzas'       => ['informe_finanzas',     'GET',  []],
        // Calendario
        'ver_calendario'         => ['calendario',           'GET',  ['desde' => $args['desde'] ?? '', 'hasta' => $args['hasta'] ?? '', 'limit' => $args['limite'] ?? 50]],
        'crear_evento'           => ['crear_evento',         'POST', []],
        // Otros
        'listar_campanas'        => ['campanas',             'GET',  ['estado' => $args['estado'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'listar_documentos'      => ['documentos',           'GET',  ['cliente_id' => $args['cliente_id'] ?? 0, 'propiedad_id' => $args['propiedad_id'] ?? 0, 'limit' => $args['limite'] ?? 20]],
        'pipeline_kanban'        => ['pipeline_kanban',      'GET',  ['pipeline_id' => $args['pipeline_id'] ?? 0]],
        'portales_propiedad'     => ['portales_propiedad',   'GET',  ['propiedad_id' => $args['propiedad_id'] ?? 0]],
        'publicar_portal'        => ['publicar_portal',      'POST', []],
    ];

    if (!isset($map[$tool])) throw new Exception("Herramienta desconocida: $tool");
    [$action, $httpMethod, $getParams] = $map[$tool];
    return callMcpApi($db, $isAdmin, $userId, $action, $httpMethod, $getParams, $httpMethod === 'POST' ? $args : []);
}

function callMcpApi(PDO $db, bool $isAdmin, int $userId, string $action, string $method, array $getParams, array $postBody): mixed {
    ob_start();
    callActionInline($db, $isAdmin, $userId, $action, $getParams, $postBody);
    $output = ob_get_clean();

    $data = json_decode($output, true);
    if ($data === null) throw new Exception("Respuesta inválida del API: " . substr($output, 0, 300));
    if (isset($data['error'])) throw new Exception($data['error']);
    return $data;
}

// ══════════════════════════════════════════════════════════════════════════════
// ACTION HANDLERS
// ══════════════════════════════════════════════════════════════════════════════

function callActionInline(PDO $db, bool $isAdmin, int $userId, string $action, array $get, array $body): void {
    if (!function_exists('_ok')) {
        function _ok(array $data): void {
            echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        function _err(string $msg, int $code = 400): void {
            http_response_code($code);
            echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
        }
        function _af(bool $isAdmin, int $userId, string $alias = 'p'): array {
            if ($isAdmin) return ['', []];
            return [" AND {$alias}.agente_id = ?", [$userId]];
        }
    }

    try { switch ($action) {

        case 'resumen': {
            $af  = $isAdmin ? '' : " AND agente_id = $userId";
            $afP = $isAdmin ? '' : " AND p.agente_id = $userId";
            $stmt = $db->query("SELECT etapa, COUNT(*) as total FROM prospectos WHERE activo = 1 $af GROUP BY etapa ORDER BY FIELD(etapa,'nuevo_lead','contactado','seguimiento','visita_programada','captado','descartado')");
            $porEtapa = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $db->query("SELECT COUNT(*) FROM clientes WHERE activo = 1 $af");
            $totalClientes = (int)$stmt->fetchColumn();
            $stmt = $db->prepare("SELECT COUNT(*) FROM tareas WHERE estado='pendiente' AND DATE(fecha_vencimiento)=CURDATE() AND (asignado_a=? OR creado_por=?)");
            $stmt->execute([$userId, $userId]);
            $tareasPendientesHoy = (int)$stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos p WHERE p.activo=1 AND p.fecha_proximo_contacto=CURDATE() $afP");
            $contactosHoy = (int)$stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos WHERE activo=1 AND etapa NOT IN ('captado','descartado') $af");
            $pipeline = (int)$stmt->fetchColumn();
            $stmt = $db->prepare("SELECT COUNT(*) FROM tareas WHERE estado='pendiente' AND fecha_vencimiento < NOW() AND (asignado_a=? OR creado_por=?)");
            $stmt->execute([$userId, $userId]);
            $tareasVencidas = (int)$stmt->fetchColumn();
            _ok(['resumen' => ['prospectos_activos_pipeline' => $pipeline, 'prospectos_por_etapa' => $porEtapa, 'total_clientes' => $totalClientes, 'tareas_pendientes_hoy' => $tareasPendientesHoy, 'tareas_vencidas' => $tareasVencidas, 'prospectos_a_contactar_hoy' => $contactosHoy]]);
            break;
        }

        case 'estadisticas': {
            $periodo = $get['periodo'] ?? 'mes';
            $af = $isAdmin ? '' : " AND usuario_id = $userId";
            switch ($periodo) {
                case 'hoy':       $desde = 'CURDATE()'; $hasta = 'CURDATE()'; break;
                case 'semana':    $desde = 'DATE_SUB(CURDATE(), INTERVAL 7 DAY)'; $hasta = 'CURDATE()'; break;
                case 'trimestre': $desde = 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)'; $hasta = 'CURDATE()'; break;
                case 'anio':      $desde = 'DATE_FORMAT(CURDATE(), "%Y-01-01")'; $hasta = 'CURDATE()'; break;
                default:          $desde = 'DATE_FORMAT(CURDATE(), "%Y-%m-01")'; $hasta = 'CURDATE()';
            }
            $stmt = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(total),0) as importe FROM facturas WHERE estado='pagada' AND DATE(fecha_emision) BETWEEN $desde AND $hasta $af");
            $facturas = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(total),0) as importe FROM presupuestos WHERE estado='aceptado' AND DATE(fecha_emision) BETWEEN $desde AND $hasta $af");
            $presupuestos = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $db->query("SELECT COUNT(*) FROM contratos WHERE estado='firmado' AND DATE(created_at) BETWEEN $desde AND $hasta $af");
            $contratosFirmados = (int)$stmt->fetchColumn();
            $afP = $isAdmin ? '' : " AND agente_id = $userId";
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos WHERE DATE(created_at) BETWEEN $desde AND $hasta $afP");
            $prospectoNuevos = (int)$stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos WHERE etapa='captado' AND DATE(updated_at) BETWEEN $desde AND $hasta $afP");
            $captados = (int)$stmt->fetchColumn();
            _ok(['estadisticas' => ['periodo' => $periodo, 'facturas_cobradas' => (int)$facturas['total'], 'importe_cobrado' => round((float)$facturas['importe'], 2), 'presupuestos_aceptados' => (int)$presupuestos['total'], 'importe_presupuestado' => round((float)$presupuestos['importe'], 2), 'contratos_firmados' => $contratosFirmados, 'prospectos_nuevos' => $prospectoNuevos, 'prospectos_captados' => $captados]]);
            break;
        }

        case 'buscar': {
            $q = trim($get['q'] ?? '');
            if (!$q) { _err('Parámetro q requerido'); break; }
            $like = "%$q%";
            $af = $isAdmin ? '' : " AND agente_id = $userId";
            $stmt = $db->prepare("SELECT id, nombre, telefono, email, etapa, temperatura FROM prospectos WHERE activo=1 AND (nombre LIKE ? OR telefono LIKE ? OR email LIKE ?) $af LIMIT 10");
            $stmt->execute([$like, $like, $like]);
            $prospectos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $db->prepare("SELECT id, CONCAT(nombre,' ',COALESCE(apellidos,'')) as nombre, tipo, email, telefono FROM clientes WHERE activo=1 AND (nombre LIKE ? OR apellidos LIKE ? OR email LIKE ? OR telefono LIKE ?) $af LIMIT 10");
            $stmt->execute([$like, $like, $like, $like]);
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $db->prepare("SELECT id, referencia, titulo, tipo, estado, precio FROM propiedades WHERE (titulo LIKE ? OR referencia LIKE ? OR localidad LIKE ?) $af LIMIT 10");
            $stmt->execute([$like, $like, $like]);
            $propiedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            _ok(['prospectos' => $prospectos, 'clientes' => $clientes, 'propiedades' => $propiedades]);
            break;
        }

        case 'prospectos': {
            $q            = '%' . ($get['q'] ?? '') . '%';
            $etapa        = $get['etapa'] ?? '';
            $temp         = $get['temperatura'] ?? '';
            $contactarHoy = ($get['contactar_hoy'] ?? '') === '1';
            $limit        = min(100, max(1, (int)($get['limit'] ?? 20)));
            $where = ['p.activo = 1']; $params = [];
            if (!$isAdmin)     { $where[] = 'p.agente_id = ?';   $params[] = $userId; }
            if ($get['q'] ?? '') { $where[] = '(p.nombre LIKE ? OR p.telefono LIKE ? OR p.email LIKE ?)'; $params = array_merge($params, [$q, $q, $q]); }
            if ($etapa)        { $where[] = 'p.etapa = ?';        $params[] = $etapa; }
            if ($temp)         { $where[] = 'p.temperatura = ?';  $params[] = $temp; }
            if ($contactarHoy) { $where[] = 'p.fecha_proximo_contacto = CURDATE()'; }
            $sql = "SELECT p.id, p.referencia, p.nombre, p.telefono, p.email, p.etapa, p.temperatura, p.tipo_propiedad, p.precio_estimado, p.localidad, p.provincia, DATE_FORMAT(p.fecha_proximo_contacto,'%d/%m/%Y') as fecha_contacto, p.proxima_accion FROM prospectos p WHERE " . implode(' AND ', $where) . " ORDER BY p.fecha_proximo_contacto ASC, p.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            _ok(['total' => count($rows), 'prospectos' => $rows]);
            break;
        }

        case 'prospecto': {
            $id = (int)($get['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            [$af, $ap] = _af($isAdmin, $userId);
            $stmt = $db->prepare("SELECT p.*, u.nombre as agente_nombre FROM prospectos p LEFT JOIN usuarios u ON p.agente_id = u.id WHERE p.id = ? $af LIMIT 1");
            $stmt->execute(array_merge([$id], $ap));
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { _err('Prospecto no encontrado', 404); break; }
            $h = $db->prepare("SELECT tipo, contenido, DATE_FORMAT(COALESCE(fecha_evento,created_at),'%d/%m/%Y %H:%i') as fecha FROM historial_prospectos WHERE prospecto_id = ? ORDER BY COALESCE(fecha_evento,created_at) DESC LIMIT 10");
            $h->execute([$id]);
            $p['historial_reciente'] = $h->fetchAll(PDO::FETCH_ASSOC);
            unset($p['propietarios_json']);
            _ok(['prospecto' => $p]);
            break;
        }

        case 'crear_prospecto': {
            $nombre = trim($body['nombre'] ?? '');
            if (!$nombre) { _err('El nombre es obligatorio'); break; }
            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(referencia,3) AS UNSIGNED)) FROM prospectos WHERE referencia LIKE 'PR%'");
            $ref = 'PR' . str_pad((int)$stmt->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
            $ins = $db->prepare("INSERT INTO prospectos (referencia, nombre, telefono, email, tipo_propiedad, precio_estimado, localidad, provincia, notas, etapa, temperatura, agente_id, activo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)");
            $ins->execute([$ref, $nombre, $body['telefono'] ?? null, $body['email'] ?? null, $body['tipo_propiedad'] ?? null, isset($body['precio_estimado']) ? (float)$body['precio_estimado'] : null, $body['localidad'] ?? null, $body['provincia'] ?? null, $body['notas'] ?? null, $body['etapa'] ?? 'nuevo_lead', $body['temperatura'] ?? 'frio', $userId]);
            $newId = (int)$db->lastInsertId();
            if (function_exists('registrarActividad')) registrarActividad('crear', 'prospecto', $newId, "Prospecto $ref creado por IA");
            _ok(['id' => $newId, 'referencia' => $ref, 'mensaje' => "Prospecto $ref creado correctamente"]);
            break;
        }

        case 'actualizar_prospecto': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            [$af, $ap] = _af($isAdmin, $userId);
            $chk = $db->prepare("SELECT id FROM prospectos WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$id], $ap));
            if (!$chk->fetch()) { _err('Prospecto no encontrado o sin acceso', 403); break; }
            $campos = []; $params = [];
            $etapasOk = ['nuevo_lead','contactado','seguimiento','visita_programada','negociacion','captado','descartado'];
            $tempsOk  = ['frio','templado','caliente'];
            if (!empty($body['etapa']) && in_array($body['etapa'], $etapasOk)) { $campos[] = 'etapa = ?'; $params[] = $body['etapa']; }
            if (!empty($body['temperatura']) && in_array($body['temperatura'], $tempsOk)) { $campos[] = 'temperatura = ?'; $params[] = $body['temperatura']; }
            if (isset($body['notas_internas'])) { $campos[] = 'notas = ?'; $params[] = $body['notas_internas']; }
            if (isset($body['fecha_proximo_contacto'])) { $campos[] = 'fecha_proximo_contacto = ?'; $params[] = $body['fecha_proximo_contacto']; }
            if (isset($body['proxima_accion'])) { $campos[] = 'proxima_accion = ?'; $params[] = $body['proxima_accion']; }
            if (empty($campos)) { _err('Nada que actualizar'); break; }
            $params[] = $id;
            $db->prepare("UPDATE prospectos SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            _ok(['mensaje' => 'Prospecto actualizado correctamente']);
            break;
        }

        case 'programar_contacto': {
            $fecha  = trim($body['fecha'] ?? '');
            $accion = trim($body['proxima_accion'] ?? '');
            $temp   = trim($body['temperatura'] ?? '');
            $etapa  = trim($body['etapa'] ?? '');
            $ids = [];
            if (!empty($body['id']))  $ids[] = (int)$body['id'];
            if (!empty($body['ids'])) { $raw = is_array($body['ids']) ? $body['ids'] : explode(',', $body['ids']); foreach ($raw as $r) { $v = (int)trim($r); if ($v > 0) $ids[] = $v; } }
            $ids = array_unique(array_filter($ids));
            if (empty($ids)) { _err('Debes indicar id o ids de los prospectos'); break; }
            $campos = []; $params = [];
            if ($fecha)  { $campos[] = 'fecha_proximo_contacto = ?'; $params[] = $fecha; }
            if ($accion) { $campos[] = 'proxima_accion = ?';         $params[] = $accion; }
            $tempsOk = ['frio','templado','caliente'];
            if ($temp && in_array($temp, $tempsOk)) { $campos[] = 'temperatura = ?'; $params[] = $temp; }
            $etapasOk = ['nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado'];
            if ($etapa && in_array($etapa, $etapasOk)) { $campos[] = 'etapa = ?'; $params[] = $etapa; }
            if (empty($campos)) { _err('Debes indicar al menos fecha, proxima_accion, temperatura o etapa'); break; }
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $af = $isAdmin ? '' : " AND agente_id = $userId";
            $db->prepare("UPDATE prospectos SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id IN ($ph) $af")->execute(array_merge($params, $ids));
            _ok(['mensaje' => count($ids) . ' prospecto(s) actualizados', 'ids' => $ids]);
            break;
        }

        case 'mover_etapas': {
            $etapa = trim($body['etapa'] ?? '');
            $etapasOk = ['nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado'];
            if (!in_array($etapa, $etapasOk)) { _err('Etapa inválida. Válidas: ' . implode(', ', $etapasOk)); break; }
            $ids = [];
            if (!empty($body['id']))  $ids[] = (int)$body['id'];
            if (!empty($body['ids'])) { $raw = is_array($body['ids']) ? $body['ids'] : explode(',', $body['ids']); foreach ($raw as $r) { $v = (int)trim($r); if ($v > 0) $ids[] = $v; } }
            $ids = array_unique(array_filter($ids));
            if (empty($ids)) { _err('Debes indicar id o ids'); break; }
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $af = $isAdmin ? '' : " AND agente_id = $userId";
            $db->prepare("UPDATE prospectos SET etapa = ?, updated_at = NOW() WHERE id IN ($ph) $af")->execute(array_merge([$etapa], $ids));
            _ok(['mensaje' => count($ids) . ' prospecto(s) movidos a etapa "' . $etapa . '"', 'ids' => $ids]);
            break;
        }

        case 'anadir_nota': {
            $pid = (int)($body['prospecto_id'] ?? 0);
            $contenido = trim($body['contenido'] ?? '');
            $tiposOk = ['nota','llamada','email','visita','whatsapp','otro'];
            $tipo = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'nota';
            if (!$pid || !$contenido) { _err('prospecto_id y contenido son obligatorios'); break; }
            [$af, $ap] = _af($isAdmin, $userId);
            $chk = $db->prepare("SELECT id FROM prospectos WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$pid], $ap));
            if (!$chk->fetch()) { _err('Prospecto no encontrado o sin acceso', 403); break; }
            $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?,?,?,?)")->execute([$pid, $userId, $contenido, $tipo]);
            $db->prepare("UPDATE prospectos SET updated_at = NOW() WHERE id = ?")->execute([$pid]);
            _ok(['mensaje' => 'Nota añadida correctamente', 'id' => (int)$db->lastInsertId()]);
            break;
        }

        case 'convertir_cliente': {
            $id = (int)($body['prospecto_id'] ?? $body['id'] ?? 0);
            if (!$id) { _err('prospecto_id requerido'); break; }
            [$af, $ap] = _af($isAdmin, $userId);
            $stmt = $db->prepare("SELECT * FROM prospectos WHERE id = ? $af LIMIT 1");
            $stmt->execute(array_merge([$id], $ap));
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { _err('Prospecto no encontrado o sin acceso', 403); break; }
            if ($p['email']) { $dup = $db->prepare("SELECT id FROM clientes WHERE email = ? LIMIT 1"); $dup->execute([$p['email']]); if ($dup->fetchColumn()) { _err('Ya existe un cliente con ese email'); break; } }
            $tipo = $body['tipo'] ?? 'vendedor';
            $db->prepare("INSERT INTO clientes (nombre, apellidos, email, telefono, tipo, localidad, provincia, notas, agente_id, activo, created_at) VALUES (?,?,?,?,?,?,?,?,?,1,NOW())")->execute([$p['nombre'], null, $p['email'], $p['telefono'], $tipo, $p['localidad'], $p['provincia'], $p['notas'], $p['agente_id'] ?? $userId]);
            $clienteId = (int)$db->lastInsertId();
            $db->prepare("UPDATE prospectos SET etapa='captado', activo=0, updated_at=NOW() WHERE id=?")->execute([$id]);
            _ok(['cliente_id' => $clienteId, 'mensaje' => "Prospecto convertido a cliente #$clienteId"]);
            break;
        }

        case 'informe_prospectos': {
            $af  = $isAdmin ? '' : " AND agente_id = $userId";
            $afP = $isAdmin ? '' : " AND p.agente_id = $userId";
            $stmt = $db->query("SELECT etapa, COUNT(*) as total, temperatura, AVG(precio_estimado) as precio_medio FROM prospectos WHERE activo=1 $af GROUP BY etapa, temperatura ORDER BY FIELD(etapa,'nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado'), temperatura");
            $embudo = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $db->query("SELECT temperatura, COUNT(*) as total FROM prospectos WHERE activo=1 $af GROUP BY temperatura");
            $temperaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos p WHERE activo=1 AND fecha_proximo_contacto BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) $afP");
            $semana = (int)$stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos p WHERE activo=1 AND fecha_proximo_contacto < CURDATE() AND etapa NOT IN ('captado','descartado') $afP");
            $vencidos = (int)$stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos p WHERE etapa='captado' AND MONTH(updated_at)=MONTH(CURDATE()) AND YEAR(updated_at)=YEAR(CURDATE()) $afP");
            $captadosMes = (int)$stmt->fetchColumn();
            _ok(['informe' => ['embudo_por_etapa_temperatura' => $embudo, 'por_temperatura' => $temperaturas, 'a_contactar_esta_semana' => $semana, 'vencidos_sin_contactar' => $vencidos, 'captados_este_mes' => $captadosMes]]);
            break;
        }

        case 'clientes': {
            $q     = '%' . ($get['q'] ?? '') . '%';
            $tipo  = $get['tipo'] ?? '';
            $limit = min(100, max(1, (int)($get['limit'] ?? 20)));
            $where = ['c.activo = 1']; $params = [];
            if (!$isAdmin) { $where[] = 'c.agente_id = ?'; $params[] = $userId; }
            if ($get['q'] ?? '') { $where[] = '(c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.apellidos LIKE ?)'; $params = array_merge($params, [$q, $q, $q, $q]); }
            if ($tipo) { $where[] = 'FIND_IN_SET(?, c.tipo)'; $params[] = $tipo; }
            $sql = "SELECT c.id, c.nombre, c.apellidos, c.tipo, c.email, c.telefono, c.localidad, c.provincia, DATE_FORMAT(c.created_at,'%d/%m/%Y') as alta FROM clientes c WHERE " . implode(' AND ', $where) . " ORDER BY c.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            _ok(['clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'cliente': {
            $id = (int)($get['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            [$af, $ap] = _af($isAdmin, $userId, 'c');
            $stmt = $db->prepare("SELECT c.*, u.nombre as agente_nombre FROM clientes c LEFT JOIN usuarios u ON c.agente_id = u.id WHERE c.id = ? $af LIMIT 1");
            $stmt->execute(array_merge([$id], $ap));
            $c = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$c) { _err('Cliente no encontrado', 404); break; }
            $stmt2 = $db->prepare("SELECT id, titulo, total, estado, DATE_FORMAT(fecha_emision,'%d/%m/%Y') as fecha FROM presupuestos WHERE cliente_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt2->execute([$id]);
            $c['presupuestos_recientes'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            _ok(['cliente' => $c]);
            break;
        }

        case 'crear_cliente': {
            $nombre = trim($body['nombre'] ?? '');
            if (!$nombre) { _err('El nombre es obligatorio'); break; }
            $db->prepare("INSERT INTO clientes (nombre, apellidos, tipo, email, telefono, localidad, provincia, notas, agente_id, activo) VALUES (?,?,?,?,?,?,?,?,?,1)")->execute([$nombre, $body['apellidos'] ?? null, $body['tipo'] ?? 'comprador', $body['email'] ?? null, $body['telefono'] ?? null, $body['localidad'] ?? null, $body['provincia'] ?? null, $body['notas'] ?? null, $userId]);
            $newId = (int)$db->lastInsertId();
            _ok(['id' => $newId, 'mensaje' => 'Cliente creado correctamente']);
            break;
        }

        case 'actualizar_cliente': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            [$af, $ap] = _af($isAdmin, $userId, 'c');
            $chk = $db->prepare("SELECT id FROM clientes c WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$id], $ap));
            if (!$chk->fetch()) { _err('Cliente no encontrado o sin acceso', 403); break; }
            $campos = []; $params = [];
            foreach (['nombre','apellidos','tipo','email','telefono','localidad','provincia','notas'] as $f) {
                if (isset($body[$f])) { $campos[] = "$f = ?"; $params[] = $body[$f]; }
            }
            if (empty($campos)) { _err('Nada que actualizar'); break; }
            $params[] = $id;
            $db->prepare("UPDATE clientes SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            _ok(['mensaje' => 'Cliente actualizado correctamente']);
            break;
        }

        case 'anadir_nota_cliente': {
            $clienteId = (int)($body['cliente_id'] ?? 0);
            $contenido = trim($body['contenido'] ?? '');
            if (!$clienteId || !$contenido) { _err('cliente_id y contenido son obligatorios'); break; }
            [$af, $ap] = _af($isAdmin, $userId, 'c');
            $chk = $db->prepare("SELECT id FROM clientes c WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$clienteId], $ap));
            if (!$chk->fetch()) { _err('Cliente no encontrado o sin acceso', 403); break; }
            $tipo = $body['tipo'] ?? 'nota';
            $db->prepare("INSERT INTO calendario_eventos (titulo, tipo, fecha_inicio, fecha_fin, todo_dia, cliente_id, usuario_id, descripcion) VALUES (?,?,NOW(),NOW(),1,?,?,?)")->execute(["Nota: " . substr($contenido, 0, 50), $tipo, $clienteId, $userId, $contenido]);
            _ok(['mensaje' => 'Nota añadida al cliente', 'id' => (int)$db->lastInsertId()]);
            break;
        }

        case 'tareas': {
            $estado  = $get['estado'] ?? '';
            $soloHoy = ($get['solo_hoy'] ?? '') === '1';
            $where = ['(t.asignado_a = ? OR t.creado_por = ?)']; $params = [$userId, $userId];
            if ($estado)  { $where[] = 't.estado = ?'; $params[] = $estado; }
            if ($soloHoy) { $where[] = 'DATE(t.fecha_vencimiento) = CURDATE()'; }
            $sql = "SELECT t.id, t.titulo, t.tipo, t.estado, t.prioridad, DATE_FORMAT(t.fecha_vencimiento,'%d/%m/%Y %H:%i') as vencimiento FROM tareas t WHERE " . implode(' AND ', $where) . " ORDER BY t.fecha_vencimiento ASC LIMIT 50";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            _ok(['tareas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'crear_tarea': {
            $titulo = trim($body['titulo'] ?? '');
            if (!$titulo) { _err('El título es obligatorio'); break; }
            $tiposOk = ['llamada','email','reunion','visita','otro'];
            $tipo = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'otro';
            $db->prepare("INSERT INTO tareas (titulo, tipo, descripcion, prioridad, estado, fecha_vencimiento, asignado_a, creado_por, cliente_id, propiedad_id) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$titulo, $tipo, $body['descripcion'] ?? null, $body['prioridad'] ?? 'media', 'pendiente', $body['fecha_vencimiento'] ?? null, $userId, $userId, isset($body['cliente_id']) ? (int)$body['cliente_id'] : null, isset($body['propiedad_id']) ? (int)$body['propiedad_id'] : null]);
            _ok(['id' => (int)$db->lastInsertId(), 'mensaje' => 'Tarea creada correctamente']);
            break;
        }

        case 'completar_tarea': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            $stmt = $db->prepare("UPDATE tareas SET estado='completada', updated_at=NOW() WHERE id=? AND (asignado_a=? OR creado_por=?)");
            $stmt->execute([$id, $userId, $userId]);
            if (!$stmt->rowCount()) { _err('Tarea no encontrada o sin acceso', 403); break; }
            _ok(['mensaje' => 'Tarea marcada como completada']);
            break;
        }

        case 'actualizar_tarea': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            $stmt = $db->prepare("SELECT id FROM tareas WHERE id=? AND (asignado_a=? OR creado_por=?) LIMIT 1");
            $stmt->execute([$id, $userId, $userId]);
            if (!$stmt->fetch()) { _err('Tarea no encontrada o sin acceso', 403); break; }
            $campos = []; $params = [];
            foreach (['titulo','descripcion','tipo','prioridad','fecha_vencimiento'] as $f) {
                if (isset($body[$f])) { $campos[] = "$f = ?"; $params[] = $body[$f]; }
            }
            $estadosOk = ['pendiente','en_progreso','completada','cancelada'];
            if (!empty($body['estado']) && in_array($body['estado'], $estadosOk)) {
                $campos[] = 'estado = ?'; $params[] = $body['estado'];
                if ($body['estado'] === 'completada') { $campos[] = 'fecha_completada = NOW()'; }
            }
            if (empty($campos)) { _err('Nada que actualizar'); break; }
            $params[] = $id;
            $db->prepare("UPDATE tareas SET " . implode(', ', $campos) . ", updated_at=NOW() WHERE id=?")->execute($params);
            _ok(['mensaje' => 'Tarea actualizada correctamente']);
            break;
        }

        case 'cancelar_tarea': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            $stmt = $db->prepare("UPDATE tareas SET estado='cancelada', updated_at=NOW() WHERE id=? AND (asignado_a=? OR creado_por=?)");
            $stmt->execute([$id, $userId, $userId]);
            if (!$stmt->rowCount()) { _err('Tarea no encontrada o sin acceso', 403); break; }
            _ok(['mensaje' => 'Tarea cancelada']);
            break;
        }

        case 'propiedades': {
            $q     = '%' . ($get['q'] ?? '') . '%';
            $est   = $get['estado'] ?? '';
            $max   = isset($get['max']) && $get['max'] !== '' ? (float)$get['max'] : null;
            $limit = min(100, max(1, (int)($get['limit'] ?? 20)));
            $where = ['1=1']; $params = [];
            if (!$isAdmin) { $where[] = 'p.agente_id = ?'; $params[] = $userId; }
            if ($get['q'] ?? '') { $where[] = '(p.titulo LIKE ? OR p.referencia LIKE ? OR p.localidad LIKE ?)'; $params = array_merge($params, [$q, $q, $q]); }
            if ($est) { $where[] = 'p.estado = ?'; $params[] = $est; }
            if ($max) { $where[] = 'p.precio <= ?'; $params[] = $max; }
            $sql = "SELECT p.id, p.referencia, p.titulo, p.tipo, p.estado, p.precio, p.localidad, p.provincia, p.habitaciones, p.banos, p.superficie_construida, p.superficie_util FROM propiedades p WHERE " . implode(' AND ', $where) . " ORDER BY p.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            _ok(['propiedades' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'propiedad': {
            $id = (int)($get['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            [$af, $ap] = _af($isAdmin, $userId, 'p');
            $stmt = $db->prepare("SELECT p.*, u.nombre as agente_nombre FROM propiedades p LEFT JOIN usuarios u ON p.agente_id = u.id WHERE p.id = ? $af LIMIT 1");
            $stmt->execute(array_merge([$id], $ap));
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { _err('Propiedad no encontrada', 404); break; }
            $f = $db->prepare("SELECT url FROM propiedad_fotos WHERE propiedad_id = ? ORDER BY orden ASC LIMIT 5");
            $f->execute([$id]);
            $p['fotos'] = $f->fetchAll(PDO::FETCH_COLUMN);
            _ok(['propiedad' => $p]);
            break;
        }

        case 'crear_propiedad': {
            $titulo = trim($body['titulo'] ?? '');
            if (!$titulo) { _err('El título es obligatorio'); break; }
            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(referencia,3) AS UNSIGNED)) FROM propiedades WHERE referencia LIKE 'IM%'");
            $ref = 'IM' . str_pad((int)$stmt->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
            $db->prepare("INSERT INTO propiedades (referencia, titulo, tipo, estado, precio, localidad, provincia, descripcion, habitaciones, banos, superficie_construida, agente_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$ref, $titulo, $body['tipo'] ?? 'piso', $body['estado'] ?? 'disponible', isset($body['precio']) ? (float)$body['precio'] : null, $body['localidad'] ?? null, $body['provincia'] ?? null, $body['descripcion'] ?? null, isset($body['habitaciones']) ? (int)$body['habitaciones'] : null, isset($body['banos']) ? (int)$body['banos'] : null, isset($body['metros']) ? (float)$body['metros'] : null, $userId]);
            $newId = (int)$db->lastInsertId();
            _ok(['id' => $newId, 'referencia' => $ref, 'mensaje' => "Propiedad $ref creada correctamente"]);
            break;
        }

        case 'actualizar_propiedad': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            [$af, $ap] = _af($isAdmin, $userId, 'p');
            $chk = $db->prepare("SELECT id FROM propiedades p WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$id], $ap));
            if (!$chk->fetch()) { _err('Propiedad no encontrada o sin acceso', 403); break; }
            $campos = []; $params = [];
            $estadosOk = ['disponible','reservado','vendido','alquilado','retirado'];
            if (!empty($body['estado']) && in_array($body['estado'], $estadosOk)) { $campos[] = 'estado = ?'; $params[] = $body['estado']; }
            if (isset($body['precio'])) { $campos[] = 'precio = ?'; $params[] = (float)$body['precio']; }
            if (isset($body['notas']))  { $campos[] = 'descripcion = ?'; $params[] = $body['notas']; }
            if (empty($campos)) { _err('Nada que actualizar'); break; }
            $params[] = $id;
            $db->prepare("UPDATE propiedades SET " . implode(', ', $campos) . ", updated_at=NOW() WHERE id=?")->execute($params);
            _ok(['mensaje' => 'Propiedad actualizada correctamente']);
            break;
        }

        case 'visitas': {
            $estado  = $get['estado'] ?? '';
            $soloHoy = ($get['solo_hoy'] ?? '') === '1';
            $limit   = min(100, max(1, (int)($get['limit'] ?? 20)));
            $af      = $isAdmin ? '' : " AND v.agente_id = $userId";
            $where = ['1=1']; $params = [];
            if ($estado)  { $where[] = 'v.estado = ?'; $params[] = $estado; }
            if ($soloHoy) { $where[] = 'v.fecha = CURDATE()'; }
            $sql = "SELECT v.id, v.estado, CONCAT(DATE_FORMAT(v.fecha,'%d/%m/%Y'),' ',COALESCE(TIME_FORMAT(v.hora,'%H:%i'),'')) as fecha, v.duracion_minutos, v.comentarios, p.titulo as propiedad, p.localidad, c.nombre as contacto FROM visitas v LEFT JOIN propiedades p ON v.propiedad_id = p.id LEFT JOIN clientes c ON v.cliente_id = c.id WHERE " . implode(' AND ', $where) . " $af ORDER BY v.fecha ASC, v.hora ASC LIMIT $limit";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            _ok(['visitas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'crear_visita': {
            $propId = (int)($body['propiedad_id'] ?? 0);
            $fecha  = trim($body['fecha'] ?? '');
            if (!$propId || !$fecha) { _err('propiedad_id y fecha son obligatorios'); break; }
            $ts = strtotime($fecha);
            if (!$ts) { _err('Formato de fecha inválido. Usa YYYY-MM-DD HH:MM'); break; }
            $fechaDate = date('Y-m-d', $ts);
            $fechaHora = date('H:i:s', $ts);
            $fechaDt   = date('Y-m-d H:i:s', $ts);
            $db->prepare("INSERT INTO visitas (propiedad_id, cliente_id, fecha, hora, duracion_minutos, comentarios, estado, agente_id) VALUES (?,?,?,?,?,?,'programada',?)")->execute([$propId, isset($body['cliente_id']) ? (int)$body['cliente_id'] : null, $fechaDate, $fechaHora, (int)($body['duracion_min'] ?? 60), $body['notas'] ?? null, $userId]);
            $newId = (int)$db->lastInsertId();
            $tituloTarea = 'Visita programada - ' . date('d/m/Y H:i', $ts);
            $db->prepare("INSERT INTO tareas (titulo, tipo, estado, fecha_vencimiento, asignado_a, creado_por, propiedad_id) VALUES (?,?,?,?,?,?,?)")->execute([$tituloTarea, 'visita', 'pendiente', $fechaDt, $userId, $userId, $propId]);
            _ok(['id' => $newId, 'mensaje' => "Visita programada para el $fecha"]);
            break;
        }

        case 'actualizar_visita': {
            $id     = (int)($body['id'] ?? 0);
            $estado = trim($body['estado'] ?? '');
            if (!$id) { _err('ID requerido'); break; }
            $estadosOk = ['programada','realizada','cancelada','no_presentado'];
            if (!in_array($estado, $estadosOk)) { _err('Estado inválido. Válidos: ' . implode(', ', $estadosOk)); break; }
            $af = $isAdmin ? '' : " AND agente_id = $userId";
            $stmt = $db->prepare("UPDATE visitas SET estado=?, updated_at=NOW() WHERE id=? $af");
            $stmt->execute([$estado, $id]);
            if (!$stmt->rowCount()) { _err('Visita no encontrada o sin acceso', 403); break; }
            _ok(['mensaje' => "Visita marcada como $estado"]);
            break;
        }

        case 'presupuestos': {
            $estado = $get['estado'] ?? '';
            $limit  = min(100, max(1, (int)($get['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND pr.usuario_id = $userId";
            $where = ['1=1']; $params = [];
            if ($estado) { $where[] = 'pr.estado = ?'; $params[] = $estado; }
            $sql = "SELECT pr.id, pr.titulo, pr.total, pr.estado, c.nombre as cliente_nombre, DATE_FORMAT(pr.fecha_emision,'%d/%m/%Y') as fecha, DATE_FORMAT(pr.fecha_expiracion,'%d/%m/%Y') as expira FROM presupuestos pr LEFT JOIN clientes c ON pr.cliente_id = c.id WHERE " . implode(' AND ', $where) . " $af ORDER BY pr.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            _ok(['presupuestos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'presupuesto': {
            $id = (int)($get['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            $af = $isAdmin ? '' : " AND pr.usuario_id = $userId";
            $stmt = $db->prepare("SELECT pr.*, c.nombre as cliente_nombre, c.email as cliente_email FROM presupuestos pr LEFT JOIN clientes c ON pr.cliente_id = c.id WHERE pr.id = ? $af LIMIT 1");
            $stmt->execute([$id]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { _err('Presupuesto no encontrado', 404); break; }
            if (!empty($p['lineas'])) $p['lineas'] = json_decode($p['lineas'], true) ?? [];
            _ok(['presupuesto' => $p]);
            break;
        }

        case 'crear_presupuesto': {
            $total = isset($body['total']) ? (float)$body['total'] : null;
            if ($total === null) { _err('El total es obligatorio'); break; }
            $titulo = trim($body['titulo'] ?? 'Presupuesto') ?: 'Presupuesto';
            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(numero,'-',-1) AS UNSIGNED)) FROM presupuestos");
            $num  = (int)$stmt->fetchColumn() + 1;
            $numero = 'P-' . date('Y') . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
            $iva = round($total * 0.21, 2);
            $subtotal = round($total - $iva, 2);
            $lineas = !empty($body['detalles']) ? [['concepto' => $body['detalles'], 'cantidad' => 1, 'precio' => $subtotal]] : [];
            $validez = (int)($body['validez_dias'] ?? 30);
            $db->prepare("INSERT INTO presupuestos (numero, cliente_id, titulo, descripcion, lineas, subtotal, iva_total, total, validez_dias, fecha_emision, fecha_expiracion, notas, estado, usuario_id) VALUES (?,?,?,?,?,?,?,?,?,CURDATE(),DATE_ADD(CURDATE(), INTERVAL ? DAY),?,?,?)")->execute([$numero, isset($body['cliente_id']) ? (int)$body['cliente_id'] : null, $titulo, $body['detalles'] ?? null, json_encode($lineas), $subtotal, $iva, $total, $validez, $body['notas'] ?? null, 'borrador', $userId]);
            $newId = (int)$db->lastInsertId();
            _ok(['id' => $newId, 'numero' => $numero, 'mensaje' => "Presupuesto $numero creado correctamente"]);
            break;
        }

        case 'facturas': {
            $estado = $get['estado'] ?? '';
            $limit  = min(100, max(1, (int)($get['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND f.usuario_id = $userId";
            $where = ['1=1']; $params = [];
            if ($estado) { $where[] = 'f.estado = ?'; $params[] = $estado; }
            $sql = "SELECT f.id, f.numero, f.concepto, f.total, f.estado, c.nombre as cliente_nombre, DATE_FORMAT(f.fecha_emision,'%d/%m/%Y') as fecha, DATE_FORMAT(f.fecha_vencimiento,'%d/%m/%Y') as vence FROM facturas f LEFT JOIN clientes c ON f.cliente_id = c.id WHERE " . implode(' AND ', $where) . " $af ORDER BY f.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            _ok(['facturas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'factura': {
            $id = (int)($get['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            $af = $isAdmin ? '' : " AND f.usuario_id = $userId";
            $stmt = $db->prepare("SELECT f.*, c.nombre as cliente_nombre, c.email as cliente_email, c.dni_nie_cif FROM facturas f LEFT JOIN clientes c ON f.cliente_id = c.id WHERE f.id = ? $af LIMIT 1");
            $stmt->execute([$id]);
            $f = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$f) { _err('Factura no encontrada', 404); break; }
            if (!empty($f['lineas'])) $f['lineas'] = json_decode($f['lineas'], true) ?? [];
            _ok(['factura' => $f]);
            break;
        }

        case 'contratos': {
            $estado = $get['estado'] ?? '';
            $limit  = min(100, max(1, (int)($get['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND ct.usuario_id = $userId";
            $where = ['1=1']; $params = [];
            if ($estado) { $where[] = 'ct.estado = ?'; $params[] = $estado; }
            $sql = "SELECT ct.id, ct.titulo, ct.estado, ct.firmante_nombre, c.nombre as cliente_nombre, DATE_FORMAT(ct.created_at,'%d/%m/%Y') as creado, DATE_FORMAT(ct.firmado_at,'%d/%m/%Y') as firmado FROM contratos ct LEFT JOIN clientes c ON ct.cliente_id = c.id WHERE " . implode(' AND ', $where) . " $af ORDER BY ct.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            _ok(['contratos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'contrato': {
            $id = (int)($get['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            $af = $isAdmin ? '' : " AND ct.usuario_id = $userId";
            $stmt = $db->prepare("SELECT ct.id, ct.titulo, ct.estado, ct.firmante_nombre, ct.firmado_at, ct.firmado_ip, DATE_FORMAT(ct.fecha_expiracion,'%d/%m/%Y') as expira, c.nombre as cliente_nombre, c.email as cliente_email, p.titulo as propiedad_titulo FROM contratos ct LEFT JOIN clientes c ON ct.cliente_id = c.id LEFT JOIN propiedades p ON ct.propiedad_id = p.id WHERE ct.id = ? $af LIMIT 1");
            $stmt->execute([$id]);
            $ct = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ct) { _err('Contrato no encontrado', 404); break; }
            _ok(['contrato' => $ct]);
            break;
        }

        case 'enviar_contrato': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            $af = $isAdmin ? '' : " AND ct.usuario_id = $userId";
            $stmt = $db->prepare("SELECT ct.*, c.email as cliente_email, c.nombre as cliente_nombre FROM contratos ct LEFT JOIN clientes c ON ct.cliente_id = c.id WHERE ct.id = ? $af LIMIT 1");
            $stmt->execute([$id]);
            $ct = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ct) { _err('Contrato no encontrado o sin acceso', 403); break; }
            if (empty($ct['cliente_email'])) { _err('El cliente no tiene email registrado'); break; }
            $link = 'https://tinoprop.es/contrato.php?token=' . $ct['token'];
            $asunto = "Contrato para firmar: " . $ct['titulo'];
            $cuerpo = "<p>Hola {$ct['cliente_nombre']},</p><p>Le enviamos el contrato <strong>{$ct['titulo']}</strong> para su revisión y firma.</p><p><a href=\"$link\">Firmar contrato</a></p>";
            $enviado = enviarEmail($ct['cliente_email'], $asunto, $cuerpo, true, $userId);
            if ($enviado) {
                $db->prepare("UPDATE contratos SET estado='enviado', updated_at=NOW() WHERE id=?")->execute([$id]);
                _ok(['mensaje' => "Contrato enviado a {$ct['cliente_email']}"]);
            } else {
                _err('Error al enviar el email: ' . (function_exists('getLastEmailError') ? (getLastEmailError() ?? 'Error desconocido') : 'Error desconocido'));
            }
            break;
        }

        case 'automatizaciones': {
            $af = $isAdmin ? '' : " AND created_by = $userId";
            $stmt = $db->query("SELECT id, nombre, trigger_tipo, activo, ejecuciones, DATE_FORMAT(ultima_ejecucion,'%d/%m/%Y %H:%i') as ultima_ejecucion FROM automatizaciones WHERE activo=1 $af ORDER BY nombre ASC");
            _ok(['automatizaciones' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'iniciar_automatizacion': {
            $autoId      = (int)($body['automatizacion_id'] ?? 0);
            $prospectoId = (int)($body['prospecto_id'] ?? 0);
            if (!$autoId || !$prospectoId) { _err('automatizacion_id y prospecto_id son obligatorios'); break; }
            $af = $isAdmin ? '' : " AND created_by = $userId";
            $stmt = $db->prepare("SELECT * FROM automatizaciones WHERE id=? AND activo=1 $af LIMIT 1");
            $stmt->execute([$autoId]);
            if (!$stmt->fetch()) { _err('Automatización no encontrada o sin acceso', 403); break; }
            $stmtP = $db->prepare("SELECT agente_id FROM prospectos WHERE id=? LIMIT 1");
            $stmtP->execute([$prospectoId]);
            $prospecto = $stmtP->fetch(PDO::FETCH_ASSOC);
            $engFile = __DIR__ . '/includes/automatizaciones_engine.php';
            if (!file_exists($engFile)) { _err('Motor de automatizaciones no disponible'); break; }
            require_once $engFile;
            $resultado = automatizacionesEjecutarTrigger('manual_ia', ['entidad_tipo' => 'prospecto', 'entidad_id' => $prospectoId, 'prospecto_id' => $prospectoId, 'agente_id' => $prospecto['agente_id'] ?? $userId, 'actor_user_id' => $userId, 'owner_user_id' => $userId]);
            _ok(['resultado' => $resultado, 'mensaje' => 'Automatización iniciada']);
            break;
        }

        case 'enviar_whatsapp': {
            $prospectoId = (int)($body['prospecto_id'] ?? 0);
            $mensaje = trim($body['mensaje'] ?? '');
            if (!$prospectoId || !$mensaje) { _err('prospecto_id y mensaje son obligatorios'); break; }
            [$af, $ap] = _af($isAdmin, $userId);
            $stmt = $db->prepare("SELECT * FROM prospectos WHERE id=? $af LIMIT 1");
            $stmt->execute(array_merge([$prospectoId], $ap));
            $prospecto = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$prospecto) { _err('Prospecto no encontrado o sin acceso', 403); break; }
            $telefono = preg_replace('/[^0-9]/', '', $prospecto['telefono'] ?? '');
            if (!$telefono) { _err('El prospecto no tiene teléfono registrado'); break; }
            $sid = getenv('TWILIO_ACCOUNT_SID') ?: ''; $token = getenv('TWILIO_AUTH_TOKEN') ?: ''; $from = getenv('TWILIO_WHATSAPP_FROM') ?: '';
            if (!$sid || !$token || !$from) {
                $stmtCfg = $db->query("SELECT account_sid, auth_token, phone_number FROM twilio_config WHERE activo=1 ORDER BY id DESC LIMIT 1");
                $twCfg = $stmtCfg ? $stmtCfg->fetch(PDO::FETCH_ASSOC) : null;
                if ($twCfg) { $sid = $sid ?: $twCfg['account_sid']; $token = $token ?: $twCfg['auth_token']; $from = $from ?: $twCfg['phone_number']; }
            }
            if (!$sid || !$token || !$from) { _err('WhatsApp (Twilio) no configurado en el CRM'); break; }
            if (strpos($from, 'whatsapp:') !== 0) $from = 'whatsapp:' . $from;
            $to = 'whatsapp:+' . $telefono;
            $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json");
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => $from, 'Body' => $mensaje]), CURLOPT_USERPWD => "$sid:$token", CURLOPT_TIMEOUT => 20]);
            $resp = json_decode(curl_exec($ch), true);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 200 && $httpCode < 300 && !empty($resp['sid'])) {
                $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?,?,?,?)")->execute([$prospectoId, $userId, "WhatsApp enviado: $mensaje", 'whatsapp']);
                _ok(['mensaje' => 'WhatsApp enviado correctamente', 'sid' => $resp['sid']]);
            } else {
                _err('Error al enviar WhatsApp: ' . ($resp['message'] ?? "HTTP $httpCode"));
            }
            break;
        }

        case 'enviar_email': {
            $prospectoId = (int)($body['prospecto_id'] ?? 0);
            $asunto = trim($body['asunto'] ?? '');
            $cuerpo = trim($body['cuerpo_html'] ?? '');
            if (!$prospectoId || !$asunto || !$cuerpo) { _err('prospecto_id, asunto y cuerpo_html son obligatorios'); break; }
            [$af, $ap] = _af($isAdmin, $userId);
            $stmt = $db->prepare("SELECT * FROM prospectos WHERE id=? $af LIMIT 1");
            $stmt->execute(array_merge([$prospectoId], $ap));
            $prospecto = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$prospecto) { _err('Prospecto no encontrado o sin acceso', 403); break; }
            $emailDest = trim($prospecto['email'] ?? '');
            if (!$emailDest) { _err('El prospecto no tiene email registrado'); break; }
            $enviado = enviarEmail($emailDest, $asunto, $cuerpo, true, $userId);
            if ($enviado) {
                $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?,?,?,?)")->execute([$prospectoId, $userId, "Email enviado: $asunto", 'email']);
                _ok(['mensaje' => "Email enviado correctamente a $emailDest"]);
            } else {
                _err('Error al enviar email: ' . (function_exists('getLastEmailError') ? (getLastEmailError() ?? 'Error desconocido') : 'Error desconocido'));
            }
            break;
        }

        case 'finanzas': {
            $estado = $get['estado'] ?? '';
            $tipo   = $get['tipo'] ?? '';
            $limit  = min(100, max(1, (int)($get['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND f.agente_id = $userId";
            $where = ['1=1']; $params = [];
            if ($estado) { $where[] = 'f.estado = ?'; $params[] = $estado; }
            if ($tipo)   { $where[] = 'f.tipo = ?';   $params[] = $tipo; }
            $sql = "SELECT f.id, f.tipo, f.concepto, f.importe, f.importe_total, f.estado, DATE_FORMAT(f.fecha,'%d/%m/%Y') as fecha, c.nombre as cliente_nombre, p.titulo as propiedad_titulo FROM finanzas f LEFT JOIN clientes c ON f.cliente_id = c.id LEFT JOIN propiedades p ON f.propiedad_id = p.id WHERE " . implode(' AND ', $where) . " $af ORDER BY f.fecha DESC LIMIT $limit";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            $totStmt = $db->prepare("SELECT COALESCE(SUM(importe_total),0) as total, estado FROM finanzas f WHERE 1=1 $af GROUP BY estado");
            $totStmt->execute();
            $totales = [];
            foreach ($totStmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $totales[$row['estado']] = round((float)$row['total'], 2); }
            _ok(['finanzas' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'totales' => $totales]);
            break;
        }

        case 'crear_finanza': {
            $concepto = trim($body['concepto'] ?? '');
            $importe  = isset($body['importe']) ? (float)$body['importe'] : null;
            if (!$concepto || $importe === null) { _err('concepto e importe son obligatorios'); break; }
            $tiposOk = ['comision_venta','comision_alquiler','honorarios','gasto','ingreso_otro'];
            $tipo = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'honorarios';
            $iva = (float)($body['iva'] ?? 0);
            $total = $importe + $iva;
            $db->prepare("INSERT INTO finanzas (tipo, concepto, importe, iva, importe_total, fecha, estado, propiedad_id, cliente_id, agente_id, notas) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([$tipo, $concepto, $importe, $iva, $total, $body['fecha'] ?? date('Y-m-d'), $body['estado'] ?? 'pendiente', isset($body['propiedad_id']) ? (int)$body['propiedad_id'] : null, isset($body['cliente_id']) ? (int)$body['cliente_id'] : null, $userId, $body['notas'] ?? null]);
            _ok(['id' => (int)$db->lastInsertId(), 'mensaje' => 'Registro financiero creado']);
            break;
        }

        case 'marcar_cobrado': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { _err('ID requerido'); break; }
            $af = $isAdmin ? '' : " AND agente_id = $userId";
            $stmt = $db->prepare("UPDATE finanzas SET estado='cobrado', updated_at=NOW() WHERE id=? $af");
            $stmt->execute([$id]);
            if (!$stmt->rowCount()) { _err('Registro no encontrado o sin acceso', 403); break; }
            _ok(['mensaje' => 'Marcado como cobrado']);
            break;
        }

        case 'informe_finanzas': {
            $af = $isAdmin ? '' : " AND agente_id = $userId";
            $stmt = $db->query("SELECT DATE_FORMAT(fecha,'%Y-%m') as mes, tipo, SUM(importe_total) as total, COUNT(*) as operaciones FROM finanzas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) $af GROUP BY mes, tipo ORDER BY mes DESC, tipo");
            $porMes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $db->query("SELECT tipo, COUNT(*) as registros, SUM(importe_total) as importe FROM finanzas WHERE estado='pendiente' $af GROUP BY tipo");
            $pendiente = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $db->query("SELECT COALESCE(SUM(importe_total),0) FROM finanzas WHERE estado='cobrado' AND YEAR(fecha)=YEAR(CURDATE()) $af");
            $anio = round((float)$stmt->fetchColumn(), 2);
            _ok(['informe' => ['por_mes' => $porMes, 'pendiente_cobro' => $pendiente, 'cobrado_anio_actual' => $anio]]);
            break;
        }

        case 'calendario': {
            $desde = $get['desde'] ?? date('Y-m-d');
            $hasta = $get['hasta'] ?? date('Y-m-d', strtotime('+30 days'));
            $limit = min(100, max(1, (int)($get['limit'] ?? 50)));
            $af    = $isAdmin ? '' : " AND e.usuario_id = $userId";
            $stmt = $db->prepare("SELECT e.id, e.titulo, e.tipo, e.todo_dia, DATE_FORMAT(e.fecha_inicio,'%d/%m/%Y %H:%i') as inicio, DATE_FORMAT(e.fecha_fin,'%d/%m/%Y %H:%i') as fin, e.ubicacion, c.nombre as cliente_nombre, p.titulo as propiedad_titulo FROM calendario_eventos e LEFT JOIN clientes c ON e.cliente_id = c.id LEFT JOIN propiedades p ON e.propiedad_id = p.id WHERE e.fecha_inicio >= ? AND e.fecha_inicio <= ? $af ORDER BY e.fecha_inicio ASC LIMIT $limit");
            $stmt->execute([$desde . ' 00:00:00', $hasta . ' 23:59:59']);
            _ok(['eventos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'crear_evento': {
            $titulo = trim($body['titulo'] ?? '');
            $inicio = trim($body['fecha_inicio'] ?? '');
            if (!$titulo || !$inicio) { _err('titulo y fecha_inicio son obligatorios'); break; }
            $fin = $body['fecha_fin'] ?? $inicio;
            $todoDia = !empty($body['todo_dia']) ? 1 : 0;
            $tiposOk = ['tarea','reunion','visita','cita','otra'];
            $tipo = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'cita';
            $db->prepare("INSERT INTO calendario_eventos (titulo, descripcion, tipo, fecha_inicio, fecha_fin, todo_dia, ubicacion, cliente_id, propiedad_id, usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$titulo, $body['descripcion'] ?? null, $tipo, date('Y-m-d H:i:s', strtotime($inicio)), date('Y-m-d H:i:s', strtotime($fin)), $todoDia, $body['ubicacion'] ?? null, isset($body['cliente_id']) ? (int)$body['cliente_id'] : null, isset($body['propiedad_id']) ? (int)$body['propiedad_id'] : null, $userId]);
            _ok(['id' => (int)$db->lastInsertId(), 'mensaje' => 'Evento creado en el calendario']);
            break;
        }

        case 'campanas': {
            $estado = $get['estado'] ?? '';
            $limit  = min(50, max(1, (int)($get['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND ca.usuario_id = $userId";
            $where = ['1=1']; $params = [];
            if ($estado) { $where[] = 'ca.estado = ?'; $params[] = $estado; }
            $sql = "SELECT ca.id, ca.nombre, ca.tipo, ca.estado, ca.total_contactos, ca.enviados, ca.abiertos, ca.clicks, DATE_FORMAT(ca.created_at,'%d/%m/%Y') as creada FROM campanas ca WHERE " . implode(' AND ', $where) . " $af ORDER BY ca.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            _ok(['campanas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'documentos': {
            $clienteId   = (int)($get['cliente_id'] ?? 0);
            $propiedadId = (int)($get['propiedad_id'] ?? 0);
            $limit       = min(50, max(1, (int)($get['limit'] ?? 20)));
            $where = ['1=1']; $params = [];
            if ($clienteId)   { $where[] = 'd.cliente_id = ?';   $params[] = $clienteId; }
            if ($propiedadId) { $where[] = 'd.propiedad_id = ?'; $params[] = $propiedadId; }
            $sql = "SELECT d.id, d.nombre, d.tipo, d.tamano, DATE_FORMAT(d.created_at,'%d/%m/%Y') as subido, c.nombre as cliente_nombre, p.titulo as propiedad_titulo FROM documentos d LEFT JOIN clientes c ON d.cliente_id = c.id LEFT JOIN propiedades p ON d.propiedad_id = p.id WHERE " . implode(' AND ', $where) . " ORDER BY d.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql); $stmt->execute($params);
            _ok(['documentos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'pipeline_kanban': {
            $pipelineId = (int)($get['pipeline_id'] ?? 0);
            $af = $isAdmin ? '' : " AND pl.created_by = $userId";
            $stmt = $db->query("SELECT pl.id, pl.nombre, pl.descripcion, pl.color, (SELECT COUNT(*) FROM pipeline_items WHERE pipeline_id = pl.id) as total_items FROM pipelines pl WHERE pl.activo=1 $af ORDER BY pl.id ASC");
            $pipelines = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$pipelineId && !empty($pipelines)) $pipelineId = (int)$pipelines[0]['id'];
            $kanban = [];
            if ($pipelineId) {
                $stmt = $db->query("SELECT pe.id, pe.nombre as etapa, pe.color, pe.orden FROM pipeline_etapas pe WHERE pe.pipeline_id = $pipelineId ORDER BY pe.orden ASC");
                $etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($etapas as &$etapa) {
                    $stmt2 = $db->prepare("SELECT pi.id, pi.titulo, pi.valor, pi.prioridad, COALESCE(p.nombre, c.nombre) as contacto, pr.titulo as propiedad_titulo FROM pipeline_items pi LEFT JOIN prospectos p ON pi.prospecto_id = p.id LEFT JOIN clientes c ON pi.cliente_id = c.id LEFT JOIN propiedades pr ON pi.propiedad_id = pr.id WHERE pi.etapa_id = ? ORDER BY pi.created_at DESC LIMIT 20");
                    $stmt2->execute([$etapa['id']]);
                    $etapa['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                }
                $kanban = $etapas;
            }
            _ok(['pipelines' => $pipelines, 'kanban' => $kanban, 'pipeline_activo' => $pipelineId]);
            break;
        }

        case 'portales_propiedad': {
            $propId = (int)($get['propiedad_id'] ?? 0);
            if (!$propId) { _err('propiedad_id requerido'); break; }
            $stmt = $db->prepare("SELECT po.id, po.nombre, po.url as portal_url, pp.estado, pp.url_publicacion, DATE_FORMAT(pp.fecha_publicacion,'%d/%m/%Y') as publicado, DATE_FORMAT(pp.fecha_actualizacion,'%d/%m/%Y') as actualizado, pp.notas FROM portales po LEFT JOIN propiedad_portales pp ON pp.portal_id = po.id AND pp.propiedad_id = ? WHERE po.activo=1 ORDER BY po.nombre ASC");
            $stmt->execute([$propId]);
            _ok(['portales' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'publicar_portal': {
            $propId   = (int)($body['propiedad_id'] ?? 0);
            $portalId = (int)($body['portal_id'] ?? 0);
            $accion   = $body['accion'] ?? 'publicar';
            if (!$propId || !$portalId) { _err('propiedad_id y portal_id son obligatorios'); break; }
            if ($accion === 'retirar') {
                $db->prepare("UPDATE propiedad_portales SET estado='retirado', fecha_actualizacion=CURDATE() WHERE propiedad_id=? AND portal_id=?")->execute([$propId, $portalId]);
                _ok(['mensaje' => 'Propiedad retirada del portal']);
            } else {
                $db->prepare("INSERT INTO propiedad_portales (propiedad_id, portal_id, estado, url_publicacion, fecha_publicacion, notas) VALUES (?,?,'publicado',?,CURDATE(),?) ON DUPLICATE KEY UPDATE estado='publicado', url_publicacion=VALUES(url_publicacion), fecha_actualizacion=CURDATE()")->execute([$propId, $portalId, $body['url'] ?? null, $body['notas'] ?? null]);
                _ok(['mensaje' => 'Propiedad publicada en el portal']);
            }
            break;
        }

        default:
            _err("Acción desconocida: '$action'");

    } } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
