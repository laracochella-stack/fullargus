<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ModeloUsuarios;

/**
 * Sincroniza en la sesión el estado de las notificaciones del usuario.
 */
final class NotificacionesSession
{
    public static function asegurarEstado(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (empty($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return;
        }

        if (array_key_exists('notificaciones_activas', $_SESSION)) {
            return;
        }

        $usuarioId = (int)($_SESSION['id'] ?? 0);
        if ($usuarioId <= 0) {
            $_SESSION['notificaciones_activas'] = 0;
            return;
        }

        $usuario = ModeloUsuarios::mdlObtenerUsuarioPorId($usuarioId);
        if (!$usuario) {
            $_SESSION['notificaciones_activas'] = 0;
            return;
        }

        if (array_key_exists('notificaciones_activas', $usuario)) {
            $_SESSION['notificaciones_activas'] = !empty($usuario['notificaciones_activas']) ? 1 : 0;
            return;
        }

        // Si la columna no existe en la base de datos, asumimos que están activas.
        $_SESSION['notificaciones_activas'] = 1;
    }
}

