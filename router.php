<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = __DIR__;

// Rutas del backend
if (strpos($uri, '/backend/') === 0) {
    $file = $base . $uri;
    if (file_exists($file) && !is_dir($file)) {
        require $file;
        return;
    }
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Not found', 'path' => $file]);
    return;
}

// Archivos estáticos del frontend
if ($uri !== '/' && $uri !== '') {
    $static = $base . '/frontend' . $uri;
    if (file_exists($static) && !is_dir($static)) {
        return false;
    }
}

// Index
$index = $base . '/frontend/index.php';
if (file_exists($index)) {
    require $index;
} else {
    readfile($base . '/frontend/index.html');
}