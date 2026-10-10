<?php
// modules/pedidos/listar.php

$db = Database::conectar();

try {
    // 1. Obtener la tasa de cambio actual de la empresa para los cálculos en pantalla
    $stmt_tasa = $db->query("SELECT tasa_cambio_usd FROM configuracion_empresa LIMIT 1");
    $empresa = $stmt_tasa->fetch();
    $tasa_actual = $empresa ? (float)$empresa['tasa_cambio_usd'] : 36.50;

    // 2. Consulta optimizada de pedidos (en un escenario real unirías con la tabla clientes/usuarios)
    // Nota: Como en tu estructura abreviada no venía el campo 'estado', asumiremos un campo simulado o controlado por lógica.
    $query = "SELECT id, factura_nro, fecha_emision, total_usd, total_bs, estado 
              FROM pedidos 
              ORDER BY id DESC";
    
    // Para evitar fallos si aún no migras la columna 'estado' a tu tabla pedidos, controlamos la consulta de forma segura:
    $query = "SELECT id, total_usd, total_bs, created_at, 'Pendiente' AS estado FROM detalle_pedidos GROUP BY pedido_id ORDER BY id DESC"; 
    // NOTA: Ajusta la query superior cuando tu tabla 'pedidos' principal esté llena. Usaremos esta de prueba limpia:
    $stmt_pedidos = $db->query("SELECT p.*, 
                                (SELECT SUM(subtotal_usd) FROM detalle_pedidos WHERE pedido_id = p.id) as calc_usd 
                                FROM pedidos p ORDER BY p.id DESC");
    $pedidos = $stmt_pedidos->fetchAll();

} catch (PDOException $e) {
    error_log("Error en listar pedidos: " . $e->getMessage());
    $pedidos = [];
    $tasa_actual = 244.00; // Tasa de respaldo según tu volcado de datos
}
?>

<div class="space-y-6">
    <!-- Encabezado de sección con Botón de Acción Principal -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div>
            <h3 class="font-bold text-lg text-gray-800 dark:text-white">Historial de Pedidos</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tasa de cambio monitoreada: <span class="font-semibold text-blue-600 dark:text-blue-400">1 USD = <?php echo number_format($tasa_actual, 2); ?> Bs.</span></p>
        </div>
        <a href="index.php?page=pedidos_crear" class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-all shadow-sm">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Nuevo Pedido</span>
        </a>
    </div>

    <!-- Tabla Simétrica de Pedidos -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-100/50 dark:bg-gray-700/30 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="p-4">ID Pedido</th>
                        <th class="p-4">Fecha / Hora</th>
                        <th class="p-4 text-center">Estado</th>
                        <th class="p-4 text-right">Monto Ref. (USD)</th>
                        <th class="p-4 text-right">Monto Total (Bs.)</th>
                        <th class="p-4 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500 dark:text-gray-400">
                                No se han registrado pedidos en el sistema actualmente.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $pedido): ?>
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/20 transition-colors">
                                <td class="p-4 font-mono font-semibold text-gray-700 dark:text-gray-300">
                                    #<?php echo str_pad($pedido['id'], 6, '0', STR_PAD_LEFT); ?>
                                </td>
                                <td class="p-4 text-gray-600 dark:text-gray-300 text-xs">
                                    <?php echo date('d/m/Y h:i A', strtotime($pedido['fecha_pedido'] ?? $pedido['created_at'] ?? 'now')); ?>
                                </td>
                                <td class="p-4 text-center">
                                    <!-- Insignia de estado simétrica -->
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                        Pendiente
                                    </span>
                                </td>
                                <td class="p-4 text-right font-semibold text-blue-600 dark:text-blue-400">
                                    $<?php echo number_format($pedido['total_usd'] ?? 0, 2); ?>
                                </td>
                                <td class="p-4 text-right font-semibold text-gray-900 dark:text-white">
                                    <?php echo number_format($pedido['total_bs'] ?? 0, 2); ?> Bs
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <!-- Botón Modificar -->
                                        <a href="index.php?page=pedidos_editar&id=<?php echo $pedido['id']; ?>" class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-all" title="Modificar Pedido">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </a>
                                        <!-- Botón Anular (Eliminar Lógico) -->
                                        <button onclick="anularPedido(<?php echo $pedido['id']; ?>)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 rounded transition-all" title="Anular Pedido">
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

<script>
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    function anularPedido(id) {
        if (confirm(`¿Está seguro de que desea anular el pedido #${id}? Esta acción devolverá los productos al inventario disponible de forma automática y auditará el proceso.`)) {
            // Aquí se llamará mediante Fetch asíncrono a acciones.php para optimizar recursos
            fetch('modules/pedidos/acciones.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `accion=anular&pedido_id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') { location.reload(); }
            });
        }
    }
</script>
