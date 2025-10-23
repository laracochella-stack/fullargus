<?php
$f = __DIR__ . '/tmp/test_template.docx'; // o tu ZIP final
if (!is_file($f)) { http_response_code(404); exit; }
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Length: ' . filesize($f));
header('Content-Disposition: attachment; filename="test_template.docx"');
readfile($f);
