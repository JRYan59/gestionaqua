<?php
// modules/usuarios/editar.php

$db = Database::conectar();

// 1. Validar y capturar de forma segura el ID del usuario a modificar (Anti-Inyección)
$id_usuario = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_usuario) {
    echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg font-medium'>Parámetro de usuario corrupto o inválido.</div>";
    return;
}

try {
    // 2. Extraer la información actual del usuario utilizando sentencias preparadas
    $stmt = $db->prepare("SELECT id, nombre, usuario, correo, telefono FROM usuarios WHERE id = :id AND activo = 1 LIMIT 1");
    $stmt->execute([':id' => $id_usuario]);
    $usuario_actual = $stmt->fetch();

    if (!$usuario_actual) {
        echo "<div class='p-4 bg-amber-100 text-amber-700 rounded-lg font-medium'>El usuario solicitado no existe o fue dado de baja previamente.</div>";
        return;
    }
} catch (PDOException $e) {
    error_log("Error al cargar formulario de edición de usuarios: " . $e->getMessage());
    echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg font-medium'>Error interno del servidor al procesar la solicitud segura.</div>";
    return;
}
?>

<div class="max-w-xl mx-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex justify-between items-center">
        <h3 class="font-bold text-gray-800 dark:text-white text-sm uppercase tracking-wider">Modificar Perfil de Usuario</h3>
        <span class="text-[10px] font-mono bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 px-2 py-0.5 rounded">ID: <?php echo $usuario_actual['id']; ?></span>
    </div>

    <form id="form-editar-usuario" class="p-6 space-y-4">
        <!-- Parámetros POST ocultos requeridos por el backend seguro -->
        <input type="hidden" name="accion" value="modificar">
        <input type="hidden" name="usuario_id" value="<?php echo $usuario_actual['id']; ?>">

        <!-- Campo: Nombre Completo -->
        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Nombre Completo</label>
            <input type="text" name="nombre" required value="<?php echo htmlspecialchars($usuario_actual['nombre']); ?>" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Campo: Nombre de Usuario -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Nombre de Usuario (Login)</label>
                <input type="text" name="usuario" required value="<?php echo htmlspecialchars($usuario_actual['usuario']); ?>" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>
            <!-- Campo: Correo -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Correo Electrónico</label>
                <input type="email" name="correo" required value="<?php echo htmlspecialchars($usuario_actual['correo'] ?? ''); ?>" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>
        </div>

        <!-- Campo: Teléfono -->
        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Teléfono de Contacto</label>
            <input type="text" name="telefono" value="<?php echo htmlspecialchars($usuario_actual['telefono'] ?? ''); ?>" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
        </div>

        <!-- Nota informativa sobre confidencialidad y control de contraseñas -->
        <div class="bg-blue-50 dark:bg-blue-950/30 border-l-4 border-blue-500 p-3 rounded text-xs text-blue-700 dark:text-blue-300">
            <p class="font-semibold flex items-center gap-1">
                <i data-lucide="info" class="w-3.5 h-3.5"></i> Seguridad de Acceso:
            </p>
            <p class="mt-0.5">Deje los campos de contraseña en blanco si desea conservar la clave actual sin modificaciones.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <!-- Campo: Nueva Contraseña -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Nueva Contraseña</label>
                <input type="password" id="password" name="password" placeholder="••••••••" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>
            <!-- Campo: Confirmar Contraseña -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Confirmar Nueva Contraseña</label>
                <input type="password" id="confirm_password" placeholder="••••••••" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>
        </div>

        <!-- Botones de Control del Formulario -->
        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="index.php?page=usuarios" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all">Cancelar</a>
            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-all shadow-sm">Actualizar Datos</button>
        </div>
    </form>
</div>

<script>
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    document.getElementById('form-editar-usuario').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const pass = document.getElementById('password').value;
        const confPass = document.getElementById('confirm_password').value;

        // Validar igualdad en el cliente si el usuario intentó setear una nueva clave
        if (pass.length > 0 && pass !== confPass) {
            return alert("Error de seguridad: Las nuevas contraseñas ingresadas no coinciden.");
        }

        const formData = new FormData(this);

        fetch('modules/usuarios/acciones.php', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') { window.location.href = 'index.php?page=usuarios'; }
        })
        .catch(err => {
            console.error(err);
            alert("Error crítico de comunicación con el procesador de datos.");
        });
    });
</script>
