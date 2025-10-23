<div class="modal fade" id="modalClienteCoincidenteSolicitud" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Cliente ya registrado</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3" data-mensaje-cliente>Se detectó un cliente previamente registrado que coincide con la información de la solicitud.</p>
        <ul class="list-unstyled small mb-0" data-detalle-cliente></ul>
        <div class="alert alert-warning small mt-3 mb-0" role="alert">
          Al continuar se utilizarán los datos del cliente existente dentro del contrato.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-info" id="btnConfirmarClienteCoincidente">
          <i class="fas fa-file-signature me-1"></i>Generar contrato con cliente existente
        </button>
      </div>
    </div>
  </div>
</div>
