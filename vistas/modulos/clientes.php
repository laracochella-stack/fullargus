<?php
use App\Controllers\ControladorClientes;
use App\Controllers\ControladorContratos;
use App\Controllers\ControladorDesarrollos;
use App\Controllers\ControladorParametros;
/**
 * Módulo de lista de clientes.
 */
// Procesar solicitudes de cambio de estado
ControladorClientes::ctrActualizarEstado();
// Procesar edición si llega formulario
ControladorClientes::ctrEditarCliente();
// Procesar creación de contrato si llega formulario
ControladorContratos::ctrCrearContrato();
// Obtener lista de desarrollos para el formulario de contrato
$desarrollosDisponibles = ControladorDesarrollos::ctrMostrarDesarrollos();

// Obtener lista de nacionalidades
$listaNacionalidades = [];
if (class_exists(ControladorParametros::class)) {
    $listaNacionalidades = ControladorParametros::ctrMostrarVariables('nacionalidad');
}
// Construir mapa identificador -> nombre para nacionalidades
$mapNacionalidades = [];
foreach ($listaNacionalidades as $nac) {
    $mapNacionalidades[$nac['identificador']] = $nac['nombre'];
}

// Formateador de fechas al estilo DD-MM-YYYY
$formatearFecha = static function (?string $fecha): string {
    if (empty($fecha)) {
        return '';
    }
    $timestamp = strtotime((string)$fecha);
    if ($timestamp === false) {
        return (string)$fecha;
    }
    return date('d-m-Y', $timestamp);
};

// Obtener lista de tipos de contrato para mapear identificador a nombre (usado en el select de desarrollos)
$listaTiposContrato = [];
if (class_exists(ControladorParametros::class)) {
    $varsTipo = ControladorParametros::ctrMostrarVariables('tipo_contrato');
    foreach ($varsTipo as $var) {
        $listaTiposContrato[$var['identificador']] = $var['nombre'];
    }
}

$hmacSecret = getenv('HMAC_SECRET');

$permisoClientes = $_SESSION['permission'] ?? 'user';
if (!in_array($permisoClientes, ['moderator','senior','owner','admin'], true)) {
    echo '<div class="alert alert-danger m-3">No tiene permisos para acceder al módulo de clientes.</div>';
    return;
}


require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Clientes',
    'subtitle' => 'Consulta, edita y asigna contratos a tus clientes.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Clientes'],
    ],
]);
?>

<!-- Modal ver cliente -->
<div class="modal fade" id="modalVerCliente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle del cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre</label>
            <input type="text" class="form-control form-control-sm" id="verNombreCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nacionalidad</label>
            <input type="text" class="form-control form-control-sm" id="verNacionalidadCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Fecha de nacimiento</label>
            <input type="text" class="form-control form-control-sm" id="verFechaCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">RFC</label>
            <input type="text" class="form-control form-control-sm" id="verRfcCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">CURP</label>
            <input type="text" class="form-control form-control-sm" id="verCurpCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">INE (IDMEX)</label>
            <input type="text" class="form-control form-control-sm" id="verIneCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Estado civil y régimen matrimonial</label>
            <input type="text" class="form-control form-control-sm" id="verEstadoCivilCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Ocupación</label>
            <input type="text" class="form-control form-control-sm" id="verOcupacionCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Teléfono</label>
            <input type="text" class="form-control form-control-sm" id="verTelefonoCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Domicilio</label>
            <input type="text" class="form-control form-control-sm" id="verDomicilioCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Correo electrónico</label>
            <input type="text" class="form-control form-control-sm" id="verEmailCliente" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Beneficiario</label>
            <input type="text" class="form-control form-control-sm" id="verBeneficiarioCliente" readonly>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal editar cliente -->
