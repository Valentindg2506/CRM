#!/usr/bin/env node
/**
 * CRM MCP Connector
 * MODE=stdio  → proceso local en el mismo PC (un solo usuario)
 * MODE=sse    → servidor HTTP con dos transportes:
 *               - /mcp  (Streamable HTTP, nuevo) → Claude Code CLI y Desktop
 *               - /sse  (SSE legacy, OAuth)       → Claude.ai web y Perplexity
 */

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { CallToolRequestSchema, ListToolsRequestSchema } from "@modelcontextprotocol/sdk/types.js";
import { config as loadEnv } from "dotenv";
import { fileURLToPath } from "url";
import { dirname, join } from "path";
import { randomBytes, randomUUID } from "crypto";

const __dirname = dirname(fileURLToPath(import.meta.url));
loadEnv({ path: join(__dirname, ".env") });

const CRM_URL      = (process.env.CRM_URL || "").replace(/\/$/, "");
const CRM_API_PATH = process.env.CRM_API_PATH || "/api/mcp_api.php";
const CRM_TOKEN    = process.env.CRM_TOKEN || "";
const MODE         = process.env.MODE || "stdio";
const PORT         = parseInt(process.env.PORT || "3001");

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
  const url = new URL(`${CRM_URL}${CRM_API_PATH}`);
  url.searchParams.set("action", action);
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== "") {
      url.searchParams.set(k, String(v));
    }
  }

  console.log(`[API CALL] action=${action} token=${token ? token.slice(0, 6) + "..." : "NONE"}`);

  const opts = {
    method,
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
  };
  if (body && method !== "GET") opts.body = JSON.stringify(body);

  const res  = await fetch(url.toString(), opts);
  const text = await res.text();
  console.log(`[API RESPONSE] action=${action} status=${res.status} body=${text.slice(0, 100)}`);

  if (!res.ok) throw new Error(`API ${res.status}: ${text.slice(0, 300)}`);
  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`Respuesta no JSON: ${text.slice(0, 200)}`);
  }
}

