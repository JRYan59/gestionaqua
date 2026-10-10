<?php
// modules/usuarios/acciones.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
    exit;
}

require_once '../../config/conexion.php';
$db = Database::conectar();

$usuario_sesion_id = 1; 
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$accion = filter_input(INPUT_POST, 'accion', FILTER_DEFAULT);

if (empty($accion)) {
    echo json_encode(["status" => "error", "message" => "Acción no especificada."]);
    exit;
}

// ==========================================
// ACCIÓN A: CREAR USUARIO EN TU TABLA REAL
// ==========================================
if ($accion === 'crear') {
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_DEFAULT);
    $apellido = filter_input(INPUT_POST, 'apellido', FILTER_DEFAULT);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $rol = filter_input(INPUT_POST, 'rol', FILTER_DEFAULT);
    $telefono = filter_input(INPUT_POST, 'telefono', FILTER_DEFAULT);
    $password_raw = $_POST['password'] ?? '';

    if (!$nombre || !$apellido || !$email || !$rol || empty($password_raw)) {
        echo json_encode(["status" => "error", "message" => "Faltan campos obligatorios para el registro relacional."]);
        exit;
    }

    try {
        $db->beginTransaction();

        // Validar que el correo único no esté ocupado
        $stmt_check = $db->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
        $stmt_check->execute([':email' => $email]);
        if ($stmt_check->fetch()) {
            echo json_encode(["status" => "error", "message" => "El correo electrónico ya se encuentra registrado."]);
            $db->rollBack();
            exit;
        }

        $password_hash = password_hash($password_raw, PASSWORD_ARGON2ID);

        // Columnas exactas de tu esquema real del PDF
        $query = "INSERT INTO usuarios (nombre, apellido, email, password, telefono, rol, activo, created_at, updated_at) VALUES (:nom, :ape, :email, :pass, :tel, :rol, 1, NOW(), NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':nom'   => $nombre,
            ':ape'   => $apellido,
            ':email' => $email,
            ':pass'  => $password_hash,
            ':tel'   => $telefono,
            ':rol'   => $rol
        ]);
        $nuevo_id = $db->lastInsertId();

        // Registrar en Auditoría el nuevo ingreso
        $datos_nuevos = json_encode(["id" => $nuevo_id, "email" => $email, "rol" => $rol]);
        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_nuevos, ip_address, fecha_auditoria) VALUES (:u_id, 'ALTA_USUARIO', 'usuarios', :r_id, :d_nuevos, :ip, NOW())");
        $stmt_audit->execute([':u_id' => $usuario_sesion_id, ':r_id' => $nuevo_id, ':d_nuevos' => $datos_nuevos, ':ip' => $ip_address]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "¡Usuario guardado con éxito bajo cifrado Argon2id!"]);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        echo json_encode(["status" => "error", "message" => "Error de Base de Datos: " . $e->getMessage()]);
    }
    exit;
}

// ==========================================
// ACCIÓN B: DESACTIVAR USUARIO (BAJA LÓGICA)
// ==========================================
if ($accion === 'desactivar') {
    $usuario_id = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);

    if (!$usuario_id) {
        echo json_encode(["status" => "error", "message" => "ID de usuario inválido."]);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt_old = $db->prepare("SELECT email, rol, activo FROM usuarios WHERE id = :id");
        $stmt_old->execute([':id' => $usuario_id]);
        $usr = $stmt_old->fetch();

        if (!$usr || (int)$usr['activo'] === 0) {
            echo json_encode(["status" => "error", "message" => "El usuario ya se encuentra inactivo."]);
            $db->rollBack();
            exit;
        }

        // Ejecutar cambio de bit para no romper llaves foráneas con pedidos/despacho
        $stmt_baja = $db->prepare("UPDATE usuarios SET activo = 0 WHERE id = :id");
        $stmt_baja->execute([':id' => $usuario_id]);

        $datos_ant = json_encode(["id" => $usuario_id, "email" => $usr['email'], "estado" => "activo"]);
        $datos_nue = json_encode(["estado" => "inactivo / revocado"]);
        
        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, fecha_auditoria) VALUES (:u_id, 'BAJA_USUARIO', 'usuarios', :r_id, :d_ant, :d_nue, :ip, NOW())");
        $stmt_audit->execute([':u_id' => $usuario_sesion_id, ':r_id' => $usuario_id, ':d_ant' => $datos_ant, ':d_nue' => $datos_nue, ':ip' => $ip_address]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "Acceso revocado de forma segura."]);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        echo json_encode(["status" => "error", "message" => "Error de DB al dar de baja: " . $e->getMessage()]);
    }
    exit;
}
