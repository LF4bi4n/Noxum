<?php
// api/citas.php
require_once __DIR__ . '/../config/noxum_db.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    json_response(["ok" => false, "mensaje" => "No autenticado"], 401);
}

$id_usuario = $_SESSION['usuario_id'];
$method     = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = pdo();

    // Detectar rol
    $stmtDoc = $pdo->prepare("SELECT id_doctor FROM doctores WHERE id_usuario = ?");
    $stmtDoc->execute([$id_usuario]);
    $doctor    = $stmtDoc->fetch();
    $id_doctor = $doctor ? $doctor['id_doctor'] : null;

    $stmtPac = $pdo->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ?");
    $stmtPac->execute([$id_usuario]);
    $paciente    = $stmtPac->fetch();
    $id_paciente_sesion = $paciente ? $paciente['id_paciente'] : null;

    // ── GET ──────────────────────────────────────────────────────
    if ($method === 'GET') {
        $where  = [];
        $params = [];

        // Si es doctor → filtrar sus citas
        if ($id_doctor) {
            $where[]  = "c.id_doctor = ?";
            $params[] = $id_doctor;
        }

        // Si es paciente → filtrar sus propias citas
        if ($id_paciente_sesion && !$id_doctor) {
            $where[]  = "c.id_paciente = ?";
            $params[] = $id_paciente_sesion;
        }

        if (!empty($_GET['hoy'])) {
            $where[] = "c.fecha = CURDATE()";
        }

        if (!empty($_GET['paciente'])) {
            $where[]  = "c.id_paciente = ?";
            $params[] = (int)$_GET['paciente'];
        }

        if (!empty($_GET['proximas'])) {
            $where[] = "c.fecha >= CURDATE()";
            $where[] = "c.estado = 'agendada'";
        }

        $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $pdo->prepare("
            SELECT
                c.id_cita,
                c.fecha,
                c.hora,
                c.estado,
                u.nombre  AS paciente_nombre,
                u.email   AS paciente_email,
                p.id_paciente,
                ud.nombre AS doctor_nombre,
                d.id_doctor,
                d.especialidad
            FROM citas c
            JOIN pacientes p  ON p.id_paciente = c.id_paciente
            JOIN usuarios  u  ON u.id_usuario  = p.id_usuario
            JOIN doctores  d  ON d.id_doctor   = c.id_doctor
            JOIN usuarios  ud ON ud.id_usuario = d.id_usuario
            $whereSQL
            ORDER BY c.fecha ASC, c.hora ASC
        ");
        $stmt->execute($params);
        $citas = $stmt->fetchAll();

        if (!empty($_GET['hoy'])) {
            json_response(["ok" => true, "total" => count($citas), "citas" => $citas]);
        } else {
            json_response(["ok" => true, "citas" => $citas]);
        }
    }

    // ── POST: crear cita ──────────────────────────────────────────
    if ($method === 'POST') {
        $data = get_json_body();

        // Si el paciente agenda, usa su propio id_paciente de sesión
        $id_pac = $id_paciente_sesion ?? (int)trim($data['id_paciente'] ?? 0);
        $fecha  = trim($data['fecha'] ?? '');
        $hora   = trim($data['hora']  ?? '');
        // Si el doctor agenda, usa su propio id_doctor de sesión; si no, toma del body (admin)
        $id_doc = $id_doctor ?? (int)($data['id_doctor'] ?? 0);

        if (!$id_pac || !$fecha || !$hora || !$id_doc) {
            json_response(["ok" => false, "errors" => ["Faltan campos: id_doctor, fecha, hora"]], 422);
        }

        // Verificar conflicto
        $conflict = $pdo->prepare("
            SELECT id_cita FROM citas
            WHERE id_doctor = ? AND fecha = ? AND hora = ? AND estado != 'cancelada'
        ");
        $conflict->execute([$id_doc, $fecha, $hora]);
        if ($conflict->fetch()) {
            json_response(["ok" => false, "errors" => ["Ya existe una cita en ese horario"]], 409);
        }

        $stmt = $pdo->prepare("
            INSERT INTO citas (id_paciente, id_doctor, fecha, hora, estado)
            VALUES (?, ?, ?, ?, 'agendada')
        ");
        $stmt->execute([$id_pac, $id_doc, $fecha, $hora]);
        $id_cita = (int)$pdo->lastInsertId();

        $pdo->prepare("
            UPDATE slots_disponibles SET disponible = 0
            WHERE id_doctor = ? AND fecha = ? AND hora_inicio = ?
        ")->execute([$id_doc, $fecha, $hora]);

        // ── Notificaciones ──────────────────────────────────────
        crearNotificacion($pdo, $id_doc, $id_pac, $fecha, $hora, 'agendada');

        json_response(["ok" => true, "id_cita" => $id_cita, "mensaje" => "Cita creada correctamente"], 201);
    }

    // ── PUT: cambiar estado ───────────────────────────────────────
    if ($method === 'PUT') {
        $data    = get_json_body();
        $id_cita = (int)($data['id_cita'] ?? 0);
        $estado  = strtolower(trim($data['estado'] ?? ''));

        if (!$id_cita || !in_array($estado, ['agendada','cancelada','atendida'])) {
            json_response(["ok" => false, "errors" => ["Estado válido: agendada|cancelada|atendida"]], 422);
        }

        // Verificar que el paciente solo cancele sus propias citas
        if ($id_paciente_sesion && !$id_doctor) {
            $check = $pdo->prepare("SELECT id_paciente FROM citas WHERE id_cita = ?");
            $check->execute([$id_cita]);
            $row = $check->fetch();
            if (!$row || $row['id_paciente'] != $id_paciente_sesion) {
                json_response(["ok" => false, "errors" => ["No tienes permiso para modificar esta cita"]], 403);
            }
        }

        $stmt = $pdo->prepare("UPDATE citas SET estado = ? WHERE id_cita = ?");
        $stmt->execute([$estado, $id_cita]);

        if ($estado === 'cancelada') {
            $citaRow = $pdo->prepare("SELECT id_doctor, id_paciente, fecha, hora FROM citas WHERE id_cita = ?");
            $citaRow->execute([$id_cita]);
            $c = $citaRow->fetch();
            if ($c) {
                $pdo->prepare("
                    UPDATE slots_disponibles SET disponible = 1
                    WHERE id_doctor = ? AND fecha = ? AND hora_inicio = ?
                ")->execute([$c['id_doctor'], $c['fecha'], $c['hora']]);
                // ── Notificaciones ──────────────────────────────
                crearNotificacion($pdo, $c['id_doctor'], $c['id_paciente'], $c['fecha'], $c['hora'], 'cancelada');
            }
        }

        json_response(["ok" => true, "mensaje" => "Estado actualizado"]);
    }

} catch (PDOException $e) {
    json_response(["ok" => false, "error" => $e->getMessage()], 500);
}

// ── Helper: crear notificaciones para doctor, paciente y admins ──
function crearNotificacion(PDO $pdo, int $id_doctor, int $id_paciente, string $fecha, string $hora, string $tipo): void {
    // Agregar columnas si no existen en la tabla pre-existente
    try { $pdo->exec("ALTER TABLE notificaciones ADD COLUMN leida TINYINT(1) DEFAULT 0"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE notificaciones ADD COLUMN creada_en DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE notificaciones MODIFY tipo VARCHAR(30) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}

    try {
        $stmtD = $pdo->prepare("SELECT u.nombre, u.id_usuario FROM doctores d JOIN usuarios u ON u.id_usuario=d.id_usuario WHERE d.id_doctor=?");
        $stmtD->execute([$id_doctor]);
        $doc = $stmtD->fetch(PDO::FETCH_ASSOC);

        $stmtP = $pdo->prepare("SELECT u.nombre, u.id_usuario FROM pacientes p JOIN usuarios u ON u.id_usuario=p.id_usuario WHERE p.id_paciente=?");
        $stmtP->execute([$id_paciente]);
        $pac = $stmtP->fetch(PDO::FETCH_ASSOC);

        if (!$doc || !$pac) return;

        $fechaFmt = date('d/m/Y', strtotime($fecha));
        $horaFmt  = substr($hora, 0, 5);
        $tipoStr  = $tipo === 'agendada' ? 'cita_agendada' : 'cita_cancelada';
        $ins      = $pdo->prepare("INSERT INTO notificaciones (id_usuario, tipo, mensaje) VALUES (?,?,?)");

        if ($tipo === 'agendada') {
            $ins->execute([$doc['id_usuario'], $tipoStr,
                "Nueva cita agendada con {$pac['nombre']} el {$fechaFmt} a las {$horaFmt}"]);
            $ins->execute([$pac['id_usuario'], $tipoStr,
                "Tu cita con {$doc['nombre']} fue agendada para el {$fechaFmt} a las {$horaFmt}"]);
        } else {
            $ins->execute([$doc['id_usuario'], $tipoStr,
                "La cita con {$pac['nombre']} del {$fechaFmt} a las {$horaFmt} fue cancelada"]);
            $ins->execute([$pac['id_usuario'], $tipoStr,
                "Tu cita con {$doc['nombre']} del {$fechaFmt} a las {$horaFmt} fue cancelada"]);
        }

        $admins = $pdo->query("SELECT id_usuario FROM usuarios WHERE rol='admin'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($admins as $a) {
            if ($tipo === 'agendada') {
                $ins->execute([$a['id_usuario'], $tipoStr,
                    "Nueva cita: {$pac['nombre']} con {$doc['nombre']} el {$fechaFmt} a las {$horaFmt}"]);
            } else {
                $ins->execute([$a['id_usuario'], $tipoStr,
                    "Cita cancelada: {$pac['nombre']} con {$doc['nombre']} del {$fechaFmt} a las {$horaFmt}"]);
            }
        }
    } catch (Throwable $e) {
        error_log("crearNotificacion error: " . $e->getMessage());
    }
}
