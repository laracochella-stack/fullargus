<?php
use App\Controllers\ControladorUsuarios;
/**
 * Módulo de inicio de sesión.
 */
$csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
?>
<div class="login-page ag-login-page d-flex align-items-center justify-content-center py-5 py-md-0">
  <div class="login-box w-100" style="max-width:360px;">
    <div class="text-center mb-4">
      <span class="d-block text-uppercase text-muted small">Gestor de contratos</span>
      <h1 class="h4 fw-bold mt-2 mb-0">Grupo Argus</h1>
    </div>
    <div class="card shadow-sm ag-login-card">
      <div class="card-body login-card-body">
        <p class="login-box-msg text-center text-muted">Inicia sesión para continuar</p>
        <form method="post" action="" class="needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
          <div class="mb-3">
            <label for="loginUsuario" class="form-label small fw-semibold">Usuario</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text"><span class="fas fa-user"></span></span>
              <input type="text" class="form-control form-control-sm" id="loginUsuario" name="ingUsuario" placeholder="Correo o usuario" autocomplete="username" required data-allow-lowercase>
            </div>
            <div class="form-text">Usa el usuario asignado o tu correo registrado.</div>
          </div>
          <div class="mb-4">
            <label for="loginPassword" class="form-label small fw-semibold">Contraseña</label>
            <div class="input-group input-group-sm password-toggle-group">
              <span class="input-group-text"><span class="fas fa-lock"></span></span>
              <input type="password" class="form-control form-control-sm" id="loginPassword" name="ingPassword" placeholder="Contraseña" autocomplete="current-password" required data-password-input>
              <button class="btn btn-outline-secondary" type="button" data-password-toggle aria-label="Mostrar contraseña">
                <span class="fas fa-eye"></span>
              </button>
            </div>
            <div class="form-text">La contraseña distingue mayúsculas y minúsculas.</div>
          </div>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Ingresar</button>
          </div>
        </form>
        <!--<p class="text-center small mt-3 mb-0">¿Aún no tienes cuenta? <a href="index.php?ruta=crearCuenta" class="fw-semibold">Crea una ahora</a></p>-->

        <?php
        // Llamar al controlador para procesar registro e inicio de sesión
        ControladorUsuarios::ctrRegistrarUsuario();
        ControladorUsuarios::ctrIngresoUsuario();
        ?>

      </div>
    </div>
  </div>
</div>
