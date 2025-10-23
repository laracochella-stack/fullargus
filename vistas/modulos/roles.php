<?php
use App\Controllers\ControladorUsuarios;
/**
 * Módulo de gestión de usuarios/roles.
 * Permite al administrador crear nuevos usuarios y ver los existentes.
 */
// Procesar alta de nuevo usuario si se envió el formulario
ControladorUsuarios::ctrRegistrarUsuario();
ControladorUsuarios::ctrEditarUsuario();
// Procesar eliminación de usuarios
ControladorUsuarios::ctrEliminarUsuario();
// Obtener lista de usuarios para mostrar
$usuarios = ControladorUsuarios::ctrMostrarUsuarios();
// Restringir acceso a este módulo sólo para administradores u owners
if (!in_array($_SESSION['permission'] ?? '', ['admin','owner'], true)) {
    echo '<div class="alert alert-danger m-3">No tiene permisos para acceder a este módulo.</div>';
    return;
}
$permisoSesion = $_SESSION['permission'] ?? '';
$rolesDisponibles = [
    'user' => 'Usuario',
    'moderator' => 'Moderador',
    'senior' => 'Senior',
    'owner' => 'Owner',
    'admin' => 'Administrador',
];
$puedeCrearAdmin = $permisoSesion === 'admin';

require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Usuarios y roles',
    'subtitle' => 'Administra cuentas de acceso y niveles de permiso.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Usuarios y roles'],
    ],
]);
?>
<section class="content">
  <div class="container-fluid">
    <!-- Formulario para crear un nuevo usuario -->
    <div class="ag-form-card card shadow-sm mb-4">
      <div class="card-header">
        <div class="ag-card-header">
          <div class="ag-card-header-text">
            <h5 class="card-title mb-0"><i class="fas fa-user-plus me-2 text-primary"></i>Crear nuevo usuario</h5>
            <hr>
            <p class="card-subtitle text-muted mb-0">Completa los datos básicos para otorgar acceso a un nuevo colaborador.</p>
          </div>
        </div>
      </div>
      <div class="card-body">
        <form method="post" action="" class="row g-3" id="formCrearUsuario" data-password-module>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="contexto_registro" value="gestion">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Nombre completo</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="fas fa-id-card"></i></span>
              <input type="text" name="nuevoNombreCompleto" class="form-control form-control-sm" placeholder="Nombre(s) y apellidos" required minlength="3">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Nombre de usuario</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="fas fa-user"></i></span>
              <input type="text" name="nuevoUsuario" class="form-control form-control-sm" placeholder="correo@ejemplo.com" pattern="^[A-Za-z0-9_.@-]{4,}$" required data-allow-lowercase>
            </div>
            <div class="form-text">Debe tener al menos 4 caracteres. Puede incluir letras, números, @, punto, guion o guion bajo.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Correo electrónico</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="fas fa-envelope"></i></span>
              <input type="email" name="nuevoEmail" class="form-control form-control-sm" placeholder="correo@ejemplo.com" required>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Contraseña</label>
            <div class="input-group input-group-sm password-toggle-group">
              <span class="input-group-text"><i class="fas fa-lock"></i></span>
              <input type="password" name="nuevoPassword" class="form-control form-control-sm" required minlength="8" pattern="(?=.*[A-ZÁÉÍÓÚÑ])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" data-password-input data-password-strength>
              <button class="btn btn-outline-secondary" type="button" data-password-toggle aria-label="Mostrar contraseña">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Confirmar contraseña</label>
            <div class="input-group input-group-sm password-toggle-group">
              <span class="input-group-text"><i class="fas fa-lock"></i></span>
              <input type="password" name="repetirPassword" class="form-control form-control-sm" required minlength="8" data-password-input data-password-confirm>
              <button class="btn btn-outline-secondary" type="button" data-password-toggle aria-label="Mostrar contraseña">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div class="form-text text-danger d-none" data-password-match-feedback>Las contraseñas deben coincidir.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Permiso</label>
            <select name="nuevoRol" class="form-select form-select-sm" required>
              <?php foreach ($rolesDisponibles as $valor => $etiqueta) : ?>
                <?php if (!$puedeCrearAdmin && $valor === 'admin') { continue; } ?>
                <option value="<?php echo $valor; ?>"><?php echo $etiqueta; ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Configura el nivel de acceso adecuado. Las contraseñas deben cumplir con los requisitos de seguridad.</div>
          </div>
          <div class="col-4">
            <div class="ag-password-guidelines" data-password-guidelines>
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-shield-alt text-primary me-2"></i>
                <h6 class="mb-0">Requisitos de contraseña</h6>
              </div>
              <div class="alert alert-warning d-none py-2 px-3 small" role="alert" data-password-alert>
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span data-password-alert-text>Ingresa una contraseña para ver los requisitos obligatorios.</span>
              </div>
              <div class="progress ag-password-progress" role="progressbar" aria-label="Fuerza de la contraseña">
                <div class="progress-bar" data-password-meter-bar style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
              <p class="small text-muted mt-2 mb-1" data-password-strength-label>La seguridad de la contraseña se evaluará en tiempo real.</p>
              <ul class="ag-password-requirements list-unstyled small mb-0" data-password-requirements>
                <li data-requirement="length" data-requirement-message="Incluye al menos 8 caracteres.">
                  <i class="fas fa-circle me-2 text-muted"></i>
                  <span>Al menos 8 caracteres</span>
                </li>
                <li data-requirement="uppercase" data-requirement-message="Agrega al menos una letra mayúscula.">
                  <i class="fas fa-circle me-2 text-muted"></i>
                  <span>Una letra mayúscula</span>
                </li>
                <li data-requirement="number" data-requirement-message="Incluye al menos un número.">
                  <i class="fas fa-circle me-2 text-muted"></i>
                  <span>Al menos un número</span>
                </li>
                <li data-requirement="special" data-requirement-message="Agrega un carácter especial (por ejemplo !, #, ?).">
                  <i class="fas fa-circle me-2 text-muted"></i>
                  <span>Un carácter especial</span>
                </li>
              </ul>
            </div>
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar usuario</button>
          </div>
        </form>
      </div>
    </div>
    <!-- Tabla de usuarios existentes -->
    <div class="ag-form-card card shadow-sm">
      <div class="card-header">
        <div class="ag-card-header">
          <div class="ag-card-header-text">
            <h5 class="card-title mb-0"><i class="fas fa-users me-2 text-primary"></i>Usuarios registrados</h5>
            <hr>
            <p class="card-subtitle text-muted mb-0">Consulta, edita o desactiva cuentas de acceso existentes.</p>
          </div>
          <span class="badge rounded-pill bg-light text-dark fw-semibold ag-card-pill"><?php echo count($usuarios); ?> usuarios</span>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle ag-data-table" id="tablaUsuarios" data-dt-resource="usuarios">
            <thead>
              <tr>
                <th scope="col" class="control" data-priority="1"></th>
                <th scope="col" class="all">ID</th>
                <th scope="col" class="all">Nombre</th>
                <th scope="col" class="min-tablet">Usuario</th>
                <th scope="col" class="min-tablet">Correo</th>
                <th scope="col" class="min-tablet">Permiso</th>
                <th scope="col" class="min-tablet">Notificaciones</th>
                <th scope="col" class="min-desktop">Fecha alta</th>
                <th scope="col" class="all no-sort text-center">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
