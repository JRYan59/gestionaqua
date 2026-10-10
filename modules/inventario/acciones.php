<?php
// modules/inventario/acciones.php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id     = filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);
    $tipo_movimiento = $_POST['tipo_movimiento'] ?? 'entrada';
    $cantidad        = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
    $motivo          = trim($_POST['motivo'] ?? 'Carga manual de stock');
    $usuario_id      = $_SESSION['usuario_id'];

    if ($producto_id && $cantidad > 0) {
        try {
            $db = Database::conectar();

            // Insertar en movimientos_inventario (el TRIGGER tr_movimiento_entrada actualizará la tabla inventario automáticamente)
            $stmt = $db->prepare("
                INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, motivo, usuario_id, fecha_movimiento)
                VALUES (:p, :t, :c, :m, :u, NOW())
            ");
            $stmt->execute([
                ':p' => $producto_id,
                ':t' => $tipo_movimiento,
                ':c' => $cantidad,
                ':m' => $motivo,
                ':u' => $usuario_id
            ]);

            header('Location: ../../index.php?page=inventario&status=updated');
            exit();

        } catch (PDOException $e) {
            die("Error al actualizar inventario: " . $e->getMessage());
        }
    }
}

header('Location: ../../index.php?page=inventario');
exit();
?>