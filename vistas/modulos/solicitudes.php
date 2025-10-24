<?php
use App\Controllers\ControladorSolicitudes;
use App\Support\AppNavigation;

$mensajeEstado = ControladorSolicitudes::ctrCambiarEstado();
$permisoSolicitudes = strtolower(trim((string)($_SESSION['permission'] ?? 'user')));
$esGestorSolicitudes = in_array($permisoSolicitudes, ['moderator','senior','owner','admin'], true);
$verCanceladas = $esGestorSolicitudes
    && isset($_GET['verCanceladas'])
    && $_GET['verCanceladas'] === '1';
$solicitudFiltroId = isset($_GET['solicitud_id']) ? (int)$_GET['solicitud_id'] : null;
$estadosTablaPermitidos = ['todos','activos','borrador','enviada','en_revision','aprobada','cancelada'];
$opcionesEstadoTabla = [
    'todos' => 'Todos los estados',
    'activos' => 'Activas (sin canceladas)',
    'borrador' => 'Solo borradores',
    'enviada' => 'Solo enviadas',
    'en_revision' => 'En revisión',
    'aprobada' => 'Solo aprobadas',
    'cancelada' => 'Solo canceladas',
];
$opcionesPropietarioTabla = [
    'todos' => 'Todos los propietarios',
    'propios' => 'Mis solicitudes',
    'otros' => 'Solicitudes de otros usuarios',
];
$estadoFiltroSolicitudes = isset($_GET['estado']) ? strtolower(trim((string)$_GET['estado'])) : 'todos';
if (!in_array($estadoFiltroSolicitudes, $estadosTablaPermitidos, true)) {
    $estadoFiltroSolicitudes = 'todos';
}
$propietarioFiltroSolicitudes = 'propios';
if ($esGestorSolicitudes) {
    $propietariosPermitidos = array_keys($opcionesPropietarioTabla);
    $propietarioFiltroSolicitudes = isset($_GET['propietario']) ? strtolower(trim((string)$_GET['propietario'])) : 'todos';
    if (!in_array($propietarioFiltroSolicitudes, $propietariosPermitidos, true)) {
        $propietarioFiltroSolicitudes = 'todos';
    }
    if ($estadoFiltroSolicitudes === 'cancelada') {
        $verCanceladas = true;
    }
} else {
    $estadoFiltroSolicitudes = $estadoFiltroSolicitudes === 'cancelada' ? 'todos' : $estadoFiltroSolicitudes;
}
$filtrosSolicitudes = ['estado' => $estadoFiltroSolicitudes];
if ($esGestorSolicitudes) {
    $filtrosSolicitudes['propietario'] = $propietarioFiltroSolicitudes;
}
$solicitudes = ControladorSolicitudes::ctrListarSolicitudes($verCanceladas, $solicitudFiltroId, true, $filtrosSolicitudes);
$filtradoPorSolicitud = $solicitudFiltroId !== null && $solicitudFiltroId > 0;
$solicitudSeleccionada = $filtradoPorSolicitud && !empty($solicitudes) ? $solicitudes[0] : null;
$solicitudesDevueltas = array_values(array_filter($solicitudes, static function ($solicitud) {
    return ($solicitud['estado'] ?? '') === 'borrador'
        && isset($solicitud['motivo_retorno'])
        && trim((string)$solicitud['motivo_retorno']) !== '';
}));
$csrfToken = $_SESSION['csrf_token'] ?? '';

$formatearFechaTabla = static function ($valor): string {
    if ($valor === null || $valor === '') {
        return '';
    }

    $tz = new \DateTimeZone('America/Mexico_City');
    $texto = (string)$valor;
    $formatos = ['d-m-Y', 'd/m/Y', 'Y-m-d'];
    foreach ($formatos as $formato) {
        $fecha = \DateTimeImmutable::createFromFormat($formato, $texto, $tz);
        if ($fecha instanceof \DateTimeImmutable) {
            return $fecha->format('d-m-Y');
        }
    }

    try {
        $fecha = new \DateTimeImmutable($texto, $tz);
        return $fecha->format('d-m-Y');
    } catch (\Exception $e) {
        return $texto;
    }
};

