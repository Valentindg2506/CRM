<?php
$pageTitle = 'Centro de Ayuda';
require_once __DIR__ . '/../../includes/header.php';

// Definir todas las secciones de ayuda
$secciones = [
    'inicio' => [
        'titulo' => 'Primeros Pasos',
        'icono' => 'bi-rocket-takeoff',
        'color' => '#10b981',
        'descripcion' => 'Aprende lo básico para comenzar a usar el CRM',
        'articulos' => [
            ['titulo' => '¿Qué es InmoCRM?', 'slug' => 'que-es'],
            ['titulo' => 'Tu primer inicio de sesión', 'slug' => 'primer-login'],
            ['titulo' => 'Navegación y menú principal', 'slug' => 'navegacion'],
            ['titulo' => 'Personalizar tu perfil', 'slug' => 'perfil'],
            ['titulo' => 'Modo oscuro y temas', 'slug' => 'temas'],
        ]
    ],
    'clientes' => [
        'titulo' => 'Gestión de Clientes',
        'icono' => 'bi-person-lines-fill',
        'color' => '#3b82f6',
        'descripcion' => 'Cómo gestionar tu base de datos de clientes',
        'articulos' => [
            ['titulo' => 'Crear un nuevo cliente', 'slug' => 'crear-cliente'],
            ['titulo' => 'Editar y eliminar clientes', 'slug' => 'editar-cliente'],
            ['titulo' => 'Buscar y filtrar clientes', 'slug' => 'buscar-clientes'],
            ['titulo' => 'Asignar clientes a agentes', 'slug' => 'asignar-clientes'],
            ['titulo' => 'Importar y exportar clientes', 'slug' => 'importar-exportar'],
        ]
    ],
    'propiedades' => [
        'titulo' => 'Propiedades',
        'icono' => 'bi-building',
        'color' => '#8b5cf6',
        'descripcion' => 'Gestión del catálogo inmobiliario',
        'articulos' => [
            ['titulo' => 'Añadir una propiedad', 'slug' => 'nueva-propiedad'],
            ['titulo' => 'Galería de imágenes', 'slug' => 'galeria'],
            ['titulo' => 'Filtros y búsqueda avanzada', 'slug' => 'filtros-propiedades'],
            ['titulo' => 'Estados de propiedad', 'slug' => 'estados-propiedad'],
            ['titulo' => 'Vincular propiedades con clientes', 'slug' => 'vincular-propiedad'],
        ]
    ],
    'pipeline' => [
        'titulo' => 'Pipeline de Ventas',
        'icono' => 'bi-kanban',
        'color' => '#f59e0b',
        'descripcion' => 'Embudo de ventas y seguimiento de oportunidades',
        'articulos' => [
            ['titulo' => 'Entender el pipeline', 'slug' => 'entender-pipeline'],
            ['titulo' => 'Vista Kanban: arrastrar y soltar', 'slug' => 'kanban'],
            ['titulo' => 'Crear y mover oportunidades', 'slug' => 'oportunidades'],
            ['titulo' => 'Personalizar etapas', 'slug' => 'etapas'],
            ['titulo' => 'Estadísticas de conversión', 'slug' => 'estadisticas-pipeline'],
        ]
    ],
    'visitas' => [
        'titulo' => 'Agenda de Visitas',
        'icono' => 'bi-calendar-event',
        'color' => '#ec4899',
        'descripcion' => 'Programación y seguimiento de visitas',
        'articulos' => [
            ['titulo' => 'Programar una visita', 'slug' => 'programar-visita'],
            ['titulo' => 'Estados de visita', 'slug' => 'estados-visita'],
            ['titulo' => 'Calendario de visitas', 'slug' => 'calendario'],
            ['titulo' => 'Notificaciones de visitas', 'slug' => 'notificaciones-visita'],
        ]
    ],
    'tareas' => [
        'titulo' => 'Tareas',
        'icono' => 'bi-check2-square',
        'color' => '#14b8a6',
        'descripcion' => 'Gestión de tareas y productividad',
        'articulos' => [
            ['titulo' => 'Crear y asignar tareas', 'slug' => 'crear-tarea'],
            ['titulo' => 'Prioridades y estados', 'slug' => 'prioridades-tareas'],
            ['titulo' => 'Completar y seguir tareas', 'slug' => 'completar-tareas'],
        ]
    ],
    'finanzas' => [
        'titulo' => 'Facturación y Presupuestos',
        'icono' => 'bi-cash-stack',
        'color' => '#22c55e',
        'descripcion' => 'Contratos, presupuestos y facturación',
        'articulos' => [
            ['titulo' => 'Crear un presupuesto', 'slug' => 'crear-presupuesto'],
            ['titulo' => 'Convertir presupuesto a factura', 'slug' => 'presupuesto-factura'],
            ['titulo' => 'Generar facturas', 'slug' => 'crear-factura'],
            ['titulo' => 'Registrar pagos', 'slug' => 'registrar-pago'],
            ['titulo' => 'Crear contratos digitales', 'slug' => 'crear-contrato'],
            ['titulo' => 'Firma digital', 'slug' => 'firma-digital'],
        ]
    ],
    'automatizaciones' => [
        'titulo' => 'Automatizaciones',
        'icono' => 'bi-robot',
        'color' => '#6366f1',
        'descripcion' => 'Automatiza tareas repetitivas',
        'articulos' => [
            ['titulo' => '¿Qué son las automatizaciones?', 'slug' => 'intro-automatizaciones'],
            ['titulo' => 'Crear una automatización', 'slug' => 'crear-automatizacion'],
            ['titulo' => 'Triggers disponibles', 'slug' => 'triggers'],
            ['titulo' => 'Acciones disponibles', 'slug' => 'acciones'],
            ['titulo' => 'Usar plantillas rápidas', 'slug' => 'plantillas-auto'],
            ['titulo' => 'Ver historial de ejecuciones', 'slug' => 'logs-automatizacion'],
        ]
    ],
    'marketing' => [
        'titulo' => 'Marketing y Funnels',
        'icono' => 'bi-funnel',
        'color' => '#f97316',
        'descripcion' => 'Embudos, formularios y captación de leads',
        'articulos' => [
            ['titulo' => 'Crear un embudo (funnel)', 'slug' => 'crear-funnel'],
            ['titulo' => 'Formularios de captación', 'slug' => 'formularios'],
            ['titulo' => 'Campos personalizados en formularios', 'slug' => 'campos-formulario'],
            ['titulo' => 'Ver envíos recibidos', 'slug' => 'envios'],
            ['titulo' => 'Landing pages', 'slug' => 'landing-pages'],
        ]
    ],
    'comunicacion' => [
        'titulo' => 'Comunicación',
        'icono' => 'bi-chat-dots',
        'color' => '#0ea5e9',
        'descripcion' => 'Mensajería y comunicación interna',
        'articulos' => [
            ['titulo' => 'Conversaciones internas', 'slug' => 'conversaciones'],
            ['titulo' => 'Enviar y recibir mensajes', 'slug' => 'mensajes'],
            ['titulo' => 'Bandeja de entrada unificada', 'slug' => 'bandeja'],
        ]
    ],
    'contenido' => [
        'titulo' => 'Blog y Contenidos',
        'icono' => 'bi-journal-richtext',
        'color' => '#a855f7',
        'descripcion' => 'Publicación de artículos y gestión de medios',
        'articulos' => [
            ['titulo' => 'Escribir un artículo', 'slug' => 'escribir-articulo'],
            ['titulo' => 'Gestionar categorías', 'slug' => 'categorias-blog'],
            ['titulo' => 'Subir y gestionar medios', 'slug' => 'medios'],
        ]
    ],
    'ajustes' => [
        'titulo' => 'Configuración del Sistema',
        'icono' => 'bi-sliders',
        'color' => '#64748b',
        'descripcion' => 'Ajustes, usuarios, roles y personalización',
        'articulos' => [
            ['titulo' => 'Configuración general', 'slug' => 'config-general'],
            ['titulo' => 'Marca blanca (whitelabel)', 'slug' => 'whitelabel'],
            ['titulo' => 'Gestionar usuarios', 'slug' => 'gestionar-usuarios'],
            ['titulo' => 'Roles y permisos', 'slug' => 'roles-permisos'],
            ['titulo' => 'Plantillas de email', 'slug' => 'plantillas-email'],
            ['titulo' => 'Claves API', 'slug' => 'api-keys'],
            ['titulo' => 'Backups', 'slug' => 'backups'],
        ]
    ],
];

