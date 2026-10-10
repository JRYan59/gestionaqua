<?php
// modules/despacho/asignar.php

$db = Database::conectar();

try {
    // 1. Obtener la lista de usuarios que tienen el rol de transportistas/despachadores
    // Nota: Como en tu estructura abreviada asocia 'transportista_id' con 'usuarios.id',
    // filtraremos los usuarios activos para el selector. En producción puedes agregar: WHERE rol = 'transportista'
    $stmt_usuarios = $db->query("SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre ASC");
    $transportistas = $stmt_usuarios->fetchAll();

    // 2. Obtener las zonas geográficas registradas activas
    $stmt_zonas = $db->query("SELECT id, nombre_zona, codigo_postal FROM zonas WHERE activo = 1 ORDER BY nombre_zona ASC");
    $zonas = $stmt_zonas->fetchAll();

    // 3. Obtener el listado simétrico de las asignaciones de rutas actualmente activas
    $query_asignaciones = "SELECT 
                            a.id AS asignacion_id,
                            u.nombre AS transportista_nombre,
                            z.nombre_zona,
                            z.codigo_postal,
                            a.fecha_asignacion
                           FROM asignacion_transportistas a
                           INNER JOIN usuarios u ON a.transportista_id = u.id
                           INNER JOIN zonas z ON a.zona_id = z.id
                           WHERE a.activo = 1
                           ORDER BY a.fecha_asignacion DESC";
    $asignaciones_activas = $db->query($query_asignaciones)->fetchAll();

} catch (PDOException $e) {
    error_log("Error en módulo despacho: " . $e->getMessage());
    echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg'>Error de seguridad al cargar los flujos logísticos.</div>";
    return;
}
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- COLUMNA 1 y 2: FORMULARIO DE ASIGNACIÓN (Simetría Visual) -->
    <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden h-fit">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <h3 class="font-bold text-gray-800 dark:text-white text-sm uppercase tracking-wider">Nueva Asignación de Ruta</h3>
        </div>
        
        <form id="form-asignar-despacho" class="p-6 space-y-5">
            <!-- Selector de Despachador -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Seleccionar Transportista</label>
                <select id="transportista_id" name="transportista_id" required class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    <option value="">-- Seleccione un transportista --</option>
                    <?php foreach ($transportistas as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Selector de Zona -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Zona Geográfica de Entrega</label>
                <select id="zona_id" name="zona_id" required class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    <option value="">-- Seleccione una zona de despacho --</option>
                    <?php foreach ($zonas as $z): ?>
                        <option value="<?php echo $z['id']; ?>">
                            <?php echo htmlspecialchars($z['nombre_zona']); ?> <?php echo $z['codigo_postal'] ? '('.htmlspecialchars($z['codigo_postal']).')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Botón de Envío -->
            <div class="pt-2">
                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-all shadow-sm flex items-center justify-center space-x-2">
                    <i data-lucide="shield-alert" class="w-4 h-4"></i>
                    <span>Vincular Ruta Segura</span>
                </button>
            </div>
        </form>
    </div>

    <!-- COLUMNA 3: MONITOR DE RUTAS ACTIVAS (Simetría e Historial) -->
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
            <h3 class="font-bold text-gray-800 dark:text-white text-sm uppercase tracking-wider">Rutas y Despachadores en Línea</h3>
            <span class="text-[10px] bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 px-2 py-0.5 rounded font-mono font-bold">LOGÍSTICA</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-100/50 dark:bg-gray-700/30 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="p-4">Transportista</th>
                        <th class="p-4">Zona Asignada</th>
                        <th class="p-4">Fecha Vinculación</th>
                        <th class="p-4 text-center">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <?php if (empty($asignaciones_activas)): ?>
                        <tr>
                            <td colspan="4" class="p-8 text-center text-gray-500 dark:text-gray-400 text-xs">
                                No hay transportistas vinculados a zonas de entrega actualmente.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($asignaciones_activas as $asig): ?>
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/20 transition-colors">
                                <td class="p-4 font-medium text-gray-900 dark:text-white">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 flex items-center justify-center text-[10px] font-bold">TR</div>
                                        <span><?php echo htmlspecialchars($asig['transportista_nombre']); ?></span>
                                    </div>
                                </td>
                                <td class="p-4 text-gray-600 dark:text-gray-300">
                                    <span class="font-semibold text-blue-600 dark:text-blue-400">
                                        <?php echo htmlspecialchars($asig['nombre_zona']); ?>
                                    </span>
                                    <span class="block text-[10px] text-gray-400">CP: <?php echo htmlspecialchars($asig['codigo_postal'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="p-4 text-gray-500 dark:text-gray-400 text-xs">
                                    <?php echo date('d/m/Y h:i A', strtotime($asig['fecha_asignacion'])); ?>
                                </td>
                                <td class="p-4 text-center">
                                    <!-- Botón para revocar/desvincular ruta de forma segura (Eliminación lógica) -->
                                    <button onclick="revocarRuta(<?php echo $asig['asignacion_id']; ?>)" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 rounded transition-all" title="Dar de baja asignación">
                                        <i data-lucide="user-x" class="w-4 h-4"></i>
                                    </button>
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

    // Procesar formulario asíncronamente con la API Fetch para no gastar recursos de recarga completa
    document.getElementById('form-asignar-despacho').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const t_id = document.getElementById('transportista_id').value;
        const z_id = document.getElementById('zona_id').value;

        fetch('modules/despacho/acciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=asignar&transportista_id=${t_id}&zona_id=${z_id}`
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') { location.reload(); }
        })
        .catch(err => {
            console.error(err);
            alert("Error crítico de red al intentar procesar la asignación.");
        });
    });

    // Función para cambiar de estado "activo = 0" (Baja lógica y auditoría)
    function revocarRuta(id) {
        if (confirm("¿Está seguro de que desea revocar esta asignación de transportista? El despachador dejará de cubrir esta zona de inmediato.")) {
            fetch('modules/despacho/acciones.php', {
                method: 'POST',
