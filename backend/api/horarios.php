<?php
// api/horarios.php
// Gestión de horarios semanales del doctor (tabla horarios_doctor)
// GET    ?id_doctor=X          → devuelve horarios del doctor
// POST                         → crea / actualiza un horario (dia + hora_inicio + hora_fin)
// DELETE ?id=X                 → elimina un horario específico
// POST  { accion:'regenerar', fecha } → regenera slots de una fecha según el horario

require_once __DIR__ . '/../config/noxum_db.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    json_response(["ok" => false, "mensaje" => "No autenticado"], 401);
}

$rol    = $_SESSION['usuario_rol'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = null;

try {
    $pdo = pdo();

    // ── Resolver id_doctor ─────────────────────────────────────────
    // Admin puede pasar ?id_doctor=X para gestionar horarios de cualquier doctor
    // Doctor solo puede gestionar los suyos
    if (in_array($rol, ['admin', 'recepcionista']) && !empty($_GET['id_doctor'])) {
        $id_doctor = (int)$_GET['id_doctor'];
    } elseif ($rol === 'doctor') {
        $stmt = $pdo->prepare("SELECT id_doctor FROM doctores WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $doc = $stmt->fetch();
        if (!$doc) {
            json_response(["ok" => false, "mensaje" => "Doctor no encontrado"], 404);
        }
        $id_doctor = $doc['id_doctor'];
    } else {
        json_response(["ok" => false, "mensaje" => "Acceso denegado"], 403);
    }

    // ── GET ────────────────────────────────────────────────────────
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT id_horario, dia_semana, hora_inicio, hora_fin
            FROM horarios_doctor
            WHERE id_doctor = ?
            ORDER BY FIELD(dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado','domingo')
        ");
        $stmt->execute([$id_doctor]);
        $horarios = $stmt->fetchAll();

        // Formatear horas a HH:MM
        foreach ($horarios as &$h) {
            $h['hora_inicio'] = substr($h['hora_inicio'], 0, 5);
            $h['hora_fin']    = substr($h['hora_fin'],    0, 5);
        }
        json_response(["ok" => true, "horarios" => $horarios]);
    }

    // ── DELETE ─────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        $id_horario = (int)($_GET['id'] ?? 0);
        if (!$id_horario) {
            json_response(["ok" => false, "mensaje" => "id requerido"], 422);
        }
        // Verificar que pertenece a este doctor
        $check = $pdo->prepare("SELECT id_doctor FROM horarios_doctor WHERE id_horario = ?");
        $check->execute([$id_horario]);
        $row = $check->fetch();
        if (!$row || $row['id_doctor'] !== $id_doctor) {
            json_response(["ok" => false, "mensaje" => "Horario no encontrado"], 404);
        }
        $pdo->prepare("DELETE FROM horarios_doctor WHERE id_horario = ?")->execute([$id_horario]);
        json_response(["ok" => true, "mensaje" => "Horario eliminado"]);
    }

    // ── POST ───────────────────────────────────────────────────────
    if ($method === 'POST') {
        $data = get_json_body();

        // Acción especial: regenerar slots de una fecha
        if (($data['accion'] ?? '') === 'regenerar') {
            $fecha = trim($data['fecha'] ?? '');
            if (!$fecha) json_response(["ok" => false, "mensaje" => "fecha requerida"], 422);

            $diasSemana = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
            $diaSemana  = $diasSemana[(int)date('w', strtotime($fecha))];

            $stmtH = $pdo->prepare("SELECT hora_inicio, hora_fin FROM horarios_doctor WHERE id_doctor = ? AND dia_semana = ?");
            $stmtH->execute([$id_doctor, $diaSemana]);
            $horario = $stmtH->fetch();

            if (!$horario) {
                json_response(["ok" => false, "mensaje" => "No hay horario definido para ese día de semana"], 422);
            }

            // Borrar slots no agendados
            $pdo->prepare("
                DELETE FROM slots_disponibles
                WHERE id_doctor = ? AND fecha = ?
                  AND disponible = 1
            ")->execute([$id_doctor, $fecha]);

            // Crear nuevos slots
            $start   = strtotime($fecha . ' ' . $horario['hora_inicio']);
            $end     = strtotime($fecha . ' ' . $horario['hora_fin']);
            $insSlot = $pdo->prepare("
                INSERT IGNORE INTO slots_disponibles (id_doctor, fecha, hora_inicio, hora_fin, disponible)
                VALUES (?, ?, ?, ?, 1)
            ");
            $count = 0;
            for ($t = $start; $t < $end; $t += 3600) {
                $insSlot->execute([$id_doctor, $fecha, date('H:i:s', $t), date('H:i:s', $t + 3600)]);
                $count++;
            }
            json_response(["ok" => true, "slots_creados" => $count]);
        }

        // Crear / reemplazar horario para un día
        $dia        = strtolower(trim($data['dia_semana']   ?? ''));
        $hora_ini   = trim($data['hora_inicio'] ?? '');
        $hora_fin   = trim($data['hora_fin']    ?? '');

        $dias_validos = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
        $errors = [];
        if (!in_array($dia, $dias_validos))         $errors[] = "dia_semana inválido";
        if (!preg_match('/^\d{2}:\d{2}$/', $hora_ini)) $errors[] = "hora_inicio inválida (HH:MM)";
        if (!preg_match('/^\d{2}:\d{2}$/', $hora_fin)) $errors[] = "hora_fin inválida (HH:MM)";
        if ($hora_ini >= $hora_fin)                  $errors[] = "hora_inicio debe ser menor que hora_fin";

        if (!empty($errors)) {
            json_response(["ok" => false, "errors" => $errors], 422);
        }

        // Upsert: si ya existe para ese día, actualizar; si no, insertar
        $existing = $pdo->prepare("SELECT id_horario FROM horarios_doctor WHERE id_doctor = ? AND dia_semana = ?");
        $existing->execute([$id_doctor, $dia]);
        $row = $existing->fetch();

        if ($row) {
            $pdo->prepare("
                UPDATE horarios_doctor SET hora_inicio = ?, hora_fin = ? WHERE id_horario = ?
            ")->execute([$hora_ini . ':00', $hora_fin . ':00', $row['id_horario']]);
            $id_horario = $row['id_horario'];
        } else {
            $ins = $pdo->prepare("
                INSERT INTO horarios_doctor (id_doctor, dia_semana, hora_inicio, hora_fin)
                VALUES (?, ?, ?, ?)
            ");
            $ins->execute([$id_doctor, $dia, $hora_ini . ':00', $hora_fin . ':00']);
            $id_horario = (int)$pdo->lastInsertId();
        }

        json_response([
            "ok"         => true,
            "id_horario" => $id_horario,
            "dia_semana" => $dia,
            "hora_inicio"=> $hora_ini,
            "hora_fin"   => $hora_fin
        ]);
    }

} catch (PDOException $e) {
    json_response(["ok" => false, "error" => $e->getMessage()], 500);
}
