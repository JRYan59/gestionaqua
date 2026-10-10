<?php
session_start();
require_once 'config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        try {
            $db = Database::conectar();

            // Buscar usuario por email en la base de datos
            $stmt = $db->prepare("SELECT id, nombre, apellido, password, rol, activo FROM usuarios WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                // Verificar si está activo
                if ((int)$usuario['activo'] !== 1) {
                    header('Location: login.php?error=inactivo');
                    exit();
                }

                if (password_verify($password, $usuario['password'])) {
                    
                    // Iniciar Variables de Sesión
                    $_SESSION['usuario_id']     = $usuario['id'];
                    $_SESSION['usuario_nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                    $_SESSION['usuario_rol']    = $usuario['rol'];

                    // Actualizar campo 'ultimo_acceso'
                    $stmtAcceso = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
                    $stmtAcceso->execute([':id' => $usuario['id']]);

                    // Registrar evento en la tabla Auditoría
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $stmtAuditoria = $db->prepare("
                        INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, ip_address) 
                        VALUES (:u, 'LOGIN_EXITOSO', 'usuarios', :r, :ip)
                    ");
                    $stmtAuditoria->execute([
                        ':u' => $usuario['id'],
                        ':r' => $usuario['id'],
                        ':ip' => $ip
                    ]);

                    // Redirigir al sistema principal
                    header('Location: index.php?page=dashboard');
                    exit();
                }
            }

            // Credenciales incorrectas
            header('Location: login.php?error=credenciales');
            exit();

        } catch (PDOException $e) {
            die("Error en la autenticación: " . $e->getMessage());
        }
    } else {
        header('Location: login.php?error=vacíos');
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}