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

require_once 'vistas/partials/content_header.php';
require_once 'vistas/partials/app_card.php';

ag_render_content_header([
    'title' => 'Aplicaciones',
    'subtitle' => 'Selecciona un módulo para comenzar a trabajar.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Aplicaciones'],
    ],
    'app' => AppNavigation::APP_INICIO,
    'route' => 'inicio',
]);
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

    <div class="ag-app-grid-toolbar d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
      <div>
        <h2 class="h5 mb-1">Explorar aplicaciones</h2>
        <p class="text-muted mb-0">Filtra por nombre o descripción para encontrar el módulo que necesitas.</p>
      </div>
      <div class="ag-app-grid-search">
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input type="search" class="form-control" placeholder="Buscar aplicación" aria-label="Buscar aplicación" autocomplete="off" data-ag-app-filter="grid">
        </div>
      </div>
    </div>

    <div class="row g-3" data-ag-app-grid="grid">
      <?php foreach ($appCards as $card) : ?>
        <?php ag_render_app_card($card); ?>
      <?php endforeach; ?>
    </div>

    <div class="ag-app-grid-empty text-center text-muted py-5" data-ag-app-empty="grid" <?php echo empty($appCards) ? '' : 'hidden'; ?>>
      <div class="display-6 mb-3"><i class="fas fa-circle-notch"></i></div>
      <p class="mb-2">No encontramos aplicaciones que coincidan con tu búsqueda.</p>
      <p class="small mb-0">Ajusta los filtros o verifica tus permisos de acceso.</p>
    </div>
  </div>
</section>
