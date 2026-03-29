<?php
$file = __DIR__ . '/index.html';
$content = file_get_contents($file);
echo $content;