## MCP Connector en este portátil

Estado actual:

- El CRM corre localmente en `http://localhost:3000`.
- El túnel público está activo en este portátil mientras siga abierto el proceso de `cloudflared`.
- URL pública actual: `https://humanities-stream-reynolds-practitioner.trycloudflare.com`

### URL que debes poner en la IA

La URL del conector MCP es siempre la ruta base:

`https://interact-prot-morgan-claimed.trycloudflare.com`

- Para Perplexity u otros que pidan la URL del servidor MCP en vez de la de SSE, pon: `https://interact-prot-morgan-claimed.trycloudflare.com` y ellos usarán el registro automático. Cuando lo hagan, se abrirá una ventana tipo "Autorizar IA" donde deberás pegar tu token del CRM de 64 caracteres.
- Por el contrario, si tu IA solicita un endpoint SSE directo, ingresa `https://interact-prot-morgan-claimed.trycloudflare.com/sse?token=TU_TOKEN`

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