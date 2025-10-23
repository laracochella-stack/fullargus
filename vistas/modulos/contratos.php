<?php
use App\Controllers\ControladorContratos;
use App\Controllers\ControladorParametros;
/**
 * Módulo de contratos: lista de contratos.
 */
?>
<?php
// Página de listado de contratos. Puede filtrar por cliente vía GET.
// Obtener ID de cliente si se pasa por la URL
$clienteId = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : null;
if (!in_array($_SESSION['permission'] ?? '', ['moderator','senior','owner','admin'], true)) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger">No tiene permisos para acceder a esta sección.</div></div></section>';
    return;
}

$mensajeEdicion = ControladorContratos::ctrEditarContrato();
$mensajeCancelacion = ControladorContratos::ctrCancelarContrato();
// Obtener lista de contratos
$contratos = ControladorContratos::ctrMostrarContratos($clienteId);

// Actualizar estatus de contratos Activos/Arvhivados
ControladorContratos::ctrActualizarEstatusMasivo();

$estadoFiltro = isset($_GET['estado']) ? strtolower((string)$_GET['estado']) : 'activos';
$estadosDisponibles = ['activos', 'archivados', 'cancelados', 'todos'];
if (!in_array($estadoFiltro, $estadosDisponibles, true)) {
    $estadoFiltro = 'activos';
}

$contratosFiltrados = array_values(array_filter($contratos, static function ($contrato) use ($estadoFiltro) {
    $estatus = (int)($contrato['estatus'] ?? 1);
    return match ($estadoFiltro) {
        'activos' => $estatus === 1,
        'archivados' => $estatus === 0,
        'cancelados' => $estatus === 2,
        'todos' => true,
        default => true,
    };
}));

$descripcionFiltro = match ($estadoFiltro) {
    'archivados' => 'solo contratos archivados',
    'cancelados' => 'solo contratos cancelados',
    'todos' => 'todos los contratos registrados',
    default => 'solo contratos activos'
};

$buildEstadoUrl = static function (string $estado) use ($clienteId): string {
    $params = ['ruta' => 'contratos'];
    if ($clienteId) {
        $params['cliente_id'] = $clienteId;
    }
    if ($estado !== 'activos') {
        $params['estado'] = $estado;
    }
    return 'index.php?' . http_build_query($params);
};

$opcionesEstado = [
    'activos' => ['label' => 'Activos', 'icon' => 'fa-circle-check'],
    'archivados' => ['label' => 'Archivados', 'icon' => 'fa-box-archive'],
    'cancelados' => ['label' => 'Cancelados', 'icon' => 'fa-ban'],
    'todos' => ['label' => 'Todos', 'icon' => 'fa-layer-group'],
];

// Generar listas únicas de desarrollos y tipos para filtros
$desarrollosLista = [];
$tiposLista = [];
foreach ($contratos as $ct) {
    if (!in_array($ct['nombre_desarrollo'], $desarrollosLista)) {
        $desarrollosLista[] = $ct['nombre_desarrollo'];
    }
    if (!in_array($ct['tipo_contrato'], $tiposLista)) {
        $tiposLista[] = $ct['tipo_contrato'];
    }
}

// Obtener lista de tipos de contrato para mapear identificador a nombre
$varsTipoContrato = [];
if (class_exists(ControladorParametros::class)) {
    $varsTipoContrato = ControladorParametros::ctrMostrarVariables('tipo_contrato');
}
$mapTiposContrato = [];
foreach ($varsTipoContrato as $var) {
    $mapTiposContrato[$var['identificador']] = $var['nombre'];
}
$mensajes = array_filter([$mensajeEdicion, $mensajeCancelacion]);
$alertasSwal = [];
foreach ($mensajes as $mensaje) {
    if (!isset($mensaje['tipo'], $mensaje['mensaje'])) {
        continue;
    }
    $alertasSwal[] = [
        'icon' => $mensaje['tipo'] === 'success' ? 'success' : 'error',
        'title' => $mensaje['tipo'] === 'success' ? 'Éxito' : 'Aviso',
        'text' => (string)$mensaje['mensaje'],
    ];
}

