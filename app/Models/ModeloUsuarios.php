<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Conexion;
use PDO;

class ModeloUsuarios
{
    private static array $columnCache = [];

    private static function columnaDisponible(string $columna): bool
    {
        if (!array_key_exists($columna, self::$columnCache)) {
            $link = Conexion::conectar();
            $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS"
                 . " WHERE TABLE_SCHEMA = DATABASE()"
                 . "   AND TABLE_NAME = 'argus_users'"
                 . "   AND COLUMN_NAME = :columna";

            $stmt = $link->prepare($sql);
            $stmt->bindValue(':columna', $columna, PDO::PARAM_STR);
            $stmt->execute();

            self::$columnCache[$columna] = ((int)$stmt->fetchColumn()) > 0;
        }

        return self::$columnCache[$columna];
    }

    /**
     * Obtener un usuario por campo específico
     *
     * @param string $tabla Nombre de la tabla
     * @param string $item  Columna a buscar
     * @param mixed  $valor Valor a comparar
     * @return array|false
     */
    public static function mdlMostrarUsuario($tabla, $item, $valor) {
        $link = Conexion::conectar();
        $stmt = $link->prepare("SELECT * FROM $tabla WHERE $item = :valor LIMIT 1");
        $stmt->bindParam(':valor', $valor, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Insertar un nuevo usuario con contraseña hasheada y permiso.
     *
     * @param array $datos Datos del usuario: username, password, permission
     * @return string 'ok' en caso de éxito, 'error' en caso de fallo
     */
    public static function mdlAgregarUsuario($datos) {
        $link = Conexion::conectar();

        $columnas = ['username', 'password', 'permission'];
        $placeholders = [':username', ':password', ':permission'];

        if (self::columnaDisponible('nombre_completo')) {
            $columnas[] = 'nombre_completo';
            $placeholders[] = ':nombre_completo';
        }

        if (self::columnaDisponible('nombre_corto')) {
            $columnas[] = 'nombre_corto';
            $placeholders[] = ':nombre_corto';
        }

        if (self::columnaDisponible('email')) {
            $columnas[] = 'email';
            $placeholders[] = ':email';
        }

        if (self::columnaDisponible('notificaciones_activas')) {
            $columnas[] = 'notificaciones_activas';
            $placeholders[] = ':notificaciones_activas';
        }

        $sql = sprintf(
            'INSERT INTO argus_users (%s) VALUES (%s)',
            implode(', ', $columnas),
            implode(', ', $placeholders)
        );

        $stmt = $link->prepare($sql);
        $stmt->bindParam(':username', $datos['username'], PDO::PARAM_STR);
        $stmt->bindParam(':password', $datos['password'], PDO::PARAM_STR);
        $stmt->bindParam(':permission', $datos['permission'], PDO::PARAM_STR);

        $nombreCompleto = $datos['nombre_completo'] ?? ($datos['nombre_corto'] ?? $datos['username']);

        if (self::columnaDisponible('nombre_completo')) {
            $stmt->bindValue(':nombre_completo', $nombreCompleto, PDO::PARAM_STR);
        }

        if (self::columnaDisponible('nombre_corto')) {
            $alias = $datos['nombre_corto'] ?? $nombreCompleto;
            $stmt->bindValue(':nombre_corto', $alias, PDO::PARAM_STR);
        }

        if (self::columnaDisponible('email')) {
            $stmt->bindValue(':email', $datos['email'] ?? null, PDO::PARAM_STR);
        }

        if (self::columnaDisponible('notificaciones_activas')) {
            $stmt->bindValue(':notificaciones_activas', !empty($datos['notificaciones_activas']) ? 1 : 0, PDO::PARAM_INT);
        }

        if ($stmt->execute()) {
            return 'ok';
        }
        return 'error';
    }

    /**
     * Obtener todos los usuarios registrados.
     *
     * @return array Lista de usuarios
     */
    public static function mdlMostrarUsuarios() {
        $link = Conexion::conectar();

        $columnas = ['id', 'username', 'permission', 'created_at'];
        if (self::columnaDisponible('nombre_completo')) {
            $columnas[] = 'nombre_completo';
        }
        if (self::columnaDisponible('nombre_corto')) {
            $columnas[] = 'nombre_corto';
        }
        if (self::columnaDisponible('email')) {
            $columnas[] = 'email';
        }
        if (self::columnaDisponible('notificaciones_activas')) {
            $columnas[] = 'notificaciones_activas';
        }

        $sql = 'SELECT ' . implode(', ', $columnas) . ' FROM argus_users ORDER BY id ASC';
        $stmt = $link->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Eliminar un usuario por su ID.
     *
     * @param int $id Identificador del usuario
     * @return string 'ok' si se eliminó, 'error' en caso contrario
     */
    public static function mdlEliminarUsuario($id) {
        $link = Conexion::conectar();
        $stmt = $link->prepare("DELETE FROM argus_users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            return 'ok';
        }
        return 'error';
    }

    public static function mdlExisteUsername(string $username): bool
    {
        $link = Conexion::conectar();
        $stmt = $link->prepare('SELECT id FROM argus_users WHERE username = :username LIMIT 1');
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function mdlExisteEmail(string $email): bool
    {
        if (!self::columnaDisponible('email')) {
            return false;
        }

        $link = Conexion::conectar();
        $stmt = $link->prepare('SELECT id FROM argus_users WHERE email = :email LIMIT 1');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function mdlObtenerUsuarioPorId(int $usuarioId): ?array
    {
        $link = Conexion::conectar();
        $stmt = $link->prepare('SELECT * FROM argus_users WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public static function mdlActualizarUsuario(array $datos): bool
    {
        $columnas = [];
        if (self::columnaDisponible('nombre_completo')) {
            $columnas['nombre_completo'] = $datos['nombre_completo'] ?? null;
        }
        if (self::columnaDisponible('email')) {
            $columnas['email'] = $datos['email'] ?? null;
        }
        $columnas['permission'] = $datos['permission'] ?? null;
        if (self::columnaDisponible('notificaciones_activas')) {
            $columnas['notificaciones_activas'] = !empty($datos['notificaciones_activas']) ? 1 : 0;
        }

        if (empty($columnas)) {
            return false;
        }

        $sets = [];
        foreach (array_keys($columnas) as $col) {
            $sets[] = $col . ' = :' . $col;
        }

        $sql = 'UPDATE argus_users SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1';
        $link = Conexion::conectar();
        $stmt = $link->prepare($sql);
        foreach ($columnas as $col => $valor) {
            if ($col === 'notificaciones_activas') {
                $stmt->bindValue(':' . $col, (int)$valor, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $col, $valor, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':id', (int)$datos['id'], PDO::PARAM_INT);

        return $stmt->execute();
    }

    public static function mdlActualizarPreferenciasNotificaciones(int $usuarioId, bool $activas): bool
    {
        if (!self::columnaDisponible('notificaciones_activas')) {
            return false;
        }

        $link = Conexion::conectar();
        $stmt = $link->prepare('UPDATE argus_users SET notificaciones_activas = :estado WHERE id = :id');
        $stmt->bindValue(':estado', $activas ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public static function mdlUsuariosConNotificacionesActivas(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        $roles = array_values(array_unique(array_filter($roles, fn($rol) => is_string($rol) && $rol !== '')));
        if (empty($roles)) {
            return [];
        }

        $columnAvailable = self::columnaDisponible('notificaciones_activas');

        $placeholders = implode(', ', array_fill(0, count($roles), '?'));
        $sql = 'SELECT id, username, permission FROM argus_users WHERE permission IN (' . $placeholders . ')';
        if ($columnAvailable) {
            $sql .= ' AND notificaciones_activas = 1';
        }

        $link = Conexion::conectar();
        $stmt = $link->prepare($sql);
        foreach ($roles as $index => $rol) {
            $stmt->bindValue($index + 1, $rol, PDO::PARAM_STR);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function mdlTodosConNotificacionesActivas(): array
    {
        $columnAvailable = self::columnaDisponible('notificaciones_activas');

        $sql = 'SELECT id, username, permission FROM argus_users';
        if ($columnAvailable) {
            $sql .= ' WHERE notificaciones_activas = 1';
        }

        $link = Conexion::conectar();
        $stmt = $link->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function mdlActualizarPassword(int $usuarioId, string $hash): bool
    {
        $link = Conexion::conectar();
        $stmt = $link->prepare('UPDATE argus_users SET password = :password WHERE id = :id');
        $stmt->bindValue(':password', $hash, PDO::PARAM_STR);
        $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
