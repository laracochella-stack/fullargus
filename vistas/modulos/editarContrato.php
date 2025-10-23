<?php
$contratoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$destino = $contratoId > 0
    ? 'index.php?ruta=crearContrato&contrato_id=' . $contratoId
    : 'index.php?ruta=contratos';

echo '<section class="content"><div class="container-fluid"><div class="alert alert-info">Redirigiendo al nuevo editor de contratos...</div></div></section>';
echo '<script>window.location.href = ' . json_encode($destino, JSON_UNESCAPED_SLASHES) . ';</script>';
