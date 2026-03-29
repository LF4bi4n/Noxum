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
    echo json_encode(['ok' => false, 'error' => 'Not found']);
    return;
}

// Archivos estáticos del frontend (css, js, img, etc.)
$static = $base . '/frontend' . $uri;
if ($uri !== '/' && file_exists($static) && !is_dir($static)) {
    $ext = pathinfo($static, PATHINFO_EXTENSION);
    $mimes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'ico'  => 'image/x-icon',
        'svg'  => 'image/svg+xml',
        'html' => 'text/html',
        'woff2'=> 'font/woff2',
        'woff' => 'font/woff',
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    readfile($static);
    return;
}

// Index
readfile($base . '/frontend/index.html');