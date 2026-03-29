<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Si la ruta empieza con /backend, servir el backend
if (strpos($uri, '/backend/') === 0) {
    $file = __DIR__ . $uri;
    if (file_exists($file)) {
        require $file;
    } else {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Not found']);
    }
    return;
}

// Para todo lo demás, servir el frontend
$frontendFile = __DIR__ . '/frontend' . $uri;

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/frontend/index.php';
    return;
}

if (file_exists($frontendFile) && !is_dir($frontendFile)) {
    return false; // Servir el archivo estático directamente
}

// Si no existe, servir index.html
require __DIR__ . '/frontend/index.php';