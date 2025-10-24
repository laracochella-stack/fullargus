<?php
use App\Controllers\ControladorParametros;
use App\Support\AppNavigation;

ControladorParametros::ctrSubirPlantillaSolicitud();
ControladorParametros::ctrEditarPlantillaSolicitud();
ControladorParametros::ctrEliminarPlantillaSolicitud();

$permisoParametros = $_SESSION['permission'] ?? 'user';
if (!in_array($permisoParametros, ['senior', 'owner', 'admin'], true)) {
    echo '<div class="alert alert-danger m-3">No tiene permisos para configurar plantillas de solicitud.</div>';
    return;
}

$plantillasSolicitud = ControladorParametros::ctrMostrarPlantillasSolicitud();

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$contar = static function (?array $items): int {
    return is_array($items) ? count($items) : 0;
};

$parametrosFormAction = 'index.php?ruta=solicitudesConfiguracion';

require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Configuración de solicitudes',
    'subtitle' => 'Gestiona las plantillas DOCX empleadas en los flujos de solicitudes.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Solicitudes', 'url' => 'index.php?ruta=solicitudes'],
        ['label' => 'Configuración'],
    ],
    'app' => AppNavigation::APP_SOLICITUDES,
    'route' => 'solicitudesConfiguracion',
]);
?>
<section class="content">
  <div class="container-fluid">
    <div class="ag-parameters-layout">
      <?php include __DIR__ . '/../partials/parametros/plantillas_solicitud.php'; ?>
    </div>
  </div>
</section>
