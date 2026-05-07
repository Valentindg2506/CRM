#!/usr/bin/env node
/**
 * CRM MCP Connector — spec-compliant OAuth 2.1 + Streamable HTTP
 * MODE=stdio → Claude Code / Desktop (sin OAuth)
 * MODE=sse   → Claude.ai web (con OAuth)
 */

import { config as loadEnv } from "dotenv";
import { fileURLToPath } from "url";
import { dirname, join } from "path";
import { randomBytes, createHash, randomUUID } from "crypto";

const __dirname = dirname(fileURLToPath(import.meta.url));
loadEnv({ path: join(__dirname, ".env") });

const CRM_URL      = (process.env.CRM_URL || "").replace(/\/$/, "");
const CRM_API_PATH = process.env.CRM_API_PATH || "/api/mcp_api.php";
const CRM_TOKEN    = process.env.CRM_TOKEN || "";
const MODE         = process.env.MODE || "stdio";
const PORT         = parseInt(process.env.PORT || "3001");

if (!CRM_URL) { process.stderr.write("[MCP] Error: falta CRM_URL en .env\n"); process.exit(1); }
if (MODE === "stdio" && !CRM_TOKEN) { process.stderr.write("[MCP] Error: falta CRM_TOKEN en .env\n"); process.exit(1); }

// ── API del CRM ───────────────────────────────────────────────────────────
async function api(token, action, params = {}, method = "GET", body = null) {
  const url = new URL(`${CRM_URL}${CRM_API_PATH}`);
  url.searchParams.set("action", action);
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== "") url.searchParams.set(k, String(v));
  }
  console.log(`[API] ${action} token=${token ? token.slice(0,6)+"..." : "NONE"}`);
  const opts = { method, headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" } };
  if (body && method !== "GET") opts.body = JSON.stringify(body);
  const res  = await fetch(url.toString(), opts);
  const text = await res.text();
  console.log(`[API] ${action} → ${res.status} ${text.slice(0, 80)}`);
  if (!res.ok) throw new Error(`API ${res.status}: ${text.slice(0, 300)}`);
  try { return JSON.parse(text); }
  catch { throw new Error(`Respuesta no JSON: ${text.slice(0, 200)}`); }
}

