<?php
// api/notificaciones.php — adaptado al esquema real de notificaciones
require_once __DIR__ . '/../config/noxum_db.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    json_response(["ok" => false, "mensaje" => "No autenticado"], 401);
}

$id_usuario = $_SESSION['usuario_id'];
$method     = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = pdo();

    // Agregar columnas faltantes si no existen
    try { $pdo->exec("ALTER TABLE notificaciones ADD COLUMN leida TINYINT(1) DEFAULT 0"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE notificaciones ADD COLUMN creada_en DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}
    // Ampliar enum para incluir nuestros tipos
    try { $pdo->exec("ALTER TABLE notificaciones MODIFY tipo VARCHAR(30) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}

    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT id_notificacion AS id_notif, tipo, mensaje, leida, creada_en
            FROM notificaciones
            WHERE id_usuario = ?
            ORDER BY id_notificacion DESC
            LIMIT 30
        ");
        $stmt->execute([$id_usuario]);
        $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtCnt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM notificaciones WHERE id_usuario = ? AND leida = 0");
        $stmtCnt->execute([$id_usuario]);
        $noLeidas = (int)$stmtCnt->fetch(PDO::FETCH_ASSOC)['cnt'];

        json_response(["ok" => true, "notificaciones" => $notifs, "no_leidas" => $noLeidas]);
    }

    if ($method === 'POST') {
        $data = get_json_body();
        if (($data['accion'] ?? '') === 'leer_todas') {
            $pdo->prepare("UPDATE notificaciones SET leida = 1 WHERE id_usuario = ?")
                ->execute([$id_usuario]);
            json_response(["ok" => true]);
        }
        if (!empty($data['id_notif'])) {
            $pdo->prepare("UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ? AND id_usuario = ?")
                ->execute([(int)$data['id_notif'], $id_usuario]);
            json_response(["ok" => true]);
        }
        json_response(["ok" => false, "mensaje" => "Acción no reconocida"], 422);
    }

} catch (Throwable $e) {
    json_response(["ok" => false, "error" => $e->getMessage()], 500);
}
