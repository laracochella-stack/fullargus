<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
?>
<div class="parametros-section mb-5" id="parametros-nacionalidades">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
      <h5 class="mb-1">Nacionalidades</h5>
      <p class="text-muted mb-0 small">Actualiza el catálogo directamente desde la tabla y comparte los cambios con todo el equipo.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-success btn-sm" data-editable-action="new" data-editable-target="#tablaNacionalidades">
        <i class="fas fa-plus me-1"></i>
        Nuevo
      </button>
      <button type="button" class="btn btn-outline-primary btn-sm d-none" data-editable-action="save" data-editable-target="#tablaNacionalidades" title="Guardar cambios">
        <i class="fas fa-cloud-upload-alt"></i>
      </button>
      <span class="badge bg-light text-dark fw-semibold ms-1">
        <?php echo $contar($nacionalidades); ?> registradas
      </span>
    </div>
  </div>
  <div class="card card-outline card-secondary shadow-sm">
    <div class="card-body">
      <p class="text-muted small mb-3">
        Doble clic en el nombre para editarlo o agrega nuevas nacionalidades desde el botón <strong>Nuevo</strong>. Recuerda guardar los cambios con el ícono de nube.
      </p>
      <div class="table-responsive">
        <table
          class="table table-hover table-striped table-sm align-middle mb-0 ag-data-table"
          id="tablaNacionalidades"
          data-dt-resource="nacionalidades"
          data-datatable-options='{"pageLength":5,"lengthMenu":[[5,10,25,-1],[5,10,25,"Todos"]]}'
          data-editable="1"
          data-editable-type="variable"
          data-variable-type="nacionalidad"
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
