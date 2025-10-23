<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ModeloAnuncios;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class ControladorConsola
{
    public static function ctrProcesarAnuncio(): ?array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }

        $accion = $_POST['accion_consola'] ?? '';
        if ($accion === '') {
            return null;
        }

        if (($_SESSION['permission'] ?? '') !== 'admin') {
            return [
                'status' => 'error',
                'message' => 'Permisos insuficientes para administrar la consola.'
            ];
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            return [
                'status' => 'error',
                'message' => 'Token CSRF inválido.'
            ];
        }

        try {
            if ($accion === 'crear_anuncio') {
                return self::crearAnuncio();
            }

            if ($accion === 'editar_anuncio') {
                return self::editarAnuncio();
            }

            if ($accion === 'cerrar_anuncio' || $accion === 'finalizar_anuncio') {
                return self::cerrarAnuncio();
            }
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'message' => 'Ocurrió un error al procesar la solicitud de anuncio.'
            ];
        }

        return null;
    }

    public static function ctrObtenerAnuncioVigente(): ?array
    {
        $anuncio = ModeloAnuncios::mdlObtenerAnuncioVigente();
        if (!$anuncio) {
            return null;
        }

        return [
            'id' => (int)($anuncio['id'] ?? 0),
            'mensaje' => (string)($anuncio['mensaje'] ?? ''),
            'vigente_hasta' => $anuncio['vigente_hasta'] ?? null,
            'creado_en' => $anuncio['creado_en'] ?? null,
            'activo' => (int)($anuncio['activo'] ?? 1),
            'mostrar_en_dashboard' => (int)($anuncio['mostrar_en_dashboard'] ?? 0),
            'mostrar_en_popup' => (int)($anuncio['mostrar_en_popup'] ?? 0),
        ];
    }

    public static function ctrObtenerAnunciosVigentes(?string $destino = null): array
    {
        $anuncios = ModeloAnuncios::mdlObtenerActivos($destino);
        if (empty($anuncios)) {
            return [];
        }

        return array_map(static function (array $anuncio): array {
            return [
                'id' => (int)($anuncio['id'] ?? 0),
                'mensaje' => (string)($anuncio['mensaje'] ?? ''),
                'vigente_hasta' => $anuncio['vigente_hasta'] ?? null,
                'creado_en' => $anuncio['creado_en'] ?? null,
                'activo' => (int)($anuncio['activo'] ?? 0),
                'mostrar_en_dashboard' => (int)($anuncio['mostrar_en_dashboard'] ?? 0),
                'mostrar_en_popup' => (int)($anuncio['mostrar_en_popup'] ?? 0),
            ];
        }, $anuncios);
    }

    public static function ctrListarAnuncios(int $limite = 20): array
    {
        $anuncios = ModeloAnuncios::mdlListarAnuncios($limite);
        if (!is_array($anuncios) || empty($anuncios)) {
            return [];
        }

        return array_map(static function (array $anuncio): array {
            return [
                'id' => (int)($anuncio['id'] ?? 0),
                'mensaje' => (string)($anuncio['mensaje'] ?? ''),
                'vigente_hasta' => $anuncio['vigente_hasta'] ?? null,
                'creado_en' => $anuncio['creado_en'] ?? null,
                'activo' => (int)($anuncio['activo'] ?? 0),
                'mostrar_en_dashboard' => (int)($anuncio['mostrar_en_dashboard'] ?? 0),
                'mostrar_en_popup' => (int)($anuncio['mostrar_en_popup'] ?? 0),
            ];
        }, $anuncios);
    }

    private static function crearAnuncio(): array
    {
        $mensaje = trim((string)($_POST['mensaje'] ?? ''));
        $duracionMinutos = (int)($_POST['duracion_minutos'] ?? 0);
        $destinos = $_POST['destinos'] ?? [];

        if ($mensaje === '') {
            return [
                'status' => 'error',
                'message' => 'Escribe el contenido del anuncio.'
            ];
        }

        if ($duracionMinutos <= 0) {
            return [
                'status' => 'error',
                'message' => 'Indica la duración del anuncio en minutos.'
            ];
        }

        if (!is_array($destinos)) {
            $destinos = [$destinos];
        }

        $destinos = array_map(static function ($destino): string {
            return is_string($destino) ? strtolower(trim($destino)) : '';
        }, $destinos);

        $mostrarEnDashboard = in_array('dashboard', $destinos, true);
        $mostrarEnPopup = in_array('popup', $destinos, true);

        if (!$mostrarEnDashboard && !$mostrarEnPopup) {
            return [
                'status' => 'error',
                'message' => 'Selecciona al menos un destino para el anuncio (panel o notificación emergente).'
            ];
        }

        $duracionMinutos = max(5, min($duracionMinutos, 1440));
        if (mb_strlen($mensaje) > 500) {
            $mensaje = mb_substr($mensaje, 0, 500);
        }

        $vigencia = self::ahora()->add(new DateInterval('PT' . $duracionMinutos . 'M'));
        $creadoPor = (int)($_SESSION['id'] ?? 0);

        $anuncioId = ModeloAnuncios::mdlCrearAnuncio($mensaje, $vigencia, $creadoPor, $mostrarEnDashboard, $mostrarEnPopup);
        if ($anuncioId > 0) {
            $anuncioPayload = [
                'id' => $anuncioId,
                'mensaje' => $mensaje,
                'vigente_hasta' => $vigencia ? $vigencia->format('Y-m-d H:i:s') : null,
            ];
            if ($mostrarEnPopup) {
                ControladorNotificaciones::registrarAnuncio($anuncioPayload);
            }

            self::limpiarSolicitud();

            return [
                'status' => 'ok',
                'message' => 'Anuncio publicado correctamente.'
            ];
        }

        return [
            'status' => 'error',
            'message' => 'No fue posible guardar el anuncio.'
        ];
    }

    private static function editarAnuncio(): array
    {
        $anuncioId = (int)($_POST['anuncio_id'] ?? 0);
        $mensaje = trim((string)($_POST['mensaje'] ?? ''));
        $vigenteHastaTexto = trim((string)($_POST['vigente_hasta'] ?? ''));
        $destinos = $_POST['destinos'] ?? [];

        if ($anuncioId <= 0) {
            return [
                'status' => 'error',
                'message' => 'No se encontró el anuncio que intentas editar.'
            ];
        }

        $anuncioActual = ModeloAnuncios::mdlObtenerPorId($anuncioId);
        if (!$anuncioActual) {
            return [
                'status' => 'error',
                'message' => 'No se encontró el anuncio que intentas editar.'
            ];
        }

        if ($mensaje === '') {
            return [
                'status' => 'error',
                'message' => 'Escribe el contenido del anuncio.'
            ];
        }

        if (mb_strlen($mensaje) > 500) {
            $mensaje = mb_substr($mensaje, 0, 500);
        }

        $vigencia = null;
        if ($vigenteHastaTexto !== '') {
            $vigencia = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $vigenteHastaTexto, self::ahora()->getTimezone());
            if (!$vigencia) {
                return [
                    'status' => 'error',
                    'message' => 'La fecha de vigencia indicada no es válida.'
                ];
            }
        } elseif (!empty($anuncioActual['vigente_hasta'])) {
            try {
                $vigencia = new DateTimeImmutable($anuncioActual['vigente_hasta'], self::ahora()->getTimezone());
            } catch (Throwable $exception) {
                $vigencia = null;
            }
        }

        if (!is_array($destinos)) {
            $destinos = [$destinos];
        }

        $destinos = array_map(static function ($destino): string {
            return is_string($destino) ? strtolower(trim($destino)) : '';
        }, $destinos);

        $mostrarEnDashboard = in_array('dashboard', $destinos, true);
        $mostrarEnPopup = in_array('popup', $destinos, true);

        if (!$mostrarEnDashboard && !$mostrarEnPopup) {
            return [
                'status' => 'error',
                'message' => 'Selecciona al menos un destino para el anuncio (panel o notificación emergente).'
            ];
        }

        $activar = (int)($_POST['anuncio_activo'] ?? ($anuncioActual['activo'] ?? 0)) === 1;

        $resultado = ModeloAnuncios::mdlActualizarAnuncio(
            $anuncioId,
            $mensaje,
            $vigencia,
            $activar,
            $mostrarEnDashboard,
            $mostrarEnPopup
        );
        if ($resultado) {
            if ($activar && $mostrarEnPopup) {
                $payload = [
                    'id' => $anuncioId,
                    'mensaje' => $mensaje,
                    'vigente_hasta' => $vigencia ? $vigencia->format('Y-m-d H:i:s') : null,
                ];
                ControladorNotificaciones::registrarAnuncio($payload);
            }

            self::limpiarSolicitud();

            return [
                'status' => 'ok',
                'message' => 'Anuncio actualizado correctamente.'
            ];
        }

        return [
            'status' => 'error',
            'message' => 'No fue posible actualizar el anuncio.'
        ];
    }

    private static function cerrarAnuncio(): array
    {
        $anuncioId = (int)($_POST['anuncio_id'] ?? 0);
        if ($anuncioId <= 0) {
            return [
                'status' => 'error',
                'message' => 'No se encontró el anuncio seleccionado.'
            ];
        }

        $resultado = ModeloAnuncios::mdlDesactivarAnuncio($anuncioId);
        if ($resultado) {
            self::limpiarSolicitud();

            return [
                'status' => 'ok',
                'message' => 'Anuncio desactivado correctamente.'
            ];
        }

        return [
            'status' => 'error',
            'message' => 'No fue posible desactivar el anuncio.'
        ];
    }

    private static function limpiarSolicitud(): void
    {
        foreach (array_keys($_POST) as $clave) {
            unset($_POST[$clave]);
        }
    }

    private static function ahora(): DateTimeImmutable
    {
        $tz = date_default_timezone_get() ?: 'UTC';
        return new DateTimeImmutable('now', new DateTimeZone($tz));
    }
}
