<?php
require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Página no encontrada',
    'subtitle' => 'El recurso solicitado no está disponible o fue movido.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Error 404'],
    ],
]);
?>
<section class="content">
  <div class="container-fluid">
    <div class="error-page">
      <h2 class="headline text-warning"> 404</h2>
      <div class="error-content">
        <h3><i class="fas fa-exclamation-triangle text-warning"></i> ¡Oops! Página no encontrada.</h3>
        <p>La página que busca no existe.</p>
        <a href="index.php?ruta=inicio" class="btn btn-primary">Volver al inicio</a>
      </div>
    </div>
  </div>
</section>
