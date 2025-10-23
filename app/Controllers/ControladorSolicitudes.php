<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\ControladorNotificaciones;
use App\Models\ModeloDesarrollos;
use App\Models\ModeloPlantillasSolicitudes;
use App\Models\ModeloSolicitudes;
use App\Models\ModeloUsuarios;
use App\Models\ModeloVariables;
use DateTimeImmutable;
use DateTimeZone;

class ControladorSolicitudes
{
    public const PLACEHOLDER_OPCIONAL = '----------';

    private const ESTADOS_PERMITIDOS = ['borrador','enviada','en_revision','aprobada','cancelada'];

    /**
     * Cache local para evitar recalcular los campos opcionales de la solicitud.
     *
     * @var array<string, string>|null
     */
    private static ?array $camposOpcionalesCache = null;
    private static ?DateTimeZone $zonaAplicacion = null;

    private static function zonaHorariaAplicacion(): DateTimeZone
    {
        if (self::$zonaAplicacion === null) {
            self::$zonaAplicacion = new DateTimeZone('America/Mexico_City');
        }

        return self::$zonaAplicacion;
    }

    private static function ahoraLocal(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::zonaHorariaAplicacion());
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

    private static function formatearFechaAlmacen(DateTimeImmutable $fecha): string
    {
        return $fecha->setTimezone(self::zonaHorariaAplicacion())->format('d-m-Y');
    }

