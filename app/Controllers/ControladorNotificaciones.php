<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ModeloNotificaciones;
use App\Models\ModeloUsuarios;

class ControladorNotificaciones
{
    private const ROLES_DESTINO = ['moderator', 'senior', 'owner', 'admin'];
    private const ESTADOS_AUTOR = [
        'en_revision' => 'Solicitud en revisión',
        'aprobada' => 'Solicitud aprobada',
        'cancelada' => 'Solicitud cancelada',
        'enviada' => 'Solicitud enviada',
        'borrador' => 'Solicitud devuelta a borrador',
    ];

    private static function construirMensaje(string $prefijo, array $solicitud): string
    {
        $mensaje = $prefijo;

        $folio = trim((string)($solicitud['folio'] ?? ''));
        if ($folio !== '') {
            $mensaje .= ' • Folio ' . $folio;
        }

        $nombre = trim((string)($solicitud['nombre_completo'] ?? ($solicitud['nombre_corto'] ?? '')));
        if ($nombre !== '') {
            $mensaje .= ' • Cliente ' . $nombre;
        }

        return $mensaje;
    }

    private static function destinatariosPorRoles(array $roles, int $excluirId = 0): array
    {
        if (empty($roles)) {
            return [];
        }

        $usuarios = ModeloUsuarios::mdlUsuariosConNotificacionesActivas($roles);
        if (empty($usuarios)) {
            return [];
        }

        $destinatarios = [];
        foreach ($usuarios as $usuario) {
            $usuarioId = (int)($usuario['id'] ?? 0);
            if ($usuarioId > 0 && $usuarioId !== $excluirId) {
                $destinatarios[] = $usuarioId;
            }
        }

        return $destinatarios;
    }

    private static function usuarioConNotificacionesActivas(int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        $usuario = ModeloUsuarios::mdlObtenerUsuarioPorId($usuarioId);
        if (!$usuario) {
            return false;
        }

        if (!array_key_exists('notificaciones_activas', $usuario)) {
            return true;
        }

        return !empty($usuario['notificaciones_activas']);
    }

    public static function registrarEnvioSolicitud(array $solicitud): void
    {
        if (!isset($solicitud['id'])) {
            return;
        }

        $excluir = (int)($_SESSION['id'] ?? 0);
        $destinatarios = self::destinatariosPorRoles(self::ROLES_DESTINO, $excluir);

        if (empty($destinatarios)) {
            return;
        }

        $mensaje = self::construirMensaje('Solicitud enviada', $solicitud);
        ModeloNotificaciones::mdlCrearNotificacionEnvio((int)$solicitud['id'], $mensaje, $destinatarios);
    }