if ($mensajeEstado && isset($mensajeEstado['tipo'], $mensajeEstado['mensaje'])) {
    $icon = $mensajeEstado['tipo'] === 'success' ? 'success' : ($mensajeEstado['tipo'] === 'info' ? 'info' : 'error');
    $titulo = $mensajeEstado['tipo'] === 'success' ? 'Éxito' : ($mensajeEstado['tipo'] === 'info' ? 'Aviso' : 'Error');
    $redirect = 'index.php?ruta=solicitudes';
    $camposFaltantes = [];
    if (!empty($mensajeEstado['campos_faltantes']) && isset($mensajeEstado['solicitud_id'])) {
        $faltantes = is_array($mensajeEstado['campos_faltantes']) ? $mensajeEstado['campos_faltantes'] : [];
        foreach ($faltantes as $campo) {
            $normalizado = strtolower((string)$campo);
            $normalizado = preg_replace('/[^a-z0-9_]/', '', $normalizado);
            if ($normalizado !== '') {
                $camposFaltantes[] = $normalizado;
            }
        }
        $camposFaltantes = array_values(array_unique($camposFaltantes));
        $queryExtra = '';
        if (!empty($camposFaltantes)) {
            $queryExtra = '&faltantes=' . rawurlencode(implode(',', $camposFaltantes));
        }
        $redirect = sprintf('index.php?ruta=nuevaSolicitud&id=%d%s', (int)$mensajeEstado['solicitud_id'], $queryExtra);
    } elseif ($esGestorSolicitudes && $verCanceladas) {
        $redirect .= '&verCanceladas=1';
    }
    $configAlert = [
        'icon' => $icon,
        'title' => $titulo,
        'text' => (string)$mensajeEstado['mensaje'],
    ];
    $configJson = json_encode($configAlert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $redirectJson = json_encode($redirect, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($configJson !== false && $redirectJson !== false) {
        echo '<script>';
        echo '(function(){';
        echo 'var config=' . $configJson . ';';
        echo 'var redirectUrl=' . $redirectJson . ';';
        echo 'var lanzarSwal=function(){';
        echo 'if (!config || typeof window==="undefined") {';
        echo 'if (redirectUrl) { window.location=redirectUrl; }';
        echo 'return;';
        echo '}';
        echo 'if (typeof Swal==="undefined") {';
        echo 'var mensaje=config.title||"";';
        echo 'if (config.text) { mensaje+="\\n"+config.text; }';
        echo 'if (mensaje) { window.alert(mensaje); }';
        echo 'if (redirectUrl) { window.location=redirectUrl; }';
        echo 'return;';
        echo '}';
        echo 'var finalConfig=Object.assign({},config);';
        echo 'var icono=String(finalConfig.icon||"").toLowerCase();';
        echo 'if (window.agSwalHelpers&&typeof window.agSwalHelpers.aplicarDefaultsSuccess==="function") {';
        echo 'finalConfig=window.agSwalHelpers.aplicarDefaultsSuccess(finalConfig);';
        echo '} else if (icono==="success") {';
        echo 'if (typeof finalConfig.timer==="undefined") { finalConfig.timer=1800; }';
        echo 'finalConfig.showConfirmButton=false;';
        echo 'if (typeof finalConfig.timerProgressBar==="undefined") { finalConfig.timerProgressBar=false; }';
        echo '}';
        echo 'Swal.fire(finalConfig).then(function(){ if (redirectUrl) { window.location=redirectUrl; }});';
        echo '};';
        echo 'if (document.readyState==="loading") {';
        echo 'document.addEventListener("DOMContentLoaded", lanzarSwal);';
        echo '} else {';
        echo 'setTimeout(lanzarSwal,0);';
        echo '}';
        echo '})();';
        echo '</script>';
    }
}

$estadoColores = [
    'borrador' => 'secondary',
    'enviada' => 'primary',
    'en_revision' => 'warning',
    'aprobada' => 'success',
    'cancelada' => 'danger'
];
?>
<?php
require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Solicitudes',
    'subtitle' => $esGestorSolicitudes ? 'Administra las solicitudes recibidas y su estado.' : 'Consulta y da seguimiento a tus solicitudes.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Solicitudes'],
    ],
    'app' => AppNavigation::APP_SOLICITUDES,
    'route' => 'solicitudes',
]);
?>
<section class="content">
  <div class="container-fluid">
    <?php if ($filtradoPorSolicitud) : ?>
      <div class="alert alert-secondary d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="mb-0">
          <i class="fas fa-filter me-2"></i>
          <?php if ($solicitudSeleccionada) : ?>
            Mostrando únicamente la solicitud <strong>#<?php echo (int)$solicitudSeleccionada['id']; ?></strong>
            <?php if (!empty($solicitudSeleccionada['folio'])) : ?>
              <span class="ms-2 text-muted">Folio <?php echo htmlspecialchars($solicitudSeleccionada['folio']); ?></span>
            <?php endif; ?>
          <?php else : ?>
            No se encontró la solicitud solicitada o no tiene permisos para verla.
          <?php endif; ?>
        </div>
        <a href="index.php?ruta=solicitudes" class="btn btn-outline-secondary btn-sm">Ver todas las solicitudes</a>
      </div>
    <?php endif; ?>
    <?php $hayFiltrosSolicitudes = $esGestorSolicitudes; ?>
    <div class="ag-table-ux-bar" data-table="#tablaSolicitudes" data-default-label="Solicitudes" data-record-format="Solicitud #{folio} · {nombre}" data-record-key="folio" data-empty-message="Selecciona una solicitud para ver acciones disponibles.">
      <div class="ag-table-ux-section ag-table-ux-primary">
        <div class="ag-table-ux-primary">
          <a href="index.php?ruta=nuevaSolicitud" class="btn btn-primary ag-table-ux-new">
            <i class="fas fa-plus me-1"></i>
            Nuevo
          </a>
          <div class="ag-table-ux-current">Solicitudes</div>
          <div class="dropdown ag-table-ux-actions">
            <button class="btn btn-outline-secondary ag-table-ux-gear" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones de la solicitud" disabled>
              <i class="fas fa-cog"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end ag-table-ux-actions-menu">
              <div class="dropdown-item-text ag-record-empty-hint">Selecciona una solicitud para ver acciones disponibles.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="ag-table-ux-section ag-table-ux-search">
        <div class="ag-table-ux-search-control">
          <label for="buscarSolicitudes" class="form-label visually-hidden">Buscar en solicitudes</label>
          <input type="search" id="buscarSolicitudes" class="form-control form-control-sm ag-table-ux-search-input" placeholder="Buscar en solicitudes">
          <button type="button" class="btn btn-outline-secondary btn-sm ag-table-ux-filter-toggle"<?php echo $hayFiltrosSolicitudes ? '' : ' disabled'; ?>>
            <i class="fas fa-filter me-1"></i> Filtros
          </button>
        </div>
        <div class="ag-table-ux-filter-panel">
          <?php if ($esGestorSolicitudes) : ?>
            <form id="filtrosSolicitudesForm" class="row g-3" autocomplete="off">
              <div class="col-12 col-lg-4">
                <label for="filtroEstadoSolicitudes" class="form-label mb-1">Estado</label>
                <select class="form-select form-select-sm" id="filtroEstadoSolicitudes" name="estado">
                  <?php foreach ($opcionesEstadoTabla as $valor => $etiqueta) : ?>
                    <option value="<?php echo htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $estadoFiltroSolicitudes === $valor ? 'selected' : ''; ?>><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-lg-4">
                <label for="filtroPropietarioSolicitudes" class="form-label mb-1">Propietario</label>
                <select class="form-select form-select-sm" id="filtroPropietarioSolicitudes" name="propietario">
                  <?php foreach ($opcionesPropietarioTabla as $valor => $etiqueta) : ?>
                    <option value="<?php echo htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $propietarioFiltroSolicitudes === $valor ? 'selected' : ''; ?>><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-lg-4">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input" type="checkbox" id="switchCanceladas" name="verCanceladas" value="1" <?php echo $verCanceladas ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="switchCanceladas">Incluir canceladas</label>
                </div>
                <p class="form-text mb-0">Los filtros se aplican automáticamente al listado.</p>
              </div>
            </form>
          <?php else : ?>
            <p class="text-muted small mb-0">No cuentas con filtros adicionales para este listado.</p>
          <?php endif; ?>
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
    <?php
    $solicitudSeleccionadaCancelada = false;
    $motivoCancelacionSeleccionada = '';
    $fechaCancelacionSeleccionada = '';
    if ($solicitudSeleccionada && strtolower((string)($solicitudSeleccionada['estado'] ?? '')) === 'cancelada') {
        $solicitudSeleccionadaCancelada = true;
        $detalleCancelacion = $solicitudSeleccionada['cancelacion'] ?? [];
        if (!is_array($detalleCancelacion)) {
            $detalleCancelacion = [];
        }
        $motivoCancelacionSeleccionada = trim((string)($detalleCancelacion['motivo'] ?? ($solicitudSeleccionada['motivo_cancelacion'] ?? '')));
        $fechaCancelacionSeleccionada = trim((string)($detalleCancelacion['cancelada_en'] ?? ''));
    }
    ?>
    <?php if ($solicitudSeleccionadaCancelada) : ?>
      <div class="callout callout-danger">
        <h5 class="mb-2"><i class="fas fa-ban me-2"></i>Solicitud cancelada</h5>
        <?php if ($motivoCancelacionSeleccionada !== '') : ?>
          <p class="mb-2"><?php echo nl2br(htmlspecialchars($motivoCancelacionSeleccionada, ENT_QUOTES, 'UTF-8')); ?></p>
        <?php else : ?>
          <p class="mb-2">Esta solicitud fue cancelada.</p>
        <?php endif; ?>
        <?php if ($fechaCancelacionSeleccionada !== '') : ?>
          <p class="mb-0 small text-muted">Cancelada el <?php echo htmlspecialchars($fechaCancelacionSeleccionada, ENT_QUOTES, 'UTF-8'); ?>.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($solicitudesDevueltas)) : ?>
      <div class="callout <?php echo $esGestorSolicitudes ? 'callout-info' : 'callout-warning'; ?>">
        <h5 class="mb-2"><i class="fas fa-undo-alt me-2"></i><?php echo $esGestorSolicitudes ? 'Solicitudes devueltas a borrador' : 'Solicitudes devueltas a borrador'; ?></h5>
        <?php if ($esGestorSolicitudes) : ?>
          <p class="mb-2">Se enlistan las solicitudes que fueron regresadas a borrador junto con el motivo proporcionado.</p>
        <?php else : ?>
          <p class="mb-2">Se devolvieron las siguientes solicitudes a borrador. Revisa el motivo y actualiza la información correspondiente antes de reenviarlas.</p>
        <?php endif; ?>
        <ul class="mb-0 small">
          <?php foreach ($solicitudesDevueltas as $devuelta) :
              $responsable = trim((string)($devuelta['nombre_corto'] ?? $devuelta['nombre_completo'] ?? $devuelta['username'] ?? ''));
              $enlaceSolicitud = !empty($devuelta['id']) ? sprintf('index.php?ruta=nuevaSolicitud&amp;id=%d', (int)$devuelta['id']) : '';
          ?>
            <li class="mb-2">
              <div>
                <strong>Folio <?php echo htmlspecialchars($devuelta['folio'] ?? 'sin folio'); ?></strong>
                <?php if ($esGestorSolicitudes && $responsable !== '') : ?>
                  <span class="ms-2 text-muted">Responsable: <?php echo htmlspecialchars($responsable); ?></span>
                <?php endif; ?>
              </div>
              <div>
                <?php echo nl2br(htmlspecialchars($devuelta['motivo_retorno'])); ?>
                <?php if (!empty($devuelta['devuelto_en'])) : ?>
                  <span class="text-muted">(<?php echo htmlspecialchars($devuelta['devuelto_en']); ?>)</span>
                <?php endif; ?>
              </div>
              <?php if ($enlaceSolicitud !== '') : ?>
                <div><a href="<?php echo $enlaceSolicitud; ?>" class="text-decoration-none">Revisar solicitud devuelta</a></div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Listado de solicitudes</h3>
      </div>
      <div class="card-body table-responsive">
        <?php
        $tablaSolicitudesParams = [
            'verCanceladas' => $verCanceladas ? '1' : '0',
            'estado' => $estadoFiltroSolicitudes,
        ];
        if ($esGestorSolicitudes) {
            $tablaSolicitudesParams['propietario'] = $propietarioFiltroSolicitudes;
        }
        if ($filtradoPorSolicitud && $solicitudFiltroId) {
            $tablaSolicitudesParams['solicitud_id'] = $solicitudFiltroId;
        }
        $tablaSolicitudesJson = json_encode($tablaSolicitudesParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($tablaSolicitudesJson === false) {
            $tablaSolicitudesJson = '{}';
        }
        ?>
        <table class="table table-striped table-hover align-middle ag-data-table" id="tablaSolicitudes" data-es-gestor="<?php echo $esGestorSolicitudes ? '1' : '0'; ?>" data-dt-resource="solicitudes" data-dt-params="<?php echo htmlspecialchars($tablaSolicitudesJson, ENT_QUOTES, 'UTF-8'); ?>" data-datatable-options='{"dom":"rtip"}'>
          <thead>
            <tr>
              <th scope="col" class="control" data-priority="1"></th>
              <th scope="col" class="all no-sort text-center ag-table-column-select">Sel.</th>
              <th scope="col" class="all">Folio</th>
              <th scope="col" class="all">Nombre</th>
              <th scope="col" class="min-tablet">Estado</th>
              <th scope="col" class="min-tablet-l">Fecha</th>
              <th scope="col" class="min-desktop">Propietario</th>
              <th scope="col" class="min-desktop">Contrato</th>
              <th scope="col" class="d-none">Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<?php
require_once 'vistas/partials/modal_placeholders_solicitud.php';
require_once 'vistas/partials/modal_regresar_borrador.php';
require_once 'vistas/partials/modal_cliente_coincidente_solicitud.php';
?>