if ($alertasSwal) {
    $configJson = json_encode($alertasSwal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo '<script>';
    echo '(function(){';
    echo 'const mensajes = ' . $configJson . ';';
    echo 'const lanzarAlertas = function () {';
    echo '    if (!Array.isArray(mensajes) || mensajes.length === 0) { return; }';
    echo '    mensajes.forEach(function (config) {';
    echo '        const opciones = Object.assign({ icon: "info", title: "Aviso" }, config || {});';
    echo '        const icono = String(opciones.icon || "").toLowerCase();';
    echo '        if (typeof Swal === "undefined") {';
    echo '            const titulo = opciones.title || "";';
    echo '            const cuerpo = opciones.text || "";';
    echo '            const mensaje = titulo ? titulo + (cuerpo ? "\\n" + cuerpo : "") : cuerpo;';
    echo '            if (mensaje) { window.alert(mensaje); }';
    echo '            return;';
    echo '        }';
    echo '        let finalConfig = Object.assign({}, opciones);';
    echo '        if (icono === "success") {';
    echo '            if (window.agSwalHelpers && typeof window.agSwalHelpers.aplicarDefaultsSuccess === "function") {';
    echo '                finalConfig = window.agSwalHelpers.aplicarDefaultsSuccess(finalConfig);';
    echo '            } else {';
    echo '                if (typeof finalConfig.timer === "undefined") { finalConfig.timer = 1800; }';
    echo '                finalConfig.showConfirmButton = false;';
    echo '                if (typeof finalConfig.timerProgressBar === "undefined") { finalConfig.timerProgressBar = false; }';
    echo '            }';
    echo '        }';
    echo '        Swal.fire(finalConfig);';
    echo '    });';
    echo '};';
    echo 'if (document.readyState === "loading") {';
    echo '    document.addEventListener("DOMContentLoaded", lanzarAlertas);';
    echo '} else {';
    echo '    setTimeout(lanzarAlertas, 0);';
    echo '}';
    echo '})();';
    echo '</script>';
}
require_once 'vistas/partials/content_header.php';
$permisoActual = $_SESSION['permission'] ?? '';
ag_render_content_header([
    'title' => 'Contratos',
    'subtitle' => 'Administre los contratos activos, archivados o cancelados.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Contratos']
    ],
]);
?>
<section class="content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Listado de contratos</h3>
      </div>
      <div class="card-body">
        <?php $puedeCrearContrato = in_array($permisoActual, ['moderator', 'senior', 'owner', 'admin'], true); ?>
        <div class="ag-table-ux-bar" data-table="#tablaContratos" data-default-label="Contratos" data-record-format="Contrato #{folio} · {cliente}" data-record-key="id" data-empty-message="Selecciona un contrato para ver acciones disponibles.">
          <div class="ag-table-ux-section ag-table-ux-primary">
            <div class="ag-table-ux-primary">
              <?php if ($puedeCrearContrato) : ?>
                <a href="index.php?ruta=crearContrato" class="btn btn-primary ag-table-ux-new">
                  <i class="fas fa-file-signature me-1"></i>
                  Nuevo
                </a>
              <?php else : ?>
                <button type="button" class="btn btn-outline-secondary ag-table-ux-new" disabled title="No tienes permisos para crear contratos">
                  <i class="fas fa-file-signature me-1"></i>
                  Nuevo
                </button>
              <?php endif; ?>
              <div class="ag-table-ux-current">Contratos</div>
              <div class="dropdown ag-table-ux-actions">
                <button class="btn btn-outline-secondary ag-table-ux-gear" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones del contrato" disabled>
                  <i class="fas fa-cog"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end ag-table-ux-actions-menu">
                  <div class="dropdown-item-text ag-record-empty-hint">Selecciona un contrato para ver acciones disponibles.</div>
                </div>
              </div>
            </div>
          </div>
          <div class="ag-table-ux-section ag-table-ux-search">
            <div class="ag-table-ux-search-control">
              <input type="search" class="form-control form-control-sm ag-table-ux-search-input" placeholder="Buscar en contratos">
              <button type="button" class="btn btn-outline-secondary btn-sm ag-table-ux-filter-toggle">
                <i class="fas fa-filter me-1"></i> Filtros
              </button>
            </div>
            <div class="ag-table-ux-filter-panel">
              <div class="mb-3">
                <p class="text-muted small fw-semibold mb-2">Estado</p>
                <div class="d-flex flex-wrap gap-2">
                  <?php foreach ($opcionesEstado as $estado => $config) :
                      $urlEstado = htmlspecialchars($buildEstadoUrl($estado), ENT_QUOTES);
                      $activo = $estadoFiltro === $estado;
                      $claseBoton = $activo ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-secondary';
                  ?>
                    <a href="<?php echo $urlEstado; ?>" class="<?php echo $claseBoton; ?> ag-filter-chip"<?php echo $activo ? ' aria-current="true"' : ''; ?>>
                      <i class="fas <?php echo htmlspecialchars($config['icon']); ?>"></i>
                      <span class="ms-1"><?php echo htmlspecialchars($config['label']); ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label class="form-label mb-1" for="filtroDesarrollo">Filtrar por desarrollo</label>
                  <select id="filtroDesarrollo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($desarrollosLista as $des) : ?>
                      <option value="<?php echo htmlspecialchars($des, ENT_QUOTES); ?>"<?php echo isset($_GET['desarrollo']) && $_GET['desarrollo'] === $des ? ' selected' : ''; ?>><?php echo htmlspecialchars($des); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-md-6 align-self-end">
                  <p class="form-text mb-0">Selecciona filtros para actualizar automáticamente el listado.</p>
                </div>
              </div>
            </div>
          </div>
          <div class="ag-table-ux-section ag-table-ux-pagination">
            <div class="ag-table-ux-length"></div>
            <div class="ag-table-ux-page-info">0-0</div>
            <div class="btn-group btn-group-sm" role="group" aria-label="Cambiar página">
              <button type="button" class="btn btn-outline-secondary" data-page="prev" aria-label="Página anterior"><i class="fas fa-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary" data-page="next" aria-label="Página siguiente"><i class="fas fa-chevron-right"></i></button>
            </div>
          </div>
        </div>
        <div class="ag-bulk-actions" id="accionesContrato" style="display:none;">
          <h5 class="ag-bulk-actions__title mb-2">Acciones para <span id="selCount">0</span> seleccionados</h5>
          <div id="contenedorBotones" class="d-flex flex-wrap gap-2"></div>
        </div>
        <p class="text-muted small mb-3">Mostrando <?php echo htmlspecialchars($descripcionFiltro); ?>.</p>
        <div class="table-responsive">
 
                  <!-- CSRF para AJAX -->
          <form id="formContratosAccion" action="index.php?ruta=contratos" method="post" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          </form>

          <?php
          $tablaContratosParams = ['estado' => $estadoFiltro];
          if ($clienteId !== null) {
              $tablaContratosParams['cliente_id'] = $clienteId;
          }
          $tablaContratosParamsJson = json_encode($tablaContratosParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          if ($tablaContratosParamsJson === false) {
              $tablaContratosParamsJson = '{}';
          }
          ?>
          <table class="table table-striped table-hover align-middle ag-data-table" id="tablaContratos" data-dt-resource="contratos" data-dt-params="<?php echo htmlspecialchars($tablaContratosParamsJson, ENT_QUOTES, 'UTF-8'); ?>" data-datatable-options='{"dom":"rtip"}'>
            <thead>
              <tr>
                <th scope="col" class="control" data-priority="1"></th>
                <th scope="col" class="min-desktop no-sort text-center">Sel.</th>
                <th scope="col" class="min-tablet-l">ID</th>
                <th scope="col" class="min-desktop">Creado el</th>
                <th scope="col" class="min-tablet">Propietario</th>
                <th scope="col" class="all">Folio</th>
                <th scope="col" class="all">Cliente</th>
                <th scope="col" class="min-tablet">Desarrollo</th>
                <th scope="col" class="min-tablet">Estado</th>
                <th scope="col" class="d-none">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
          <?php if (empty($contratosFiltrados)) : ?>
            <p class="text-muted small mb-0">No se encontraron contratos para este filtro.</p>
          <?php endif; ?>

          <!-- Contenedor de acciones por selección -->

        </div>
      </div>
    </div>
  </div>
  <?php require_once 'vistas/partials/modal_placeholders_contrato.php'; ?>
</section>