$rolesEdicion = $rolesDisponibles;
$esAdminSesion = $permisoSesion === 'admin';
?>

<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="formEditarUsuario">
        <div class="modal-header bg-warning">
          <h5 class="modal-title">Editar usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="editarUsuario" value="1">
          <input type="hidden" name="usuario_id" id="editarUsuarioId">
          <input type="hidden" name="editarNotificaciones" id="editarNotificacionesHidden" value="0">
          <div class="mb-3">
            <label class="form-label">Nombre completo</label>
            <input type="text" class="form-control form-control-sm" name="editarNombreCompleto" id="editarNombreCompleto" required minlength="3">
          </div>
          <div class="mb-3">
            <label class="form-label">Correo electrónico</label>
            <input type="email" class="form-control form-control-sm" name="editarEmail" id="editarEmail" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Permiso</label>
            <select class="form-select form-select-sm" name="editarRol" id="editarRol" required data-session-es-admin="<?php echo $esAdminSesion ? '1' : '0'; ?>">
              <?php foreach ($rolesEdicion as $valor => $etiqueta) : ?>
                <option value="<?php echo $valor; ?>"<?php echo (!$esAdminSesion && $valor === 'admin') ? ' data-restricted-role="admin"' : ''; ?>><?php echo $etiqueta; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="editarNotificacionesSwitch">
            <label class="form-check-label" for="editarNotificacionesSwitch">Notificaciones de alertas activas</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