$seccionActiva = get('seccion', '');
$articuloActivo = get('articulo', '');
?>

<!-- Barra de búsqueda -->
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark, #0d9668));">
            <div class="card-body text-center py-5">
                <h2 class="text-white mb-2"><i class="bi bi-life-preserver"></i> Centro de Ayuda</h2>
                <p class="text-white opacity-75 mb-4">¿En qué podemos ayudarte hoy?</p>
                <div class="position-relative mx-auto" style="max-width: 500px;">
                    <input type="text" id="buscadorAyuda" class="form-control form-control-lg ps-5" placeholder="Buscar en los manuales..." autocomplete="off">
                    <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resultados de búsqueda (oculto por defecto) -->
<div id="resultadosBusqueda" class="row justify-content-center mb-4" style="display:none;">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-search"></i> Resultados de búsqueda</span>
                <button class="btn btn-sm btn-outline-secondary" id="cerrarBusqueda"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="list-group list-group-flush" id="listaResultados">
            </div>
        </div>
    </div>
</div>

<?php if ($seccionActiva && $articuloActivo): ?>
    <?php
    // Mostrar artículo específico
    $seccion = $secciones[$seccionActiva] ?? null;
    $articulo = null;
    if ($seccion) {
        foreach ($seccion['articulos'] as $a) {
            if ($a['slug'] === $articuloActivo) { $articulo = $a; break; }
        }
    }
    if (!$seccion || !$articulo): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Artículo no encontrado.</div>
    <?php else: ?>
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Centro de Ayuda</a></li>
                <li class="breadcrumb-item"><a href="index.php?seccion=<?= $seccionActiva ?>"><?= htmlspecialchars($seccion['titulo']) ?></a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($articulo['titulo']) ?></li>
            </ol>
        </nav>
        <?php include __DIR__ . '/contenido.php'; ?>
    <?php endif; ?>

