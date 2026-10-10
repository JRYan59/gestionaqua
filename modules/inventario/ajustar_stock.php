<?php
session_start();
require_once '../../config/conexion.php';

// Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id     = (int)($_POST['producto_id'] ?? 0);
    $tipo_movimiento = trim($_POST['tipo_movimiento'] ?? '');
    $cantidad        = (int)($_POST['cantidad'] ?? 0);
    $motivo          = trim($_POST['motivo'] ?? 'Ajuste manual de inventario'); // Añadimos motivo
    $usuario_id      = $_SESSION['usuario_id']; // ID del usuario en sesión para la auditoría

    if ($producto_id <= 0 || $cantidad <= 0 || !in_array($tipo_movimiento, ['entrada', 'salida', 'ajuste'])) {
        header('Location: ../../index.php?page=inventario&error=datos_invalidos');
        exit();
    }

    try {
        $db = Database::conectar();

        // Iniciamos una transacción para asegurar la integridad de ambas tablas
        $db->beginTransaction();

        // 1. Obtener el stock actual desde la tabla 'inventario'
        $stmt = $db->prepare("SELECT stock_actual FROM inventario WHERE producto_id = ? LIMIT 1");
        $stmt->execute([$producto_id]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si el producto no tiene registro en la tabla inventario, lo inicializamos en 0
        $saldo_anterior = $inv ? (int)$inv['stock_actual'] : 0;
        $saldo_nuevo    = $saldo_anterior;

        // 2. Calcular nuevo stock según tipo de movimiento
        if ($tipo_movimiento === 'entrada') {
            $saldo_nuevo = $saldo_anterior + $cantidad;
        } elseif ($tipo_movimiento === 'salida') {
            $saldo_nuevo = $saldo_anterior - $cantidad;
            // Evitar que el stock sea menor a cero en una salida normal
            if ($saldo_nuevo < 0) {
                header('Location: ../../index.php?page=inventario&error=stock_insuficiente');
                exit();
            }
        } elseif ($tipo_movimiento === 'ajuste') {
            $saldo_nuevo = $cantidad;
        }

        // 3. Actualizar o Insertar en la tabla 'inventario'
        if ($inv) {
            // Si ya existe el registro, actualizamos el stock y registramos la fecha de última entrada si aplica
            if ($tipo_movimiento === 'entrada') {
                $sql_inv = "UPDATE inventario SET stock_actual = ?, ultima_entrada = CURDATE() WHERE producto_id = ?";
            } else {
                $sql_inv = "UPDATE inventario SET stock_actual = ? WHERE producto_id = ?";
            }
            $stmt_inv = $db->prepare($sql_inv);
            $stmt_inv->execute([$saldo_nuevo, $producto_id]);
        } else {
            // Si por alguna razón no existía la fila del producto en inventario, la creamos
            $ultima_entrada = ($tipo_movimiento === 'entrada') ? 'CURDATE()' : 'NULL';
            $sql_inv = "INSERT INTO inventario (producto_id, stock_actual, stock_reservado, ultima_entrada) VALUES (?, ?, 0, $ultima_entrada)";
            $stmt_inv = $db->prepare($sql_inv);
            $stmt_inv->execute([$producto_id, $saldo_nuevo]);
        }

        // 4. Registrar el historial en 'movimientos_inventario'
        $sql_mov = "INSERT INTO movimientos_inventario 
                    (producto_id, tipo_movimiento, cantidad, motivo, usuario_id, saldo_anterior, saldo_nuevo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_mov = $db->prepare($sql_mov);
        $stmt_mov->execute([
            $producto_id,
            $tipo_movimiento,
            $cantidad,
            $motivo,
            $usuario_id,
            $saldo_anterior,
            $saldo_nuevo
        ]);

        // Si todo salió bien, confirmamos los cambios en la BD
        $db->commit();

        header('Location: ../../index.php?page=inventario&status=stock_actualizado');
        exit();

    } catch (Exception $e) {
        // Si algo falla, revertimos cualquier cambio incompleto
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        die("Error al actualizar el stock: " . $e->getMessage());
    }
} else {
    header('Location: ../../index.php?page=inventario');
    exit();
}
?>