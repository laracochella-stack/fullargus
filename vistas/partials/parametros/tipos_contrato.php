<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
?>
<div class="parametros-section" id="parametros-tipos-contrato">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
      <h5 class="mb-1">Tipos de contrato</h5>
      <p class="text-muted mb-0 small">Gestiona directamente desde la tabla los tipos disponibles al elaborar contratos.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-success btn-sm" data-editable-action="new" data-editable-target="#tablaTipos">
        <i class="fas fa-plus me-1"></i>
        Nuevo
      </button>
      <button type="button" class="btn btn-outline-primary btn-sm d-none" data-editable-action="save" data-editable-target="#tablaTipos" title="Guardar cambios">
        <i class="fas fa-cloud-upload-alt"></i>
      </button>
      <span class="badge bg-light text-dark fw-semibold ms-1">
        <?php echo $contar($tiposContrato); ?> registrados
      </span>
    </div>
  </div>
  <div class="card card-outline card-secondary shadow-sm">
    <div class="card-body">
      <p class="text-muted small mb-3">
        Haz doble clic sobre el nombre para editarlo. Los cambios aparecerán resaltados hasta que presiones el botón de la nube para guardarlos.
      </p>
      <div class="table-responsive">
        <table
          class="table table-hover table-striped table-sm align-middle mb-0 ag-data-table"
          id="tablaTipos"
          data-dt-resource="tipos_contrato"
          data-datatable-options='{"pageLength":5,"lengthMenu":[[5,10,25,-1],[5,10,25,"Todos"]]}'
          data-editable="1"
          data-editable-type="variable"
          data-variable-type="tipo_contrato"
          data-editable-endpoint="<?php echo htmlspecialchars($parametrosFormAction, ENT_QUOTES, 'UTF-8'); ?>"
        >
          <thead>
            <tr>
              <th scope="col" class="control" data-priority="1"></th>
              <th scope="col" class="min-tablet-l">ID</th>
              <th scope="col" class="all">Identificador</th>
              <th
                scope="col"
                class="all"
                data-editable-field="nombre"
                data-editor="text"
                data-required="1"
                data-maxlength="120"
                data-payload="nombre"
              >
                Nombre
              </th>
              <th scope="col" class="all no-sort text-center">Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
