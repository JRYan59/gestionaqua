<?php
// modules/pedidos/acciones.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
    exit;
}

require_once '../../config/conexion.php';
$db = Database::conectar();

// Parámetros fijos temporales (Alineados con el Administrador ID=1 del PDF)
$usuario_sesion_id = 1; 
$cliente_id_defecto = 1; // Tu tabla pedidos exige un cliente_id que exista en usuarios
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

$input_raw = file_get_contents('php://input');
$data_json = json_decode($input_raw, true);

$accion = '';
if ($data_json && isset($data_json['accion'])) {
    $accion = $data_json['accion'];
} elseif (isset($_POST['accion'])) {
    $accion = $_POST['accion'];
}

if (empty($accion)) {
    echo json_encode(["status" => "error", "message" => "Acción no especificada."]);
    exit;
}

// ==========================================
// ACCIÓN A: CREAR PEDIDO CON DISPARADORES
// ==========================================
if ($accion === 'crear') {
    $items = $data_json['items'] ?? [];
    $metodo_pago_raw = $data_json['metodo_pago'] ?? 'EFECTIVO_USD';
    $referencia_pago = filter_var($data_json['referencia_pago'] ?? '', FILTER_DEFAULT);

    if (empty($items)) {
        echo json_encode(["status" => "error", "message" => "El pedido no contiene productos."]);
        exit;
    }

    // Convertir el método de pago al ENUM exacto en minúsculas exigido por tu base de datos
    $metodo_pago = strtolower($metodo_pago_raw); // 'efectivo_usd', 'pago_movil', etc.

    try {
        // Obtener tasa de cambio oficial de tu tabla configuracion_empresa
        $stmt_tasa = $db->query("SELECT tasa_cambio_usd FROM configuracion_empresa LIMIT 1");
        $empresa = $stmt_tasa->fetch();
        $tasa_actual = $empresa ? (float)$empresa['tasa_cambio_usd'] : 244.00;

        $db->beginTransaction();

        // 1. Calcular totales preliminares en memoria para rellenar la cabecera obligatoria
        $total_general_usd = 0;
        foreach ($items as $item) {
            $total_general_usd += (float)$item['precio'] * (int)$item['cantidad'];
        }
        $total_general_bs = $total_general_usd * $tasa_actual;

        // 2. Insertar cabecera respetando restricciones NOT NULL del PDF
        // Dejamos que el trigger 'tr_pedido_before_insert' genere el numero_pedido automáticamente
        $query_pedido = "INSERT INTO pedidos 
            (cliente_id, estado, direccion_entrega, subtotal_usd, subtotal_bs, total_usd, total_bs, metodo_pago, monto_recibido_bs, fecha_pedido) 
            VALUES 
            (:cliente_id, 'pendiente', 'Despacho de Almacén Central', :sub_usd, :sub_bs, :tot_usd, :tot_bs, :metodo, :recibido_bs, NOW())";
            
        $stmt_pedido = $db->prepare($query_pedido);
        $stmt_pedido->execute([
            ':cliente_id'  => $cliente_id_defecto,
            ':sub_usd'     => $total_general_usd,
            ':sub_bs'      => $total_general_bs,
            ':tot_usd'     => $total_general_usd,
            ':tot_bs'      => $total_general_bs,
            ':metodo'      => $metodo_pago,
            ':recibido_bs' => ($metodo_pago === 'pago_movil' || $metodo_pago === 'transferencia') ? $total_general_bs : 0.00
        ]);
        $pedido_id = $db->lastInsertId();

        // Sentencias preparadas reutilizables para el kárdex real
        $stmt_get_saldo = $db->prepare("SELECT saldo_nuevo FROM movimientos_inventario WHERE producto_id = :p_id ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $stmt_detalle = $db->prepare("INSERT INTO detalle_pedidos (pedido_id, producto_id, cantidad, precio_unitario_usd, precio_unitario_bs, subtotal_usd, subtotal_bs) VALUES (:pedido_id, :producto_id, :cantidad, :pu_usd, 0, 0, 0)"); // El trigger tr_detalle_pedido_before_insert autocalcula los campos en cero
        $stmt_movimiento = $db->prepare("INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, motivo, referencia, usuario_id, saldo_anterior, saldo_nuevo, fecha_movimiento) VALUES (:producto_id, 'salida', :cantidad, :motivo, :referencia, :usuario_id, :saldo_ant, :saldo_nue, NOW())");

        // 3. Iterar y procesar cada artículo vendido
        foreach ($items as $item) {
            $producto_id = (int)$item['id'];
            $cantidad_pedida = (int)$item['cantidad'];
            $precio_usd = (float)$item['precio'];

            // Consultar balance matemático en tu tabla movimientos_inventario
            $stmt_get_saldo->execute([':p_id' => $producto_id]);
            $last_mov = $stmt_get_saldo->fetch();
            $saldo_anterior = $last_mov ? (int)$last_mov['saldo_nuevo'] : 0;

            if ($saldo_anterior < $cantidad_pedida) {
                throw new Exception("Operación rechazada: Stock insuficiente para el producto ID $producto_id. Disponible en Kárdex: $saldo_anterior");
            }

            // Insertar detalle. El trigger 'tr_detalle_pedido_before_insert' rellenará los subtotales en Bs de forma automática.
            $stmt_detalle->execute([
                ':pedido_id'   => $pedido_id,
                ':producto_id' => $producto_id,
                ':cantidad'    => $cantidad_pedida,
                ':pu_usd'      => $precio_usd
            ]);

            $saldo_nuevo = $saldo_anterior - $cantidad_pedida;

            // Registrar movimiento de salida. El trigger 'tr_movimiento_entrada' (bloque salida) 
            // descontará automáticamente el stock físico de tu tabla 'inventario'
            $stmt_movimiento->execute([
                ':producto_id'    => $producto_id,
                ':cantidad'       => $cantidad_pedida,
                ':motivo'         => "Despacho automatizado por Venta Factura #$pedido_id",
                ':referencia'     => !empty($referencia_pago) ? $referencia_pago : 'Efectivo',
                ':usuario_id'     => $usuario_sesion_id,
                ':saldo_ant'      => $saldo_anterior,
                ':saldo_nue'      => $saldo_nuevo
            ]);
        }

        // 4. Inyectar registro JSON en la bitácora de auditoría general
        $datos_nuevos_json = json_encode([
            "pedido_id"    => $pedido_id,
            "total_usd"    => $total_general_usd,
            "metodo_pago"  => $metodo_pago,
            "referencia"   => !empty($referencia_pago) ? $referencia_pago : 'N/A'
        ]);

        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, fecha_auditoria) VALUES (:u_id, 'VENTA_PROCESADA_CON_TRIGGERS', 'pedidos', :r_id, NULL, :d_nuevos, :ip, NOW())");
        $stmt_audit->execute([
            ':u_id'     => $usuario_sesion_id,
            ':r_id'     => $pedido_id,
            ':d_nuevos' => $datos_nuevos_json,
            ':ip'       => $ip_address
        ]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "¡Venta guardada correctamente! Los disparadores automáticos de la base de datos balancearon el stock."]);

    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error de automatización en pedidos: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error de Base de Datos: " . $e->getMessage()]);
    }
    exit;
}

