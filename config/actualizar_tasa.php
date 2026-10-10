<?php
// modules/configuracion/actualizar_tasa.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
    exit;
}

require_once '../../config/conexion.php';
$db = Database::conectar();

// Control de sesión seguro. Si no ha iniciado sesión formalmente, forzamos ID = 1 para desarrollo
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$usuario_id = $_SESSION['usuario_id'] ?? 1;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Capturar la tasa enviada desde el modal
$tasa_cruda = $_POST['tasa_cambio'] ?? '';
$nueva_tasa = filter_var($tasa_cruda, FILTER_VALIDATE_FLOAT);

if ($nueva_tasa === false || $nueva_tasa <= 0) {
    echo json_encode(["status" => "error", "message" => "Error: El valor de la tasa ingresada es inválido."]);
    exit;
}

try {
    $db->beginTransaction();

    // 1. Obtener los metadatos y el ID real del primer registro de la empresa (Evita asumir id = 1)
    $stmt_empresa = $db->query("SELECT id, tasa_cambio_usd FROM configuracion_empresa ORDER BY id ASC LIMIT 1");
    $empresa = $stmt_empresa->fetch();

    if (!$empresa) {
        throw new Exception("No se encontró ningún registro de configuración de empresa en la base de datos.");
    }

    $empresa_id = (int)$empresa['id'];
    $tasa_anterior = (float)$empresa['tasa_cambio_usd'];

    // 2. Formatear la tasa a los 4 decimales exigidos por el tipo decimal(10,4) de tu PDF
    $tasa_formateada = number_format($nueva_tasa, 4, '.', '');

    // 3. Ejecutar la actualización segura utilizando el ID dinámico real
    $stmt_update = $db->prepare("UPDATE configuracion_empresa SET tasa_cambio_usd = :tasa WHERE id = :id");
    $stmt_update->execute([
        ':tasa' => $tasa_formateada,
        ':id'   => $empresa_id
    ]);

    // 4. Inyectar traza en la bitácora JSON de la tabla 'auditoria'
    $datos_ant = json_encode(["tasa_cambio_usd" => $tasa_anterior]);
    $datos_nue = json_encode(["tasa_cambio_usd" => (float)$tasa_formateada]);

    $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, fecha_auditoria) VALUES (:u_id, 'ACTUALIZACION_TASA_DIARIA', 'configuracion_empresa', :r_id, :ant, :nue, :ip, NOW())");
    $stmt_audit->execute([
        ':u_id' => $usuario_id,
        ':r_id' => $empresa_id,
        ':ant'  => $datos_ant,
        ':nue'  => $datos_nue,
        ':ip'   => $ip_address
    ]);

    $db->commit();
    echo json_encode([
        "status" => "success", 
        "message" => "¡Tasa de cambio actualizada a " . number_format($nueva_tasa, 2) . " Bs. con éxito!"
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    error_log("Error al actualizar la tasa diaria: " . $e->getMessage());
    
    // El sistema te devolverá el mensaje real de MySQL en caso de fallas relacionales
    echo json_encode(["status" => "error", "message" => "Fallo en el motor: " . $e->getMessage()]);
}
exit;
