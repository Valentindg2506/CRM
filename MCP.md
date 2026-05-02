## MCP Connector en este portátil

Estado actual:

- El CRM corre localmente en `http://localhost:3000`.
- El túnel público está activo en este portátil mientras siga abierto el proceso de `cloudflared`.
- URL pública actual: `https://humanities-stream-reynolds-practitioner.trycloudflare.com`

### URL que debes poner en la IA

La URL del conector MCP es siempre la ruta `/sse` con el token del CRM:

`https://humanities-stream-reynolds-practitioner.trycloudflare.com/sse?token=TU_TOKEN`

`TU_TOKEN` no es tu contraseña. Es el token MCP que generas dentro del CRM en `Ajustes → Conector Claude (MCP)`.

### Cómo sacar el token en el CRM

1. Entra al CRM.
2. Ve a `Ajustes → Conector Claude (MCP)`.
3. Pulsa `Generar token` o copia el token si ya existe.
4. Ese token suele tener 64 caracteres hexadecimales.

### Cómo configurarlo en Claude.ai

1. Abre `claude.ai`.
2. Entra a tu perfil.
3. Ve a `Settings → Integrations`.
4. Añade un conector MCP nuevo.
5. Pega esta URL:

`https://humanities-stream-reynolds-practitioner.trycloudflare.com/sse?token=TU_TOKEN`

Si Claude te pide el tipo de conexión, elige `SSE` o `Remote URL`.

### Cómo configurarlo en Claude Desktop

En `~/Library/Application Support/Claude/claude_desktop_config.json`, añade algo así:

```json
{
  "mcpServers": {
    "crm": {
      "url": "https://humanities-stream-reynolds-practitioner.trycloudflare.com/sse?token=TU_TOKEN"
    }
  }
}
```

### Cómo usarlo en cualquier otra IA o cliente MCP

Usa la misma URL SSE. Si el cliente soporta conectar a una URL MCP remota, pega exactamente:

`https://humanities-stream-reynolds-practitioner.trycloudflare.com/sse?token=TU_TOKEN`

Compatibilidad real:

- Claude.ai y clientes MCP que acepten una URL SSE remota con token suelen funcionar con esta configuración.
- Perplexity y otros clientes corporativos suelen pedir `automatic registration` o un flujo OAuth para conectarse. Este conector ya implementa el registro automático OAuth, por lo que podrás configurarlo.
  - Para Perplexity u otros que pidan la URL del servidor MCP en vez de la de SSE, pon: `https://humanities-stream-reynolds-practitioner.trycloudflare.com` y ellos usarán el registro automático. Cuando lo hagan, se abrirá una ventana tipo "Autorizar IA" donde deberás pegar tu token del CRM de 64 caracteres.

### Pruebas rápidas

Health check:

```bash
curl https://humanities-stream-reynolds-practitioner.trycloudflare.com/health
```

Prueba de SSE con token inválido:

```bash
curl -i 'https://humanities-stream-reynolds-practitioner.trycloudflare.com/sse?token=abc'
```

### Importante

- Esta URL es temporal y depende de que el proceso de `cloudflared` siga corriendo en este portátil.
- Si reinicias el portátil o cierras `cloudflared`, la URL puede cambiar.
- Si quieres una URL fija tipo `mcp.tinoprop.es`, hay que mover el DNS de `tinoprop.es` a Cloudflare. Ahora mismo ese dominio sigue en Hostinger, por eso no pude publicar un subdominio estable ahí.

Si quieres, el siguiente paso es dejar este túnel arrancando solo al iniciar sesión en macOS para que no tengas que tocar nada.