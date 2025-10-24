<?php
use App\Controllers\ControladorConsola;
use App\Support\AppNavigation;

$anunciosVigentes = ControladorConsola::ctrObtenerAnunciosVigentes('dashboard');
$anunciosCallout = [];
foreach ($anunciosVigentes as $anuncioVigente) {
    $vigenciaTexto = '';
    $vigenciaIso = '';
    if (!empty($anuncioVigente['vigente_hasta'])) {
        try {
            $anuncioFecha = new \DateTimeImmutable($anuncioVigente['vigente_hasta']);
            $vigenciaTexto = $anuncioFecha->format('d/m/Y H:i');
            $vigenciaIso = $anuncioFecha->format(\DateTimeImmutable::ATOM);
        } catch (\Throwable $e) {
            $vigenciaTexto = (string)$anuncioVigente['vigente_hasta'];
            $vigenciaIso = (string)$anuncioVigente['vigente_hasta'];
        }
    }

    $anunciosCallout[] = [
        'id' => (int)($anuncioVigente['id'] ?? 0),
        'mensaje' => (string)($anuncioVigente['mensaje'] ?? ''),
        'vigencia_texto' => $vigenciaTexto,
        'vigencia_iso' => $vigenciaIso,
    ];
}

$appCards = AppNavigation::getAppCards($_SESSION ?? []);

require_once 'vistas/partials/app_card.php';
?>
<section class="content">
  <div class="container-fluid py-3 py-md-4">
    <?php foreach ($anunciosCallout as $anuncio) : ?>
      <div class="alert alert-warning d-flex align-items-start gap-3 flex-column flex-md-row" role="status" id="anuncio-general-<?php echo (int)$anuncio['id']; ?>" data-expira="<?php echo htmlspecialchars($anuncio['vigencia_iso'], ENT_QUOTES, 'UTF-8'); ?>">
        <div class="flex-shrink-0">
          <span class="ag-app-alert-icon"><i class="fas fa-bullhorn"></i></span>
        </div>
        <div class="flex-grow-1">
          <h5 class="alert-heading mb-2">Aviso general</h5>
          <p class="mb-1"><?php echo nl2br(htmlspecialchars($anuncio['mensaje'], ENT_QUOTES, 'UTF-8')); ?></p>
          <?php if ($anuncio['vigencia_texto'] !== '') : ?>
            <p class="text-muted mb-0">Disponible hasta: <?php echo htmlspecialchars($anuncio['vigencia_texto'], ENT_QUOTES, 'UTF-8'); ?></p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="mb-4">
      <h1 class="h3 fw-semibold mb-0">Aplicaciones</h1>
    </div>

    <div class="row g-4" data-ag-app-grid="grid">
      <?php foreach ($appCards as $card) : ?>
        <?php ag_render_app_card($card); ?>
      <?php endforeach; ?>
    </div>

    <div class="ag-app-grid-empty text-center text-muted py-5" data-ag-app-empty="grid" <?php echo empty($appCards) ? '' : 'hidden'; ?>>
      <div class="display-6 mb-3"><i class="fas fa-circle-notch"></i></div>
      <p class="mb-0">No hay aplicaciones disponibles para tu perfil.</p>
    </div>
  </div>
</section>
