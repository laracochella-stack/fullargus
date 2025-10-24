<?php
use App\Controllers\ControladorConsola;
use App\Support\AppNavigation;
/**
 * Consola administrativa para gestionar anuncios globales.
 */

$resultadoConsola = ControladorConsola::ctrProcesarAnuncio();
$permisoConsola = $_SESSION['permission'] ?? 'user';

if ($permisoConsola !== 'admin') {
    echo '<div class="alert alert-danger m-3">Solo los administradores pueden acceder a la consola.</div>';
    return;
}

$anuncioActual = ControladorConsola::ctrObtenerAnuncioVigente();
$anunciosDisponibles = ControladorConsola::ctrListarAnuncios(25);
$vigenteHastaTexto = '';

$destinosSeleccionados = [
    'dashboard' => true,
    'popup' => false,
];

$destinosFormulario = $_POST['destinos'] ?? null;
if ($destinosFormulario !== null) {
    if (!is_array($destinosFormulario)) {
        $destinosFormulario = [$destinosFormulario];
    }

    foreach ($destinosSeleccionados as $clave => $_valor) {
        $destinosSeleccionados[$clave] = in_array($clave, $destinosFormulario, true);
    }
} elseif ($anuncioActual) {
    $destinosSeleccionados['dashboard'] = (int)($anuncioActual['mostrar_en_dashboard'] ?? 0) === 1;
    $destinosSeleccionados['popup'] = (int)($anuncioActual['mostrar_en_popup'] ?? 0) === 1;

    if (!$destinosSeleccionados['dashboard'] && !$destinosSeleccionados['popup']) {
        $destinosSeleccionados['dashboard'] = true;
    }
}

if ($anuncioActual && !empty($anuncioActual['vigente_hasta'])) {
    try {
        $vigencia = new \DateTimeImmutable($anuncioActual['vigente_hasta']);
        $vigenteHastaTexto = $vigencia->format('d/m/Y H:i');
    } catch (\Throwable $exception) {
        $vigenteHastaTexto = (string)$anuncioActual['vigente_hasta'];
    }
}

require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Consola administrativa',
    'subtitle' => 'Configura anuncios e información relevante para todo el equipo.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Consola'],
    ],
    'app' => AppNavigation::APP_CONFIGURACION,
    'route' => 'consola',
]);
?>

