# Política de Seguridad

## Reporte de Vulnerabilidades

Si descubres una vulnerabilidad de seguridad en este proyecto, por favor **no abras un issue público**. En su lugar, contacta directamente:

**Email:** valentindegennaro@gmail.com  
**Asunto:** `[SECURITY] CRM - Descripción breve`

Responderé en un plazo máximo de 72 horas.

## Información a incluir en el reporte

- Descripción detallada de la vulnerabilidad
- Pasos para reproducirla
- Impacto potencial estimado
- Versión/commit afectado (si es conocido)

## Qué NO hacer

- No publicar la vulnerabilidad en issues, redes sociales o foros antes de que sea corregida
- No explotar la vulnerabilidad en sistemas en producción
- No acceder a datos de otros usuarios

## Medidas de seguridad implementadas

- Autenticación con `password_hash()` bcrypt
- Protección CSRF en todos los formularios con tokens de sesión
- Prepared statements PDO en todas las queries (sin SQL injection)
- Sanitización de inputs contra XSS con `htmlspecialchars()`
- Protección contra fuerza bruta (bloqueo tras 5 intentos fallidos, 15 min)
- Separación de credenciales en archivo `.env` (fuera del control de versiones)
- Validación de firma HMAC en webhooks externos
- Roles de acceso (admin / agente) con verificación por módulo
- `session_regenerate_id()` en cada login para prevenir session fixation
- Verificación de IP en sesión para prevenir session hijacking
- Directorio de uploads con ejecución PHP deshabilitada
- Variables de entorno para claves secretas (no hardcoded)
- Bloqueo de instaladores en producción mediante `INSTALLER_KEY`

## Scope

Este repositorio es **código privado**. Si tienes acceso a él, estás sujeto a los términos de la licencia propietaria incluida en el archivo `LICENSE`.
