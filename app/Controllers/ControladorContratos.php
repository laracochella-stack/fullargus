<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Conexion;
use App\Models\ModeloClientes;
use App\Models\ModeloContratos;
use App\Models\ModeloDesarrollos;
use App\Models\ModeloPlantillas;
use App\Models\ModeloSolicitudes;
use App\Models\ModeloUsuarios;
use DateTimeImmutable;
use DateTimeZone;
use NumberFormatter;

/**
 * Controlador de contratos (placeholder).
 */
class ControladorContratos
{
    private static ?DateTimeZone $zonaAplicacion = null;

    private static function zonaHorariaAplicacion(): DateTimeZone
    {
        if (self::$zonaAplicacion === null) {
            self::$zonaAplicacion = new DateTimeZone('America/Mexico_City');
        }

        return self::$zonaAplicacion;
    }

    private static function parsearFecha(?string $valor): ?DateTimeImmutable
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string)$valor);
        if ($texto === '') {
            return null;
        }

        $tz = self::zonaHorariaAplicacion();
        $formatos = ['d-m-Y', 'd/m/Y', 'Y-m-d', 'Y/m/d', 'j-n-Y', 'j/n/Y'];
        foreach ($formatos as $formato) {
            $fecha = DateTimeImmutable::createFromFormat($formato, $texto, $tz);
            if ($fecha instanceof DateTimeImmutable) {
                return $fecha;
            }
        }

        try {
            return new DateTimeImmutable($texto, $tz);
        } catch (\Exception $exception) {
            return null;
        }
    }

    private static function formatearFechaAlmacen(?DateTimeImmutable $fecha): ?string
    {
        if ($fecha === null) {
            return null;
        }

        return $fecha->setTimezone(self::zonaHorariaAplicacion())->format('Y-m-d');
    }

    private static function fechaActualFormateada(): string
    {
        return self::formatearFechaAlmacen(new DateTimeImmutable('now', self::zonaHorariaAplicacion()));
    }

    private static function fechaActualConHora(): string
    {
        return (new DateTimeImmutable('now', self::zonaHorariaAplicacion()))->format('d-m-Y H:i:s');
    }

    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    }

    private static function esRutaAbsoluta(string $ruta): bool
    {
        if ($ruta === '') {
            return false;
        }

        if (str_starts_with($ruta, '/') || str_starts_with($ruta, '\\')) {
            return true;
        }

        if (strlen($ruta) >= 3 && ctype_alpha($ruta[0]) && $ruta[1] === ':') {
            $tercero = $ruta[2] ?? '';
            if ($tercero === '\\' || $tercero === '/') {
                return true;
            }
        }

        return str_contains($ruta, '://');
    }

    private static function resolverRutaArchivo(?string $ruta): ?string
    {
        if ($ruta === null) {
            return null;
        }

        $ruta = trim(str_replace('\\', '/', $ruta));
        if ($ruta === '') {
            return null;
        }

        $candidatos = [];
        if (self::esRutaAbsoluta($ruta)) {
            $candidatos[] = $ruta;
        }

        $base = self::basePath();
        $candidatos[] = $base . '/' . ltrim($ruta, '/');
        $candidatos[] = dirname(__DIR__) . '/' . ltrim($ruta, '/');

        foreach ($candidatos as $candidato) {
            if ($candidato && is_file($candidato)) {
                $real = realpath($candidato);
                return $real !== false ? $real : $candidato;
            }
        }

        return null;
    }

    private static function usuarioTieneRol(array $roles): bool
    {
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return false;
        }

        $permiso = strtolower(trim((string)($_SESSION['permission'] ?? '')));

        if ($permiso === 'admin') {
            return true;
        }

        return in_array($permiso, $roles, true);
    }

    private static function normalizarNombreCorto(?string $valor): string
    {
        $texto = trim((string)$valor);
        if ($texto === '') {
            return '';
        }

        return function_exists('mb_strtoupper') ? mb_strtoupper($texto, 'UTF-8') : strtoupper($texto);
    }

    private static function obtenerNombreCortoContrato(?int $solicitudId, ?string $predeterminado = null): string
    {
        $fuentes = [];

        if ($solicitudId !== null && $solicitudId > 0) {
            try {
                $solicitud = ModeloSolicitudes::mdlObtenerSolicitudPorId($solicitudId);
            } catch (\Throwable $e) {
                $solicitud = null;
            }

            if (is_array($solicitud)) {
                $fuentes[] = $solicitud['nombre_corto'] ?? null;
                $fuentes[] = $solicitud['nombre_completo'] ?? null;
                $fuentes[] = $solicitud['username'] ?? null;
            }
        }

        $fuentes[] = $predeterminado;
        $fuentes[] = $_SESSION['nombre_corto'] ?? null;
        $fuentes[] = $_SESSION['nombre_completo'] ?? null;
        $fuentes[] = $_SESSION['username'] ?? null;

        foreach ($fuentes as $fuente) {
            $normalizado = self::normalizarNombreCorto($fuente);
            if ($normalizado !== '') {
                return $normalizado;
            }
        }

        return 'USUARIO_DESCONOCIDO';
    }

    private static function usuarioPuedeGestionarContratos(): bool
    {
        return self::usuarioTieneRol(['moderator', 'senior', 'owner', 'admin']);
    }

    private static function usuarioActualId(): int
    {
        return (int)($_SESSION['id'] ?? 0);
    }

    private static function validarPasswordActual(string $password): bool
    {
        $passwordPlano = (string)$password;
        if (trim($passwordPlano) === '') {
            return false;
        }

        $usuarioId = self::usuarioActualId();
        if ($usuarioId <= 0) {
            return false;
        }

        $usuario = ModeloUsuarios::mdlObtenerUsuarioPorId($usuarioId);
        if (!$usuario || empty($usuario['password'])) {
            return false;
        }

        return password_verify($passwordPlano, (string)$usuario['password']);
    }

    private static function obtenerIdentificacionSolicitud(?int $solicitudId): string
    {
        if ($solicitudId === null || $solicitudId <= 0) {
            return '';
        }

        try {
            $solicitud = ModeloSolicitudes::mdlObtenerSolicitudPorId($solicitudId);
        } catch (\Throwable $throwable) {
            $solicitud = null;
        }

        if (!is_array($solicitud)) {
            return '';
        }

        $valor = $solicitud['identificacion']
            ?? $solicitud['solicitud_datta_identificacion']
            ?? '';
        $valor = trim((string)$valor);
        if ($valor === '') {
            return '';
        }

        return function_exists('mb_strtoupper') ? mb_strtoupper($valor, 'UTF-8') : strtoupper($valor);
    }

    /**
     * Normaliza la colección de tipos de contrato recibida desde los parámetros.
     *
     * @param array<int|string, mixed> $tipos
     * @return array<string, string>
     */
    private static function normalizarTiposContratoContexto(array $tipos): array
    {
        $mapa = [];

        foreach ($tipos as $clave => $valor) {
            if (is_array($valor)) {
                $identificador = (string)($valor['identificador'] ?? $valor['id'] ?? '');
                $nombre = (string)($valor['nombre'] ?? $valor['descripcion'] ?? '');
                if ($identificador === '') {
                    continue;
                }

                $mapa[$identificador] = $nombre !== '' ? $nombre : $identificador;
                continue;
            }

            if (is_string($clave)) {
                $mapa[(string)$clave] = (string)$valor;
            }
        }

        return $mapa;
    }

    /**
     * Limpia la información de la solicitud para exponer únicamente valores escalares.
     *
     * @param array<string, mixed> $solicitud
     * @return array<string, mixed>
     */
    private static function limpiarSolicitudParaRespuesta(array $solicitud): array
    {
        $limpia = [];

        foreach ($solicitud as $clave => $valor) {
            if (is_scalar($valor) || $valor === null) {
                $limpia[(string)$clave] = is_string($valor) ? trim((string)$valor) : $valor;
            }
        }

        return $limpia;
    }

    /**
     * Normaliza y estructura los datos relevantes de una solicitud para ser
     * almacenados dentro del JSON del contrato. Incluye secciones de albacea y
     * anualidad con formatos consistentes y valores preparados para los
     * placeholders del documento.
     *
     * @param array<string, mixed>|null $solicitud Datos crudos de la solicitud
     * @param array<string, mixed> $base Valores previos que sirven como base
     *
     * @return array<string, mixed>
     */
    private static function normalizarSolicitudContrato(?array $solicitud, array $base = []): array
    {
        $base = is_array($base) ? $base : [];

        $datos = [
            'id' => isset($base['id']) ? (int)$base['id'] : 0,
            'folio' => trim((string)($base['folio'] ?? '')),
            'estado' => trim((string)($base['estado'] ?? '')),
            'created_at' => trim((string)($base['created_at'] ?? '')),
            'albacea' => [
                'activo' => isset($base['albacea']['activo']) ? (bool)$base['albacea']['activo'] : false,
                'nombre' => isset($base['albacea']['nombre']) ? (string)$base['albacea']['nombre'] : '',
                'edad' => $base['albacea']['edad'] ?? '',
                'parentesco' => isset($base['albacea']['parentesco']) ? (string)$base['albacea']['parentesco'] : '',
                'celular' => isset($base['albacea']['celular']) ? (string)$base['albacea']['celular'] : '',
            ],
            'anualidad' => [
                'usa_pago_anual' => isset($base['anualidad']['usa_pago_anual']) ? (bool)$base['anualidad']['usa_pago_anual'] : false,
                'pago_anual' => isset($base['anualidad']['pago_anual']) ? (string)$base['anualidad']['pago_anual'] : '',
                'pago_anual_valor' => $base['anualidad']['pago_anual_valor'] ?? '',
                'fecha_pago_anual' => isset($base['anualidad']['fecha_pago_anual']) ? (string)$base['anualidad']['fecha_pago_anual'] : '',
                'fecha_pago_anual_texto' => isset($base['anualidad']['fecha_pago_anual_texto']) ? (string)$base['anualidad']['fecha_pago_anual_texto'] : '',
                'plazo_anual' => $base['anualidad']['plazo_anual'] ?? '',
            ],
        ];

        if (!is_array($solicitud)) {
            return $datos;
        }

        $upper = static function (string $valor): string {
            if ($valor === '') {
                return '';
            }

            return function_exists('mb_strtoupper')
                ? mb_strtoupper($valor, 'UTF-8')
                : strtoupper($valor);
        };

        $obtener = static function (string $campo) use ($solicitud): string {
            return trim(ControladorSolicitudes::valorParaFormulario($solicitud[$campo] ?? null));
        };

        $datos['id'] = isset($solicitud['id']) ? (int)$solicitud['id'] : $datos['id'];
        $datos['folio'] = $obtener('folio') ?: $datos['folio'];
        $datos['estado'] = trim((string)($solicitud['estado'] ?? $datos['estado']));
        $datos['created_at'] = trim((string)($solicitud['created_at'] ?? $datos['created_at']));

        $albaceaActivoRaw = $solicitud['albacea_activo'] ?? ($solicitud['albacea']['activo'] ?? false);
        $albaceaActivo = filter_var($albaceaActivoRaw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($albaceaActivo === null) {
            $albaceaActivo = in_array(
                strtolower((string)$albaceaActivoRaw),
                ['1', 'true', 'si', 'sí', 'on'],
                true
            );
        }

        $edadTexto = $obtener('albacea_edad');
        $edadValor = $edadTexto === ''
            ? ''
            : (is_numeric($edadTexto) ? (int)$edadTexto : $edadTexto);

        $celular = $obtener('albacea_celular');
        $celular = $celular !== '' ? preg_replace('/[^0-9+]/', '', $celular) : '';

        $datos['albacea'] = [
            'activo' => $albaceaActivo,
            'nombre' => $upper($obtener('albacea_nombre')),
            'edad' => $edadValor,
            'parentesco' => $upper($obtener('albacea_parentesco')),
            'celular' => $celular,
        ];

        $usaPagoAnualRaw = $solicitud['usa_pago_anual'] ?? ($solicitud['anualidad']['usa_pago_anual'] ?? false);
        $usaPagoAnual = filter_var($usaPagoAnualRaw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($usaPagoAnual === null) {
            $usaPagoAnual = in_array(
                strtolower((string)$usaPagoAnualRaw),
                ['1', 'true', 'si', 'sí', 'on'],
                true
            );
        }

        $pagoAnualTexto = $obtener('pago_anual');
        $pagoAnualNormalizado = self::normalizarMonto($pagoAnualTexto);
        $pagoAnualValor = '';
        if ($pagoAnualNormalizado !== '') {
            $pagoAnualValor = (float)$pagoAnualNormalizado;
        } elseif ($pagoAnualTexto !== '' && is_numeric($pagoAnualTexto)) {
            $pagoAnualValor = (float)$pagoAnualTexto;
        }

        $fechaPagoAnual = $obtener('fecha_pago_anual');
        $plazoAnualTexto = $obtener('plazo_anual');
        $plazoAnualValor = $plazoAnualTexto === ''
            ? ''
            : (is_numeric($plazoAnualTexto) ? (int)$plazoAnualTexto : $plazoAnualTexto);

        $datos['anualidad'] = [
            'usa_pago_anual' => $usaPagoAnual,
            'pago_anual' => $pagoAnualNormalizado !== '' ? $pagoAnualNormalizado : $pagoAnualTexto,
            'pago_anual_valor' => $pagoAnualValor,
            'fecha_pago_anual' => $fechaPagoAnual,
            'fecha_pago_anual_texto' => $fechaPagoAnual !== '' ? self::fechaEnFormatoLargo($fechaPagoAnual) : '',
            'plazo_anual' => $plazoAnualValor,
        ];

        return $datos;
    }

    /**
     * Obtiene y normaliza la información de la solicitud vinculada a un
     * contrato. Si no existe la solicitud, devuelve la estructura base con
     * valores vacíos.
     *
     * @param int $solicitudId Identificador de la solicitud asociada
     * @param array<string, mixed> $solicitudActual Datos almacenados previamente en el contrato
     *
     * @return array<string, mixed>
     */
    private static function obtenerSolicitudParaContrato(int $solicitudId, array $solicitudActual = []): array
    {
        $base = is_array($solicitudActual) ? $solicitudActual : [];
        $solicitud = null;

        if ($solicitudId > 0) {
            $solicitudObtenida = ControladorSolicitudes::ctrObtenerSolicitudPorId($solicitudId);
            if (is_array($solicitudObtenida)) {
                $solicitud = $solicitudObtenida;
            }
        }

        return self::normalizarSolicitudContrato($solicitud, $base);
    }

    /**
     * Construye los arreglos de prellenado a partir de una solicitud existente.
     *
     * @param array<string, mixed> $solicitud
     * @param array<string, mixed> $contexto
     *
     * @return array{
     *     solicitud: array<string, mixed>,
     *     cliente: array<string, mixed>,
     *     contrato: array<string, mixed>,
     *     desarrollo: array<string, mixed>,
     *     resumen: array<string, mixed>
     * }
     */
    private static function construirPrefillSolicitud(array $solicitud, array $contexto = []): array
    {
        $nacionalidades = $contexto['nacionalidades'] ?? [];
        $tiposContrato = self::normalizarTiposContratoContexto($contexto['tiposContrato'] ?? []);
        $desarrollos = $contexto['desarrollos'] ?? [];

        $upper = static function ($valor): string {
            $texto = trim(ControladorSolicitudes::valorParaFormulario($valor));
            if ($texto === '') {
                return '';
            }

            return function_exists('mb_strtoupper')
                ? mb_strtoupper($texto, 'UTF-8')
                : strtoupper($texto);
        };

        $obtenerCampoSolicitud = static function (string $campo) use ($solicitud): string {
            return trim(ControladorSolicitudes::valorParaFormulario($solicitud[$campo] ?? null));
        };

        $solicitudNormalizada = self::normalizarSolicitudContrato($solicitud);

        $telefonos = [
            $solicitud['celular'] ?? null,
            $solicitud['telefono'] ?? null,
        ];
        $telefonoPrincipal = '';
        foreach ($telefonos as $telefono) {
            if ($telefono === null) {
                continue;
            }

            $limpio = preg_replace('/[^0-9+]/', '', (string)$telefono);
            if ($limpio !== '') {
                $telefonoPrincipal = $limpio;
                break;
            }
        }

        $estadoCivilBase = $obtenerCampoSolicitud('estado_civil');
        $regimen = $obtenerCampoSolicitud('regimen');
        if ($estadoCivilBase !== '' && $regimen !== '') {
            $estadoCivilBase .= ' / ' . $regimen;
        } elseif ($estadoCivilBase === '' && $regimen !== '') {
            $estadoCivilBase = $regimen;
        }

        $beneficiarioNombre = $obtenerCampoSolicitud('beneficiario');
        $beneficiarioParentesco = $obtenerCampoSolicitud('parentesco_beneficiario');
        if ($beneficiarioNombre !== '' && $beneficiarioParentesco !== '') {
            $beneficiarioNombre .= ' (' . $beneficiarioParentesco . ')';
        }

        $idmexSolicitud = $upper($solicitud['idmex'] ?? '');
        $identificacionSolicitud = $upper($solicitud['identificacion_numero'] ?? '');
        $identificacionesCapturadas = array_values(array_filter([
            $idmexSolicitud,
            $identificacionSolicitud,
        ], static function (string $valor): bool {
            return $valor !== '';
        }));
        $identificacionCompuesta = $identificacionesCapturadas
            ? implode(' / ', array_unique($identificacionesCapturadas))
            : '';

        $diceSerSugerido = $upper($solicitud['parentesco_beneficiario'] ?? '');
        if ($diceSerSugerido === '') {
            $diceSerSugerido = $upper($solicitud['ocupacion'] ?? '');
        }

        $clientePrefill = [
            'cliente_nombre' => $upper($solicitud['nombre_completo'] ?? ''),
            'cliente_fecha_nacimiento' => $obtenerCampoSolicitud('fecha_nacimiento'),
            'cliente_rfc' => $upper($solicitud['rfc'] ?? ''),
            'cliente_curp' => $upper($solicitud['curp'] ?? ''),
            'cliente_identificacion' => $upper($solicitud['identificacion'] ?? ''),
            'cliente_ine' => $identificacionCompuesta,
            'cliente_estado_civil' => $upper($estadoCivilBase),
            'cliente_ocupacion' => $upper($solicitud['ocupacion'] ?? ''),
            'dice_ser' => $diceSerSugerido,
            'cliente_domicilio' => $upper($solicitud['domicilio'] ?? ''),
            'cliente_email' => $obtenerCampoSolicitud('email'),
            'cliente_beneficiario' => $upper($beneficiarioNombre),
            'cliente_telefono' => $telefonoPrincipal,
            'telefono_cliente_visible' => $telefonoPrincipal,
            'parentesco_beneficiario' => $upper($solicitud['parentesco_beneficiario'] ?? ''),
        ];

        $nacionalidadSolicitudNombre = $upper($solicitud['nacionalidad'] ?? '');
        if ($nacionalidadSolicitudNombre === '' && !empty($solicitud['nacionalidad_id'])) {
            $idBuscar = (int)$solicitud['nacionalidad_id'];
            foreach ($nacionalidades as $nacionalidad) {
                if ((int)($nacionalidad['id'] ?? 0) === $idBuscar) {
                    $nombre = $nacionalidad['nombre'] ?? '';
                    $clientePrefill['cliente_nacionalidad'] = $upper($nombre);
                    break;
                }
            }
        } elseif ($nacionalidadSolicitudNombre !== '') {
            $clientePrefill['cliente_nacionalidad'] = $nacionalidadSolicitudNombre;
        }

        $fraccion = $upper($solicitud['lote_manzana'] ?? '');
        $pagoAnualValor = $obtenerCampoSolicitud('pago_anual');
        $plazoAnualValor = $obtenerCampoSolicitud('plazo_anual');

        $contratoPrefill = [
            'folio' => $obtenerCampoSolicitud('folio'),
            'fecha_contrato' => $obtenerCampoSolicitud('fecha'),
            'fecha_firma' => $obtenerCampoSolicitud('fecha_firma'),
            'inicio_pagos' => $obtenerCampoSolicitud('fecha_pago_mensual'),
            'vigencia_pagare' => $obtenerCampoSolicitud('fecha_pago_anual'),
            'contrato_superficie' => $obtenerCampoSolicitud('superficie'),
            'fracciones' => $fraccion,
            'mensualidades' => $obtenerCampoSolicitud('plazo_mensualidades'),
            'monto_inmueble' => $obtenerCampoSolicitud('costo_total'),
            'enganche' => $obtenerCampoSolicitud('enganche'),
            'saldo_pago' => $obtenerCampoSolicitud('saldo'),
            'pago_mensual' => $obtenerCampoSolicitud('pago_mensual'),
            'parcialidades_anuales' => $pagoAnualValor !== '' ? 'PAGO ANUAL: ' . $pagoAnualValor : '',
            'rango_pago' => $plazoAnualValor !== '' ? $plazoAnualValor . ' AÑOS' : '',
            'habitacional' => $upper($solicitud['deslinde'] ?? ''),
            'observaciones' => '',
        ];

        $contratoPrefill['albacea_activo'] = $solicitudNormalizada['albacea']['activo'];
        $contratoPrefill['albacea_nombre'] = $solicitudNormalizada['albacea']['nombre'];
        $contratoPrefill['albacea_edad'] = $solicitudNormalizada['albacea']['edad'];
        $contratoPrefill['albacea_parentesco'] = $solicitudNormalizada['albacea']['parentesco'];
        $contratoPrefill['albacea_celular'] = $solicitudNormalizada['albacea']['celular'];
        $contratoPrefill['albacea'] = $solicitudNormalizada['albacea'];

        $contratoPrefill['usa_pago_anual'] = $solicitudNormalizada['anualidad']['usa_pago_anual'];
        $contratoPrefill['pago_anual'] = $solicitudNormalizada['anualidad']['pago_anual'];
        $contratoPrefill['pago_anual_valor'] = $solicitudNormalizada['anualidad']['pago_anual_valor'];
        $contratoPrefill['fecha_pago_anual'] = $solicitudNormalizada['anualidad']['fecha_pago_anual'];
        $contratoPrefill['plazo_anual'] = $solicitudNormalizada['anualidad']['plazo_anual'];
        $contratoPrefill['anualidad'] = $solicitudNormalizada['anualidad'];

        $totalInmueble = (float)($contratoPrefill['monto_inmueble'] !== '' ? $contratoPrefill['monto_inmueble'] : 0);
        $engancheMonto = (float)($contratoPrefill['enganche'] !== '' ? $contratoPrefill['enganche'] : 0);
        if (($contratoPrefill['saldo_pago'] === '' || $contratoPrefill['saldo_pago'] === null) && ($totalInmueble || $engancheMonto)) {
            $contratoPrefill['saldo_pago'] = $totalInmueble - $engancheMonto;
        }

        $desarrolloPrefill = [
            'desarrollo_id' => 0,
            'contrato_superficie' => $contratoPrefill['contrato_superficie'],
            'tipo_contrato_id' => '',
            'tipo_contrato_nombre' => '',
            'superficie_fixed' => '',
        ];

        $desarrolloSolicitudId = (int)($solicitud['desarrollo_id'] ?? 0);
        $nombreDesarrolloSolicitud = trim((string)($solicitud['desarrollo'] ?? ''));
        if ($desarrolloSolicitudId > 0) {
            foreach ($desarrollos as $desarrollo) {
                if ((int)($desarrollo['id'] ?? 0) === $desarrolloSolicitudId) {
                    $tipoId = $desarrollo['tipo_contrato'] ?? '';
                    $desarrolloPrefill = [
                        'desarrollo_id' => (int)$desarrollo['id'],
                        'contrato_superficie' => $contratoPrefill['contrato_superficie'],
                        'tipo_contrato_id' => is_string($tipoId) ? $tipoId : '',
                        'tipo_contrato_nombre' => $tiposContrato[(string)$tipoId] ?? (is_string($tipoId) ? $tipoId : ''),
                        'superficie_fixed' => '',
                    ];
                    break;
                }
            }
        } elseif ($nombreDesarrolloSolicitud !== '') {
            foreach ($desarrollos as $desarrollo) {
                $nombreListado = trim((string)($desarrollo['nombre'] ?? ''));
                if ($nombreListado !== '' && strcasecmp($nombreListado, $nombreDesarrolloSolicitud) === 0) {
                    $tipoId = $desarrollo['tipo_contrato'] ?? '';
                    $desarrolloPrefill = [
                        'desarrollo_id' => (int)$desarrollo['id'],
                        'contrato_superficie' => $contratoPrefill['contrato_superficie'],
                        'tipo_contrato_id' => is_string($tipoId) ? $tipoId : '',
                        'tipo_contrato_nombre' => $tiposContrato[(string)$tipoId] ?? (is_string($tipoId) ? $tipoId : ''),
                        'superficie_fixed' => '',
                    ];
                    break;
                }
            }
        }

        $solicitudId = (int)($solicitud['id'] ?? 0);
        $resumen = [
            'id' => $solicitudId,
            'folio' => trim((string)($solicitud['folio'] ?? '')),
            'nombre' => trim((string)($solicitud['nombre_completo'] ?? $solicitud['nombre'] ?? '')),
            'nombre_completo' => trim((string)($solicitud['nombre_completo'] ?? $solicitud['nombre'] ?? '')),
            'estado' => trim((string)($solicitud['estado'] ?? '')),
            'created_at' => trim((string)($solicitud['created_at'] ?? '')),
        ];

        return [
            'solicitud' => self::limpiarSolicitudParaRespuesta($solicitud),
            'cliente' => $clientePrefill,
            'contrato' => $contratoPrefill,
            'desarrollo' => $desarrolloPrefill,
            'resumen' => $resumen,
        ];
    }

    private static function usuarioEsAdministrativo(): bool
    {
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return false;
        }

        $permiso = strtolower(trim((string)($_SESSION['permission'] ?? '')));

        return in_array($permiso, ['owner', 'admin'], true);
    }

    /**
     * Obtiene los datos necesarios para prellenar el formulario de contrato a
     * partir de la información capturada en una solicitud existente.
     *
     * @param int $solicitudId
     * @param array<string, mixed> $contexto
     *
     * @return array<string, array<string, mixed>>|null
     */
    public static function obtenerPrefillSolicitud(int $solicitudId, array $contexto = []): ?array
    {
        if ($solicitudId <= 0) {
            return null;
        }

        $solicitud = $contexto['solicitud'] ?? ControladorSolicitudes::ctrObtenerSolicitudPorId($solicitudId);
        if (!$solicitud || !is_array($solicitud)) {
            return null;
        }

        $nacionalidades = $contexto['nacionalidades'] ?? [];
        $tiposContrato = $contexto['tiposContrato'] ?? [];
        $desarrollos = $contexto['desarrollos'] ?? [];

        if (empty($nacionalidades) && class_exists(ControladorParametros::class)) {
            $nacionalidades = ControladorParametros::ctrMostrarVariables('nacionalidad');
        }

        if (empty($tiposContrato) && class_exists(ControladorParametros::class)) {
            $tiposContrato = ControladorParametros::ctrMostrarVariables('tipo_contrato');
        }

        if (empty($desarrollos)) {
            $desarrollos = ControladorDesarrollos::ctrMostrarDesarrollos();
        }

        return self::construirPrefillSolicitud($solicitud, [
            'nacionalidades' => $nacionalidades,
            'tiposContrato' => $tiposContrato,
            'desarrollos' => $desarrollos,
        ]);
    }

    public static function ctrCancelarContrato(): ?array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cancelarContrato'])) {
            return null;
        }

        if (!self::usuarioPuedeGestionarContratos()) {
            return ['tipo' => 'error', 'mensaje' => 'No tiene permisos para cancelar contratos.'];
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            return ['tipo' => 'error', 'mensaje' => 'Token de seguridad inválido.'];
        }

        $contratoId = (int)($_POST['contrato_id'] ?? 0);
        if ($contratoId <= 0) {
            return ['tipo' => 'error', 'mensaje' => 'Identificador de contrato no válido.'];
        }

        $contratoRow = ModeloContratos::mdlMostrarContratoPorId($contratoId);
        if (!$contratoRow) {
            return ['tipo' => 'error', 'mensaje' => 'El contrato indicado no existe.'];
        }

        $data = json_decode($contratoRow['datta_contrato'] ?? '', true);
        if (!is_array($data)) {
            return ['tipo' => 'error', 'mensaje' => 'Los datos del contrato están dañados.'];
        }

        $passwordConfirmacion = (string)($_POST['password_confirmacion'] ?? '');
        if (trim($passwordConfirmacion) === '') {
            return ['tipo' => 'error', 'mensaje' => 'Ingrese su contraseña para confirmar la cancelación del contrato.'];
        }
        if (!self::validarPasswordActual($passwordConfirmacion)) {
            return ['tipo' => 'error', 'mensaje' => 'La contraseña proporcionada no es válida.'];
        }

        $motivo = trim((string)($_POST['motivo_cancelacion'] ?? ''));
        if ($motivo === '') {
            return ['tipo' => 'error', 'mensaje' => 'Debe indicar el motivo de la cancelación del contrato.'];
        }

        if (function_exists('mb_strlen')) {
            if (mb_strlen($motivo, 'UTF-8') < 5) {
                return ['tipo' => 'error', 'mensaje' => 'Describa con mayor detalle el motivo de la cancelación.'];
            }
            $motivo = mb_substr($motivo, 0, 500, 'UTF-8');
        } else {
            if (strlen($motivo) < 5) {
                return ['tipo' => 'error', 'mensaje' => 'Describa con mayor detalle el motivo de la cancelación.'];
            }
            $motivo = substr($motivo, 0, 500);
        }

        $solicitudId = (int)($data['contrato']['solicitud_origen_id'] ?? 0);
        if ($solicitudId > 0) {
            if (!self::usuarioEsAdministrativo()) {
                return ['tipo' => 'error', 'mensaje' => 'Solo OWNER o ADMIN pueden cancelar contratos vinculados a una solicitud.'];
            }

            $solicitudVinculada = ModeloSolicitudes::mdlObtenerSolicitudPorId($solicitudId);
            if (!$solicitudVinculada) {
                return ['tipo' => 'error', 'mensaje' => 'No se pudo verificar la solicitud vinculada. Cancele la solicitud desde su módulo antes de continuar.'];
            }

            $estadoSolicitud = strtolower((string)($solicitudVinculada['estado'] ?? ''));
            if ($estadoSolicitud !== 'cancelada') {
                return ['tipo' => 'error', 'mensaje' => 'Debe cancelar primero la solicitud vinculada (#' . $solicitudId . ') antes de cancelar el contrato.'];
            }
        }

        $data['contrato']['estado'] = 'cancelado';
        $data['contrato']['estatus'] = 2;
        $data['contrato']['cancelado_en'] = self::fechaActualConHora();
        $data['contrato']['motivo_cancelacion'] = $motivo;

        $usuarioCancela = self::usuarioActualId();
        $data['contrato']['cancelado_por'] = $usuarioCancela > 0 ? $usuarioCancela : null;

        $jsonNuevo = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($jsonNuevo === false) {
            return ['tipo' => 'error', 'mensaje' => 'No fue posible preparar la información del contrato.'];
        }

        $folioActual = $data['contrato']['folio'] ?? ($contratoRow['folio'] ?? null);
        $actualizacion = ModeloContratos::mdlEditarContrato($contratoId, $jsonNuevo, $folioActual);
        if ($actualizacion !== 'ok') {
            return ['tipo' => 'error', 'mensaje' => 'No fue posible actualizar los datos del contrato.'];
        }

        try {
            $estatusActualizado = ModeloContratos::mdlActualizarEstatusIndividual($contratoId, 2);
        } catch (\Throwable $e) {
            return ['tipo' => 'error', 'mensaje' => 'No fue posible actualizar el estatus del contrato.'];
        }

        if (!$estatusActualizado) {
            return ['tipo' => 'error', 'mensaje' => 'No fue posible actualizar el estatus del contrato.'];
        }

        return ['tipo' => 'success', 'mensaje' => 'Contrato cancelado correctamente.'];
    }

    private static function normalizarFecha(?string $fecha): ?string
    {
        $fechaObj = self::parsearFecha($fecha);
        return $fechaObj ? self::formatearFechaAlmacen($fechaObj) : null;
    }

    private static function fechaEnFormatoLargo(?string $fecha): string
    {
        $fechaObj = self::parsearFecha($fecha);
        if (!$fechaObj) {
            return '';
        }

        $meses = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
        $indiceMes = max(0, min(11, (int)$fechaObj->format('n') - 1));

        return sprintf('%s DE %s DE %s', $fechaObj->format('j'), $meses[$indiceMes] ?? '', $fechaObj->format('Y'));
    }

    private static function fechaEnFormatoCorto(?string $fecha): string
    {
        $fechaObj = self::parsearFecha($fecha);
        if (!$fechaObj) {
            return '';
        }

        return $fechaObj->format('d-m-Y');
    }

    private static function normalizarMonto(?string $valor): string
    {
        if ($valor === null) {
            return '';
        }

        $limpio = str_replace(',', '', trim((string)$valor));
        $limpio = preg_replace('/[^0-9.]/', '', $limpio);
        if ($limpio === '' || !is_numeric($limpio)) {
            return '';
        }

        return number_format((float)$limpio, 2, '.', '');
    }

    private static function normalizarDecimal($valor, int $precision = 2): string
    {
        if ($valor === null) {
            return '';
        }

        $texto = is_string($valor) ? $valor : (string)$valor;
        $limpio = str_replace(',', '', trim($texto));
        $limpio = preg_replace('/[^0-9.\-]/', '', $limpio);
        if ($limpio === '' || $limpio === '-' || $limpio === '.') {
            return '';
        }

        $numero = (float)$limpio;
        $formateado = number_format($numero, $precision, '.', '');
        if ($precision > 0) {
            $formateado = rtrim(rtrim($formateado, '0'), '.');
        }

        return $formateado === '' ? '0' : $formateado;
    }

    private static function superficieEnTexto(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $numero = (float)str_replace(',', '', $valor);
        if ($numero <= 0) {
            return '';
        }

        $numeroBase = rtrim(rtrim(number_format($numero, 2, '.', ','), '0'), '.');
        if ($numeroBase === '') {
            $numeroBase = '0';
        }

        $unidad = abs($numero - 1.0) < 0.005 ? 'METRO CUADRADO' : 'METROS CUADRADOS';

        if (class_exists(NumberFormatter::class)) {
            try {
                $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
                $entero = (int)floor($numero);
                $decimales = (int)round(($numero - $entero) * 100);
                $textoEntero = mb_strtoupper($formatter->format($entero));
                if ($textoEntero === '') {
                    $textoEntero = 'CERO';
                }

                $texto = $textoEntero;
                if ($decimales > 0) {
                    $textoDecimales = mb_strtoupper($formatter->format($decimales));
                    $texto .= ' PUNTO ' . $textoDecimales;
                }

                return $numeroBase . ' M2 ' . $texto . ' ' . $unidad;
            } catch (\Throwable $e) {
                // Ignorar y usar el formato numérico simple.
            }
        }

        return $numeroBase . ' M2 ' . $unidad;
    }

    private static function normalizarGenero(mixed $valor): string
    {
        $texto = trim((string)$valor);
        if ($texto === '') {
            return '';
        }

        if (function_exists('mb_strtoupper')) {
            $texto = mb_strtoupper($texto, 'UTF-8');
        } else {
            $texto = strtoupper($texto);
        }

        $canonico = preg_replace('/[^A-Z]/u', '', $texto);

        switch ($canonico) {
            case 'ALC':
            case 'ELCLIENTE':
            case 'MASCULINO':
            case 'HOMBRE':
                return 'AL C.';
            case 'ALAC':
            case 'LAC':
            case 'CLIENTA':
            case 'FEMENINO':
            case 'MUJER':
                return 'A LA C.';
            default:
                return '';
        }
    }

    private static function calcularEdad(?string $fecha): ?int
    {
        if (!$fecha) {
            return null;
        }

        try {
            $nacimiento = new \DateTimeImmutable($fecha);
            $hoy = new \DateTimeImmutable('today');

            return (int)$nacimiento->diff($hoy)->y;
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function obtenerFechaDual(array $fuente, string $campoBase): array
    {
        $valorDate = self::normalizarFecha($fuente[$campoBase] ?? null);
        $valorTexto = trim((string)($fuente[$campoBase . '_texto'] ?? ''));

        if ($valorTexto === '' && $valorDate) {
            $valorTexto = self::fechaEnFormatoLargo($valorDate);
        }

        return [
            'date' => $valorDate,
            'texto' => $valorTexto,
        ];
    }

    private static function normalizarFracciones($entrada): array
    {
        if ($entrada === null) {
            return [];
        }

        if (is_array($entrada)) {
            $candidatos = $entrada;
        } else {
            $texto = trim((string)$entrada);
            if ($texto === '') {
                return [];
            }

            $decoded = json_decode($texto, true);
            if (is_array($decoded)) {
                $candidatos = $decoded;
            } else {
                $candidatos = explode(',', $texto);
            }
        }

        $resultado = [];
        foreach ($candidatos as $valor) {
            $valorLimpio = trim((string)$valor);
            if ($valorLimpio !== '') {
                $resultado[] = $valorLimpio;
            }
        }

        return $resultado;
    }

    /**
     * Verifica si un cliente ya tiene un contrato existente.
     *
     * @param int $clienteId
     * @return bool
     */
    static public function ctrExisteContrato($clienteId)
    {
        return ModeloContratos::mdlExisteContratoPorCliente($clienteId);
    }

    /**
     * Crea un nuevo contrato para un cliente existente y responde en JSON.
     * Mantiene la compatibilidad con los formularios anteriores pero normaliza
     * la estructura de respuesta a { status, message, ... }.
     */
    
    static public function ctrCrearContrato()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['crearContrato'])) {
            return;
        }

        $respond = function (string $status, array $payload = [], int $httpCode = 200): void {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                if ($httpCode !== 200) {
                    http_response_code($httpCode);
                }
            }

            echo json_encode(array_merge(['status' => $status], $payload), JSON_UNESCAPED_UNICODE);
            exit;
        };

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            $respond('error', ['message' => 'Sesión no válida', 'code' => 'AUTH-001'], 401);
        }

        if (!self::usuarioPuedeGestionarContratos()) {
            $respond('error', ['message' => 'No tiene permisos para gestionar contratos', 'code' => 'AUTH-003'], 403);
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            $respond('error', ['message' => 'Token CSRF inválido', 'code' => 'AUTH-002'], 403);
        }

        try {
            $clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
            if ($clienteId <= 0) {
                throw new \InvalidArgumentException('Seleccione un cliente válido.');
            }

            $desarrolloId = isset($_POST['desarrollo_id']) ? (int)$_POST['desarrollo_id'] : 0;
            if ($desarrolloId <= 0) {
                throw new \InvalidArgumentException('Seleccione un desarrollo válido.');
            }

            $clienteData = ModeloClientes::mdlMostrarClientePorId($clienteId);
            if (!$clienteData) {
                throw new \InvalidArgumentException('El cliente seleccionado no existe.');
            }

            $desarrolloData = ModeloDesarrollos::mdlMostrarDesarrolloPorId($desarrolloId);
            if (!$desarrolloData) {
                throw new \InvalidArgumentException('Seleccione un desarrollo válido.');
            }

            $folio = strtoupper(trim($_POST['folio'] ?? ''));
            $folio = preg_replace('/[^A-Z0-9_-]/u', '', $folio);
            if ($folio === '') {
                throw new \InvalidArgumentException('Capture un folio válido para el contrato.');
            }
            if (ModeloContratos::mdlExisteFolio($folio)) {
                throw new \InvalidArgumentException('El folio indicado ya está registrado.');
            }
            $mensualidades = (int)($_POST['mensualidades'] ?? 0);
            $superficieEntrada = trim($_POST['contrato_superficie'] ?? '');
            $superficieNormalizada = self::normalizarDecimal($superficieEntrada, 2);
            if ($superficieNormalizada === '') {
                throw new \InvalidArgumentException('Capture la superficie del contrato.');
            }

            $superficieTexto = trim((string)($_POST['superficie_fixed'] ?? ''));
            if ($superficieTexto === '') {
                $superficieTexto = self::superficieEnTexto($superficieNormalizada);
            }

            $diceSerCapturado = strtoupper(trim($_POST['dice_ser'] ?? ''));
            $habitacional = strtoupper(trim($_POST['habitacional'] ?? ''));
            $tipoContrato = trim($_POST['tipo_contrato'] ?? '');
            $rangoPagoLibre = trim($_POST['rango_pago'] ?? '');
            $clausulas = trim($_POST['financiamiento_clusulas'] ?? '');
            $parcialidades = trim($_POST['parcialidades_anuales'] ?? '');
            $clausulaPosecion = strtoupper(trim($_POST['clausula_c_posecion'] ?? ''));

            $montoInmueble = (float)($_POST['monto_inmueble'] ?? 0);
            $enganche = (float)($_POST['enganche'] ?? 0);
            $saldoPago = (float)($_POST['saldo_pago'] ?? 0);
            $penalizacion = (float)($_POST['penalizacion'] ?? 0);
            $pagoMensual = (float)($_POST['pago_mensual'] ?? 0);

            $fracciones = self::normalizarFracciones($_POST['fracciones'] ?? '');

            $entregaDual = self::obtenerFechaDual($_POST, 'entrega_posecion');
            $firmaDual = self::obtenerFechaDual($_POST, 'fecha_firma');
            $inicioPagosDual = self::obtenerFechaDual($_POST, 'inicio_pagos');
            $vigenciaPagareDual = self::obtenerFechaDual($_POST, 'vigencia_pagare');
            $rangoInicioDual = self::obtenerFechaDual($_POST, 'rango_pago_inicio');
            $rangoFinDual = self::obtenerFechaDual($_POST, 'rango_pago_fin');
            $fechaContratoDual = self::obtenerFechaDual($_POST, 'fecha_contrato');

            $solicitudOrigenId = isset($_POST['solicitud_id_origen']) ? (int)$_POST['solicitud_id_origen'] : 0;
            $nombreCortoUsuario = self::obtenerNombreCortoContrato($solicitudOrigenId);
            $identificacionSolicitudTipo = self::obtenerIdentificacionSolicitud($solicitudOrigenId);
            $solicitudSegmento = self::obtenerSolicitudParaContrato($solicitudOrigenId);

            $contratoDetalle = [
                'folio' => $folio,
                'nombre_corto' => $nombreCortoUsuario,
                'mensualidades' => $mensualidades,
                'superficie' => $superficieNormalizada,
                'superficie_fixed' => $superficieTexto,
                'fraccion_vendida' => implode(',', $fracciones),
                'entrega_posecion' => $entregaDual['texto'],
                'entrega_posecion_date' => $entregaDual['date'],
                'clausula_c_posecion' => $clausulaPosecion,
                'fecha_firma_contrato' => $firmaDual['texto'],
                'fecha_firma_contrato_date' => $firmaDual['date'],
                'habitacional_colindancias' => $habitacional,
                'inicio_pagos' => $inicioPagosDual['texto'],
                'inicio_pagos_date' => $inicioPagosDual['date'],
                'tipo_contrato' => $tipoContrato,
                'monto_precio_inmueble' => number_format($montoInmueble, 2, '.', ','),
                'monto_precio_inmueble_valor' => $montoInmueble,
                'monto_precio_inmueble_fixed' => trim($_POST['monto_inmueble_fixed'] ?? ''),
                'enganche' => number_format($enganche, 2, '.', ','),
                'enganche_valor' => $enganche,
                'enganche_fixed' => trim($_POST['enganche_fixed'] ?? ''),
                'saldo_pago' => number_format($saldoPago, 2, '.', ','),
                'saldo_pago_valor' => $saldoPago,
                'saldo_pago_fixed' => trim($_POST['saldo_pago_fixed'] ?? ''),
                'parcialidades_anuales' => $parcialidades,
                'financiamiento_clusulas' => $clausulas,
                'penalizacion_10' => number_format($penalizacion, 2, '.', ','),
                'penalizacion_10_valor' => $penalizacion,
                'penalizacion_10_fixed' => trim($_POST['penalizacion_fixed'] ?? ''),
                'pago_mensual' => number_format($pagoMensual, 2, '.', ','),
                'pago_mensual_valor' => $pagoMensual,
                'pago_mensual_fixed' => trim($_POST['pago_mensual_fixed'] ?? ''),
                'fecha_contrato' => $fechaContratoDual['texto'],
                'fecha_contrato_date' => $fechaContratoDual['date'],
                'fecha_contrato_fixed' => trim($_POST['fecha_contrato_fixed'] ?? '') ?: $fechaContratoDual['texto'],
                'rango_pago_inicio' => $rangoInicioDual['texto'],
                'rango_pago_inicio_date' => $rangoInicioDual['date'],
                'rango_pago_fin' => $rangoFinDual['texto'],
                'rango_pago_fin_date' => $rangoFinDual['date'],
                'rango_pago' => $rangoPagoLibre,
                'vigencia_pagare' => $vigenciaPagareDual['texto'],
                'vigencia_pagare_date' => $vigenciaPagareDual['date'],
                'dice_ser' => $diceSerCapturado,
                'observaciones' => trim((string)($_POST['observaciones'] ?? '')),
                'estatus' => 1,
                'estado' => 'activo',
                'solicitud_origen_id' => $solicitudOrigenId > 0 ? $solicitudOrigenId : 0,
            ];

            $contratoDetalle['albacea_activo'] = $solicitudSegmento['albacea']['activo'];
            $contratoDetalle['albacea_nombre'] = $solicitudSegmento['albacea']['nombre'];
            $contratoDetalle['albacea_edad'] = $solicitudSegmento['albacea']['edad'];
            $contratoDetalle['albacea_parentesco'] = $solicitudSegmento['albacea']['parentesco'];
            $contratoDetalle['albacea_celular'] = $solicitudSegmento['albacea']['celular'];
            $contratoDetalle['albacea'] = $solicitudSegmento['albacea'];

            $contratoDetalle['usa_pago_anual'] = $solicitudSegmento['anualidad']['usa_pago_anual'];
            $contratoDetalle['pago_anual'] = $solicitudSegmento['anualidad']['pago_anual'];
            $contratoDetalle['pago_anual_valor'] = $solicitudSegmento['anualidad']['pago_anual_valor'];
            $contratoDetalle['fecha_pago_anual'] = $solicitudSegmento['anualidad']['fecha_pago_anual'];
            $contratoDetalle['plazo_anual'] = $solicitudSegmento['anualidad']['plazo_anual'];
            $contratoDetalle['anualidad'] = $solicitudSegmento['anualidad'];

            if ($fechaContratoDual['date']) {
                $contratoDetalle['dia_inicio'] = (int)substr($fechaContratoDual['date'], 8, 2);
            }

            if ($identificacionSolicitudTipo !== '') {
                $contratoDetalle['solicitud_identificacion'] = $identificacionSolicitudTipo;
            }

            $clienteJson = $clienteData;
            if (!isset($clienteJson['fecha']) && isset($clienteJson['fecha_nacimiento'])) {
                $clienteJson['fecha'] = $clienteJson['fecha_nacimiento'];
            }
            if (!isset($clienteJson['fecha_texto'])) {
                $clienteJson['fecha_texto'] = self::fechaEnFormatoLargo($clienteJson['fecha'] ?? '');
            }

            if ($diceSerCapturado !== '') {
                $clienteJson['dice_ser'] = $diceSerCapturado;
            }

            $jsonData = json_encode([
                'cliente' => $clienteJson,
                'desarrollo' => $desarrolloData,
                'contrato' => $contratoDetalle,
                'solicitud' => $solicitudSegmento,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $contratoId = ModeloContratos::mdlCrearContrato([
                'cliente_id' => $clienteId,
                'desarrollo_id' => $desarrolloId,
                'datta_contrato' => $jsonData,
                'creado_por' => (int)($_SESSION['id'] ?? 0),
                'folio' => $folio,
            ]);

            if (!$contratoId) {
                throw new \RuntimeException('No se pudo registrar el contrato.');
            }

            $respond('ok', [
                'message' => 'Contrato creado correctamente',
                'contrato_id' => $contratoId,
                'cliente_id' => $clienteId,
            ]);
        } catch (\InvalidArgumentException $validationException) {
            error_log('Validación al crear contrato: ' . $validationException->getMessage());
            $respond('error', [
                'message' => $validationException->getMessage(),
                'code' => 'VAL-001',
            ], 422);
        } catch (\JsonException $jsonException) {
            error_log('Error de JSON al crear contrato existente: ' . $jsonException->getMessage());
            $respond('error', [
                'message' => 'Ocurrió un error al procesar la información del contrato.',
                'code' => 'JSON-001',
                'detail' => $jsonException->getMessage(),
            ], 500);
        } catch (\Throwable $throwable) {
            error_log('Error al crear contrato existente: ' . $throwable->getMessage());
            $respond('error', [
                'message' => $throwable instanceof \PDOException ? 'Error de base de datos al crear el contrato.' : 'Ocurrió un error al crear el contrato.',
                'code' => (string)($throwable->getCode() ?: 'CTR-500'),
                'detail' => $throwable instanceof \PDOException ? ($throwable->errorInfo[2] ?? $throwable->getMessage()) : $throwable->getMessage(),
            ], 500);
        }
    }

    static public function ctrCrearContratoCompleto()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $esCreacion = isset($_POST['crearContratoCompleto']);
        $esEdicion = isset($_POST['editarContratoCompleto']);
        if (!$esCreacion && !$esEdicion) {
            return;
        }

        $respond = function (string $status, array $payload = [], int $httpCode = 200): void {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                if ($httpCode !== 200) {
                    http_response_code($httpCode);
                }
            }

            echo json_encode(array_merge(['status' => $status], $payload), JSON_UNESCAPED_UNICODE);
            exit;
        };

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            $respond('error', ['message' => 'Sesión no válida', 'code' => 'AUTH-001'], 401);
        }

        if (!self::usuarioPuedeGestionarContratos()) {
            $respond('error', ['message' => 'No tiene permisos para gestionar contratos', 'code' => 'AUTH-003'], 403);
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            $respond('error', ['message' => 'Token CSRF inválido', 'code' => 'AUTH-002'], 403);
        }

        $pdo = Conexion::conectar();

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            $contratoId = $esEdicion ? (int)($_POST['contrato_id'] ?? 0) : 0;
            $contratoActual = null;
            $jsonActual = [
                'cliente' => [],
                'desarrollo' => [],
                'contrato' => [],
                'solicitud' => [],
            ];

            if ($esEdicion) {
                if ($contratoId <= 0) {
                    throw new \InvalidArgumentException('Identificador de contrato inválido.');
                }

                $contratoActual = ModeloContratos::mdlMostrarContratoPorId($contratoId);
                if (!$contratoActual) {
                    throw new \InvalidArgumentException('El contrato seleccionado no existe.');
                }

                $jsonGuardado = json_decode($contratoActual['datta_contrato'] ?? '[]', true);
                if (is_array($jsonGuardado)) {
                    $jsonActual = array_merge([
                        'cliente' => [],
                        'desarrollo' => [],
                        'contrato' => [],
                        'solicitud' => [],
                    ], $jsonGuardado);
                }
            }

            $clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
            if ($esEdicion && $clienteId <= 0) {
                $clienteId = (int)($contratoActual['cliente_id'] ?? 0);
            }

            $esNuevoCliente = $clienteId <= 0;
            $clienteJson = $jsonActual['cliente'] ?? [];

            if ($esNuevoCliente) {
            $clienteNombre = strtoupper(trim($_POST['cliente_nombre'] ?? ''));
            $clienteNacionalidad = trim($_POST['cliente_nacionalidad'] ?? '');
            $clienteGenero = self::normalizarGenero($_POST['cliente_genero'] ?? null);
            $clienteRfc = strtoupper(trim($_POST['cliente_rfc'] ?? ''));
            $clienteCurp = strtoupper(trim($_POST['cliente_curp'] ?? ''));
            $clienteIdentificacion = strtoupper(trim($_POST['cliente_identificacion'] ?? ''));
            $clienteIne = strtoupper(trim($_POST['cliente_ine'] ?? ''));
            $clienteEstadoCivil = strtoupper(trim($_POST['cliente_estado_civil'] ?? ''));
            $clienteOcupacion = strtoupper(trim($_POST['cliente_ocupacion'] ?? ''));
            $clienteTelefono = trim($_POST['cliente_telefono'] ?? '');
            $clienteDomicilio = strtoupper(trim($_POST['cliente_domicilio'] ?? ''));
                $clienteEmail = trim($_POST['cliente_email'] ?? '');
                $clienteBeneficiario = strtoupper(trim($_POST['cliente_beneficiario'] ?? ''));

            if ($clienteNombre === '') {
                throw new \InvalidArgumentException('Capture el nombre del cliente.');
            }

            if ($clienteGenero === '') {
                throw new \InvalidArgumentException('Seleccione un género válido para el cliente.');
            }

            if (!in_array($clienteIdentificacion, ['INE', 'PASAPORTE', 'CEDULA PROFESIONAL'], true)) {
                throw new \InvalidArgumentException('Seleccione una identificación válida para el cliente.');
            }

                $fechaNacimientoDual = self::obtenerFechaDual($_POST, 'cliente_fecha_nacimiento');
                if (!$fechaNacimientoDual['date']) {
                    throw new \InvalidArgumentException('Fecha de nacimiento inválida.');
                }

                if ($clienteRfc === '') {
                    throw new \InvalidArgumentException('Capture el RFC del cliente.');
                }

                if (ModeloClientes::mdlExisteRfc($clienteRfc)) {
                    throw new \InvalidArgumentException('El RFC capturado ya está registrado.');
                }

                $datosCliente = [
                    'nombre' => $clienteNombre,
                    'nacionalidad' => $clienteNacionalidad,
                    'fecha_nacimiento' => $fechaNacimientoDual['date'],
                    'rfc' => $clienteRfc,
                    'curp' => $clienteCurp,
                    'ine' => $clienteIne,
                    'estado_civil' => $clienteEstadoCivil,
                    'ocupacion' => $clienteOcupacion,
                    'telefono' => $clienteTelefono,
                    'domicilio' => $clienteDomicilio,
                    'email' => $clienteEmail,
                    'beneficiario' => $clienteBeneficiario,
                ];

                $clienteId = ModeloClientes::mdlAgregarClienteRetId($datosCliente);
                if ($clienteId <= 0) {
                    throw new \RuntimeException('No se pudo registrar al cliente.');
                }

                $clienteJson = array_merge($datosCliente, [
                    'id' => $clienteId,
                    'genero' => $clienteGenero,
                    'fecha' => $fechaNacimientoDual['date'],
                    'fecha_texto' => $fechaNacimientoDual['texto'],
                    'fecha_nacimiento_texto' => $fechaNacimientoDual['texto'],
                    'edad' => self::calcularEdad($fechaNacimientoDual['date']),
                    'identificacion' => $clienteIdentificacion,
                ]);
            } else {
                $clienteExistente = ModeloClientes::mdlMostrarClientePorId($clienteId);
                if (!$clienteExistente) {
                    throw new \RuntimeException('El cliente seleccionado no existe.');
                }

                $clienteNombre = strtoupper(trim($_POST['cliente_nombre'] ?? ($clienteExistente['nombre'] ?? '')));
                $clienteNacionalidad = trim($_POST['cliente_nacionalidad'] ?? ($clienteExistente['nacionalidad'] ?? ''));
                $clienteGenero = self::normalizarGenero($_POST['cliente_genero'] ?? ($clienteJson['genero'] ?? null));
                $clienteRfc = strtoupper(trim($_POST['cliente_rfc'] ?? ($clienteExistente['rfc'] ?? '')));
                $clienteCurp = strtoupper(trim($_POST['cliente_curp'] ?? ($clienteExistente['curp'] ?? '')));
                $clienteIdentificacion = strtoupper(trim($_POST['cliente_identificacion'] ?? ($clienteJson['identificacion'] ?? '')));
                $clienteIne = strtoupper(trim($_POST['cliente_ine'] ?? ($clienteExistente['ine'] ?? '')));
                $clienteEstadoCivil = strtoupper(trim($_POST['cliente_estado_civil'] ?? ($clienteExistente['estado_civil'] ?? '')));
                $clienteOcupacion = strtoupper(trim($_POST['cliente_ocupacion'] ?? ($clienteExistente['ocupacion'] ?? '')));
                $clienteTelefono = trim($_POST['cliente_telefono'] ?? ($clienteExistente['telefono'] ?? ''));
                $clienteDomicilio = strtoupper(trim($_POST['cliente_domicilio'] ?? ($clienteExistente['domicilio'] ?? '')));
                $clienteEmail = trim($_POST['cliente_email'] ?? ($clienteExistente['email'] ?? ''));
                $clienteBeneficiario = strtoupper(trim($_POST['cliente_beneficiario'] ?? ($clienteExistente['beneficiario'] ?? '')));

                $fechaNacimientoDual = self::obtenerFechaDual($_POST, 'cliente_fecha_nacimiento');
                if (!$fechaNacimientoDual['date']) {
                    $fechaNacimientoDual['date'] = $clienteExistente['fecha_nacimiento'] ?? null;
                    $fechaNacimientoDual['texto'] = $clienteJson['fecha_nacimiento_texto']
                        ?? ($fechaNacimientoDual['date'] ? self::fechaEnFormatoLargo($fechaNacimientoDual['date']) : '');
                }

                if ($esEdicion) {
                    if ($clienteNombre === '') {
                        throw new \InvalidArgumentException('Capture el nombre del cliente.');
                    }
                    if ($fechaNacimientoDual['date'] === null) {
                        throw new \InvalidArgumentException('Fecha de nacimiento inválida.');
                    }
                    if ($clienteRfc === '') {
                        throw new \InvalidArgumentException('Capture el RFC del cliente.');
                    }
                    if ($clienteGenero === '') {
                        throw new \InvalidArgumentException('Seleccione un género válido para el cliente.');
                    }
                if (!in_array($clienteIdentificacion, ['INE', 'PASAPORTE', 'CEDULA PROFESIONAL'], true)) {
                        throw new \InvalidArgumentException('Seleccione una identificación válida para el cliente.');
                    }
                    if (ModeloClientes::mdlExisteRfc($clienteRfc, $clienteId)) {
                        throw new \InvalidArgumentException('El RFC capturado ya está registrado.');
                    }

                    $datosActualizar = [
                        'id' => $clienteId,
                        'nombre' => $clienteNombre,
                        'nacionalidad' => $clienteNacionalidad,
                        'fecha_nacimiento' => $fechaNacimientoDual['date'],
                        'rfc' => $clienteRfc,
                        'curp' => $clienteCurp,
                        'ine' => $clienteIne,
                        'estado_civil' => $clienteEstadoCivil,
                        'ocupacion' => $clienteOcupacion,
                        'telefono' => $clienteTelefono,
                        'domicilio' => $clienteDomicilio,
                        'email' => $clienteEmail,
                        'beneficiario' => $clienteBeneficiario,
                    ];

                    if (ModeloClientes::mdlEditarCliente($datosActualizar) !== 'ok') {
                        throw new \RuntimeException('No se pudo actualizar la información del cliente.');
                    }

                    $clienteExistente = array_merge($clienteExistente, $datosActualizar);
                }

                $fechaNacimiento = $fechaNacimientoDual['date'] ?? ($clienteExistente['fecha_nacimiento'] ?? null);
                $fechaTexto = $fechaNacimientoDual['texto']
                    ?: ($fechaNacimiento ? self::fechaEnFormatoLargo($fechaNacimiento) : '');

                $clienteJson = array_merge($clienteJson, [
                    'id' => $clienteId,
                    'nombre' => $clienteNombre ?: ($clienteExistente['nombre'] ?? ''),
                    'nacionalidad' => $clienteNacionalidad,
                    'genero' => $clienteGenero,
                    'fecha_nacimiento' => $fechaNacimiento,
                    'fecha' => $fechaNacimiento,
                    'fecha_nacimiento_texto' => $fechaTexto,
                    'fecha_texto' => $fechaTexto,
                    'rfc' => $clienteRfc,
                    'curp' => $clienteCurp,
                    'ine' => $clienteIne,
                    'estado_civil' => $clienteEstadoCivil,
                    'ocupacion' => $clienteOcupacion,
                    'telefono' => $clienteTelefono,
                    'domicilio' => $clienteDomicilio,
                    'email' => $clienteEmail,
                    'beneficiario' => $clienteBeneficiario,
                    'edad' => self::calcularEdad($fechaNacimiento),
                    'identificacion' => $clienteIdentificacion,
                ]);
            }

            $desarrolloId = isset($_POST['desarrollo_id']) ? (int)$_POST['desarrollo_id'] : 0;
            if ($esEdicion && $desarrolloId <= 0) {
                $desarrolloId = (int)($contratoActual['desarrollo_id'] ?? 0);
            }
            if ($desarrolloId <= 0) {
                throw new \InvalidArgumentException('Seleccione un desarrollo válido.');
            }

            $desarrolloData = ModeloDesarrollos::mdlMostrarDesarrolloPorId($desarrolloId);
            if (!$desarrolloData) {
                throw new \InvalidArgumentException('Seleccione un desarrollo válido.');
            }

            $folio = strtoupper(trim($_POST['folio'] ?? ''));
            $folio = preg_replace('/[^A-Z0-9_-]/u', '', $folio);
            if ($folio === '') {
                throw new \InvalidArgumentException('Capture un folio válido para el contrato.');
            }
            if (ModeloContratos::mdlExisteFolio($folio, $esEdicion ? $contratoId : null)) {
                throw new \InvalidArgumentException('El folio indicado ya está registrado.');
            }
            $mensualidades = (int)($_POST['mensualidades'] ?? 0);
            $superficieEntrada = trim($_POST['contrato_superficie'] ?? '');

            $fracciones = self::normalizarFracciones($_POST['fracciones'] ?? '');

            $entregaDual = self::obtenerFechaDual($_POST, 'entrega_posecion');
            $firmaDual = self::obtenerFechaDual($_POST, 'fecha_firma');
            $inicioPagosDual = self::obtenerFechaDual($_POST, 'inicio_pagos');
            $vigenciaPagareDual = self::obtenerFechaDual($_POST, 'vigencia_pagare');
            $rangoInicioDual = self::obtenerFechaDual($_POST, 'rango_pago_inicio');
            $rangoFinDual = self::obtenerFechaDual($_POST, 'rango_pago_fin');
            $fechaContratoDual = self::obtenerFechaDual($_POST, 'fecha_contrato');

            $habitacional = strtoupper(trim($_POST['habitacional'] ?? ''));
            $tipoContrato = trim($_POST['tipo_contrato'] ?? '');
            $rangoPagoLibre = trim($_POST['rango_pago'] ?? '');
            $clausulas = trim($_POST['financiamiento_clusulas'] ?? '');
            $parcialidades = trim($_POST['parcialidades_anuales'] ?? '');
            $clausulaPosecion = strtoupper(trim($_POST['clausula_c_posecion'] ?? ''));

            $montoInmueble = (float)($_POST['monto_inmueble'] ?? 0);
            $enganche = (float)($_POST['enganche'] ?? 0);
            $saldoPago = (float)($_POST['saldo_pago'] ?? 0);
            $penalizacion = (float)($_POST['penalizacion'] ?? 0);
            $pagoMensual = (float)($_POST['pago_mensual'] ?? 0);

            $solicitudOrigenId = isset($_POST['solicitud_id_origen']) ? (int)$_POST['solicitud_id_origen'] : 0;
            if ($esEdicion && $solicitudOrigenId <= 0) {
                $solicitudOrigenId = (int)($jsonActual['contrato']['solicitud_origen_id'] ?? 0);
            }
            $nombreCortoUsuario = self::obtenerNombreCortoContrato($solicitudOrigenId, $jsonActual['contrato']['nombre_corto'] ?? null);
            $identificacionSolicitudTipo = self::obtenerIdentificacionSolicitud($solicitudOrigenId);
            $solicitudSegmento = self::obtenerSolicitudParaContrato($solicitudOrigenId, $jsonActual['solicitud'] ?? []);

            $contratoAnterior = $jsonActual['contrato'] ?? [];

            $superficieFuente = $superficieEntrada !== ''
                ? $superficieEntrada
                : (string)($contratoAnterior['superficie'] ?? '');
            $superficieNormalizada = self::normalizarDecimal($superficieFuente, 2);
            if ($superficieNormalizada === '' && isset($contratoAnterior['superficie'])) {
                $superficieNormalizada = self::normalizarDecimal($contratoAnterior['superficie'], 2);
            }
            if ($superficieNormalizada === '') {
                throw new \InvalidArgumentException('Capture la superficie del contrato.');
            }

            $superficieTexto = trim((string)($_POST['superficie_fixed'] ?? ($contratoAnterior['superficie_fixed'] ?? '')));
            if ($superficieTexto === '') {
                $superficieTexto = self::superficieEnTexto($superficieNormalizada);
            }

            $diceSerFormulario = strtoupper(trim($_POST['dice_ser'] ?? ''));
            if ($diceSerFormulario === '' && isset($contratoAnterior['dice_ser'])) {
                $diceSerFormulario = strtoupper(trim((string)$contratoAnterior['dice_ser']));
            }

            $contratoDetalle = array_merge($contratoAnterior, [
                'folio' => $folio,
                'nombre_corto' => $nombreCortoUsuario,
                'mensualidades' => $mensualidades,
                'superficie' => $superficieNormalizada,
                'superficie_fixed' => $superficieTexto,
                'fraccion_vendida' => implode(',', $fracciones),
                'entrega_posecion' => $entregaDual['texto'],
                'entrega_posecion_date' => $entregaDual['date'],
                'clausula_c_posecion' => $clausulaPosecion,
                'fecha_firma_contrato' => $firmaDual['texto'],
                'fecha_firma_contrato_date' => $firmaDual['date'],
                'habitacional_colindancias' => $habitacional,
                'inicio_pagos' => $inicioPagosDual['texto'],
                'inicio_pagos_date' => $inicioPagosDual['date'],
                'tipo_contrato' => $tipoContrato,
                'monto_precio_inmueble' => number_format($montoInmueble, 2, '.', ','),
                'monto_precio_inmueble_valor' => $montoInmueble,
                'monto_precio_inmueble_fixed' => trim($_POST['monto_inmueble_fixed'] ?? ($contratoAnterior['monto_precio_inmueble_fixed'] ?? '')),
                'enganche' => number_format($enganche, 2, '.', ','),
                'enganche_valor' => $enganche,
                'enganche_fixed' => trim($_POST['enganche_fixed'] ?? ($contratoAnterior['enganche_fixed'] ?? '')),
                'saldo_pago' => number_format($saldoPago, 2, '.', ','),
                'saldo_pago_valor' => $saldoPago,
                'saldo_pago_fixed' => trim($_POST['saldo_pago_fixed'] ?? ($contratoAnterior['saldo_pago_fixed'] ?? '')),
                'parcialidades_anuales' => $parcialidades,
                'financiamiento_clusulas' => $clausulas,
                'penalizacion_10' => number_format($penalizacion, 2, '.', ','),
                'penalizacion_10_valor' => $penalizacion,
                'penalizacion_10_fixed' => trim($_POST['penalizacion_fixed'] ?? ($contratoAnterior['penalizacion_10_fixed'] ?? '')),
                'pago_mensual' => number_format($pagoMensual, 2, '.', ','),
                'pago_mensual_valor' => $pagoMensual,
                'pago_mensual_fixed' => trim($_POST['pago_mensual_fixed'] ?? ($contratoAnterior['pago_mensual_fixed'] ?? '')),
                'fecha_contrato' => $fechaContratoDual['texto'],
                'fecha_contrato_date' => $fechaContratoDual['date'],
                'fecha_contrato_fixed' => trim($_POST['fecha_contrato_fixed'] ?? ($contratoAnterior['fecha_contrato_fixed'] ?? '')) ?: $fechaContratoDual['texto'],
                'rango_pago_inicio' => $rangoInicioDual['texto'],
                'rango_pago_inicio_date' => $rangoInicioDual['date'],
                'rango_pago_fin' => $rangoFinDual['texto'],
                'rango_pago_fin_date' => $rangoFinDual['date'],
                'rango_pago' => $rangoPagoLibre,
                'vigencia_pagare' => $vigenciaPagareDual['texto'],
                'vigencia_pagare_date' => $vigenciaPagareDual['date'],
                'dice_ser' => $diceSerFormulario,
                'observaciones' => trim((string)($_POST['observaciones'] ?? ($contratoAnterior['observaciones'] ?? $contratoAnterior['referencias'] ?? ''))),
                'estatus' => $contratoAnterior['estatus'] ?? 1,
                'estado' => $contratoAnterior['estado'] ?? 'activo',
                'solicitud_origen_id' => $solicitudOrigenId > 0 ? $solicitudOrigenId : 0,
            ]);

            $contratoDetalle['albacea_activo'] = $solicitudSegmento['albacea']['activo'];
            $contratoDetalle['albacea_nombre'] = $solicitudSegmento['albacea']['nombre'];
            $contratoDetalle['albacea_edad'] = $solicitudSegmento['albacea']['edad'];
            $contratoDetalle['albacea_parentesco'] = $solicitudSegmento['albacea']['parentesco'];
            $contratoDetalle['albacea_celular'] = $solicitudSegmento['albacea']['celular'];
            $contratoDetalle['albacea'] = $solicitudSegmento['albacea'];

            $contratoDetalle['usa_pago_anual'] = $solicitudSegmento['anualidad']['usa_pago_anual'];
            $contratoDetalle['pago_anual'] = $solicitudSegmento['anualidad']['pago_anual'];
            $contratoDetalle['pago_anual_valor'] = $solicitudSegmento['anualidad']['pago_anual_valor'];
            $contratoDetalle['fecha_pago_anual'] = $solicitudSegmento['anualidad']['fecha_pago_anual'];
            $contratoDetalle['plazo_anual'] = $solicitudSegmento['anualidad']['plazo_anual'];
            $contratoDetalle['anualidad'] = $solicitudSegmento['anualidad'];

            if ($fechaContratoDual['date']) {
                $contratoDetalle['dia_inicio'] = (int)substr($fechaContratoDual['date'], 8, 2);
            }

            if ($identificacionSolicitudTipo !== '') {
                $contratoDetalle['solicitud_identificacion'] = $identificacionSolicitudTipo;
            } elseif (array_key_exists('solicitud_identificacion', $contratoDetalle)) {
                unset($contratoDetalle['solicitud_identificacion']);
            }

            if ($diceSerFormulario !== '') {
                $clienteJson['dice_ser'] = $diceSerFormulario;
            }

            $jsonData = json_encode([
                'cliente' => $clienteJson,
                'desarrollo' => $desarrolloData,
                'contrato' => $contratoDetalle,
                'solicitud' => $solicitudSegmento,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            if ($esEdicion) {
                $resultado = ModeloContratos::mdlEditarContrato($contratoId, $jsonData, $folio, $clienteId, $desarrolloId);
                if ($resultado !== 'ok') {
                    throw new \RuntimeException('No se pudo actualizar el contrato.');
                }

                $pdo->commit();

                $respond('ok', [
                    'message' => 'Contrato actualizado correctamente',
                    'contrato_id' => $contratoId,
                    'cliente_id' => $clienteId,
                ]);
            } else {
                $contratoIdNuevo = ModeloContratos::mdlCrearContrato([
                    'cliente_id' => $clienteId,
                    'desarrollo_id' => $desarrolloId,
                    'datta_contrato' => $jsonData,
                    'creado_por' => (int)($_SESSION['id'] ?? 0),
                    'folio' => $folio,
                ]);

                if (!$contratoIdNuevo) {
                    throw new \RuntimeException('No se pudo registrar el contrato.');
                }

                $pdo->commit();

                $respond('ok', [
                    'message' => 'Contrato creado correctamente',
                    'contrato_id' => $contratoIdNuevo,
                    'cliente_id' => $clienteId,
                ]);
            }
        } catch (\InvalidArgumentException $validationException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Validación al crear contrato completo: ' . $validationException->getMessage());
            $respond('error', [
                'message' => $validationException->getMessage(),
                'code' => 'VAL-001',
            ], 422);
        } catch (\JsonException $jsonException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error de JSON al crear contrato completo: ' . $jsonException->getMessage());
            $respond('error', [
                'message' => 'Ocurrió un error al procesar la información del contrato.',
                'code' => 'JSON-001',
                'detail' => $jsonException->getMessage(),
            ], 500);
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error al crear contrato completo: ' . $throwable->getMessage());
            $respond('error', [
                'message' => $throwable instanceof \PDOException ? 'Error de base de datos al crear el contrato.' : 'Ocurrió un error al crear el contrato.',
                'code' => (string)($throwable->getCode() ?: 'CTR-500'),
                'detail' => $throwable instanceof \PDOException ? ($throwable->errorInfo[2] ?? $throwable->getMessage()) : $throwable->getMessage(),
            ], 500);
        }
    }

    /*
     * Obtiene los datos de un contrato existente para un cliente específico.
     * Devuelve un array asociativo con los datos o null si no existe.
     *
     * @param int $clienteId
     * @return array|null
     */
    static public function ctrMostrarContratoPorCliente($clienteId)
    {
        $data = ModeloContratos::mdlMostrarContratoPorCliente($clienteId);
        if (!$data) return null;
        // Decodificar JSON y combinar con datos
        $result = [];
        $result['nombre_desarrollo'] = $data['nombre_desarrollo'];
        if (!empty($data['datta_contrato'])) {
            $json = json_decode($data['datta_contrato'], true);
            if (isset($json['contrato']) && is_array($json['contrato'])) {
                // Mapear campos a los nombres utilizados en la vista
                $contrato = $json['contrato'];
                $result['mensualidades'] = $contrato['mensualidades'] ?? '';
                $result['superficie'] = $contrato['superficie'] ?? '';
                $result['fraccion_vendida'] = $contrato['fraccion_vendida'] ?? '';
                $result['entrega_posecion'] = $contrato['entrega_posecion'] ?? '';
                $result['fecha_firma_contrato'] = $contrato['fecha_firma_contrato'] ?? '';
                $result['habitacional_colindancias'] = $contrato['habitacional_colindancias'] ?? '';
                $result['inicio_pagos'] = $contrato['inicio_pagos'] ?? '';
                $result['tipo_contrato'] = $contrato['tipo_contrato'] ?? '';
            }
        }
        return $result;
    }

    /**
     * Devuelve la lista de contratos. Si se especifica un cliente, sólo se listan
     * los contratos de ese cliente. Decodifica el JSON de datta_contrato para
     * exponer campos básicos en el array de resultados.
     *
     * @param int|null $clienteId
     * @return array
     */
    static public function ctrMostrarContratos($clienteId = null)
    {
        if (!self::usuarioPuedeGestionarContratos()) {
            return [];
        }

        $contratos = ModeloContratos::mdlMostrarContratos($clienteId);
        $resultado = [];
        foreach ($contratos as $c) {
            $jsonContrato = [];
            if (!empty($c['datta_contrato'])) {
                $decodificado = json_decode($c['datta_contrato'], true);
                if (is_array($decodificado)) {
                    $jsonContrato = $decodificado;
                }
            }

            $estatus = isset($c['estatus']) ? (int)$c['estatus'] : null;
            if ($estatus === null && isset($jsonContrato['contrato']) && is_array($jsonContrato['contrato'])) {
                if (isset($jsonContrato['contrato']['estatus'])) {
                    $estatus = (int)$jsonContrato['contrato']['estatus'];
                } else {
                    $estadoTexto = strtolower((string)($jsonContrato['contrato']['estado'] ?? ''));
                    $estatus = match ($estadoTexto) {
                        'cancelado' => 2,
                        'archivado' => 0,
                        default => 1,
                    };
                }
            }

            if ($estatus === null) {
                $estatus = 1;
            }

            $row = [
                'id' => $c['id'],
                'estatus' => $estatus,
                'cliente_id' => $c['cliente_id'],
                'nombre_cliente' => $c['nombre_cliente'] ?? '',
                'desarrollo_id' => $c['desarrollo_id'],
                'nombre_desarrollo' => $c['nombre_desarrollo'] ?? '',
                'tipo_contrato' => $c['tipo_contrato'] ?? '',
                'created_at' => $c['created_at'] ?? '',
                'lotes_disponibles' => $c['lotes_disponibles'] ?? ''
            ];
            $row['solicitud_origen_id'] = 0;
            $row['estatus_texto'] = match ($estatus) {
                0 => 'Archivado',
                2 => 'Cancelado',
                default => 'Activo',
            };
            // Decodificar json para obtener campos adicionales (mensualidades, superficie, fraccion)
            if (!empty($jsonContrato) && isset($jsonContrato['contrato']) && is_array($jsonContrato['contrato'])) {
                $row['mensualidades'] = $jsonContrato['contrato']['mensualidades'] ?? '';
                $row['superficie'] = $jsonContrato['contrato']['superficie'] ?? '';
                $row['fraccion_vendida'] = $jsonContrato['contrato']['fraccion_vendida'] ?? '';
                $row['entrega_posecion'] = $jsonContrato['contrato']['entrega_posecion'] ?? '';
                $row['fecha_firma_contrato'] = $jsonContrato['contrato']['fecha_firma_contrato'] ?? '';
                $row['habitacional_colindancias'] = $jsonContrato['contrato']['habitacional_colindancias'] ?? '';
                $row['inicio_pagos'] = $jsonContrato['contrato']['inicio_pagos'] ?? '';
                $row['contrato_detalle'] = $jsonContrato['contrato'];
                $row['cliente_detalle'] = $jsonContrato['cliente'] ?? [];
                $row['desarrollo_detalle'] = $jsonContrato['desarrollo'] ?? [];
                $row['solicitud_origen_id'] = (int)($jsonContrato['contrato']['solicitud_origen_id'] ?? 0);
                // Type contract may come from contrato as well; override if set
                if (!empty($jsonContrato['contrato']['tipo_contrato'])) {
                    $row['tipo_contrato'] = $jsonContrato['contrato']['tipo_contrato'];
                }
                // Nuevos campos del contrato (monto, enganche, saldo, parcialidades, penalización, etc.)
                $row['monto_precio_inmueble']      = $jsonContrato['contrato']['monto_precio_inmueble'] ?? '';
                $row['monto_precio_inmueble_fixed'] = $jsonContrato['contrato']['monto_precio_inmueble_fixed'] ?? '';
                $row['enganche']                   = $jsonContrato['contrato']['enganche'] ?? '';
                $row['enganche_fixed']             = $jsonContrato['contrato']['enganche_fixed'] ?? '';
                $row['saldo_pago']                 = $jsonContrato['contrato']['saldo_pago'] ?? '';
                $row['saldo_pago_fixed']           = $jsonContrato['contrato']['saldo_pago_fixed'] ?? '';
                $row['parcialidades_anuales']      = $jsonContrato['contrato']['parcialidades_anuales'] ?? '';
                $row['penalizacion_10']            = $jsonContrato['contrato']['penalizacion_10'] ?? '';
                $row['penalizacion_10_fixed']      = $jsonContrato['contrato']['penalizacion_10_fixed'] ?? '';
                // Folio y rango de pago se almacenan en versiones unificadas
                $row['folio']                      = $jsonContrato['contrato']['folio'] ?? '';
                $row['nombre_corto']               = $jsonContrato['contrato']['nombre_corto'] ?? '';
                $row['rango_pago']                 = $jsonContrato['contrato']['rango_pago'] ?? '';
                $row['vigencia_pagare']            = $jsonContrato['contrato']['vigencia_pagare'] ?? '';
                // Campos de compatibilidad antiguos
                $row['dia_pago']                   = $jsonContrato['contrato']['dia_pago'] ?? '';
                $row['rango_compromiso_pago']      = $jsonContrato['contrato']['rango_compromiso_pago'] ?? '';
                // Nuevos campos: pago mensual y fecha de contrato
                $row['pago_mensual']               = $jsonContrato['contrato']['pago_mensual'] ?? '';
                $row['pago_mensual_fixed']         = $jsonContrato['contrato']['pago_mensual_fixed'] ?? '';
                $row['fecha_contrato']             = $jsonContrato['contrato']['fecha_contrato'] ?? '';
                $row['fecha_contrato_fixed']       = $jsonContrato['contrato']['fecha_contrato_fixed'] ?? '';
                // Superficie convertida a letras y día de inicio
                $row['superficie_fixed']           = $jsonContrato['contrato']['superficie_fixed'] ?? '';
                $row['dia_inicio']                 = $jsonContrato['contrato']['dia_inicio'] ?? '';
            }
            $row['contrato_detalle'] = $row['contrato_detalle'] ?? [];
            $row['cliente_detalle'] = $row['cliente_detalle'] ?? [];
            $row['desarrollo_detalle'] = $row['desarrollo_detalle'] ?? [];
            $resultado[] = $row;
        }
        return $resultado;
    }

    public static function ctrEditarContrato(): ?array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['editarContrato'])) {
            return null;
        }

        if (!self::usuarioPuedeGestionarContratos()) {
            return ['tipo' => 'error', 'mensaje' => 'No tiene permisos para editar contratos.'];
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            return ['tipo' => 'error', 'mensaje' => 'Token de seguridad inválido.'];
        }

        $contratoId = (int)($_POST['contrato_id'] ?? 0);
        if ($contratoId <= 0) {
            return ['tipo' => 'error', 'mensaje' => 'Identificador de contrato no válido.'];
        }

        $contratoRow = ModeloContratos::mdlMostrarContratoPorId($contratoId);
        if (!$contratoRow) {
            return ['tipo' => 'error', 'mensaje' => 'El contrato seleccionado no existe.'];
        }

        $data = json_decode($contratoRow['datta_contrato'] ?? '', true);
        if (!is_array($data)) {
            return ['tipo' => 'error', 'mensaje' => 'Los datos almacenados del contrato son inválidos.'];
        }

        $contratoJson = $data['contrato'] ?? [];

        $solicitudOrigen = isset($_POST['solicitud_origen_id']) ? (int)$_POST['solicitud_origen_id'] : (int)($contratoJson['solicitud_origen_id'] ?? 0);
        $contratoJson['solicitud_origen_id'] = $solicitudOrigen > 0 ? $solicitudOrigen : 0;
        $contratoJson['nombre_corto'] = self::obtenerNombreCortoContrato($contratoJson['solicitud_origen_id'], $contratoJson['nombre_corto'] ?? null);
        $solicitudSegmento = self::obtenerSolicitudParaContrato($contratoJson['solicitud_origen_id'], $data['solicitud'] ?? []);

        $folioEditado = strtoupper(trim($_POST['folio'] ?? ($contratoJson['folio'] ?? '')));
        $folioEditado = preg_replace('/[^A-Z0-9_-]/u', '', $folioEditado);
        if ($folioEditado === '') {
            return ['tipo' => 'error', 'mensaje' => 'Capture un folio válido para el contrato.'];
        }
        if (ModeloContratos::mdlExisteFolio($folioEditado, $contratoId)) {
            return ['tipo' => 'error', 'mensaje' => 'Ya existe un contrato con el folio indicado.'];
        }
        $contratoJson['folio'] = $folioEditado;
        $contratoJson['mensualidades'] = max(1, (int)($_POST['mensualidades'] ?? ($contratoJson['mensualidades'] ?? 1)));
        $contratoJson['superficie'] = trim($_POST['superficie'] ?? ($contratoJson['superficie'] ?? ''));
        $contratoJson['superficie_fixed'] = trim($_POST['superficie_fixed'] ?? ($contratoJson['superficie_fixed'] ?? ''));
        $contratoJson['fraccion_vendida'] = trim($_POST['fracciones'] ?? ($contratoJson['fraccion_vendida'] ?? ''));

        $entregaDate = self::normalizarFecha($_POST['entrega_posecion'] ?? null);
        $contratoJson['entrega_posecion_date'] = $entregaDate;
        $contratoJson['entrega_posecion'] = $entregaDate ? self::fechaEnFormatoLargo($entregaDate) : '';

        $firmaDate = self::normalizarFecha($_POST['fecha_firma'] ?? null);
        $contratoJson['fecha_firma_contrato_date'] = $firmaDate;
        $contratoJson['fecha_firma_contrato'] = $firmaDate ? self::fechaEnFormatoLargo($firmaDate) : '';

        $inicioPagosDate = self::normalizarFecha($_POST['inicio_pagos'] ?? null);
        $contratoJson['inicio_pagos_date'] = $inicioPagosDate;
        $contratoJson['inicio_pagos'] = $inicioPagosDate ? self::fechaEnFormatoLargo($inicioPagosDate) : '';

        $rangoInicioDate = self::normalizarFecha($_POST['rango_pago_inicio'] ?? null);
        $contratoJson['rango_pago_inicio_date'] = $rangoInicioDate;
        $contratoJson['rango_pago_inicio'] = $rangoInicioDate ? self::fechaEnFormatoLargo($rangoInicioDate) : '';

        $rangoFinDate = self::normalizarFecha($_POST['rango_pago_fin'] ?? null);
        $contratoJson['rango_pago_fin_date'] = $rangoFinDate;
        $contratoJson['rango_pago_fin'] = $rangoFinDate ? self::fechaEnFormatoLargo($rangoFinDate) : '';

        $fechaContratoDate = self::normalizarFecha($_POST['fecha_contrato'] ?? null);
        $contratoJson['fecha_contrato_date'] = $fechaContratoDate;
        $contratoJson['fecha_contrato'] = $fechaContratoDate ? self::fechaEnFormatoLargo($fechaContratoDate) : '';
        if ($fechaContratoDate) {
            $contratoJson['fecha_contrato_fixed'] = trim($_POST['fecha_contrato_fixed'] ?? ($contratoJson['fecha_contrato_fixed'] ?? self::fechaEnFormatoLargo($fechaContratoDate)));
            $contratoJson['dia_inicio'] = (int)substr($fechaContratoDate, 8, 2);
        }

        $vigenciaPagareDate = self::normalizarFecha($_POST['vigencia_pagare'] ?? null);
        $contratoJson['vigencia_pagare_date'] = $vigenciaPagareDate;
        $contratoJson['vigencia_pagare'] = $vigenciaPagareDate ? self::fechaEnFormatoLargo($vigenciaPagareDate) : trim($_POST['vigencia_pagare_texto'] ?? ($contratoJson['vigencia_pagare'] ?? ''));

        $contratoJson['habitacional_colindancias'] = strtoupper(trim($_POST['habitacional'] ?? ($contratoJson['habitacional_colindancias'] ?? '')));
        $contratoJson['clausula_c_posecion'] = strtoupper(trim($_POST['clausula_c_posecion'] ?? ($contratoJson['clausula_c_posecion'] ?? '')));
        $contratoJson['financiamiento_clusulas'] = trim($_POST['financiamiento_clusulas'] ?? ($contratoJson['financiamiento_clusulas'] ?? ''));

        $contratoJson['monto_precio_inmueble'] = self::normalizarMonto($_POST['monto_inmueble'] ?? ($contratoJson['monto_precio_inmueble'] ?? ''));
        $contratoJson['monto_precio_inmueble_fixed'] = trim($_POST['monto_inmueble_fixed'] ?? ($contratoJson['monto_precio_inmueble_fixed'] ?? ''));
        $contratoJson['enganche'] = self::normalizarMonto($_POST['enganche'] ?? ($contratoJson['enganche'] ?? ''));
        $contratoJson['enganche_fixed'] = trim($_POST['enganche_fixed'] ?? ($contratoJson['enganche_fixed'] ?? ''));
        $contratoJson['saldo_pago'] = self::normalizarMonto($_POST['saldo_pago'] ?? ($contratoJson['saldo_pago'] ?? ''));
        $contratoJson['saldo_pago_fixed'] = trim($_POST['saldo_pago_fixed'] ?? ($contratoJson['saldo_pago_fixed'] ?? ''));
        $contratoJson['penalizacion_10'] = self::normalizarMonto($_POST['penalizacion'] ?? ($contratoJson['penalizacion_10'] ?? ''));
        $contratoJson['penalizacion_10_fixed'] = trim($_POST['penalizacion_fixed'] ?? ($contratoJson['penalizacion_10_fixed'] ?? ''));
        $contratoJson['pago_mensual'] = self::normalizarMonto($_POST['pago_mensual'] ?? ($contratoJson['pago_mensual'] ?? ''));
        $contratoJson['pago_mensual_fixed'] = trim($_POST['pago_mensual_fixed'] ?? ($contratoJson['pago_mensual_fixed'] ?? ''));

        $contratoJson['parcialidades_anuales'] = trim($_POST['parcialidades_anuales'] ?? ($contratoJson['parcialidades_anuales'] ?? ''));
        $contratoJson['rango_pago'] = trim($_POST['rango_pago'] ?? ($contratoJson['rango_pago'] ?? ''));
        $contratoJson['dice_ser'] = strtoupper(trim($_POST['dice_ser'] ?? ($contratoJson['dice_ser'] ?? '')));
        $contratoJson['observaciones'] = trim((string)($_POST['observaciones'] ?? ($contratoJson['observaciones'] ?? $contratoJson['referencias'] ?? '')));

        $contratoJson['albacea_activo'] = $solicitudSegmento['albacea']['activo'];
        $contratoJson['albacea_nombre'] = $solicitudSegmento['albacea']['nombre'];
        $contratoJson['albacea_edad'] = $solicitudSegmento['albacea']['edad'];
        $contratoJson['albacea_parentesco'] = $solicitudSegmento['albacea']['parentesco'];
        $contratoJson['albacea_celular'] = $solicitudSegmento['albacea']['celular'];
        $contratoJson['albacea'] = $solicitudSegmento['albacea'];

        $contratoJson['usa_pago_anual'] = $solicitudSegmento['anualidad']['usa_pago_anual'];
        $contratoJson['pago_anual'] = $solicitudSegmento['anualidad']['pago_anual'];
        $contratoJson['pago_anual_valor'] = $solicitudSegmento['anualidad']['pago_anual_valor'];
        $contratoJson['fecha_pago_anual'] = $solicitudSegmento['anualidad']['fecha_pago_anual'];
        $contratoJson['plazo_anual'] = $solicitudSegmento['anualidad']['plazo_anual'];
        $contratoJson['anualidad'] = $solicitudSegmento['anualidad'];

        $data['contrato'] = $contratoJson;
        $data['solicitud'] = $solicitudSegmento;

        $jsonNuevo = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($jsonNuevo === false) {
            return ['tipo' => 'error', 'mensaje' => 'No fue posible preparar la información del contrato.'];
        }

        $resultado = ModeloContratos::mdlEditarContrato($contratoId, $jsonNuevo, $contratoJson['folio'] ?? null);
        if ($resultado !== 'ok') {
            return ['tipo' => 'error', 'mensaje' => 'No se pudo actualizar el contrato.'];
        }

        return ['tipo' => 'success', 'mensaje' => 'Contrato actualizado correctamente.'];
    }


    /**
     * Devuelve el listado de placeholders disponibles para un contrato
     * específico en formato JSON. Solo los usuarios con permisos de gestión
     * pueden consultar esta información mediante solicitudes AJAX.
     */
    public static function ctrObtenerPlaceholdersContrato(): void
    {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'GET'
            || !isset($_GET['obtenerPlaceholdersContrato'])
        ) {
            return;
        }

        $respond = static function (string $status, array $payload = [], int $httpCode = 200): void {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                if ($httpCode !== 200) {
                    http_response_code($httpCode);
                }
            }

            echo json_encode(array_merge(['status' => $status], $payload), JSON_UNESCAPED_UNICODE);
            exit;
        };

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            $respond('error', ['message' => 'Debe iniciar sesión para continuar.'], 401);
        }

        if (!self::usuarioPuedeGestionarContratos()) {
            $respond('error', ['message' => 'No tiene permisos para consultar los placeholders.'], 403);
        }

        $contratoId = isset($_GET['contrato_id']) ? (int)$_GET['contrato_id'] : 0;
        if ($contratoId <= 0) {
            $respond('error', ['message' => 'Identificador de contrato no válido.'], 422);
        }

        $contratoRow = ModeloContratos::mdlMostrarContratoPorId($contratoId);
        if (!$contratoRow) {
            $respond('error', ['message' => 'El contrato indicado no existe.'], 404);
        }

        $json = $contratoRow['datta_contrato'] ?? '';
        $data = $json !== '' ? json_decode($json, true) : null;
        if (!is_array($data)) {
            $respond('error', ['message' => 'Los datos del contrato están dañados.'], 500);
        }

        $placeholders = self::construirPlaceholdersContrato([
            'registro' => $contratoRow,
            'cliente' => $data['cliente'] ?? [],
            'desarrollo' => $data['desarrollo'] ?? [],
            'contrato' => $data['contrato'] ?? [],
            'solicitud' => $data['solicitud'] ?? [],
        ]);
        ksort($placeholders);

        $listado = [];
        foreach ($placeholders as $clave => $valor) {
            $listado[] = [
                'clave' => $clave,
                'valor' => $valor,
            ];
        }

        $respond('ok', [
            'contrato_id' => $contratoId,
            'total' => count($listado),
            'placeholders' => $listado,
        ]);
    }

    /**
     * Busca solicitudes compatibles con los datos capturados del contrato para
     * facilitar la vinculación sin necesidad de introducir el ID manualmente.
     */
    public static function ctrBuscarSolicitudesCompatibles(): void
    {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST'
            || !isset($_POST['buscarSolicitudesContrato'])
        ) {
            return;
        }

        $respond = static function (string $status, array $payload = [], int $httpCode = 200): void {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                if ($httpCode !== 200) {
                    http_response_code($httpCode);
                }
            }

            echo json_encode(array_merge(['status' => $status], $payload), JSON_UNESCAPED_UNICODE);
            exit;
        };

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            $respond('error', ['message' => 'Debe iniciar sesión para continuar.'], 401);
        }

        if (!self::usuarioPuedeGestionarContratos()) {
            $respond('error', ['message' => 'No tiene permisos para vincular solicitudes.'], 403);
        }

        $token = (string)($_POST['csrf_token'] ?? '');
        if ($token === '' || $token !== ($_SESSION['csrf_token'] ?? '')) {
            $respond('error', ['message' => 'Token de seguridad inválido. Refresque la página e intente de nuevo.'], 419);
        }

        $criterios = [
            'folio' => $_POST['folio'] ?? '',
            'rfc' => $_POST['rfc'] ?? '',
            'curp' => $_POST['curp'] ?? '',
        ];

        $hayDatos = false;
        foreach ($criterios as $valor) {
            if (trim((string)$valor) !== '') {
                $hayDatos = true;
                break;
            }
        }

        if (!$hayDatos) {
            $respond('ok', [
                'total' => 0,
                'solicitudes' => [],
                'message' => 'Capture folio, RFC o CURP antes de buscar.',
            ]);
        }

        try {
            $limite = isset($_POST['limite']) ? (int)$_POST['limite'] : 15;
            $resultados = ModeloSolicitudes::mdlBuscarSolicitudesCoincidencias($criterios, $limite);
        } catch (\Throwable $throwable) {
            error_log('[Contratos] Error al buscar solicitudes compatibles: '
                . $throwable->getMessage() . ' en ' . $throwable->getFile() . ':' . $throwable->getLine());
            $respond('error', [
                'message' => 'No fue posible buscar solicitudes compatibles.',
            ], 500);
        }

        $listado = [];
        foreach ($resultados as $solicitud) {
            $id = (int)($solicitud['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $listado[] = [
                'id' => $id,
                'folio' => trim((string)($solicitud['folio'] ?? '')),
                'nombre_completo' => trim((string)($solicitud['nombre_completo'] ?? '')),
                'rfc' => trim((string)($solicitud['rfc'] ?? '')),
                'curp' => trim((string)($solicitud['curp'] ?? '')),
                'estado' => trim((string)($solicitud['estado'] ?? '')),
                'created_at' => trim((string)($solicitud['created_at'] ?? '')),
                'coincidencias' => (int)($solicitud['coincidencias'] ?? 0),
            ];
        }

        $payload = [
            'total' => count($listado),
            'solicitudes' => $listado,
        ];

        if (empty($listado)) {
            $payload['message'] = 'No se encontraron solicitudes compatibles con los datos proporcionados.';
        }

        $respond('ok', $payload);
    }


    /**
     * Devuelve la información necesaria para prellenar el contrato a partir de
     * una solicitud seleccionada desde la interfaz de vinculación.
     */
    public static function ctrObtenerPrefillSolicitudContrato(): void
    {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST'
            || !isset($_POST['obtenerPrefillSolicitud'])
        ) {
            return;
        }

        $respond = static function (string $status, array $payload = [], int $httpCode = 200): void {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                if ($httpCode !== 200) {
                    http_response_code($httpCode);
                }
            }

            echo json_encode(array_merge(['status' => $status], $payload), JSON_UNESCAPED_UNICODE);
            exit;
        };

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            $respond('error', ['message' => 'Debe iniciar sesión para continuar.'], 401);
        }

        if (!self::usuarioPuedeGestionarContratos()) {
            $respond('error', ['message' => 'No tiene permisos para vincular solicitudes.'], 403);
        }

        $token = (string)($_POST['csrf_token'] ?? '');
        if ($token === '' || $token !== ($_SESSION['csrf_token'] ?? '')) {
            $respond('error', ['message' => 'Token de seguridad inválido. Refresque la página e intente nuevamente.'], 419);
        }

        $solicitudId = isset($_POST['solicitud_id']) ? (int)$_POST['solicitud_id'] : 0;
        if ($solicitudId <= 0) {
            $respond('error', ['message' => 'Identificador de solicitud no válido.'], 422);
        }

        $solicitud = ControladorSolicitudes::ctrObtenerSolicitudPorId($solicitudId);
        if (!$solicitud || !is_array($solicitud)) {
            $respond('error', ['message' => 'No se encontró la solicitud indicada.'], 404);
        }

        if (!empty($solicitud['contrato_id'])) {
            $respond('error', ['message' => 'La solicitud ya está vinculada a un contrato.'], 409);
        }

        $nacionalidades = class_exists(ControladorParametros::class)
            ? ControladorParametros::ctrMostrarVariables('nacionalidad')
            : [];
        $tiposContrato = class_exists(ControladorParametros::class)
            ? ControladorParametros::ctrMostrarVariables('tipo_contrato')
            : [];
        $desarrollos = ControladorDesarrollos::ctrMostrarDesarrollos();

        $prefill = self::construirPrefillSolicitud($solicitud, [
            'nacionalidades' => $nacionalidades,
            'tiposContrato' => $tiposContrato,
            'desarrollos' => $desarrollos,
        ]);

        $respond('ok', [
            'resumen' => $prefill['resumen'],
            'solicitud' => $prefill['solicitud'],
            'cliente' => $prefill['cliente'],
            'contrato' => $prefill['contrato'],
            'desarrollo' => $prefill['desarrollo'],
            'message' => sprintf(
                'Se vinculó la solicitud #%d y se actualizaron los datos del contrato.',
                $prefill['resumen']['id'] ?? $solicitudId
            ),
        ]);
    }


    /**
     * Genera un documento de contrato en formato DOCX y PDF a partir del registro
     * existente de un contrato. Este método lee los datos JSON almacenados en la
     * tabla argus_contratos_data, reemplaza los placeholders de la plantilla
     * correspondiente y crea un archivo ZIP con ambos formatos listo para descargar.
     *
     * La identificación del contrato se recibe por GET o POST mediante el
     * parámetro 'contrato_id'. Si la plantilla o los datos no existen, devuelve
     * un mensaje de error en formato JSON.
     *
     * @return void
     */
    public static function ctrGenerarDocumento()
    {
        // Aumentar límites razonables (si el hosting lo permite)
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '120');

        $bufferLevel = ob_get_level();
        ob_start();
        $errorHandlerRegistered = false;

        $respond = static function (string $status, array $payload = [], int $httpCode = 200) use ($bufferLevel, &$errorHandlerRegistered): void {
            if ($errorHandlerRegistered) {
                restore_error_handler();
                $errorHandlerRegistered = false;
            }

            $extraOutput = '';
            while (ob_get_level() > $bufferLevel) {
                $extraOutput = ob_get_clean() . $extraOutput;
            }

            $extraOutput = trim($extraOutput);
            if ($extraOutput !== '') {
                error_log('[Contrato DOCX] Salida inesperada capturada: ' . $extraOutput);
                if ($status === 'ok') {
                    $status = 'error';
                    $payload = [
                        'msg' => 'Se generó una salida inesperada durante la generación del contrato.',
                        'error_details' => $extraOutput,
                    ];
                    $httpCode = 500;
                } else {
                    $payload['unexpected_output'] = $extraOutput;
                }
            }

            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                if ($httpCode !== 200) {
                    http_response_code($httpCode);
                }
            }

            echo json_encode(array_merge(['status' => $status], $payload), JSON_UNESCAPED_UNICODE);
            exit;
        };

        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0) {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        $errorHandlerRegistered = true;

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        // ========= 1) OBTENER ID =========
        $contratoId = null;
        if (isset($_GET['contrato_id'])) {
            $contratoId = intval($_GET['contrato_id']);
        } elseif (isset($_POST['contrato_id'])) {
            $contratoId = intval($_POST['contrato_id']);
        }
        if (!$contratoId) {
            $respond('error', ['msg' => 'ID de contrato no proporcionado']);
        }

        if (!self::usuarioPuedeGestionarContratos()) {
            $respond('error', ['msg' => 'No tiene permisos para generar contratos'], 403);
        }

        try {
            // ========= 2) LEER CONTRATO Y DATOS =========
            $contratoRow = ModeloContratos::mdlMostrarContratoPorId($contratoId);
            if (!$contratoRow) {
                $respond('error', ['msg' => 'Contrato no encontrado'], 404);
            }
            $jsonData = $contratoRow['datta_contrato'] ?? null;
            $data     = $jsonData ? json_decode($jsonData, true) : null;
            if (!$data || !is_array($data)) {
                $respond('error', ['msg' => 'Datos del contrato no válidos'], 500);
            }

            $cliente    = $data['cliente']    ?? [];
            $desarrollo = $data['desarrollo'] ?? [];
            $contrato   = $data['contrato']   ?? [];

            // ========= 3) TIPO DE CONTRATO Y PLANTILLA =========
            $tipoContrato = $contrato['tipo_contrato'] ?? null;
            if (!$tipoContrato) {
                $respond('error', ['msg' => 'Tipo de contrato no definido'], 409);
            }

            $plantilla = ModeloPlantillas::mdlObtenerPlantillaPorTipo($tipoContrato);
            if (!$plantilla || empty($plantilla['ruta_archivo'])) {
                $respond('error', ['msg' => 'No se encontró una plantilla para el tipo de contrato'], 409);
            }

            // Resolver ruta absoluta de la plantilla de forma segura
            $plantillaPath = self::resolverRutaArchivo($plantilla['ruta_archivo'] ?? null);
            if (!$plantillaPath) {
                error_log('[Contrato DOCX] Plantilla no encontrada: ' . ($plantilla['ruta_archivo'] ?? 'N/D'));
                $respond('error', [
                    'msg' => 'El archivo de plantilla no existe en el servidor',
                    'error_details' => 'Ruta configurada: ' . ($plantilla['ruta_archivo'] ?? 'N/D')
                ], 500);
            }

            // ========= 4) VALIDACIONES DE EXTENSIONES =========
            $erroresExt = [];
            if (!class_exists('\\ZipArchive'))     $erroresExt[] = 'zip/ZipArchive';
            if (!extension_loaded('xml'))          $erroresExt[] = 'xml';
            if (!extension_loaded('dom'))          $erroresExt[] = 'dom';
            if (!extension_loaded('xmlwriter'))    $erroresExt[] = 'xmlwriter';
            if (!empty($erroresExt)) {
                $respond('error', [
                    'msg'    => 'Faltan extensiones PHP requeridas: ' . implode(', ', $erroresExt)
                ], 500);
            }

            // ========= 5) PLACEHOLDERS =========
            $placeholders = self::construirPlaceholdersContrato([
                'registro' => $contratoRow,
                'cliente' => $cliente,
                'desarrollo' => $desarrollo,
                'contrato' => $contrato,
                'solicitud' => $data['solicitud'] ?? [],
            ]);

            // ========= 6) PREPARAR RUTAS DE SALIDA =========
            // Usamos tu /tmp existente (ya confirmado que funciona en Hostinger)
            $basePath = self::basePath();
            $tmpDir = $basePath . '/tmp';
            if (!is_dir($tmpDir)) {
                @mkdir($tmpDir, 0775, true);
            }

            // Nombres seguros (ASCII) para evitar problemas en headers y sistemas de archivos
            $nombreCliente = preg_replace('/[^A-Za-z0-9_-]+/', '-', $cliente['nombre'] ?? 'cliente');
            $fechaFolio    = preg_replace('/[^A-Za-z0-9_-]+/', '-', $contrato['folio'] ?? 'folio');
            $baseName      = $fechaFolio . '-' . $nombreCliente;

            $docxPath = $tmpDir . '/' . $baseName . '.docx';

            if (!is_writable($tmpDir)) {
                error_log('[Contrato DOCX] Directorio temporal sin permisos de escritura: ' . $tmpDir);
                $respond('error', [
                    'msg' => 'El directorio temporal no permite escritura. Verifique permisos.',
                    'error_details' => 'Directorio temporal: ' . $tmpDir
                ], 500);
            }

            if (!class_exists('\\PhpOffice\\PhpWord\\TemplateProcessor')) {
                $respond('error', [
                    'msg' => 'La librería PhpWord no está disponible en el servidor.',
                    'error_details' => 'Clase PhpOffice\\PhpWord\\TemplateProcessor no encontrada.'
                ], 500);
            }

            try {
                // ========= 7) GENERAR DOCX =========
                if (class_exists('\\PhpOffice\\PhpWord\\Settings')) {
                    \PhpOffice\PhpWord\Settings::setTempDir($tmpDir);
                }

                $template = new \PhpOffice\PhpWord\TemplateProcessor($plantillaPath);
                foreach ($placeholders as $clave => $valor) {
                    $template->setValue($clave, $valor);
                }
                $template->saveAs($docxPath);
                clearstatcache(true, $docxPath);

                if (!is_file($docxPath) || filesize($docxPath) < 2048) {
                    throw new \RuntimeException('DOCX generado vacío o incompleto (posible límite de memoria/permiso).');
                }

                $respond('ok', [
                    'docx'   => 'tmp/' . basename($docxPath),
                    'nombre' => basename($docxPath)
                ]);
            } catch (\Throwable $e) {
                error_log('[Contrato DOCX] Error al generar: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
                $respond('error', [
                    'msg' => 'No se pudo generar el contrato.',
                    'error_details' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString()
                ], 500);
            }
        } catch (\Throwable $unhandled) {
            error_log('[Contrato DOCX] Error inesperado: ' . $unhandled->getMessage());
            $respond('error', [
                'msg' => 'No se pudo generar el contrato.',
                'error_details' => $unhandled->getMessage(),
            ], 500);
        }
    }


    // Actualiza el estatus de múltiples contratos de forma masiva.
    // Recibe por POST un CSV de IDs y el nuevo estatus (0 o 1).
    // Requiere sesión iniciada y token CSRF válido.
    public static function ctrActualizarEstatusMasivo()
    {
        if (!isset($_POST['actualizarEstatusMasivo'])) {
            return;
        }

        // Sesión y CSRF
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            echo 'error-sesion';
            exit;
        }
        if (!self::usuarioPuedeGestionarContratos()) {
            echo 'error-permiso';
            exit;
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo 'error-token';
            exit;
        }

        // Entrada
        $idsCsv = trim($_POST['ids'] ?? '');
        $nuevo = isset($_POST['nuevo_estatus']) ? (int)$_POST['nuevo_estatus'] : -1;

        if ($idsCsv === '' || ($nuevo !== 0 && $nuevo !== 1)) {
            echo 'error';
            exit;
        }

        // Normalizar IDs
        $ids = array_values(array_filter(array_map('intval', explode(',', $idsCsv)), fn($v) => $v > 0));
        if (empty($ids)) {
            echo 'error';
            exit;
        }

        try {
            $ok = ModeloContratos::mdlActualizarEstatusMasivo($ids, $nuevo);
            echo $ok ? 'ok' : 'error';
        } catch (\RuntimeException $ex) {
            echo 'error';
        }
        exit;
    }

    public static function ctrContarContratosPorUsuario(?int $usuarioId = null): array
    {
        try {
            return ModeloContratos::mdlContarContratosPorUsuario($usuarioId);
        } catch (\Throwable $exception) {
            return [
                'total' => 0,
                'activos' => 0,
                'cancelados' => 0,
            ];
        }
    }

    private static function formatearValorGenerico($valor): string
    {
        if ($valor === null) {
            return '';
        }

        if (is_bool($valor)) {
            return $valor ? 'SI' : 'NO';
        }

        if (is_float($valor)) {
            return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.') ?: '0';
        }

        if (is_int($valor)) {
            return (string)$valor;
        }

        if (is_numeric($valor) && preg_match('/^-?\d+\.\d+$/', (string)$valor)) {
            $floatValor = (float)$valor;
            return rtrim(rtrim(number_format($floatValor, 2, '.', ''), '0'), '.') ?: '0';
        }

        if (is_scalar($valor)) {
            $valorString = (string)$valor;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valorString) === 1) {
                $fechaCorta = self::fechaEnFormatoCorto($valorString);
                return $fechaCorta !== '' ? $fechaCorta : $valorString;
            }

            return $valorString;
        }

        return '';
    }

    private static function construirPlaceholdersContrato(array $datos): array
    {
        $registro = is_array($datos['registro'] ?? null) ? $datos['registro'] : [];
        $cliente = is_array($datos['cliente'] ?? null) ? $datos['cliente'] : [];
        $desarrollo = is_array($datos['desarrollo'] ?? null) ? $datos['desarrollo'] : [];
        $contrato = is_array($datos['contrato'] ?? null) ? $datos['contrato'] : [];
        $solicitud = is_array($datos['solicitud'] ?? null) ? $datos['solicitud'] : [];

        $valor = static function (array $fuente, string $clave) {
            $dato = $fuente[$clave] ?? '';
            return is_scalar($dato) || $dato === null ? $dato : '';
        };

        $contratoNormalizado = $contrato;
        if (array_key_exists('entrega_posecion_texto', $contratoNormalizado)) {
            $contratoNormalizado['entrega_posesion_texto'] = $contratoNormalizado['entrega_posecion_texto'];
            unset($contratoNormalizado['entrega_posecion_texto']);
        }
        if (array_key_exists('entrega_posecion', $contratoNormalizado)) {
            $contratoNormalizado['entrega_posesion_texto'] = $contratoNormalizado['entrega_posecion'];
            if (!isset($contratoNormalizado['entrega_posesion'])) {
                $contratoNormalizado['entrega_posesion'] = $contratoNormalizado['entrega_posecion'];
            }
            unset($contratoNormalizado['entrega_posecion']);
        }
        if (array_key_exists('entrega_posecion_date', $contratoNormalizado)) {
            $contratoNormalizado['entrega_posesion_date'] = $contratoNormalizado['entrega_posecion_date'];
            unset($contratoNormalizado['entrega_posecion_date']);
        }
        if (isset($contratoNormalizado['entrega_posesion_date'])
            && (!isset($contratoNormalizado['entrega_posesion']) || $contratoNormalizado['entrega_posesion'] === '')
        ) {
            $contratoNormalizado['entrega_posesion'] = $contratoNormalizado['entrega_posesion_date'];
        }
        if (isset($contratoNormalizado['clausula_c_posecion'])) {
            $contratoNormalizado['clausula_c_posesion'] = $contratoNormalizado['clausula_c_posecion'];
            unset($contratoNormalizado['clausula_c_posecion']);
        }
        if (isset($contratoNormalizado['financiamiento_clusulas'])) {
            $contratoNormalizado['financiamiento_clausulas'] = $contratoNormalizado['financiamiento_clusulas'];
            unset($contratoNormalizado['financiamiento_clusulas']);
        }
        if (isset($contratoNormalizado['superficie']) && is_string($contratoNormalizado['superficie'])) {
            $contratoNormalizado['superficie'] = self::normalizarDecimal($contratoNormalizado['superficie']);
        }

        $contrato = $contratoNormalizado;

        $clienteFechaRegistro = $valor($cliente, 'fecha');
        $clienteFechaRegistroTexto = $valor($cliente, 'fecha_texto');
        if ($clienteFechaRegistroTexto === '' && $clienteFechaRegistro) {
            $clienteFechaRegistroTexto = self::fechaEnFormatoLargo($clienteFechaRegistro);
        }

        $clienteFechaNacimiento = $valor($cliente, 'fecha_nacimiento');
        $clienteFechaNacimientoTexto = $valor($cliente, 'fecha_nacimiento_texto');
        if ($clienteFechaNacimientoTexto === '' && $clienteFechaNacimiento) {
            $clienteFechaNacimientoTexto = self::fechaEnFormatoLargo($clienteFechaNacimiento);
        }

        $entregaPosesionDate = $valor($contrato, 'entrega_posesion_date');
        $entregaPosesionTexto = $valor($contrato, 'entrega_posesion_texto');
        if ($entregaPosesionTexto === '' && $entregaPosesionDate) {
            $entregaPosesionTexto = self::fechaEnFormatoLargo($entregaPosesionDate);
        }
        $entregaPosesionValor = $entregaPosesionDate ?: $valor($contrato, 'entrega_posesion');

        $diceSerUnificado = $valor($cliente, 'dice_ser') ?: $valor($contrato, 'dice_ser');

        $placeholders = [
            'CLIENTE_NOMBRE' => self::formatearValorGenerico($valor($cliente, 'nombre')),
            'CLIENTE_NACIONALIDAD' => self::formatearValorGenerico($valor($cliente, 'nacionalidad')),
            'CLIENTE_FECHA' => self::formatearValorGenerico($clienteFechaRegistro),
            'CLIENTE_GENERO' => self::formatearValorGenerico($valor($cliente, 'genero')),
            'CLIENTE_RFC' => self::formatearValorGenerico($valor($cliente, 'rfc')),
            'CLIENTE_CURP' => self::formatearValorGenerico($valor($cliente, 'curp')),
            'CLIENTE_INE' => self::formatearValorGenerico($valor($cliente, 'ine')),
            'CLIENTE_ESTADO_CIVIL' => self::formatearValorGenerico($valor($cliente, 'estado_civil')),
            'CLIENTE_OCUPACION' => self::formatearValorGenerico($valor($cliente, 'ocupacion')),
            'CLIENTE_TELEFONO' => self::formatearValorGenerico($valor($cliente, 'telefono')),
            'CLIENTE_DOMICILIO' => self::formatearValorGenerico($valor($cliente, 'domicilio')),
            'CLIENTE_EMAIL' => self::formatearValorGenerico($valor($cliente, 'email')),
            'CLIENTE_BENEFICIARIO' => self::formatearValorGenerico($valor($cliente, 'beneficiario')),
            'CLIENTE_EDAD' => self::formatearValorGenerico($valor($cliente, 'edad')),
            'CLIENTE_REFERENCIA' => self::formatearValorGenerico($valor($cliente, 'referencia')),
            'CLIENTE_DICE_SER' => self::formatearValorGenerico($diceSerUnificado),
            'CLIENTE_FECHA_NACIMIENTO' => self::formatearValorGenerico($clienteFechaNacimiento),

            'DESARROLLO_ID' => self::formatearValorGenerico($valor($desarrollo, 'id')),
            'DESARROLLO_NOMBRE' => self::formatearValorGenerico($valor($desarrollo, 'nombre')),
            'DESARROLLO_TIPO_CONTRATO' => self::formatearValorGenerico($valor($desarrollo, 'tipo_contrato')),
            'DESARROLLO_DESCRIPCION' => self::formatearValorGenerico($valor($desarrollo, 'descripcion')),
            'DESARROLLO_SUPERFICIE' => self::formatearValorGenerico($valor($desarrollo, 'superficie')),
            'DESARROLLO_CLAVE_CATASTRAL' => self::formatearValorGenerico($valor($desarrollo, 'clave_catastral')),
            'DESARROLLO_LOTES' => self::formatearValorGenerico($valor($desarrollo, 'lotes_disponibles')),
            'DESARROLLO_PRECIO_LOTE' => self::formatearValorGenerico($valor($desarrollo, 'precio_lote')),
            'DESARROLLO_PRECIO_TOTAL' => self::formatearValorGenerico($valor($desarrollo, 'precio_total')),
            'DESARROLLO_CREATED_AT' => self::formatearValorGenerico($valor($desarrollo, 'created_at')),

            'CONTRATO_FOLIO' => self::formatearValorGenerico($valor($contrato, 'folio')),
            'CONTRATO_MENSUALIDADES' => self::formatearValorGenerico($valor($contrato, 'mensualidades')),
            'CONTRATO_SUPERFICIE_NUM' => self::formatearValorGenerico($valor($contrato, 'superficie')),
            'CONTRATO_SUPERFICIE_LT' => self::formatearValorGenerico($valor($contrato, 'superficie_fixed')),
            'CONTRATO_FRACCION_VENDIDA' => self::formatearValorGenerico($valor($contrato, 'fraccion_vendida')),
            'CONTRATO_ENTREGA_POSESION' => self::formatearValorGenerico($entregaPosesionValor),
            'CONTRATO_ENTREGA_POSESION_TEXTO' => self::formatearValorGenerico($entregaPosesionTexto),
            'CONTRATO_CLAUSULA_C_POSESION' => self::formatearValorGenerico($valor($contrato, 'clausula_c_posesion')),
            'CONTRATO_FECHA_FIRMA' => self::formatearValorGenerico($valor($contrato, 'fecha_firma_contrato')),
            'CONTRATO_COLINDANCIAS' => self::formatearValorGenerico($valor($contrato, 'habitacional_colindancias')),
            'CONTRATO_INICIO_PAGOS' => self::formatearValorGenerico($valor($contrato, 'inicio_pagos')),
            'CONTRATO_OBSERVACIONES' => self::formatearValorGenerico($valor($contrato, 'observaciones') ?: $valor($contrato, 'referencias')),
            'CONTRATO_PRECIO_INMUEBLE' => self::formatearValorGenerico($valor($contrato, 'monto_precio_inmueble_fixed')),
            'CONTRATO_ENGANCHE' => self::formatearValorGenerico($valor($contrato, 'enganche')),
            'CONTRATO_SALDO' => self::formatearValorGenerico($valor($contrato, 'saldo_pago_fixed')),
            'CONTRATO_PARCIALIDADES_ANUALES' => self::formatearValorGenerico($valor($contrato, 'parcialidades_anuales')),
            'CONTRATO_PENALIZACION' => self::formatearValorGenerico($valor($contrato, 'penalizacion_10_fixed')),
            'CONTRATO_PAGO_MENSUAL' => self::formatearValorGenerico($valor($contrato, 'pago_mensual_fixed')),
            'CONTRATO_FECHA_N' => self::formatearValorGenerico($valor($contrato, 'fecha_contrato')),
            'CONTRATO_FECHA_T' => self::formatearValorGenerico($valor($contrato, 'fecha_contrato_fixed')),
            'CONTRATO_INICIO_PAGO' => self::formatearValorGenerico($valor($contrato, 'rango_pago_inicio')),
            'CONTRATO_FIN_PAGO' => self::formatearValorGenerico($valor($contrato, 'rango_pago_fin')),
            'CONTRATO_RANGO' => self::formatearValorGenerico($valor($contrato, 'rango_pago')),
            'CONTRATO_DIA_INICIO' => self::formatearValorGenerico($valor($contrato, 'dia_inicio')),
            'CONTRATO_DICE_SER' => self::formatearValorGenerico($diceSerUnificado),
        ];

        $placeholders['CLIENTE_FECHA_TEXTO'] = self::formatearValorGenerico($clienteFechaRegistroTexto);
        $placeholders['CLIENTE_FECHA_NACIMIENTO_TEXTO'] = self::formatearValorGenerico($clienteFechaNacimientoTexto);

        $fechaFirma = $valor($contrato, 'fecha_firma_contrato_date') ?: $valor($contrato, 'fecha_firma_contrato');
        $placeholders['CONTRATO_FECHA_FIRMA_TEXTO'] = is_string($fechaFirma) && $fechaFirma !== ''
            ? self::fechaEnFormatoLargo($fechaFirma)
            : '';

        $fechaContrato = $valor($contrato, 'fecha_contrato_date') ?: $valor($contrato, 'fecha_contrato');
        $placeholders['CONTRATO_FECHA_N_TEXTO'] = is_string($fechaContrato) && $fechaContrato !== ''
            ? self::fechaEnFormatoLargo($fechaContrato)
            : '';

        $placeholders['DESARROLLO_PRECIO_LOTE_VALOR'] = self::normalizarMonto($valor($desarrollo, 'precio_lote'));
        $placeholders['DESARROLLO_PRECIO_TOTAL_VALOR'] = self::normalizarMonto($valor($desarrollo, 'precio_total'));
        $placeholders['CONTRATO_PRECIO_INMUEBLE_VALOR'] = self::normalizarMonto($valor($contrato, 'monto_precio_inmueble'));
        $placeholders['CONTRATO_ENGANCHE_VALOR'] = self::normalizarMonto($valor($contrato, 'enganche'));
        $placeholders['CONTRATO_SALDO_VALOR'] = self::normalizarMonto($valor($contrato, 'saldo_pago'));
        $placeholders['CONTRATO_PAGO_MENSUAL_VALOR'] = self::normalizarMonto($valor($contrato, 'pago_mensual'));

        $estatus = $valor($contrato, 'estatus');
        if ($estatus === '' && isset($registro['estatus'])) {
            $estatus = $registro['estatus'];
        }
        $placeholders['CONTRATO_ESTATUS'] = self::formatearValorGenerico($estatus);
        $placeholders['CONTRATO_ESTADO'] = strtoupper(self::formatearValorGenerico($valor($contrato, 'estado')));
        $placeholders['CONTRATO_USUARIO'] = strtoupper(self::formatearValorGenerico($valor($contrato, 'nombre_corto')));
        $placeholders['CONTRATO_ID'] = self::formatearValorGenerico($valor($registro, 'id'));
        $placeholders['CONTRATO_CLIENTE_ID'] = self::formatearValorGenerico($valor($registro, 'cliente_id'));
        $placeholders['CONTRATO_DESARROLLO_ID'] = self::formatearValorGenerico($valor($registro, 'desarrollo_id'));
        $placeholders['CONTRATO_GENERADO_EN'] = self::fechaActualConHora();
        $placeholders['CONTRATO_GENERADO_EN_TEXTO'] = self::fechaEnFormatoLargo(self::fechaActualFormateada());

        $clausulas = $valor($contrato, 'financiamiento_clausulas');
        if ($clausulas === '') {
            $clausulas = $valor($contrato, 'financiamiento_clusulas');
        }
        $placeholders['CONTRATO_CLAUSULAS'] = self::formatearValorGenerico($clausulas);

        $omitidos = [
            'CLIENTE_FECHA',
            'CLIENTE_FECHA_TEXTO',
            'CLIENTE_FECHA_NACIMIENTO',
            'CLIENTE_FECHA_NACIMIENTO_TEXTO',
            'CONTRATO_ENTREGA_POSESION',
            'CONTRATO_ENTREGA_POSESION_TEXTO',
            'CONTRATO_ENTREGA_POSESION_DATE',
            'CONTRATO_ENTREGA_POSESION_DATE_TEXTO',
            'CONTRATO_ENTREGA_POSECION',
            'CONTRATO_ENTREGA_POSECION_TEXTO',
            'CONTRATO_CLAUSULA_C_POSESION',
            'CONTRATO_CLAUSULA_C_POSECION',
            'CONTRATO_CLAUSULAS',
        ];

        $agregarGenericos = static function (array &$destino, array $fuente, string $prefijo) use (&$agregarGenericos, $omitidos): void {
            foreach ($fuente as $campo => $valorCampo) {
                $clave = $prefijo . strtoupper((string)$campo);

                if (in_array($clave, $omitidos, true)) {
                    continue;
                }

                if (is_array($valorCampo)) {
                    if (!array_key_exists($clave, $destino)) {
                        $destino[$clave] = '';
                    }
                    $agregarGenericos($destino, $valorCampo, $clave . '_');
                    continue;
                }

                if ($valorCampo === null) {
                    if (!array_key_exists($clave, $destino)) {
                        $destino[$clave] = '';
                    }
                    continue;
                }

                if (!array_key_exists($clave, $destino)) {
                    $destino[$clave] = self::formatearValorGenerico($valorCampo);
                }

                if (!array_key_exists($clave . '_TEXTO', $destino)) {
                    $valorString = (string)$valorCampo;
                    if ($valorString !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valorString) === 1) {
                        $destino[$clave . '_TEXTO'] = self::fechaEnFormatoLargo($valorString);
                    }
                }
            }
        };

        $agregarGenericos($placeholders, $cliente, 'CLIENTE_');
        $agregarGenericos($placeholders, $desarrollo, 'DESARROLLO_');
        $agregarGenericos($placeholders, $contrato, 'CONTRATO_');
        $agregarGenericos($placeholders, $registro, 'CONTRATO_REGISTRO_');
        $agregarGenericos($placeholders, $solicitud, 'SOLICITUD_');

        return $placeholders;
    }
}
