<?php
use App\Controllers\ControladorDesarrollos;
use App\Controllers\ControladorParametros;
use App\Support\AppNavigation;
/**
 * Módulo de lista de desarrollos.
 * Muestra una tabla con los desarrollos registrados y permite editarlos mediante un modal.
 */
// Procesar peticiones AJAX para crear, editar o eliminar desarrollos
$esPeticionAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($esPeticionAjax) {
    $accion = $_GET['accion'] ?? '';
    $accion = is_string($accion) ? $accion : '';
    $resultado = null;

    switch ($accion) {
        case 'agregarDesarrollo':
            $resultado = ControladorDesarrollos::ctrAgregarDesarrollo();
            break;
        case 'editarDesarrollo':
            $resultado = ControladorDesarrollos::ctrEditarDesarrollo();
            break;
        case 'eliminarDesarrollo':
            $resultado = ControladorDesarrollos::ctrEliminarDesarrollo();
            break;
        default:
            $resultado = null;
            break;
    }

    $mensajePorAccion = [
        'agregarDesarrollo' => 'Desarrollo registrado correctamente.',
        'editarDesarrollo' => 'Desarrollo actualizado correctamente.',
        'eliminarDesarrollo' => 'El desarrollo se eliminó correctamente.',
    ];

    $respuesta = [
        'status' => 'error',
        'message' => 'Solicitud inválida.',
        'http' => 400,
    ];

    if ($resultado === 'ok') {
        $respuesta = [
            'status' => 'ok',
            'message' => $mensajePorAccion[$accion] ?? 'Operación completada correctamente.',
            'http' => 200,
        ];
    } elseif ($resultado === 'error_csrf') {
        $respuesta = [
            'status' => 'error',
            'message' => 'La sesión expiró, recargue la página e intente de nuevo.',
            'http' => 419,
        ];
    } elseif ($resultado === 'error_permiso') {
        $respuesta = [
            'status' => 'error',
            'message' => 'No cuentas con permisos para realizar esta acción.',
            'http' => 403,
        ];
    } elseif ($resultado === 'error_id') {
        $respuesta = [
            'status' => 'error',
            'message' => 'No se reconoció el identificador del desarrollo.',
            'http' => 422,
        ];
    } elseif ($resultado === 'error') {
        $respuesta = [
            'status' => 'error',
            'message' => 'No se pudo completar la operación solicitada.',
            'http' => 500,
        ];
    }

    http_response_code((int)($respuesta['http'] ?? 400));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $respuesta['status'],
        'message' => $respuesta['message'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    return;
}

// Procesar envíos estándar (no AJAX)
ControladorDesarrollos::ctrAgregarDesarrollo();
ControladorDesarrollos::ctrEditarDesarrollo();
ControladorDesarrollos::ctrEliminarDesarrollo();
// Obtener todos los desarrollos para listarlos
$tiposContratoList  = ControladorParametros::ctrMostrarVariables('tipo_contrato');
// Obtener listado de tipos de contrato para mapear identificador a nombre
$listaTiposContrato = [];
if (class_exists(ControladorParametros::class)) {
    $varsTipo = ControladorParametros::ctrMostrarVariables('tipo_contrato');
    foreach ($varsTipo as $var) {
        $listaTiposContrato[$var['identificador']] = $var['nombre'];
    }
}

$permisoDesarrollos = $_SESSION['permission'] ?? 'user';
if (!in_array($permisoDesarrollos, ['senior','owner','admin'], true)) {
    echo '<div class="alert alert-danger m-3">No tiene permisos para gestionar desarrollos.</div>';
    return;
}
?>

<?php
require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Desarrollos',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Desarrollos'],
    ],
    'app' => AppNavigation::APP_DESARROLLOS,
    'route' => 'desarrollos',
]);
?>
<section class="content">
  <div class="container-fluid">
    <div class="row mb-3">
      <div class="col-lg-3 col-6">
        <div class="small-box bg-success" role="button" data-bs-toggle="modal" data-bs-target="#modalNuevoDesarrollo" aria-label="Registrar un nuevo desarrollo">
          <div class="inner">
            <h4 class="mb-1">Crear desarrollo</h4>
            <p class="mb-0">Captura un nuevo proyecto</p>
          </div>
          <div class="icon"><i class="fas fa-city"></i></div>
          <span class="small-box-footer">Abrir formulario <i class="fas fa-arrow-circle-right"></i></span>
        </div>
      </div>
    </div>

    <?php $abrirModalDesarrollo = isset($_GET['accion']) && $_GET['accion'] === 'agregarDesarrollo'; ?>
    <?php if ($abrirModalDesarrollo) : ?>
      <script>
        document.addEventListener('DOMContentLoaded', () => {
          const modal = document.getElementById('modalNuevoDesarrollo');
          if (!modal) {
            return;
          }

          if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(modal);
            modalInstance.show();
          } else {
            modal.classList.add('show');
            modal.removeAttribute('aria-hidden');
          }
        });
      </script>
    <?php endif; ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Listado</h3></div>
      <div class="card-body table-responsive">
        <table class="table table-hover align-middle ag-data-table" id="tablaDesarrollos" data-dt-resource="desarrollos">
          <thead>
            <tr>
              <th scope="col" class="control" data-priority="1"></th>
              <th scope="col" class="min-tablet-l">ID</th>
              <th scope="col" class="all">Nombre</th>
              <th scope="col" class="min-tablet">Tipo de contrato</th>
              <th scope="col" class="all no-sort">Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<!-- Modal editar desarrollo -->
