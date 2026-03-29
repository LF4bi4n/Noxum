<?php

// http://localhost/noxum/backend/api/setup_admin.php

require_once __DIR__ . '/../config/noxum_db.php';

try {
    $pdo = pdo();

    // Verificar si ya existe
    $check = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $check->execute(['admin@noxum.com']);

    if ($check->fetch()) {
        // Ya existe, solo actualizar contraseña por si acaso
        $hash = password_hash('admin1', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET password = ?, nombre = 'Admin', rol = 'recepcionista' WHERE email = ?")
            ->execute([$hash, 'admin@noxum.com']);

        json_response([
            "ok"      => true,
            "mensaje" => "Admin ya existía — contraseña actualizada a 'admin1'",
            "login"   => ["email" => "admin@noxum.com", "password" => "admin1"]
        ]);
    }

    // Crear admin nuevo
    $hash = password_hash('admin1', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute(['Admin', 'admin@noxum.com', $hash, 'recepcionista', 'activo']);
    $id = (int) $pdo->lastInsertId();

    // Insertar en tabla recepcionistas
    $pdo->prepare("INSERT INTO recepcionistas (id_usuario) VALUES (?)")
        ->execute([$id]);

    json_response([
        "ok"      => true,
        "mensaje" => "Admin creado exitosamente",
        "login"   => [
            "email"    => "admin@noxum.com",
            "password" => "admin1"
        ]
    ], 201);

} catch (PDOException $e) {
    json_response(["ok" => false, "error" => $e->getMessage()], 500);
}
