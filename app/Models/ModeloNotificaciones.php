<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Conexion;
use PDO;
use PDOException;

class ModeloNotificaciones
{
    private static function normalizarDestinatarios(array $usuarios): array
    {
        $usuarios = array_map(static function ($id): int {
            if (is_int($id)) {
                return $id;
            }
            if (is_string($id) && is_numeric($id)) {
                return (int)$id;
            }
            return 0;
        }, $usuarios);

        return array_values(array_unique(array_filter($usuarios, static fn(int $id): bool => $id > 0)));
    }

    private static function insertarNotificacion(
        string $mensaje,
        array $usuarios,
        ?int $solicitudId,
        string $tipo,
        ?int $referenciaId,
        ?string $url
    ): void {
        $destinatarios = self::normalizarDestinatarios($usuarios);
        if ($mensaje === '' || empty($destinatarios)) {
            return;
        }

        $tipoNormalizado = trim($tipo) !== '' ? trim($tipo) : 'general';
        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            if (mb_strlen($tipoNormalizado, 'UTF-8') > 50) {
                $tipoNormalizado = mb_substr($tipoNormalizado, 0, 50, 'UTF-8');
            }
        } elseif (strlen($tipoNormalizado) > 50) {
            $tipoNormalizado = substr($tipoNormalizado, 0, 50);
        }

        $pdo = Conexion::conectar();
        $pdo->beginTransaction();

        try {
            $insert = $pdo->prepare(
                'INSERT INTO argus_notifications (solicitud_id, mensaje, tipo, referencia_id, url) '
                . 'VALUES (:solicitud_id, :mensaje, :tipo, :referencia_id, :url)'
            );

            if ($solicitudId !== null && $solicitudId > 0) {
                $insert->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
            } else {
                $insert->bindValue(':solicitud_id', null, PDO::PARAM_NULL);
            }

            $insert->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);
            $insert->bindValue(':tipo', $tipoNormalizado, PDO::PARAM_STR);

            if ($referenciaId !== null && $referenciaId > 0) {
                $insert->bindValue(':referencia_id', $referenciaId, PDO::PARAM_INT);
            } else {
                $insert->bindValue(':referencia_id', null, PDO::PARAM_NULL);
            }

            if ($url !== null && $url !== '') {
                $insert->bindValue(':url', $url, PDO::PARAM_STR);
            } else {
                $insert->bindValue(':url', null, PDO::PARAM_NULL);
            }

            $insert->execute();

            $notificationId = (int)$pdo->lastInsertId();
            if ($notificationId <= 0) {
                $pdo->rollBack();
                return;
            }

            $insertDest = $pdo->prepare(
                'INSERT INTO argus_notification_destinatarios (notification_id, usuario_id) VALUES (:notification_id, :usuario_id)'
            );

