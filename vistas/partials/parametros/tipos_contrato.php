<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
?>
<div class="parametros-section" id="parametros-tipos-contrato">
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
          <form id="formAddTipo" method="post" class="ag-parameter-form" action="<?php echo htmlspecialchars($parametrosFormAction, ENT_QUOTES, 'UTF-8'); ?>">
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
