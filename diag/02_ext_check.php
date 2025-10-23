<?php
header('Content-Type: text/plain; charset=utf-8');
printf("ZipArchive: %s\n", class_exists('ZipArchive') ? 'OK' : 'FALTA');
printf("xml: %s\n", extension_loaded('xml') ? 'OK' : 'FALTA');
printf("dom: %s\n", extension_loaded('dom') ? 'OK' : 'FALTA');
printf("xmlwriter: %s\n", extension_loaded('xmlwriter') ? 'OK' : 'FALTA');
printf("mbstring: %s\n", extension_loaded('mbstring') ? 'OK' : 'FALTA');

$docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$paths = [
  $docroot.'/tmp',
  __DIR__.'/tmp',
];
foreach ($paths as $p) {
  @mkdir($p, 0775, true);
  printf("%s => %s, writable=%s\n", $p, is_dir($p)?'OK':'NO', is_writable($p)?'sí':'no');
}
