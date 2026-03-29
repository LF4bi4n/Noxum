<?php
// api/perfil.php
require_once __DIR__ . '/../config/noxum_db.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    json_response(["ok" => false, "mensaje" => "No autenticado"], 401);
}

$id_usuario = $_SESSION['usuario_id'];
$method     = $_SERVER['REQUEST_METHOD'];

$uploadDir = __DIR__ . '/../../frontend/img/perfiles/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── GET ────────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $pdo = pdo();
        // Asegurarse de que la columna existe antes de consultarla
        try {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(255) NULL");
        } catch (PDOException $e) { /* ya existe */ }

        $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        json_response(["ok" => true, "foto" => $row['foto_perfil'] ?? null]);
    } catch (Throwable $e) {
        json_response(["ok" => false, "error" => $e->getMessage()], 500);
    }
}

// ── POST ───────────────────────────────────────────────────────
if ($method === 'POST') {
    if (empty($_FILES['foto'])) {
        json_response(["ok" => false, "errors" => ["No se recibió ningún archivo"]], 422);
    }

    $file    = $_FILES['foto'];
    $maxSize = 3 * 1024 * 1024;
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $ext_map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_response(["ok" => false, "errors" => ["Error al subir el archivo"]], 422);
    }
    if ($file['size'] > $maxSize) {
        json_response(["ok" => false, "errors" => ["La imagen no debe superar 3MB"]], 422);
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowed)) {
        json_response(["ok" => false, "errors" => ["Solo se permiten imágenes JPG, PNG, WEBP o GIF"]], 422);
    }

    $ext      = $ext_map[$mimeType];
    $filename = 'perfil_' . $id_usuario . '.' . $ext;
    $destPath = $uploadDir . $filename;

    foreach (['jpg','png','webp','gif'] as $e) {
        $old = $uploadDir . 'perfil_' . $id_usuario . '.' . $e;
        if (file_exists($old) && $e !== $ext) @unlink($old);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        json_response(["ok" => false, "errors" => ["No se pudo guardar la imagen"]], 500);
    }

    $urlFoto = 'img/perfiles/' . $filename;

    try {
        $pdo = pdo();
        try {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(255) NULL");
        } catch (PDOException $e) { /* ya existe */ }

        $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id_usuario = ?")
            ->execute([$urlFoto, $id_usuario]);

        json_response(["ok" => true, "foto" => $urlFoto, "mensaje" => "Foto actualizada"]);
    } catch (Throwable $e) {
        json_response(["ok" => false, "error" => $e->getMessage()], 500);
    }
}
