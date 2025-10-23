<?php
use App\Controllers\ControladorUsuarios;

$csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
ControladorUsuarios::ctrRegistrarUsuario();
?>
<div class="ag-login-page d-flex align-items-center justify-content-center py-5 py-md-0">
  <div class="login-box w-100" style="max-width: 520px;">
    <div class="text-center mb-4">
      <span class="d-block text-uppercase text-muted small">Gestor de contratos</span>
      <h1 class="h4 fw-bold mt-2 mb-0">Crear cuenta</h1>
      <p class="text-muted small mb-0">Completa el formulario para registrarte y comenzar a usar la plataforma.</p>
    </div>
    <div class="card shadow-sm ag-login-card">
      <div class="card-body">
        <form method="post" action="" class="row g-3" data-password-module novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
          <input type="hidden" name="contexto_registro" value="publico">
          <input type="hidden" name="nuevoUsuario" value="" data-email-username-target>
          <input type="hidden" name="nuevoRol" value="user">
          <div class="col-12">
            <label for="registroNombreCompleto" class="form-label small fw-semibold">Nombre completo</label>
            <input type="text" class="form-control form-control-sm" id="registroNombreCompleto" name="nuevoNombreCompleto" placeholder="Nombre(s) y apellidos" required minlength="3" autocomplete="name">
          </div>
          <div class="col-12">
            <label for="registroEmail" class="form-label small fw-semibold">Correo electrónico</label>
            <input type="email" class="form-control form-control-sm" id="registroEmail" name="nuevoEmail" placeholder="correo@ejemplo.com" required autocomplete="email" data-sync-email-to-username>
            <div class="form-text">Usa un correo activo. Será tu usuario para iniciar sesión.</div>
          </div>
          <div class="col-12">
            <label for="registroPassword" class="form-label small fw-semibold">Contraseña</label>
            <div class="input-group input-group-sm password-toggle-group">
              <span class="input-group-text"><span class="fas fa-lock"></span></span>
              <input type="password" class="form-control form-control-sm" id="registroPassword" name="nuevoPassword" placeholder="Contraseña" required minlength="8" autocomplete="new-password" data-password-input data-password-strength>
              <button class="btn btn-outline-secondary" type="button" data-password-toggle aria-label="Mostrar contraseña">
                <span class="fas fa-eye"></span>
              </button>
            </div>
          </div>
          <div class="col-12">
            <label for="registroPasswordConfirm" class="form-label small fw-semibold">Confirmar contraseña</label>
            <div class="input-group input-group-sm password-toggle-group">
              <span class="input-group-text"><span class="fas fa-lock"></span></span>
              <input type="password" class="form-control form-control-sm" id="registroPasswordConfirm" name="repetirPassword" placeholder="Repite tu contraseña" required minlength="8" autocomplete="new-password" data-password-input data-password-confirm>
              <button class="btn btn-outline-secondary" type="button" data-password-toggle aria-label="Mostrar contraseña">
                <span class="fas fa-eye"></span>
              </button>
            </div>
            <div class="form-text text-danger d-none" data-password-match-feedback>Las contraseñas deben coincidir.</div>
          </div>
          <div class="col-12">
            <div class="ag-password-guidelines" data-password-guidelines>
              <div class="d-flex align-items-center mb-2">
                <i class="fas fa-shield-alt text-primary me-2"></i>
                <h6 class="mb-0">Requisitos de seguridad</h6>
              </div>
              <div class="alert alert-warning d-none py-2 px-3 small" role="alert" data-password-alert>
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span data-password-alert-text>Ingresa una contraseña para ver los requisitos.</span>
              </div>
              <div class="progress ag-password-progress" role="progressbar" aria-label="Fuerza de la contraseña">
                <div class="progress-bar" data-password-meter-bar style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
              <p class="small text-muted mt-2 mb-1" data-password-strength-label>Ingresa una contraseña para evaluar su seguridad.</p>
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
          <div class="col-12 d-grid">
            <button type="submit" class="btn btn-primary">Crear cuenta</button>
          </div>
          <div class="col-12 text-center">
            <p class="small mb-0">¿Ya tienes cuenta? <a href="index.php?ruta=login" class="fw-semibold">Inicia sesión</a></p>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
