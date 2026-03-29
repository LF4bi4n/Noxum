<?php
$html = file_get_contents(__DIR__ . '/index.html');
header('Content-Type: text/html; charset=utf-8');
echo $html;
exit;