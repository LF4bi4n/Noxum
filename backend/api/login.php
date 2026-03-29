<?php
// api/login.php
require_once __DIR__ . '/../config/noxum_db.php';
session_start();

$method = $_SERVER['REQUEST_METHOD'];

//verificar sesión activa 
if ($method === 'GET' && isset($_GET['check'])) {
    if (isset($_SESSION['usuario_id'])) {
        json_response([
            "ok"      => true,
            "usuario" => [
                "id"     => $_SESSION['usuario_id'],
                "nombre" => $_SESSION['usuario_nombre'],
                "email"  => $_SESSION['usuario_email'],
                "rol"    => $_SESSION['usuario_rol']
            ]
        ]);
    } else {
        json_response(["ok" => false, "mensaje" => "Sin sesión activa"], 401);
    }
}

//POST: cerrar sesión
if ($method === 'POST' && isset($_GET['logout'])) {
    session_destroy();
    json_response(["ok" => true, "mensaje" => "Sesión cerrada"]);
}

//POST: iniciar sesión 
if ($method !== 'POST') {
    json_response(["ok" => false, "mensaje" => "Método no permitido"], 405);
}

$data     = get_json_body();
$email    = strtolower(trim($data['email']    ?? ''));
$password =                  $data['password'] ?? '';

if (!$email || !$password) {
    json_response(["ok" => false, "errors" => ["Email y contraseña son requeridos."]], 422);
}

try {
    $pdo  = pdo();
    $stmt = $pdo->prepare(
        "SELECT id_usuario, nombre, email, password, rol, estado
         FROM usuarios WHERE email = ?"
    );
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['password'])) {
        json_response(["ok" => false, "errors" => ["Email o contraseña incorrectos."]], 401);
    }

    if ($usuario['estado'] === 'inactivo') {
        json_response(["ok" => false, "errors" => ["Tu cuenta está desactivada."]], 403);
    }

    //Guardar sesión
    $_SESSION['usuario_id']     = $usuario['id_usuario'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_email']  = $usuario['email'];
    $_SESSION['usuario_rol']    = $usuario['rol'];

    json_response([
        "ok"      => true,
        "mensaje" => "¡Bienvenido, " . $usuario['nombre'] . "!",
        "usuario" => [
            "id"     => $usuario['id_usuario'],
            "nombre" => $usuario['nombre'],
            "email"  => $usuario['email'],
            "rol"    => $usuario['rol']
        ]
    ]);

} catch (PDOException $e) {
    json_response(["ok" => false, "errors" => ["Error interno del servidor."]], 500);
}
