<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Conexion;
use PDO;

class ModeloSolicitudes
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;
    private static ?bool $columnaNombreCortoDisponible = null;
    private static ?bool $columnasRetornoDisponibles = null;
    private static ?bool $columnaFolioDisponible = null;
    private static ?bool $columnaContratoEstatusDisponible = null;
    private static ?bool $jsonFuncionesDisponibles = null;

    public static function mdlCrearSolicitud(array $datosSolicitud, string $estado, int $usuarioId): int
    {
        $datos = $datosSolicitud;
        $folio = self::normalizarFolio($datos['folio'] ?? null);
        $datos['folio'] = $folio;

        $json = self::encodeSolicitudDatta($datos);
        if ($json === null) {
            return 0;
        }

        $link = Conexion::conectar();
        $columnas = ['solicitud_datta', 'estado', 'usuario_id'];
        $placeholders = [':solicitud_datta', ':estado', ':usuario_id'];

        if (self::columnaFolioDisponible()) {
            array_unshift($columnas, 'folio');
            array_unshift($placeholders, ':folio');
        }

        $stmt = $link->prepare(
            'INSERT INTO argus_solicitudes (' . implode(', ', $columnas) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->bindValue(':solicitud_datta', $json, PDO::PARAM_STR);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);

        if (self::columnaFolioDisponible()) {
            if ($folio !== null) {
                $stmt->bindValue(':folio', $folio, PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':folio', null, PDO::PARAM_NULL);
            }
        }

        if ($stmt->execute()) {
            return (int) $link->lastInsertId();
        }

        return 0;
    }

    public static function mdlActualizarSolicitud(int $id, array $datosSolicitud): bool
    {
        $datos = $datosSolicitud;
        $folio = self::normalizarFolio($datos['folio'] ?? null);
        $datos['folio'] = $folio;

        $json = self::encodeSolicitudDatta($datos);
        if ($json === null) {
            return false;
        }

        $link = Conexion::conectar();
        $sql = 'UPDATE argus_solicitudes SET solicitud_datta = :solicitud_datta, updated_at = CURRENT_TIMESTAMP';
        if (self::columnaFolioDisponible()) {
            $sql .= ', folio = :folio';
        }

        $sql .= ' WHERE id = :id';

        $stmt = $link->prepare($sql);
        $stmt->bindValue(':solicitud_datta', $json, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        if (self::columnaFolioDisponible()) {
            if ($folio !== null) {
                $stmt->bindValue(':folio', $folio, PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':folio', null, PDO::PARAM_NULL);
            }
        }

        return $stmt->execute();
    }

    public static function mdlObtenerSolicitudes(
        ?int $usuarioId,
        bool $verCanceladas,
        bool $incluirBorradores = false,
        ?int $soloBorradoresUsuarioId = null,
        array $filtros = []
    ): array
    {
        $link = Conexion::conectar();
        $columnas = [
            's.id',
            's.solicitud_datta',
            's.estado',
            's.usuario_id',
            's.created_at',
            's.updated_at',
            'u.username',
            self::subselectContratoId() . ' AS contrato_id',
            self::subselectContratoFolio() . ' AS contrato_folio',
            self::subselectContratoEstado() . ' AS contrato_estado',
        ];

        if (self::columnaFolioDisponible()) {
            $columnas[] = 's.folio';
        } else {
            $columnas[] = "JSON_UNQUOTE(JSON_EXTRACT(s.solicitud_datta, '$.folio')) AS folio";
        }

        if (self::columnaNombreCortoDisponible()) {
            $columnas[] = 'u.nombre_corto';
        }

        if (self::columnasRetornoDisponibles()) {
            $columnas[] = 's.motivo_retorno';
            $columnas[] = 's.devuelto_por';
            $columnas[] = 's.devuelto_en';
        }

        $sql = 'SELECT ' . implode(', ', $columnas)
            . ' FROM argus_solicitudes s'
            . ' JOIN argus_users u ON u.id = s.usuario_id'
            . ' WHERE 1 = 1';

        $params = [];
        if ($usuarioId !== null) {
            $sql .= ' AND s.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }

        if (!$verCanceladas) {
            $sql .= " AND s.estado <> 'cancelada'";
        }

        if ($incluirBorradores) {
            if ($soloBorradoresUsuarioId !== null) {
                $sql .= ' AND (s.estado <> \'borrador\' OR s.usuario_id = :solo_borradores_usuario)';
                $params[':solo_borradores_usuario'] = $soloBorradoresUsuarioId;
            }
        } else {
            $sql .= " AND s.estado <> 'borrador'";
        }

        $estadoFiltro = isset($filtros['estado']) ? strtolower(trim((string)$filtros['estado'])) : null;
        if ($estadoFiltro !== null && $estadoFiltro !== 'todos') {
            if ($estadoFiltro === 'activos') {
                $sql .= " AND s.estado <> 'cancelada'";
            } elseif (in_array($estadoFiltro, ['borrador', 'enviada', 'en_revision', 'aprobada', 'cancelada'], true)) {
                $sql .= ' AND s.estado = :estado_filtro';
                $params[':estado_filtro'] = $estadoFiltro;
            }
        }

        $propietarioFiltro = isset($filtros['propietario']) ? strtolower(trim((string)$filtros['propietario'])) : null;
        $usuarioActualId = isset($filtros['usuario_actual_id']) ? (int)$filtros['usuario_actual_id'] : null;
        if ($usuarioId === null && $usuarioActualId) {
            if ($propietarioFiltro === 'propios') {
                $sql .= ' AND s.usuario_id = :usuario_actual_propios';
                $params[':usuario_actual_propios'] = $usuarioActualId;
            } elseif ($propietarioFiltro === 'otros') {
                $sql .= ' AND s.usuario_id <> :usuario_actual_otros';
                $params[':usuario_actual_otros'] = $usuarioActualId;
            }
        }

        $sql .= " ORDER BY CASE WHEN s.estado = 'enviada' THEN 0 ELSE 1 END, s.id DESC";

        $stmt = $link->prepare($sql);
        foreach ($params as $clave => $valor) {
            $tipo = is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($clave, $valor, $tipo);
        }

        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $resultado = [];
        foreach ($filas as $fila) {
            $mapeada = self::mapearFila($fila);
            if ($mapeada !== null) {
                $resultado[] = $mapeada;
            }
        }

        return $resultado;
    }

    public static function mdlObtenerSolicitudPorId(int $id): ?array
    {
        $link = Conexion::conectar();
        $columnas = [
            's.id',
            's.solicitud_datta',
            's.estado',
            's.usuario_id',
            's.created_at',
            's.updated_at',
            'u.username',
            self::subselectContratoId() . ' AS contrato_id',
            self::subselectContratoFolio() . ' AS contrato_folio',
            self::subselectContratoEstado() . ' AS contrato_estado',
        ];

        if (self::columnaFolioDisponible()) {
            $columnas[] = 's.folio';
        } else {
            $columnas[] = "JSON_UNQUOTE(JSON_EXTRACT(s.solicitud_datta, '$.folio')) AS folio";
        }

        if (self::columnaNombreCortoDisponible()) {
            $columnas[] = 'u.nombre_corto';
        }

        if (self::columnasRetornoDisponibles()) {
            $columnas[] = 's.motivo_retorno';
            $columnas[] = 's.devuelto_por';
            $columnas[] = 's.devuelto_en';
        }

        $sql = 'SELECT ' . implode(', ', $columnas)
            . ' FROM argus_solicitudes s JOIN argus_users u ON u.id = s.usuario_id'
            . ' WHERE s.id = :id LIMIT 1';

        $stmt = $link->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return self::mapearFila($resultado);
    }

    /**
     * Busca solicitudes que coincidan con al menos uno de los datos proporcionados
     * (folio, RFC o CURP). Solo se consideran las solicitudes que no están
     * vinculadas actualmente a un contrato.
     */
    public static function mdlBuscarSolicitudesCoincidencias(array $criterios, int $limite = 10): array
    {
        $folio = self::normalizarFolio($criterios['folio'] ?? null);
        $rfc = self::normalizarTextoBusqueda($criterios['rfc'] ?? null);
        $curp = self::normalizarTextoBusqueda($criterios['curp'] ?? null);

        if ($folio === null && $rfc === null && $curp === null) {
            return [];
        }

        $limite = max(1, min(50, $limite));
        $link = Conexion::conectar();

        $usarJson = self::jsonFuncionesDisponibles($link);
        $filas = [];

        if ($usarJson) {
            try {
                $filas = self::buscarSolicitudesCoincidenciasJson($link, $folio, $rfc, $curp, $limite);
            } catch (\Throwable $throwable) {
                // Si la consulta con funciones JSON falla, marcamos como no disponibles
                // y utilizamos la ruta de compatibilidad.
                self::$jsonFuncionesDisponibles = false;
                $usarJson = false;
            }
        }

        if (!$usarJson) {
            $filas = self::buscarSolicitudesCoincidenciasFallback($link, $folio, $rfc, $curp, $limite);
        }

        if (empty($filas)) {
            return [];
        }

        $resultado = [];
        foreach ($filas as $fila) {
            $mapeada = self::mapearFila($fila);
            if ($mapeada === null) {
                continue;
            }

            $id = isset($mapeada['id']) ? (int) $mapeada['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            if ($usarJson) {
                // La consulta con JSON ya excluye contratos existentes, pero en caso
                // de estructuras inconsistentes volvemos a validar.
                if (!empty($mapeada['contrato_id'])) {
                    continue;
                }
            } else {
                if (self::solicitudVinculadaContrato($link, $id)) {
                    continue;
                }

                // Garantizar claves de contrato presentes aunque no se utilicen.
                $mapeada['contrato_id'] = null;
                $mapeada['contrato_folio'] = null;
                $mapeada['contrato_estado'] = null;
            }

            if (!isset($mapeada['nombre_completo']) || trim((string) $mapeada['nombre_completo']) === '') {
                $posiblesNombres = [
                    self::extraerDatoSolicitud($mapeada, 'nombre_completo'),
                    self::extraerDatoSolicitud($mapeada, 'nombre'),
                    self::extraerDatoSolicitud($mapeada, 'cliente_nombre'),
                    self::extraerDatoSolicitud($mapeada, 'cliente_nombre_completo'),
                ];
                foreach ($posiblesNombres as $nombre) {
                    if ($nombre !== null && trim($nombre) !== '') {
                        $mapeada['nombre_completo'] = trim($nombre);
                        break;
                    }
                }
                if (!isset($mapeada['nombre_completo'])) {
                    $mapeada['nombre_completo'] = '';
                }
            }

            $coincidencias = self::calcularCoincidenciasFila($mapeada, $folio, $rfc, $curp);
            if ($coincidencias <= 0) {
                continue;
            }

            $mapeada['coincidencias'] = $coincidencias;
            $resultado[$id] = $mapeada;
        }

        if (empty($resultado)) {
            return [];
        }

        $listado = array_values($resultado);

        usort($listado, static function (array $a, array $b): int {
            $coincidenciasA = (int) ($a['coincidencias'] ?? 0);
            $coincidenciasB = (int) ($b['coincidencias'] ?? 0);
            if ($coincidenciasA === $coincidenciasB) {
                return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
            }
            return $coincidenciasB <=> $coincidenciasA;
        });

        if (count($listado) > $limite) {
            $listado = array_slice($listado, 0, $limite);
        }

        return $listado;
    }

    private static function subselectContratoId(): string
    {
        return '(
            SELECT c.id
              FROM argus_contratos_data c
             WHERE CAST(JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.solicitud_origen_id\')) AS UNSIGNED) = s.id
             ORDER BY c.id DESC
             LIMIT 1
        )';
    }

    private static function subselectContratoFolio(): string
    {
        return '(
            SELECT COALESCE(
                    c.folio,
                    JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.folio\'))
                )
              FROM argus_contratos_data c
             WHERE CAST(JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.solicitud_origen_id\')) AS UNSIGNED) = s.id
             ORDER BY c.id DESC
             LIMIT 1
        )';
    }

    private static function subselectContratoEstado(): string
    {
        if (self::columnaContratoEstatusDisponible()) {
            return '(
                SELECT COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.estado\')),
                        CASE
                            WHEN c.estatus IS NULL THEN NULL
                            WHEN c.estatus = 2 THEN \'cancelado\'
                            WHEN c.estatus = 1 THEN \'activo\'
                            ELSE CAST(c.estatus AS CHAR)
                        END
                    )
                  FROM argus_contratos_data c
                 WHERE CAST(JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.solicitud_origen_id\')) AS UNSIGNED) = s.id
                 ORDER BY c.id DESC
                 LIMIT 1
            )';
        }

        return '(
            SELECT CASE
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.estado\')) IS NULL THEN NULL
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.estado\')) REGEXP \'^[0-9]+$\' THEN CASE CAST(JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.estado\')) AS UNSIGNED)
                            WHEN 2 THEN \'cancelado\'
                            WHEN 1 THEN \'activo\'
                            ELSE JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.estado\'))
                        END
                    ELSE JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.estado\'))
                END
              FROM argus_contratos_data c
             WHERE CAST(JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, \'$.contrato.solicitud_origen_id\')) AS UNSIGNED) = s.id
             ORDER BY c.id DESC
             LIMIT 1
        )';
    }

    private static function columnaNombreCortoDisponible(): bool
    {
        if (self::$columnaNombreCortoDisponible !== null) {
            return self::$columnaNombreCortoDisponible;
        }

        $link = Conexion::conectar();
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS"
             . " WHERE TABLE_SCHEMA = DATABASE()"
             . "   AND TABLE_NAME = 'argus_users'"
             . "   AND COLUMN_NAME = 'nombre_corto'";

        $stmt = $link->query($sql);
        self::$columnaNombreCortoDisponible = ((int)$stmt->fetchColumn()) > 0;

        return self::$columnaNombreCortoDisponible;
    }

    private static function columnasRetornoDisponibles(): bool
    {
        if (self::$columnasRetornoDisponibles !== null) {
            return self::$columnasRetornoDisponibles;
        }

        $link = Conexion::conectar();
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS"
             . " WHERE TABLE_SCHEMA = DATABASE()"
             . "   AND TABLE_NAME = 'argus_solicitudes'"
             . "   AND COLUMN_NAME = 'motivo_retorno'";

        $stmt = $link->query($sql);
        self::$columnasRetornoDisponibles = ((int)$stmt->fetchColumn()) > 0;

        return self::$columnasRetornoDisponibles;
    }

    private static function columnaFolioDisponible(): bool
    {
        if (self::$columnaFolioDisponible !== null) {
            return self::$columnaFolioDisponible;
        }

        $link = Conexion::conectar();
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS"
             . " WHERE TABLE_SCHEMA = DATABASE()"
             . "   AND TABLE_NAME = 'argus_solicitudes'"
             . "   AND COLUMN_NAME = 'folio'";

        $stmt = $link->query($sql);
        self::$columnaFolioDisponible = ((int)$stmt->fetchColumn()) > 0;

        return self::$columnaFolioDisponible;
    }

    private static function columnaContratoEstatusDisponible(): bool
    {
        if (self::$columnaContratoEstatusDisponible !== null) {
            return self::$columnaContratoEstatusDisponible;
        }

        $link = Conexion::conectar();
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS"
             . " WHERE TABLE_SCHEMA = DATABASE()"
             . "   AND TABLE_NAME = 'argus_contratos_data'"
             . "   AND COLUMN_NAME = 'estatus'";

        $stmt = $link->query($sql);
        self::$columnaContratoEstatusDisponible = ((int)$stmt->fetchColumn()) > 0;

        return self::$columnaContratoEstatusDisponible;
    }

    public static function mdlExisteFolio(string $folio, ?int $excluirId = null): bool
    {
        $folioNormalizado = self::normalizarFolio($folio);
        if ($folioNormalizado === null) {
            return false;
        }

        $link = Conexion::conectar();

        if (self::columnaFolioDisponible()) {
            $sql = 'SELECT id FROM argus_solicitudes WHERE folio = :folio';
        } else {
            $sql = "SELECT id FROM argus_solicitudes WHERE JSON_UNQUOTE(JSON_EXTRACT(solicitud_datta, '$.folio')) = :folio";
        }

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id <> :excluir';
        }

        $sql .= ' LIMIT 1';

        $stmt = $link->prepare($sql);
        $stmt->bindValue(':folio', $folioNormalizado, PDO::PARAM_STR);

        if ($excluirId !== null && $excluirId > 0) {
            $stmt->bindValue(':excluir', $excluirId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function mdlActualizarEstado(int $id, string $estado, ?string $motivo = null, ?int $usuarioId = null): bool
    {
        $link = Conexion::conectar();

        $set = 'estado = :estado, updated_at = CURRENT_TIMESTAMP';
        $motivoDisponible = self::columnasRetornoDisponibles();

        if ($motivoDisponible) {
            if ($estado === 'borrador') {
                $set .= ', motivo_retorno = :motivo_retorno, devuelto_por = :devuelto_por, devuelto_en = CURRENT_TIMESTAMP';
            } else {
                $set .= ', motivo_retorno = NULL, devuelto_por = NULL, devuelto_en = NULL';
            }
        }

        $usarTransaccion = ($estado === 'cancelada');
        $transaccionPropia = false;

        try {
            if ($usarTransaccion && !$link->inTransaction()) {
                $link->beginTransaction();
                $transaccionPropia = true;
            }

            $stmt = $link->prepare('UPDATE argus_solicitudes SET ' . $set . ' WHERE id = :id');
            $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            if ($motivoDisponible && $estado === 'borrador') {
                $motivoBorrador = $motivo !== null ? trim($motivo) : '';
                $stmt->bindValue(':motivo_retorno', $motivoBorrador, PDO::PARAM_STR);
                if ($usuarioId !== null && $usuarioId > 0) {
                    $stmt->bindValue(':devuelto_por', $usuarioId, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':devuelto_por', null, PDO::PARAM_NULL);
                }
            }

            if (!$stmt->execute()) {
                if ($transaccionPropia && $link->inTransaction()) {
                    $link->rollBack();
                }
                return false;
            }

            if ($estado === 'cancelada') {
                $motivoCancelacion = $motivo !== null ? trim($motivo) : '';
                if (function_exists('mb_substr')) {
                    $motivoCancelacion = mb_substr($motivoCancelacion, 0, 500, 'UTF-8');
                } else {
                    $motivoCancelacion = substr($motivoCancelacion, 0, 500);
                }

                $stmtSelect = $link->prepare('SELECT solicitud_datta FROM argus_solicitudes WHERE id = :id LIMIT 1');
                $stmtSelect->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtSelect->execute();
                $jsonActual = $stmtSelect->fetchColumn();

                $datosSolicitud = [];
                if (is_string($jsonActual) && $jsonActual !== '') {
                    $decoded = json_decode($jsonActual, true);
                    if (is_array($decoded)) {
                        $datosSolicitud = $decoded;
                    }
                }

                if (!is_array($datosSolicitud)) {
                    $datosSolicitud = [];
                }

                $cancelacionPrev = [];
                if (isset($datosSolicitud['cancelacion']) && is_array($datosSolicitud['cancelacion'])) {
                    $cancelacionPrev = $datosSolicitud['cancelacion'];
                }

                $cancelacionPrev['motivo'] = $motivoCancelacion;
                $cancelacionPrev['cancelada_por'] = ($usuarioId !== null && $usuarioId > 0) ? (int)$usuarioId : null;
                $cancelacionPrev['cancelada_en'] = date('Y-m-d H:i:s');

                $datosSolicitud['cancelacion'] = $cancelacionPrev;

                $jsonActualizado = json_encode($datosSolicitud, self::JSON_FLAGS);
                if ($jsonActualizado === false) {
                    throw new \RuntimeException('No fue posible preparar la información actualizada de la solicitud.');
                }

                $stmtUpdate = $link->prepare('UPDATE argus_solicitudes SET solicitud_datta = :json WHERE id = :id');
                $stmtUpdate->bindValue(':json', $jsonActualizado, PDO::PARAM_STR);
                $stmtUpdate->bindValue(':id', $id, PDO::PARAM_INT);

                if (!$stmtUpdate->execute()) {
                    throw new \RuntimeException('No fue posible guardar el motivo de cancelación de la solicitud.');
                }
            }

            if ($transaccionPropia && $link->inTransaction()) {
                $link->commit();
            }

            return true;
        } catch (\Throwable $throwable) {
            if ($transaccionPropia && $link->inTransaction()) {
                $link->rollBack();
            }

            return false;
        }
    }

    private static function mapearFila(?array $fila): ?array
    {
        if ($fila === null) {
            return null;
        }

        $datos = [];
        if (array_key_exists('solicitud_datta', $fila) && $fila['solicitud_datta'] !== null) {
            $decoded = json_decode((string) $fila['solicitud_datta'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $datos = $decoded;
            }
        }

        unset($fila['solicitud_datta']);

        foreach ($datos as $clave => $valor) {
            if (!array_key_exists($clave, $fila)) {
                $fila[$clave] = $valor;
            } else {
                $fila['solicitud_datta_' . $clave] = $valor;
            }
        }

        return $fila;
    }

    private static function extraerDatoSolicitud(array $fila, string $campo): ?string
    {
        if (array_key_exists($campo, $fila) && $fila[$campo] !== null && $fila[$campo] !== '') {
            return (string) $fila[$campo];
        }

        $claveAlterna = 'solicitud_datta_' . $campo;
        if (array_key_exists($claveAlterna, $fila) && $fila[$claveAlterna] !== null && $fila[$claveAlterna] !== '') {
            return (string) $fila[$claveAlterna];
        }

        return null;
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

    private static function normalizarTextoBusqueda($valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($texto, 'UTF-8')
            : strtoupper($texto);
    }

    private static function calcularCoincidenciasFila(array $fila, ?string $folio, ?string $rfc, ?string $curp): int
    {
        $coincidencias = 0;

        if ($folio !== null) {
            $valor = self::normalizarFolio(self::extraerDatoSolicitud($fila, 'folio'));
            if ($valor !== null && $valor === $folio) {
                $coincidencias++;
            }
        }

        if ($rfc !== null) {
            $valor = self::normalizarTextoBusqueda(self::extraerDatoSolicitud($fila, 'rfc'));
            if ($valor !== null && $valor === $rfc) {
                $coincidencias++;
            }
        }

        if ($curp !== null) {
            $valor = self::normalizarTextoBusqueda(self::extraerDatoSolicitud($fila, 'curp'));
            if ($valor !== null && $valor === $curp) {
                $coincidencias++;
            }
        }

        return $coincidencias;
    }

    private static function jsonFuncionesDisponibles(?PDO $link = null): bool
    {
        if (self::$jsonFuncionesDisponibles !== null) {
            return self::$jsonFuncionesDisponibles;
        }

        $pdo = $link ?? Conexion::conectar();

        try {
            $stmt = $pdo->query("SELECT JSON_EXTRACT('{\"x\":1}', '$.x') AS resultado");
            $stmt->fetch();
            self::$jsonFuncionesDisponibles = true;
        } catch (\Throwable $throwable) {
            self::$jsonFuncionesDisponibles = false;
        }

        return self::$jsonFuncionesDisponibles;
    }

    private static function buscarSolicitudesCoincidenciasJson(PDO $link, ?string $folio, ?string $rfc, ?string $curp, int $limite): array
    {
        $columnas = [
            's.id',
            's.solicitud_datta',
            's.estado',
            's.usuario_id',
            's.created_at',
            's.updated_at',
            'u.username',
            self::subselectContratoId() . ' AS contrato_id',
            self::subselectContratoFolio() . ' AS contrato_folio',
            self::subselectContratoEstado() . ' AS contrato_estado',
        ];

        if (self::columnaFolioDisponible()) {
            $columnas[] = 's.folio';
        } else {
            $columnas[] = "JSON_UNQUOTE(JSON_EXTRACT(s.solicitud_datta, '$.folio')) AS folio";
        }

        if (self::columnaNombreCortoDisponible()) {
            $columnas[] = 'u.nombre_corto';
        }

        if (self::columnasRetornoDisponibles()) {
            $columnas[] = 's.motivo_retorno';
            $columnas[] = 's.devuelto_por';
            $columnas[] = 's.devuelto_en';
        }

        $jsonRfc = "JSON_UNQUOTE(JSON_EXTRACT(s.solicitud_datta, '$.rfc'))";
        $jsonCurp = "JSON_UNQUOTE(JSON_EXTRACT(s.solicitud_datta, '$.curp'))";

        $condiciones = [];
        $params = [];
        $ordenCoincidencias = [];

        if ($folio !== null) {
            $campoFolio = self::columnaFolioDisponible()
                ? 's.folio'
                : "JSON_UNQUOTE(JSON_EXTRACT(s.solicitud_datta, '$.folio'))";
            $condiciones[] = $campoFolio . ' = :folio';
            $params[':folio'] = $folio;
            $ordenCoincidencias[] = 'CASE WHEN ' . $campoFolio . ' = :folio THEN 1 ELSE 0 END';
        }

        if ($rfc !== null) {
            $exprRfc = 'UPPER(TRIM(' . $jsonRfc . '))';
            $condiciones[] = $exprRfc . ' = :rfc';
            $params[':rfc'] = $rfc;
            $ordenCoincidencias[] = 'CASE WHEN ' . $exprRfc . ' = :rfc THEN 1 ELSE 0 END';
        }

        if ($curp !== null) {
            $exprCurp = 'UPPER(TRIM(' . $jsonCurp . '))';
            $condiciones[] = $exprCurp . ' = :curp';
            $params[':curp'] = $curp;
            $ordenCoincidencias[] = 'CASE WHEN ' . $exprCurp . ' = :curp THEN 1 ELSE 0 END';
        }

        if (empty($condiciones)) {
            return [];
        }

        $ordenamiento = 's.id DESC';
        if (!empty($ordenCoincidencias)) {
            $ordenamiento = '(' . implode(' + ', $ordenCoincidencias) . ') DESC, ' . $ordenamiento;
        }

        $sql = 'SELECT ' . implode(', ', $columnas)
            . ' FROM argus_solicitudes s'
            . ' JOIN argus_users u ON u.id = s.usuario_id'
            . " WHERE s.estado <> 'cancelada'"
            . ' AND NOT EXISTS ('
                . ' SELECT 1'
                . ' FROM argus_contratos_data c'
                . " WHERE CAST(JSON_UNQUOTE(JSON_EXTRACT(c.datta_contrato, '\$.contrato.solicitud_origen_id')) AS UNSIGNED) = s.id"
            . ' )'
            . ' AND (' . implode(' OR ', $condiciones) . ')'
            . ' ORDER BY ' . $ordenamiento
            . ' LIMIT :limite';

        $stmt = $link->prepare($sql);

        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function buscarSolicitudesCoincidenciasFallback(PDO $link, ?string $folio, ?string $rfc, ?string $curp, int $limite): array
    {
        $columnas = [
            's.id',
            's.solicitud_datta',
            's.estado',
            's.usuario_id',
            's.created_at',
            's.updated_at',
            'u.username',
        ];

        if (self::columnaFolioDisponible()) {
            $columnas[] = 's.folio';
        }

        if (self::columnaNombreCortoDisponible()) {
            $columnas[] = 'u.nombre_corto';
        }

        if (self::columnasRetornoDisponibles()) {
            $columnas[] = 's.motivo_retorno';
            $columnas[] = 's.devuelto_por';
            $columnas[] = 's.devuelto_en';
        }

        $condiciones = [];
        $params = [];

        if ($folio !== null) {
            $sub = [];
            if (self::columnaFolioDisponible()) {
                $sub[] = 'UPPER(TRIM(s.folio)) = :folio_exact';
                $params[':folio_exact'] = $folio;
            }
            $sub[] = 'UPPER(s.solicitud_datta) LIKE :folio_like';
            $params[':folio_like'] = sprintf('%%"FOLIO":"%s%%', $folio);
            $condiciones[] = '(' . implode(' OR ', $sub) . ')';
        }

        if ($rfc !== null) {
            $condiciones[] = 'UPPER(s.solicitud_datta) LIKE :rfc_like';
            $params[':rfc_like'] = sprintf('%%"RFC":"%s%%', $rfc);
        }

        if ($curp !== null) {
            $condiciones[] = 'UPPER(s.solicitud_datta) LIKE :curp_like';
            $params[':curp_like'] = sprintf('%%"CURP":"%s%%', $curp);
        }

        if (empty($condiciones)) {
            return [];
        }

        $limiteConsulta = min(150, max($limite * 3, $limite + 10));

        $sql = 'SELECT ' . implode(', ', $columnas)
            . ' FROM argus_solicitudes s'
            . ' JOIN argus_users u ON u.id = s.usuario_id'
            . " WHERE s.estado <> 'cancelada'"
            . ' AND (' . implode(' OR ', $condiciones) . ')'
            . ' ORDER BY s.id DESC'
            . ' LIMIT :limite';

        $stmt = $link->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limiteConsulta, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function solicitudVinculadaContrato(PDO $link, int $solicitudId): bool
    {
        if ($solicitudId <= 0) {
            return false;
        }

        static $stmt = null;
        if ($stmt === null) {
            $stmt = $link->prepare(
                'SELECT id FROM argus_contratos_data'
                . ' WHERE datta_contrato LIKE :patternNumero'
                . '    OR datta_contrato LIKE :patternCadena'
                . ' ORDER BY id DESC LIMIT 1'
            );
        }

        $stmt->bindValue(':patternNumero', '%"solicitud_origen_id":' . $solicitudId . '%', PDO::PARAM_STR);
        $stmt->bindValue(':patternCadena', '%"solicitud_origen_id":"' . $solicitudId . '"%', PDO::PARAM_STR);
        $stmt->execute();
        $existe = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $existe;
    }

    private static function encodeSolicitudDatta(array $datos): ?string
    {
        $json = json_encode($datos, self::JSON_FLAGS);
        if ($json === false) {
            return null;
        }

        return $json;
    }

    public static function mdlContarSolicitudesPorEstado(?int $usuarioId = null): array
    {
        $link = Conexion::conectar();
        $sql = 'SELECT estado, COUNT(*) AS total FROM argus_solicitudes';
        $params = [];

        if ($usuarioId !== null) {
            $sql .= ' WHERE usuario_id = :usuario';
            $params[':usuario'] = $usuarioId;
        }

        $sql .= ' GROUP BY estado';

        $stmt = $link->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, PDO::PARAM_INT);
        }
        $stmt->execute();

        $conteos = [
            'borrador' => 0,
            'enviada' => 0,
            'en_revision' => 0,
            'aprobada' => 0,
            'cancelada' => 0,
        ];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $estado = $fila['estado'] ?? '';
            $total = (int)($fila['total'] ?? 0);
            if (array_key_exists($estado, $conteos)) {
                $conteos[$estado] = $total;
            }
        }

        return $conteos;
    }
}