<section class="content">
  <div class="container-fluid">
    <?php if (is_array($resultadoConsola) && isset($resultadoConsola['message'])) : ?>
      <?php $tipoAlerta = ($resultadoConsola['status'] ?? '') === 'ok' ? 'success' : 'danger'; ?>
      <div class="alert alert-<?php echo $tipoAlerta; ?>">
        <?php echo htmlspecialchars($resultadoConsola['message']); ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bullhorn me-2"></i>Anuncios</h3>
      </div>
      <div class="card-body">
        <p class="text-muted">Publica mensajes que se mostrarán en el panel de control para todos los usuarios. El anuncio se ocultará automáticamente cuando expire su vigencia.</p>
        <form method="post" class="mb-4">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="accion_consola" value="crear_anuncio">
          <div class="mb-3">
            <label class="form-label" for="mensajeAnuncio">Mensaje</label>
            <textarea class="form-control" id="mensajeAnuncio" name="mensaje" rows="4" maxlength="500" required><?php echo isset($anuncioActual['mensaje']) ? htmlspecialchars($anuncioActual['mensaje']) : ''; ?></textarea>
            <div class="form-text">Máximo 500 caracteres. Se respetan saltos de línea.</div>
          </div>
          <div class="mb-3">
            <span class="form-label d-block">Mostrar anuncio en</span>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="destinos[]" id="destinoDashboard" value="dashboard" <?php echo $destinosSeleccionados['dashboard'] ? 'checked' : ''; ?>>
              <label class="form-check-label" for="destinoDashboard">Panel de inicio (index.php)</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="destinos[]" id="destinoPopup" value="popup" <?php echo $destinosSeleccionados['popup'] ? 'checked' : ''; ?>>
              <label class="form-check-label" for="destinoPopup">Notificación emergente</label>
            </div>
            <div class="form-text">Selecciona al menos una opción para definir dónde se mostrará.</div>
          </div>

          <div class="row g-3 align-items-end">
            <div class="col-sm-4 col-md-3">
              <label class="form-label" for="duracionAnuncio">Duración (minutos)</label>
              <input type="number" class="form-control" id="duracionAnuncio" name="duracion_minutos" min="5" max="1440" step="5" value="60" required>
              <div class="form-text">Entre 5 minutos y 24 horas.</div>
            </div>
            <div class="col-sm-8 col-md-9 text-sm-end">
              <button type="submit" class="btn btn-primary">Publicar anuncio</button>
            </div>
          </div>
        </form>

        <?php if ($anuncioActual) : ?>
          <div class="callout callout-info">
            <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Anuncio activo</h5>
            <p class="mb-2"><?php echo nl2br(htmlspecialchars($anuncioActual['mensaje'] ?? '')); ?></p>
            <?php if ($vigenteHastaTexto !== '') : ?>
              <p class="text-muted mb-3">Vigente hasta: <?php echo htmlspecialchars($vigenteHastaTexto); ?></p>
            <?php endif; ?>
            <form method="post" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="accion_consola" value="finalizar_anuncio">
              <input type="hidden" name="anuncio_id" value="<?php echo (int)($anuncioActual['id'] ?? 0); ?>">
              <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-ban me-1"></i>Cerrar anuncio</button>
            </form>
          </div>
        <?php else : ?>
          <div class="alert alert-light border">No hay anuncios activos actualmente.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h3 class="card-title mb-0"><i class="fas fa-list me-2"></i>Anuncios registrados</h3>
        <span class="text-muted small">Se muestran los 25 anuncios más recientes.</span>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($anunciosDisponibles)) : ?>
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th scope="col">Mensaje</th>
                  <th scope="col" class="text-nowrap">Estado</th>
                  <th scope="col" class="text-nowrap">Vigente hasta</th>
                  <th scope="col" class="text-nowrap">Creado</th>
                  <th scope="col" class="text-nowrap">Destino</th>
                  <th scope="col" class="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($anunciosDisponibles as $anuncio) : ?>
                  <?php
                  $vigenciaTexto = 'Sin fecha';
                  $vigenciaIso = '';
                  if (!empty($anuncio['vigente_hasta'])) {
                      try {
                          $vigenciaObj = new \DateTimeImmutable($anuncio['vigente_hasta']);
                          $vigenciaTexto = $vigenciaObj->format('d/m/Y H:i');
                          $vigenciaIso = $vigenciaObj->format('Y-m-d\\TH:i');
                      } catch (\Throwable $exception) {
                          $vigenciaTexto = (string)$anuncio['vigente_hasta'];
                          $vigenciaIso = (string)$anuncio['vigente_hasta'];
                      }
                  }

                  $creadoTexto = '';
                  if (!empty($anuncio['creado_en'])) {
                      try {
                          $creadoObj = new \DateTimeImmutable($anuncio['creado_en']);
                          $creadoTexto = $creadoObj->format('d/m/Y H:i');
                      } catch (\Throwable $exception) {
                          $creadoTexto = (string)$anuncio['creado_en'];
                      }
                  }

                  $activo = (int)($anuncio['activo'] ?? 0) === 1;
                  $estadoBadge = $activo ? 'success' : 'secondary';
                  $estadoTexto = $activo ? 'Activo' : 'Finalizado';
                  $mostrarDashboard = (int)($anuncio['mostrar_en_dashboard'] ?? 0) === 1;
                  $mostrarPopup = (int)($anuncio['mostrar_en_popup'] ?? 0) === 1;
                  $destinosEtiquetas = [];
                  if ($mostrarDashboard) {
                      $destinosEtiquetas[] = '<span class="badge bg-primary">Panel</span>';
                  }
                  if ($mostrarPopup) {
                      $destinosEtiquetas[] = '<span class="badge bg-warning text-dark">Notificación</span>';
                  }
                  if (empty($destinosEtiquetas)) {
                      $destinosEtiquetas[] = '<span class="badge bg-secondary">Sin destino</span>';
                  }
                  ?>
                  <tr>
                    <td class="align-middle">
                      <div class="text-break" style="max-width: 420px; white-space: pre-wrap;"><?php echo htmlspecialchars($anuncio['mensaje']); ?></div>
                    </td>
                    <td class="align-middle text-nowrap"><span class="badge bg-<?php echo $estadoBadge; ?>"><?php echo $estadoTexto; ?></span></td>
                    <td class="align-middle text-nowrap"><?php echo htmlspecialchars($vigenciaTexto); ?></td>
                    <td class="align-middle text-nowrap"><?php echo htmlspecialchars($creadoTexto); ?></td>
                    <td class="align-middle text-nowrap"><?php echo implode(' ', $destinosEtiquetas); ?></td>
                    <td class="align-middle text-center">
                      <div class="d-flex justify-content-center flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarAnuncio" data-id="<?php echo (int)$anuncio['id']; ?>" data-mensaje="<?php echo htmlspecialchars($anuncio['mensaje'], ENT_QUOTES); ?>" data-vigencia="<?php echo htmlspecialchars($vigenciaIso); ?>" data-activo="<?php echo $activo ? '1' : '0'; ?>" data-dashboard="<?php echo $mostrarDashboard ? '1' : '0'; ?>" data-popup="<?php echo $mostrarPopup ? '1' : '0'; ?>">
                          <i class="fas fa-edit me-1"></i>Editar
                        </button>
                        <?php if ($activo) : ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="accion_consola" value="finalizar_anuncio">
                            <input type="hidden" name="anuncio_id" value="<?php echo (int)$anuncio['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Deseas finalizar este anuncio?');">
                              <i class="fas fa-stop-circle me-1"></i>Finalizar
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else : ?>
          <div class="p-4">
            <div class="alert alert-light border mb-0">Aún no se han registrado anuncios en la plataforma.</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="modalEditarAnuncio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="formEditarAnuncio">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="accion_consola" value="editar_anuncio">
        <input type="hidden" name="anuncio_id" id="anuncioEditarId">
        <input type="hidden" name="anuncio_activo" value="0">
        <div class="modal-header">
          <h5 class="modal-title">Editar anuncio</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="anuncioEditarMensaje">Mensaje</label>
            <textarea class="form-control" name="mensaje" id="anuncioEditarMensaje" rows="4" maxlength="500" required></textarea>
            <div class="form-text">Máximo 500 caracteres.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="anuncioEditarVigencia">Vigente hasta</label>
            <input type="datetime-local" class="form-control" name="vigente_hasta" id="anuncioEditarVigencia">
            <div class="form-text">Deja vacío para conservar la vigencia actual.</div>
          </div>
          <div class="mb-3">
            <span class="form-label d-block">Mostrar anuncio en</span>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="destinos[]" id="anuncioEditarDestinoDashboard" value="dashboard" checked>
              <label class="form-check-label" for="anuncioEditarDestinoDashboard">Panel de inicio (index.php)</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="destinos[]" id="anuncioEditarDestinoPopup" value="popup">
              <label class="form-check-label" for="anuncioEditarDestinoPopup">Notificación emergente</label>
            </div>
            <div class="form-text">Selecciona al menos una opción.</div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" name="anuncio_activo" id="anuncioEditarActivo">
            <label class="form-check-label" for="anuncioEditarActivo">
              Mantener anuncio activo
            </label>
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

