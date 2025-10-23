<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Conexion;
use PDO;

/**
 * Modelo para variables generales (nacionalidades, tipos de contrato, etc.).
 */
class ModeloVariables
{
    /**
     * Obtiene todas las variables de un tipo dado.
     *
     * @param string $tipo Tipo de variable (ej: 'nacionalidad', 'tipo_contrato')
     * @return array
     */
    static public function mdlMostrarVariables($tipo)
    {
        $stmt = Conexion::conectar()->prepare("SELECT * FROM argus_variables WHERE tipo = :tipo ORDER BY id DESC");
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una variable individual por su identificador numérico.
     *
     * @param int $id Identificador autoincremental de la tabla argus_variables.
     * @return array|null Arreglo asociativo con los datos de la variable o null si no existe.
     */
    public static function mdlObtenerVariablePorId(int $id): ?array
    {
        $stmt = Conexion::conectar()->prepare(
            "SELECT * FROM argus_variables WHERE id = :id LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Devuelve el nombre de una variable dado su tipo e identificador.
     * Si no existe, devuelve el identificador original.
     *
     * @param string $tipo Tipo de variable (ej: 'nacionalidad', 'tipo_contrato')
     * @param string $identificador Identificador único de la variable
     * @return string Nombre de la variable o el mismo identificador si no se encuentra
     */
    static public function mdlObtenerNombrePorIdentificador($tipo, $identificador)
    {
        $stmt = Conexion::conectar()->prepare(
            "SELECT nombre FROM argus_variables WHERE tipo = :tipo AND identificador = :identificador LIMIT 1"
        );
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindParam(':identificador', $identificador, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['nombre'] ?? $identificador;
    }

    /**
     * Inserta una nueva variable.
     *
     * @param array $datos
     * @return string 'ok' o 'error'
     */
    static public function mdlAgregarVariable($datos)
    {
        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO argus_variables (tipo, identificador, nombre) VALUES (:tipo, :identificador, :nombre)"
        );
        $stmt->bindParam(':tipo', $datos['tipo'], PDO::PARAM_STR);
        $stmt->bindParam(':identificador', $datos['identificador'], PDO::PARAM_STR);
        $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
        return $stmt->execute() ? 'ok' : 'error';
    }

    /**
     * Actualiza una variable existente.
     *
     * @param array $datos
     * @return string
     */
    static public function mdlEditarVariable($datos)
    {
        $stmt = Conexion::conectar()->prepare(
            "UPDATE argus_variables SET identificador = :identificador, nombre = :nombre WHERE id = :id"
        );
        $stmt->bindParam(':identificador', $datos['identificador'], PDO::PARAM_STR);
        $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
        $stmt->bindParam(':id', $datos['id'], PDO::PARAM_INT);
        return $stmt->execute() ? 'ok' : 'error';
    }

    /**
     * Elimina una variable por su ID.
     *
     * @param int $id
     * @return string
     */
    static public function mdlEliminarVariable($id)
    {
        $stmt = Conexion::conectar()->prepare("DELETE FROM argus_variables WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute() ? 'ok' : 'error';
    }
}
