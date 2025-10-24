<?php
use App\Controllers\ControladorContratos;
use App\Controllers\ControladorSolicitudes;
use App\Controllers\ControladorUsuarios;

if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
    echo '<div class="alert alert-warning m-3">Debe iniciar sesión para acceder a su perfil.</div>';
    return;
}

$mensajePreferencias = ControladorUsuarios::ctrActualizarPreferenciasNotificaciones();
$mensajePassword = ControladorUsuarios::ctrActualizarPassword();
$usuarioId = (int)($_SESSION['id'] ?? 0);
$perfil = ControladorUsuarios::ctrObtenerPerfilUsuario($usuarioId) ?? [];
$conteoContratos = ControladorContratos::ctrContarContratosPorUsuario($usuarioId);
$conteoSolicitudes = ControladorSolicitudes::ctrContarSolicitudesPorEstado($usuarioId);

foreach ([$mensajePreferencias, $mensajePassword] as $mensajeFlash) {
    if ($mensajeFlash && isset($mensajeFlash['tipo'], $mensajeFlash['mensaje'])) {
        $icon = $mensajeFlash['tipo'] === 'success' ? 'success' : 'error';
        $titulo = $mensajeFlash['tipo'] === 'success' ? 'Éxito' : 'Aviso';
        echo "<script>Swal.fire({icon:'{$icon}', title:'{$titulo}', text:'" . addslashes($mensajeFlash['mensaje']) . "'});</script>";
    }
}

$contratosActivos = (int)($conteoContratos['activos'] ?? 0);
$contratosCancelados = (int)($conteoContratos['cancelados'] ?? 0);
$contratosTotales = (int)($conteoContratos['total'] ?? ($contratosActivos + $contratosCancelados));

$solicitudesIngresadas = (int)($conteoSolicitudes['borrador'] ?? 0);
$solicitudesEnviadas = (int)($conteoSolicitudes['enviada'] ?? 0);
$solicitudesRevision = (int)($conteoSolicitudes['en_revision'] ?? 0);
$solicitudesAprobadas = (int)($conteoSolicitudes['aprobada'] ?? 0);
$solicitudesCanceladas = (int)($conteoSolicitudes['cancelada'] ?? 0);

$nombreCompleto = htmlspecialchars($_SESSION['nombre_completo'] ?? ($perfil['nombre_completo'] ?? ''), ENT_QUOTES);
$correo = htmlspecialchars($_SESSION['email'] ?? ($perfil['email'] ?? ''), ENT_QUOTES);
$usuario = htmlspecialchars($_SESSION['username'] ?? ($perfil['username'] ?? ''), ENT_QUOTES);
$permiso = htmlspecialchars($_SESSION['permission'] ?? ($perfil['permission'] ?? ''), ENT_QUOTES);
$notificacionesActivas = !empty($_SESSION['notificaciones_activas']);

