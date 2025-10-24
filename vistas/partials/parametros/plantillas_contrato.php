<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
$tipoOptions = [];
foreach ($tiposContrato as $tip) {
    $tipoOptions[] = [
        'value' => (int)($tip['id'] ?? 0),
        'label' => $escape($tip['nombre'] ?? ''),
    ];
}
$tipoOptionsJson = htmlspecialchars(json_encode($tipoOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
?>
<div class="parametros-section mb-5" id="parametros-plantillas-contrato">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
      <h5 class="mb-1">Plantillas de contrato</h5>
      <p class="text-muted mb-0 small">Asocia cada tipo de contrato con su archivo oficial y edítalo directamente desde la tabla.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-success btn-sm" data-editable-action="new" data-editable-target="#tablaPlantillas">
        <i class="fas fa-plus me-1"></i>
        Nuevo
      </button>
      <button type="button" class="btn btn-outline-primary btn-sm d-none" data-editable-action="save" data-editable-target="#tablaPlantillas" title="Guardar cambios">
        <i class="fas fa-cloud-upload-alt"></i>
      </button>
      <span class="badge bg-light text-dark fw-semibold ms-1">
        <?php echo $contar($plantillas); ?> disponibles
      </span>
    </div>
  </div>
  <div class="card card-outline card-secondary shadow-sm">
    <div class="card-body">
      <p class="text-muted small mb-3">
        Utiliza doble clic para seleccionar el tipo de contrato o reemplazar el archivo. Los registros modificados se resaltarán hasta que guardes los cambios.
      </p>
      <div class="alert alert-info small d-flex align-items-start gap-2" role="alert">
        <i class="fas fa-circle-info mt-1" aria-hidden="true"></i>
        <div>
          <strong>¿Cómo actualizar una plantilla?</strong>
          <ol class="mb-0 ps-3">
            <li>Doble clic en la columna <em>Tipo</em> para elegir el contrato correspondiente.</li>
            <li>Doble clic en la columna <em>Archivo</em> para cargar el documento oficial en formato DOCX o PDF.</li>
            <li>Cuando las filas aparezcan resaltadas, presiona el botón <i class="fas fa-cloud-upload-alt"></i> <span class="visually-hidden">Guardar cambios</span> para aplicar los cambios.</li>
          </ol>
        </div>
      </div>
      <div class="table-responsive">
        <table
          class="table table-hover table-striped table-sm align-middle mb-0 ag-data-table"
          id="tablaPlantillas"
          data-dt-resource="plantillas_contrato"
          data-datatable-options='{"pageLength":5,"lengthMenu":[[5,10,25,-1],[5,10,25,"Todos"]]}'
          data-editable="1"
          data-editable-type="plantilla-contrato"
          data-editable-endpoint="<?php echo htmlspecialchars($parametrosFormAction, ENT_QUOTES, 'UTF-8'); ?>"
          data-select-tipo-options="<?php echo $tipoOptionsJson !== false ? $tipoOptionsJson : '[]'; ?>"
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
                data-value-key="tipo_id"
                data-payload="tipo_contrato_id"
              >
                Tipo
              </th>
              <th scope="col" class="min-tablet">Nombre original</th>
              <th
                scope="col"
                class="min-tablet"
                data-editable-field="archivo"
                data-editor="file"
                data-accept=".docx,.pdf"
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
