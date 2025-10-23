<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Conexion;
use PDO;

class ModeloClientes
{
    /**
     * Inserta un nuevo registro en la tabla argus_clientes.
     * @param array $datos Datos del cliente
     * @return string 'ok' en caso de éxito, 'error' en caso de fallo
     */
    public static function mdlAgregarCliente($datos) {
        $link = Conexion::conectar();
        $rfc = self::normalizarRfc($datos['rfc'] ?? '');
        $sql = "INSERT INTO argus_clientes (nombre, nacionalidad, fecha_nacimiento, rfc, curp, ine, estado_civil, ocupacion, telefono, domicilio, email, beneficiario, estado) VALUES (:nombre, :nacionalidad, :fecha_nacimiento, :rfc, :curp, :ine, :estado_civil, :ocupacion, :telefono, :domicilio, :email, :beneficiario, :estado)";
        $stmt = $link->prepare($sql);
        $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
        $stmt->bindParam(':nacionalidad', $datos['nacionalidad'], PDO::PARAM_STR);
        $stmt->bindParam(':fecha_nacimiento', $datos['fecha_nacimiento']);
        $stmt->bindParam(':rfc', $rfc, PDO::PARAM_STR);
        $stmt->bindParam(':curp', $datos['curp'], PDO::PARAM_STR);
        $stmt->bindParam(':ine', $datos['ine'], PDO::PARAM_STR);
        $stmt->bindParam(':estado_civil', $datos['estado_civil'], PDO::PARAM_STR);
        $stmt->bindParam(':ocupacion', $datos['ocupacion'], PDO::PARAM_STR);
        $stmt->bindParam(':telefono', $datos['telefono'], PDO::PARAM_STR);
        $stmt->bindParam(':domicilio', $datos['domicilio'], PDO::PARAM_STR);
        $stmt->bindParam(':email', $datos['email'], PDO::PARAM_STR);
        $stmt->bindParam(':beneficiario', $datos['beneficiario'], PDO::PARAM_STR);
        $estado = self::normalizarEstado($datos['estado'] ?? 'activo');
        $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
        if ($stmt->execute()) {
            return 'ok';
        }
        return 'error';
    }

    /**
     * Inserta un nuevo cliente y devuelve el ID generado. Si falla, devuelve 0.
     * Este método se utiliza cuando se necesita enlazar inmediatamente al cliente
     * con otros registros (por ejemplo, crear un contrato). No reemplaza a
     * mdlAgregarCliente, que sigue devolviendo 'ok' o 'error'.
     *
     * @param array $datos Datos del cliente
     * @return int ID del cliente insertado o 0 en caso de error
     */
    public static function mdlAgregarClienteRetId($datos) {
        $link = Conexion::conectar();
        $rfc = self::normalizarRfc($datos['rfc'] ?? '');
        $sql = "INSERT INTO argus_clientes (nombre, nacionalidad, fecha_nacimiento, rfc, curp, ine, estado_civil, ocupacion, telefono, domicilio, email, beneficiario, estado) VALUES (:nombre, :nacionalidad, :fecha_nacimiento, :rfc, :curp, :ine, :estado_civil, :ocupacion, :telefono, :domicilio, :email, :beneficiario, :estado)";
        $stmt = $link->prepare($sql);
        $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
        $stmt->bindParam(':nacionalidad', $datos['nacionalidad'], PDO::PARAM_STR);
        $stmt->bindParam(':fecha_nacimiento', $datos['fecha_nacimiento']);
        $stmt->bindParam(':rfc', $rfc, PDO::PARAM_STR);
        $stmt->bindParam(':curp', $datos['curp'], PDO::PARAM_STR);
        $stmt->bindParam(':ine', $datos['ine'], PDO::PARAM_STR);
        $stmt->bindParam(':estado_civil', $datos['estado_civil'], PDO::PARAM_STR);
        $stmt->bindParam(':ocupacion', $datos['ocupacion'], PDO::PARAM_STR);
        $stmt->bindParam(':telefono', $datos['telefono'], PDO::PARAM_STR);
        $stmt->bindParam(':domicilio', $datos['domicilio'], PDO::PARAM_STR);
        $stmt->bindParam(':email', $datos['email'], PDO::PARAM_STR);
        $stmt->bindParam(':beneficiario', $datos['beneficiario'], PDO::PARAM_STR);
        $estado = self::normalizarEstado($datos['estado'] ?? 'activo');
        $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
        if ($stmt->execute()) {
            return (int)$link->lastInsertId();
        }
        return 0;
    }

