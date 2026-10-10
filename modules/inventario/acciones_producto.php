<?php
// modules/inventario/acciones_producto.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
    exit;
}

require_once '../../config/conexion.php';
$db = Database::conectar();

$usuario_sesion_id = 1; 
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

$nombre = filter_input(INPUT_POST, 'nombre', FILTER_DEFAULT);
$categoria_id = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);
$codigo_barras = filter_input(INPUT_POST, 'codigo_barras', FILTER_DEFAULT);
$precio_usd = filter_input(INPUT_POST, 'precio_usd', FILTER_VALIDATE_FLOAT);
$stock_inicial = filter_input(INPUT_POST, 'stock_inicial', FILTER_VALIDATE_INT);

if (!$nombre || !$categoria_id || $precio_usd === false || $stock_inicial === false) {
    echo json_encode(["status" => "error", "message" => "Faltan campos obligatorios o los formatos numéricos son inválidos."]);
    exit;
}

try {
    $db->beginTransaction();

    // 1. Insertar el nuevo producto en la base de datos (Anti-Inyección)
    $stmt_prod = $db->prepare("INSERT INTO productos (categoria_id, nombre, codigo_barras, precio_usd, activo) VALUES (:cat_id, :nombre, :cod, :precio, 1)");
    $stmt_prod->execute([
        ':cat_id' => $categoria_id,
        ':nombre' => $nombre,
        ':cod'    => !empty($codigo_barras) ? $codigo_barras : null,
        ':precio' => $precio_usd
    ]);
    $producto_id = $db->lastInsertId();

    // 2. Crear la primera huella en tu tabla Kárdex real 'movimientos_inventario'
    $stmt_kardex = $db->prepare("INSERT INTO movimientos_inventario (producto_id, cantidad, saldo_anterior, saldo_nuevo, tipo_movimiento, motivo, usuario_id, fecha_movimiento) VALUES (:p_id, :cant, 0, :saldo_n, 'ENTRADA', 'Carga inicial por registro de nuevo producto', :u_id, NOW())");
    $stmt_kardex->execute([
        ':p_id'    => $producto_id,
        ':cant'    => $stock_inicial,
        ':saldo_n' => $stock_inicial,
        ':u_id'    => $usuario_sesion_id
    ]);

    // 3. Documentar en la bitácora de auditoría general (JSON)
    $datos_nuevos_json = json_encode([
        "producto_id"   => $producto_id,
        "nombre"        => $nombre,
        "precio_usd"    => $precio_usd,
        "stock_inicial" => $stock_inicial
    ]);

    $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, fecha_auditoria) VALUES (:u_id, 'REGISTRO_NUEVO_PRODUCTO_KARDEX', 'productos', :r_id, NULL, :d_nuevos, :ip, :ua, NOW())");
    $stmt_audit->execute([
        ':u_id'     => $usuario_sesion_id,
        ':r_id'     => $producto_id,
        ':d_nuevos' => $datos_nuevos_json,
        ':ip'       => $ip_address,
        ':ua'       => $user_agent
    ]);

    $db->commit();
    echo json_encode(["status" => "success", "message" => "¡Producto registrado en catálogo y stock inicial inyectado en el Kárdex con éxito!"]);

} catch (PDOException $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    error_log("Error al crear producto desde interfaz: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Error interno de base de datos al guardar el ítem."]);
}
exit;
