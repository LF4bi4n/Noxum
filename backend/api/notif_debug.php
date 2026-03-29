<?php
require_once __DIR__ . '/../config/noxum_db.php';
session_start();

$pdo = pdo();
$id_usuario = $_SESSION['usuario_id'] ?? 'NO SESSION';

// Ver estructura real de la tabla
$cols = $pdo->query("DESCRIBE notificaciones")->fetchAll(PDO::FETCH_ASSOC);

// Todas las filas sin ORDER
$todas = $pdo->query("SELECT * FROM notificaciones LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

// Info del usuario en sesión
$uInfo = null;
if ($id_usuario !== 'NO SESSION') {
    $su = $pdo->prepare("SELECT id_usuario, nombre, rol FROM usuarios WHERE id_usuario = ?");
    $su->execute([$id_usuario]);
    $uInfo = $su->fetch(PDO::FETCH_ASSOC);
}

json_response([
    "session_id_usuario"  => $id_usuario,
    "usuario_info"        => $uInfo,
    "columnas_tabla"      => $cols,
    "filas"               => $todas
]);
