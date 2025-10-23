<?php
/**
 * Pie de página
 */

$timezone = $_ENV['APP_TIMEZONE']
    ?? $_SERVER['APP_TIMEZONE']
    ?? getenv('APP_TIMEZONE')
    ?? 'America/Guatemala';

try {
    $clockDate = new DateTime('now', new DateTimeZone($timezone));
} catch (Exception $exception) {
    $clockDate = new DateTime('now');
    $timezone = 'UTC';
}

$clockDisplay = $clockDate->format('H:i');
?>
<footer class="main-footer text-center">
  <div class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-2">
    <small class="mb-0">&copy; <?php echo date('Y'); ?> Contratos Grupo Argus, Todos los derechos reservados Grupo Argus Pachuca</small>
    <span
      class="app-footer-clock text-muted"
      id="appFooterClock"
      data-app-timezone="<?php echo htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8'); ?>"
      aria-live="polite"
    >
      <i class="fa-regular fa-clock" aria-hidden="true"></i>
      <span data-clock-value><?php echo htmlspecialchars($clockDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
    </span>
  </div>
</footer>