    /**
     * Obtiene todos los clientes registrados.
     * @return array
     */
    public static function mdlMostrarClientes() {
        $link = Conexion::conectar();
        $stmt = $link->query("SELECT * FROM argus_clientes ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza un registro de la tabla argus_clientes.
     *
     * @param array $datos Datos del cliente (incluye id)
     * @return string 'ok' en caso de éxito, 'error' en caso de fallo
     */
    public static function mdlEditarCliente($datos) {
        $link = Conexion::conectar();
        $rfc = self::normalizarRfc($datos['rfc'] ?? '');
        $sql = "UPDATE argus_clientes SET nombre = :nombre, nacionalidad = :nacionalidad, fecha_nacimiento = :fecha_nacimiento, rfc = :rfc, curp = :curp, ine = :ine, estado_civil = :estado_civil, ocupacion = :ocupacion, telefono = :telefono, domicilio = :domicilio, email = :email, beneficiario = :beneficiario WHERE id = :id";
        $stmt = $link->prepare($sql);
        $stmt->bindParam(':id', $datos['id'], PDO::PARAM_INT);
        $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
        $stmt->bindParam(':nacionalidad', $datos['nacionalidad'], PDO::PARAM_STR);
        $stmt->bindParam(':fecha_nacimiento', $datos['fecha_nacimiento']);
        $stmt->bindParam(':rfc', $rfc, PDO::PARAM_STR);
        $stmt->bindParam(':curp', $datos['curp'], PDO::PARAM_STR);
        $stmt->bindParam(':ine', $datos['ine'], PDO::PARAM_STR);
        $stmt->bindParam(':estado_civil', $datos['estado_civil'], PDO::PARAM_STR);
        $stmt->bindParam(':ocupacion', $datos['ocupacion'], PDO::PARAM_STR);
        $stmt->bindParam(':telefono', $datos['telefono'], PDO::PARAM_STR);
        $stmt->bindParam(':domicilio', $datos['domicilio'], PDO::PARAM_STR);
        $stmt->bindParam(':email', $datos['email'], PDO::PARAM_STR);
        $stmt->bindParam(':beneficiario', $datos['beneficiario'], PDO::PARAM_STR);
        if ($stmt->execute()) {
            return 'ok';
        }
        return 'error';
    }

    /**
     * Actualiza el estado (activo/archivado) de un cliente.
     */
    public static function mdlActualizarEstado(int $clienteId, string $estado): string
    {
        $estadoNormalizado = self::normalizarEstado($estado);
        if ($estadoNormalizado === '') {
            return 'error_estado';
        }

        $link = Conexion::conectar();
        $stmt = $link->prepare('UPDATE argus_clientes SET estado = :estado WHERE id = :id');
        $stmt->bindValue(':estado', $estadoNormalizado, PDO::PARAM_STR);
        $stmt->bindValue(':id', $clienteId, PDO::PARAM_INT);

        return $stmt->execute() ? 'ok' : 'error';
    }

    /**
     * Obtiene un cliente por su ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function mdlMostrarClientePorId($id) {
        $link = Conexion::conectar();
        $stmt = $link->prepare("SELECT * FROM argus_clientes WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca un cliente que coincida por RFC o CURP.
     *
     * Si se proporcionan ambos identificadores se prioriza la coincidencia por RFC.
     *
     * @param string|null $rfc
     * @param string|null $curp
     * @return array|null
     */
    public static function mdlBuscarPorRfcCurp(?string $rfc, ?string $curp): ?array
    {
        $rfcNormalizado = null;
        if ($rfc !== null) {
            $rfcNormalizado = self::normalizarRfc($rfc);
            if ($rfcNormalizado === '') {
                $rfcNormalizado = null;
            }
        }

        $curpNormalizado = null;
        if ($curp !== null) {
            $curpNormalizado = self::normalizarCurp($curp);
            if ($curpNormalizado === '') {
                $curpNormalizado = null;
            }
        }

        if ($rfcNormalizado === null && $curpNormalizado === null) {
            return null;
        }

        $link = Conexion::conectar();

        $condiciones = [];
        $params = [];

        if ($rfcNormalizado !== null) {
            $condiciones[] = 'rfc = :rfc';
            $params[':rfc'] = $rfcNormalizado;
        }

        if ($curpNormalizado !== null) {
            $condiciones[] = 'UPPER(curp) = :curp';
            $params[':curp'] = $curpNormalizado;
        }

        $sql = 'SELECT * FROM argus_clientes WHERE ' . implode(' OR ', $condiciones);

        $orden = [];
        if ($rfcNormalizado !== null) {
            $orden[] = 'CASE WHEN rfc = :orden_rfc THEN 0 ELSE 1 END';
            $params[':orden_rfc'] = $rfcNormalizado;
        }
        if ($curpNormalizado !== null) {
            $orden[] = 'CASE WHEN UPPER(curp) = :orden_curp THEN 0 ELSE 1 END';
            $params[':orden_curp'] = $curpNormalizado;
        }
        $orden[] = "CASE WHEN estado = 'activo' THEN 0 ELSE 1 END";
        $orden[] = 'id DESC';

        $sql .= ' ORDER BY ' . implode(', ', $orden) . ' LIMIT 1';

        $stmt = $link->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public static function mdlExisteRfc(string $rfc, ?int $excluirId = null): bool
    {
        $normalizado = self::normalizarRfc($rfc);
        if ($normalizado === '') {
            return false;
        }

        $link = Conexion::conectar();
        $sql = 'SELECT id FROM argus_clientes WHERE rfc = :rfc';
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id <> :id';
        }
        $sql .= ' LIMIT 1';

        $stmt = $link->prepare($sql);
        $stmt->bindValue(':rfc', $normalizado, PDO::PARAM_STR);
        if ($excluirId !== null && $excluirId > 0) {
            $stmt->bindValue(':id', $excluirId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function normalizarRfc(?string $rfc): string
    {
        if ($rfc === null) {
            return '';
        }

        $limpio = strtoupper(trim($rfc));
        $limpio = preg_replace('/[^A-Z0-9&]/u', '', $limpio);

        return $limpio ?? '';
    }

    private static function normalizarCurp(?string $curp): string
    {
        if ($curp === null) {
            return '';
        }

        $limpio = strtoupper(trim($curp));
        $limpio = preg_replace('/[^A-Z0-9]/u', '', $limpio);

        return $limpio ?? '';
    }

    private static function normalizarEstado(?string $estado): string
    {
        if ($estado === null) {
            return 'activo';
        }

        $estado = strtolower(trim($estado));
        $permitidos = ['activo', 'archivado'];

        return in_array($estado, $permitidos, true) ? $estado : '';
    }
}
