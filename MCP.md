El esquema final

MacBook Air i7 (siempre encendida)
┌──────────────────────────────────────────┐
│  node index.js  (pm2, corre siempre)     │
│  Cloudflare Tunnel → URL HTTPS pública   │
└──────────────────────────────────────────┘
          │ internet / WiFi
    ┌─────┴──────┐
    ▼            ▼
Tu Linux         Mac otra persona
claude.ai        Claude Desktop
(navegador)      → misma URL pública
→ URL pública
Pasos para montarlo en la MacBook Air
1 — Instala Node.js en el Mac

Abre una terminal en el Mac y ejecuta:


# Con Homebrew (si lo tienes)
brew install node

# O descarga el instalador desde nodejs.org
Comprueba:


node --version   # tiene que ser 18 o superior
2 — Copia la carpeta mcp-connector/ al Mac

Desde tu servidor o desde el portátil Linux:


scp -r usuario@tuservidor.com:/var/www/html/CRM/mcp-connector ~/mcp-connector
3 — Instala dependencias


cd ~/mcp-connector
npm install
4 — Crea el archivo .env

Dentro de ~/mcp-connector/.env:


CRM_URL=https://tudominio.com
MODE=sse
PORT=3000
5 — Instala pm2 para que corra siempre


npm install -g pm2
pm2 start ~/mcp-connector/index.js --name crm-mcp
pm2 save
pm2 startup   # sigue las instrucciones que imprime, arranca solo al reiniciar el Mac
Comprueba que está corriendo:


pm2 status
# o abre en el navegador del Mac: http://localhost:3000/health
6 — Instala Cloudflare Tunnel para tener URL pública


brew install cloudflared
Crea el túnel (solo la primera vez):


cloudflared tunnel login          # abre el navegador, autoriza con tu cuenta Cloudflare
cloudflared tunnel create crm-mcp # crea el túnel, apunta el ID que te da
Crea el archivo de configuración ~/.cloudflared/config.yml:


tunnel: crm-mcp
credentials-file: /Users/TU_USUARIO/.cloudflared/AQUI_EL_ID.json

ingress:
  - hostname: mcp.tudominio.com
    service: http://localhost:3000
  - service: http_status:404
Añade el subdominio en Cloudflare DNS:


cloudflared tunnel route dns crm-mcp mcp.tudominio.com
Arranca el túnel con pm2 también:


pm2 start "cloudflared tunnel run crm-mcp" --name cloudflare-tunnel
pm2 save
7 — Resultado

Tu servidor queda accesible en https://mcp.tudominio.com de forma permanente y con HTTPS.

Cómo configura cada usuario
Tú (Linux, claude.ai en el navegador):

En claude.ai → perfil → Settings → Integrations → añadir conector MCP con la URL:


https://mcp.tudominio.com/sse?token=TU_TOKEN
La otra persona (Mac, Claude Desktop):

Edita ~/Library/Application Support/Claude/claude_desktop_config.json:


{
  "mcpServers": {
    "crm": {
      "url": "https://mcp.tudominio.com/sse?token=TOKEN_DE_ESA_PERSONA"
    }
  }
}
Cada uno usa su propio token del CRM, así cada uno ve sus propios datos.

Si no tienes dominio en Cloudflare o no quieres complicarlo con el subdominio, Cloudflare también da URLs gratuitas aleatorias con cloudflared tunnel --url http://localhost:3000 — aunque cambian cada vez que reinicias. Con el dominio propio es permanente.

¿Tienes ya dominio en Cloudflare o necesitas la versión sin dominio?