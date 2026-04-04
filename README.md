# Tinoprop

CRM Inmobiliario completo para el mercado inmobiliario español. Desarrollado en PHP + MySQL, compatible con Hostinger y hosting compartido.

## Requisitos

- PHP 7.4+ (recomendado 8.0+)
- MySQL 5.7+ / MariaDB 10.3+
- Apache con mod_rewrite (incluido en Hostinger)
- Sin dependencias externas (no requiere Composer)

## Instalacion

1. **Subir archivos** al hosting (via FTP o gestor de archivos de Hostinger)
2. **Configurar base de datos**: Crear un archivo `.env` en la raiz:
   ```
   cp .env.example .env
   ```
   Edita `.env` con tus credenciales:
   ```
   DB_HOST=localhost
   DB_NAME=tu_base_datos
   DB_USER=tu_usuario
   DB_PASS=tu_contraseña
   APP_URL=https://tudominio.com/crm
    APP_ENV=production
    INSTALLER_KEY=una_clave_larga_para_instaladores
    CRON_BACKUP_KEY=otra_clave_larga_para_backups
    CHAT_SIGNING_KEY=clave_privada_para_chat
    WHATSAPP_APP_SECRET=app_secret_de_meta
   ```
3. **Ejecutar instalador** accediendo a `https://tudominio.com/crm/install.php?install_key=TU_INSTALLER_KEY`
4. **Ejecutar instaladores de módulos** (uno por uno desde el navegador):
   - `install_email.php` — Módulo de Email
   - `install_campanas.php` — Campañas Drip
   - `install_plantillas.php` — Plantillas de Email
   - `install_reputacion.php` — Gestión de Reputación
   - `install_trigger_links.php` — Trigger Links
   - `install_social.php` — Redes Sociales, Blog, Medios, Contratos
   - `install_formularios.php` — Formularios de captación
   - `install_marketing_utm.php` — UTM Tracking
5. **Acceder** con las credenciales por defecto:
   - Email: `admin@inmocrm.es`
   - Password: `admin123`
6. **Cambiar contraseña** del administrador inmediatamente
7. **Eliminar** todos los `install*.php` del servidor

## Modulos

| Modulo | Descripcion |
|--------|-------------|
| **Dashboard** | KPIs, resumen financiero, proximas visitas, tareas urgentes, actividad reciente |
| **Marketing** | Centro de comando: KPIs, campañas, trigger links, reputación, analytics |
| **Propiedades** | CRUD completo con 40+ campos, fotos, busqueda avanzada, matching |
| **Clientes** | Gestion de contactos (comprador, vendedor, inquilino, propietario, inversor) |
| **Prospectos** | Pipeline de captación, inline editing, importación CSV |
| **Campañas** | Secuencias drip de email/SMS con pasos y condiciones |
| **Email** | Bandeja de entrada, envío, plantillas con variables dinámicas |
| **Visitas** | Programacion de visitas, estado, valoracion |
| **Tareas** | Gestion de tareas con prioridad, vencimiento, asignacion a agentes |
| **Documentos** | Subida y gestion de contratos, escrituras, certificados |
| **Finanzas** | Comisiones, honorarios, gastos, IVA español (21%, 10%, 4%) |
| **Portales** | Control de publicacion en Idealista, Fotocasa, Habitaclia, Pisos.com, etc. |
| **Social** | Planificación de posts para Facebook, Instagram, LinkedIn, Twitter |
| **Formularios** | Constructor de formularios embebibles con captación de leads |
| **Funnels** | Editor visual de embudos de venta |
| **Landing Pages** | Creación de landing pages para campañas |
| **Trigger Links** | Enlaces rastreables con acciones automáticas al hacer clic |
| **Reputación** | Solicitud y seguimiento de reseñas Google |
| **Blog** | CMS para publicación de artículos con SEO |
| **A/B Testing** | Framework para tests A/B de campañas |
| **Informes** | Estadisticas de propiedades, clientes, visitas, finanzas, ranking de agentes |
| **Usuarios** | Gestion de agentes con roles (admin/agente) |

---

## 🔧 Guía de Integraciones

### 1. Cron para Campañas Drip (Ejecución Automática)

Las campañas drip necesitan un cron job que procese la cola de envíos. Crea el archivo `cron/campanas.php`:

**Lógica que debe tener:**

