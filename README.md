# TinoProp CRM

> **Software propietario — Todos los derechos reservados.**  
> Consulta el archivo [LICENSE](./LICENSE) antes de usar, copiar o distribuir.

CRM inmobiliario completo desarrollado a medida para el mercado español. Cubre todo el ciclo comercial de una agencia inmobiliaria: captación de propietarios, gestión de clientes y compradores, pipeline de ventas, marketing automatizado, seguimiento financiero e integraciones con portales y herramientas externas.

---

## Índice

- [Visión general](#visión-general)
- [Stack técnico](#stack-técnico)
- [Arquitectura](#arquitectura)
- [Módulos](#módulos)
- [Base de datos](#base-de-datos)
- [Seguridad](#seguridad)
- [Integraciones](#integraciones)
- [Características específicas para España](#características-específicas-para-españa)
- [Licencia](#licencia)

---

## Visión general

TinoProp CRM es una plataforma web de gestión inmobiliaria construida en PHP puro sin frameworks ni dependencias externas (sin Composer). Está diseñado para desplegarse en hosting compartido (Hostinger) con un único archivo `.env` como única fuente de configuración.

El sistema cubre dos flujos principales de negocio:

**Captación (Prospectos):** Gestión del ciclo completo desde el primer contacto con un propietario hasta la firma del contrato de captación. Incluye pipeline Kanban, historial de contactos por tipo (llamada, email, visita, WhatsApp), próxima acción, historial de cambios de precio de la propiedad y calendario integrado.

**Compraventa (Clientes):** Gestión de compradores, inquilinos, inversores y vendedores con matching automático entre sus preferencias y las propiedades en cartera, seguimiento de visitas y pipeline de cierre.

---

## Stack técnico

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.3 (compatible 8.0+) |
| Base de datos | MySQL 8 / MariaDB 10.6+ |
| Frontend | Bootstrap 5.3, Bootstrap Icons, JavaScript vanilla |
| Calendario | Flatpickr |
| Hosting | Hostinger (hosting compartido, Apache) |
| Configuración | Variables de entorno vía `.env` |
| Autenticación | Sesiones PHP nativas con bcrypt |
| API externa | Google Calendar API v3, WhatsApp Business API (Meta), Stripe |

Sin Composer. Sin frameworks PHP. Sin npm. Sin bundlers. Todo el JavaScript es vanilla o CDN.

---

## Arquitectura

```
CRM/
├── config/
│   └── database.php          # Bootstrap: DB, constantes, handlers de error
├── includes/                 # Núcleo compartido
│   ├── auth.php              # Autenticación, sesiones, roles
│   ├── helpers.php           # CSRF, flash, sanitize, formateo, paginación
│   ├── ajustes_helper.php    # Ajustes por usuario (BD)
│   ├── validators.php        # Validadores españoles (DNI/NIE/CIF, teléfono, CP)
│   ├── email.php             # Envío SMTP/mail() con plantillas
│   ├── export.php            # Exportación CSV, PDF, JSON
│   ├── encryption.php        # Cifrado AES para datos sensibles
│   ├── google_calendar_helper.php  # OAuth2 + Google Calendar API
│   ├── automatizaciones_engine.php # Motor de triggers y automatizaciones
│   ├── custom_fields_helper.php    # Campos personalizados dinámicos
│   ├── header.php            # Template principal + sidebar + nav
│   └── footer.php            # Cierre de layout
├── modules/                  # Módulos funcionales (cada uno autocontenido)
│   ├── prospectos/
│   ├── clientes/
│   ├── propiedades/
│   ├── visitas/
│   ├── tareas/
│   ├── calendario/
│   ├── finanzas/
│   ├── marketing/
│   ├── campanas/
│   ├── email/
│   ├── social/
│   ├── formularios/
│   ├── funnels/
│   ├── landing/
│   ├── blog/
│   ├── portales/
│   ├── documentos/
│   ├── contratos/
│   ├── presupuestos/
│   ├── pagos/
│   ├── informes/
│   ├── pipelines/
│   ├── automatizaciones/
│   ├── ajustes/
│   └── usuarios/
├── api/                      # Endpoints AJAX internos y webhooks externos
│   ├── prospectos.php        # Edición inline, historial, sync calendario
│   ├── check_duplicate.php   # Validación de duplicados (teléfono/email)
│   ├── whatsapp_webhook.php  # Webhook Meta WhatsApp Business
│   └── notificaciones.php    # Polling de notificaciones
├── cron/                     # Scripts de ejecución programada
│   ├── purgar_logs.php
│   ├── secuencia_captacion.php
│   └── backup.php
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── uploads/              # Archivos subidos (gitignored)
├── legal/                    # Documentos RGPD generados
├── index.php                 # Dashboard principal
├── login.php / logout.php
├── booking.php               # Página pública de reservas de visita
├── formulario.php            # Formularios públicos embebibles
├── funnel.php                # Páginas de funnel públicas
└── .env                      # Credenciales (gitignored)
```

Cada módulo sigue el patrón `index.php` (listado) + `form.php` (crear/editar) + `ver.php` (detalle) + `delete.php` (borrado con confirmación). Los módulos se comunican con el núcleo mediante `includes/` y entre sí sólo a través de la base de datos.

---

## Módulos

### Dashboard
KPIs principales en tiempo real: prospectos activos, propiedades en cartera, clientes activos, visitas del mes. Ganancia potencial total (prospectos + cartera), comisiones cobradas vs. pendientes. Pipeline de captación por etapa, estado de cartera, contador de contactos del mes por tipo. Lista de prospectos urgentes ordenados por días sin contacto y lista de clientes PSI.

### Prospectos (Captación)
Pipeline de captación con 7 etapas: Nuevo Lead → 1er Contacto → Seguimiento → Visita Programada → Negociando → Captado → Descartado. Cada prospecto incluye:
- Ficha completa de la propiedad (tipo, operación, superficies, habitaciones, características, dirección con piso/escalera/puerta separados, ref. catastral, certificación energética)
- Historial de contactos estructurado por tipo (llamada, email, visita, WhatsApp, nota, otro) con fecha y contenido editable
- Historial de cambios de precio y modificaciones de propiedad con timeline visual
- Campo "Próxima Acción" libre para el agente
- Calendario mini integrado (Flatpickr) con todos los eventos del día del agente
- Temperatura del lead (frío/templado/caliente)
- Edición inline de todos los campos sin recarga de página
- Importación masiva CSV con mapeo de columnas
- Validación en tiempo real de teléfono y email duplicado contra prospectos y clientes

### Clientes (PSI)
Compradores, vendedores, inquilinos, propietarios e inversores. Múltiples tipos por cliente. Preferencias de búsqueda (zona, presupuesto, habitaciones, superficie, tipo de operación). Timeline de actividad. Tags, documentos RGPD, bulk actions. Matching automático con propiedades en cartera basado en presupuesto, zona y tipo.

### Propiedades (Cartera)
40+ campos por propiedad: superficies (total/construida/útil/parcela), habitaciones, baños, aseos, planta, extras (ascensor, garaje, trastero, terraza, balcón, jardín, piscina, AA, calefacción), orientación, conservación, certificación energética, ref. catastral, enlace a portal, descripción pública e interna. Galería de fotos con orden arrastrable. Control de publicación en portales externos. Estado (disponible/reservado/vendido/alquilado/retirado).

### Calendario
Vista mensual con todos los tipos de evento: eventos manuales (color personalizado, todo el día o con hora), visitas programadas, tareas con vencimiento y próximo contacto de prospectos. Admins ven todos los agentes; agentes sólo los propios. Leyenda de colores por tipo. Panel "Hoy" y "Próximos 7 días". Mini-calendario integrado en el detalle de prospecto con carga dinámica de eventos por día.

### Google Calendar (sincronización)
Cada usuario puede conectar su cuenta personal de Google mediante OAuth 2.0 desde Ajustes. El botón "Sincronizar ahora" exporta los próximos 60 días de visitas, tareas pendientes, próximos contactos de prospectos y eventos de calendario al Google Calendar del usuario. Los eventos ya sincronizados se actualizan en lugar de duplicarse (mapeo por `google_calendar_event_map`). Tokens de refresco automático. Cada usuario gestiona su propia conexión de forma independiente.

### Visitas
Programación con fecha, hora, agente, cliente, propiedad y notas. Estado de la visita (pendiente/realizada/cancelada). Valoración post-visita. Vista en lista y en calendario.

### Tareas
Gestión de tareas con prioridad (normal/alta/urgente), estado (pendiente/en progreso/completada), vencimiento, asignación a agente, vinculación opcional a cliente o propiedad. Indicadores de vencimiento en dashboard.

### Finanzas
Registro de comisiones (venta/alquiler/honorarios), gastos e ingresos. IVA español configurable (21%, 10%, 4%, exento). Estado de cobro (pendiente/cobrado). Filtro por agente. Totales y pendiente de cobro.

### Marketing
Centro de comando con KPIs de marketing: leads captados, tasa de conversión, fuentes UTM, ranking de agentes. Sub-módulos:

- **Campañas drip**: Secuencias automatizadas de email/SMS con pasos configurables, condiciones de avance y esperas
- **Email**: Bandeja de entrada, composición, plantillas con variables dinámicas (`{{nombre}}`, `{{email}}`, etc.)
- **Social**: Planificación y publicación en Facebook, Instagram, LinkedIn, Twitter con calendario editorial
- **Reputación**: Solicitud automatizada de reseñas Google con seguimiento de respuestas
- **A/B Testing**: Framework para comparar variantes de campañas
- **UTM Tracking**: Rastreo de fuente de leads por parámetros UTM en formularios públicos
- **Landing Pages**: Editor de páginas de aterrizaje para campañas
- **Funnels**: Editor visual de embudos de captación/venta

### Formularios
Constructor de formularios de captación embebibles en páginas externas. Cada formulario genera un snippet de código. Los envíos crean prospectos automáticamente y disparan automatizaciones configuradas.

### Automatizaciones
Motor de triggers y acciones. Triggers: nuevo prospecto, nueva visita, nuevo cliente, cambio de etapa, etc. Acciones: enviar email, crear tarea, actualizar campo, enviar WhatsApp. Log de ejecución por automatización.

### Portales
Control de publicación de propiedades en Idealista, Fotocasa, Habitaclia, Pisos.com, Infocasa, Milanuncios y Fotocasa. Estado y fecha de última publicación por portal.

### Documentos y Contratos
Subida y gestión de documentos por propiedad/cliente/prospecto. Plantillas de contratos con variables dinámicas (nombre, dirección, precio...) generadas como PDF. Historial de versiones.

### Presupuestos
Generación de presupuestos de honorarios. Estado (borrador/enviado/aceptado/rechazado). Exportación a PDF.

### Pagos
Integración con Stripe para cobro de honorarios online. Webhook de confirmación. Historial de transacciones.

### Pipelines personalizados
Kanban configurables para cualquier flujo de trabajo (además del pipeline de captación estándar). Columnas, colores y acciones definibles por el administrador.

### Informes
Estadísticas de propiedades, clientes, visitas y finanzas. Ranking de agentes por captaciones y visitas. Exportación CSV.

### Usuarios y Roles
Dos roles base: `admin` (acceso total) y `agente` (acceso filtrado a sus propios registros). Sistema de permisos granular por módulo configurable desde la interfaz. Perfil de usuario con foto, cambio de contraseña. Backup manual de BD desde panel de admin.

### Ajustes (por usuario)
Tema (claro/oscuro), color primario, sidebar compacta, widgets del dashboard, ítems por página, notificaciones por email, Google Calendar (conexión OAuth individual).

### RGPD / Legal
Generación de documentos de consentimiento RGPD. Registro de consentimiento por cliente. Configuración de datos del responsable del tratamiento.

---

## Base de datos

El esquema tiene más de 60 tablas. Las principales:

| Tabla | Descripción |
|-------|-------------|
| `usuarios` | Agentes y admins |
| `usuario_ajustes` | Preferencias por usuario (clave-valor) |
| `prospectos` | Pipeline de captación con 60+ campos |
| `historial_prospectos` | Historial de contactos (llamada/email/visita/whatsapp/nota) |
| `historial_propiedad_prospecto` | Cambios de precio y modificaciones de propiedad |
| `clientes` | Compradores, vendedores, inquilinos, inversores |
| `propiedades` | Cartera de propiedades |
| `visitas` | Visitas programadas |
| `tareas` | Tareas con prioridad y asignación |
| `calendario_eventos` | Eventos manuales de calendario |
| `google_calendar_tokens` | Tokens OAuth por usuario |
| `google_calendar_event_map` | Mapeo CRM ↔ Google Calendar |
| `campanas` | Campañas drip |
| `campana_pasos` | Pasos de cada campaña |
| `campana_contactos` | Estado de cada contacto en cada campaña |
| `finanzas` | Comisiones, gastos, ingresos |
| `automatizaciones` | Reglas de automatización |
| `automatizaciones_log` | Log de ejecución |
| `formularios` | Formularios de captación |
| `formulario_envios` | Envíos recibidos |
| `custom_fields` | Campos personalizados por entidad |
| `whitelabel_config` | Branding personalizado |
| `notificaciones` | Notificaciones en-app |

---

## Seguridad

- **Autenticación**: `password_hash()` con bcrypt, `session_regenerate_id()` en login
- **CSRF**: Token de sesión verificado en todos los formularios POST y endpoints AJAX
- **SQL Injection**: 100% prepared statements PDO, nunca concatenación en queries
- **XSS**: `htmlspecialchars()` en toda salida de datos de usuario
- **Fuerza bruta**: Bloqueo de IP tras 5 intentos fallidos (15 min de lockout)
- **Session hijacking**: Verificación de IP en cada request autenticado
- **Credenciales**: Todas las claves en `.env`, nunca en código fuente
- **Instaladores**: Bloqueados en producción sin `INSTALLER_KEY` en `.env`
- **Uploads**: Directorio con PHP deshabilitado vía `.htaccess`, validación de tipo MIME
- **Webhooks**: Verificación de firma HMAC (WhatsApp/Meta)
- **Roles**: Verificación de permisos por módulo y por registro (agente sólo ve los suyos)
- **OAuth**: State parameter anti-CSRF en flujo Google OAuth

---

## Integraciones

| Servicio | Propósito | Configuración |
|----------|-----------|---------------|
| **Google Calendar** | Sincronización de eventos por usuario | OAuth 2.0, credenciales en `.env` |
| **WhatsApp Business (Meta)** | Recepción de mensajes, chat | Webhook + API Token en `.env` |
| **Stripe** | Cobro de honorarios online | API Key + Webhook Secret en `.env` |
| **Twilio** | Envío de SMS | SID + Auth Token en `.env` |
| **Google Reviews** | Solicitud de reseñas | Place ID configurable desde UI |
| **Portales inmobiliarios** | Control de publicación | Gestión manual desde módulo Portales |
| **Facebook / Instagram** | Publicación social | Page Access Token en tabla `social_cuentas` |

---

## Características específicas para España

- 52 provincias españolas en selector
- Validación de DNI, NIE y CIF con algoritmo oficial
- Referencia catastral (campo dedicado en propiedades y prospectos)
- Certificación energética (A–G, en trámite, exento)
- IVA español (21%, 10%, 4%, exento) en módulo de finanzas
- Portales inmobiliarios del mercado español (Idealista, Fotocasa, Habitaclia, Pisos.com, etc.)
- Tipos de inmueble del mercado español (Piso, Chalet, Adosado, Ático, Local, Nave, etc.)
- Campos de dirección adaptados (escalera, piso, puerta separados)
- RGPD / LOPD: registro de consentimiento, textos legales configurables, email DPD
- Zona horaria `Europe/Madrid` en toda la aplicación
- Formato de fechas español (dd/mm/yyyy)
- Formato de precios en euros con separadores españoles

---

## Licencia

Este software es **propietario y confidencial**.

Copyright (c) 2024-2026 Valentín De Gennaro. Todos los derechos reservados.

**Queda expresamente prohibido** copiar, distribuir, modificar, sublicenciar o hacer ingeniería inversa sobre este software sin autorización escrita del autor. Consulta el archivo [LICENSE](./LICENSE) para los términos completos.

Para consultas de licenciamiento: valentindegennaro@gmail.com
