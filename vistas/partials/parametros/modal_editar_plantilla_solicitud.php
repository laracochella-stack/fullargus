<?php
$parametrosFormAction = $parametrosFormAction ?? 'index.php?ruta=parametros';
?>
<div class="modal fade" id="modalEditarPlantillaSolicitud" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formEditarPlantillaSolicitud" method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars($parametrosFormAction, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $escape($_SESSION['csrf_token'] ?? ''); ?>">
        <input type="hidden" name="editarPlantillaSolicitud" value="1">
        <input type="hidden" name="plantilla_solicitud_id" id="editarPlantillaSolicitudId">
        <div class="modal-header">
          <h5 class="modal-title">Editar plantilla de solicitud</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Tipo de plantilla</label>
            <select name="plantilla_tipo" id="editarPlantillaSolicitudTipo" class="form-select form-select-sm" required>
              <option value="default">Solicitud estándar</option>
              <option value="albacea">Solicitud con albacea</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Archivo actual</label>
            <input type="text" id="editarPlantillaSolicitudNombre" class="form-control form-control-sm form-control-plaintext" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Reemplazar archivo (opcional)</label>
            <input type="file" name="plantilla" class="form-control form-control-sm" accept=".docx">
            <small class="text-muted">Solo archivos .docx. Tamaño máximo 150&nbsp;MB.</small>
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
