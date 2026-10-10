<?php
// modules/despacho/acciones.php

// 1. Forzar respuesta JSON limpia para peticiones asíncronas desde la interfaz JavaScript
header('Content-Type: application/json');

// Validar que la petición entre estrictamente por método POST seguro
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Método de comunicación no permitido."]);
    exit;
}

require_once '../../config/conexion.php';
$db = Database::conectar();

// Id de usuario de sesión fijo temporalmente para auditoría (Se sustituye con $_SESSION más adelante)
$usuario_sesion_id = 1; 
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

$accion = filter_input(INPUT_POST, 'accion', FILTER_DEFAULT);

if (empty($accion)) {
    echo json_encode(["status" => "error", "message" => "Acción logística no especificada."]);
    exit;
}

// ==========================================
// ACCIÓN A: VINCULAR TRANSPORTE A UNA ZONA
// ==========================================
if ($accion === 'asignar') {
    $transportista_id = filter_input(INPUT_POST, 'transportista_id', FILTER_VALIDATE_INT);
    $zona_id = filter_input(INPUT_POST, 'zona_id', FILTER_VALIDATE_INT);

    if (!$transportista_id || !$zona_id) {
        echo json_encode(["status" => "error", "message" => "Los datos seleccionados son inválidos."]);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Verificar si ya existía una asignación previa inactiva para reactivarla o insertar una nueva
        $stmt_check = $db->prepare("SELECT id, activo FROM asignacion_transportistas WHERE transportista_id = :t_id AND zona_id = :z_id");
        $stmt_check->execute([':t_id' => $transportista_id, ':z_id' => $zona_id]);
        $existe = $stmt_check->fetch();

        if ($existe) {
            if ((int)$existe['activo'] === 1) {
                echo json_encode(["status" => "error", "message" => "Este transportista ya se encuentra asignado y activo en la zona elegida."]);
                $db->rollBack();
                exit;
            } else {
                // Reactivación si existía previamente dada de baja (Optimiza espacio en disco)
                $stmt_reactivar = $db->prepare("UPDATE asignacion_transportistas SET activo = 1, fecha_asignacion = NOW() WHERE id = :id");
                $stmt_reactivar->execute([':id' => $existe['id']]);
                $asignacion_id = $existe['id'];
                $audit_type = "REACTIVACION_RUTA_DESPACHO";
            }
        } else {
            // Inserción nativa regular
            $stmt_insert = $db->prepare("INSERT INTO asignacion_transportistas (transportista_id, zona_id, activo, fecha_asignacion) VALUES (:t_id, :z_id, 1, NOW())");
            $stmt_insert->execute([':t_id' => $transportista_id, ':z_id' => $zona_id]);
            $asignacion_id = $db->lastInsertId();
            $audit_type = "CREACION_RUTA_DESPACHO";
        }

        // 2. Preparar el JSON simétrico para registrar en la bitácora de auditoría
        $datos_nuevos_json = json_encode([
            "asignacion_id"    => $asignacion_id,
            "transportista_id" => $transportista_id,
            "zona_id"          => $zona_id,
            "estado"           => "Activo"
        ]);

        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, fecha_auditoria) VALUES (:u_id, :accion, 'asignacion_transportistas', :r_id, NULL, :d_nuevos, :ip, :ua, NOW())");
        $stmt_audit->execute([
            ':u_id'     => $usuario_sesion_id,
            ':accion'   => $audit_type,
            ':r_id'     => $asignacion_id,
            ':d_nuevos' => $datos_nuevos_json,
            ':ip'       => $ip_address,
            ':ua'       => $user_agent
        ]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "¡Transportista asignado a la zona correctamente!"]);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        
        // Captura elegante si el motor detecta la restricción UNIQUE por concurrencia
        if ($e->getCode() == 23000) {
            echo json_encode(["status" => "error", "message" => "Error de integridad: Ya existe este emparejamiento activo en el sistema."]);
        } else {
            error_log("Error crítico en asignación de despacho: " . $e->getMessage());
            echo json_encode(["status" => "error", "message" => "Error interno de servidor al procesar la ruta."]);
        }
    }
    exit;
}

// ==========================================
// ACCIÓN B: REVOCAR ASIGNACIÓN (ELIMINACIÓN LÓGICA)
// ==========================================
if ($accion === 'revocar') {
    $asignacion_id = filter_input(INPUT_POST, 'asignacion_id', FILTER_VALIDATE_INT);

    if (!$asignacion_id) {
        echo json_encode(["status" => "error", "message" => "ID de asignación inválido o corrupto."]);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Verificar existencia antes de modificar
        $stmt_get = $db->prepare("SELECT transportista_id, zona_id, activo FROM asignacion_transportistas WHERE id = :id");
        $stmt_get->execute([':id' => $asignacion_id]);
        $ruta_actual = $stmt_get->fetch();

        if (!$ruta_actual || (int)$ruta_actual['activo'] === 0) {
            echo json_encode(["status" => "error", "message" => "La ruta seleccionada ya no se encuentra activa o no existe."]);
            $db->rollBack();
            exit;
        }

        // 2. Ejecutar la baja lógica cambiando el bit 'activo = 0' para no perder el histórico relacional
        $stmt_baja = $db->prepare("UPDATE asignacion_transportistas SET activo = 0 WHERE id = :id");
        $stmt_baja->execute([':id' => $asignacion_id]);

        // 3. Documentar el cambio estructural para la tabla de auditoría
        $datos_anteriores_json = json_encode([
            "asignacion_id"    => $asignacion_id,
            "transportista_id" => $ruta_actual['transportista_id'],
            "zona_id"          => $ruta_actual['zona_id'],
            "estado_anterior"  => "Activo"
        ]);
        
        $datos_nuevos_json = json_encode(["estado_nuevo" => "Inactivo / Revocado"]);

        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, fecha_auditoria) VALUES (:u_id, 'REVOCACION_RUTA_DESPACHO', 'asignacion_transportistas', :r_id, :d_anteriores, :d_nuevos, :ip, :ua, NOW())");
        $stmt_audit->execute([
            ':u_id'         => $usuario_sesion_id,
            ':r_id'         => $asignacion_id,
            ':d_anteriores' => $datos_anteriores_json,
            ':d_nuevos'     => $datos_nuevos_json,
            ':ip'           => $ip_address,
            ':ua'           => $user_agent
        ]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "La ruta ha sido revocada de forma segura. El despachador fue desvinculado."]);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error al revocar despacho: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error de base de datos al dar de baja la asignación."]);
    }
    exit;
}
