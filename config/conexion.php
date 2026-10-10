<?php
// config/conexion.php

class Database {
    // Parámetros de la base de datos. Soportan entorno Docker/local.
    private static $host = null;
    private static $db_name = null;
    private static $username = null;
    private static $password = null;
    private static $conn = null;

    private static function initConfig() {
        if (self::$host === null) {
            self::$host = getenv('DB_HOST') ?: 'localhost';
        }
        if (self::$db_name === null) {
            self::$db_name = getenv('DB_NAME') ?: 'botella_agua_db';
        }
        if (self::$username === null) {
            self::$username = getenv('DB_USER') ?: 'root';
        }
        if (self::$password === null) {
            self::$password = getenv('DB_PASS') ?: '';
        }
    }

    /**
     * Retorna la instancia única de la conexión PDO (Patrón Singleton)
     */
    public static function conectar() {
        self::initConfig();

        if (self::$conn === null) {
            try {
                // Configuración de DNS
                $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$db_name . ";charset=utf8mb4";
                
                // Opciones críticas de rendimiento y seguridad avanzada
                $opciones = [
                    // 1. Lanza excepciones en caso de errores en lugar de fallos silenciosos
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    
                    // 2. Retorna los datos como arreglos asociativos por defecto (Ahorra memoria)
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    
                    // 3. DESACTIVAR emulación de preparados. Obliga a MySQL a compilar el SQL 
                    // de forma separada a los datos. Esto anula la Inyección SQL por completo.
                    PDO::ATTR_EMULATE_PREPARES => false,
                    
                    // 4. Asegura la comunicación nativa en UTF-8 multibyte
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
                
                self::$conn = new PDO($dsn, self::$username, self::$password, $opciones);
                
            } catch (PDOException $e) {
                // Registra el error real en el log interno del servidor para el desarrollador
                error_log("Error crítico de conexión Aqua_DB: " . $e->getMessage());
                
                // Muestra un mensaje limpio y seguro al usuario externo (Confidencialidad)
                // Jamás se debe mostrar el $e->getMessage() en producción para evitar dar pistas a atacantes
                die("<div style='font-family:sans-serif; padding:20px; text-align:center;'>
                        <h2 style='color:#ef4444;'>Error de Configuración</h2>
                        <p style='color:#4b5563;'>No se pudo establecer comunicación con el sistema de base de datos de forma segura.</p>
                     </div>");
            }
        }
        return self::$conn;
    }
}
