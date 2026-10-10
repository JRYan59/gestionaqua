<?php
session_start();

// Si el usuario ya está autenticado, enviarlo directo al Dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php?page=dashboard');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema - AQUA_DB</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full bg-gray-900 flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl p-8 space-y-6">
        
        <!-- Encabezado / Logo -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600 text-white shadow-lg mb-2">
                <i data-lucide="droplet" class="w-8 h-8"></i>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-wide">AQUA_DB</h1>
            <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Gestión Operativa de Agua Potable</p>
        </div>

        <!-- Alerta de Error -->
        <?php if (isset($_GET['error'])): ?>
            <div class="p-3 bg-red-950/50 border border-red-800 text-red-300 rounded-lg text-xs font-medium text-center">
                <?php 
                    if ($_GET['error'] === 'credenciales') echo 'Correo o contraseña incorrectos.';
                    elseif ($_GET['error'] === 'inactivo') echo 'Este usuario se encuentra inactivo.';
                    else echo 'Por favor completa todos los campos.';
                ?>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form action="procesar_login.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Correo Electrónico</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i data-lucide="mail" class="w-4 h-4"></i>
                    </span>
                    <input type="email" name="email" required placeholder="admin@aquabot.com" 
                           class="w-full pl-10 pr-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-sm text-white placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Contraseña</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                    </span>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full pl-10 pr-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-sm text-white placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all">
                </div>
            </div>

            <button type="submit" 
                    class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-lg transition-all shadow-lg hover:shadow-blue-600/25">
                Iniciar Sesión
            </button>
        </form>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>