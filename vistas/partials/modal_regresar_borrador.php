<div class="modal fade" id="modalRegresarBorrador" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="index.php?ruta=solicitudes" id="formRegresarBorrador">
        <div class="modal-header bg-warning">
          <h5 class="modal-title">Regresar solicitud a borrador</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES); ?>">
          <input type="hidden" name="cambiar_estado_solicitud" value="1">
          <input type="hidden" name="solicitud_id" id="regresarBorradorSolicitudId">
          <input type="hidden" name="nuevo_estado" value="borrador">
          <div class="alert alert-warning small" id="regresarBorradorResumen" role="alert"></div>
          <div class="mb-3">
            <label for="regresarBorradorMotivo" class="form-label">Motivo del regreso</label>
            <textarea class="form-control form-control-sm" name="motivo_regreso" id="regresarBorradorMotivo" rows="4" required minlength="5" maxlength="500" placeholder="Describe el motivo del regreso a borrador."></textarea>
            <div class="form-text">El autor verá este mensaje como guía para realizar los ajustes necesarios.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">Regresar a borrador</button>
        </div>
      </form>
    </div>
  </div>
</div>
