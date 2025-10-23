<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Conexion;
use PDO;

/**
 * Modelo de contratos (placeholder).
 */
class ModeloContratos
{
    private static ?bool $columnaEstatusDisponible = null;
    private static ?bool $columnaCreadoPorDisponible = null;
    private static ?bool $columnaFolioDisponible = null;
    /**
     * Comprueba si existe un contrato para un cliente determinado.
     *
     * @param int $clienteId
     * @return bool Verdadero si existe un contrato
     */
    static public function mdlExisteContratoPorCliente($clienteId)
    {
        $stmt = Conexion::conectar()->prepare("SELECT id FROM argus_contratos_data WHERE cliente_id = :id LIMIT 1");
        $stmt->bindParam(":id", $clienteId, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return $resultado ? true : false;
    }

    /**
     * Inserta un nuevo contrato en la base de datos.
     *
     * @param array $datos
     * @return int ID del contrato creado
     */
    static public function mdlCrearContrato($datos)
    {
        $pdo = Conexion::conectar();
        $folio = self::normalizarFolio($datos['folio'] ?? null);
        $columnas = ['cliente_id', 'desarrollo_id', 'datta_contrato'];
        $placeholders = [':cliente_id', ':desarrollo_id', ':datta_contrato'];

        if (self::columnaCreadoPorDisponible()) {
            $columnas[] = 'creado_por';
            $placeholders[] = ':creado_por';
        }

        if (self::columnaFolioDisponible()) {
            $columnas[] = 'folio';
            $placeholders[] = ':folio';
        }

        $sql = sprintf(
            'INSERT INTO argus_contratos_data (%s) VALUES (%s)',
            implode(', ', $columnas),
            implode(', ', $placeholders)
        );

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":cliente_id", $datos['cliente_id'], PDO::PARAM_INT);
        $stmt->bindParam(":desarrollo_id", $datos['desarrollo_id'], PDO::PARAM_INT);
        $stmt->bindParam(":datta_contrato", $datos['datta_contrato'], PDO::PARAM_STR);
        if (self::columnaCreadoPorDisponible()) {
            $creadoPor = isset($datos['creado_por']) ? (int)$datos['creado_por'] : null;
            if ($creadoPor !== null) {
                $stmt->bindValue(':creado_por', $creadoPor, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':creado_por', null, PDO::PARAM_NULL);
            }
        }
        if (self::columnaFolioDisponible()) {
            if ($folio !== null) {
                $stmt->bindValue(':folio', $folio, PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':folio', null, PDO::PARAM_NULL);
            }
        }
        $stmt->execute();

        return (int)$pdo->lastInsertId();
    }

    

