<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Conexion;
use DateTimeInterface;
use PDO;
use PDOException;

class ModeloAnuncios
{
    public static function mdlCrearAnuncio(
        string $mensaje,
        ?DateTimeInterface $vigenteHasta,
        int $creadoPor,
        bool $mostrarEnDashboard,
        bool $mostrarEnPopup
    ): int {
        try {
            $pdo = Conexion::conectar();
            $sql = 'INSERT INTO argus_admin_announcements '
                . '(mensaje, vigente_hasta, creado_por, activo, mostrar_en_dashboard, mostrar_en_popup) '
                . 'VALUES (:mensaje, :vigente_hasta, :creado_por, 1, :mostrar_dashboard, :mostrar_popup)';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);

            if ($vigenteHasta !== null) {
                $stmt->bindValue(':vigente_hasta', $vigenteHasta->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':vigente_hasta', null, PDO::PARAM_NULL);
            }

            if ($creadoPor > 0) {
                $stmt->bindValue(':creado_por', $creadoPor, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':creado_por', null, PDO::PARAM_NULL);
            }

            $stmt->bindValue(':mostrar_dashboard', $mostrarEnDashboard ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':mostrar_popup', $mostrarEnPopup ? 1 : 0, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                return 0;
            }

            return (int)$pdo->lastInsertId();
        } catch (PDOException $exception) {
            return 0;
        }
    }

    public static function mdlActualizarAnuncio(
        int $anuncioId,
        string $mensaje,
        ?DateTimeInterface $vigenteHasta,
        bool $activar,
        bool $mostrarEnDashboard,
        bool $mostrarEnPopup
    ): bool {
        if ($anuncioId <= 0) {
            return false;
        }

        try {
            $pdo = Conexion::conectar();
            $sql = 'UPDATE argus_admin_announcements '
                . 'SET mensaje = :mensaje, vigente_hasta = :vigente_hasta, activo = :activo, '
                . 'mostrar_en_dashboard = :mostrar_dashboard, mostrar_en_popup = :mostrar_popup '
                . 'WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);
            if ($vigenteHasta !== null) {
                $stmt->bindValue(':vigente_hasta', $vigenteHasta->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':vigente_hasta', null, PDO::PARAM_NULL);
            }
            $stmt->bindValue(':activo', $activar ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':mostrar_dashboard', $mostrarEnDashboard ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':mostrar_popup', $mostrarEnPopup ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':id', $anuncioId, PDO::PARAM_INT);

            $resultado = $stmt->execute();

            return $resultado;
        } catch (PDOException $exception) {
            return false;
        }
    }

    public static function mdlDesactivarAnuncio(int $anuncioId): bool
    {
        if ($anuncioId <= 0) {
            return false;
        }

        try {
            $pdo = Conexion::conectar();
            $stmt = $pdo->prepare('UPDATE argus_admin_announcements SET activo = 0, vigente_hasta = COALESCE(vigente_hasta, NOW()) WHERE id = :id');
            $stmt->bindValue(':id', $anuncioId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $exception) {
            return false;
        }
    }

    public static function mdlCerrarExpirados(): void
    {
        try {
            $pdo = Conexion::conectar();
            $pdo->exec('UPDATE argus_admin_announcements SET activo = 0 WHERE activo = 1 AND vigente_hasta IS NOT NULL AND vigente_hasta < NOW()');
        } catch (PDOException $exception) {
            // Tabla aún no disponible.
        }
    }

    public static function mdlObtenerAnuncioVigente(): ?array
    {
        self::mdlCerrarExpirados();

        try {
            $pdo = Conexion::conectar();
            $sql = 'SELECT id, mensaje, vigente_hasta, creado_por, creado_en, activo, mostrar_en_dashboard, mostrar_en_popup '
                . 'FROM argus_admin_announcements '
                . 'WHERE activo = 1 '
                . 'AND (vigente_hasta IS NULL OR vigente_hasta >= NOW()) '
                . 'ORDER BY mostrar_en_dashboard DESC, creado_en DESC LIMIT 1';
            $stmt = $pdo->query($sql);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            return $fila !== false ? $fila : null;
        } catch (PDOException $exception) {
            return null;
        }
    }

    public static function mdlObtenerActivos(?string $destino = null): array
    {
        self::mdlCerrarExpirados();

        try {
            $pdo = Conexion::conectar();

            $condiciones = ['activo = 1', '(vigente_hasta IS NULL OR vigente_hasta >= NOW())'];
            if ($destino === 'dashboard') {
                $condiciones[] = 'mostrar_en_dashboard = 1';
            } elseif ($destino === 'popup') {
                $condiciones[] = 'mostrar_en_popup = 1';
            }

            $sql = 'SELECT id, mensaje, vigente_hasta, creado_por, creado_en, activo, mostrar_en_dashboard, mostrar_en_popup '
                . 'FROM argus_admin_announcements';

            if (!empty($condiciones)) {
                $sql .= ' WHERE ' . implode(' AND ', $condiciones);
            }

            $sql .= ' ORDER BY creado_en DESC';

            $stmt = $pdo->query($sql);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $exception) {
            return [];
        }
    }

    public static function mdlListarAnuncios(int $limite = 20): array
    {
        try {
            $pdo = Conexion::conectar();
            $sql = 'SELECT id, mensaje, vigente_hasta, creado_por, creado_en, activo, mostrar_en_dashboard, mostrar_en_popup '
                . 'FROM argus_admin_announcements ORDER BY creado_en DESC LIMIT :limite';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $exception) {
            return [];
        }
    }

    public static function mdlObtenerPorId(int $anuncioId): ?array
    {
        if ($anuncioId <= 0) {
            return null;
        }

        try {
            $pdo = Conexion::conectar();
            $stmt = $pdo->prepare('SELECT id, mensaje, vigente_hasta, creado_por, creado_en, activo, mostrar_en_dashboard, mostrar_en_popup '
                . 'FROM argus_admin_announcements WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $anuncioId, PDO::PARAM_INT);
            $stmt->execute();
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            return $fila !== false ? $fila : null;
        } catch (PDOException $exception) {
            return null;
        }
    }
}
