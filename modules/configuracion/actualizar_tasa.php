<?php
// modules/configuracion/actualizar_tasa.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no autorizada']);
    exit();
}

require_once '../../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tasa = filter_input(INPUT_POST, 'tasa_cambio', FILTER_VALIDATE_FLOAT);

    if ($tasa && $tasa > 0) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("UPDATE configuracion_empresa SET tasa_cambio_usd = :tasa, fecha_actualizacion_tasa = NOW() WHERE id = 1");
            $stmt->execute([':tasa' => $tasa]);

            echo json_encode(['status' => 'success', 'message' => 'Tasa cambiaria actualizada a ' . number_format($tasa, 2) . ' Bs']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Monto de tasa inválido']);
    }
}
?>