<div class="modal fade" id="modalEditarCliente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="formEditarCliente" method="post" action="index.php?ruta=clientes&accion=editarCliente">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="id_cliente" id="editarIdCliente">
        <div class="modal-header">
          <h5 class="modal-title">Editar cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre</label>
              <input type="text" class="form-control form-control-sm" name="nombre" id="editarNombreCliente" maxlength="50" pattern="[A-Za-zÑÁÉÍÓÚ\s]{1,50}" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nacionalidad</label>
              <select class="form-select form-select-sm" name="nacionalidad" id="editarNacionalidadCliente" required>
                <?php if (!empty($listaNacionalidades)) : ?>
                  <?php foreach ($listaNacionalidades as $nac) : ?>
                    <option value="<?php echo htmlspecialchars($nac['identificador'], ENT_QUOTES); ?>">
                      <?php echo htmlspecialchars($nac['nombre']); ?>
                    </option>
                  <?php endforeach; ?>
                <?php else : ?>
                  <option value="" disabled>No hay nacionalidades registradas</option>
                <?php endif; ?>
              </select>
              <?php if (empty($listaNacionalidades)) : ?>
                <div class="form-text text-warning">Agrega nacionalidades en el módulo de Parámetros para seleccionarlas aquí.</div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label">Fecha de nacimiento</label>
              <input type="date" class="form-control form-control-sm" name="fecha_nacimiento" id="editarFechaCliente" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">RFC</label>
              <input type="text" class="form-control form-control-sm" name="rfc" id="editarRfcCliente" pattern="^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$" maxlength="13" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">CURP</label>
              <input type="text" class="form-control form-control-sm" name="curp" id="editarCurpCliente" maxlength="18" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">INE (IDMEX)</label>
              <input type="text" class="form-control form-control-sm" name="ine" id="editarIneCliente" maxlength="50" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">Estado civil y régimen matrimonial</label>
              <input type="text" class="form-control form-control-sm" name="estado_civil" id="editarEstadoCivilCliente" maxlength="50" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">Ocupación</label>
              <input type="text" class="form-control form-control-sm" name="ocupacion" id="editarOcupacionCliente" maxlength="50" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="editarTelefonoCliente">Teléfono</label>
              <input type="tel" class="form-control form-control-sm" id="editarTelefonoCliente" data-intl-hidden="#editarTelefonoClienteHidden" required data-requirement="Selecciona la lada y captura un número de 10 dígitos como mínimo." aria-describedby="editarTelefonoClienteHint">
              <div class="invalid-feedback">Ingrese un número válido.</div>
              <div id="editarTelefonoClienteHint" class="form-text ag-field-hint">Incluye la clave lada del país. El número final se guardará con prefijo internacional.</div>
            </div>
            <input type="hidden" name="telefono" id="editarTelefonoClienteHidden">
            <div class="col-md-6">
              <label class="form-label">Domicilio</label>
              <input type="text" class="form-control form-control-sm" name="domicilio" id="editarDomicilioCliente" maxlength="50" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">Correo electrónico</label>
              <input type="email" class="form-control form-control-sm" name="email" id="editarEmailCliente" required maxlength="100">
            </div>
            <div class="col-md-6">
              <label class="form-label">Beneficiario</label>
              <input type="text" class="form-control form-control-sm" name="beneficiario" id="editarBeneficiarioCliente" maxlength="50" required oninput="this.value = this.value.toUpperCase();">
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