// ── Herramientas MCP ──────────────────────────────────────────────────────
const TOOLS = [
  { name: "resumen_dashboard", description: "Resumen del CRM: prospectos por etapa, clientes, tareas pendientes hoy y contactos a hacer hoy.", inputSchema: { type: "object", properties: {} } },
  { name: "estadisticas", description: "Estadísticas de ventas por período (hoy/semana/mes/trimestre/anio).", inputSchema: { type: "object", properties: { periodo: { type: "string", enum: ["hoy","semana","mes","trimestre","anio"] } } } },
  { name: "buscar", description: "Búsqueda global en prospectos, clientes y propiedades.", inputSchema: { type: "object", properties: { q: { type: "string" } }, required: ["q"] } },
  { name: "listar_prospectos", description: "Lista prospectos con filtros opcionales.", inputSchema: { type: "object", properties: { busqueda: { type: "string" }, etapa: { type: "string" }, temperatura: { type: "string" }, contactar_hoy: { type: "boolean" }, limite: { type: "number" } } } },
  { name: "ver_prospecto", description: "Datos completos e historial de un prospecto.", inputSchema: { type: "object", properties: { id: { type: "number" } }, required: ["id"] } },
  { name: "crear_prospecto", description: "Crea un nuevo prospecto.", inputSchema: { type: "object", properties: { nombre: { type: "string" }, telefono: { type: "string" }, email: { type: "string" }, tipo_propiedad: { type: "string" }, precio_estimado: { type: "number" }, localidad: { type: "string" }, provincia: { type: "string" }, notas: { type: "string" }, etapa: { type: "string" }, temperatura: { type: "string" } }, required: ["nombre"] } },
  { name: "actualizar_prospecto", description: "Actualiza etapa, temperatura, notas o fecha de contacto.", inputSchema: { type: "object", properties: { id: { type: "number" }, etapa: { type: "string" }, temperatura: { type: "string" }, notas_internas: { type: "string" }, fecha_proximo_contacto: { type: "string" }, proxima_accion: { type: "string" } }, required: ["id"] } },
  { name: "programar_contacto", description: "Cambia fecha próximo contacto / próxima acción (acepta id o ids).", inputSchema: { type: "object", properties: { id: { type: "number" }, ids: { type: "string" }, fecha: { type: "string" }, proxima_accion: { type: "string" }, temperatura: { type: "string" }, etapa: { type: "string" } } } },
  { name: "mover_etapas", description: "Mueve prospectos a una etapa del pipeline.", inputSchema: { type: "object", properties: { id: { type: "number" }, ids: { type: "string" }, etapa: { type: "string", enum: ["nuevo_lead","contactado","seguimiento","visita_programada","en_negociacion","captado","descartado"] } }, required: ["etapa"] } },
  { name: "anadir_nota", description: "Añade una nota al historial de un prospecto.", inputSchema: { type: "object", properties: { prospecto_id: { type: "number" }, contenido: { type: "string" }, tipo: { type: "string" } }, required: ["prospecto_id","contenido"] } },
  { name: "convertir_a_cliente", description: "Convierte un prospecto en cliente.", inputSchema: { type: "object", properties: { prospecto_id: { type: "number" }, tipo: { type: "string" } }, required: ["prospecto_id"] } },
  { name: "informe_prospectos", description: "Informe del embudo de captación.", inputSchema: { type: "object", properties: {} } },
  { name: "listar_clientes", description: "Lista clientes con filtros opcionales.", inputSchema: { type: "object", properties: { busqueda: { type: "string" }, tipo: { type: "string" }, limite: { type: "number" } } } },
  { name: "ver_cliente", description: "Datos completos de un cliente.", inputSchema: { type: "object", properties: { id: { type: "number" } }, required: ["id"] } },
  { name: "crear_cliente", description: "Crea un nuevo cliente.", inputSchema: { type: "object", properties: { nombre: { type: "string" }, apellidos: { type: "string" }, tipo: { type: "string" }, email: { type: "string" }, telefono: { type: "string" }, localidad: { type: "string" }, notas: { type: "string" } }, required: ["nombre"] } },
  { name: "actualizar_cliente", description: "Actualiza datos de un cliente.", inputSchema: { type: "object", properties: { id: { type: "number" }, nombre: { type: "string" }, apellidos: { type: "string" }, tipo: { type: "string" }, email: { type: "string" }, telefono: { type: "string" }, notas: { type: "string" } }, required: ["id"] } },
  { name: "anadir_nota_cliente", description: "Añade una nota a un cliente.", inputSchema: { type: "object", properties: { cliente_id: { type: "number" }, contenido: { type: "string" }, tipo: { type: "string" } }, required: ["cliente_id","contenido"] } },
  { name: "listar_tareas", description: "Lista tareas del usuario.", inputSchema: { type: "object", properties: { estado: { type: "string" }, solo_hoy: { type: "boolean" } } } },
  { name: "crear_tarea", description: "Crea una nueva tarea.", inputSchema: { type: "object", properties: { titulo: { type: "string" }, tipo: { type: "string" }, fecha_vencimiento: { type: "string" }, descripcion: { type: "string" }, prioridad: { type: "string" }, cliente_id: { type: "number" } }, required: ["titulo","tipo"] } },
  { name: "completar_tarea", description: "Marca una tarea como completada.", inputSchema: { type: "object", properties: { id: { type: "number" } }, required: ["id"] } },
  { name: "actualizar_tarea", description: "Actualiza estado, prioridad o fecha de una tarea.", inputSchema: { type: "object", properties: { id: { type: "number" }, titulo: { type: "string" }, estado: { type: "string" }, prioridad: { type: "string" }, fecha_vencimiento: { type: "string" } }, required: ["id"] } },
  { name: "cancelar_tarea", description: "Cancela una tarea.", inputSchema: { type: "object", properties: { id: { type: "number" } }, required: ["id"] } },
  { name: "listar_propiedades", description: "Busca propiedades en el catálogo.", inputSchema: { type: "object", properties: { busqueda: { type: "string" }, estado: { type: "string" }, precio_maximo: { type: "number" }, limite: { type: "number" } } } },
  { name: "ver_propiedad", description: "Datos completos de una propiedad.", inputSchema: { type: "object", properties: { id: { type: "number" } }, required: ["id"] } },
  { name: "crear_propiedad", description: "Da de alta una propiedad en el catálogo.", inputSchema: { type: "object", properties: { titulo: { type: "string" }, tipo: { type: "string" }, estado: { type: "string" }, precio: { type: "number" }, localidad: { type: "string" }, descripcion: { type: "string" }, habitaciones: { type: "number" }, banos: { type: "number" }, metros: { type: "number" } }, required: ["titulo"] } },
  { name: "actualizar_propiedad", description: "Actualiza estado o precio de una propiedad.", inputSchema: { type: "object", properties: { id: { type: "number" }, estado: { type: "string" }, precio: { type: "number" }, notas: { type: "string" } }, required: ["id"] } },
  { name: "listar_visitas", description: "Lista visitas programadas.", inputSchema: { type: "object", properties: { estado: { type: "string" }, solo_hoy: { type: "boolean" }, limite: { type: "number" } } } },
  { name: "crear_visita", description: "Programa una visita (fecha: YYYY-MM-DD HH:MM).", inputSchema: { type: "object", properties: { propiedad_id: { type: "number" }, cliente_id: { type: "number" }, fecha: { type: "string" }, duracion_min: { type: "number" }, notas: { type: "string" } }, required: ["propiedad_id","fecha"] } },
  { name: "actualizar_visita", description: "Actualiza estado de una visita.", inputSchema: { type: "object", properties: { id: { type: "number" }, estado: { type: "string", enum: ["programada","realizada","cancelada","no_presentado"] } }, required: ["id","estado"] } },
  { name: "listar_presupuestos", description: "Lista presupuestos.", inputSchema: { type: "object", properties: { estado: { type: "string" }, limite: { type: "number" } } } },
  { name: "ver_presupuesto", description: "Detalle de un presupuesto.", inputSchema: { type: "object", properties: { id: { type: "number" } }, required: ["id"] } },
  { name: "crear_presupuesto", description: "Crea un presupuesto.", inputSchema: { type: "object", properties: { titulo: { type: "string" }, cliente_id: { type: "number" }, total: { type: "number" }, detalles: { type: "string" }, validez_dias: { type: "number" } }, required: ["total"] } },
  { name: "listar_facturas", description: "Lista facturas.", inputSchema: { type: "object", properties: { estado: { type: "string" }, limite: { type: "number" } } } },
  { name: "ver_factura", description: "Detalle de una factura.", inputSchema: { type: "object", properties: { id: { type: "number" } }, required: ["id"] } },
  { name: "listar_contratos", description: "Lista contratos.", inputSchema: { type: "object", properties: { estado: { type: "string" }, limite: { type: "number" } } } },
  { name: "ver_contrato", description: "Detalle de un contrato.", inputSchema: { type: "object", properties: { id: { type: "number" } }, required: ["id"] } },
  { name: "enviar_contrato", description: "Envía un contrato por email al cliente para firmarlo.", inputSchema: { type: "object", properties: { id: { type: "number" } }, required: ["id"] } },
  { name: "enviar_whatsapp", description: "Envía un WhatsApp a un prospecto.", inputSchema: { type: "object", properties: { prospecto_id: { type: "number" }, mensaje: { type: "string" } }, required: ["prospecto_id","mensaje"] } },
  { name: "enviar_email", description: "Envía un email a un prospecto.", inputSchema: { type: "object", properties: { prospecto_id: { type: "number" }, asunto: { type: "string" }, cuerpo_html: { type: "string" } }, required: ["prospecto_id","asunto","cuerpo_html"] } },
  { name: "listar_finanzas", description: "Lista comisiones y registros financieros.", inputSchema: { type: "object", properties: { estado: { type: "string" }, tipo: { type: "string" }, limite: { type: "number" } } } },
  { name: "registrar_comision", description: "Crea un registro de comisión u honorario.", inputSchema: { type: "object", properties: { concepto: { type: "string" }, importe: { type: "number" }, iva: { type: "number" }, tipo: { type: "string" }, estado: { type: "string" }, fecha: { type: "string" }, cliente_id: { type: "number" }, propiedad_id: { type: "number" }, notas: { type: "string" } }, required: ["concepto","importe"] } },
  { name: "marcar_cobrado", description: "Marca una comisión como cobrada.", inputSchema: { type: "object", properties: { id: { type: "number" } }, required: ["id"] } },
  { name: "informe_finanzas", description: "Informe financiero de los últimos 6 meses.", inputSchema: { type: "object", properties: {} } },
  { name: "listar_automatizaciones", description: "Lista automatizaciones disponibles.", inputSchema: { type: "object", properties: {} } },
  { name: "iniciar_automatizacion", description: "Inicia una automatización sobre un prospecto.", inputSchema: { type: "object", properties: { automatizacion_id: { type: "number" }, prospecto_id: { type: "number" } }, required: ["automatizacion_id","prospecto_id"] } },
  { name: "listar_eventos", description: "Lista eventos del calendario.", inputSchema: { type: "object", properties: { desde: { type: "string" }, hasta: { type: "string" }, limite: { type: "number" } } } },
  { name: "crear_evento", description: "Crea un evento en el calendario.", inputSchema: { type: "object", properties: { titulo: { type: "string" }, tipo: { type: "string" }, fecha_inicio: { type: "string" }, fecha_fin: { type: "string" }, ubicacion: { type: "string" }, cliente_id: { type: "number" }, propiedad_id: { type: "number" } }, required: ["titulo","fecha_inicio"] } },
  { name: "listar_campanas", description: "Lista campañas de marketing.", inputSchema: { type: "object", properties: { estado: { type: "string" }, limite: { type: "number" } } } },
  { name: "listar_documentos", description: "Lista documentos de un cliente o propiedad.", inputSchema: { type: "object", properties: { cliente_id: { type: "number" }, propiedad_id: { type: "number" }, limite: { type: "number" } } } },
  { name: "pipeline_kanban", description: "Vista Kanban de pipelines de ventas.", inputSchema: { type: "object", properties: { pipeline_id: { type: "number" } } } },
  { name: "portales_propiedad", description: "Portales donde está publicada una propiedad.", inputSchema: { type: "object", properties: { propiedad_id: { type: "number" } }, required: ["propiedad_id"] } },
  { name: "publicar_portal", description: "Publica o retira una propiedad de un portal.", inputSchema: { type: "object", properties: { propiedad_id: { type: "number" }, portal_id: { type: "number" }, accion: { type: "string", enum: ["publicar","retirar"] }, url: { type: "string" } }, required: ["propiedad_id","portal_id"] } },
];

