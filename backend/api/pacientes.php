<?php
// api/pacientes.php
require_once __DIR__ . '/../config/noxum_db.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    json_response(["ok" => false, "mensaje" => "No autenticado"], 401);
}

$id_usuario = $_SESSION['usuario_id'];
$method     = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = pdo();

    $stmtDoc = $pdo->prepare("SELECT id_doctor FROM doctores WHERE id_usuario = ?");
    $stmtDoc->execute([$id_usuario]);
    $doctor    = $stmtDoc->fetch();
    $id_doctor = $doctor ? $doctor['id_doctor'] : null;

    if ($method === 'GET') {

        // ?todos=1 → todos los pacientes (para el select del modal)
        $todos = !empty($_GET['todos']);

        if ($id_doctor && !$todos) {
            // Pacientes que tienen cita con este doctor (para la tabla principal)
            $stmt = $pdo->prepare("
                SELECT
                    p.id_paciente,
                    u.nombre,
                    u.email,
                    p.fecha_nacimiento,
                    (SELECT c2.estado FROM citas c2
                     WHERE c2.id_paciente = p.id_paciente AND c2.id_doctor = :id_doc
                     ORDER BY c2.fecha DESC, c2.hora DESC LIMIT 1) AS ultimo_estado,
                    (SELECT c3.fecha FROM citas c3
                     WHERE c3.id_paciente = p.id_paciente AND c3.id_doctor = :id_doc2
                     ORDER BY c3.fecha DESC, c3.hora DESC LIMIT 1) AS ultima_fecha,
                    (SELECT COUNT(*) FROM citas c4
                     WHERE c4.id_paciente = p.id_paciente AND c4.id_doctor = :id_doc3) AS total_citas
                FROM pacientes p
                JOIN usuarios u ON u.id_usuario = p.id_usuario
                WHERE EXISTS (
                    SELECT 1 FROM citas cx
                    WHERE cx.id_paciente = p.id_paciente AND cx.id_doctor = :id_doc4
                )
                ORDER BY ultima_fecha DESC
            ");
            $stmt->execute([
                ':id_doc'  => $id_doctor,
                ':id_doc2' => $id_doctor,
                ':id_doc3' => $id_doctor,
                ':id_doc4' => $id_doctor,
            ]);
        } else {
            // TODOS los pacientes registrados en el sistema (para modal y admin)
            $stmt = $pdo->prepare("
                SELECT
                    p.id_paciente,
                    u.nombre,
                    u.email,
                    p.fecha_nacimiento,
                    (SELECT c2.estado FROM citas c2
                     WHERE c2.id_paciente = p.id_paciente
                     ORDER BY c2.fecha DESC, c2.hora DESC LIMIT 1) AS ultimo_estado,
                    (SELECT c3.fecha FROM citas c3
                     WHERE c3.id_paciente = p.id_paciente
                     ORDER BY c3.fecha DESC, c3.hora DESC LIMIT 1) AS ultima_fecha,
                    (SELECT COUNT(*) FROM citas c4
                     WHERE c4.id_paciente = p.id_paciente) AS total_citas
                FROM pacientes p
                JOIN usuarios u ON u.id_usuario = p.id_usuario
                ORDER BY u.nombre ASC
            ");
            $stmt->execute();
        }

        $pacientes = $stmt->fetchAll();
        json_response(["ok" => true, "pacientes" => $pacientes]);
    }

} catch (PDOException $e) {
    json_response(["ok" => false, "error" => $e->getMessage()], 500);
}
