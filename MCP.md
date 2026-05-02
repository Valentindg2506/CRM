## MCP Connector — Guía de conexión

El CRM expone un servidor MCP que permite a las IAs consultar y gestionar prospectos, clientes, tareas, propiedades y más.

---

## Claude Desktop (app de escritorio)

**No necesita Cloudflare ni ningún servidor externo.** Desktop lanza el conector directamente como proceso local.

Edita el archivo:
```
~/Library/Application Support/Claude/claude_desktop_config.json
```

Y añade dentro del JSON (ya configurado en tu equipo):

```json
{
  "mcpServers": {
    "crm": {
      "command": "/usr/local/bin/node",
      "args": ["/Users/Valentin/Documents/GitHub/CRM/mcp-connector/index.js"],
      "env": {
        "MODE": "stdio",
        "CRM_URL": "https://tinoprop.es",
        "CRM_TOKEN": "TU_TOKEN_64_CHARS"
      }
    }
  }
}
```

Luego **reinicia Claude Desktop** (Cmd+Q y vuelve a abrir). Verás el icono de herramientas (🔧) en el chat cuando esté conectado.

> **¿Dónde consigo el token?** → CRM → Ajustes → Conector Claude (MCP)

---

## Claude Code CLI / VS Code Extension

Usa el protocolo Streamable HTTP a través del túnel Cloudflare.

**1. Arranca el servidor MCP:**
```bash
cd /Users/Valentin/Documents/GitHub/CRM/mcp-connector
npm run start:sse
```

**2. Abre el túnel Cloudflare:**
```bash
cloudflared tunnel --url http://localhost:3001
```
Anota la URL que aparece, p.ej. `https://abc-def.trycloudflare.com`

**3. Edita `~/.claude/settings.json`** (ya configurado en tu equipo):
```json
{
  "mcpServers": {
    "crm": {
      "type": "http",
      "url": "https://TU_URL.trycloudflare.com/mcp",
      "headers": {
        "Authorization": "Bearer TU_TOKEN_64_CHARS"
      }
    }
  }
}
```

No hace falta reiniciar Claude Code — detecta el cambio automáticamente.

**Prueba de conectividad:**
```bash
curl https://TU_URL.trycloudflare.com/health
```

---

## Claude.ai web / Perplexity (SSE + OAuth)

Necesitas el servidor MCP corriendo con Cloudflare (pasos 1 y 2 de la sección anterior).

**Si la IA pide la URL del servidor MCP** (registro automático):
```
https://TU_URL.trycloudflare.com
```
Se abrirá una ventana "Autorizar IA" donde pegas tu token de 64 caracteres.

**Si la IA pide endpoint SSE directo:**
```
https://TU_URL.trycloudflare.com/sse?token=TU_TOKEN
```

---

## Importante

| | Claude Desktop | Claude Code / VSCode | Claude.ai web |
|---|---|---|---|
| Necesita Cloudflare | ❌ No | ✅ Sí | ✅ Sí |
| Necesita servidor corriendo | ❌ No | ✅ Sí | ✅ Sí |
| Protocolo | stdio (local) | Streamable HTTP `/mcp` | SSE `/sse` + OAuth |
| Configuración | `claude_desktop_config.json` | `~/.claude/settings.json` | Manual en la UI de la IA |

- La URL de Cloudflare es **temporal** y cambia si reinicias `cloudflared`. Cuando cambie, actualiza `~/.claude/settings.json`.
- El servidor MCP usa el **puerto 3001** (el CRM PHP usa el 3000, no se pisan).
- Para una URL fija (`mcp.tinoprop.es`) hay que mover el DNS de `tinoprop.es` a Cloudflare. Ahora está en Hostinger.

---

## Arranque automático del servidor al iniciar sesión (opcional)

Crea `~/Library/LaunchAgents/com.crm.mcp.plist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>Label</key>             <string>com.crm.mcp</string>
  <key>ProgramArguments</key>
  <array>
    <string>/usr/local/bin/node</string>
    <string>/Users/Valentin/Documents/GitHub/CRM/mcp-connector/index.js</string>
  </array>
  <key>EnvironmentVariables</key>
  <dict>
    <key>MODE</key> <string>sse</string>
  </dict>
  <key>RunAtLoad</key>         <true/>
  <key>KeepAlive</key>         <true/>
  <key>StandardOutPath</key>   <string>/tmp/crm-mcp.log</string>
  <key>StandardErrorPath</key> <string>/tmp/crm-mcp.log</string>
</dict>
</plist>
```

Actívalo:
```bash
launchctl load ~/Library/LaunchAgents/com.crm.mcp.plist
```