// ── Ejecutar herramienta ──────────────────────────────────────────────────
async function callTool(token, name, args) {
  switch (name) {
    case "resumen_dashboard":     return api(token, "resumen");
    case "estadisticas":          return api(token, "estadisticas", { periodo: args.periodo || "mes" });
    case "buscar":                return api(token, "buscar", { q: args.q });
    case "listar_prospectos":     return api(token, "prospectos", { q: args.busqueda, etapa: args.etapa, temperatura: args.temperatura, contactar_hoy: args.contactar_hoy ? 1 : undefined, limit: args.limite || 20 });
    case "ver_prospecto":         return api(token, "prospecto", { id: args.id });
    case "crear_prospecto":       return api(token, "crear_prospecto", {}, "POST", args);
    case "actualizar_prospecto":  return api(token, "actualizar_prospecto", {}, "POST", args);
    case "programar_contacto":    return api(token, "programar_contacto", {}, "POST", args);
    case "mover_etapas":          return api(token, "mover_etapas", {}, "POST", args);
    case "anadir_nota":           return api(token, "anadir_nota", {}, "POST", args);
    case "convertir_a_cliente":   return api(token, "convertir_cliente", {}, "POST", args);
    case "informe_prospectos":    return api(token, "informe_prospectos");
    case "listar_clientes":       return api(token, "clientes", { q: args.busqueda, tipo: args.tipo, limit: args.limite || 20 });
    case "ver_cliente":           return api(token, "cliente", { id: args.id });
    case "crear_cliente":         return api(token, "crear_cliente", {}, "POST", args);
    case "actualizar_cliente":    return api(token, "actualizar_cliente", {}, "POST", args);
    case "anadir_nota_cliente":   return api(token, "anadir_nota_cliente", {}, "POST", args);
    case "listar_tareas":         return api(token, "tareas", { estado: args.estado, solo_hoy: args.solo_hoy ? 1 : 0 });
    case "crear_tarea":           return api(token, "crear_tarea", {}, "POST", args);
    case "completar_tarea":       return api(token, "completar_tarea", {}, "POST", args);
    case "actualizar_tarea":      return api(token, "actualizar_tarea", {}, "POST", args);
    case "cancelar_tarea":        return api(token, "cancelar_tarea", {}, "POST", args);
    case "listar_propiedades":    return api(token, "propiedades", { q: args.busqueda, estado: args.estado, max: args.precio_maximo, limit: args.limite || 20 });
    case "ver_propiedad":         return api(token, "propiedad", { id: args.id });
    case "crear_propiedad":       return api(token, "crear_propiedad", {}, "POST", args);
    case "actualizar_propiedad":  return api(token, "actualizar_propiedad", {}, "POST", args);
    case "listar_visitas":        return api(token, "visitas", { estado: args.estado, solo_hoy: args.solo_hoy ? 1 : 0, limit: args.limite || 20 });
    case "crear_visita":          return api(token, "crear_visita", {}, "POST", args);
    case "actualizar_visita":     return api(token, "actualizar_visita", {}, "POST", args);
    case "listar_presupuestos":   return api(token, "presupuestos", { estado: args.estado, limit: args.limite || 20 });
    case "ver_presupuesto":       return api(token, "presupuesto", { id: args.id });
    case "crear_presupuesto":     return api(token, "crear_presupuesto", {}, "POST", args);
    case "listar_facturas":       return api(token, "facturas", { estado: args.estado, limit: args.limite || 20 });
    case "ver_factura":           return api(token, "factura", { id: args.id });
    case "listar_contratos":      return api(token, "contratos", { estado: args.estado, limit: args.limite || 20 });
    case "ver_contrato":          return api(token, "contrato", { id: args.id });
    case "enviar_contrato":       return api(token, "enviar_contrato", {}, "POST", args);
    case "enviar_whatsapp":       return api(token, "enviar_whatsapp", {}, "POST", args);
    case "enviar_email":          return api(token, "enviar_email", {}, "POST", args);
    case "listar_finanzas":       return api(token, "finanzas", { estado: args.estado, tipo: args.tipo, limit: args.limite || 20 });
    case "registrar_comision":    return api(token, "crear_finanza", {}, "POST", args);
    case "marcar_cobrado":        return api(token, "marcar_cobrado", {}, "POST", args);
    case "informe_finanzas":      return api(token, "informe_finanzas");
    case "listar_automatizaciones": return api(token, "automatizaciones");
    case "iniciar_automatizacion":  return api(token, "iniciar_automatizacion", {}, "POST", args);
    case "listar_eventos":        return api(token, "calendario", { desde: args.desde, hasta: args.hasta, limit: args.limite || 50 });
    case "crear_evento":          return api(token, "crear_evento", {}, "POST", args);
    case "listar_campanas":       return api(token, "campanas", { estado: args.estado, limit: args.limite || 20 });
    case "listar_documentos":     return api(token, "documentos", { cliente_id: args.cliente_id, propiedad_id: args.propiedad_id, limit: args.limite || 20 });
    case "pipeline_kanban":       return api(token, "pipeline_kanban", { pipeline_id: args.pipeline_id });
    case "portales_propiedad":    return api(token, "portales_propiedad", { propiedad_id: args.propiedad_id });
    case "publicar_portal":       return api(token, "publicar_portal", {}, "POST", args);
    default: throw new Error(`Herramienta desconocida: ${name}`);
  }
}

