<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Conexion;
use PDO;

class ModeloPlantillasSolicitudes
{
    public static function mdlObtenerPlantillas(): array
    {
        $stmt = Conexion::conectar()->prepare(
            'SELECT id, tipo, nombre_archivo, ruta_archivo, created_at, updated_at
             FROM argus_solicitud_plantillas
             ORDER BY tipo ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function mdlObtenerPlantillaPorTipo(string $tipo): ?array
    {
        $stmt = Conexion::conectar()->prepare(
            'SELECT id, tipo, nombre_archivo, ruta_archivo
             FROM argus_solicitud_plantillas
             WHERE tipo = :tipo
             LIMIT 1'
        );
        $stmt->bindValue(':tipo', strtolower($tipo), PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function mdlGuardarPlantilla(string $tipo, string $nombreArchivo, string $rutaArchivo): bool
    {
        $pdo = Conexion::conectar();
        $stmt = $pdo->prepare(
            'INSERT INTO argus_solicitud_plantillas (tipo, nombre_archivo, ruta_archivo)
             VALUES (:tipo, :nombre, :ruta)
             ON DUPLICATE KEY UPDATE nombre_archivo = VALUES(nombre_archivo), ruta_archivo = VALUES(ruta_archivo), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->bindValue(':tipo', strtolower($tipo), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $nombreArchivo, PDO::PARAM_STR);
        $stmt->bindValue(':ruta', $rutaArchivo, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public static function mdlEliminarPlantilla(int $id): bool
    {
        $stmt = Conexion::conectar()->prepare(
            'DELETE FROM argus_solicitud_plantillas WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public static function mdlObtenerPlantillaPorId(int $id): ?array
    {
        $stmt = Conexion::conectar()->prepare(
            'SELECT id, tipo, nombre_archivo, ruta_archivo
             FROM argus_solicitud_plantillas
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function mdlActualizarPlantilla(int $id, string $tipo, string $nombreArchivo, string $rutaArchivo): bool
    {
        $stmt = Conexion::conectar()->prepare(
            'UPDATE argus_solicitud_plantillas
             SET tipo = :tipo,
                 nombre_archivo = :nombre,
                 ruta_archivo = :ruta,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->bindValue(':tipo', strtolower($tipo), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $nombreArchivo, PDO::PARAM_STR);
        $stmt->bindValue(':ruta', $rutaArchivo, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
