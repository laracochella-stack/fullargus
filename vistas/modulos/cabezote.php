<?php
/**
 * Cabezote (barra superior) de la aplicación.
 */
$currentRoute = $agCurrentRoute ?? ($_GET['ruta'] ?? 'inicio');
if (!is_string($currentRoute) || trim($currentRoute) === '') {
    $currentRoute = 'inicio';
}
$isInicio = $currentRoute === 'inicio';
$notificacionesActivas = !empty($_SESSION['notificaciones_activas']);
?>
<nav class="main-header navbar navbar-expand-lg navbar-white navbar-light ag-top-navbar">
  <div class="container-fluid">
    <div class="ag-top-navbar__shell">
      <?php if (!$isInicio) : ?>
        <a
          class="btn btn-outline-primary ag-icon-button ag-top-navbar__back"
          href="index.php?ruta=inicio"
          aria-label="Regresar al menú principal"
        >
          <span aria-hidden="true">&gt;</span>
        </a>
      <?php endif; ?>
      <a class="navbar-brand fw-semibold" href="index.php?ruta=inicio">
        <i class="fas fa-cubes me-2"></i>
        Argus Apps
      </a>
      <div class="ag-top-navbar__actions ms-auto">
        <?php if (!$isInicio) : ?>
          <button
            class="navbar-toggler d-inline-flex d-lg-none align-items-center justify-content-center"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#agTopNavbar"
            aria-controls="agTopNavbar"
            aria-expanded="false"
            aria-label="Mostrar navegación"
          >
            <span class="navbar-toggler-icon"></span>
          </button>
        <?php endif; ?>
        <div
          class="dropdown ag-top-navbar__notification<?php echo $notificacionesActivas ? '' : ' notificaciones-desactivadas'; ?>"
          id="dropdownNotificaciones"
          data-notifications-enabled="<?php echo $notificacionesActivas ? '1' : '0'; ?>"
        >
          <a
            class="nav-link position-relative<?php echo $notificacionesActivas ? '' : ' text-muted'; ?>"
            data-bs-toggle="dropdown"
            href="#"
            role="button"
            aria-expanded="false"
            aria-haspopup="true"
            aria-label="Abrir panel de notificaciones"
          >
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
        </div>
        <div class="dropdown ag-top-navbar__profile">
          <a class="nav-link d-flex align-items-center gap-2" data-bs-toggle="dropdown" href="#" role="button" aria-label="Abrir menú de usuario">
            <span class="ag-top-navbar__username"><?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? ($_SESSION['username'] ?? 'Usuario')); ?></span>
            <i class="far fa-user-circle" aria-hidden="true"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <a class="dropdown-item" href="index.php?ruta=perfil"><i class="fas fa-user-cog me-2"></i>Mi perfil</a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="index.php?ruta=salir"><i class="fas fa-sign-out-alt me-2"></i>Salir</a></li>
          </ul>
        </div>
      </div>
    </div>
    <?php if (!$isInicio) : ?>
      <div class="collapse navbar-collapse ag-top-navbar__collapse" id="agTopNavbar">
        <div class="ag-top-navbar__collapse-inner">
          <form class="ag-top-navbar__search" role="search" data-ag-app-search-form>
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
              <input type="search" class="form-control" placeholder="Buscar aplicación" aria-label="Buscar aplicación" autocomplete="off" data-ag-app-filter="grid drawer">
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</nav>