// ── Modo HTTP con OAuth ───────────────────────────────────────────────────
if (MODE === "sse") {
  const { default: express } = await import("express");
  const app = express();

  // CORS
  app.use((req, res, next) => {
    const origin = req.headers.origin || "*";
    res.setHeader("Access-Control-Allow-Origin", origin);
    res.setHeader("Access-Control-Allow-Methods", "GET, POST, DELETE, OPTIONS");
    res.setHeader("Access-Control-Allow-Headers", "Content-Type, Authorization, Mcp-Session-Id");
    res.setHeader("Access-Control-Allow-Credentials", "true");
    res.setHeader("Access-Control-Expose-Headers", "Mcp-Session-Id");
    if (req.method === "OPTIONS") return res.status(204).end();
    next();
  });

  app.use(express.json());
  app.use(express.urlencoded({ extended: true }));

  // Logging completo
  app.use((req, _res, next) => {
    const auth = req.headers.authorization ? req.headers.authorization.slice(0, 25) + "..." : "-";
    console.log(`[${new Date().toISOString()}] ${req.method} ${req.path} auth=${auth}`);
    if (req.body && Object.keys(req.body).length > 0) {
      const safe = { ...req.body };
      if (safe.crm_token) safe.crm_token = safe.crm_token.slice(0, 6) + "...";
      console.log(`  body: ${JSON.stringify(safe).slice(0, 150)}`);
    }
    next();
  });

  const authCodes   = {};  // code → { token, code_challenge, expires }
  const oauthClients = {}; // clientId → { secret, redirectUris }

  const SCOPES = ["mcp", "claudeai"]; // acepta ambos (Bug #653: Claude siempre manda "claudeai")

  // ── OAuth Discovery ──────────────────────────────────────────────────────
  app.get("/.well-known/oauth-protected-resource", (req, res) => {
    const base = `https://${req.get("host")}`;
    res.json({
      resource:                 `${base}/mcp`,
      authorization_servers:    [base],
      bearer_methods_supported: ["header"],
      scopes_supported:         SCOPES,
    });
  });

  app.get("/.well-known/oauth-authorization-server", (req, res) => {
    const base = `https://${req.get("host")}`;
    res.json({
      issuer:                                 base,
      authorization_endpoint:                 `${base}/authorize`,
      token_endpoint:                         `${base}/token`,
      registration_endpoint:                  `${base}/register`,
      scopes_supported:                       SCOPES,
      response_types_supported:               ["code"],
      grant_types_supported:                  ["authorization_code"],
      code_challenge_methods_supported:       ["S256"],
      token_endpoint_auth_methods_supported:  ["none", "client_secret_post", "client_secret_basic"],
    });
  });

  // ── Dynamic Client Registration ──────────────────────────────────────────
  app.post("/register", (req, res) => {
    const clientId     = randomBytes(16).toString("hex");
    const clientSecret = randomBytes(32).toString("hex");
    const redirectUris = req.body?.redirect_uris || [];
    oauthClients[clientId] = { secret: clientSecret, redirectUris };
    res.status(201).json({
      client_id:                clientId,
      client_secret:            clientSecret,
      client_id_issued_at:      Math.floor(Date.now() / 1000),
      client_secret_expires_at: 0,
      redirect_uris:            redirectUris,
      grant_types:              ["authorization_code"],
      response_types:           ["code"],
      token_endpoint_auth_method: "none",
      client_name:              req.body?.client_name || "MCP Client",
    });
  });

  // ── Authorization form ───────────────────────────────────────────────────
  app.get("/authorize", (req, res) => {
    // Pasar todos los OAuth params al form (sin path)
    const p = { ...req.query };
    const params = new URLSearchParams(p).toString();
    res.setHeader("Content-Type", "text/html; charset=utf-8");
    res.send(`<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Conectar CRM</title>
<style>
  *{box-sizing:border-box}body{font-family:system-ui,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f0f2f5}
  .card{background:#fff;padding:2.5rem;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.1);max-width:440px;width:100%;margin:1rem}
  h2{margin:0 0 .5rem;color:#111;font-size:1.4rem}p{color:#555;font-size:.95rem;margin:.5rem 0 1rem}
  label{font-size:.85rem;color:#333;font-weight:600;display:block;margin-bottom:4px}
  input{width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:8px;font-family:monospace;font-size:.88rem;transition:border .2s}
  input:focus{outline:none;border-color:#0066cc}
  button{width:100%;padding:12px;background:#0066cc;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:1rem;font-weight:700;margin-top:1rem;transition:background .2s}
  button:hover{background:#0055aa}.hint{font-size:.78rem;color:#888;margin-top:12px;text-align:center}
</style></head><body>
<div class="card">
  <h2>Conectar CRM a Claude</h2>
  <p>Introduce tu token personal del CRM.<br>
     Lo encuentras en <strong>Ajustes → Conector Claude (MCP)</strong>.</p>
  <form method="POST" action="/authorize?${params}">
    <label for="tok">Token personal (64 caracteres)</label>
    <input id="tok" type="text" name="crm_token" placeholder="a1b2c3d4e5f6..." autocomplete="off" required minlength="32">
    <button type="submit">Conectar</button>
  </form>
  <p class="hint">Cada usuario del CRM tiene su propio token.</p>
</div></body></html>`);
  });

  app.post("/authorize", (req, res) => {
    const redirectUri = req.query.redirect_uri || req.body.redirect_uri || "";
    const state       = req.query.state        || req.body.state        || "";
    const challenge   = req.query.code_challenge        || "";
    const crmToken    = (req.body.crm_token || "").trim();

    if (!redirectUri) return res.status(400).send("Falta redirect_uri");
    if (!crmToken)    return res.status(400).send("Falta el token del CRM");

    const code = randomBytes(16).toString("hex");
    authCodes[code] = { token: crmToken, code_challenge: challenge, expires: Date.now() + 300_000 };

    const callbackUrl = new URL(redirectUri);
    callbackUrl.searchParams.set("code", code);
    if (state) callbackUrl.searchParams.set("state", state);
    console.log(`[OAUTH] Autorizado → code=${code.slice(0,8)}... → ${callbackUrl.hostname}`);
    res.redirect(callbackUrl.toString());
  });

  // ── Token endpoint ───────────────────────────────────────────────────────
  app.post("/token", (req, res) => {
    res.setHeader("Cache-Control", "no-store");
    res.setHeader("Pragma", "no-cache");

    const code = req.body.code || req.query.code || "";
    if (!code || !authCodes[code]) {
      console.log(`[OAUTH] /token → código inválido: ${code?.slice(0,8)}`);
      return res.status(400).json({ error: "invalid_grant", error_description: "Código inválido o expirado" });
    }

    const entry = authCodes[code];
    delete authCodes[code];

    if (entry.expires < Date.now()) {
      return res.status(400).json({ error: "invalid_grant", error_description: "Código expirado" });
    }

    // Validar PKCE
    if (entry.code_challenge) {
      const verifier = req.body.code_verifier || "";
      if (!verifier) return res.status(400).json({ error: "invalid_grant", error_description: "code_verifier requerido" });
      const expected = createHash("sha256").update(verifier).digest("base64url");
      if (expected !== entry.code_challenge) {
        console.log(`[OAUTH] PKCE inválido. Challenge=${entry.code_challenge} | Got=${expected}`);
        return res.status(400).json({ error: "invalid_grant", error_description: "code_verifier inválido" });
      }
    }

    console.log("[OAUTH] /token → token entregado OK");
    res.json({
      access_token:  entry.token,
      token_type:    "Bearer",
      expires_in:    31536000,
      scope:         "mcp",
      refresh_token: randomBytes(32).toString("hex"),
    });
  });

  // ── MCP endpoint — stateless, application/json ───────────────────────────
  async function handleMcp(req, res) {
    const base = `https://${req.get("host")}`;

    // Extraer token (header case-insensitive)
    const authHeader = req.headers.authorization || req.headers["Authorization"] || "";
    const token = authHeader.replace(/^Bearer\s+/i, "").trim();

    // Sin token → 401 con WWW-Authenticate
    if (token.length < 32) {
      console.log(`[MCP] Sin token (${token.length} chars) → 401`);
      res.status(401)
        .setHeader("WWW-Authenticate", `Bearer realm="${base}/mcp", resource_metadata="${base}/.well-known/oauth-protected-resource"`)
        .json({ jsonrpc: "2.0", id: null, error: { code: -32000, message: "Unauthorized — introduce tu token del CRM" } });
      return;
    }

    // GET → 405 (no soportamos SSE server→client)
    if (req.method === "GET") {
      res.status(405).setHeader("Allow", "POST").json({ error: "Use POST for MCP" });
      return;
    }
    if (req.method !== "POST") { res.status(405).end(); return; }

    const body   = req.body || {};
    const method = body.method || "";
    const id     = body.id ?? null;
    const params = body.params || {};

    console.log(`[MCP] method=${method || "(vacío)"} token=${token.slice(0,8)}...`);

    switch (method) {
      case "initialize": {
        const sessionId = randomUUID();
        res.setHeader("Mcp-Session-Id", sessionId);
        return res.json({
          jsonrpc: "2.0", id,
          result: {
            protocolVersion: "2024-11-05",
            capabilities:    { tools: { listChanged: true } },
            serverInfo:      { name: "crm-tinoprop", version: "3.0.0" },
          },
        });
      }

      case "notifications/initialized":
        return res.status(202).end();

      case "ping":
        return res.json({ jsonrpc: "2.0", id, result: {} });

      case "tools/list":
        return res.json({ jsonrpc: "2.0", id, result: { tools: TOOLS } });

      case "tools/call": {
        const toolName = params.name || "";
        const args     = params.arguments || {};
        try {
          const data = await callTool(token, toolName, args);
          return res.json({
            jsonrpc: "2.0", id,
            result: { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] },
          });
        } catch (err) {
          console.error(`[MCP] Error en ${toolName}:`, err.message);
          return res.json({
            jsonrpc: "2.0", id,
            result: { content: [{ type: "text", text: `Error: ${err.message}` }], isError: true },
          });
        }
      }

      default:
        if (!method) {
          // POST vacío o sin método → responder con 401/200 según token
          return res.status(400).json({ jsonrpc: "2.0", id: null, error: { code: -32600, message: "Falta method en JSON-RPC" } });
        }
        return res.json({ jsonrpc: "2.0", id, error: { code: -32601, message: `Método desconocido: ${method}` } });
    }
  }

  // Rutas — acepta /mcp y raíz
  app.all("/mcp", handleMcp);
  app.all("/",    handleMcp);

  // Health
  app.get("/health", (_req, res) => res.json({ status: "ok", crm: CRM_URL, tools: TOOLS.length, protocol: "2024-11-05" }));

  // Catch-all debug
  app.use((req, res) => {
    console.log(`[404] ${req.method} ${req.path} | auth=${req.headers.authorization ? "SÍ" : "NO"} | headers: ${Object.keys(req.headers).join(",")}`);
    res.status(404).json({ error: "Not found", path: req.path });
  });

  app.listen(PORT, "0.0.0.0", () => {
    console.log("────────────────────────────────────────────────────────");
    console.log(`  CRM MCP Server → puerto ${PORT} | ${TOOLS.length} herramientas`);
    console.log(`  CRM: ${CRM_URL}`);
    console.log("────────────────────────────────────────────────────────");
    console.log(`  Claude.ai web → https://mcp.valentindg.store/mcp`);
    console.log("────────────────────────────────────────────────────────\n");
  });

// ── Modo stdio (Claude Code / Desktop) ───────────────────────────────────
} else {
  const { Server }                                             = await import("@modelcontextprotocol/sdk/server/index.js");
  const { StdioServerTransport }                               = await import("@modelcontextprotocol/sdk/server/stdio.js");
  const { CallToolRequestSchema, ListToolsRequestSchema }      = await import("@modelcontextprotocol/sdk/types.js");

  const server = new Server({ name: "crm-tinoprop", version: "3.0.0" }, { capabilities: { tools: {} } });
  server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));
  server.setRequestHandler(CallToolRequestSchema, async (req) => {
    const { name, arguments: args = {} } = req.params;
    try {
      const data = await callTool(CRM_TOKEN, name, args);
      return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
    } catch (err) {
      return { content: [{ type: "text", text: `Error: ${err.message}` }], isError: true };
    }
  });
  await server.connect(new StdioServerTransport());
}
