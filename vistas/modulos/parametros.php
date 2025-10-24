<?php
use App\Controllers\ControladorParametros;
use App\Support\AppNavigation;

/**
 * Panel de parámetros generales para administradores y moderadores.
 * Esta vista ahora funciona como un panel de acceso rápido a la configuración
 * específica de cada módulo.
 */

// Procesar formularios heredados para mantener compatibilidad.
ControladorParametros::ctrAgregarVariable();
ControladorParametros::ctrEditarVariable();
ControladorParametros::ctrEliminarVariable();
ControladorParametros::ctrSubirPlantilla();
ControladorParametros::ctrEditarPlantilla();
ControladorParametros::ctrEliminarPlantilla();
ControladorParametros::ctrSubirPlantillaSolicitud();
ControladorParametros::ctrEditarPlantillaSolicitud();
ControladorParametros::ctrEliminarPlantillaSolicitud();

$permisoParametros = $_SESSION['permission'] ?? 'user';
if (!in_array($permisoParametros, ['senior', 'owner', 'admin'], true)) {
    echo '<div class="alert alert-danger m-3">No tiene permisos para acceder a los parámetros.</div>';
    return;
}

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$contar = static function (?array $items): int {
    return is_array($items) ? count($items) : 0;
};

$nacionalidades = ControladorParametros::ctrMostrarVariables('nacionalidad');
$tiposContrato = ControladorParametros::ctrMostrarVariables('tipo_contrato');
$plantillasContrato = ControladorParametros::ctrMostrarPlantillas();
$plantillasSolicitud = ControladorParametros::ctrMostrarPlantillasSolicitud();

$cards = [
    [
        'title' => 'Clientes',
        'description' => 'Gestiona el catálogo de nacionalidades disponibles en formularios y listados.',
        'icon' => 'fas fa-flag',
        'link' => 'index.php?ruta=clientesConfiguracion',
        'badge' => sprintf('%d nacionalidades', $contar($nacionalidades)),
    ],
    [
        'title' => 'Contratos',
        'description' => 'Configura los tipos de contrato autorizados y administra las plantillas DOCX asociadas.',
        'icon' => 'fas fa-file-signature',
        'link' => 'index.php?ruta=contratosConfiguracion',
        'badge' => sprintf('%d tipos · %d plantillas', $contar($tiposContrato), $contar($plantillasContrato)),
    ],
    [
        'title' => 'Solicitudes',
        'description' => 'Centraliza las plantillas oficiales utilizadas para generar solicitudes.',
        'icon' => 'fas fa-inbox',
        'link' => 'index.php?ruta=solicitudesConfiguracion',
        'badge' => sprintf('%d plantillas', $contar($plantillasSolicitud)),
    ],
];

require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Parámetros generales',
    'subtitle' => 'Selecciona el módulo que deseas configurar.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Parámetros'],
    ],
    'app' => AppNavigation::APP_CONFIGURACION,
    'route' => 'parametros',
]);
?>
<section class="content">
  <div class="container-fluid">
    <div class="row g-4 align-items-stretch">
      <div class="col-12">
        <div class="alert alert-info shadow-sm">
          <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2">
            <div class="flex-shrink-0 text-primary fs-3">
              <i class="fas fa-sliders-h" aria-hidden="true"></i>
            </div>
            <div>
              <h2 class="h5 mb-1">Configuración distribuida por módulos</h2>
              <p class="mb-0">
                Ahora cada aplicación cuenta con un panel de ajustes dedicado. Utiliza los accesos directos para administrar
                catálogos y plantillas sin perder el contexto del módulo correspondiente.
              </p>
            </div>
          </div>
        </div>
      </div>
      <?php foreach ($cards as $card) : ?>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="card h-100 shadow-sm border-0">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-start gap-3 mb-3">
                <div class="fs-3 text-primary">
                  <i class="<?php echo $escape($card['icon']); ?>" aria-hidden="true"></i>
                </div>
                <div>
                  <h3 class="h5 mb-1"><?php echo $escape($card['title']); ?></h3>
                  <p class="text-muted mb-0"><?php echo $escape($card['description']); ?></p>
                </div>
              </div>
              <div class="mt-auto d-flex flex-column gap-2">
                <span class="badge bg-light text-dark align-self-start fw-semibold"><?php echo $escape($card['badge']); ?></span>
                <a class="btn btn-outline-primary" href="<?php echo $escape($card['link']); ?>">
                  <i class="fas fa-arrow-right me-2" aria-hidden="true"></i>
                  Ir a configuración
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
