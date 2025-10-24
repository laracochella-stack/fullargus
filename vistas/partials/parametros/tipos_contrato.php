<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
?>
<div class="parametros-section ag-parameters-panel" id="parametros-tipos-contrato">
  <div class="ag-parameters-panel__header">
    <div class="ag-parameters-panel__intro">
      <h5 class="ag-parameters-panel__title">Tipos de contrato</h5>
      <p class="ag-parameters-panel__description text-muted mb-0 small">
        Gestiona la lista de tipos disponibles al elaborar contratos.
      </p>
    </div>
    <div class="ag-parameters-panel__toolbar">
      <div class="btn-group ag-parameters-panel__btn-group" role="group" aria-label="Acciones de tipos de contrato">
        <button type="button" class="btn btn-success btn-sm" data-editable-action="new" data-editable-target="#tablaTipos">
          <i class="fas fa-plus me-1" aria-hidden="true"></i>
          <span>Nuevo</span>
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm d-none" data-editable-action="save" data-editable-target="#tablaTipos" title="Guardar cambios">
          <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
          <span class="visually-hidden">Guardar cambios</span>
        </button>
      </div>
      <span class="badge bg-light text-dark fw-semibold ag-parameters-panel__badge">
        <?php echo $contar($tiposContrato); ?> registrados
      </span>
    </div>
  </div>
  <div class="ag-parameters-panel__body">
    <div class="table-responsive ag-parameters-panel__table">
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
