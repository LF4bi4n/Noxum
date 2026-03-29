<?php
// backend/api/setup_doctor.php
// Endpoint para que el ADMIN cree doctores y recepcionistas.
// Solo usuarios con rol 'admin' o 'recepcionista' pueden llamar este endpoint.

require_once __DIR__ . '/../config/noxum_db.php';
session_start();

// ── Verificar que el que llama sea admin o recepcionista ──────
if (!isset($_SESSION['usuario_rol']) ||
    !in_array($_SESSION['usuario_rol'], ['admin', 'recepcionista'])) {
    json_response(["ok" => false, "mensaje" => "Acceso denegado. Solo el admin puede crear usuarios de este tipo."], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["ok" => false, "mensaje" => "Método no permitido"], 405);
}

$data = get_json_body();

$nombre       = trim($data['nombre']       ?? '');
$email        = trim($data['email']        ?? '');
$password     =      $data['password']     ?? '';
$rol          = trim($data['rol']          ?? 'doctor');       // 'doctor' o 'recepcionista'
$especialidad = trim($data['especialidad'] ?? 'Psicologia');   // solo aplica para doctores

// ── Validaciones ──────────────────────────────────────────────
$errores = [];

if (strlen($nombre) < 2)
    $errores[] = "El nombre debe tener al menos 2 caracteres.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errores[] = "El email no es valido.";
if (strlen($password) < 8)
    $errores[] = "La contrasena debe tener al menos 8 caracteres.";
if (!in_array($rol, ['doctor', 'recepcionista']))
    $errores[] = "Rol no valido. Solo se permite 'doctor' o 'recepcionista'.";

if (!empty($errores)) {
    json_response(["ok" => false, "errors" => $errores], 422);
}

try {
    $pdo = pdo();

    // Verificar email duplicado
    $check = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $check->execute([strtolower($email)]);
    if ($check->fetch()) {
        json_response(["ok" => false, "errors" => ["Este email ya esta registrado."]], 409);
    }

    // Crear usuario
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, ?, 'activo')"
    );
    $stmt->execute([$nombre, strtolower($email), $hash, $rol]);
    $id_usuario = (int) $pdo->lastInsertId();

    // Insertar en tabla del rol
    if ($rol === 'doctor') {
        $pdo->prepare("INSERT INTO doctores (id_usuario, especialidad) VALUES (?, ?)")
            ->execute([$id_usuario, $especialidad]);
    } elseif ($rol === 'recepcionista') {
        $pdo->prepare("INSERT INTO recepcionistas (id_usuario) VALUES (?)")
            ->execute([$id_usuario]);
    }

    json_response([
        "ok"      => true,
        "mensaje" => ucfirst($rol) . " creado exitosamente.",
        "usuario" => [
            "id"     => $id_usuario,
            "nombre" => $nombre,
            "email"  => strtolower($email),
            "rol"    => $rol
        ]
    ], 201);

} catch (PDOException $e) {
    json_response(["ok" => false, "errors" => ["Error interno del servidor."]], 500);
}
