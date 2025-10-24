<?php
use App\Controllers\ControladorParametros;
use App\Support\AppNavigation;
/**
 * Panel de parámetros generales para administradores y moderadores.
 * Permite gestionar nacionalidades, tipos de contrato y subir plantillas.
 */

// Procesar formularios
ControladorParametros::ctrAgregarVariable();
ControladorParametros::ctrEditarVariable();
ControladorParametros::ctrEliminarVariable();
ControladorParametros::ctrSubirPlantilla();
ControladorParametros::ctrEditarPlantilla();
ControladorParametros::ctrEliminarPlantilla();
ControladorParametros::ctrSubirPlantillaSolicitud();
ControladorParametros::ctrEditarPlantillaSolicitud();
ControladorParametros::ctrEliminarPlantillaSolicitud();

// Obtener variables
$nacionalidades = ControladorParametros::ctrMostrarVariables('nacionalidad');
$tiposContrato = ControladorParametros::ctrMostrarVariables('tipo_contrato');
$plantillas = ControladorParametros::ctrMostrarPlantillas();
$plantillasSolicitud = ControladorParametros::ctrMostrarPlantillasSolicitud();
$__basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

$permisoParametros = $_SESSION['permission'] ?? 'user';
if (!in_array($permisoParametros, ['senior','owner','admin'], true)) {
    echo '<div class="alert alert-danger m-3">No tiene permisos para acceder a los parámetros.</div>';
    return;
}

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$contar = static function (?array $items): int {
    return is_array($items) ? count($items) : 0;
};
?>


