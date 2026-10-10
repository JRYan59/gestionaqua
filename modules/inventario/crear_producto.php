<?php
// modules/inventario/crear_producto.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/conexion.php';

$mensaje_error = '';

// 1. Obtener listado de categorías activas
$categorias = [];
try {
    $db = Database::conectar();
    $stmtCat = $db->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre ASC");
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje_error = "Error al cargar categorías: " . $e->getMessage();
}

// 2. Procesar el Formulario cuando se envía (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo         = trim($_POST['codigo'] ?? '');
    $nombre         = trim($_POST['nombre'] ?? '');
    $categoria_id   = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);
    $tipo           = trim($_POST['tipo'] ?? 'producto');
    
    // Normalizar formato numérico ("0,7" -> "0.7")
    $raw_precio     = str_replace(',', '.', $_POST['precio_usd'] ?? '0');
    $precio_usd     = filter_var($raw_precio, FILTER_VALIDATE_FLOAT);
    $stock_inicial  = filter_input(INPUT_POST, 'stock_inicial', FILTER_VALIDATE_INT) ?? 0;

    if (empty($codigo) || empty($nombre) || $precio_usd === false) {
        $mensaje_error = "Por favor completa los campos obligatorios (*).";
    } else {
        try {
            $db->beginTransaction();

            // Insertar en la tabla 'productos' según el esquema oficial
            // (El TRIGGER tr_producto_after_insert creará automáticamente la fila en 'inventario')
            $stmtProd = $db->prepare("
                INSERT INTO productos (codigo, nombre, categoria_id, precio_usd, tipo) 
                VALUES (:codigo, :nombre, :cat, :precio, :tipo)
            ");
            $stmtProd->execute([
                ':codigo' => $codigo,
                ':nombre' => $nombre,
                ':cat'    => $categoria_id ?: null,
                ':precio' => $precio_usd,
                ':tipo'   => $tipo
            ]);

            $producto_id = $db->lastInsertId();

            // Si se definió un stock inicial mayor a 0, registramos el movimiento de entrada
            // El TRIGGER tr_movimiento_entrada actualizará 'stock_actual' en 'inventario'
            if ($stock_inicial > 0) {
                $stmtMov = $db->prepare("
                    INSERT INTO movimientos_inventario 
                    (producto_id, tipo_movimiento, cantidad, motivo, usuario_id, fecha_movimiento)
                    VALUES (:p, 'entrada', :cant, 'Carga de Stock Inicial', :u, NOW())
                ");
                $stmtMov->execute([
                    ':p'    => $producto_id,
                    ':cant' => $stock_inicial,
                    ':u'    => $_SESSION['usuario_id'] ?? 1
                ]);
            }

            $db->commit();

            // Redirección interna
            echo "<script>window.location.href = 'index.php?page=inventario&status=updated';</script>";
            exit();

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $mensaje_error = "Error de BD: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-2xl mx-auto space-y-6">

    <!-- Encabezado -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Registrar Nuevo Producto</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Estructura vinculada a la base de datos</p>
        </div>
        <a href="index.php?page=inventario" class="inline-flex items-center space-x-2 px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Volver al Kárdex</span>
        </a>
    </div>

    <!-- Alerta de Error de BD si ocurre -->
    <?php if (!empty($mensaje_error)): ?>
        <div class="p-4 bg-red-950/40 border border-red-800 text-red-300 rounded-xl text-xs flex items-center space-x-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0 text-red-400"></i>
            <span><?= htmlspecialchars($mensaje_error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Formulario Principal -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
        <form action="index.php?page=inventario_crear" method="POST" class="space-y-4">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Campo: codigo -->
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Código / Referencia *</label>
                    <input type="text" name="codigo" required placeholder="Ej. BOT-20LR" value="<?= htmlspecialchars($_POST['codigo'] ?? ''); ?>"
                           class="w-full p-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none font-mono">
                </div>

                <!-- Campo: categoria_id -->
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Categoría</label>
                    <select name="categoria_id" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="">-- Seleccionar Categoría --</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id']; ?>" <?= (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Campo: nombre -->
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Nombre Completo del Ítem *</label>
                <input type="text" name="nombre" required placeholder="Ej. Recarga Botellón 20 Litros" value="<?= htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                       class="w-full p-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Campo: precio_usd -->
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Precio Ref. (USD) *</label>
                    <input type="text" name="precio_usd" required placeholder="0.70" value="<?= htmlspecialchars($_POST['precio_usd'] ?? ''); ?>"
                           class="w-full p-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none font-mono">
                </div>

                <!-- Campo: tipo (ENUM) -->
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Tipo de Ítem</label>
                    <select name="tipo" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="producto">Producto (Físico)</option>
                        <option value="servicio">Servicio (Llenado)</option>
                    </select>
                </div>

                <!-- Stock Inicial -->
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Stock Inicial</label>
                    <input type="number" min="0" name="stock_inicial" placeholder="0" value="<?= htmlspecialchars($_POST['stock_inicial'] ?? '0'); ?>"
                           class="w-full p-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none font-mono">
                </div>
            </div>

            <!-- Botón de Envío -->
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl transition-all shadow-lg hover:shadow-blue-600/25 flex items-center justify-center space-x-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Guardar Producto en Sistema</span>
                </button>
            </div>

        </form>
    </div>

</div>

<script>
    lucide.createIcons();
</script>