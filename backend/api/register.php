<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// api/register.php
require_once __DIR__ . '/../config/noxum_db.php';
session_start();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_response(["ok" => false, "mensaje" => "Método no permitido"], 405);
}

$data = get_json_body();

$nombre   = trim($data['nombre']   ?? '');
$email    = trim($data['email']    ?? '');
$password =      $data['password'] ?? '';

// El registro público es SOLO para pacientes.
// Doctores y recepcionistas los crea el admin desde su dashboard.
$rol = 'paciente';

//Validaciones
$errores = [];

if (strlen($nombre) < 2) {
    $errores[] = "El nombre debe tener al menos 2 caracteres.";
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = "El email no es válido.";
}
if (strlen($password) < 8) {
    $errores[] = "La contraseña debe tener al menos 8 caracteres.";
}

if (!empty($errores)) {
    json_response(["ok" => false, "errors" => $errores], 422);
}

//Verificar email duplicado 
try {
    $pdo = pdo();

    $check = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $check->execute([strtolower($email)]);

    if ($check->fetch()) {
        json_response(["ok" => false, "errors" => ["Este email ya está registrado."]], 409);
    }

    //Insertar usuario 
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([
        $nombre,
        strtolower($email),
        $hash,
        $rol
    ]);
    $id_usuario = (int) $pdo->lastInsertId();

    //Insertar en tabla del rol 
    if ($rol === 'paciente') {
        $pdo->prepare("INSERT INTO pacientes (id_usuario) VALUES (?)")
            ->execute([$id_usuario]);

    } elseif ($rol === 'doctor') {
        $pdo->prepare("INSERT INTO doctores (id_usuario, especialidad) VALUES (?, ?)")
            ->execute([$id_usuario, 'Psicología']);

    } elseif ($rol === 'recepcionista') {
        $pdo->prepare("INSERT INTO recepcionistas (id_usuario) VALUES (?)")
            ->execute([$id_usuario]);
    }

    //Guardar sesión 
    $_SESSION['usuario_id']     = $id_usuario;
    $_SESSION['usuario_nombre'] = $nombre;
    $_SESSION['usuario_email']  = strtolower($email);
    $_SESSION['usuario_rol']    = $rol;

    json_response([
        "ok"      => true,
        "mensaje" => "¡Cuenta creada exitosamente!",
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
