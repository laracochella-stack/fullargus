<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
$solicitudOptions = [
    ['value' => 'default', 'label' => 'Solicitud estándar'],
    ['value' => 'albacea', 'label' => 'Solicitud con albacea'],
];
$solicitudOptionsJson = htmlspecialchars(json_encode($solicitudOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
?>
<div class="parametros-section ag-parameters-panel" id="parametros-plantillas-solicitud">
  <div class="ag-parameters-panel__header">
    <div class="ag-parameters-panel__intro">
      <h5 class="ag-parameters-panel__title">Plantillas de solicitud</h5>
      <p class="ag-parameters-panel__description text-muted mb-0 small">
        Mantén vigentes los formatos DOCX utilizados en los flujos de solicitudes.
      </p>
    </div>
    <div class="ag-parameters-panel__toolbar">
      <div class="btn-group ag-parameters-panel__btn-group" role="group" aria-label="Acciones de plantillas de solicitud">
        <button type="button" class="btn btn-success btn-sm" data-editable-action="new" data-editable-target="#tablaPlantillasSolicitud">
          <i class="fas fa-plus me-1" aria-hidden="true"></i>
          <span>Nuevo</span>
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm d-none" data-editable-action="save" data-editable-target="#tablaPlantillasSolicitud" title="Guardar cambios">
          <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
          <span class="visually-hidden">Guardar cambios</span>
        </button>
      </div>
      <span class="badge bg-light text-dark fw-semibold ag-parameters-panel__badge">
        <?php echo $contar($plantillasSolicitud); ?> disponibles
      </span>
    </div>
  </div>
  <div class="ag-parameters-panel__body">
    <div class="table-responsive ag-parameters-panel__table">
      <table
          class="table table-hover table-striped table-sm align-middle mb-0 ag-data-table"
          id="tablaPlantillasSolicitud"
          data-dt-resource="plantillas_solicitud"
          data-datatable-options='{"pageLength":5,"lengthMenu":[[5,10,25,-1],[5,10,25,"Todos"]]}'
          data-editable="1"
          data-editable-type="plantilla-solicitud"
          data-editable-endpoint="<?php echo htmlspecialchars($parametrosFormAction, ENT_QUOTES, 'UTF-8'); ?>"
          data-select-tipo-options="<?php echo $solicitudOptionsJson !== false ? $solicitudOptionsJson : '[]'; ?>"
        >
          <thead>
            <tr>
              <th scope="col" class="control" data-priority="1"></th>
              <th scope="col" class="min-tablet-l">ID</th>
              <th
                scope="col"
                class="all"
                data-editable-field="tipo"
                data-editor="select"
                data-value-key="tipo_valor"
                data-payload="plantilla_tipo"
              >
                Tipo
              </th>
              <th scope="col" class="min-tablet">Nombre original</th>
              <th
                scope="col"
                class="min-tablet"
                data-editable-field="archivo"
                data-editor="file"
                data-accept=".docx"
                data-payload="plantilla"
              >
                Archivo
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
