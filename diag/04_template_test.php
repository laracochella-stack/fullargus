<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Settings;

$tmp = __DIR__ . '/tmp';
@mkdir($tmp, 0775, true);
Settings::setTempDir($tmp);

// AJUSTA esta ruta a una de tus .docx de plantilla en el servidor:
$plantilla = __DIR__ . '/../vistas/plantillas/tpl_68d6fc2fad4e2.docx'; 

if (!is_file($plantilla)) {
  die('No encuentro la plantilla: '.$plantilla);
}

try {
  $tpl = new TemplateProcessor($plantilla);
  $tpl->setValue('CLIENTE_NOMBRE', 'Prueba Hostinger');
  $out = $tmp . '/test_template.docx';
  $tpl->saveAs($out);
  clearstatcache(true, $out);
  echo json_encode([
    'ok'   => true,
    'size' => filesize($out),
    'file' => str_replace($_SERVER['DOCUMENT_ROOT'].'/', '', $out),
  ]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'err'=>$e->getMessage()]);
}