<?php
require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Parámetros generales',
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
    <div class="card card-primary card-outline card-outline-tabs">
      <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs" id="parametrosTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <a class="nav-link active" id="parametros-edicion-tab" data-bs-toggle="pill" data-toggle="pill" href="#parametros-tab-edicion" role="tab" aria-controls="parametros-tab-edicion" aria-selected="true">
              <i class="fas fa-sliders-h me-2"></i>Edición de campos
            </a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link" id="parametros-plantillas-tab" data-bs-toggle="pill" data-toggle="pill" href="#parametros-tab-plantillas" role="tab" aria-controls="parametros-tab-plantillas" aria-selected="false">
              <i class="far fa-copy me-2"></i>Plantillas
            </a>
          </li>
        </ul>
      </div>
      <div class="card-body">
        <div class="tab-content" id="parametrosTabsContent">
          <div class="tab-pane fade show active" id="parametros-tab-edicion" role="tabpanel" aria-labelledby="parametros-edicion-tab">
            <div class="mb-4">
              <h4 class="fw-semibold mb-1">Gestiona los catálogos base</h4>
              <p class="text-muted mb-0">Administra nacionalidades y tipos de contrato disponibles en los formularios del sistema.</p>
            </div>

            <div class="parametros-section mb-5">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                  <h5 class="mb-1">Nacionalidades</h5>
                  <p class="text-muted mb-0">Administra el catálogo de nacionalidades disponibles para los usuarios.</p>
                </div>
                <span class="badge bg-light text-dark fw-semibold">
                  <?php echo $contar($nacionalidades); ?> registradas
                </span>
              </div>
              <div class="row g-3 align-items-stretch">
                <div class="col-12 col-xl-4">
                  <div class="ag-form-card card shadow-sm h-100">
                    <div class="card-header border-0">
                      <h3 class="card-title h6 mb-0 text-uppercase text-muted">Registrar nacionalidad</h3>
                    </div>
                    <div class="card-body">
                      <form id="formAddNacionalidad" method="post" class="ag-parameter-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="tipo" value="nacionalidad">
                        <input type="hidden" name="agregarVariable" value="1">
                        <fieldset class="ag-parameter-fieldset">
                          <legend class="visually-hidden">Agregar nacionalidad</legend>
                          <div class="mb-3">
                            <label class="form-label" for="nacionalidadIdentificador">Identificador</label>
                            <input type="text" name="identificador" id="nacionalidadIdentificador" class="form-control form-control-sm form-control-plaintext" readonly aria-describedby="nacionalidadIdentificadorHelp" placeholder="AUTO">
                            <div id="nacionalidadIdentificadorHelp" class="form-text">Se generará automáticamente al guardar.</div>
                          </div>
                          <div class="mb-4">
                            <label class="form-label" for="nacionalidadNombre">Nombre</label>
                            <input type="text" name="nombre" id="nacionalidadNombre" class="form-control form-control-sm" required maxlength="120" autocomplete="off" placeholder="Ej. Mexicana" autocapitalize="characters">
                          </div>
                          <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                              <i class="fas fa-plus me-1"></i>Agregar nacionalidad
                            </button>
                          </div>
                        </fieldset>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="col-12 col-xl-8">
                  <div class="ag-form-card card card-outline card-secondary shadow-sm h-100">
                    <div class="card-header border-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                      <div>
                        <h3 class="card-title h6 mb-1">Listado de nacionalidades</h3>
                        <p class="text-muted mb-0 small">Visualiza y edita los registros existentes.</p>
                      </div>
                      <span class="badge bg-light text-dark fw-semibold">
                        <?php echo $contar($nacionalidades); ?> registradas
                      </span>
                    </div>
                    <div class="card-body p-0">
                      <div class="table-responsive">
                        <table class="table table-hover table-striped table-sm align-middle mb-0 ag-data-table" id="tablaNacionalidades" data-dt-resource="nacionalidades" data-datatable-options='{"pageLength":5,"lengthMenu":[[5,10,25,-1],[5,10,25,"Todos"]]}'>
                          <thead>
                            <tr>
                              <th scope="col" class="control" data-priority="1"></th>
                              <th scope="col" class="min-tablet-l">ID</th>
                              <th scope="col" class="all">Identificador</th>
                              <th scope="col" class="all">Nombre</th>
                              <th scope="col" class="all no-sort text-center">Acciones</th>
                            </tr>
                          </thead>
                          <tbody></tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="parametros-section">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                  <h5 class="mb-1">Tipos de contrato</h5>
                  <p class="text-muted mb-0">Configura las opciones disponibles al crear contratos.</p>
                </div>
                <span class="badge bg-light text-dark fw-semibold">
                  <?php echo $contar($tiposContrato); ?> registrados
                </span>
              </div>
              <div class="row g-3 align-items-stretch">
                <div class="col-12 col-xl-4">
                  <div class="ag-form-card card shadow-sm h-100">
                    <div class="card-header border-0">
                      <h3 class="card-title h6 mb-0 text-uppercase text-muted">Registrar tipo de contrato</h3>
                    </div>
                    <div class="card-body">
                      <form id="formAddTipo" method="post" class="ag-parameter-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="tipo" value="tipo_contrato">
                        <input type="hidden" name="agregarVariable" value="1">
                        <fieldset class="ag-parameter-fieldset">
                          <legend class="visually-hidden">Agregar tipo de contrato</legend>
                          <div class="mb-3">
                            <label class="form-label" for="tipoContratoIdentificador">Identificador</label>
                            <input type="text" name="identificador" id="tipoContratoIdentificador" class="form-control form-control-sm form-control-plaintext" readonly aria-describedby="tipoContratoIdentificadorHelp" placeholder="AUTO">
                            <div id="tipoContratoIdentificadorHelp" class="form-text">El sistema generará el identificador.</div>
                          </div>
                          <div class="mb-4">
                            <label class="form-label" for="tipoContratoNombre">Nombre</label>
                            <input type="text" name="nombre" id="tipoContratoNombre" class="form-control form-control-sm" required maxlength="120" autocomplete="off" placeholder="Ej. Compraventa" autocapitalize="characters">
                          </div>
                          <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                              <i class="fas fa-plus me-1"></i>Agregar tipo
                            </button>
                          </div>
                        </fieldset>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="col-12 col-xl-8">
                  <div class="ag-form-card card card-outline card-secondary shadow-sm h-100">
                    <div class="card-header border-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                      <div>
                        <h3 class="card-title h6 mb-1">Listado de tipos de contrato</h3>
                        <p class="text-muted mb-0 small">Consulta y gestiona los tipos configurados.</p>
                      </div>
                      <span class="badge bg-light text-dark fw-semibold">
                        <?php echo $contar($tiposContrato); ?> registrados
                      </span>
                    </div>
                    <div class="card-body p-0">
                      <div class="table-responsive">
                        <table class="table table-hover table-striped table-sm align-middle mb-0 ag-data-table" id="tablaTipos" data-dt-resource="tipos_contrato" data-datatable-options='{"pageLength":5,"lengthMenu":[[5,10,25,-1],[5,10,25,"Todos"]]}'>
                          <thead>
                            <tr>
                              <th scope="col" class="control" data-priority="1"></th>
                              <th scope="col" class="min-tablet-l">ID</th>
                              <th scope="col" class="all">Identificador</th>
                              <th scope="col" class="all">Nombre</th>
                              <th scope="col" class="all no-sort text-center">Acciones</th>
                            </tr>
                          </thead>
                          <tbody></tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="parametros-tab-plantillas" role="tabpanel" aria-labelledby="parametros-plantillas-tab">
            <div class="mb-4">
              <h4 class="fw-semibold mb-1">Plantillas institucionales</h4>
              <p class="text-muted mb-0">Carga y administra los archivos base para contratos y solicitudes.</p>
            </div>

            <div class="parametros-section mb-5">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                  <h5 class="mb-1">Plantillas de contrato</h5>
                  <p class="text-muted mb-0">Asocia un archivo DOCX a cada tipo de contrato disponible.</p>
                </div>
                <span class="badge bg-light text-dark fw-semibold">
                  <?php echo $contar($plantillas); ?> disponibles
                </span>
              </div>
              <div class="row g-3 align-items-stretch">
                <div class="col-12 col-xl-4">
                  <div class="ag-form-card card shadow-sm h-100">
                    <div class="card-header border-0">
                      <h3 class="card-title h6 mb-0 text-uppercase text-muted">Subir nueva plantilla</h3>
                    </div>
                    <div class="card-body">
                      <form id="formSubirPlantilla" method="post" enctype="multipart/form-data" class="ag-parameter-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="subirPlantilla" value="1">
                        <fieldset class="ag-parameter-fieldset">
                          <legend class="visually-hidden">Subir plantilla de contrato</legend>
                          <div class="mb-3">
                            <label class="form-label" for="tipoContratoPlantilla">Tipo de contrato</label>
                            <select name="tipo_contrato_id" id="tipoContratoPlantilla" class="form-select form-select-sm" required>
                              <option value="">Seleccione tipo</option>
                              <?php foreach ($tiposContrato as $tip) : ?>
                                <option value="<?php echo (int)($tip['id'] ?? 0); ?>"><?php echo $escape($tip['nombre'] ?? ''); ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="mb-4">
                            <label class="form-label" for="archivoPlantillaContrato">Archivo (solo .docx)</label>
                            <input type="file" name="plantilla" id="archivoPlantillaContrato" class="form-control form-control-sm" accept=".docx" required aria-describedby="plantillaContratoAyuda">
                            <div id="plantillaContratoAyuda" class="form-text">Máximo 150&nbsp;MB por archivo.</div>
                          </div>
                          <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                              <i class="fas fa-upload me-1"></i>Subir plantilla
                            </button>
                          </div>
                        </fieldset>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="col-12 col-xl-8">
                  <div class="ag-form-card card card-outline card-secondary shadow-sm h-100">
                    <div class="card-header border-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                      <div>
                        <h3 class="card-title h6 mb-1">Plantillas cargadas</h3>
                        <p class="text-muted mb-0 small">Descarga, reemplaza o elimina los archivos existentes.</p>
                      </div>
                      <span class="badge bg-light text-dark fw-semibold">
                        <?php echo $contar($plantillas); ?> disponibles
                      </span>
                    </div>
                    <div class="card-body p-0">
                      <div class="table-responsive">
                        <table class="table table-hover table-striped table-sm align-middle mb-0 ag-data-table" id="tablaPlantillas" data-dt-resource="plantillas_contrato" data-datatable-options='{"pageLength":5,"lengthMenu":[[5,10,25,-1],[5,10,25,"Todos"]]}'>
                          <thead>
                            <tr>
                              <th scope="col" class="control" data-priority="1"></th>
                              <th scope="col" class="min-tablet-l">ID</th>
                              <th scope="col" class="all">Tipo</th>
                              <th scope="col" class="min-tablet">Nombre original</th>
                              <th scope="col" class="min-tablet">Archivo</th>
                              <th scope="col" class="all no-sort text-center">Acciones</th>
                            </tr>
                          </thead>
                          <tbody></tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="parametros-section">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                  <h5 class="mb-1">Plantillas de solicitud</h5>
                  <p class="text-muted mb-0">Mantén actualizados los formatos DOCX utilizados en los flujos de solicitudes.</p>
                </div>
                <span class="badge bg-light text-dark fw-semibold">
                  <?php echo $contar($plantillasSolicitud); ?> disponibles
                </span>
              </div>
              <div class="row g-3 align-items-stretch">
                <div class="col-12 col-xl-4">
                  <div class="ag-form-card card shadow-sm h-100">
                    <div class="card-header border-0">
                      <h3 class="card-title h6 mb-0 text-uppercase text-muted">Subir nueva plantilla</h3>
                    </div>
                    <div class="card-body">
                      <form id="formSubirPlantillaSolicitud" method="post" enctype="multipart/form-data" class="ag-parameter-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="subirPlantillaSolicitud" value="1">
                        <fieldset class="ag-parameter-fieldset">
                          <legend class="visually-hidden">Subir plantilla de solicitud</legend>
                          <div class="mb-3">
                            <label class="form-label" for="plantillaSolicitudTipo">Tipo de plantilla</label>
                            <select name="plantilla_tipo" id="plantillaSolicitudTipo" class="form-select form-select-sm" required>
                              <option value="">Seleccione tipo</option>
                              <option value="default">Solicitud estándar</option>
                              <option value="albacea">Solicitud con albacea</option>
                            </select>
                          </div>
                          <div class="mb-4">
                            <label class="form-label" for="archivoPlantillaSolicitud">Archivo (solo .docx)</label>
                            <input type="file" name="plantilla" id="archivoPlantillaSolicitud" class="form-control form-control-sm" accept=".docx" required aria-describedby="plantillaSolicitudAyuda">
                            <div id="plantillaSolicitudAyuda" class="form-text">Máximo 150&nbsp;MB por archivo.</div>
                          </div>
                          <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                              <i class="fas fa-upload me-1"></i>Subir plantilla
                            </button>
                          </div>
                        </fieldset>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="col-12 col-xl-8">
                  <div class="ag-form-card card card-outline card-secondary shadow-sm h-100">
                    <div class="card-header border-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                      <div>
                        <h3 class="card-title h6 mb-1">Plantillas disponibles</h3>
                        <p class="text-muted mb-0 small">Gestiona los formatos utilizados en las solicitudes.</p>
                      </div>
                      <span class="badge bg-light text-dark fw-semibold">
                        <?php echo $contar($plantillasSolicitud); ?> disponibles
                      </span>
                    </div>
                    <div class="card-body p-0">
                      <div class="table-responsive">
                        <table class="table table-hover table-striped table-sm align-middle mb-0 ag-data-table" id="tablaPlantillasSolicitud" data-dt-resource="plantillas_solicitud" data-datatable-options='{"pageLength":5,"lengthMenu":[[5,10,25,-1],[5,10,25,"Todos"]]}'>
                          <thead>
                            <tr>
                              <th scope="col" class="control" data-priority="1"></th>
                              <th scope="col" class="min-tablet-l">ID</th>
                              <th scope="col" class="all">Tipo</th>
                              <th scope="col" class="min-tablet">Nombre original</th>
                              <th scope="col" class="min-tablet">Archivo</th>
                              <th scope="col" class="all no-sort text-center">Acciones</th>
                            </tr>
                          </thead>
                          <tbody></tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Modal editar variable -->
  <div class="modal fade" id="modalEditarVariable" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="formEditarVariable" method="post">
          <input type="hidden" name="csrf_token" value="<?php echo $escape($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="editarVariable" value="1">
          <input type="hidden" name="id" id="editarVariableId">
          <div class="modal-header">
            <h5 class="modal-title">Editar variable</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Identificador</label>
              <input type="text" name="identificador" id="editarVariableIdentificador" class="form-control form-control-sm form-control-plaintext" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Nombre</label>
              <input type="text" name="nombre" id="editarVariableNombre" class="form-control form-control-sm" required>
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

  <!-- Modal editar plantilla -->
  <div class="modal fade" id="modalEditarPlantilla" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="formEditarPlantilla" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo $escape($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="editarPlantilla" value="1">
          <input type="hidden" name="plantilla_id" id="editarPlantillaId">
          <div class="modal-header">
            <h5 class="modal-title">Editar plantilla</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Tipo de contrato</label>
              <select name="tipo_contrato_id" id="editarPlantillaTipo" class="form-select form-select-sm" required>
                <?php foreach ($tiposContrato as $t) : ?>
                  <option value="<?php echo (int)($t['id'] ?? 0); ?>"><?php echo $escape($t['nombre'] ?? ''); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Reemplazar archivo (opcional)</label>
              <input type="file" name="plantilla" class="form-control form-control-sm" accept=".docx,.pdf">
              <small class="text-muted">Dejar en blanco para conservar el archivo actual. Tamaño máximo 150 MB.</small>
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

  <div class="modal fade" id="modalEditarPlantillaSolicitud" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="formEditarPlantillaSolicitud" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo $escape($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="editarPlantillaSolicitud" value="1">
          <input type="hidden" name="plantilla_solicitud_id" id="editarPlantillaSolicitudId">
          <div class="modal-header">
            <h5 class="modal-title">Editar plantilla de solicitud</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Tipo de plantilla</label>
              <select name="plantilla_tipo" id="editarPlantillaSolicitudTipo" class="form-select form-select-sm" required>
                <option value="default">Solicitud estándar</option>
                <option value="albacea">Solicitud con albacea</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Archivo actual</label>
              <input type="text" id="editarPlantillaSolicitudNombre" class="form-control form-control-sm form-control-plaintext" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Reemplazar archivo (opcional)</label>
              <input type="file" name="plantilla" class="form-control form-control-sm" accept=".docx">
              <small class="text-muted">Solo archivos .docx. Tamaño máximo 150&nbsp;MB.</small>
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
</section>
