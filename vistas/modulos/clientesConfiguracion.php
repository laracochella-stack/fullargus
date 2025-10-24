<?php
use App\Controllers\ControladorParametros;
use App\Support\AppNavigation;

ControladorParametros::ctrAgregarVariable();
ControladorParametros::ctrEditarVariable();
ControladorParametros::ctrEliminarVariable();

$permisoParametros = $_SESSION['permission'] ?? 'user';
if (!in_array($permisoParametros, ['senior', 'owner', 'admin'], true)) {
    echo '<div class="alert alert-danger m-3">No tiene permisos para configurar nacionalidades.</div>';
    return;
}

$nacionalidades = ControladorParametros::ctrMostrarVariables('nacionalidad');

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$contar = static function (?array $items): int {
    return is_array($items) ? count($items) : 0;
};

$parametrosFormAction = 'index.php?ruta=clientesConfiguracion';

require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Configuración de clientes',
    'subtitle' => 'Administra el catálogo de nacionalidades disponibles.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Clientes', 'url' => 'index.php?ruta=clientes'],
        ['label' => 'Configuración'],
    ],
    'app' => AppNavigation::APP_CLIENTES,
    'route' => 'clientesConfiguracion',
]);
?>
<section class="content">
  <div class="container-fluid">
    <div class="ag-parameters-layout">
      <?php include __DIR__ . '/../partials/parametros/nacionalidades.php'; ?>
    </div>
  </div>
</section>