<div class="modal fade" id="modalEditarDesarrollo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="formEditarDesarrollo" method="post" action="index.php?ruta=desarrollos&accion=editarDesarrollo">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="id" id="editarIdDesarrollo">
        <div class="modal-header">
          <h5 class="modal-title">Editar desarrollo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre del desarrollo</label>
              <input type="text" class="form-control form-control-sm" name="nombre_desarrollo" id="editarNombreDesarrollo" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipo de contrato</label>
              <select class="form-select form-select-sm" name="tipo_contrato" id="editarTipoContrato" required>
                <?php foreach ($listaTiposContrato as $iden => $nom) : ?>
                  <option value="<?php echo htmlspecialchars($iden, ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($nom); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Ubicación y descripción</label>
              <textarea class="form-control form-control-sm" name="descripcion" id="editarDescripcion" rows="2" readonly></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Superficie</label>
              <input type="text" class="form-control form-control-sm" name="superficie" id="editarSuperficie" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label">Clave catastral</label>
              <input type="text" class="form-control form-control-sm" name="clave_catastral" id="editarClaveCatastral" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label">Lotes disponibles</label>
              <!-- Campo para ingresar nuevos lotes en edición. Escribe un lote y presiona Enter para agregarlo -->
              <input type="text" class="form-control form-control-sm" id="inputLoteEditar" placeholder="Ingresa un lote y presiona Enter" readonly>
              <div id="contenedorLotesEditar" class="mt-2"></div>
              <!-- Input oculto que contendrá el arreglo JSON con los lotes -->
              <input type="hidden" name="lotes_disponibles" id="lotesDisponiblesEditar">
            </div>
            <div class="col-md-6">
              <label class="form-label">Precio por lote</label>
              <!-- Prefijo de moneda para edición de desarrollos -->
              <div class="input-group input-group-sm">
                <span class="input-group-text">$</span>
                <input type="text" class="form-control form-control-sm" name="precio_lote" id="editarPrecioLote" readonly>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Precio total</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">$</span>
                <input type="text" class="form-control form-control-sm" name="precio_total" id="editarPrecioTotal" readonly>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal ver desarrollo (solo lectura) -->
<div class="modal fade" id="modalVerDesarrollo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle del desarrollo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre del desarrollo</label>
            <input type="text" class="form-control form-control-sm" id="verNombreDesarrollo" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tipo de contrato</label>
            <input type="text" class="form-control form-control-sm" id="verTipoContrato" readonly>
          </div>
          <div class="col-12">
            <label class="form-label">Ubicación y descripción</label>
            <textarea class="form-control form-control-sm" id="verDescripcion" rows="2" readonly></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Superficie</label>
            <input type="text" class="form-control form-control-sm" id="verSuperficie" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Clave catastral</label>
            <input type="text" class="form-control form-control-sm" id="verClaveCatastral" readonly>
          </div>
          <div class="col-12">
            <label class="form-label">Lotes disponibles</label>
            <div id="contenedorLotesVer" class="mt-2"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Precio por lote</label>
            <input type="text" class="form-control form-control-sm" id="verPrecioLote" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Precio total</label>
            <input type="text" class="form-control form-control-sm" id="verPrecioTotal" readonly>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal nuevo desarrollo -->
<div class="modal fade" id="modalNuevoDesarrollo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="formDesarrollo" method="post" action="index.php?ruta=desarrollos&accion=agregarDesarrollo">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <div class="modal-header">
          <h5 class="modal-title">Crear desarrollo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre del desarrollo</label>
              <input type="text" class="form-control form-control-sm" name="nombre_desarrollo" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipo de contrato</label>
              <select class="form-select form-select-sm" name="tipo_contrato" id="tipo_contrato" required>
                <option value="" disabled selected>Seleccione</option>
                <?php if (!empty($tiposContratoList)) : ?>
                  <?php foreach ($tiposContratoList as $tipo) : ?>
                    <option value="<?php echo htmlspecialchars($tipo['identificador'], ENT_QUOTES); ?>">
                      <?php echo htmlspecialchars($tipo['nombre']); ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Ubicación y descripción</label>
              <textarea class="form-control form-control-sm form-control-plaintext" name="descripcion" rows="2" readonly></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Superficie</label>
              <input type="text" class="form-control form-control-sm number" name="superficie" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Clave catastral</label>
              <input type="text" class="form-control form-control-sm form-control-plaintext" name="clave_catastral" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label">Lotes disponibles</label>
              <!-- Campo para ingresar lotes individuales. El usuario escribe un lote y presiona Enter para agregarlo -->
              <input type="text" class="form-control form-control-sm" id="inputLoteNuevo" placeholder="Ingresa un lote y presiona Enter">
              <!-- Contenedor donde se mostrarán las etiquetas de lotes ingresados -->
              <div id="contenedorLotesNuevo" class="mt-2"></div>
              <!-- Input oculto que contendrá el arreglo JSON con los lotes -->
              <input type="hidden" name="lotes_disponibles" id="lotesDisponiblesNuevo">
            </div>
            <div class="col-md-6">
              <label class="form-label">Precio por lote</label>
              <!-- Agrupamos el campo con un prefijo para mostrar el símbolo de pesos -->
              <div class="input-group input-group-sm">
                <span class="input-group-text">$</span>
                <input type="text" class="form-control form-control-sm form-control-plaintext" name="precio_lote" id="crearPrecioLote" readonly>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Precio total</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">$</span>
                <input type="text" class="form-control form-control-sm form-control-plaintext" name="precio_total" id="crearPrecioTotal" readonly>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