<!-- Tabla de clientes -->
<section class="content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Listado</h3></div>
      <div class="card-body table-responsive">
        <div class="ag-table-ux-bar" data-table="#tablaClientes" data-default-label="Clientes" data-record-format="Cliente {data-nombre}" data-record-key="data-id" data-empty-message="Selecciona un cliente para ver acciones disponibles.">
          <div class="ag-table-ux-section ag-table-ux-primary">
            <div class="ag-table-ux-primary">
              <a href="index.php?ruta=crearCuenta" class="btn btn-primary ag-table-ux-new">
                <i class="fas fa-user-plus me-1"></i>
                Nuevo
              </a>
              <div class="ag-table-ux-current">Clientes</div>
              <div class="dropdown ag-table-ux-actions">
                <button class="btn btn-outline-secondary ag-table-ux-gear" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones del cliente" disabled>
                  <i class="fas fa-cog"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end ag-table-ux-actions-menu">
                  <div class="dropdown-item-text ag-record-empty-hint">Selecciona un cliente para ver acciones disponibles.</div>
                </div>
              </div>
            </div>
          </div>
          <div class="ag-table-ux-section ag-table-ux-search">
            <div class="ag-table-ux-search-control">
              <input type="search" class="form-control form-control-sm ag-table-ux-search-input" placeholder="Buscar en clientes">
              <button type="button" class="btn btn-outline-secondary btn-sm ag-table-ux-filter-toggle">
                <i class="fas fa-filter me-1"></i> Filtros
              </button>
            </div>
            <div class="ag-table-ux-filter-panel">
              <div class="row g-3" id="filtrosClientes">
                <div class="col-12 col-md-4">
                  <label class="form-label" for="filtroClientesEstado">Estado</label>
                  <select class="form-select form-select-sm" id="filtroClientesEstado" aria-label="Filtrar por estado">
                    <option value="activos" selected>Activos</option>
                    <option value="archivados">Archivados</option>
                    <option value="todos">Todos</option>
                  </select>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label" for="filtroClientesNacionalidad">Nacionalidad</label>
                  <select class="form-select form-select-sm" id="filtroClientesNacionalidad" aria-label="Filtrar por nacionalidad">
                    <option value="">Todas</option>
                    <?php foreach ($listaNacionalidades as $nac) : ?>
                      <option value="<?php echo htmlspecialchars($nac['identificador'] ?? '', ENT_QUOTES); ?>"><?php echo htmlspecialchars($nac['nombre'] ?? ''); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label" for="filtroClientesTexto">Búsqueda</label>
                  <input type="search" id="filtroClientesTexto" class="form-control form-control-sm" placeholder="Nombre, correo o RFC" aria-label="Filtrar por texto">
                  <p class="form-text mb-0">Los filtros se aplican automáticamente al listado.</p>
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
          <div class="ag-table-ux-section ag-table-ux-extra"></div>
        </div>
        <table class="table table-hover align-middle ag-data-table" id="tablaClientes" data-dt-resource="clientes" data-dt-params='{"estado":"activos"}'>
          <thead>
            <tr>
              <th scope="col" class="control" data-priority="1"></th>
              <th scope="col" class="min-desktop no-sort text-center">Sel.</th>
              <th scope="col" class="min-tablet-l">ID</th>
              <th scope="col" class="all">Nombre</th>
              <th scope="col" class="min-tablet">Email</th>
              <th scope="col" class="min-tablet">Estado</th>
              <th scope="col" class="d-none">Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const tabla = document.getElementById('tablaClientes');
    if (!tabla) {
      return;
    }

    const estadoSelect = document.getElementById('filtroClientesEstado');
    const nacionalidadSelect = document.getElementById('filtroClientesNacionalidad');
    const textoInput = document.getElementById('filtroClientesTexto');
    let filtrosInicializados = false;

    const obtenerManager = function () {
      if (window.AGDataTables && typeof window.AGDataTables.reload === 'function') {
        return window.AGDataTables;
      }
      return null;
    };

    const recopilarParametros = function () {
      return {
        estado: estadoSelect ? estadoSelect.value : 'activos',
        nacionalidad: nacionalidadSelect ? nacionalidadSelect.value : '',
        q: textoInput ? textoInput.value.trim() : ''
      };
    };

    const aplicarFiltros = function () {
      const manager = obtenerManager();
      if (!manager) {
        return;
      }

      const params = recopilarParametros();
      if (!params.estado) {
        params.estado = 'activos';
      }
      if (!params.nacionalidad) {
        params.nacionalidad = '';
      }
      if (!params.q) {
        params.q = '';
      }

      manager.reload(tabla, params, true);
    };

    const prepararFiltros = function () {
      if (filtrosInicializados) {
        return;
      }

      const manager = obtenerManager();
      if (!manager) {
        return;
      }

      filtrosInicializados = true;
      manager.updateParams(tabla, recopilarParametros());

      if (estadoSelect) {
        estadoSelect.addEventListener('change', aplicarFiltros);
      }

      if (nacionalidadSelect) {
        nacionalidadSelect.addEventListener('change', aplicarFiltros);
      }

      if (textoInput) {
        let debounceId;
        textoInput.addEventListener('input', function () {
          window.clearTimeout(debounceId);
          debounceId = window.setTimeout(aplicarFiltros, 400);
        });
      }
    };

    document.addEventListener('ag:datatable:ready', function (event) {
      if (!event || !event.detail) {
        return;
      }

      if (event.detail.element === tabla || event.detail.selector === '#tablaClientes') {
        prepararFiltros();
      }
    });
  });
</script>