<?php elseif ($seccionActiva): ?>
    <?php
    // Mostrar sección con sus artículos
    $seccion = $secciones[$seccionActiva] ?? null;
    if (!$seccion): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Sección no encontrada.</div>
    <?php else: ?>
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Centro de Ayuda</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($seccion['titulo']) ?></li>
            </ol>
        </nav>
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi <?= $seccion['icono'] ?>" style="color: <?= $seccion['color'] ?>"></i>
                <strong><?= htmlspecialchars($seccion['titulo']) ?></strong>
                <span class="text-muted ms-2">— <?= htmlspecialchars($seccion['descripcion']) ?></span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($seccion['articulos'] as $a): ?>
                <a href="index.php?seccion=<?= $seccionActiva ?>&articulo=<?= $a['slug'] ?>" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                    <i class="bi bi-file-earmark-text me-3 text-muted"></i>
                    <div>
                        <div class="fw-semibold"><?= htmlspecialchars($a['titulo']) ?></div>
                        <small class="text-muted">Haz clic para leer la guía completa</small>
                    </div>
                    <i class="bi bi-chevron-right ms-auto text-muted"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Vista principal: grid de secciones -->
    <div class="row g-3" id="gridSecciones">
        <?php foreach ($secciones as $key => $seccion): ?>
        <div class="col-md-6 col-xl-4 seccion-card" data-titulo="<?= strtolower($seccion['titulo']) ?>" data-desc="<?= strtolower($seccion['descripcion']) ?>">
            <a href="index.php?seccion=<?= $key ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm card-hover-lift">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; background: <?= $seccion['color'] ?>15;">
                                <i class="bi <?= $seccion['icono'] ?> fs-4" style="color: <?= $seccion['color'] ?>"></i>
                            </div>
                            <div>
                                <h6 class="mb-0"><?= htmlspecialchars($seccion['titulo']) ?></h6>
                                <small class="text-muted"><?= count($seccion['articulos']) ?> artículos</small>
                            </div>
                        </div>
                        <p class="text-muted small mb-2"><?= htmlspecialchars($seccion['descripcion']) ?></p>
                        <ul class="list-unstyled mb-0 small">
                            <?php foreach (array_slice($seccion['articulos'], 0, 3) as $a): ?>
                            <li class="py-1 text-muted"><i class="bi bi-dot"></i> <?= htmlspecialchars($a['titulo']) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($seccion['articulos']) > 3): ?>
                            <li class="py-1" style="color: var(--primary)"><i class="bi bi-three-dots"></i> y <?= count($seccion['articulos']) - 3 ?> más</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Preguntas frecuentes -->
    <div class="card mt-4 border-0 shadow-sm">
        <div class="card-header">
            <i class="bi bi-patch-question"></i> <strong>Preguntas Frecuentes</strong>
        </div>
        <div class="card-body p-0">
            <div class="accordion accordion-flush" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            ¿Cómo cambio entre modo claro y oscuro?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Haz clic en el icono de <i class="bi bi-moon-stars"></i> luna (o <i class="bi bi-sun"></i> sol) en la barra superior derecha. Tu preferencia se guardará automáticamente y se mantendrá al volver a iniciar sesión.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            ¿Cómo personalizo los colores de la aplicación?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Ve a <strong>Ajustes → Marca Blanca</strong>. Allí puedes cambiar el color primario, secundario y de acento. También puedes subir tu logo, favicon y añadir CSS personalizado. Los cambios se aplican inmediatamente a toda la aplicación.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            ¿Cómo creo una automatización?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Ve a <strong>Automatizaciones</strong> en el menú lateral. Puedes usar una de las <em>plantillas rápidas</em> o crear una nueva manualmente. Cada automatización tiene un <strong>trigger</strong> (evento que la dispara) y una o más <strong>acciones</strong> (lo que se ejecuta). Por ejemplo: "Cuando llega un nuevo formulario → Crear tarea + Enviar email".
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            ¿Cómo convierto un presupuesto en factura?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Abre el presupuesto que quieres convertir y haz clic en el botón <strong>"Convertir a factura"</strong>. Se creará automáticamente una factura con los mismos datos del presupuesto (cliente, líneas de detalle, importes). Luego puedes editar la factura si necesitas ajustar algo.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            ¿Puedo usar el CRM desde el móvil?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Sí, el CRM es completamente responsive. En móvil, el menú lateral se oculta automáticamente y puedes abrirlo con el botón <i class="bi bi-list"></i> de hamburguesa. Todas las funciones están disponibles desde cualquier dispositivo con navegador web moderno.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                            ¿Cómo hago un backup de mis datos?
                        </button>
                    </h2>
                    <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Solo los administradores pueden hacer backups. Ve a <strong>Backups</strong> en el menú lateral (sección Sistema). Haz clic en "Crear backup" para descargar un archivo SQL con toda la base de datos. Se recomienda hacer backups semanales como mínimo.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Datos de búsqueda
