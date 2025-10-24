<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
?>
<div class="parametros-section" id="parametros-plantillas-solicitud">
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
          <form id="formSubirPlantillaSolicitud" method="post" enctype="multipart/form-data" class="ag-parameter-form" action="<?php echo htmlspecialchars($parametrosFormAction, ENT_QUOTES, 'UTF-8'); ?>">
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
