En el detalle de cada prospecto y de cada cliente quiero que cada campo tenga un laiz al lado para que sea editable y sea mas facil que ir a editar arriba a la derecha. En el caso del cuadrado de seguimientro que los dias sean editables pero que salga un mini calendario, en el cual quiero que me diga las tareas que tengo en los dias ya que dependiendo de eso es cuando se agenda la proxima llamada. Te adjunto una captura de ejemplo. 
En historial de contactos quiero que se ponga automaticamente que dia escribi ese contacto en el historial, y que sea en forma de lista que se agregue una linea y al terminar la proxima sea otra no la misma, que sea en forma de lista.
En detalle de prospecto no hay un boton para volver para atras.
Te voy a adjuntar una captura de los datos que quiero que tenga el dashboard de agente, es una captura de un dashboard de un CRM pero en excel.
En la seccion calendario no se agrega nada solo, como las visitas, contactos, etc.
En configuracion en campos personalizados pon para que se puedan agregar campos personalizados tambien a prospectos.

**Prompt**

1. DETALLE DE PROSPECTO / CLIENTE (EDICIÓN RÁPIDA)
Cada campo (nombre, email, teléfono, estado, etc.) debe tener un icono de lápiz al lado.
Al hacer click en el lápiz:
	El campo se convierte en editable inline (sin recargar la página).
	Guardado automático con AJAX al salir del campo (on blur) o al presionar Enter.
	Evitar tener que usar el botón general de "editar".
2. SEGUIMIENTO (MINI CALENDARIO)
En el bloque de seguimiento:
	Los días deben ser editables mediante un mini calendario (datepicker).
	Al seleccionar un día:
	Mostrar tareas existentes de ese día (si hay).
	Permitir elegir ese día como próxima acción (ej: llamada).
	Debe integrarse con las tareas existentes del sistema.
3. HISTORIAL DE CONTACTOS
Al crear una nueva entrada:
	Guardar automáticamente la fecha y hora actual.
	Cambiar el formato:
	Mostrar como lista (tipo timeline o lista vertical).
	Cada nuevo contacto debe ser una nueva línea independiente.
	No concatenar en el mismo bloque.
	Orden descendente (más reciente primero).
4. NAVEGACIÓN
En la vista de detalle de prospecto:
	Añadir botón claro de “volver atrás”.
	Puede usar historial del navegador o redirigir al listado.
5. DASHBOARD DE AGENTE
Crear un dashboard basado en métricas (te adjuntaré referencia tipo Excel).
Debe incluir:
	Prospectos activos
	Propiedades en cartera
	Clientes activos
	Ganancia total en cartera
	comisiones cobradas
	Pendiente de cobro
	% Del potencial cobrado
	Lista de contactar hoy filtrado de prospectos
	Lista de contactar por urgencia por dia atrasado.
	Diseño claro, visual y orientado a productividad.
6. CALENDARIO
Actualmente no se agregan eventos automáticamente.
Solucionar:
	Al crear:
	Visitas
	Contactos
	Tareas
	→ deben reflejarse automáticamente en el calendario.
	Sincronización en tiempo real o al recargar.
7. CAMPOS PERSONALIZADOS
En configuración:
Permitir crear campos personalizados también para:
	Prospectos (actualmente no disponible).
	Tipos de campo:
	Texto
	Número
	Fecha
	Select
	Deben mostrarse en el detalle del prospecto y ser editables.
