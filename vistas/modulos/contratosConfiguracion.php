<?php
use App\Controllers\ControladorParametros;
use App\Support\AppNavigation;

ControladorParametros::ctrAgregarVariable();
ControladorParametros::ctrEditarVariable();
ControladorParametros::ctrEliminarVariable();
ControladorParametros::ctrSubirPlantilla();
ControladorParametros::ctrEditarPlantilla();
ControladorParametros::ctrEliminarPlantilla();

$permisoParametros = $_SESSION['permission'] ?? 'user';
if (!in_array($permisoParametros, ['senior', 'owner', 'admin'], true)) {
    echo '<div class="alert alert-danger m-3">No tiene permisos para configurar contratos.</div>';
    return;
}

$tiposContrato = ControladorParametros::ctrMostrarVariables('tipo_contrato');
$plantillas = ControladorParametros::ctrMostrarPlantillas();

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$contar = static function (?array $items): int {
    return is_array($items) ? count($items) : 0;
};

$parametrosFormAction = 'index.php?ruta=contratosConfiguracion';

require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Configuración de contratos',
    'subtitle' => 'Mantén actualizados tipos y plantillas oficiales.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Contratos', 'url' => 'index.php?ruta=contratos'],
        ['label' => 'Configuración'],
    ],
    'app' => AppNavigation::APP_CONTRATOS,
    'route' => 'contratosConfiguracion',
]);
?>
<section class="content">
  <div class="container-fluid">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <p class="text-muted mb-4">
          Define los tipos de contrato disponibles y carga las plantillas DOCX que se utilizarán como base para los documentos
          generados desde el sistema.
        </p>
        <?php include __DIR__ . '/../partials/parametros/tipos_contrato.php'; ?>
        <hr class="my-5">
        <?php include __DIR__ . '/../partials/parametros/plantillas_contrato.php'; ?>
      </div>
    </div>
  </div>
</section>
