#!/usr/bin/env node
/**
 * CRM MCP Connector
 * MODE=stdio  → proceso local en el mismo PC (un solo usuario)
 * MODE=sse    → servidor HTTP en red local, varios usuarios sin instalar nada
 */

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { CallToolRequestSchema, ListToolsRequestSchema } from "@modelcontextprotocol/sdk/types.js";
import { config as loadEnv } from "dotenv";
import { fileURLToPath } from "url";
import { dirname, join } from "path";

const __dirname = dirname(fileURLToPath(import.meta.url));
loadEnv({ path: join(__dirname, ".env") });

const CRM_URL   = (process.env.CRM_URL || "").replace(/\/$/, "");
const CRM_TOKEN = process.env.CRM_TOKEN || ""; // solo necesario en modo stdio
const MODE      = process.env.MODE || "stdio";
const PORT      = parseInt(process.env.PORT || "3000");

if (!CRM_URL) {
  process.stderr.write("[CRM MCP] Error: falta CRM_URL en el archivo .env\n");
  process.exit(1);
}
if (MODE === "stdio" && !CRM_TOKEN) {
  process.stderr.write("[CRM MCP] Error: en modo stdio necesitas CRM_TOKEN en el .env\n");
  process.exit(1);
}

// ── Llamada a la API PHP del CRM ───────────────────────────────────────────
async function api(token, action, params = {}, method = "GET", body = null) {
  const url = new URL(`${CRM_URL}/app/api/mcp_api.php`);
  url.searchParams.set("action", action);
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== "") {
      url.searchParams.set(k, String(v));
    }
  }
  const opts = {
    method,
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
  };
  if (body && method !== "GET") opts.body = JSON.stringify(body);

  const res  = await fetch(url.toString(), opts);
  const text = await res.text();
  if (!res.ok) throw new Error(`API ${res.status}: ${text.slice(0, 300)}`);
  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`Respuesta no JSON: ${text.slice(0, 200)}`);
  }
}

// ── Herramientas disponibles ───────────────────────────────────────────────
const TOOLS = [
  {
    name: "resumen_dashboard",
    description: "Resumen del CRM: prospectos por etapa, clientes, tareas y contactos de hoy.",
    inputSchema: { type: "object", properties: {} },
  },
  {
    name: "listar_prospectos",
    description: "Lista prospectos con filtros opcionales (etapa, temperatura, búsqueda, solo los de hoy).",
    inputSchema: {
      type: "object",
      properties: {
        busqueda:     { type: "string",  description: "Nombre, teléfono o email" },
        etapa:        { type: "string",  enum: ["nuevo_lead","contactado","seguimiento","visita_programada","captado","descartado"] },
        temperatura:  { type: "string",  enum: ["frio","templado","caliente"] },
        contactar_hoy:{ type: "boolean", description: "Solo prospectos a contactar hoy" },
        limite:       { type: "number",  description: "Máximo de resultados (default 20)" },
      },
    },
  },
  {
    name: "ver_prospecto",
    description: "Datos completos de un prospecto e historial de contactos reciente.",
    inputSchema: {
      type: "object",
      properties: { id: { type: "number", description: "ID del prospecto" } },
      required: ["id"],
    },
  },
  {
    name: "crear_prospecto",
    description: "Crea un nuevo prospecto/lead en el CRM.",
    inputSchema: {
      type: "object",
      properties: {
        nombre:          { type: "string" },
        telefono:        { type: "string" },
        email:           { type: "string" },
        tipo_propiedad:  { type: "string", description: "Piso, Casa, Chalet, Local…" },
        precio_estimado: { type: "number" },
        localidad:       { type: "string" },
        provincia:       { type: "string" },
        notas:           { type: "string" },
        etapa:           { type: "string", default: "nuevo_lead" },
        temperatura:     { type: "string", enum: ["frio","templado","caliente"], default: "frio" },
      },
      required: ["nombre"],
    },
  },
  {
    name: "anadir_nota",
    description: "Añade una nota o registro de contacto al historial de un prospecto.",
    inputSchema: {
      type: "object",
      properties: {
        prospecto_id: { type: "number" },
        contenido:    { type: "string" },
        tipo:         { type: "string", enum: ["nota","llamada","email","visita","whatsapp","otro"], default: "nota" },
      },
      required: ["prospecto_id", "contenido"],
    },
  },
  {
    name: "listar_clientes",
    description: "Lista clientes con filtros opcionales.",
    inputSchema: {
      type: "object",
      properties: {
        busqueda: { type: "string" },
        tipo:     { type: "string", enum: ["comprador","vendedor","inquilino","propietario","inversor"] },
        limite:   { type: "number", default: 20 },
      },
    },
  },
  {
    name: "ver_cliente",
    description: "Datos completos de un cliente.",
    inputSchema: {
      type: "object",
      properties: { id: { type: "number" } },
      required: ["id"],
    },
  },
  {
    name: "listar_tareas",
    description: "Tareas del usuario: todas o solo las de hoy.",
    inputSchema: {
      type: "object",
      properties: {
        estado:   { type: "string", enum: ["pendiente","en_progreso","completada"] },
        solo_hoy: { type: "boolean", description: "Solo tareas de hoy" },
      },
    },
  },
];

