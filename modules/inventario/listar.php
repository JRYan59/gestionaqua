<?php
// modules/inventario/listar.php - Vista de Inventario & Kárdex
try {
    $db = Database::conectar();
    
    // Consulta corregida con LEFT JOIN a la tabla relacional de inventario
    $query = "SELECT p.*, 
                     COALESCE(i.stock_actual, 0) AS stock_actual, 
                     COALESCE(i.stock_disponible, 0) AS stock_disponible,
                     (SELECT MAX(fecha_movimiento) FROM movimientos_inventario WHERE producto_id = p.id) AS ultimo_movimiento
              FROM productos p 
              LEFT JOIN inventario i ON p.id = i.producto_id 
              ORDER BY p.id DESC";
              
    $stmt = $db->query($query);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $productos = [];
    $error_msg = $e->getMessage();
}
?>

<div class="space-y-6">
    <!-- Header del Módulo -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Inventario & Kárdex</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">Gestión de existencias y productos en tiempo real</p>
        </div>
        <a href="index.php?page=inventario_crear" class="inline-flex items-center space-x-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-xl shadow-md transition-all">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>Nuevo Producto</span>
        </a>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="p-4 bg-red-900/30 border border-red-800 text-red-300 rounded-xl text-xs">
            Error al cargar inventario: <?= htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <!-- Tabla de Productos -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                        <th class="p-4">Código</th>
                        <th class="p-4">Producto</th>
                        <th class="p-4">Categoría / Tipo</th>
                        <th class="p-4 text-center">Stock Actual</th>
                        <th class="p-4 text-right">Precio (USD)</th>
                        <th class="p-4 text-center">Último Movimiento</th>
                        <th class="p-4 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50 text-xs text-gray-700 dark:text-gray-300">
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-400">No hay productos registrados en el sistema.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $prod): ?>
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-all">
                                <td class="p-4 font-mono font-bold text-gray-900 dark:text-white">
                                    <?= htmlspecialchars($prod['codigo'] ?? 'N/A'); ?>
                                </td>
                                <td class="p-4 font-semibold text-gray-900 dark:text-white">
                                    <?= htmlspecialchars($prod['nombre'] ?? $prod['producto'] ?? ''); ?>
                                </td>
                                <td class="p-4">
                                    <div class="font-medium"><?= htmlspecialchars($prod['categoria'] ?? 'General'); ?></div>
                                    <div class="text-[10px] text-gray-400 capitalize"><?= htmlspecialchars($prod['tipo'] ?? 'Producto'); ?></div>
                                </td>
                                <td class="p-4 text-center">
                                    <?php $stock = (int)($prod['stock_actual'] ?? 0); ?>
                                    <span class="inline-flex items-center justify-center min-w-[32px] px-2 py-0.5 rounded-full text-xs font-bold <?= $stock > 0 ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'; ?>">
                                        <?= $stock; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right font-mono font-bold text-gray-900 dark:text-white">
                                    $<?= number_format((float)($prod['precio_usd'] ?? $prod['precio'] ?? 0), 2); ?>
                                </td>
                                <td class="p-4 text-center text-gray-400 text-[11px]">
                                    <?= !empty($prod['ultimo_movimiento']) ? date('d/m/Y h:i A', strtotime($prod['ultimo_movimiento'])) : 'Sin movimientos'; ?>
                                </td>
                                
                                <!-- BOTONES DE ACCIÓN FUNCIONALES -->
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <!-- 1. Botón Ajustar Stock / Movimiento -->
                                        <button onclick="abrirModalStock(<?= $prod['id']; ?>, '<?= htmlspecialchars($prod['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?= $stock; ?>)" 
                                                class="p-1.5 text-blue-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/40 rounded-lg transition-all" 
                                                title="Ajustar Stock / Movimiento">
                                            <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
                                        </button>

                                        <!-- 2. Botón Editar Producto (Apuntando a la vista del index) -->
                                        <a href="index.php?page=inventario_editar&id=<?= $prod['id']; ?>" 
                                           class="p-1.5 text-amber-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 rounded-lg transition-all" 
                                           title="Editar Producto">
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </a>

                                        <!-- 3. Botón Suspender / Eliminar (Apuntando a acciones_producto.php) -->
                                        <button onclick="confirmarSuspender(<?= $prod['id']; ?>, '<?= htmlspecialchars($prod['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')" 
                                                class="p-1.5 text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-all" 
                                                title="Suspender / Eliminar">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================== MODAL AJUSTE DE STOCK ==================== -->
<div id="modal-stock" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white dark:bg-gray-800 w-full max-w-sm rounded-2xl border border-gray-200 dark:border-gray-700 shadow-2xl p-6 space-y-4">
        <div class="flex justify-between items-center border-b border-gray-100 dark:border-gray-700 pb-3">
            <h3 class="font-bold text-gray-800 dark:text-white text-sm">Ajuste de Stock</h3>
            <button onclick="cerrarModalStock()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form action="modules/inventario/ajustar_stock.php" method="POST" class="space-y-4">
            <input type="hidden" name="producto_id" id="stock_prod_id">

            <div>
                <p id="stock_prod_nombre" class="text-xs font-semibold text-blue-500 truncate mb-1"></p>
                <p class="text-[11px] text-gray-400">Stock Actual: <span id="stock_prod_actual" class="font-bold text-gray-700 dark:text-gray-200"></span></p>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Tipo de Movimiento</label>
                <select name="tipo_movimiento" class="w-full p-2.5 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="entrada">Entrada (+ Sumar Stock)</option>
                    <option value="salida">Salida (- Restar Stock)</option>
                    <option value="ajuste">Ajuste Manual (Establecer Fijo)</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Cantidad</label>
                <input type="number" name="cantidad" min="1" required class="w-full p-2.5 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-xs font-bold text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Motivo / Observación</label>
                <input type="text" name="motivo" placeholder="Ej: Inventario inicial, daño de empaque..." class="w-full p-2.5 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" onclick="cerrarModalStock()" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs font-semibold rounded-xl">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-xl shadow-md">Guardar Movimiento</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== JAVASCRIPT DE ACCIONES ==================== -->
<script>
    // Inicializar íconos de Lucide tras renderizar
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Modal Ajuste de Stock
    function abrirModalStock(id, nombre, stockActual) {
        document.getElementById('stock_prod_id').value = id;
        document.getElementById('stock_prod_nombre').innerText = nombre;
        document.getElementById('stock_prod_actual').innerText = stockActual;
        document.getElementById('modal-stock').classList.remove('hidden');
    }

    function cerrarModalStock() {
        document.getElementById('modal-stock').classList.add('hidden');
    }

    // Confirmación para Suspender / Eliminar corregida a acciones_producto.php
    function confirmarSuspender(id, nombre) {
        if (confirm(`¿Estás seguro de que deseas suspender o eliminar el producto "${nombre}"?`)) {
            window.location.href = `modules/inventario/acciones_producto.php?action=eliminar&id=${id}`;
        }
    }
</script>