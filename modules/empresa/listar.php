<?php
// Cargar datos actuales de la empresa
$empresa = [
    'nombre_comercial' => '',
    'razon_social'     => '',
    'rif'              => '',
    'telefono'         => '',
    'email'            => '',
    'direccion'        => '',
    'tasa_cambio_usd'  => 0.00,
    'logo_url'         => '',
    'actualizado_en'   => ''
];

try {
    $db = Database::conectar();
    $stmt = $db->query("SELECT * FROM configuracion_empresa LIMIT 1");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data) {
        $empresa = array_merge($empresa, $data);
    }
} catch (Exception $e) {
    echo "<div class='p-4 bg-red-900/40 border border-red-800 text-red-300 rounded-xl mb-4 text-xs'>Error al cargar configuración: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<div class="space-y-6">
    <!-- Header del Módulo -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Configuración de la Empresa</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">Parámetros generales, imagen corporativa y tasa oficial de cambio (BCV / USD)</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 bg-amber-500/10 border border-amber-500/30 text-amber-500 rounded-full text-[10px] font-bold uppercase tracking-wider">
            Zona de Administración
        </span>
    </div>

    <!-- Formulario Unificado -->
    <form action="modules/empresa/guardar.php" method="POST" enctype="multipart/form-data" class="space-y-6">
        
        <!-- Tarjeta: Tasa de Cambio -->
        <div class="p-5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm space-y-4">
            <div class="flex items-center space-x-3">
                <div class="p-2 bg-blue-600/10 text-blue-500 rounded-xl">
                    <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-white">Tasa Oficial USD / BS</h3>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">Se usará automáticamente en los triggers y cálculos del sistema</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Tasa de Cambio (Bs. por 1 USD) *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs text-gray-400 font-bold">Bs.</span>
                        <input type="number" step="0.0001" min="0.01" name="tasa_cambio_usd" value="<?= htmlspecialchars($empresa['tasa_cambio_usd']); ?>" required class="w-full pl-10 pr-3 py-2 bg-gray-50 dark:bg-gray-900/50 border border-gray-300 dark:border-gray-700 rounded-xl text-sm font-mono font-bold text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>
                <div class="flex items-end">
                    <p class="text-[11px] text-gray-400 font-mono">Última act: <?= !empty($empresa['actualizado_en']) ? date('d/m/Y h:i A', strtotime($empresa['actualizado_en'])) : 'N/A'; ?></p>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Información Institucional y Logo -->
        <div class="p-5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm space-y-4">
            <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider border-b border-gray-100 dark:border-gray-700 pb-3">Información Institucional</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Nombre Comercial *</label>
                    <input type="text" name="nombre_comercial" value="<?= htmlspecialchars($empresa['nombre_comercial']); ?>" required class="w-full p-2.5 bg-gray-50 dark:bg-gray-900/50 border border-gray-300 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Razón Social</label>
                    <input type="text" name="razon_social" value="<?= htmlspecialchars($empresa['razon_social']); ?>" class="w-full p-2.5 bg-gray-50 dark:bg-gray-900/50 border border-gray-300 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">RIF / Identificación Fiscal</label>
                    <input type="text" name="rif" value="<?= htmlspecialchars($empresa['rif']); ?>" class="w-full p-2.5 bg-gray-50 dark:bg-gray-900/50 border border-gray-300 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Teléfono Principal</label>
                    <input type="text" name="telefono" value="<?= htmlspecialchars($empresa['telefono']); ?>" class="w-full p-2.5 bg-gray-50 dark:bg-gray-900/50 border border-gray-300 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Correo Electrónico Institucional</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($empresa['email']); ?>" class="w-full p-2.5 bg-gray-50 dark:bg-gray-900/50 border border-gray-300 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Dirección Física</label>
                    <textarea name="direccion" rows="2" class="w-full p-2.5 bg-gray-50 dark:bg-gray-900/50 border border-gray-300 dark:border-gray-700 rounded-xl text-xs text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"><?= htmlspecialchars($empresa['direccion']); ?></textarea>
                </div>

                <!-- Campo para Subir Logo Institucional -->
                <div class="md:col-span-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                    <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-2">Logo Corporativo (PNG, JPG, WEBP)</label>
                    <div class="flex items-center space-x-4">
                        <?php if (!empty($empresa['logo_url']) && file_exists($empresa['logo_url'])): ?>
                            <img src="<?= htmlspecialchars($empresa['logo_url']); ?>" alt="Logo Actual" class="w-12 h-12 object-contain rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 p-1">
                        <?php endif; ?>
                        <input type="file" name="logo" accept="image/*" class="block w-full text-xs text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-300 dark:border-gray-700">
                    </div>
                </div>
            </div>
        </div>

        <!-- Botón Guardar -->
        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center space-x-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-xl shadow-lg transition-all">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span>Guardar Cambios</span>
            </button>
        </div>

    </form>
</div>