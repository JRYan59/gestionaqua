<div class="max-w-xl mx-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
        <h3 class="font-bold text-gray-800 dark:text-white text-sm uppercase tracking-wider">Registrar Personal / Cliente</h3>
    </div>

    <form id="form-crear-usuario" class="p-6 space-y-4">
        <input type="hidden" name="accion" value="crear">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Nombre</label>
                <input type="text" name="nombre" required placeholder="Juan" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Apellido</label>
                <input type="text" name="apellido" required placeholder="Pérez" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Correo Electrónico (Login)</label>
                <input type="email" name="email" required placeholder="juan@gmail.com" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Rol / Cargo</label>
                <select name="rol" required class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                    <option value="cliente">Cliente Regular</option>
                    <option value="transportista">Transportista / Despachador</option>
                    <option value="administrador">Administrador de Sistema</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Teléfono de Contacto</label>
            <input type="text" name="telefono" placeholder="+58-412-555-0000" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-gray-100 dark:border-gray-700 pt-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Contraseña</label>
                <input type="password" id="password" name="password" required placeholder="••••••••" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Confirmar Contraseña</label>
                <input type="password" id="confirm_password" required placeholder="••••••••" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="index.php?page=usuarios" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all">Cancelar</a>
            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-all shadow-sm">Registrar</button>
        </div>
    </form>
</div>

<script>
    document.getElementById('form-crear-usuario').addEventListener('submit', function(e) {
        e.preventDefault();
        if (document.getElementById('password').value !== document.getElementById('confirm_password').value) {
            return alert("Error: Las contraseñas no coinciden.");
        }
        fetch('modules/usuarios/acciones.php', {
            method: 'POST',
            body: new URLSearchParams(new FormData(this))
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') { window.location.href = 'index.php?page=usuarios'; }
        });
    });
</script>
