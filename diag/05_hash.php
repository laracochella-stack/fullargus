<?php
header('Content-Type: text/plain; charset=utf-8');
$f = __DIR__ . '/tmp/test_template.docx';
if (!is_file($f)) { die("No existe $f\n"); }
printf("size=%d bytes\nmd5=%s\n", filesize($f), md5_file($f));
