<?php
// modules/auditoria/listar.php

$db = Database::conectar();

try {
    // Consulta optimizada uniendo la bitácora con los nombres reales de los usuarios que hicieron la acción
    $query = "SELECT 
                a.id,
                u.nombre AS usuario_nombre,
                a.accion,
                a.tabla_afectada,
                a.registro_id,
                a.datos_anteriores,
                a.datos_nuevos,
                a.ip_address,
                a.fecha_auditoria
              FROM auditoria a
              LEFT JOIN usuarios u ON a.usuario_id = u.id
              ORDER BY a.fecha_auditoria DESC 
              LIMIT 50"; // Limitamos a los últimos 50 movimientos para no saturar memoria RAM del servidor
              
    $stmt = $db->query($query);
    $registros = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error en bitácora de auditoría: " . $e->getMessage());
    $registros = [];
}
?>

<div class="space-y-6">
    <!-- Encabezado del Módulo -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <h3 class="font-bold text-lg text-gray-800 dark:text-white">Bitácora de Auditoría del Sistema</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Historial inmutable de operaciones del motor relacional. Rastreo en tiempo real.</p>
    </div>

    <!-- Tabla Simétrica de Eventos -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-100/50 dark:bg-gray-700/30 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="p-4">Fecha / Hora</th>
                        <th class="p-4">Usuario Responsable</th>
                        <th class="p-4">Acción Ejecutada</th>
                        <th class="p-4">Tabla / Reg. ID</th>
                        <th class="p-4 text-center">Metadatos (JSON)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500 dark:text-gray-400 text-xs">
                                Ningún evento registrado en la bitácora de auditoría todavía.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registros as $reg): ?>
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/20 transition-colors">
                                <td class="p-4 font-mono text-xs text-gray-600 dark:text-gray-400">
                                    <?php echo date('d/m/Y h:i:A', strtotime($reg['fecha_auditoria'])); ?>
                                </td>
                                <td class="p-4 font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($reg['usuario_nombre'] ?? 'Sistema / Automatización'); ?>
                                    <span class="block text-[10px] font-normal text-gray-400">IP: <?php echo htmlspecialchars($reg['ip_address']); ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="inline-block px-2 py-0.5 bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400 rounded text-xs font-mono font-bold">
                                        <?php echo htmlspecialchars($reg['accion']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-gray-600 dark:text-gray-300 text-xs">
                                    <span class="capitalize font-semibold"><?php echo htmlspecialchars($reg['tabla_afectada']); ?></span>
                                    <span class="block text-[10px] text-gray-400">Registro ID: #<?php echo $reg['registro_id']; ?></span>
                                </td>
                                <td class="p-4 text-center">
                                    <!-- Botones simétricos para desplegar el contenido JSON sin recargar páginas -->
                                    <button 
                                        onclick="abrirModalInspector('<?php echo addslashes($reg['datos_anteriores'] ?? '{}'); ?>', '<?php echo addslashes($reg['datos_nuevos'] ?? '{}'); ?>', '<?php echo $reg['accion']; ?>')"
                                        class="px-3 py-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-xs font-medium rounded transition-all">
                                        Inspeccionar
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

<!-- VENTANA MODAL FLUIDA Y SIMÉTRICA (Invisible por defecto, se activa con JS) -->
<div id="modal-inspector" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm transition-all">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-xl border border-gray-200 dark:border-gray-700 shadow-xl flex flex-col max-h-[85vh]">
        <!-- Cabecera Modal -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
            <h4 class="font-bold text-gray-800 dark:text-white text-sm uppercase font-mono tracking-tight" id="modal-titulo">Análisis del Movimiento</h4>
            <button onclick="cerrarModalInspector()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <!-- Cuerpo Modal (Scrollable para objetos JSON extensos) -->
        <div class="p-6 overflow-y-auto space-y-4 text-xs font-mono">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Bloque datos anteriores -->
                <div>
                    <span class="block font-sans text-xs font-semibold text-gray-500 uppercase mb-2">Estado Anterior (JSON)</span>
                    <pre id="code-anterior" class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg text-red-600 dark:text-red-400 overflow-x-auto border border-gray-200 dark:border-gray-700"></pre>
                </div>
                <!-- Bloque datos nuevos -->
                <div>
                    <span class="block font-sans text-xs font-semibold text-gray-500 uppercase mb-2">Estado Resultante (JSON)</span>
                    <pre id="code-nuevo" class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg text-green-600 dark:text-green-400 overflow-x-auto border border-gray-200 dark:border-gray-700"></pre>
                </div>
            </div>
        </div>
        <!-- Cierre Modal -->
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 text-right">
            <button onclick="cerrarModalInspector()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500 text-white text-xs font-medium rounded-lg transition-all">Cerrar Inspector</button>
        </div>
    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    const modal = document.getElementById('modal-inspector');
    const titulo = document.getElementById('modal-titulo');
    const codeAnterior = document.getElementById('code-anterior');
    const codeNuevo = document.getElementById('code-nuevo');

    function abrirModalInspector(anteriorRaw, nuevoRaw, accionNombre) {
        titulo.innerText = `Inspector: ${accionNombre}`;
        
        // Convertir strings a JSON estructurado y formateado con identación simétrica de 2 espacios
        try {
            const objAnt = JSON.parse(anteriorRaw);
            codeAnterior.innerText = Object.keys(objAnt).length > 0 ? JSON.stringify(objAnt, null, 2) : "N/A (Registro Nuevo / Primer Ingreso)";
        } catch(e) { codeAnterior.innerText = "N/A"; }

        try {
            const objNue = JSON.parse(nuevoRaw);
            codeNuevo.innerText = Object.keys(objNue).length > 0 ? JSON.stringify(objNue, null, 2) : "N/A (Registro Eliminado / Estado Vacío)";
        } catch(e) { codeNuevo.innerText = "N/A"; }

        modal.classList.remove('hidden');
    }

    function cerrarModalInspector() {
        modal.classList.add('hidden');
    }
</script>
