<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($uri, '/backend/') === 0) {
    $file = __DIR__ . $uri;
    if (file_exists($file)) {
        require $file;
    } else {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Not found: ' . $file]);
    }
    return;
}

$frontendFile = __DIR__ . '/frontend' . $uri;

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/frontend/index.php';
    return;
}

if (file_exists($frontendFile) && !is_dir($frontendFile)) {
    return false;
}

require __DIR__ . '/frontend/index.php';