// ── Crear una instancia de servidor MCP para un token concreto ─────────────
// Cada conexión SSE crea su propio servidor con su propio token de usuario.
function crearServidor(token) {
  const server = new Server(
    { name: "crm-connector", version: "1.0.0" },
    { capabilities: { tools: {} } }
  );

  server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

  server.setRequestHandler(CallToolRequestSchema, async (req) => {
    const { name, arguments: args = {} } = req.params;
    try {
      let data;
      switch (name) {
        case "resumen_dashboard":
          data = await api(token, "resumen");
          break;
        case "listar_prospectos":
          data = await api(token, "prospectos", {
            q:            args.busqueda,
            etapa:        args.etapa,
            temperatura:  args.temperatura,
            contactar_hoy: args.contactar_hoy ? 1 : undefined,
            limit:        args.limite || 20,
          });
          break;
        case "ver_prospecto":
          data = await api(token, "prospecto", { id: args.id });
          break;
        case "crear_prospecto":
          data = await api(token, "crear_prospecto", {}, "POST", args);
          break;
        case "anadir_nota":
          data = await api(token, "anadir_nota", {}, "POST", args);
          break;
        case "listar_clientes":
          data = await api(token, "clientes", {
            q:    args.busqueda,
            tipo: args.tipo,
            limit: args.limite || 20,
          });
          break;
        case "ver_cliente":
          data = await api(token, "cliente", { id: args.id });
          break;
        case "listar_tareas":
          data = await api(token, "tareas", {
            estado:   args.estado,
            solo_hoy: args.solo_hoy ? 1 : 0,
          });
          break;
        default:
          return { content: [{ type: "text", text: `Herramienta desconocida: ${name}` }], isError: true };
      }
      return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
    } catch (err) {
      return { content: [{ type: "text", text: `Error al contactar el CRM: ${err.message}` }], isError: true };
    }
  });

  return server;
}

