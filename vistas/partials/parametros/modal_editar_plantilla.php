<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
?>
<div class="modal fade" id="modalEditarPlantilla" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formEditarPlantilla" method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars($parametrosFormAction, ENT_QUOTES, 'UTF-8'); ?>">
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
            <input type="file" name="plantilla" class="form-control form-control-sm" accept=".docx">
            <small class="text-muted">Dejar en blanco para conservar el archivo actual. Tamaño máximo 150&nbsp;MB.</small>
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
