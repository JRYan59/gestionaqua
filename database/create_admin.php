<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/conexion.php';

$email = getenv('ADMIN_EMAIL') ?: '';
$password = getenv('ADMIN_PASSWORD') ?: '';
$firstName = getenv('ADMIN_FIRST_NAME') ?: 'Administrador';
$lastName = getenv('ADMIN_LAST_NAME') ?: 'Sistema';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Define ADMIN_EMAIL con un correo válido.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Define ADMIN_PASSWORD con al menos 12 caracteres.\n");
    exit(1);
}

try {
    $db = Database::conectar();
    $check = $db->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
    $check->execute([':email' => $email]);

    if ($check->fetch()) {
        fwrite(STDERR, "Ya existe un usuario con ese correo; no se hicieron cambios.\n");
        exit(1);
    }

    $insert = $db->prepare(
        'INSERT INTO usuarios (nombre, apellido, email, password, rol, activo) '
        . 'VALUES (:nombre, :apellido, :email, :password, \'administrador\', 1)'
    );
    $insert->execute([
        ':nombre' => $firstName,
        ':apellido' => $lastName,
        ':email' => $email,
        ':password' => password_hash($password, PASSWORD_ARGON2ID),
    ]);

    fwrite(STDOUT, "Administrador creado: {$email}\n");
} catch (PDOException $exception) {
    fwrite(STDERR, "No se pudo crear el administrador: revisa la conexión y el esquema.\n");
    error_log($exception->getMessage());
    exit(1);
}
