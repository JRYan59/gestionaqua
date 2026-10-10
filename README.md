# AQUA

Aplicación PHP para gestión de inventario, pedidos, despacho y usuarios.

## Requisitos

- PHP CLI 8.2 o posterior.
- Extensiones PHP: `pdo_mysql`, `mbstring` y `session`.
- MariaDB 11.8+ o MySQL 8.0+.
- Git.

## Instalación local

1. Clona el repositorio y entra en su carpeta.
2. Crea una base vacía y un usuario dedicado para la aplicación. No uses `root` desde PHP:

```sql
CREATE DATABASE botella_agua_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'aqua_app'@'127.0.0.1' IDENTIFIED BY 'REEMPLAZA_CON_UN_SECRETO_LARGO';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, TRIGGER, EXECUTE
ON botella_agua_db.* TO 'aqua_app'@'127.0.0.1';
```

El usuario administrador de la base de datos debe ejecutar estos comandos. En una instalación nueva, carga el esquema y los datos genéricos:

```bash
mariadb -u root -p botella_agua_db < database/schema.sql
mariadb -u root -p botella_agua_db < database/seed.sql
```

3. Copia `.env.example` a `.env` y establece `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS`. No agregues `.env` a Git.
4. Carga esas variables al iniciar PHP y prepara el primer administrador con una contraseña propia de al menos 12 caracteres:

```bash
set -a
source .env
set +a
export ADMIN_EMAIL='admin@ejemplo.local'
read -rsp 'Contraseña nueva del administrador: ' ADMIN_PASSWORD; echo
export ADMIN_PASSWORD
php -d extension=pdo_mysql.so database/create_admin.php
unset ADMIN_PASSWORD
```

El script se ejecuta solo desde CLI, rechaza contraseñas cortas, no sobrescribe correos existentes y guarda la contraseña con Argon2id. No existe una contraseña inicial compartida ni una cuenta de administrador precargada.

5. Inicia el servidor de desarrollo:

```bash
set -a
source .env
set +a
php -d extension=pdo_mysql.so -S 127.0.0.1:8080
```

Abre <http://127.0.0.1:8080/login.php>.

## Base de datos versionada

- `database/schema.sql` contiene estructura, vistas, rutinas y triggers; no contiene filas de negocio ni usuarios.
- `database/seed.sql` contiene únicamente categorías genéricas y una configuración neutra; se puede volver a ejecutar sin sobrescribir ajustes existentes.
- No subas volcados de producción, archivos `.env`, credenciales ni datos personales.

Para copias de seguridad, guarda los volcados fuera del repositorio y protégelos como datos sensibles.
