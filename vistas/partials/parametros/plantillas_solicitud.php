<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
$solicitudOptions = [
    ['value' => 'default', 'label' => 'Solicitud estándar'],
    ['value' => 'albacea', 'label' => 'Solicitud con albacea'],
];
$solicitudOptionsJson = htmlspecialchars(json_encode($solicitudOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
?>
<div class="parametros-section" id="parametros-plantillas-solicitud">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
      <h5 class="mb-1">Plantillas de solicitud</h5>
      <p class="text-muted mb-0 small">Mantén actualizados los formatos DOCX utilizados en los flujos de solicitudes.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-success btn-sm" data-editable-action="new" data-editable-target="#tablaPlantillasSolicitud">
        <i class="fas fa-plus me-1"></i>
        Nuevo
      </button>
      <button type="button" class="btn btn-outline-primary btn-sm d-none" data-editable-action="save" data-editable-target="#tablaPlantillasSolicitud" title="Guardar cambios">
        <i class="fas fa-cloud-upload-alt"></i>
      </button>
      <span class="badge bg-light text-dark fw-semibold ms-1">
        <?php echo $contar($plantillasSolicitud); ?> disponibles
      </span>
    </div>
  </div>
  <div class="card card-outline card-secondary shadow-sm">
    <div class="card-body">
      <p class="text-muted small mb-3">
        Selecciona el tipo de plantilla con doble clic o carga un nuevo archivo cuando sea necesario. Guarda siempre los cambios para aplicar las modificaciones.
      </p>
      <div class="alert alert-info small d-flex align-items-start gap-2" role="alert">
        <i class="fas fa-circle-info mt-1" aria-hidden="true"></i>
        <div>
          <strong>Pasos para registrar una plantilla:</strong>
          <ol class="mb-0 ps-3">
            <li>Doble clic sobre la columna <em>Tipo</em> y elige el formato de solicitud que necesitas.</li>
            <li>Doble clic en la columna <em>Archivo</em> para subir el DOCX institucional correspondiente.</li>
            <li>Cuando la fila quede resaltada, utiliza el botón <i class="fas fa-cloud-upload-alt"></i> <span class="visually-hidden">Guardar cambios</span> para confirmar el registro.</li>
          </ol>
        </div>
      </div>
      <div class="table-responsive">
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