<script>
  (function configurarModalAnuncios() {
    const modal = document.getElementById('modalEditarAnuncio');
    if (!modal) {
      return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
      const boton = event.relatedTarget;
      if (!boton) {
        return;
      }

      const anuncioId = boton.getAttribute('data-id') || '';
      const mensaje = boton.getAttribute('data-mensaje') || '';
      const vigencia = boton.getAttribute('data-vigencia') || '';
      const activo = boton.getAttribute('data-activo') === '1';
      const destinoDashboard = boton.getAttribute('data-dashboard') === '1';
      const destinoPopup = boton.getAttribute('data-popup') === '1';

      const inputId = document.getElementById('anuncioEditarId');
      const textareaMensaje = document.getElementById('anuncioEditarMensaje');
      const inputVigencia = document.getElementById('anuncioEditarVigencia');
      const checkboxActivo = document.getElementById('anuncioEditarActivo');
      const checkboxDestinoDashboard = document.getElementById('anuncioEditarDestinoDashboard');
      const checkboxDestinoPopup = document.getElementById('anuncioEditarDestinoPopup');

      if (inputId) inputId.value = anuncioId;
      if (textareaMensaje) textareaMensaje.value = mensaje;
      if (inputVigencia) inputVigencia.value = vigencia;
      if (checkboxActivo) checkboxActivo.checked = activo;
      if (checkboxDestinoDashboard) checkboxDestinoDashboard.checked = destinoDashboard;
      if (checkboxDestinoPopup) checkboxDestinoPopup.checked = destinoPopup;
    });

    modal.addEventListener('hidden.bs.modal', function () {
      const form = document.getElementById('formEditarAnuncio');
      if (form) {
        form.reset();
      }
    });
  })();
</script>
