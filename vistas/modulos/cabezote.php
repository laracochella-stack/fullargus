<?php
/**
 * Cabezote (barra superior) de la aplicación.
 */
?>
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <!-- Izquierda: botón para ocultar/mostrar sidebar -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
    </li>
  </ul>
  <!-- Derecha: notificaciones y menú de usuario -->
  <ul class="navbar-nav ms-auto">
    <?php $notificacionesActivas = !empty($_SESSION['notificaciones_activas']); ?>
    <li class="nav-item dropdown<?php echo $notificacionesActivas ? '' : ' notificaciones-desactivadas'; ?>" id="dropdownNotificaciones" data-notifications-enabled="<?php echo $notificacionesActivas ? '1' : '0'; ?>">
        <a class="nav-link position-relative<?php echo $notificacionesActivas ? '' : ' text-muted'; ?>" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" aria-haspopup="true" aria-label="Abrir panel de notificaciones">
          <i class="far fa-bell" aria-hidden="true"></i>
          <span class="badge bg-danger navbar-badge" id="badgeNotificaciones" style="display:none;" aria-live="polite" aria-atomic="true">0</span>
          <span class="visually-hidden" id="textoBadgeNotificaciones">Notificaciones sin leer</span>
        </a>
        <div class="dropdown-menu dropdown-menu-end p-0 shadow notificaciones-dropdown" aria-labelledby="dropdownNotificaciones" data-bs-auto-close="outside">
          <div class="dropdown-header bg-light fw-bold d-flex align-items-center justify-content-between">
            <span>Notificaciones</span>
            <span class="badge bg-secondary" data-role="contador-notificaciones" style="display:none;">0</span>
          </div>
          <div id="listaNotificaciones" class="list-group list-group-flush" role="list" aria-live="polite" aria-busy="false">
            <div class="list-group-item text-center text-muted small" id="notificacionesVacio" data-default-text="Sin notificaciones pendientes" data-disabled-text="Notificaciones desactivadas. Actívalas desde tu perfil." role="listitem">
              <?php echo $notificacionesActivas ? 'Sin notificaciones pendientes' : 'Notificaciones desactivadas. Actívalas desde tu perfil.'; ?>
            </div>
          </div>
          <div class="dropdown-footer text-center py-2">
            <a href="index.php?ruta=solicitudes" class="text-decoration-none">Ver solicitudes</a>
          </div>
        </div>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button">
        <?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? ($_SESSION['username'] ?? 'Usuario')); ?>
        <i class="far fa-user-circle"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-end">
        <li>
          <a class="dropdown-item" href="index.php?ruta=perfil"><i class="fas fa-user-cog me-2"></i>Mi perfil</a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="index.php?ruta=salir"><i class="fas fa-sign-out-alt me-2"></i>Salir</a></li>
      </ul>
    </li>
  </ul>
</nav>