    /**
     * Obtiene un contrato existente para un cliente. Se une con la tabla de desarrollos
     * para incluir el nombre del desarrollo, su superficie y tipo de contrato.
     *
     * @param int $clienteId
     * @return array|null
     */
    static public function mdlMostrarContratoPorCliente($clienteId)
    {
        $columnas = [
            'c.datta_contrato',
            'd.nombre AS nombre_desarrollo',
        ];

        if (self::columnaFolioDisponible()) {
            $columnas[] = 'c.folio';
        } else {
            $columnas[] = "JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, '$.contrato.folio')) AS folio";
        }

        $sql = 'SELECT ' . implode(', ', $columnas)
             . ' FROM argus_contratos_data c'
             . ' INNER JOIN argus_desarrollos d ON c.desarrollo_id = d.id'
             . ' WHERE c.cliente_id = :cliente_id'
             . ' LIMIT 1';

        $stmt = Conexion::conectar()->prepare($sql);
        $stmt->bindParam(":cliente_id", $clienteId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * Obtiene uno o varios contratos. Cuando se pasa un ID de cliente, se
     * devuelven únicamente los contratos para ese cliente, incluyendo los
     * nombres del cliente y del desarrollo y el tipo de contrato. Si no se
     * pasa parámetro, se devuelven todos los contratos existentes.
     *
     * @param int|null $clienteId ID del cliente o null para todos
     * @return array Lista de contratos
     */
    static public function mdlMostrarContratos($clienteId = null)
    {
        $pdo = Conexion::conectar();
        $columnas = [
            'c.id',
            'c.cliente_id',
            'c.desarrollo_id',
            'c.datta_contrato',
            'c.created_at',
        ];

        if (self::columnaFolioDisponible()) {
            $columnas[] = 'c.folio';
        } else {
            $columnas[] = "JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, '$.contrato.folio')) AS folio";
        }

        if (self::columnaEstatusDisponible()) {
            $columnas[] = 'c.estatus';
        }

        $columnas[] = 'cl.nombre AS nombre_cliente';
        $columnas[] = 'd.nombre AS nombre_desarrollo';
        $columnas[] = 'd.tipo_contrato AS tipo_contrato';
        $columnas[] = 'd.lotes_disponibles';

        $sql = 'SELECT ' . implode(', ', $columnas)
                . ' FROM argus_contratos_data c
                INNER JOIN argus_clientes cl ON c.cliente_id = cl.id
                INNER JOIN argus_desarrollos d ON c.desarrollo_id = d.id';

        if ($clienteId !== null) {
            $sql .= ' WHERE c.cliente_id = :cli';
        }

        $sql .= ' ORDER BY c.created_at DESC';

        $stmt = $pdo->prepare($sql);
        if ($clienteId !== null) {
            $stmt->bindParam(':cli', $clienteId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza un contrato existente. Sólo se actualiza el campo datta_contrato
     * con un nuevo JSON. Se identifica por su ID.
     *
     * @param int $idContrato ID del contrato
     * @param string $jsonData JSON codificado con los datos del contrato
     * @return string 'ok' o 'error'
     */
    static public function mdlEditarContrato($idContrato, $jsonData, ?string $folio = null, ?int $clienteId = null, ?int $desarrolloId = null)
    {
        $pdo = Conexion::conectar();
        $sql = 'UPDATE argus_contratos_data SET datta_contrato = :json';

        if ($clienteId !== null && $clienteId > 0) {
            $sql .= ', cliente_id = :cliente_id';
        }

        if ($desarrolloId !== null && $desarrolloId > 0) {
            $sql .= ', desarrollo_id = :desarrollo_id';
        }

        $folioNormalizado = self::normalizarFolio($folio);
        if (self::columnaFolioDisponible()) {
            $sql .= ', folio = :folio';
        }

        $sql .= ' WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':json', $jsonData, PDO::PARAM_STR);
        $stmt->bindParam(':id', $idContrato, PDO::PARAM_INT);

        if ($clienteId !== null && $clienteId > 0) {
            $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
        }

        if ($desarrolloId !== null && $desarrolloId > 0) {
            $stmt->bindValue(':desarrollo_id', $desarrolloId, PDO::PARAM_INT);
        }

        if (self::columnaFolioDisponible()) {
            if ($folioNormalizado !== null) {
                $stmt->bindValue(':folio', $folioNormalizado, PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':folio', null, PDO::PARAM_NULL);
            }
        }
        if ($stmt->execute()) {
            return 'ok';
        }
        return 'error';
    }

    /**
     * Devuelve un contrato específico por ID, incluyendo su JSON y llaves
     * de cliente y desarrollo. Si no existe, devuelve null.
     *
     * @param int $idContrato
     * @return array|null
     */
    static public function mdlMostrarContratoPorId($idContrato)
    {
        $columnas = [
            'c.id',
            'c.cliente_id',
            'c.desarrollo_id',
            'c.datta_contrato',
        ];

        if (self::columnaFolioDisponible()) {
            $columnas[] = 'c.folio';
        } else {
            $columnas[] = "JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, '$.contrato.folio')) AS folio";
        }

        if (self::columnaEstatusDisponible()) {
            $columnas[] = 'c.estatus';
        }

        $sql = 'SELECT ' . implode(', ', $columnas)
            . ' FROM argus_contratos_data c WHERE c.id = :id LIMIT 1';

        $stmt = Conexion::conectar()->prepare($sql);
        $stmt->bindParam(':id', $idContrato, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function mdlActualizarEstatusIndividual(int $contratoId, int $nuevoEstatus): bool
    {
        if (!self::columnaEstatusDisponible()) {
            return self::actualizarEstatusEnJson([$contratoId], $nuevoEstatus);
        }

        $pdo = Conexion::conectar();
        $stmt = $pdo->prepare('UPDATE argus_contratos_data SET estatus = :estatus WHERE id = :id');
        $stmt->bindValue(':estatus', $nuevoEstatus, PDO::PARAM_INT);
        $stmt->bindValue(':id', $contratoId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Actualiza el estatus de múltiples contratos de forma masiva.
    // Recibe un array de IDs y el nuevo estatus (0 o 1).
    // Devuelve true si se actualizó al menos un registro, false en caso contrario.

    public static function mdlActualizarEstatusMasivo(array $ids, int $nuevoEstatus): bool
    {
        if (empty($ids)) {
            return false;
        }

        if (!self::columnaEstatusDisponible()) {
            return self::actualizarEstatusEnJson($ids, $nuevoEstatus);
        }

        $pdo = Conexion::conectar();
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE argus_contratos_data SET estatus = ? WHERE id IN ($marks)";
        $stmt = $pdo->prepare($sql);

        $params = array_merge([$nuevoEstatus], $ids);

        return $stmt->execute($params);
    }

    private static function columnaEstatusDisponible(): bool
    {
        if (self::$columnaEstatusDisponible !== null) {
            return self::$columnaEstatusDisponible;
        }

        $pdo = Conexion::conectar();
        $stmt = $pdo->prepare("SHOW COLUMNS FROM argus_contratos_data LIKE 'estatus'");
        $stmt->execute();
        self::$columnaEstatusDisponible = (bool)$stmt->fetch();

        return self::$columnaEstatusDisponible;
    }

    private static function actualizarEstatusEnJson(array $ids, int $nuevoEstatus): bool
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
        if (empty($ids)) {
            return false;
        }

        $pdo = Conexion::conectar();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $select = $pdo->prepare("SELECT id, datta_contrato FROM argus_contratos_data WHERE id IN ($placeholders)");
        $select->execute($ids);
        $registros = $select->fetchAll(PDO::FETCH_ASSOC);
        if (!$registros) {
            return false;
        }

        $actualizados = 0;
        $update = $pdo->prepare('UPDATE argus_contratos_data SET datta_contrato = :json WHERE id = :id');

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            foreach ($registros as $fila) {
                $json = json_decode((string)($fila['datta_contrato'] ?? ''), true);
                if (!is_array($json)) {
                    continue;
                }

                if (!isset($json['contrato']) || !is_array($json['contrato'])) {
                    $json['contrato'] = [];
                }

                $json['contrato']['estatus'] = $nuevoEstatus;
                $json['contrato']['estado'] = self::estadoTextoPorCodigo($nuevoEstatus);
                if ($nuevoEstatus === 2 && empty($json['contrato']['cancelado_en'])) {
                    $json['contrato']['cancelado_en'] = date('Y-m-d H:i:s');
                }

                $jsonCodificado = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                if ($jsonCodificado === false) {
                    continue;
                }

                $update->bindValue(':json', $jsonCodificado, PDO::PARAM_STR);
                $update->bindValue(':id', (int)$fila['id'], PDO::PARAM_INT);

                if ($update->execute()) {
                    $actualizados++;
                }
            }

            $pdo->commit();
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException('No fue posible actualizar el estatus del contrato.', 0, $throwable);
        }

        return $actualizados > 0;
    }

    private static function estadoTextoPorCodigo(int $estatus): string
    {
        return match ($estatus) {
            0 => 'archivado',
            2 => 'cancelado',
            default => 'activo',
        };
    }

    private static function columnaCreadoPorDisponible(): bool
    {
        if (self::$columnaCreadoPorDisponible !== null) {
            return self::$columnaCreadoPorDisponible;
        }

        $pdo = Conexion::conectar();
        $stmt = $pdo->prepare("SHOW COLUMNS FROM argus_contratos_data LIKE 'creado_por'");
        $stmt->execute();
        self::$columnaCreadoPorDisponible = (bool)$stmt->fetch();

        return self::$columnaCreadoPorDisponible;
    }

    private static function columnaFolioDisponible(): bool
    {
        if (self::$columnaFolioDisponible !== null) {
            return self::$columnaFolioDisponible;
        }

        $pdo = Conexion::conectar();
        $stmt = $pdo->prepare("SHOW COLUMNS FROM argus_contratos_data LIKE 'folio'");
        $stmt->execute();
        self::$columnaFolioDisponible = (bool)$stmt->fetch();

        return self::$columnaFolioDisponible;
    }

    public static function mdlExisteFolio(string $folio, ?int $excluirId = null): bool
    {
        $folioNormalizado = self::normalizarFolio($folio);
        if ($folioNormalizado === null) {
            return false;
        }

        $pdo = Conexion::conectar();

        if (self::columnaFolioDisponible()) {
            $sql = 'SELECT id FROM argus_contratos_data WHERE folio = :folio';
        } else {
            $sql = "SELECT id FROM argus_contratos_data WHERE JSON_UNQUOTE(JSON_EXTRACT(datta_contrato, '$.contrato.folio')) = :folio";
        }

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id <> :excluir';
        }

        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':folio', $folioNormalizado, PDO::PARAM_STR);

        if ($excluirId !== null && $excluirId > 0) {
            $stmt->bindValue(':excluir', $excluirId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function normalizarFolio(?string $folio): ?string
    {
        if ($folio === null) {
            return null;
        }

        $folio = trim($folio);
        if ($folio === '') {
            return null;
        }

        $folio = function_exists('mb_strtoupper') ? mb_strtoupper($folio, 'UTF-8') : strtoupper($folio);
        $folio = preg_replace('/[^A-Z0-9_-]/u', '', $folio);

        return $folio !== '' ? $folio : null;
    }

    public static function mdlContarContratosPorUsuario(?int $usuarioId = null): array
    {
        $pdo = Conexion::conectar();
        $columnasExtra = '';
        if (self::columnaEstatusDisponible()) {
            $columnasExtra .= ', estatus';
        }

        $sql = 'SELECT datta_contrato' . $columnasExtra;
        if (self::columnaCreadoPorDisponible()) {
            $sql .= ', creado_por';
        }
        $sql .= ' FROM argus_contratos_data';

        $params = [];
        if ($usuarioId !== null && self::columnaCreadoPorDisponible()) {
            $sql .= ' WHERE creado_por = :usuario';
            $params[':usuario'] = $usuarioId;
        }

        $stmt = $pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, PDO::PARAM_INT);
        }

        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total = 0;
        $activos = 0;
        $cancelados = 0;

        foreach ($filas as $fila) {
            $total++;
            $estado = null;

            if (isset($fila['estatus']) && $fila['estatus'] !== null) {
                $estado = (string)$fila['estatus'];
                if ($estado === '2' || $estado === 'cancelado') {
                    $cancelados++;
                    continue;
                }
            }

            if ($estado === null && isset($fila['datta_contrato'])) {
                $json = json_decode((string)$fila['datta_contrato'], true);
                if (is_array($json)) {
                    $estadoContrato = strtolower(trim((string)($json['contrato']['estado'] ?? '')));
                    if ($estadoContrato === 'cancelado') {
                        $cancelados++;
                        continue;
                    }
                }
            }

            $activos++;
        }

        return [
            'total' => $total,
            'activos' => $activos,
            'cancelados' => $cancelados,
        ];
    }

    public static function mdlContarContratosActivosPorCliente(int $clienteId): int
    {
        if ($clienteId <= 0) {
            return 0;
        }

        $pdo = Conexion::conectar();
        $columnas = ['datta_contrato'];
        if (self::columnaEstatusDisponible()) {
            $columnas[] = 'estatus';
        }

        $sql = 'SELECT ' . implode(', ', $columnas) . ' FROM argus_contratos_data WHERE cliente_id = :cliente';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':cliente', $clienteId, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $activos = 0;

        foreach ($filas as $fila) {
            if (isset($fila['estatus'])) {
                $estatus = (int)$fila['estatus'];
                if ($estatus === 1) {
                    $activos++;
                    continue;
                }

                if (in_array($estatus, [0, 2], true)) {
                    continue;
                }
            }

            $json = json_decode((string)($fila['datta_contrato'] ?? ''), true);
            if (is_array($json)) {
                $estadoContrato = strtolower(trim((string)($json['contrato']['estado'] ?? '')));
                if ($estadoContrato === 'cancelado' || $estadoContrato === 'archivado') {
                    continue;
                }
                if ($estadoContrato === 'activo') {
                    $activos++;
                    continue;
                }
            }

            if (!isset($fila['estatus'])) {
                $activos++;
            }
        }

        return $activos;
    }
}
