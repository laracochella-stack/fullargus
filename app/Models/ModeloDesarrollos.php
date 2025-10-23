<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Conexion;
use PDO;

/**
 * Modelo de desarrollos (placeholder).
 */
class ModeloDesarrollos
{
    /**
     * Inserta un nuevo desarrollo en la tabla argus_desarrollos.
     *
     * @param array $datos Datos del desarrollo (nombre, tipo_contrato, descripcion, superficie, clave_catastral, lotes_disponibles, precio_lote, precio_total)
     * @return string 'ok' en caso de éxito, 'error' en caso de fallo
     */
    public static function mdlAgregarDesarrollo($datos) {
        $link = Conexion::conectar();
        $sql = "INSERT INTO argus_desarrollos (nombre, tipo_contrato, descripcion, superficie, clave_catastral, lotes_disponibles, precio_lote, precio_total) VALUES (:nombre, :tipo_contrato, :descripcion, :superficie, :clave_catastral, :lotes_disponibles, :precio_lote, :precio_total)";
        $stmt = $link->prepare($sql);
        $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
        $stmt->bindParam(':tipo_contrato', $datos['tipo_contrato'], PDO::PARAM_STR);
        $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
        $stmt->bindParam(':superficie', $datos['superficie'], PDO::PARAM_STR);
        $stmt->bindParam(':clave_catastral', $datos['clave_catastral'], PDO::PARAM_STR);
        // Guardamos los lotes como cadena JSON (TEXT) en base de datos
        $stmt->bindParam(':lotes_disponibles', $datos['lotes_disponibles'], PDO::PARAM_STR);
        $stmt->bindParam(':precio_lote', $datos['precio_lote']);
        $stmt->bindParam(':precio_total', $datos['precio_total']);
        if ($stmt->execute()) {
            return 'ok';
        }
        return 'error';
    }

    /**
     * Actualiza un registro de la tabla argus_desarrollos.
     *
     * @param array $datos Datos del desarrollo (incluye id) que se va a editar
     * @return string 'ok' en caso de éxito, 'error' en caso de fallo
     */
    public static function mdlEditarDesarrollo($datos) {
        $link = Conexion::conectar();
        $sql = "UPDATE argus_desarrollos SET nombre = :nombre, tipo_contrato = :tipo_contrato, descripcion = :descripcion, superficie = :superficie, clave_catastral = :clave_catastral, lotes_disponibles = :lotes_disponibles, precio_lote = :precio_lote, precio_total = :precio_total WHERE id = :id";
        $stmt = $link->prepare($sql);
        $stmt->bindParam(':id', $datos['id'], PDO::PARAM_INT);
        $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
        $stmt->bindParam(':tipo_contrato', $datos['tipo_contrato'], PDO::PARAM_STR);
        $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
        $stmt->bindParam(':superficie', $datos['superficie'], PDO::PARAM_STR);
        $stmt->bindParam(':clave_catastral', $datos['clave_catastral'], PDO::PARAM_STR);
        // Guardamos los lotes como cadena JSON (TEXT) en base de datos
        $stmt->bindParam(':lotes_disponibles', $datos['lotes_disponibles'], PDO::PARAM_STR);
        $stmt->bindParam(':precio_lote', $datos['precio_lote']);
        $stmt->bindParam(':precio_total', $datos['precio_total']);
        if ($stmt->execute()) {
            return 'ok';
        }
        return 'error';
    }

    /**
     * Obtiene todos los desarrollos registrados.
     *
     * @return array
     */
    public static function mdlMostrarDesarrollos() {
        $link = Conexion::conectar();
        $stmt = $link->query("SELECT * FROM argus_desarrollos ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un desarrollo por su ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function mdlMostrarDesarrolloPorId($id) {
        $link = Conexion::conectar();
        $stmt = $link->prepare("SELECT * FROM argus_desarrollos WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina un desarrollo por su identificador.
     */
    public static function mdlEliminarDesarrollo(int $id): string
    {
        if ($id <= 0) {
            return 'error';
        }

        $link = Conexion::conectar();
        $stmt = $link->prepare('DELETE FROM argus_desarrollos WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute() ? 'ok' : 'error';
    }
}