// ── Herramientas disponibles ───────────────────────────────────────────────
const TOOLS = [
  // ── DASHBOARD ─────────────────────────────────────────────────────────────
  {
    name: "resumen_dashboard",
    description: "Resumen del CRM: prospectos activos por etapa, total clientes, tareas pendientes hoy y contactos a hacer hoy.",
    inputSchema: { type: "object", properties: {} },
  },
  {
    name: "estadisticas",
    description: "Estadísticas de ventas del CRM: ingresos de facturas, presupuestos aceptados, contratos firmados, prospectos nuevos y conversión por período.",
    inputSchema: {
      type: "object",
      properties: {
        periodo: { type: "string", enum: ["hoy","semana","mes","trimestre","anio"], description: "Período de análisis (default: mes)" },
      },
    },
  },
  {
    name: "buscar",
    description: "Búsqueda global en todo el CRM: busca simultáneamente en prospectos, clientes y propiedades por nombre, email o teléfono.",
    inputSchema: {
      type: "object",
      properties: {
        q: { type: "string", description: "Texto a buscar" },
      },
      required: ["q"],
    },
  },
  // ── PROSPECTOS ─────────────────────────────────────────────────────────────
  {
    name: "listar_prospectos",
    description: "Lista prospectos/leads del CRM con filtros opcionales por etapa, temperatura o fecha de contacto.",
    inputSchema: {
      type: "object",
      properties: {
        busqueda:      { type: "string",  description: "Nombre, teléfono o email" },
        etapa:         { type: "string",  enum: ["nuevo_lead","contactado","seguimiento","visita_programada","captado","descartado"] },
        temperatura:   { type: "string",  enum: ["frio","templado","caliente"] },
        contactar_hoy: { type: "boolean", description: "Solo prospectos a contactar hoy" },
        limite:        { type: "number",  description: "Máximo de resultados (default 20)" },
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
  {
    name: "crear_tarea",
    description: "Crea una nueva tarea o recordatorio de seguimiento en la agenda.",
    inputSchema: {
      type: "object",
      properties: {
        titulo:            { type: "string" },
        tipo:              { type: "string", enum: ["llamada","email","reunion","visita","otro"] },
        fecha_vencimiento: { type: "string", description: "Formato YYYY-MM-DD HH:MM:SS" },
        prospecto_id:      { type: "number" },
        descripcion:       { type: "string" },
      },
      required: ["titulo", "tipo"],
    },
  },
  {
    name: "completar_tarea",
    description: "Marca una tarea pendiente como finalizada.",
    inputSchema: {
      type: "object",
      properties: { id: { type: "number" } },
      required: ["id"],
    },
  },
  {
    name: "actualizar_prospecto",
    description: "Actualiza el estado de pipeline, temperatura u otros detalles clave de un prospecto.",
    inputSchema: {
      type: "object",
      properties: {
        id:             { type: "number" },
        etapa:          { type: "string", enum: ["nuevo_lead","contactado","seguimiento","visita_programada","negociacion","captado","descartado"] },
        temperatura:    { type: "string", enum: ["frio","templado","caliente"] },
        notas_internas: { type: "string" },
      },
      required: ["id"],
    },
  },
  {
    name: "listar_automatizaciones",
    description: "Devuelve todos los flujos de automatizaciones o campañas de marketing que la IA puede activar.",
    inputSchema: { type: "object", properties: {} },
  },
  {
    name: "iniciar_automatizacion",
    description: "Lanza manualmente un motor de automatización sobre un usuario o cliente.",
    inputSchema: {
      type: "object",
      properties: {
        automatizacion_id: { type: "number" },
        prospecto_id:      { type: "number" },
      },
      required: ["automatizacion_id", "prospecto_id"],
    },
  },
  {
    name: "enviar_whatsapp",
    description: "Usa el canal oficial del CRM para enviar un mensaje instantáneo de WhatsApp corporativo.",
    inputSchema: {
      type: "object",
      properties: {
        prospecto_id: { type: "number" },
        mensaje:      { type: "string" },
      },
      required: ["prospecto_id", "mensaje"],
    },
  },
  {
    name: "enviar_email",
    description: "Manda un correo de seguimiento comercial desde el CRM.",
    inputSchema: {
      type: "object",
      properties: {
        prospecto_id: { type: "number" },
        asunto:       { type: "string" },
        cuerpo_html:  { type: "string" },
      },
      required: ["prospecto_id", "asunto", "cuerpo_html"],
    },
  },
  {
    name: "listar_propiedades",
    description: "Busca en el catálogo de inmuebles del CRM para enseñar u ofrecer a los leads.",
    inputSchema: {
      type: "object",
      properties: {
        busqueda:      { type: "string" },
        estado:        { type: "string", enum: ["disponible","reservada","vendida","alquilada"] },
        precio_maximo: { type: "number" },
      },
    },
  },
  {
    name: "generar_presupuesto",
    description: "Crea un presupuesto/propuesta en el CRM y lo asocia a un cliente o prospecto.",
    inputSchema: {
      type: "object",
      properties: {
        cliente_id:   { type: "number" },
        titulo:       { type: "string" },
        total:        { type: "number" },
        detalles:     { type: "string", description: "Conceptos u honorarios de la intermediación" },
        notas:        { type: "string" },
        validez_dias: { type: "number", description: "Días de validez (default 30)" },
      },
      required: ["total"],
    },
  },
  {
    name: "listar_presupuestos",
    description: "Lista presupuestos del CRM con filtro por estado (borrador, enviado, aceptado, rechazado).",
    inputSchema: {
      type: "object",
      properties: {
        estado: { type: "string", enum: ["borrador","enviado","aceptado","rechazado"] },
        limite: { type: "number" },
      },
    },
  },
  {
    name: "ver_presupuesto",
    description: "Detalle completo de un presupuesto: líneas, totales, estado y cliente.",
    inputSchema: {
      type: "object",
      properties: { id: { type: "number" } },
      required: ["id"],
    },
  },
  {
    name: "listar_facturas",
    description: "Lista facturas del CRM con filtro por estado (borrador, emitida, pagada, vencida).",
    inputSchema: {
      type: "object",
      properties: {
        estado: { type: "string", enum: ["borrador","emitida","pagada","vencida"] },
        limite: { type: "number" },
      },
    },
  },
  {
    name: "ver_factura",
    description: "Detalle completo de una factura: líneas, totales, estado de pago y cliente.",
    inputSchema: {
      type: "object",
      properties: { id: { type: "number" } },
      required: ["id"],
    },
  },
  {
    name: "listar_contratos",
    description: "Lista contratos del CRM con filtro por estado (borrador, enviado, firmado, rechazado).",
    inputSchema: {
      type: "object",
      properties: {
        estado: { type: "string", enum: ["borrador","enviado","visto","firmado","rechazado","expirado"] },
        limite: { type: "number" },
      },
    },
  },
  {
    name: "ver_contrato",
    description: "Detalle de un contrato: título, estado, fecha de firma y cliente asociado.",
    inputSchema: {
      type: "object",
      properties: { id: { type: "number" } },
      required: ["id"],
    },
  },
  {
    name: "ver_propiedad",
    description: "Datos completos de una propiedad: descripción, precio, estado y agente.",
    inputSchema: {
      type: "object",
      properties: { id: { type: "number" } },
      required: ["id"],
    },
  },
  {
    name: "crear_propiedad",
    description: "Da de alta una nueva propiedad en el catálogo del CRM.",
    inputSchema: {
      type: "object",
      properties: {
        titulo:       { type: "string" },
        tipo:         { type: "string", description: "Piso, Casa, Chalet, Local, Terreno…" },
        estado:       { type: "string", enum: ["disponible","reservada","vendida","alquilada"], default: "disponible" },
        precio:       { type: "number" },
        localidad:    { type: "string" },
        provincia:    { type: "string" },
        descripcion:  { type: "string" },
        habitaciones: { type: "number" },
        banos:        { type: "number" },
        metros:       { type: "number" },
      },
      required: ["titulo"],
    },
  },
  {
    name: "actualizar_propiedad",
    description: "Actualiza el estado o precio de una propiedad (disponible, reservada, vendida, alquilada).",
    inputSchema: {
      type: "object",
      properties: {
        id:     { type: "number" },
        estado: { type: "string", enum: ["disponible","reservada","vendida","alquilada"] },
        precio: { type: "number" },
        notas:  { type: "string" },
      },
      required: ["id"],
    },
  },
  {
    name: "crear_cliente",
    description: "Crea un nuevo cliente en el CRM.",
    inputSchema: {
      type: "object",
      properties: {
        nombre:    { type: "string" },
        apellidos: { type: "string" },
        tipo:      { type: "string", enum: ["comprador","vendedor","inquilino","propietario","inversor"] },
        email:     { type: "string" },
        telefono:  { type: "string" },
        localidad: { type: "string" },
        provincia: { type: "string" },
        notas:     { type: "string" },
      },
      required: ["nombre"],
    },
  },
  {
    name: "actualizar_cliente",
    description: "Actualiza datos de contacto o tipo de un cliente existente.",
    inputSchema: {
      type: "object",
      properties: {
        id:        { type: "number" },
        nombre:    { type: "string" },
        apellidos: { type: "string" },
        tipo:      { type: "string" },
        email:     { type: "string" },
        telefono:  { type: "string" },
        notas:     { type: "string" },
      },
      required: ["id"],
    },
  },
  {
    name: "listar_visitas",
    description: "Lista visitas programadas a propiedades con filtro por estado o fecha.",
    inputSchema: {
      type: "object",
      properties: {
        estado:   { type: "string", enum: ["programada","realizada","cancelada"] },
        solo_hoy: { type: "boolean" },
        limite:   { type: "number" },
      },
    },
  },
  {
    name: "crear_visita",
    description: "Programa una visita a una propiedad para un prospecto o cliente.",
    inputSchema: {
      type: "object",
      properties: {
        propiedad_id: { type: "number" },
        prospecto_id: { type: "number" },
        cliente_id:   { type: "number" },
        fecha:        { type: "string", description: "Formato YYYY-MM-DD HH:MM" },
        duracion_min: { type: "number", description: "Duración en minutos (default 60)" },
        notas:        { type: "string" },
      },
      required: ["propiedad_id", "fecha"],
    },
  },
  {
    name: "listar_campanas",
    description: "Lista las campañas de marketing del CRM.",
    inputSchema: {
      type: "object",
      properties: {
        estado: { type: "string", enum: ["borrador","activa","pausada","finalizada"] },
        limite: { type: "number" },
      },
    },
  },
  {
    name: "estadisticas",
    description: "Estadísticas de ventas: ingresos de facturas, presupuestos aceptados, contratos firmados y prospectos nuevos por período.",
    inputSchema: {
      type: "object",
      properties: {
        periodo: { type: "string", enum: ["hoy","semana","mes","trimestre","anio"], description: "Período (default: mes)" },
      },
    },
  },
  {
    name: "buscar",
    description: "Búsqueda global en prospectos, clientes y propiedades a la vez.",
    inputSchema: {
      type: "object",
      properties: {
        q: { type: "string", description: "Texto a buscar (nombre, email, teléfono, referencia)" },
      },
      required: ["q"],
    },
  },
];

// ── Crear una instancia de servidor MCP para un token concreto ─────────────
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
            q:             args.busqueda,
            etapa:         args.etapa,
            temperatura:   args.temperatura,
            contactar_hoy: args.contactar_hoy ? 1 : undefined,
            limit:         args.limite || 20,
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
          data = await api(token, "clientes", { q: args.busqueda, tipo: args.tipo, limit: args.limite || 20 });
          break;
        case "ver_cliente":
          data = await api(token, "cliente", { id: args.id });
          break;
        case "listar_tareas":
          data = await api(token, "tareas", { estado: args.estado, solo_hoy: args.solo_hoy ? 1 : 0 });
          break;
        case "crear_tarea":
          data = await api(token, "crear_tarea", {}, "POST", args);
          break;
        case "completar_tarea":
          data = await api(token, "completar_tarea", {}, "POST", args);
          break;
        case "actualizar_prospecto":
          data = await api(token, "actualizar_prospecto", {}, "POST", args);
          break;
        case "listar_automatizaciones":
          data = await api(token, "automatizaciones", {});
          break;
        case "iniciar_automatizacion":
          data = await api(token, "iniciar_automatizacion", {}, "POST", args);
          break;
        case "enviar_whatsapp":
          data = await api(token, "enviar_whatsapp", {}, "POST", args);
          break;
        case "enviar_email":
          data = await api(token, "enviar_email", {}, "POST", args);
          break;
        case "listar_propiedades":
          data = await api(token, "propiedades", { q: args.busqueda, estado: args.estado, max: args.precio_maximo });
          break;
        case "ver_propiedad":
          data = await api(token, "propiedad", { id: args.id });
          break;
        case "crear_propiedad":
          data = await api(token, "crear_propiedad", {}, "POST", args);
          break;
        case "actualizar_propiedad":
          data = await api(token, "actualizar_propiedad", {}, "POST", args);
          break;
        case "generar_presupuesto":
          data = await api(token, "crear_presupuesto", {}, "POST", args);
          break;
        case "listar_presupuestos":
          data = await api(token, "presupuestos", { estado: args.estado, limit: args.limite || 20 });
          break;
        case "ver_presupuesto":
          data = await api(token, "presupuesto", { id: args.id });
          break;
        case "listar_facturas":
          data = await api(token, "facturas", { estado: args.estado, limit: args.limite || 20 });
          break;
        case "ver_factura":
          data = await api(token, "factura", { id: args.id });
          break;
        case "listar_contratos":
          data = await api(token, "contratos", { estado: args.estado, limit: args.limite || 20 });
          break;
        case "ver_contrato":
          data = await api(token, "contrato", { id: args.id });
          break;
        case "crear_cliente":
          data = await api(token, "crear_cliente", {}, "POST", args);
          break;
        case "actualizar_cliente":
          data = await api(token, "actualizar_cliente", {}, "POST", args);
          break;
        case "listar_visitas":
          data = await api(token, "visitas", { estado: args.estado, solo_hoy: args.solo_hoy ? 1 : 0, limit: args.limite || 20 });
          break;
        case "crear_visita":
          data = await api(token, "crear_visita", {}, "POST", args);
          break;
        case "listar_campanas":
          data = await api(token, "campanas", { estado: args.estado, limit: args.limite || 20 });
          break;
        case "estadisticas":
          data = await api(token, "estadisticas", { periodo: args.periodo || "mes" });
          break;
        case "buscar":
          data = await api(token, "buscar", { q: args.q });
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

// ── Modo SSE: servidor HTTP ────────────────────────────────────────────────
if (MODE === "sse") {
  const { default: express }               = await import("express");
  const { SSEServerTransport }             = await import("@modelcontextprotocol/sdk/server/sse.js");
  const { StreamableHTTPServerTransport }  = await import("@modelcontextprotocol/sdk/server/streamableHttp.js");

  const app = express();

  // CORS — necesario para IAs web y Cloudflare tunnel
  app.use((req, res, next) => {
    const origin = req.headers.origin || "*";
    res.header("Access-Control-Allow-Origin", origin);
    res.header("Access-Control-Allow-Methods", "GET, POST, DELETE, OPTIONS");
    res.header("Access-Control-Allow-Headers", "Content-Type, Authorization, mcp-session-id, x-requested-with");
    res.header("Access-Control-Allow-Credentials", "true");
    if (req.method === "OPTIONS") return res.status(200).end();
    next();
  });

  app.use(express.json());
  app.use(express.urlencoded({ extended: true }));

  // ── Almacenes en memoria ─────────────────────────────────────────────────
  const oauthClients    = {};  // clientId → clientSecret
  const authCodes       = {};  // code     → token_del_crm
  const sesionesSSE     = {};  // sessionId → SSEServerTransport (legacy)
  const sesionesHTTP    = new Map(); // sessionId → StreamableHTTPServerTransport (nuevo)

  // ── Protected Resource Metadata (requerido por MCP + OAuth para Claude.ai web) ──
  // Claude.ai lo consulta para saber dónde está el endpoint MCP y qué auth server lo protege.
  app.get("/.well-known/oauth-protected-resource", (req, res) => {
    const base = `https://${req.get("host")}`;
    res.json({
      resource:                    base,
      authorization_servers:       [base],
      bearer_methods_supported:    ["header"],
      mcp_endpoint:                `${base}/mcp`,
    });
  });

  // ── Helper interno para manejar peticiones MCP (Streamable HTTP) ──────────
  async function handleMcp(req, res) {
    let token = "";
    if (req.headers.authorization) {
      token = req.headers.authorization.replace(/^Bearer\s+/i, "").trim();
    }
    if (!token && req.query.token) {
      token = String(req.query.token).trim();
    }

    const sessionId = req.headers["mcp-session-id"];

    // Sesión ya existente — delegar al transporte correcto
    if (sessionId && sesionesHTTP.has(sessionId)) {
      const { transport } = sesionesHTTP.get(sessionId);
      await transport.handleRequest(req, res, req.body);
      return;
    }

    // Nueva sesión — solo se abre con POST (mensaje de inicialización)
    if (req.method !== "POST") {
      res.status(405).set("Allow", "POST").json({ error: "Method Not Allowed — usa POST para iniciar sesión MCP" });
      return;
    }

    if (token.length < 32) {
      res.status(401).json({ error: "Token inválido. Genera uno en el CRM → Ajustes → Conector Claude (MCP)" });
      return;
    }

    const transport = new StreamableHTTPServerTransport({
      sessionIdGenerator: () => randomUUID(),
    });

    const servidor = crearServidor(token);
    await servidor.connect(transport);

    transport.onclose = () => {
      if (transport.sessionId) sesionesHTTP.delete(transport.sessionId);
    };

    await transport.handleRequest(req, res, req.body);

    // Guardar sesión para peticiones sucesivas
    if (transport.sessionId) {
      sesionesHTTP.set(transport.sessionId, { transport, servidor });
    }
  }

  // /mcp — endpoint principal (Claude Code, Desktop)
  app.all("/mcp", handleMcp);
  // Alias en raíz — Claude.ai web puede usar la URL base directamente como endpoint MCP
  app.post("/", handleMcp);

  // ── [LEGACY] OAuth Discovery (Automatic Registration) ───────────────────
  app.get("/.well-known/oauth-authorization-server", (req, res) => {
    const baseUrl = `https://${req.get("host")}`;
    res.json({
      issuer:                               baseUrl,
      authorization_endpoint:              `${baseUrl}/authorize`,
      token_endpoint:                       `${baseUrl}/token`,
      registration_endpoint:               `${baseUrl}/register`,
      scopes_supported:                    ["mcp"],
      response_types_supported:            ["code"],
      grant_types_supported:               ["authorization_code"],
      token_endpoint_auth_methods_supported: ["client_secret_basic", "client_secret_post"],
    });
  });

  // ── [LEGACY] Dynamic Client Registration ────────────────────────────────
  app.post("/register", (req, res) => {
    console.log("[OAUTH] /register req.body:", req.body);
    const clientId     = randomBytes(16).toString("hex");
    const clientSecret = randomBytes(32).toString("hex");
    oauthClients[clientId] = clientSecret;
    const clientData = req.body || {};
    res.status(201).json({
      client_id:               clientId,
      client_secret:           clientSecret,
      client_id_issued_at:     Math.floor(Date.now() / 1000),
      client_secret_expires_at: 0,
      client_name:             clientData.client_name || "MCP Client",
      redirect_uris:           clientData.redirect_uris || [],
      grant_types:             clientData.grant_types || ["authorization_code"],
      response_types:          clientData.response_types || ["code"],
      token_endpoint_auth_method: clientData.token_endpoint_auth_method || "client_secret_post",
      logo_uri:                clientData.logo_uri || "",
    });
  });

  // ── [LEGACY] Authorization Endpoint (página HTML) ───────────────────────
  app.get("/authorize", (req, res) => {
    console.log("[OAUTH] /authorize req.query:", req.query);
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
            <input type="hidden" name="redirect_uri" value="${redirect_uri || ""}">
            <input type="hidden" name="state" value="${state || ""}">
            <input type="text" name="crm_token" placeholder="Ej: f3b1c9... (64 caracteres)" required>
            <button type="submit">Conectar</button>
          </form>
        </div>
      </body>
      </html>
    `);
  });

  app.post("/authorize", (req, res) => {
    const { redirect_uri, state, crm_token } = req.body;
    if (!redirect_uri) return res.status(400).send("Falta redirect_uri");
    const code = randomBytes(16).toString("hex");
    authCodes[code] = crm_token;
    const url = new URL(redirect_uri);
    url.searchParams.set("code", code);
    if (state) url.searchParams.set("state", state);
    res.redirect(url.toString());
  });

  app.post("/token", (req, res) => {
    console.log("[OAUTH] /token req.body:", req.body);
    const code = req.body.code || req.query.code;
    if (!code || !authCodes[code]) {
      return res.status(400).json({ error: "invalid_grant" });
    }
    const trueToken = authCodes[code];
    delete authCodes[code];
    res.json({ access_token: trueToken, token_type: "Bearer", expires_in: 315360000 });
  });

  // ── [LEGACY] SSE endpoint (/sse + /messages) — Claude.ai web, Perplexity ─
  app.get("/sse", async (req, res) => {
    let token = req.query.token || "";
    if (!token && req.headers.authorization) {
      token = req.headers.authorization.replace(/^Bearer\s+/i, "").trim();
    }
    if (token.length < 32) {
      res.status(401).json({ error: "Token inválido. Genera uno en el CRM → Ajustes → Conector Claude (MCP)" });
      return;
    }
    const transport = new SSEServerTransport("/messages", res);
    sesionesSSE[transport.sessionId] = transport;

    // Ping cada 25s para evitar que Cloudflare corte la conexión SSE por inactividad (~100s timeout)
    const keepalive = setInterval(() => {
      if (!res.writableEnded) res.write(": ping\n\n");
    }, 25000);

    res.on("close", () => {
      clearInterval(keepalive);
      delete sesionesSSE[transport.sessionId];
    });

    const servidor = crearServidor(token);
    await servidor.connect(transport);
  });

  app.post("/messages", async (req, res) => {
    const transport = sesionesSSE[req.query.sessionId];
    if (!transport) { res.status(404).end(); return; }
    await transport.handlePostMessage(req, res);
  });

  // ── Health check ─────────────────────────────────────────────────────────
  app.get("/health", (_req, res) => {
    res.json({ status: "ok", modo: "sse", puerto: PORT, crm: CRM_URL });
  });

  app.listen(PORT, "0.0.0.0", () => {
    console.log("─────────────────────────────────────────────────────────────");
    console.log(`  ✅  CRM MCP Server corriendo en puerto ${PORT}`);
    console.log("─────────────────────────────────────────────────────────────");
    console.log(`  CRM:          ${CRM_URL}`);
    console.log(`  Health check: http://localhost:${PORT}/health`);
    console.log("");
    console.log("  ▶ Claude Code CLI / Desktop (Streamable HTTP — recomendado):");
    console.log(`    URL: http://localhost:${PORT}/mcp`);
    console.log(`    Header: Authorization: Bearer TU_TOKEN`);
    console.log("");
    console.log("  ▶ Claude.ai web / Perplexity (SSE legacy + OAuth):");
    console.log(`    URL: http://localhost:${PORT}/sse?token=TU_TOKEN`);
    console.log("─────────────────────────────────────────────────────────────\n");
  });

// ── Modo stdio: proceso local (un solo usuario, sin red) ───────────────────
} else {
  const { StdioServerTransport } = await import("@modelcontextprotocol/sdk/server/stdio.js");
  const transport = new StdioServerTransport();
  const servidor  = crearServidor(CRM_TOKEN);
  await servidor.connect(transport);
}