require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Mi perfil',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Mi perfil'],
    ],
]);
?>
<section class="content">
  <div class="container-fluid">
    <div class="row g-3">
      <div class="col-xl-4 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-gradient-primary text-white">
            <h3 class="card-title mb-0"><i class="fas fa-id-badge me-2"></i>Información personal</h3>
          </div>
          <div class="card-body">
            <ul class="list-group list-group-flush">
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="text-muted"><i class="fas fa-user me-2"></i>Nombre completo</span>
                <span class="fw-semibold"><?php echo $nombreCompleto ?: '—'; ?></span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="text-muted"><i class="fas fa-at me-2"></i>Usuario</span>
                <span class="fw-semibold"><?php echo $usuario ?: '—'; ?></span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="text-muted"><i class="fas fa-envelope me-2"></i>Correo</span>
                <span class="fw-semibold text-truncate" style="max-width: 180px;" title="<?php echo $correo; ?>"><?php echo $correo ?: '—'; ?></span>
              </li>
              <!--<li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="text-muted"><i class="fas fa-user-shield me-2"></i>Permiso</span>
                <span class="badge bg-secondary text-uppercase"><?php echo $permiso ?: '—'; ?></span>
              </li>-->
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="text-muted"><i class="fas fa-bell me-2"></i>Notificaciones</span>
                <?php if ($notificacionesActivas) : ?>
                  <span class="badge bg-success">Activas</span>
                <?php else : ?>
                  <span class="badge bg-secondary">Inactivas</span>
                <?php endif; ?>
              </li>
              <li class="list-group-item">
                <small class="text-muted">Contraseñas seguras deben incluir al menos una letra mayúscula, un número y un carácter especial.</small>
              </li>
            </ul>
            <form method="post" class="mt-3">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="actualizarPreferenciasNotificaciones" value="1">
              <input type="hidden" name="notificaciones_estado" value="<?php echo $notificacionesActivas ? '0' : '1'; ?>">
              <button type="submit" class="btn <?php echo $notificacionesActivas ? 'btn-outline-warning' : 'btn-warning'; ?> w-100">
                <i class="fas fa-bell me-2"></i><?php echo $notificacionesActivas ? 'Desactivar notificaciones' : 'Activar notificaciones'; ?>
              </button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-xl-8 col-lg-7">
        <div class="card shadow-sm h-100">
          <div class="card-header bg-gradient-secondary text-white">
            <h3 class="card-title mb-0"><i class="fas fa-key me-2"></i>Cambiar contraseña</h3>
          </div>
          <div class="card-body">
            <form method="post" action="" class="row g-3" id="formCambioPassword">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="cambiarPasswordUsuario" value="1">
              <div class="col-md-4">
                <label class="form-label fw-semibold">Contraseña actual</label>
                <div class="input-group input-group-sm password-toggle-group">
                  <span class="input-group-text"><i class="fas fa-lock"></i></span>
                  <input type="password" name="password_actual" class="form-control form-control-sm" required autocomplete="current-password" data-password-input>
                  <button class="btn btn-outline-secondary" type="button" data-password-toggle aria-label="Mostrar contraseña">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Nueva contraseña</label>
                <div class="input-group input-group-sm password-toggle-group">
                  <span class="input-group-text"><i class="fas fa-lock"></i></span>
                  <input type="password" name="password_nuevo" class="form-control form-control-sm" required minlength="8" pattern="(?=.*[A-ZÁÉÍÓÚÑ])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" autocomplete="new-password" data-password-input>
                  <button class="btn btn-outline-secondary" type="button" data-password-toggle aria-label="Mostrar contraseña">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Confirmar contraseña</label>
                <div class="input-group input-group-sm password-toggle-group">
                  <span class="input-group-text"><i class="fas fa-lock"></i></span>
                  <input type="password" name="password_confirmar" class="form-control form-control-sm" required minlength="8" autocomplete="new-password" data-password-input>
                  <button class="btn btn-outline-secondary" type="button" data-password-toggle aria-label="Mostrar contraseña">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col-12 text-end">
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Actualizar contraseña</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    <div class="row g-3 mt-2">
      <div class="col-md-4">
        <div class="small-box bg-success text-white">
          <div class="inner">
            <h3><?php echo $contratosActivos; ?></h3>
            <p>Contratos activos</p>
          </div>
          <div class="icon"><i class="fas fa-file-contract"></i></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="small-box bg-danger text-white">
          <div class="inner">
            <h3><?php echo $contratosCancelados; ?></h3>
            <p>Contratos cancelados</p>
          </div>
          <div class="icon"><i class="fas fa-ban"></i></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="small-box bg-primary text-white">
          <div class="inner">
            <h3><?php echo $contratosTotales; ?></h3>
            <p>Total de contratos</p>
          </div>
          <div class="icon"><i class="fas fa-clipboard-check"></i></div>
        </div>
      </div>
    </div>
    <div class="row g-3 mt-1">
      <?php
      $tarjetasSolicitudes = [
        ['titulo' => 'Solicitudes ingresadas', 'valor' => $solicitudesIngresadas, 'color' => 'secondary', 'icon' => 'fa-inbox'],
        ['titulo' => 'Solicitudes enviadas', 'valor' => $solicitudesEnviadas, 'color' => 'info', 'icon' => 'fa-paper-plane'],
        ['titulo' => 'En revisión', 'valor' => $solicitudesRevision, 'color' => 'warning', 'icon' => 'fa-search'],
        ['titulo' => 'Aprobadas', 'valor' => $solicitudesAprobadas, 'color' => 'success', 'icon' => 'fa-check-circle'],
        ['titulo' => 'Canceladas', 'valor' => $solicitudesCanceladas, 'color' => 'danger', 'icon' => 'fa-times-circle'],
      ];
      foreach ($tarjetasSolicitudes as $info) :
      ?>
        <div class="col-md-4 col-lg-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
              <div class="me-3 text-<?php echo $info['color']; ?>">
                <i class="fas <?php echo $info['icon']; ?> fa-2x"></i>
              </div>
              <div>
                <div class="h4 mb-0 fw-bold"><?php echo $info['valor']; ?></div>
                <small class="text-muted"><?php echo $info['titulo']; ?></small>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
