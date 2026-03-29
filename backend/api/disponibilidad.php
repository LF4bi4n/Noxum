<?php
// api/disponibilidad.php
require_once __DIR__ . '/../config/noxum_db.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    json_response(["ok" => false, "mensaje" => "No autenticado"], 401);
}

$id_usuario = $_SESSION['usuario_id'];
$method     = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = pdo();

    // Resolver id_doctor
    $stmtDoc = $pdo->prepare("SELECT id_doctor FROM doctores WHERE id_usuario = ?");
    $stmtDoc->execute([$id_usuario]);
    $docRow = $stmtDoc->fetch(PDO::FETCH_ASSOC);

    if (!empty($_GET['id_doctor'])) {
        // Paciente/admin pasan id_doctor explícito
        $id_doctor = (int)$_GET['id_doctor'];
    } elseif ($docRow) {
        // Doctor usa su propio id
        $id_doctor = (int)$docRow['id_doctor'];
    } else {
        json_response(["ok" => false, "mensaje" => "id_doctor requerido"], 403);
    }

    // ── GET ──────────────────────────────────────────────────────
    if ($method === 'GET') {

        // ?fecha= → slots del día
        if (!empty($_GET['fecha'])) {
            $fecha = trim($_GET['fecha']);

            // ¿Bloqueado?
            $stmtBlq = $pdo->prepare("SELECT 1 FROM bloqueos_horario WHERE id_doctor = ? AND fecha = ?");
            $stmtBlq->execute([$id_doctor, $fecha]);
            if ($stmtBlq->fetch()) {
                json_response(["ok" => true, "slots" => [], "bloqueado" => true]);
            }

            // Buscar slots existentes
            $stmtS = $pdo->prepare("
                SELECT DATE_FORMAT(hora_inicio, '%H:%i:%s') AS hora
                FROM slots_disponibles
                WHERE id_doctor = ? AND fecha = ? AND disponible = 1
                ORDER BY hora_inicio ASC
            ");
            $stmtS->execute([$id_doctor, $fecha]);
            $slots = array_column($stmtS->fetchAll(PDO::FETCH_ASSOC), 'hora');

            // Sin slots → generar desde horarios_doctor
            if (empty($slots)) {
                $dias = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
                $dow  = (int)date('w', strtotime($fecha));
                $dia  = $dias[$dow];

                $stmtH = $pdo->prepare("SELECT hora_inicio, hora_fin FROM horarios_doctor WHERE id_doctor = ? AND dia_semana = ?");
                $stmtH->execute([$id_doctor, $dia]);
                $h = $stmtH->fetch(PDO::FETCH_ASSOC);

                if ($h) {
                    $start = strtotime($fecha . ' ' . $h['hora_inicio']);
                    $end   = strtotime($fecha . ' ' . $h['hora_fin']);
                    $ins   = $pdo->prepare("INSERT IGNORE INTO slots_disponibles (id_doctor, fecha, hora_inicio, hora_fin, disponible) VALUES (?,?,?,?,1)");
                    for ($t = $start; $t < $end; $t += 3600) {
                        $ins->execute([$id_doctor, $fecha, date('H:i:s',$t), date('H:i:s',$t+3600)]);
                    }
                    $stmtS->execute([$id_doctor, $fecha]);
                    $slots = array_column($stmtS->fetchAll(PDO::FETCH_ASSOC), 'hora');
                }
            }

            json_response(["ok" => true, "slots" => $slots, "bloqueado" => false]);
        }

        // ?mes=&anio= → disponibilidad del mes
        $mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
        $anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

        $stmtBlq = $pdo->prepare("SELECT DATE_FORMAT(fecha,'%Y-%m-%d') AS f FROM bloqueos_horario WHERE id_doctor=? AND MONTH(fecha)=? AND YEAR(fecha)=?");
        $stmtBlq->execute([$id_doctor, $mes, $anio]);
        $bloqueados = array_column($stmtBlq->fetchAll(PDO::FETCH_ASSOC), 'f');

        $stmtSl = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(fecha,'%Y-%m-%d') AS f FROM slots_disponibles WHERE id_doctor=? AND disponible=1 AND MONTH(fecha)=? AND YEAR(fecha)=?");
        $stmtSl->execute([$id_doctor, $mes, $anio]);
        $libres = array_column($stmtSl->fetchAll(PDO::FETCH_ASSOC), 'f');

        // Días futuros cubiertos por horarios_doctor
        $stmtHD = $pdo->prepare("SELECT dia_semana FROM horarios_doctor WHERE id_doctor=?");
        $stmtHD->execute([$id_doctor]);
        $diasConfig = array_column($stmtHD->fetchAll(PDO::FETCH_ASSOC), 'dia_semana');

        if (!empty($diasConfig)) {
            $nombres = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
            $hoy     = date('Y-m-d');
            $ndias   = (int)date('t', mktime(0,0,0,$mes,1,$anio));
            for ($d = 1; $d <= $ndias; $d++) {
                $f   = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
                if ($f < $hoy) continue;
                $dow = (int)date('w', mktime(0,0,0,$mes,$d,$anio));
                if (in_array($nombres[$dow], $diasConfig)) $libres[] = $f;
            }
            $libres = array_values(array_unique($libres));
        }

        $result = [];
        foreach ($bloqueados as $f) $result[$f] = 'bloqueado';
        foreach ($libres as $f) if (!isset($result[$f])) $result[$f] = 'libre';

        json_response(["ok" => true, "disponibilidad" => $result]);
    }

    // ── POST: toggle bloqueo ──────────────────────────────────────
    if ($method === 'POST') {
        $data   = get_json_body();
        $fecha  = trim($data['fecha']  ?? '');
        $estado = trim($data['estado'] ?? '');

        if (!$fecha || !in_array($estado, ['libre','bloqueado'])) {
            json_response(["ok" => false, "errors" => ["fecha y estado requeridos"]], 422);
        }

        if ($estado === 'bloqueado') {
            $pdo->prepare("INSERT IGNORE INTO bloqueos_horario (id_doctor,fecha,motivo) VALUES (?,?,'Bloqueado')")->execute([$id_doctor,$fecha]);
            $pdo->prepare("UPDATE slots_disponibles SET disponible=0 WHERE id_doctor=? AND fecha=?")->execute([$id_doctor,$fecha]);
        } else {
            $pdo->prepare("DELETE FROM bloqueos_horario WHERE id_doctor=? AND fecha=?")->execute([$id_doctor,$fecha]);
            $pdo->prepare("UPDATE slots_disponibles SET disponible=1 WHERE id_doctor=? AND fecha=?")->execute([$id_doctor,$fecha]);

            $dias = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
            $dia  = $dias[(int)date('w', strtotime($fecha))];
            $stmtH = $pdo->prepare("SELECT hora_inicio,hora_fin FROM horarios_doctor WHERE id_doctor=? AND dia_semana=?");
            $stmtH->execute([$id_doctor,$dia]);
            $h = $stmtH->fetch(PDO::FETCH_ASSOC);

            if ($h) {
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM slots_disponibles WHERE id_doctor=? AND fecha=?");
                $cnt->execute([$id_doctor,$fecha]);
                if ((int)$cnt->fetchColumn() === 0) {
                    $start = strtotime($fecha.' '.$h['hora_inicio']);
                    $end   = strtotime($fecha.' '.$h['hora_fin']);
                    $ins   = $pdo->prepare("INSERT IGNORE INTO slots_disponibles (id_doctor,fecha,hora_inicio,hora_fin,disponible) VALUES (?,?,?,?,1)");
                    for ($t=$start;$t<$end;$t+=3600) $ins->execute([$id_doctor,$fecha,date('H:i:s',$t),date('H:i:s',$t+3600)]);
                }
            }
        }
        json_response(["ok" => true, "fecha" => $fecha, "estado" => $estado]);
    }

} catch (Throwable $e) {
    json_response(["ok" => false, "error" => $e->getMessage(), "linea" => $e->getLine()], 500);
}
