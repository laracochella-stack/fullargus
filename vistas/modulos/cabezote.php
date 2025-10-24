<?php
/**
 * Cabezote (barra superior) de la aplicación.
 */
?>
<nav class="main-header navbar navbar-expand-lg navbar-white navbar-light ag-top-navbar">
  <div class="container-fluid">
    <a class="navbar-brand fw-semibold" href="index.php?ruta=inicio">
      <i class="fas fa-cubes me-2"></i>
      Argus Apps
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#agTopNavbar" aria-controls="agTopNavbar" aria-expanded="false" aria-label="Mostrar navegación">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="agTopNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center">
        <li class="nav-item me-lg-2">
          <button type="button" class="btn btn-outline-primary ag-app-launcher" data-ag-app-launcher="true">
            <i class="fas fa-th-large me-1"></i>
            Aplicaciones
          </button>
        </li>
      </ul>
      <form class="d-none d-lg-flex align-items-center me-3" role="search" data-ag-app-search-form>
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input type="search" class="form-control" placeholder="Buscar aplicación" aria-label="Buscar aplicación" autocomplete="off" data-ag-app-filter="grid drawer">
        </div>
      </form>
      <ul class="navbar-nav ms-auto align-items-lg-center">
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
    </div>
  </div>
</nav>