```php
<?php
// cron/campanas.php — Ejecutar cada 5 minutos via cron
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';

$db = getDB();

// 1. Buscar contactos de campañas activas que les toque recibir un paso
$contactos = $db->query("
    SELECT cc.*, c.nombre, c.apellidos, c.email, c.telefono,
           camp.nombre as campana_nombre, camp.tipo as campana_tipo
    FROM campana_contactos cc
    JOIN clientes c ON cc.cliente_id = c.id
    JOIN campanas camp ON cc.campana_id = camp.id
    WHERE cc.estado = 'activo'
      AND cc.proximo_envio <= NOW()
      AND camp.estado = 'activa'
    LIMIT 50
")->fetchAll();

foreach ($contactos as $cc) {
    // 2. Obtener el paso actual
    $paso = $db->prepare("
        SELECT * FROM campana_pasos 
        WHERE campana_id = ? AND orden = ?
    ");
    $paso->execute([$cc['campana_id'], $cc['paso_actual'] + 1]);
    $paso = $paso->fetch();
    
    if (!$paso) {
        // Ya completó todos los pasos
        $db->prepare("UPDATE campana_contactos SET estado='completado' WHERE id=?")
           ->execute([$cc['id']]);
        continue;
    }
    
    // 3. Ejecutar según tipo de paso
    if ($paso['tipo'] === 'email' && $cc['email']) {
        // Reemplazar variables
        $cuerpo = str_replace(
            ['{{nombre}}', '{{apellidos}}', '{{email}}'],
            [$cc['nombre'], $cc['apellidos'], $cc['email']],
            $paso['contenido']
        );
        $asunto = str_replace('{{nombre}}', $cc['nombre'], $paso['asunto']);
        
        // Enviar email (usa tu función enviarEmail de includes/email.php)
        enviarEmail($cc['email'], $asunto, $cuerpo);
        
        // Actualizar estadísticas
        $db->prepare("UPDATE campana_pasos SET enviados = enviados + 1 WHERE id=?")
           ->execute([$paso['id']]);
        $db->prepare("UPDATE campanas SET enviados = enviados + 1 WHERE id=?")
           ->execute([$cc['campana_id']]);
    }
    
    if ($paso['tipo'] === 'esperar') {
        // Solo avanza al siguiente paso después de esperar
    }
    
    // 4. Avanzar al siguiente paso
    $siguientePaso = $db->prepare("
        SELECT esperar_minutos FROM campana_pasos 
        WHERE campana_id = ? AND orden = ?
    ");
    $siguientePaso->execute([$cc['campana_id'], $cc['paso_actual'] + 2]);
    $sig = $siguientePaso->fetch();
    
    $proximoEnvio = $sig 
        ? date('Y-m-d H:i:s', strtotime('+' . ($sig['esperar_minutos'] ?: 1440) . ' minutes'))
        : null;
    
    $db->prepare("UPDATE campana_contactos SET paso_actual = paso_actual + 1, proximo_envio = ? WHERE id=?")
       ->execute([$proximoEnvio, $cc['id']]);
}

echo date('Y-m-d H:i:s') . " — Procesados: " . count($contactos) . " contactos\n";
```

**Configurar en Hostinger:**

1. Ve al panel de Hostinger → **Avanzado** → **Cron Jobs**
2. Añade: `*/5 * * * * php /home/u908766211/domains/tinoprop.es/public_html/crm/cron/campanas.php`
3. Esto ejecuta cada 5 minutos

---

### 2. UTM Tracking (Captación de Fuentes de Leads)

Para registrar de dónde vienen tus leads, captura los parámetros UTM en tus formularios públicos.

**Dónde:** En cualquier formulario público (ej: `booking.php`, formularios embebidos, landing pages)

**Cómo:** Añade estos campos hidden al formulario HTML:

```html
<input type="hidden" name="utm_source" id="utm_source">
<input type="hidden" name="utm_medium" id="utm_medium">
<input type="hidden" name="utm_campaign" id="utm_campaign">
<script>
// Auto-rellenar desde la URL
const params = new URLSearchParams(window.location.search);
['utm_source','utm_medium','utm_campaign','utm_term','utm_content'].forEach(p => {
    const el = document.getElementById(p);
    if (el && params.get(p)) el.value = params.get(p);
});
</script>
```

**En el PHP que procesa el form**, después de crear el prospecto/cliente:

```php
// Guardar UTM si hay datos
if (!empty($_POST['utm_source']) || !empty($_POST['utm_medium'])) {
    $db->prepare("INSERT INTO marketing_utm 
        (prospecto_id, utm_source, utm_medium, utm_campaign, utm_term, utm_content, landing_url, referrer, ip) 
        VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([
        $nuevoProspectoId,
        $_POST['utm_source'] ?? '',
        $_POST['utm_medium'] ?? '',
        $_POST['utm_campaign'] ?? '',
        $_POST['utm_term'] ?? '',
        $_POST['utm_content'] ?? '',
        $_SERVER['REQUEST_URI'] ?? '',
        $_SERVER['HTTP_REFERER'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
}
```