            foreach ($destinatarios as $usuarioId) {
                $insertDest->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
                $insertDest->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
                $insertDest->execute();
            }

            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Notificaciones] Error PDO al crear notificación: ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Notificaciones] Error inesperado al crear notificación: ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString());
        }
    }

    /**
     * Registra una notificación para los destinatarios indicados.
     *
     * @param int    $solicitudId Identificador de la solicitud que originó la alerta.
     * @param string $mensaje     Mensaje a mostrar en la alerta.
     * @param int[]  $usuarios    IDs de usuarios destino.
     */
    public static function mdlCrearNotificacionEnvio(int $solicitudId, string $mensaje, array $usuarios): void
    {
        if ($solicitudId <= 0 || $mensaje === '') {
            return;
        }
        self::insertarNotificacion(
            $mensaje,
            $usuarios,
            $solicitudId,
            'solicitud',
            $solicitudId > 0 ? $solicitudId : null,
            null
        );
    }

    public static function mdlCrearNotificacionAnuncio(int $anuncioId, string $mensaje, array $usuarios, ?string $url = null): void
    {
        if ($anuncioId <= 0 || $mensaje === '') {
            return;
        }

        self::insertarNotificacion(
            $mensaje,
            $usuarios,
            null,
            'anuncio',
            $anuncioId,
            $url
        );
    }

    /**
     * Obtiene las notificaciones pendientes para un usuario y las marca como entregadas.
     *
     * @param int $usuarioId ID del usuario autenticado.
     *
     * @return array Lista de notificaciones con id, solicitud_id, mensaje y created_at.
     */
    public static function mdlObtenerPendientes(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return [];
        }

        try {
            $pdo = Conexion::conectar();
            $filtraLeidas = self::columnaLeidoDisponible();
            $sql = 'SELECT n.id, n.solicitud_id, n.mensaje, n.created_at, n.tipo, n.referencia_id, n.url'
                    . ($filtraLeidas ? ', d.leido' : '')
                    . ' FROM argus_notification_destinatarios d'
                    . ' INNER JOIN argus_notifications n ON n.id = d.notification_id'
                    . ' WHERE d.usuario_id = :usuario_id AND d.entregado = 0';

            if ($filtraLeidas) {
                $sql .= ' AND d.leido = 0';
            }

            $sql .= ' ORDER BY n.created_at ASC LIMIT 20';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();

            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (empty($filas)) {
                return [];
            }

            $ids = [];
            $pendientes = array_map(static function (array $fila) use (&$ids): array {
                $id = (int)($fila['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }

                return [
                    'id' => $id,
                    'solicitud_id' => isset($fila['solicitud_id']) ? (int)$fila['solicitud_id'] : 0,
                    'mensaje' => (string)($fila['mensaje'] ?? ''),
                    'created_at' => $fila['created_at'] ?? null,
                    'tipo' => (string)($fila['tipo'] ?? 'solicitud'),
                    'referencia_id' => isset($fila['referencia_id']) ? (int)$fila['referencia_id'] : null,
                    'url' => $fila['url'] ?? null,
                ];
            }, $filas);

            if (!empty($ids)) {
                self::mdlMarcarEntregadas($usuarioId, $ids, false);
            }

            return $pendientes;
        } catch (PDOException $exception) {
            error_log('[Notificaciones] Error PDO al obtener pendientes: ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
            return [];
        } catch (\Throwable $throwable) {
            error_log('[Notificaciones] Error inesperado al obtener pendientes: ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString());
            return [];
        }
    }

    public static function mdlMarcarEntregadas(int $usuarioId, array $notificaciones, bool $marcarComoLeido = false): int
    {
        if ($usuarioId <= 0) {
            return 0;
        }

        $ids = array_map(static function ($valor): int {
            if (is_int($valor)) {
                return $valor;
            }
            if (is_string($valor) && is_numeric($valor)) {
                return (int)$valor;
            }
            return 0;
        }, $notificaciones);

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if (empty($ids)) {
            return 0;
        }

        try {
            $pdo = Conexion::conectar();

            $placeholders = [];
            $parametros = [':usuario_id' => $usuarioId];

            foreach ($ids as $indice => $id) {
                $paramName = ':notification_' . $indice;
                $placeholders[] = $paramName;
                $parametros[$paramName] = $id;
            }

            $columnasActualizar = [
                'entregado = 1',
                'entregado_en = IF(entregado_en IS NULL, NOW(), entregado_en)',
            ];

            if ($marcarComoLeido && self::columnaLeidoDisponible()) {
                $columnasActualizar[] = 'leido = 1';
                $columnasActualizar[] = 'leido_en = IF(leido_en IS NULL, NOW(), leido_en)';
            }

            $sql = 'UPDATE argus_notification_destinatarios'
                . ' SET ' . implode(', ', $columnasActualizar)
                . ' WHERE usuario_id = :usuario_id AND notification_id IN (' . implode(', ', $placeholders) . ')';

            $stmt = $pdo->prepare($sql);
            foreach ($parametros as $nombre => $valor) {
                $stmt->bindValue($nombre, $valor, PDO::PARAM_INT);
            }

            $stmt->execute();
            return (int)$stmt->rowCount();
        } catch (PDOException $exception) {
            error_log('[Notificaciones] Error PDO al marcar entregadas: ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
            return 0;
        } catch (\Throwable $throwable) {
            error_log('[Notificaciones] Error inesperado al marcar entregadas: ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString());
            return 0;
        }
    }

    public static function mdlObtenerHistorial(int $usuarioId, int $limite = 10): array
    {
        if ($usuarioId <= 0) {
            return [];
        }

        $limite = max(1, min($limite, 50));

        try {
            $pdo = Conexion::conectar();
            $columnaLeido = self::columnaLeidoDisponible();
            $sql = 'SELECT n.id, n.solicitud_id, n.mensaje, n.created_at, n.tipo, n.referencia_id, n.url, d.entregado'
                . ($columnaLeido ? ', d.leido' : '')
                . ' FROM argus_notification_destinatarios d'
                . ' INNER JOIN argus_notifications n ON n.id = d.notification_id'
                . ' WHERE d.usuario_id = :usuario_id';

            if ($columnaLeido) {
                $sql .= ' AND d.leido = 0';
            }

            $sql .= ' ORDER BY n.created_at DESC LIMIT ' . $limite;

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();

            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(static function (array $fila) use ($columnaLeido): array {
                return [
                    'id' => (int)($fila['id'] ?? 0),
                    'solicitud_id' => isset($fila['solicitud_id']) ? (int)$fila['solicitud_id'] : 0,
                    'mensaje' => (string)($fila['mensaje'] ?? ''),
                    'created_at' => $fila['created_at'] ?? null,
                    'entregado' => !empty($fila['entregado']),
                    'tipo' => (string)($fila['tipo'] ?? 'solicitud'),
                    'referencia_id' => isset($fila['referencia_id']) ? (int)$fila['referencia_id'] : null,
                    'url' => $fila['url'] ?? null,
                    'leido' => $columnaLeido ? !empty($fila['leido']) : null,
                ];
            }, $filas);
        } catch (PDOException $exception) {
            error_log('[Notificaciones] Error PDO al obtener historial: ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
            return [];
        } catch (\Throwable $throwable) {
            error_log('[Notificaciones] Error inesperado al obtener historial: ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString());
            return [];
        }
    }

    public static function mdlContarPendientes(int $usuarioId): int
    {
        if ($usuarioId <= 0) {
            return 0;
        }

        try {
            $pdo = Conexion::conectar();
            $sql = 'SELECT COUNT(*) FROM argus_notification_destinatarios WHERE usuario_id = :usuario_id AND leido = 0';
            if (!self::columnaLeidoDisponible()) {
                $sql = 'SELECT COUNT(*) FROM argus_notification_destinatarios WHERE usuario_id = :usuario_id AND entregado = 0';
            }

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $exception) {
            error_log('[Notificaciones] Error PDO al contar pendientes: ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
            return 0;
        } catch (\Throwable $throwable) {
            error_log('[Notificaciones] Error inesperado al contar pendientes: ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString());
            return 0;
        }
    }

    private static function columnaLeidoDisponible(): bool
    {
        static $disponible = null;

        if ($disponible !== null) {
            return $disponible;
        }

        try {
            $pdo = Conexion::conectar();
            $stmt = $pdo->query("SHOW COLUMNS FROM argus_notification_destinatarios LIKE 'leido'");
            $disponible = $stmt && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (PDOException $exception) {
            error_log('[Notificaciones] Error PDO al detectar columna leido: ' . $exception->getMessage());
            $disponible = false;
        } catch (\Throwable $throwable) {
            error_log('[Notificaciones] Error inesperado al detectar columna leido: ' . $throwable->getMessage());
            $disponible = false;
        }

        return $disponible;
    }
}
