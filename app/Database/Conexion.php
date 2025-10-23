<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

/**
 * Administrador de conexiones PDO configurable mediante variables de entorno.
 */
class Conexion
{
    private static ?PDO $instance = null;

    /**
     * Obtiene una instancia compartida de PDO utilizando la configuración de entorno.
     */
    public static function conectar(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbName = $_ENV['DB_NAME'] ?? 'argus';
        $user = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? '';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            if ($debug) {
                throw $exception;
            }

            throw new PDOException('Error de conexión a la base de datos.');
        }

        self::$instance = $pdo;

        return $pdo;
    }
}
