<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
?>
<div class="modal fade" id="modalEditarVariable" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formEditarVariable" method="post" action="<?php echo htmlspecialchars($parametrosFormAction, ENT_QUOTES, 'UTF-8'); ?>">
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
