<?php
require __DIR__ . '/../vendor/autoload.php'; // ajusta según tu estructura

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

// temp local (mismo dir diag/tmp)
$tmp = __DIR__ . '/tmp';
@mkdir($tmp, 0775, true);
Settings::setTempDir($tmp);

// crear docx simple
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText('Hola desde Hostinger (prueba mínima)');
$docx = $tmp . '/test_min.docx';
IOFactory::createWriter($phpWord, 'Word2007')->save($docx);

// salida
clearstatcache(true, $docx);
echo json_encode([
  'exists' => file_exists($docx),
  'size'   => file_exists($docx) ? filesize($docx) : 0,
  'path'   => str_replace($_SERVER['DOCUMENT_ROOT'].'/', '', $docx),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
