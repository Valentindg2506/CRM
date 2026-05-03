## MCP Connector — Guía de conexión

URL permanente del conector: **`https://mcp.valentindg.store`**

Esta URL nunca cambia. El servidor y el tunnel arrancan solos al iniciar sesión en el Mac.

---

## Claude Desktop (app de escritorio)

Conecta directamente sin red — lanza el proceso Node.js local.  
**No necesita internet ni que el tunnel esté activo.**

Configurado en:
```
~/Library/Application Support/Claude/claude_desktop_config.json
```

Reinicia Claude Desktop para que detecte cambios.

---

## Claude Code CLI / VS Code Extension

Configurado en `~/.claude/settings.json`:
```json
{
  "mcpServers": {
    "crm": {
      "type": "http",
      "url": "https://mcp.valentindg.store/mcp",
      "headers": {
        "Authorization": "Bearer TU_TOKEN_64_CHARS"
      }
    }
  }
}
```

---

## Claude.ai web / Perplexity

Añadir servidor con la URL base (flujo OAuth automático):
```
https://mcp.valentindg.store
```

O endpoint SSE directo con token:
```
https://mcp.valentindg.store/sse?token=TU_TOKEN
```

---

## Herramientas disponibles para la IA (31 acciones)

| Categoría | Herramientas |
|---|---|
| **Dashboard** | `resumen_dashboard`, `estadisticas`, `buscar` |
| **Prospectos** | `listar_prospectos`, `ver_prospecto`, `crear_prospecto`, `actualizar_prospecto`, `anadir_nota` |
| **Clientes** | `listar_clientes`, `ver_cliente`, `crear_cliente`, `actualizar_cliente` |
| **Propiedades** | `listar_propiedades`, `ver_propiedad`, `crear_propiedad`, `actualizar_propiedad` |
| **Tareas** | `listar_tareas`, `crear_tarea`, `completar_tarea` |
| **Visitas** | `listar_visitas`, `crear_visita` |
| **Comercial** | `listar_presupuestos`, `ver_presupuesto`, `generar_presupuesto`, `listar_facturas`, `ver_factura`, `listar_contratos`, `ver_contrato` |
| **Comunicación** | `enviar_whatsapp`, `enviar_email` |
| **Marketing** | `listar_campanas`, `listar_automatizaciones`, `iniciar_automatizacion` |

---

## Infraestructura (todo automático)

| Servicio | Qué hace | Se inicia |
|---|---|---|
| `com.crm.mcp-server` | Servidor Node.js en puerto 3001 | Al iniciar sesión |
| `com.crm.mcp-tunnel` | Tunnel Cloudflare → `mcp.valentindg.store` | Al iniciar sesión |

Ver logs en tiempo real:
```bash
tail -f /tmp/crm-mcp-server.log   # servidor MCP
tail -f /tmp/crm-mcp-tunnel.log   # tunnel Cloudflare
```

Reiniciar manualmente si algo falla:
```bash
launchctl kickstart -k gui/$(id -u)/com.crm.mcp-server
launchctl kickstart -k gui/$(id -u)/com.crm.mcp-tunnel
```

Health check:
```bash
curl https://mcp.valentindg.store/health
```