// ==========================================
// ACCIÓN B: ANULAR PEDIDO Y REINTEGRAR
// ==========================================
if ($accion === 'anular') {
    $pedido_id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);

    if (!$pedido_id) {
        echo json_encode(["status" => "error", "message" => "Parámetro de anulación inválido."]);
        exit;
    }

    try {
        $db->beginTransaction();

        // Extraer los ítems para calcular la devolución en el Kárdex
        $stmt_detalle = $db->prepare("SELECT producto_id, cantidad FROM detalle_pedidos WHERE pedido_id = :pedido_id");
        $stmt_detalle->execute([':pedido_id' => $pedido_id]);
        $items_anular = $stmt_detalle->fetchAll();

        $stmt_get_saldo = $db->prepare("SELECT saldo_nuevo FROM movimientos_inventario WHERE producto_id = :p_id ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $stmt_reintegrar = $db->prepare("INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, motivo, usuario_id, saldo_anterior, saldo_nuevo, fecha_movimiento) VALUES (:producto_id, 'entrada', :cantidad, :motivo, :usuario_id, :saldo_ant, :saldo_nue, NOW())");

        foreach ($items_anular as $item) {
            $p_id = (int)$item['producto_id'];
            $cant = (int)$item['cantidad'];

            $stmt_get_saldo->execute([':p_id' => $p_id]);
            $last_mov = $stmt_get_saldo->fetch();
            $saldo_anterior = $last_mov ? (int)$last_mov['saldo_nuevo'] : 0;
            $saldo_nuevo = $saldo_anterior + $cant;

            // Insertar como 'entrada'. El trigger tr_movimiento_entrada sumará el stock físico a la tabla inventario
            $stmt_reintegrar->execute([
                ':producto_id' => $p_id,
                ':cantidad'    => $cant,
                ':motivo'      => "Reintegro automático por anulación de Orden #$pedido_id",
                ':usuario_id'  => $usuario_sesion_id,
                ':saldo_ant'   => $saldo_anterior,
                ':saldo_nue'   => $saldo_nuevo
            ]);
        }

        // Remover de la tabla pedidos (El ON DELETE CASCADE limpia los detalles automáticamente)
        $stmt_delete = $db->prepare("DELETE FROM pedidos WHERE id = :pedido_id");
        $stmt_delete->execute([':pedido_id' => $pedido_id]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "Orden anulada con éxito. Los disparadores restituyeron los balances del inventario."]);

    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
