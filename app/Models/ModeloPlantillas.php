<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Conexion;
use PDO;

/**
 * Modelo para plantillas de contratos.
 */
class ModeloPlantillas
{
    /**
     * Inserta un registro de plantilla.
     *
     * @param array $datos
     * @return string 'ok' o 'error'
     */
    static public function mdlAgregarPlantilla($datos)
    {
        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO argus_plantillas (tipo_contrato_id, nombre_archivo, ruta_archivo) VALUES (:tipo_id, :nombre_archivo, :ruta_archivo)"
        );
        $stmt->bindParam(':tipo_id', $datos['tipo_contrato_id'], PDO::PARAM_INT);
        $stmt->bindParam(':nombre_archivo', $datos['nombre_archivo'], PDO::PARAM_STR);
        $stmt->bindParam(':ruta_archivo', $datos['ruta_archivo'], PDO::PARAM_STR);
        return $stmt->execute() ? 'ok' : 'error';
    }

    /**
     * Obtiene todas las plantillas con información del tipo de contrato.
     *
     * @return array
     */
    static public function mdlMostrarPlantillas()
    {
        $stmt = Conexion::conectar()->prepare(
            "SELECT p.id, p.tipo_contrato_id, v.nombre AS nombre_tipo, p.nombre_archivo, p.ruta_archivo, p.created_at
             FROM argus_plantillas p
             LEFT JOIN argus_variables v ON p.tipo_contrato_id = v.id"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina una plantilla por su ID.
     *
     * @param int $id
     * @return string
     */
    static public function mdlEliminarPlantilla($id)
    {
        $stmt = Conexion::conectar()->prepare("DELETE FROM argus_plantillas WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute() ? 'ok' : 'error';
    }

    /**
     * Actualiza una plantilla existente. Permite cambiar el tipo de contrato,
     * el nombre original del archivo y la ruta del archivo.
     * Si se desea mantener alguno de los valores, debe enviarse el
     * valor actual.
     *
     * @param array $datos Debe contener id, tipo_contrato_id, nombre_archivo, ruta_archivo
     * @return string
     */
    static public function mdlEditarPlantilla($datos)
    {
        $stmt = Conexion::conectar()->prepare(
            "UPDATE argus_plantillas SET tipo_contrato_id = :tipo_id, nombre_archivo = :nombre_archivo, ruta_archivo = :ruta_archivo WHERE id = :id"
        );
        $stmt->bindParam(':tipo_id', $datos['tipo_contrato_id'], PDO::PARAM_INT);
        $stmt->bindParam(':nombre_archivo', $datos['nombre_archivo'], PDO::PARAM_STR);
        $stmt->bindParam(':ruta_archivo', $datos['ruta_archivo'], PDO::PARAM_STR);
        $stmt->bindParam(':id', $datos['id'], PDO::PARAM_INT);
        return $stmt->execute() ? 'ok' : 'error';
    }

    /**
     * Obtiene una plantilla por tipo de contrato excluyendo un ID específico.
     *
     * @param int $tipoId
     * @param int $excluirId
     * @return array|null
     */
    public static function mdlObtenerPlantillaPorTipoExcluyendoId(int $tipoId, int $excluirId): ?array
    {
        $stmt = Conexion::conectar()->prepare(
            "SELECT id, tipo_contrato_id, nombre_archivo, ruta_archivo
             FROM argus_plantillas
             WHERE tipo_contrato_id = :tipo_id AND id <> :excluir_id
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->bindParam(':tipo_id', $tipoId, PDO::PARAM_INT);
        $stmt->bindParam(':excluir_id', $excluirId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtiene una plantilla por su identificador de tipo de contrato.
     *
     * Este método retorna la primera plantilla que coincida con el tipo de contrato
     * indicado. Se espera que la tabla argus_plantillas contenga una columna
     * tipo_contrato_id que haga referencia al tipo definido en la tabla argus_variables.
     *
     * @param mixed $tipoId Identificador del tipo de contrato
     * @return array|null Devuelve los campos de la plantilla o null si no existe
     */
    static public function mdlObtenerPlantillaPorTipo($tipoId)
    {
        $pdo = Conexion::conectar();
        // Si el parámetro es numérico intentamos obtener por ID directamente.
        if (is_numeric($tipoId)) {
            $stmt = $pdo->prepare(
                "SELECT id, tipo_contrato_id, nombre_archivo, ruta_archivo
                 FROM argus_plantillas
                 WHERE tipo_contrato_id = :tipo_id
                 LIMIT 1"
            );
            $stmt->bindParam(':tipo_id', $tipoId);
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) return $res;
        }
        // Cuando no es numérico o no se encuentra coincidencia por ID, buscar por
        // identificador o nombre en la tabla de variables. Esto permite usar
        // directamente el string almacenado en argus_desarrollos.tipo_contrato.
        $stmt = $pdo->prepare(
            "SELECT p.id, p.tipo_contrato_id, p.nombre_archivo, p.ruta_archivo
             FROM argus_plantillas p
             INNER JOIN argus_variables v ON p.tipo_contrato_id = v.id
             WHERE v.identificador = :identificador OR v.nombre = :nombre
             LIMIT 1"
        );
        $valorBusqueda = $tipoId === null ? '' : (string)$tipoId;
        $stmt->bindValue(':identificador', $valorBusqueda, PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $valorBusqueda, PDO::PARAM_STR);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? $res : null;
    }
}