**Ejemplo de URL con UTM:**
```
https://tinoprop.es/booking.php?utm_source=instagram&utm_medium=ads&utm_campaign=pisos_madrid
```

---

### 3. WhatsApp Business API

El webhook ya está implementado en `api/whatsapp_webhook.php`. Para conectarlo:

1. **Crear app** en [Meta for Developers](https://developers.facebook.com/)
2. **Activar WhatsApp** en Products → WhatsApp → Getting Started
3. **Configurar webhook URL**: `https://tinoprop.es/crm/api/whatsapp_webhook.php`
4. **Verify Token**: El que pongas en tu `.env` como `WHATSAPP_VERIFY_TOKEN`
5. **Suscribirse** a los campos: `messages`, `message_status`

**Variables de entorno necesarias en `.env`:**
```
WHATSAPP_VERIFY_TOKEN=tu_token_secreto
WHATSAPP_API_TOKEN=tu_bearer_token_de_meta
WHATSAPP_PHONE_ID=tu_phone_number_id
```

**Para validar la firma** (seguridad — ahora mismo no se valida), añade esto al inicio de `api/whatsapp_webhook.php`:

```php
// Validar firma del webhook de Meta
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $payload, getenv('WHATSAPP_APP_SECRET'));
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('Firma inválida');
}
```

---

### 4. Publicación Automática en Redes Sociales

El módulo Social (`modules/social/index.php`) tiene un botón "Publicar" que cambia el estado pero no conecta con APIs. Para publicar de verdad:

#### Facebook / Instagram (Meta Graph API)

1. Crea una **App de Meta** en [developers.facebook.com](https://developers.facebook.com/)
2. Añade el producto **Facebook Login** + **Instagram Graph API**
3. Genera un **Page Access Token** de larga duración
4. Guarda el token en la tabla `social_cuentas` (campo `access_token`)

**Código para publicar en Facebook Page** (añadir en `modules/social/index.php`, dentro del `if ($a === 'publicar')`):

```php
if ($a === 'publicar') {
    $pid = intval(post('pid'));
    $post = $db->prepare("SELECT * FROM social_posts WHERE id=?");
    $post->execute([$pid]);
    $post = $post->fetch();
    
    $plataformas = json_decode($post['plataformas'], true) ?: [];
    
    foreach ($plataformas as $plat) {
        $cuenta = $db->prepare("SELECT * FROM social_cuentas WHERE plataforma=? AND activo=1 LIMIT 1");
        $cuenta->execute([$plat]);
        $cuenta = $cuenta->fetch();
        if (!$cuenta) continue;
        
        if ($plat === 'facebook') {
            $ch = curl_init("https://graph.facebook.com/v18.0/{$cuenta['page_id']}/feed");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'message' => $post['contenido'],
                    'link' => $post['enlace'] ?: null,
                    'access_token' => $cuenta['access_token']
                ]),
                CURLOPT_RETURNTRANSFER => true,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            
            $db->prepare("UPDATE social_posts SET respuesta_api=? WHERE id=?")
               ->execute([$resp, $pid]);
        }
        
        if ($plat === 'instagram') {
            // Instagram requiere imagen. Paso 1: crear media container
            $ch = curl_init("https://graph.facebook.com/v18.0/{$cuenta['page_id']}/media");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'image_url' => $post['imagen_url'],
                    'caption' => $post['contenido'],
                    'access_token' => $cuenta['access_token']
                ]),
                CURLOPT_RETURNTRANSFER => true,
            ]);
            $resp = json_decode(curl_exec($ch), true);
            curl_close($ch);
            
            // Paso 2: publicar
            if (!empty($resp['id'])) {
                $ch = curl_init("https://graph.facebook.com/v18.0/{$cuenta['page_id']}/media_publish");
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query([
                        'creation_id' => $resp['id'],
                        'access_token' => $cuenta['access_token']
                    ]),
                    CURLOPT_RETURNTRANSFER => true,
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }
    
    $db->prepare("UPDATE social_posts SET estado='publicado', publicado_at=NOW() WHERE id=?")
       ->execute([$pid]);
}
```

#### LinkedIn

1. Crea app en [linkedin.com/developers](https://www.linkedin.com/developers/)
2. Solicita los scopes: `w_member_social`, `w_organization_social`
3. Genera un OAuth Access Token
4. Endpoint: `POST https://api.linkedin.com/v2/ugcPosts`

---

### 5. Google Reviews (Enlace Directo)

Para obtener tu enlace de Google Reviews:

1. Ve a [Google Business Profile](https://business.google.com/)
2. Selecciona tu negocio
3. Clic en **"Pedir reseñas"** → Copia el enlace
4. Alternativamente: `https://search.google.com/local/writereview?placeid=TU_PLACE_ID`
5. Pega el enlace en **Marketing → Reputación → Configuración → Enlace Google Reviews**

Para encontrar tu `Place ID`:
- Ve a [developers.google.com/maps/documentation/places/web-service/place-id-finder](https://developers.google.com/maps/documentation/places/web-service/place-id-finder)
- Busca tu negocio y copia el Place ID

---

### 6. SMS vía Twilio

El módulo SMS (`modules/sms/`) ya tiene la integración básica. Necesitas:

1. **Crear cuenta** en [twilio.com](https://www.twilio.com/)
2. **Obtener credenciales**: Account SID, Auth Token, y un número de teléfono
3. **Configurar** en el CRM: Módulos → SMS → Configuración
4. Rellena: Account SID, Auth Token, Número remitente (formato `+34XXXXXXXXX`)

**Variables de entorno opcionales en `.env`:**
```
TWILIO_SID=tu_account_sid
TWILIO_TOKEN=tu_auth_token
TWILIO_FROM=+34612345678
```

---

## Caracteristicas especificas para España

- Referencia catastral
- Certificacion energetica (A-G, en tramite, exento)
- IVA español configurable (21%, 10%, 4%, exento)
- 52 provincias espanolas
- Portales inmobiliarios espanoles (Idealista, Fotocasa, etc.)
- DNI/NIE/CIF para clientes
- Tipos de inmueble del mercado espanol

## Seguridad

- Passwords encriptados con `password_hash()` (bcrypt)
- Proteccion CSRF en todos los formularios
- Sanitizacion de inputs (XSS)
- Prepared statements (SQL injection)
- Protección contra fuerza bruta (bloqueo tras 5 intentos)
- `.htaccess` para proteger directorios sensibles
- Bloqueo de ejecucion PHP en carpeta de uploads
- Roles de usuario (admin/agente)
- Variables de entorno via `.env` para credenciales
- Validación de firma en webhooks (WhatsApp)

## Estructura de archivos

```
CRM/
├── config/database.php       # Configuracion BD y app (lee .env)
├── .env                      # Credenciales (NO subir a Git)
├── .env.example              # Template de credenciales
├── includes/                 # Core del sistema
│   ├── auth.php             # Autenticacion
│   ├── helpers.php          # Funciones auxiliares
│   ├── email.php            # Envío de emails (SMTP / mail)
│   ├── export.php           # Exportación CSV/JSON
│   ├── validators.php       # Validadores españoles (DNI/NIE/CIF)
│   ├── header.php           # Template header + sidebar
│   └── footer.php           # Template footer
├── modules/                  # Modulos funcionales
│   ├── marketing/           # Centro de comando marketing
│   ├── campanas/            # Campañas drip email/SMS
│   ├── email/               # Bandeja email + plantillas
│   ├── social/              # Redes sociales
│   ├── propiedades/         # CRUD propiedades
│   ├── prospectos/          # Pipeline de captación
│   ├── clientes/            # CRUD clientes
│   ├── visitas/             # Gestion visitas
│   ├── tareas/              # Gestion tareas
│   ├── documentos/          # Gestion documentos
│   ├── finanzas/            # Tracking financiero
│   ├── portales/            # Publicacion portales
│   ├── formularios/         # Formularios de captación
│   ├── funnels/             # Embudos de venta
│   ├── landing/             # Landing pages
│   ├── blog/                # CMS Blog
│   ├── informes/            # Estadisticas
│   └── usuarios/            # Gestion usuarios
├── api/                      # Endpoints API
│   ├── prospectos.php       # API AJAX prospectos
│   ├── whatsapp_webhook.php # Webhook WhatsApp Business
│   └── chat.php             # API chat web
├── cron/                     # Tareas programadas
│   └── backup.php           # Backup automático BD
├── assets/
│   ├── css/style.css        # Estilos
│   ├── js/app.js            # JavaScript
│   └── uploads/             # Archivos subidos
├── t.php                     # Handler público de Trigger Links
├── booking.php               # Página pública de reservas
├── index.php                 # Dashboard
├── login.php                 # Inicio de sesion
└── .htaccess                 # Configuracion Apache
```

## Tecnologias

- PHP puro (sin frameworks)
- MySQL/MariaDB
- Bootstrap 5 (CDN)
- Bootstrap Icons
- HTML5 / CSS3
- JavaScript vanilla
