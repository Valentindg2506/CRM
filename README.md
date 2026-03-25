# InmoCRM España

CRM Inmobiliario completo para el mercado inmobiliario español. Desarrollado en PHP + MySQL, compatible con Hostinger y hosting compartido.

## Requisitos

- PHP 7.4+ (recomendado 8.0+)
- MySQL 5.7+ / MariaDB 10.3+
- Apache con mod_rewrite (incluido en Hostinger)
- Sin dependencias externas (no requiere Composer)

## Instalacion

1. **Subir archivos** al hosting (via FTP o gestor de archivos de Hostinger)
2. **Configurar base de datos** en `config/database.php`:
   - `DB_HOST` - Host MySQL (normalmente `localhost`)
   - `DB_NAME` - Nombre de la base de datos
   - `DB_USER` - Usuario MySQL
   - `DB_PASS` - Contraseña MySQL
   - `APP_URL` - URL de tu dominio (ej: `https://tudominio.com/crm`)
3. **Ejecutar instalador** accediendo a `https://tudominio.com/crm/install.php`
4. **Acceder** con las credenciales por defecto:
   - Email: `admin@inmocrm.es`
   - Password: `admin123`
5. **Cambiar contraseña** del administrador inmediatamente
6. **Eliminar** `install.php` del servidor

## Modulos

| Modulo | Descripcion |
|--------|-------------|
| **Dashboard** | KPIs, resumen financiero, proximas visitas, tareas urgentes, actividad reciente |
| **Propiedades** | CRUD completo con 40+ campos, fotos, busqueda avanzada, matching |
| **Clientes** | Gestion de contactos (comprador, vendedor, inquilino, propietario, inversor) |
| **Visitas** | Programacion de visitas, estado, valoracion |
| **Tareas** | Gestion de tareas con prioridad, vencimiento, asignacion a agentes |
| **Documentos** | Subida y gestion de contratos, escrituras, certificados |
| **Finanzas** | Comisiones, honorarios, gastos, IVA español (21%, 10%, 4%) |
| **Portales** | Control de publicacion en Idealista, Fotocasa, Habitaclia, Pisos.com, etc. |
| **Informes** | Estadisticas de propiedades, clientes, visitas, finanzas, ranking de agentes |
| **Usuarios** | Gestion de agentes con roles (admin/agente) |

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
- `.htaccess` para proteger directorios sensibles
- Bloqueo de ejecucion PHP en carpeta de uploads
- Roles de usuario (admin/agente)

## Estructura de archivos

```
CRM/
├── config/database.php       # Configuracion BD y app
├── includes/                 # Core del sistema
│   ├── auth.php             # Autenticacion
│   ├── helpers.php          # Funciones auxiliares
│   ├── header.php           # Template header + sidebar
│   └── footer.php           # Template footer
├── modules/                  # Modulos funcionales
│   ├── propiedades/         # CRUD propiedades
│   ├── clientes/            # CRUD clientes
│   ├── visitas/             # Gestion visitas
│   ├── tareas/              # Gestion tareas
│   ├── documentos/          # Gestion documentos
│   ├── finanzas/            # Tracking financiero
│   ├── portales/            # Publicacion portales
│   ├── informes/            # Estadisticas
│   └── usuarios/            # Gestion usuarios
├── assets/
│   ├── css/style.css        # Estilos
│   ├── js/app.js            # JavaScript
│   └── uploads/             # Archivos subidos
├── index.php                 # Dashboard
├── login.php                 # Inicio de sesion
├── logout.php                # Cerrar sesion
├── install.php               # Instalador (eliminar despues)
└── .htaccess                 # Configuracion Apache
```

## Tecnologias

- PHP puro (sin frameworks)
- MySQL/MariaDB
- Bootstrap 5 (CDN)
- Bootstrap Icons
- HTML5 / CSS3
- JavaScript vanilla