// ── Modo SSE: servidor HTTP en red local ───────────────────────────────────
if (MODE === "sse") {
  const { default: express }       = await import("express");
  const { SSEServerTransport }     = await import("@modelcontextprotocol/sdk/server/sse.js");

  const app              = express();
  const sesionesActivas  = {};  // sessionId → transport

  // ── Almacenes en memoria para OAuth Mock ────────────────────────────────
  const oauthClients = {}; // clientId → clientSecret
  const authCodes    = {}; // code → token_del_crm

  app.use(express.urlencoded({ extended: true }));

  // 1. Discovery de OAuth para "Automatic Registration"
  app.get("/.well-known/oauth-authorization-server", (req, res) => {
    const baseUrl = `${req.protocol}://${req.get("host")}`;
    res.json({
      issuer: baseUrl,
      authorization_endpoint: `${baseUrl}/authorize`,
      token_endpoint: `${baseUrl}/token`,
      registration_endpoint: `${baseUrl}/register`,
      scopes_supported: ["mcp"],
      response_types_supported: ["code"],
      grant_types_supported: ["authorization_code"],
      token_endpoint_auth_methods_supported: ["client_secret_basic", "client_secret_post"]
    });
  });

  // 2. Dynamic Client Registration (DCR)
  app.post("/register", express.json(), (req, res) => {
    const { randomBytes } = require("crypto");
    const clientId = randomBytes(16).toString("hex");
    const clientSecret = randomBytes(32).toString("hex");
    oauthClients[clientId] = clientSecret;

    const clientData = req.body || {};
    res.status(201).json({
      client_id: clientId,
      client_secret: clientSecret,
      client_id_issued_at: Math.floor(Date.now() / 1000),
      client_secret_expires_at: 0,
      client_name: clientData.client_name || "MCP Client",
      redirect_uris: clientData.redirect_uris || [],
      grant_types: clientData.grant_types || ["authorization_code"],
      token_endpoint_auth_method: "client_secret_basic"
    });
  });

  // 3. Authorization Endpoint (Página HTML para que el usuario ponga su token)
  app.get("/authorize", (req, res) => {
    const { redirect_uri, state } = req.query;
    res.send(`
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="utf-8">
        <title>Conectar CRM a IA</title>
        <style>
          body { font-family: system-ui, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f9f9f9; }
          .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
          h2 { margin-top: 0; color: #333; }
          input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
          button { width: 100%; padding: 10px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
          button:hover { background: #005bb5; }
        </style>
      </head>
      <body>
        <div class="card">
          <h2>Autorizar IA</h2>
          <p>Introduce tu token del CRM para conectar con la inteligencia artificial.</p>
          <form method="POST" action="/authorize">
            <input type="hidden" name="redirect_uri" value="${redirect_uri || ''}">
            <input type="hidden" name="state" value="${state || ''}">
            <input type="text" name="crm_token" placeholder="Ej: f3b1c9... (64 caracteres)" required>
            <button type="submit">Conectar</button>
          </form>
        </div>
      </body>
      </html>
    `);
  });

  // 4. Procesa y aprueba la autorización
  app.post("/authorize", (req, res) => {
    const { redirect_uri, state, crm_token } = req.body;
    if (!redirect_uri) return res.status(400).send("Falta redirect_uri");
    
    // Generamos un código temporal
    const { randomBytes } = require("crypto");
    const code = randomBytes(16).toString("hex");
    authCodes[code] = crm_token;

    // Redirigir de vuelta al redirect_uri con el código
    const url = new URL(redirect_uri);
    url.searchParams.set("code", code);
    if (state) url.searchParams.set("state", state);
    
    res.redirect(url.toString());
  });

  // 5. Token Endpoint (Cambia el código temporal por el access_token = el CRM token)
  app.post("/token", express.urlencoded({ extended: true }), (req, res) => {
    const { code } = req.body;
    if (!code || !authCodes[code]) {
      return res.status(400).json({ error: "invalid_grant" });
    }
    
    const trueToken = authCodes[code];
    delete authCodes[code]; // Un solo uso

    res.json({
      access_token: trueToken,
      token_type: "Bearer",
      expires_in: 315360000 // 10 años
    });
  });

  // Endpoint de conexión SSE — el cliente se conecta aquí con su token por Query o Bearer Auth
  app.get("/sse", async (req, res) => {
    let token = req.query.token || "";
    if (!token && req.headers.authorization) {
      token = req.headers.authorization.replace(/^Bearer\s/i, "").trim();
    }
    if (token.length < 32) {
      res.status(401).json({ error: "Token inválido. Genera uno en el CRM → Ajustes → Conector Claude (MCP)" });
      return;
    }

    const transport = new SSEServerTransport("/messages", res);
    sesionesActivas[transport.sessionId] = transport;

    res.on("close", () => {
      delete sesionesActivas[transport.sessionId];
    });

    const servidor = crearServidor(token);
    await servidor.connect(transport);
  });

  // Endpoint de mensajes MCP
  app.post("/messages", express.json(), async (req, res) => {
    const transport = sesionesActivas[req.query.sessionId];
    if (!transport) { res.status(404).end(); return; }
    await transport.handlePostMessage(req, res);
  });

  // Health check para comprobar que el servidor está corriendo
  app.get("/health", (_req, res) => {
    res.json({ status: "ok", modo: "sse", puerto: PORT, crm: CRM_URL });
  });

  app.listen(PORT, "0.0.0.0", () => {
    console.log("─────────────────────────────────────────────────────");
    console.log(`  ✅  CRM MCP Server corriendo en puerto ${PORT}`);
    console.log("─────────────────────────────────────────────────────");
    console.log(`  CRM: ${CRM_URL}`);
    console.log(`  Health check: http://localhost:${PORT}/health`);
    console.log("");
    console.log("  Para conectar Claude de cada usuario:");
    console.log(`  http://TU_IP_LOCAL:${PORT}/sse?token=TOKEN_DEL_USUARIO`);
    console.log("─────────────────────────────────────────────────────\n");
  });

// ── Modo stdio: proceso local (un solo usuario, sin red) ───────────────────
} else {
  const { StdioServerTransport } = await import("@modelcontextprotocol/sdk/server/stdio.js");
  const transport = new StdioServerTransport();
  const servidor  = crearServidor(CRM_TOKEN);
  await servidor.connect(transport);
}