    public static function registrarAnuncio(array $anuncio): void
    {
        $anuncioId = (int)($anuncio['id'] ?? 0);
        $mensaje = trim((string)($anuncio['mensaje'] ?? ''));

        if ($anuncioId <= 0 || $mensaje === '') {
            return;
        }

        $usuarios = ModeloUsuarios::mdlTodosConNotificacionesActivas();
        if (empty($usuarios)) {
            return;
        }

        $destinatarios = [];
        foreach ($usuarios as $usuario) {
            $usuarioId = (int)($usuario['id'] ?? 0);
            if ($usuarioId > 0) {
                $destinatarios[] = $usuarioId;
            }
        }

        if (empty($destinatarios)) {
            return;
        }

        $prefijo = 'Nuevo anuncio: ';
        $mensajeTruncado = $mensaje;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($mensajeTruncado, 'UTF-8') > 180) {
                $mensajeTruncado = rtrim(mb_substr($mensajeTruncado, 0, 177, 'UTF-8')) . '…';
            }
        } elseif (strlen($mensajeTruncado) > 180) {
            $mensajeTruncado = rtrim(substr($mensajeTruncado, 0, 177)) . '…';
        }

        $notificacion = $prefijo . $mensajeTruncado;
        $url = 'index.php?ruta=inicio#anuncio-general';

        ModeloNotificaciones::mdlCrearNotificacionAnuncio($anuncioId, $notificacion, $destinatarios, $url);
    }

    /**
     * Notifica al autor de la solicitud cuando su trámite ha sido aprobado,
     * siempre que tenga activadas las notificaciones en su perfil.
     */
    public static function registrarAprobacionSolicitud(array $solicitud): void
    {
        self::registrarActualizacionAutor($solicitud, 'aprobada');
    }

    public static function registrarActualizacionAutor(array $solicitud, string $estado): void
    {
        if (!isset($solicitud['id']) || !isset(self::ESTADOS_AUTOR[$estado])) {
            return;
        }

        $usuarioId = (int)($solicitud['usuario_id'] ?? 0);
        if ($usuarioId <= 0 || !self::usuarioConNotificacionesActivas($usuarioId)) {
            return;
        }

        $mensaje = self::construirMensaje(self::ESTADOS_AUTOR[$estado], $solicitud);

        if ($estado === 'borrador') {
            $motivo = trim((string)($solicitud['motivo_retorno'] ?? ''));
            if ($motivo !== '') {
                $motivo = preg_replace('/\s+/', ' ', $motivo);
                if (function_exists('mb_strlen')) {
                    if (mb_strlen($motivo, 'UTF-8') > 160) {
                        $motivo = rtrim(mb_substr($motivo, 0, 157, 'UTF-8')) . '…';
                    }
                } elseif (strlen($motivo) > 160) {
                    $motivo = rtrim(substr($motivo, 0, 157)) . '…';
                }
                $mensaje .= ' • Motivo: ' . $motivo;
            }
        }

        ModeloNotificaciones::mdlCrearNotificacionEnvio((int)$solicitud['id'], $mensaje, [$usuarioId]);
    }

    public static function ctrObtenerPendientes(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            return;
        }

        $usuarioId = (int)($_SESSION['id'] ?? 0);
        if ($usuarioId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Usuario no válido']);
            return;
        }

        $habilitadas = isset($_SESSION['notificaciones_activas'])
            ? (int)$_SESSION['notificaciones_activas'] === 1
            : null;

        if ($habilitadas === null) {
            $habilitadas = self::usuarioConNotificacionesActivas($usuarioId);
            $_SESSION['notificaciones_activas'] = $habilitadas ? 1 : 0;
        } elseif (!$habilitadas && self::usuarioConNotificacionesActivas($usuarioId)) {
            $habilitadas = true;
            $_SESSION['notificaciones_activas'] = 1;
        }

        if (!$habilitadas) {
            echo json_encode([
                'status' => 'ok',
                'notifications' => [],
                'notifications_enabled' => false,
                'pending_total' => 0,
            ]);
            return;
        }

        try {
            $pendientes = ModeloNotificaciones::mdlObtenerPendientes($usuarioId);
            $totalPendientes = ModeloNotificaciones::mdlContarPendientes($usuarioId);

            echo json_encode([
                'status' => 'ok',
                'notifications' => $pendientes,
                'notifications_enabled' => true,
                'pending_total' => $totalPendientes,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $throwable) {
            error_log('[Notificaciones] Error en ctrObtenerPendientes: ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudieron recuperar las notificaciones.',
                'error_details' => $throwable->getMessage(),
                'notifications_enabled' => $habilitadas,
                'pending_total' => 0,
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function ctrObtenerHistorial(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            return;
        }

        $usuarioId = (int)($_SESSION['id'] ?? 0);
        if ($usuarioId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Usuario no válido']);
            return;
        }

        $habilitadas = isset($_SESSION['notificaciones_activas'])
            ? (int)$_SESSION['notificaciones_activas'] === 1
            : null;
        if ($habilitadas === null) {
            $habilitadas = self::usuarioConNotificacionesActivas($usuarioId);
            $_SESSION['notificaciones_activas'] = $habilitadas ? 1 : 0;
        } elseif (!$habilitadas && self::usuarioConNotificacionesActivas($usuarioId)) {
            $habilitadas = true;
            $_SESSION['notificaciones_activas'] = 1;
        }

        if (!$habilitadas) {
            echo json_encode([
                'status' => 'ok',
                'notifications' => [],
                'notifications_enabled' => false,
            ]);
            return;
        }

        $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
        if ($limite <= 0) {
            $limite = 10;
        }

        try {
            $historial = ModeloNotificaciones::mdlObtenerHistorial($usuarioId, $limite);
            echo json_encode([
                'status' => 'ok',
                'notifications' => $historial,
                'notifications_enabled' => true,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $throwable) {
            error_log('[Notificaciones] Error en ctrObtenerHistorial: ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo obtener el historial de notificaciones.',
                'error_details' => $throwable->getMessage(),
                'notifications_enabled' => $habilitadas,
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function ctrMarcarEntregadas(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            return;
        }

        $usuarioId = (int)($_SESSION['id'] ?? 0);
        if ($usuarioId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Usuario no válido']);
            return;
        }

        $ids = [];
        $raw = file_get_contents('php://input') ?: '';
        if ($raw !== '') {
            $payload = json_decode($raw, true);
            if (is_array($payload) && array_key_exists('notifications', $payload)) {
                $ids = is_array($payload['notifications']) ? $payload['notifications'] : [$payload['notifications']];
            }
        }

        if (empty($ids) && isset($_POST['notifications'])) {
            $ids = is_array($_POST['notifications']) ? $_POST['notifications'] : [$_POST['notifications']];
        }

        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No se indicaron notificaciones a marcar.']);
            return;
        }

        try {
            $marcadas = ModeloNotificaciones::mdlMarcarEntregadas($usuarioId, $ids, true);
            echo json_encode([
                'status' => 'ok',
                'updated' => $marcadas,
            ]);
        } catch (\Throwable $throwable) {
            error_log('[Notificaciones] Error al marcar entregadas: ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudieron actualizar las notificaciones.',
            ]);
        }
    }
}
