<?php
// modules/usuarios/listar.php
$db = Database::conectar();

try {
    // Consulta adaptada a tus columnas reales del PDF
    $stmt = $db->query("SELECT id, nombre, apellido, email, telefono, rol, activo FROM usuarios WHERE activo = 1 ORDER BY rol ASC, nombre ASC");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error al listar usuarios reales: " . $e->getMessage());
    $usuarios = [];
}
?>

<div class="space-y-6">
    <!-- Encabezado simétrico -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div>
            <h3 class="font-bold text-lg text-gray-800 dark:text-white">Control de Personal y Roles</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Gestión de accesos integrada con el sistema de despacho y pedidos.</p>
        </div>
        <a href="index.php?page=usuarios_crear" class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-all shadow-sm">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span>Nuevo Personal</span>
        </a>
    </div>

    <!-- Tabla Simétrica de Usuarios -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-100/50 dark:bg-gray-700/30 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="p-4">Nombre Completo</th>
                        <th class="p-4">Correo Electrónico (Login)</th>
                        <th class="p-4">Teléfono</th>
                        <th class="p-4 text-center">Rol Asignado</th>
                        <th class="p-4 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <?php if (empty($usuarios)) { ?>
                        <tr><td colspan="5" class="p-8 text-center text-gray-500 dark:text-gray-400">No hay usuarios activos registrados en el sistema.</td></tr>
                    <?php } else { 
                        foreach ($usuarios as $usr) { ?>
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/20 transition-colors">
                                <td class="p-4 font-medium text-gray-900 dark:text-white">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-xs uppercase">
                                            <?= substr($usr['nombre'], 0, 1) . substr($usr['apellido'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <span class="block font-semibold"><?= htmlspecialchars($usr['nombre'] . ' ' . $usr['apellido']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 font-mono text-xs text-gray-600 dark:text-gray-400"><?= htmlspecialchars($usr['email']); ?></td>
                                <td class="p-4 text-gray-600 dark:text-gray-300"><?= htmlspecialchars($usr['telefono'] ?? 'Sin teléfono'); ?></td>
                                <td class="p-4 text-center">
                                    <!-- Insignia simétrica según el ENUM exacto de tu DB -->
                                    <?php if ($usr['rol'] === 'administrador') { ?>
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 capitalize">Admin</span>
                                    <?php } elseif ($usr['rol'] === 'transportista') { ?>
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 capitalize">Despachador</span>
                                    <?php } else { ?>
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 capitalize">Cliente</span>
                                    <?php } ?>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <a href="index.php?page=usuarios_editar&id=<?= $usr['id']; ?>" class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-all"><i data-lucide="edit-3" class="w-4 h-4"></i></a>
                                        <button onclick="darDeBajaUsuario(<?= $usr['id']; ?>, '<?= htmlspecialchars($usr['nombre']); ?>')" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 rounded transition-all"><i data-lucide="user-x" class="w-4 h-4"></i></button>
                                    </div>
                                </td>
                            </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }
    function darDeBajaUsuario(id, nombre) {
        if (confirm(`¿Desactivar el acceso a "${nombre}"? El proceso quedará registrado en la auditoría.`)) {
            fetch('modules/usuarios/acciones.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `accion=desactivar&usuario_id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') { location.reload(); }
            });
        }
    }
</script>
