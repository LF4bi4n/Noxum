<?php
// api/especialistas.php
require_once __DIR__ . '/../config/noxum_db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    json_response(["ok" => false, "mensaje" => "Método no permitido"], 405);
}

try {
    $pdo = pdo();

    $stmt = $pdo->prepare("
        SELECT
            d.id_doctor,
            u.nombre,
            u.email,
            d.especialidad
        FROM doctores d
        JOIN usuarios u ON u.id_usuario = d.id_usuario
        WHERE u.estado = 'activo'
        ORDER BY u.nombre ASC
    ");
    $stmt->execute();
    $doctores = $stmt->fetchAll();

    json_response(["ok" => true, "especialistas" => $doctores]);

} catch (PDOException $e) {
    json_response(["ok" => false, "error" => $e->getMessage()], 500);
}