    private static function fechaActualFormateada(): string
    {
        return self::formatearFechaAlmacen(self::ahoraLocal());
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

    private static function usuarioIdActual(): int
    {
        return (int)($_SESSION['id'] ?? 0);
    }

    private static function usuarioPuedeGestionarSolicitudes(): bool
    {
        return self::usuarioTieneRol(['moderator', 'senior', 'owner']);
    }

    private static function validarPasswordUsuarioActual(string $password): bool
    {
        $passwordPlano = (string)$password;
        if (trim($passwordPlano) === '') {
            return false;
        }

        $usuarioId = self::usuarioIdActual();
        if ($usuarioId <= 0) {
            return false;
        }

        $usuario = ModeloUsuarios::mdlObtenerUsuarioPorId($usuarioId);
        if (!$usuario || empty($usuario['password'])) {
            return false;
        }

        return password_verify($passwordPlano, (string)$usuario['password']);
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

    /**
     * Guarda una solicitud nueva o existente.
     *
     * @return array|null Resultado con claves tipo, mensaje y opcionalmente detalle.
     */
    public static function ctrGuardarSolicitud(): ?array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_GET['accion'] ?? '') !== 'guardarSolicitud') {
            return null;
        }

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return ['tipo' => 'error', 'mensaje' => 'Debe iniciar sesión para continuar.'];
        }

        if (!isset($_POST['csrf_token']) || ($_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? ''))) {
            return ['tipo' => 'error', 'mensaje' => 'Token de seguridad inválido.'];
        }

        $id = isset($_POST['solicitud_id']) ? (int)$_POST['solicitud_id'] : 0;
        $campos = self::camposSolicitud();
        $datosSolicitud = [];

        foreach ($campos as $campo => $tipo) {
            $valor = $_POST[$campo] ?? null;
            $datosSolicitud[$campo] = self::sanitizarValor($valor, $tipo);
        }

        $folioCapturado = isset($datosSolicitud['folio']) ? (string)$datosSolicitud['folio'] : '';
        if ($folioCapturado !== '') {
            $folioNormalizado = preg_replace('/[^A-Za-z0-9_-]/u', '', function_exists('mb_strtoupper') ? mb_strtoupper($folioCapturado, 'UTF-8') : strtoupper($folioCapturado));
            $datosSolicitud['folio'] = $folioNormalizado !== '' ? $folioNormalizado : null;
        } else {
            $datosSolicitud['folio'] = null;
        }

        if ($datosSolicitud['folio'] !== null && ModeloSolicitudes::mdlExisteFolio($datosSolicitud['folio'], $id > 0 ? $id : null)) {
            return ['tipo' => 'error', 'mensaje' => 'El folio ingresado ya está registrado en otra solicitud.'];
        }

        $normalizarTexto = static function (?string $texto): ?string {
            if ($texto === null || $texto === '') {
                return null;
            }

            $texto = trim($texto);
            if ($texto === '') {
                return null;
            }

            return function_exists('mb_strtoupper') ? mb_strtoupper($texto, 'UTF-8') : strtoupper($texto);
        };

        $nacionalidadId = (int)($datosSolicitud['nacionalidad_id'] ?? 0);
        if ($nacionalidadId > 0) {
            $variable = ModeloVariables::mdlObtenerVariablePorId($nacionalidadId);
            if ($variable && ($variable['tipo'] ?? '') === 'nacionalidad') {
                $datosSolicitud['nacionalidad_identificador'] = $variable['identificador'] ?? null;
                $datosSolicitud['nacionalidad'] = $normalizarTexto($variable['nombre'] ?? null);
            } else {
                $datosSolicitud['nacionalidad_id'] = null;
                $datosSolicitud['nacionalidad_identificador'] = null;
                $datosSolicitud['nacionalidad'] = null;
            }
        } else {
            $datosSolicitud['nacionalidad_id'] = null;
            $datosSolicitud['nacionalidad_identificador'] = null;
            $datosSolicitud['nacionalidad'] = null;
        }

        $desarrolloId = (int)($datosSolicitud['desarrollo_id'] ?? 0);
        if ($desarrolloId > 0) {
            $desarrollo = ModeloDesarrollos::mdlMostrarDesarrolloPorId($desarrolloId);
            if ($desarrollo) {
                $datosSolicitud['desarrollo'] = $desarrollo['nombre'] ?? '';
                $datosSolicitud['desarrollo_tipo_contrato'] = $desarrollo['tipo_contrato'] ?? '';
            } else {
                $datosSolicitud['desarrollo_id'] = null;
                $datosSolicitud['desarrollo'] = null;
                $datosSolicitud['desarrollo_tipo_contrato'] = null;
            }
        } else {
            $datosSolicitud['desarrollo_id'] = null;
            $datosSolicitud['desarrollo'] = null;
            $datosSolicitud['desarrollo_tipo_contrato'] = null;
        }

        $datosSolicitud = self::aplicarPlaceholdersOpcionales($datosSolicitud);

        $usuarioIdSesion = self::usuarioIdActual();
        $esGestor = self::usuarioPuedeGestionarSolicitudes();

        if ($id > 0) {
            $actual = ModeloSolicitudes::mdlObtenerSolicitudPorId($id);
            if (!$actual) {
                return ['tipo' => 'error', 'mensaje' => 'La solicitud indicada no existe.'];
            }
            if (!empty($actual['contrato_id'])) {
                return ['tipo' => 'error', 'mensaje' => 'La solicitud ya cuenta con un contrato y no puede modificarse.'];
            }
            if ($esGestor) {
                if (($actual['estado'] ?? '') === 'cancelada') {
                    return ['tipo' => 'error', 'mensaje' => 'No es posible editar solicitudes canceladas.'];
                }
            } elseif ((int)$actual['usuario_id'] !== $usuarioIdSesion) {
                return ['tipo' => 'error', 'mensaje' => 'No tiene permisos para editar esta solicitud.'];
            } elseif (($actual['estado'] ?? '') !== 'borrador') {
                return ['tipo' => 'error', 'mensaje' => 'Solo puede editar solicitudes en borrador.'];
            }

            $resultado = ModeloSolicitudes::mdlActualizarSolicitud($id, $datosSolicitud);
            if ($resultado) {
                return ['tipo' => 'success', 'mensaje' => 'Solicitud actualizada correctamente.'];
            }

            return ['tipo' => 'error', 'mensaje' => 'No fue posible actualizar la solicitud.'];
        }

        $nuevoId = ModeloSolicitudes::mdlCrearSolicitud($datosSolicitud, 'borrador', (int)($_SESSION['id'] ?? 0));
        if ($nuevoId > 0) {
            return ['tipo' => 'success', 'mensaje' => 'Solicitud guardada como borrador.'];
        }

        return ['tipo' => 'error', 'mensaje' => 'No fue posible guardar la solicitud.'];
    }

    /**
     * Cambiar el estado de una solicitud.
     */
    public static function ctrCambiarEstado(): ?array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cambiar_estado_solicitud'])) {
            return null;
        }

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return ['tipo' => 'error', 'mensaje' => 'Debe iniciar sesión.'];
        }

        if (!isset($_POST['csrf_token']) || ($_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? ''))) {
            return ['tipo' => 'error', 'mensaje' => 'Token de seguridad inválido.'];
        }

        $id = (int)($_POST['solicitud_id'] ?? 0);
        $nuevoEstado = $_POST['nuevo_estado'] ?? '';
        if (!in_array($nuevoEstado, self::ESTADOS_PERMITIDOS, true)) {
            return ['tipo' => 'error', 'mensaje' => 'Estado no permitido.'];
        }

        $solicitud = ModeloSolicitudes::mdlObtenerSolicitudPorId($id);
        if (!$solicitud) {
            return ['tipo' => 'error', 'mensaje' => 'La solicitud no existe.'];
        }

        $usuarioId = self::usuarioIdActual();
        $esGestor = self::usuarioPuedeGestionarSolicitudes();
        $permisoActual = strtolower(trim((string)($_SESSION['permission'] ?? '')));

        $contratoId = (int)($solicitud['contrato_id'] ?? 0);
        $tieneContrato = $contratoId > 0;
        $puedeCancelarConContrato = in_array($permisoActual, ['owner', 'admin'], true);

        if ($tieneContrato) {
            if ($nuevoEstado === 'cancelada') {
                if (!$puedeCancelarConContrato) {
                    return ['tipo' => 'error', 'mensaje' => 'Solo OWNER o ADMIN pueden cancelar solicitudes con contrato.'];
                }
            } else {
                return ['tipo' => 'error', 'mensaje' => 'No es posible cambiar el estado de una solicitud que ya cuenta con contrato.'];
            }
        }

        if ($nuevoEstado === 'enviada') {
            if ((int)$solicitud['usuario_id'] !== $usuarioId && !$esGestor) {
                return ['tipo' => 'error', 'mensaje' => 'Solo el autor puede enviar la solicitud.'];
            }
            if ($solicitud['estado'] !== 'borrador') {
                return ['tipo' => 'error', 'mensaje' => 'Solo es posible enviar solicitudes en borrador.'];
            }
            if ($solicitud['estado'] === 'cancelada') {
                return ['tipo' => 'error', 'mensaje' => 'No es posible enviar una solicitud cancelada.'];
            }
            $camposFaltantes = self::validarCamposObligatoriosEnvio($solicitud);
            if (!empty($camposFaltantes)) {
                return [
                    'tipo' => 'error',
                    'mensaje' => 'No puedes enviar la solicitud con campos faltantes. Completa la información requerida para continuar.',
                    'codigo' => 'campos_obligatorios_pendientes',
                    'solicitud_id' => $id,
                    'campos_faltantes' => $camposFaltantes,
                ];
            }
            $datosSolicitudNormalizados = [];
            foreach (self::camposSolicitud() as $campo => $tipo) {
                $datosSolicitudNormalizados[$campo] = $solicitud[$campo] ?? null;
            }
            $datosSolicitudConPlaceholders = self::aplicarPlaceholdersOpcionales($datosSolicitudNormalizados);
            if ($datosSolicitudConPlaceholders !== $datosSolicitudNormalizados) {
                ModeloSolicitudes::mdlActualizarSolicitud($id, $datosSolicitudConPlaceholders);
                foreach ($datosSolicitudConPlaceholders as $campo => $valor) {
                    $solicitud[$campo] = $valor;
                }
            }
        } else {
            if (!$esGestor) {
                return ['tipo' => 'error', 'mensaje' => 'No tiene permisos para cambiar el estado seleccionado.'];
            }
            if ($solicitud['estado'] === 'cancelada') {
                return ['tipo' => 'error', 'mensaje' => 'Las solicitudes canceladas no pueden modificarse.'];
            }
            if ($solicitud['estado'] === $nuevoEstado) {
                return ['tipo' => 'info', 'mensaje' => 'La solicitud ya se encuentra en el estado seleccionado.'];
            }
            if ($nuevoEstado === 'borrador' && $solicitud['estado'] === 'borrador') {
                return ['tipo' => 'info', 'mensaje' => 'La solicitud ya está en borrador.'];
            }
            if ($nuevoEstado === 'borrador' && !in_array($solicitud['estado'], ['enviada', 'en_revision'], true)) {
                return ['tipo' => 'error', 'mensaje' => 'Solo es posible regresar a borrador solicitudes enviadas o en revisión.'];
            }
        }

        $motivoCancelacion = null;
        if ($nuevoEstado === 'cancelada') {
            $passwordConfirmacion = (string)($_POST['password_confirmacion'] ?? '');
            if (trim($passwordConfirmacion) === '') {
                return ['tipo' => 'error', 'mensaje' => 'Ingrese su contraseña para confirmar la cancelación de la solicitud.'];
            }
            if (!self::validarPasswordUsuarioActual($passwordConfirmacion)) {
                return ['tipo' => 'error', 'mensaje' => 'La contraseña proporcionada no es válida.'];
            }
            $motivoCancelacion = trim((string)($_POST['motivo_cancelacion'] ?? ''));
            if ($motivoCancelacion === '') {
                return ['tipo' => 'error', 'mensaje' => 'Debe indicar el motivo de la cancelación de la solicitud.'];
            }
            if (function_exists('mb_strlen')) {
                if (mb_strlen($motivoCancelacion, 'UTF-8') < 5) {
                    return ['tipo' => 'error', 'mensaje' => 'Describa con mayor detalle el motivo de la cancelación.'];
                }
                $motivoCancelacion = mb_substr($motivoCancelacion, 0, 500, 'UTF-8');
            } else {
                if (strlen($motivoCancelacion) < 5) {
                    return ['tipo' => 'error', 'mensaje' => 'Describa con mayor detalle el motivo de la cancelación.'];
                }
                $motivoCancelacion = substr($motivoCancelacion, 0, 500);
            }
        }

        $motivoRetorno = null;
        if ($esGestor && $nuevoEstado === 'borrador') {
            $motivoRetorno = trim((string)($_POST['motivo_regreso'] ?? ''));
            if ($motivoRetorno === '') {
                return ['tipo' => 'error', 'mensaje' => 'Debe indicar el motivo para regresar la solicitud a borrador.'];
            }
            if (function_exists('mb_strlen')) {
                if (mb_strlen($motivoRetorno, 'UTF-8') < 5) {
                    return ['tipo' => 'error', 'mensaje' => 'Describa con mayor detalle el motivo del regreso.'];
                }
                $motivoRetorno = mb_substr($motivoRetorno, 0, 500, 'UTF-8');
            } else {
                if (strlen($motivoRetorno) < 5) {
                    return ['tipo' => 'error', 'mensaje' => 'Describa con mayor detalle el motivo del regreso.'];
                }
                $motivoRetorno = substr($motivoRetorno, 0, 500);
            }
        }

        $motivoActualizar = null;
        $usuarioActualizar = null;

        if ($nuevoEstado === 'cancelada') {
            $motivoActualizar = $motivoCancelacion;
            $usuarioActualizar = $usuarioId > 0 ? $usuarioId : null;
        } elseif ($esGestor && $nuevoEstado === 'borrador') {
            $motivoActualizar = $motivoRetorno;
            $usuarioActualizar = $usuarioId > 0 ? $usuarioId : null;
        }

        $resultado = ModeloSolicitudes::mdlActualizarEstado($id, $nuevoEstado, $motivoActualizar, $usuarioActualizar);
        if ($resultado) {
            $datosSolicitud = null;
            if (in_array($nuevoEstado, ['enviada', 'aprobada', 'en_revision', 'cancelada', 'borrador'], true)) {
                $datosSolicitud = ModeloSolicitudes::mdlObtenerSolicitudPorId($id) ?? $solicitud;
                $datosSolicitud['id'] = $id;
                if ($nuevoEstado === 'borrador' && $motivoRetorno !== null) {
                    $datosSolicitud['motivo_retorno'] = $motivoRetorno;
                }
            }
            if ($nuevoEstado === 'enviada' && $datosSolicitud) {
                ControladorNotificaciones::registrarEnvioSolicitud($datosSolicitud);
                ControladorNotificaciones::registrarActualizacionAutor($datosSolicitud, 'enviada');
            }
            if ($datosSolicitud && $nuevoEstado !== 'enviada') {
                if ($nuevoEstado === 'aprobada') {
                    ControladorNotificaciones::registrarAprobacionSolicitud($datosSolicitud);
                } elseif (in_array($nuevoEstado, ['en_revision', 'cancelada', 'borrador'], true)) {
                    ControladorNotificaciones::registrarActualizacionAutor($datosSolicitud, $nuevoEstado);
                }
            }
            $mensaje = match ($nuevoEstado) {
                'enviada' => 'Solicitud enviada correctamente.',
                'en_revision' => 'Solicitud marcada en revisión.',
                'aprobada' => 'Solicitud aprobada.',
                'cancelada' => 'Solicitud cancelada.',
                'borrador' => 'Solicitud devuelta a borrador.',
                default => 'Solicitud actualizada.'
            };
            return ['tipo' => 'success', 'mensaje' => $mensaje];
        }

        return ['tipo' => 'error', 'mensaje' => 'No fue posible actualizar el estado.'];
    }

    private static function camposOpcionales(): array
    {
        if (self::$camposOpcionalesCache !== null) {
            return self::$camposOpcionalesCache;
        }

        $todos = self::camposSolicitud();
        $obligatorios = array_flip(self::camposObligatoriosEnvio());
        $opcionales = [];

        foreach ($todos as $campo => $tipo) {
            if (!isset($obligatorios[$campo])) {
                $opcionales[$campo] = $tipo;
            }
        }

        self::$camposOpcionalesCache = $opcionales;

        return $opcionales;
    }

    private static function aplicarPlaceholdersOpcionales(array $datos): array
    {
        $opcionales = self::camposOpcionales();

        foreach ($opcionales as $campo => $_tipo) {
            if (!array_key_exists($campo, $datos)) {
                continue;
            }

            $valor = $datos[$campo];

            if (is_bool($valor)) {
                continue;
            }

            if (is_string($valor)) {
                $valor = trim($valor);
            }

            $estaVacio = $valor === null || $valor === '';

            if (!$estaVacio && is_string($valor) && self::esPlaceholderOpcional($valor)) {
                $estaVacio = true;
            }

            if (!$estaVacio && in_array($campo, ['pago_anual', 'identificacion_numero'], true)) {
                $valorEvaluar = $valor;
                if (is_string($valorEvaluar)) {
                    $valorEvaluar = str_replace(',', '.', trim($valorEvaluar));
                }
                if (is_numeric($valorEvaluar) && abs((float)$valorEvaluar) < 0.0000001) {
                    $estaVacio = true;
                }
            }

            if ($estaVacio) {
                $datos[$campo] = self::PLACEHOLDER_OPCIONAL;
                continue;
            }

            if (is_string($valor)) {
                $datos[$campo] = $valor;
            }
        }

        return $datos;
    }

    public static function esPlaceholderOpcional($valor): bool
    {
        if (!is_string($valor)) {
            return false;
        }

        return trim($valor) === self::PLACEHOLDER_OPCIONAL;
    }

    public static function valorParaFormulario($valor): string
    {
        if ($valor === null) {
            return '';
        }

        if (self::esPlaceholderOpcional($valor)) {
            return '';
        }

        if (is_string($valor)) {
            $texto = trim($valor);
            if ($texto !== '' && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) || preg_match('/^\d{1,2}[\/-]\d{1,2}[\/-]\d{4}$/', $texto))) {
                $fechaObj = self::parsearFecha($texto);
                if ($fechaObj) {
                    return $fechaObj->format('Y-m-d');
                }
            }

            return $texto;
        }

        if (is_scalar($valor)) {
            return (string)$valor;
        }

        return '';
    }

    /**
     * Obtener listado de solicitudes según el contexto del usuario.
     */
    public static function ctrListarSolicitudes(
        bool $verCanceladas = false,
        ?int $solicitudId = null,
        bool $incluirBorradores = false,
        array $filtros = []
    ): array
    {
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return [];
        }

        $esGestor = self::usuarioPuedeGestionarSolicitudes();
        $usuarioActualId = self::usuarioIdActual();
        $usuarioId = $esGestor ? null : $usuarioActualId;

        if ($solicitudId !== null && $solicitudId > 0) {
            $solicitud = self::ctrObtenerSolicitudPorId($solicitudId);
            if (!$solicitud) {
                return [];
            }
            if (!$esGestor && (int)($solicitud['usuario_id'] ?? 0) !== $usuarioId) {
                return [];
            }

            return [$solicitud];
        }

        $estadoFiltro = null;
        $estadosPermitidos = array_merge(['todos', 'activos'], self::ESTADOS_PERMITIDOS);
        if (isset($filtros['estado'])) {
            $estadoValor = strtolower(trim((string)$filtros['estado']));
            if (in_array($estadoValor, $estadosPermitidos, true)) {
                $estadoFiltro = $estadoValor;
            }
        }

        $propietarioFiltro = 'todos';
        if ($esGestor) {
            $propietarioValor = isset($filtros['propietario']) ? strtolower(trim((string)$filtros['propietario'])) : 'todos';
            if (!in_array($propietarioValor, ['todos', 'propios', 'otros'], true)) {
                $propietarioValor = 'todos';
            }
            $propietarioFiltro = $propietarioValor;
        }

        if (!$esGestor) {
            $verCanceladas = false;
        } elseif ($estadoFiltro === 'cancelada') {
            $verCanceladas = true;
        }

        $soloBorradoresUsuarioId = null;
        if ($incluirBorradores) {
            if ($esGestor) {
                if ($propietarioFiltro !== 'otros') {
                    $soloBorradoresUsuarioId = $usuarioActualId;
                }
            } else {
                $soloBorradoresUsuarioId = $usuarioActualId;
            }
        }

        $filtrosConsulta = [];
        if ($estadoFiltro !== null) {
            $filtrosConsulta['estado'] = $estadoFiltro;
        }
        if ($esGestor) {
            $filtrosConsulta['propietario'] = $propietarioFiltro;
            $filtrosConsulta['usuario_actual_id'] = $usuarioActualId;
        }

        return ModeloSolicitudes::mdlObtenerSolicitudes(
            $usuarioId,
            $verCanceladas,
            $incluirBorradores,
            $soloBorradoresUsuarioId,
            $filtrosConsulta
        );
    }

    /**
     * Obtener una solicitud individual verificando permisos básicos.
     */
    public static function ctrObtenerSolicitudPorId(int $id): ?array
    {
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return null;
        }
        $solicitud = ModeloSolicitudes::mdlObtenerSolicitudPorId($id);
        if (!$solicitud) {
            return null;
        }
        if (self::usuarioPuedeGestionarSolicitudes()) {
            return $solicitud;
        }
        if ((int)$solicitud['usuario_id'] !== (int)($_SESSION['id'] ?? 0)) {
            return null;
        }
        return $solicitud;
    }

    public static function ctrContarSolicitudesPorEstado(?int $usuarioId = null): array
    {
        return ModeloSolicitudes::mdlContarSolicitudesPorEstado($usuarioId);
    }

    /**
     * Catálogo de campos disponibles en la tabla de solicitudes.
     */
    private static function camposSolicitud(): array
    {
        return [
            'folio' => 'string',
            'fecha' => 'date',
            'fecha_firma' => 'date',
            'nombre_completo' => 'string',
            'nacionalidad_id' => 'int',
            'nacionalidad_identificador' => 'string',
            'nacionalidad' => 'upper',
            'fecha_nacimiento' => 'date',
            'edad_actual' => 'int',
            'identificacion' => 'string',
            'identificacion_numero' => 'string',
            'idmex' => 'string',
            'curp' => 'upper',
            'rfc' => 'upper',
            'celular' => 'phone',
            'telefono' => 'phone',
            'email' => 'email',
            'domicilio' => 'string',
            'estado_civil' => 'string',
            'regimen' => 'string',
            'ocupacion' => 'string',
            'empresa' => 'string',
            'testigo_contrato' => 'string',
            'celular_testigo_contrato' => 'phone',
            'nombre_referencia_1' => 'string',
            'celular_referencia_1' => 'phone',
            'nombre_referencia_2' => 'string',
            'celular_referencia_2' => 'phone',
            'beneficiario' => 'string',
            'edad_beneficiario' => 'int',
            'parentesco_beneficiario' => 'string',
            'celular_beneficiario' => 'phone',
            'albacea_activo' => 'bool',
            'albacea_nombre' => 'string',
            'albacea_edad' => 'int',
            'albacea_parentesco' => 'string',
            'albacea_celular' => 'phone',
            'desarrollo_id' => 'int',
            'desarrollo' => 'string',
            'desarrollo_tipo_contrato' => 'string',
            'ubicacion' => 'string',
            'lote_manzana' => 'string',
            'superficie' => 'string',
            'deslinde' => 'string',
            'costo_total' => 'float',
            'enganche' => 'float',
            'saldo' => 'float',
            'plazo_mensualidades' => 'int',
            'apartado' => 'float',
            'complemento_enganche' => 'float',
            'fecha_liquidacion_enganche' => 'date',
            'pago_mensual' => 'float',
            'fecha_pago_mensual' => 'date',
            'usa_pago_anual' => 'bool',
            'pago_anual' => 'float',
            'fecha_pago_anual' => 'date',
            'plazo_anual' => 'int',
        ];
    }

    private static function camposObligatoriosEnvio(): array
    {
        return [
            'fecha',
            'fecha_firma',
            'nombre_completo',
            'nacionalidad_id',
            'fecha_nacimiento',
            'edad_actual',
            'identificacion',
            'curp',
            'rfc',
            'celular',
            'email',
            'domicilio',
            'estado_civil',
            'ocupacion',
            'beneficiario',
            'edad_beneficiario',
            'parentesco_beneficiario',
            'desarrollo_id',
            'ubicacion',
            'lote_manzana',
            'superficie',
            'costo_total',
            'enganche',
            'saldo',
            'plazo_mensualidades',
            'apartado',
            'complemento_enganche',
            'pago_mensual',
            'fecha_pago_mensual',
            'testigo_contrato',
            'celular_testigo_contrato',
            'nombre_referencia_1',
            'celular_referencia_1',
            'nombre_referencia_2',
            'celular_referencia_2',
        ];
    }

    private static function validarCamposObligatoriosEnvio(array $solicitud): array
    {
        $faltantes = [];
        $esValorVacio = static function ($valor): bool {
            if ($valor === null) {
                return true;
            }
            if (is_string($valor)) {
                $texto = trim($valor);
                if ($texto === '' || self::esPlaceholderOpcional($texto)) {
                    return true;
                }
            }

            return false;
        };

        foreach (self::camposObligatoriosEnvio() as $campo) {
            $valor = $solicitud[$campo] ?? null;

            if ($campo === 'nacionalidad_id' || $campo === 'desarrollo_id') {
                if ($valor === null || (int)$valor <= 0) {
                    $faltantes[] = $campo;
                }
                continue;
            }

            if ($esValorVacio($valor)) {
                $faltantes[] = $campo;
            }
        }

        $tipoIdentificacion = strtoupper(trim((string)($solicitud['identificacion'] ?? '')));
        $numeroIdentificacion = $solicitud['identificacion_numero'] ?? null;
        $idmex = $solicitud['idmex'] ?? null;
        $numeroVacio = $esValorVacio($numeroIdentificacion);
        $idmexVacio = $esValorVacio($idmex);

        if ($tipoIdentificacion === 'INE') {
            if ($idmexVacio) {
                $faltantes[] = 'idmex';
            }
        } elseif (in_array($tipoIdentificacion, ['PASAPORTE', 'CEDULA PROFESIONAL'], true)) {
            if ($numeroVacio) {
                $faltantes[] = 'identificacion_numero';
            }
        } else {
            if ($numeroVacio && $idmexVacio) {
                $faltantes[] = 'identificacion_numero';
            }
        }

        $usaPagoAnualRaw = $solicitud['usa_pago_anual'] ?? false;
        $usaPagoAnual = filter_var($usaPagoAnualRaw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($usaPagoAnual === null) {
            $usaPagoAnual = in_array((string)$usaPagoAnualRaw, ['1', 'true', 'si', 'sí', 'on'], true);
        }

        if ($usaPagoAnual) {
            foreach (['pago_anual', 'fecha_pago_anual', 'plazo_anual'] as $campoPagoAnual) {
                $valor = $solicitud[$campoPagoAnual] ?? null;
                if ($esValorVacio($valor)) {
                    if (!in_array($campoPagoAnual, $faltantes, true)) {
                        $faltantes[] = $campoPagoAnual;
                    }
                }
            }
        }

        $albaceaActivoRaw = $solicitud['albacea_activo'] ?? false;
        $albaceaActivo = filter_var($albaceaActivoRaw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($albaceaActivo === null) {
            $albaceaActivo = in_array((string)$albaceaActivoRaw, ['1', 'true', 'si', 'sí', 'on'], true);
        }
        if ($albaceaActivo) {
            foreach (['albacea_nombre', 'albacea_edad', 'albacea_parentesco', 'albacea_celular'] as $campoAlbacea) {
                $valor = $solicitud[$campoAlbacea] ?? null;
                if ($esValorVacio($valor)) {
                    $faltantes[] = $campoAlbacea;
                }
            }
        }

        $normalizados = [];
        foreach ($faltantes as $campo) {
            $normalizado = strtolower((string)$campo);
            $normalizado = preg_replace('/[^a-z0-9_]/', '', $normalizado);
            if ($normalizado !== '' && !in_array($normalizado, $normalizados, true)) {
                $normalizados[] = $normalizado;
            }
        }

        return $normalizados;
    }

    private static function sanitizarValor($valor, string $tipo)
    {
        if ($valor === null) {
            return null;
        }
        if (is_string($valor)) {
            $valor = trim($valor);
            if ($valor === '') {
                return null;
            }
        }
        switch ($tipo) {
            case 'date':
                $fechaObj = self::parsearFecha((string)$valor);
                return $fechaObj ? self::formatearFechaAlmacen($fechaObj) : null;
            case 'int':
                return is_numeric($valor) ? intval($valor) : null;
            case 'float':
                $limpio = preg_replace('/[^0-9.]/', '', (string)$valor);
                return $limpio === '' ? null : round((float)$limpio, 2);
            case 'email':
                $email = filter_var(trim((string)$valor), FILTER_VALIDATE_EMAIL);
                return $email ?: null;
            case 'phone':
                $soloDigitos = preg_replace('/[^0-9+]/', '', (string)$valor);
                return $soloDigitos ?: null;
            case 'upper':
                return strtoupper(trim((string)$valor));
            case 'bool':
                if (is_bool($valor)) {
                    return $valor;
                }
                if ($valor === null) {
                    return false;
                }
                if (is_string($valor)) {
                    $valor = trim($valor);
                }
                return filter_var($valor, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
            default:
                return (string)$valor;
        }
    }

    public static function ctrGenerarSolicitudDocx(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['generarSolicitudDocx'])) {
            return;
        }

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
                error_log('[Solicitud DOCX] Salida inesperada capturada: ' . $extraOutput);
                if ($status === 'ok') {
                    $status = 'error';
                    $payload = [
                        'message' => 'Se generó una salida inesperada durante la generación de la solicitud.',
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

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            $respond('error', ['message' => 'Debe iniciar sesión para continuar.'], 401);
        }

        if (!isset($_POST['csrf_token']) || ($_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? ''))) {
            $respond('error', ['message' => 'Token de seguridad inválido.'], 403);
        }

        $solicitudId = isset($_POST['solicitud_id']) ? (int)$_POST['solicitud_id'] : 0;
        if ($solicitudId <= 0) {
            $respond('error', ['message' => 'Identificador de solicitud no válido.'], 422);
        }

        $solicitud = ModeloSolicitudes::mdlObtenerSolicitudPorId($solicitudId);
        if (!$solicitud) {
            $respond('error', ['message' => 'La solicitud indicada no existe.'], 404);
        }

        $esGestor = self::usuarioPuedeGestionarSolicitudes();
        if (!$esGestor) {
            $respond('error', ['message' => 'No tiene permisos para generar esta solicitud.'], 403);
        }

        $albaceaActivo = !empty($solicitud['albacea_activo']);
        $plantilla = ModeloPlantillasSolicitudes::mdlObtenerPlantillaPorTipo($albaceaActivo ? 'albacea' : 'default');
        if (!$plantilla) {
            $respond('error', ['message' => 'No hay una plantilla configurada para generar la solicitud.'], 409);
        }

        $plantillaPath = self::resolverRutaArchivo($plantilla['ruta_archivo'] ?? null);
        if (!$plantillaPath) {
            error_log('[Solicitud DOCX] Plantilla no encontrada para ruta: ' . ($plantilla['ruta_archivo'] ?? 'N/D'));
            $respond('error', [
                'message' => 'El archivo de plantilla configurado no está disponible.',
                'error_details' => 'Ruta configurada: ' . ($plantilla['ruta_archivo'] ?? 'N/D'),
            ], 500);
        }

        if (!class_exists('\\PhpOffice\\PhpWord\\TemplateProcessor')) {
            $respond('error', [
                'message' => 'La librería PhpWord no está instalada en el servidor.',
                'error_details' => 'Clase PhpOffice\\PhpWord\\TemplateProcessor no disponible.',
            ], 500);
        }

        $placeholders = self::construirPlaceholdersSolicitud($solicitud);

        $basePath = self::basePath();
        $tmpDir = $basePath . '/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        if (!is_writable($tmpDir)) {
            error_log('[Solicitud DOCX] Directorio temporal sin permisos de escritura: ' . $tmpDir);
            $respond('error', [
                'message' => 'El directorio temporal no permite escritura. Contacte al administrador.',
                'error_details' => 'Directorio temporal: ' . $tmpDir,
            ], 500);
        }

        $folio = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($solicitud['folio'] ?? 'SOLICITUD'));
        $nombre = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($solicitud['nombre_completo'] ?? 'CLIENTE'));
        $baseName = trim($folio . '-' . $nombre, '-');
        if ($baseName === '') {
            $baseName = 'solicitud-' . $solicitudId;
        }

        $docxPath = $tmpDir . '/' . $baseName . '.docx';

        try {
            if (class_exists('\\PhpOffice\\PhpWord\\Settings')) {
                \PhpOffice\PhpWord\Settings::setTempDir($tmpDir);
            }

            $template = new \PhpOffice\PhpWord\TemplateProcessor($plantillaPath);
            foreach ($placeholders as $clave => $valor) {
                $template->setValue($clave, $valor);
            }
            $template->saveAs($docxPath);
            clearstatcache(true, $docxPath);

            if (!is_file($docxPath) || filesize($docxPath) < 1024) {
                throw new \RuntimeException('El archivo generado parece estar vacío.');
            }

            $respond('ok', [
                'docx' => 'tmp/' . basename($docxPath),
                'nombre' => basename($docxPath),
            ]);
        } catch (\Throwable $throwable) {
            error_log('[Solicitud DOCX] Error al generar: ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString());
            $respond('error', [
                'message' => 'No se pudo generar la solicitud.',
                'error_details' => $throwable->getMessage(),
                'error_trace' => $throwable->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Expone los placeholders calculados de una solicitud para uso en la
     * interfaz de administración mediante peticiones AJAX seguras.
     */
    public static function ctrObtenerPlaceholdersSolicitud(): void
    {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'GET'
            || !isset($_GET['obtenerPlaceholdersSolicitud'])
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

        $solicitudId = isset($_GET['solicitud_id']) ? (int)$_GET['solicitud_id'] : 0;
        if ($solicitudId <= 0) {
            $respond('error', ['message' => 'Identificador de solicitud no válido.'], 422);
        }

        $solicitud = ModeloSolicitudes::mdlObtenerSolicitudPorId($solicitudId);
        if (!$solicitud) {
            $respond('error', ['message' => 'La solicitud indicada no existe.'], 404);
        }

        $esGestor = self::usuarioPuedeGestionarSolicitudes();
        if (!$esGestor) {
            $respond('error', ['message' => 'No tiene permisos para consultar esta solicitud.'], 403);
        }

        $placeholders = self::construirPlaceholdersSolicitud($solicitud);
        ksort($placeholders);

        $listado = [];
        foreach ($placeholders as $clave => $valor) {
            $listado[] = [
                'clave' => $clave,
                'valor' => $valor,
            ];
        }

        $respond('ok', [
            'solicitud_id' => $solicitudId,
            'total' => count($listado),
            'placeholders' => $listado,
        ]);
    }

    private static function agregarPlaceholdersFaltantes(array $placeholders, array $solicitud): array
    {
        foreach ($solicitud as $campo => $valor) {
            if ($campo === 'solicitud_datta') {
                continue;
            }

            if ($valor !== null && !is_scalar($valor)) {
                continue;
            }

            $clave = 'SOL_' . strtoupper((string)$campo);
            if (!array_key_exists($clave, $placeholders)) {
                $placeholders[$clave] = self::formatearValorGenerico($valor);
            }

            if (!array_key_exists($clave . '_TEXTO', $placeholders) && self::campoEsFecha((string)$campo, $valor)) {
                $placeholders[$clave . '_TEXTO'] = self::fechaEnFormatoLargo((string)$valor);
            }
        }

        return $placeholders;
    }

    private static function campoEsFecha(string $campo, $valor): bool
    {
        if ($valor === null) {
            return false;
        }

        if (stripos($campo, 'fecha') === false) {
            return false;
        }

        $valorCadena = (string)$valor;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valorCadena) === 1;
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

    private static function construirPlaceholdersSolicitud(array $solicitud): array
    {
        $placeholders = [];
        $campos = array_keys(self::camposSolicitud());
        foreach ($campos as $campo) {
            $clave = 'SOL_' . strtoupper($campo);
            $valor = $solicitud[$campo] ?? null;

            if ($valor === null) {
                $placeholders[$clave] = '';
                if (str_contains($clave, 'FECHA')) {
                    $placeholders[$clave . '_TEXTO'] = '';
                }
                continue;
            }

            switch ($campo) {
                case 'albacea_activo':
                    $placeholders[$clave] = $valor ? 'SI' : 'NO';
                    break;
                case 'costo_total':
                case 'enganche':
                case 'saldo':
                case 'apartado':
                case 'complemento_enganche':
                case 'pago_mensual':
                case 'pago_anual':
                    $placeholders[$clave] = number_format((float)$valor, 2, '.', ',');
                    $placeholders[$clave . '_VALOR'] = (string)$valor;
                    break;
                case 'plazo_mensualidades':
                case 'edad_actual':
                case 'edad_beneficiario':
                case 'albacea_edad':
                case 'plazo_anual':
                    $placeholders[$clave] = (string)$valor;
                    break;
                case 'fecha':
                case 'fecha_firma':
                case 'fecha_nacimiento':
                case 'fecha_liquidacion_enganche':
                case 'fecha_pago_mensual':
                case 'fecha_pago_anual':
                    $fecha = (string)$valor;
                    $fechaCorta = self::fechaEnFormatoCorto($fecha);
                    $placeholders[$clave] = $fechaCorta !== '' ? $fechaCorta : $fecha;
                    $placeholders[$clave . '_TEXTO'] = self::fechaEnFormatoLargo($fecha);
                    break;
                default:
                    $placeholders[$clave] = is_scalar($valor) ? (string)$valor : '';
                    break;
            }
        }

        $placeholders['SOLICITUD_ID'] = (string)($solicitud['id'] ?? '');
        $placeholders['SOLICITUD_ESTADO'] = strtoupper((string)($solicitud['estado'] ?? ''));
        $placeholders['SOLICITUD_USUARIO'] = strtoupper((string)($solicitud['nombre_corto'] ?? $solicitud['username'] ?? ''));
        $placeholders['SOLICITUD_GENERADA_EN'] = self::ahoraLocal()->format('d-m-Y H:i:s');
        $placeholders['SOLICITUD_GENERADA_EN_TEXTO'] = self::fechaEnFormatoLargo(self::fechaActualFormateada());

        return self::agregarPlaceholdersFaltantes($placeholders, $solicitud);
    }
}