const secciones = <?= json_encode($secciones, JSON_UNESCAPED_UNICODE) ?>;

const buscador = document.getElementById('buscadorAyuda');
const resultadosDiv = document.getElementById('resultadosBusqueda');
const listaResultados = document.getElementById('listaResultados');
const cerrarBtn = document.getElementById('cerrarBusqueda');

if (buscador) {
    buscador.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        if (q.length < 2) {
            resultadosDiv.style.display = 'none';
            return;
        }

        let resultados = [];
        for (const [key, seccion] of Object.entries(secciones)) {
            seccion.articulos.forEach(art => {
                if (art.titulo.toLowerCase().includes(q) || seccion.titulo.toLowerCase().includes(q) || seccion.descripcion.toLowerCase().includes(q)) {
                    resultados.push({
                        titulo: art.titulo,
                        seccionTitulo: seccion.titulo,
                        icono: seccion.icono,
                        color: seccion.color,
                        url: `index.php?seccion=${key}&articulo=${art.slug}`
                    });
                }
            });
        }

        if (resultados.length === 0) {
            listaResultados.innerHTML = '<div class="list-group-item text-muted py-3"><i class="bi bi-emoji-frown"></i> No se encontraron resultados para "' + buscador.value.replace(/[<>]/g,'') + '"</div>';
        } else {
            listaResultados.innerHTML = resultados.slice(0, 10).map(r =>
                `<a href="${r.url}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                    <i class="bi ${r.icono} me-3" style="color:${r.color}"></i>
                    <div>
                        <div class="fw-semibold">${r.titulo}</div>
                        <small class="text-muted">${r.seccionTitulo}</small>
                    </div>
                    <i class="bi bi-chevron-right ms-auto text-muted"></i>
                </a>`
            ).join('');
        }
        resultadosDiv.style.display = 'block';
    });
}

if (cerrarBtn) {
    cerrarBtn.addEventListener('click', function() {
        resultadosDiv.style.display = 'none';
        buscador.value = '';
    });
}
</script>

<style>
.card-hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card-hover-lift:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
