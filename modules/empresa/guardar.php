<?php
session_start();
require_once '../../config/conexion.php';

// Validar inicio de sesión y rol de administrador
$rol_usuario = strtolower($_SESSION['usuario_rol'] ?? $_SESSION['rol'] ?? '');
if (!isset($_SESSION['usuario_id']) || $rol_usuario !== 'administrador') {
    header('Location: ../../index.php?page=inventario');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::conectar();

        // 1. Obtener datos actuales de la empresa
        $stmt_actual = $db->query("SELECT id, logo_url FROM configuracion_empresa LIMIT 1");
        $empresa_actual = $stmt_actual->fetch(PDO::FETCH_ASSOC);

        $nombre_comercial = trim($_POST['nombre_comercial'] ?? '');
        $nombre_empresa   = $nombre_comercial; // Satisface la restricción NOT NULL de la columna nombre_empresa
        $razon_social     = trim($_POST['razon_social'] ?? '');
        $rif              = trim($_POST['rif'] ?? '');
        $telefono         = trim($_POST['telefono'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $direccion        = trim($_POST['direccion'] ?? '');
        $tasa_cambio_usd  = (float)($_POST['tasa_cambio_usd'] ?? 0.00);
        $logo_url         = $empresa_actual['logo_url'] ?? '';

        // 2. Procesar subida de logo si se seleccionó una imagen
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $directorio_destino = '../../uploads/';
            
            if (!is_dir($directorio_destino)) {
                mkdir($directorio_destino, 0777, true);
            }

            $extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $extensiones_permitidas = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

            if (in_array($extension, $extensiones_permitidas)) {
                $nombre_archivo = 'logo_empresa_' . time() . '.' . $extension;
                $ruta_relativa  = 'uploads/' . $nombre_archivo;
                $ruta_absoluta  = $directorio_destino . $nombre_archivo;

                if (move_uploaded_file($_FILES['logo']['tmp_name'], $ruta_absoluta)) {
                    // Eliminar el logo anterior si existe en disco
                    if (!empty($logo_url) && file_exists('../../' . $logo_url)) {
                        @unlink('../../' . $logo_url);
                    }
                    $logo_url = $ruta_relativa;
                }
            }
        }

        // 3. Insertar o Actualizar alineado con la estructura real de la BD
        if ($empresa_actual) {
            $sql = "UPDATE configuracion_empresa SET 
                    nombre_comercial = ?, 
                    nombre_empresa   = ?,
                    razon_social     = ?, 
                    rif              = ?, 
                    telefono         = ?, 
                    email            = ?, 
                    direccion        = ?, 
                    tasa_cambio_usd  = ?, 
                    logo_url         = ?, 
                    updated_at       = NOW() 
                    WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $nombre_comercial, $nombre_empresa, $razon_social, $rif, $telefono, 
                $email, $direccion, $tasa_cambio_usd, $logo_url, $empresa_actual['id']
            ]);
        } else {
            $sql = "INSERT INTO configuracion_empresa 
                    (nombre_comercial, nombre_empresa, razon_social, rif, telefono, email, direccion, tasa_cambio_usd, logo_url, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $nombre_comercial, $nombre_empresa, $razon_social, $rif, $telefono, 
                $email, $direccion, $tasa_cambio_usd, $logo_url
            ]);
        }

        header('Location: ../../index.php?page=empresa&status=success');
        exit();

    } catch (Exception $e) {
        die("Error al guardar la configuración: " . $e->getMessage());
    }
} else {
    header('Location: ../../index.php?page=empresa');
    exit();
}
?>