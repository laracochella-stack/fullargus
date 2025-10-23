<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ModeloClientes;
use App\Models\ModeloContratos;

class ControladorClientes
{
    /**
     * Registrar un nuevo cliente a partir de los datos recibidos por POST.
     * Se espera que la vista envíe los datos con nombres de campo coincidentes.
     */
    public static function ctrAgregarCliente() {
        // Comprobar que se haya enviado el formulario de cliente y que la acción sea agregar
        if (isset($_POST['nombre']) && isset($_GET['accion']) && $_GET['accion'] === 'agregar') {
            // Validar token CSRF
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                return 'error_csrf';
            }
            // Verificar que el usuario tenga sesión iniciada y permisos adecuados (moderador o superior)
            if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'] ?? '', ['moderator','senior','owner','admin'], true)) {
                return 'error_permiso';
            }
            $datos = [
                'nombre'        => trim($_POST['nombre']),
                'nacionalidad'  => trim($_POST['nacionalidad']),
                'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? null,
                'rfc'           => strtoupper(trim($_POST['rfc'] ?? '')),
                'curp'          => trim($_POST['curp']),
                'ine'           => trim($_POST['ine']),
                'estado_civil'  => trim($_POST['estado_civil']),
                'ocupacion'     => trim($_POST['ocupacion']),
                'telefono'      => trim($_POST['telefono']),
                'domicilio'     => trim($_POST['domicilio']),
                'email'         => trim($_POST['email']),
                'beneficiario'  => trim($_POST['beneficiario']),
                'estado'        => 'activo',
            ];
            if ($datos['rfc'] === '') {
                return 'error_rfc';
            }
            if (ModeloClientes::mdlExisteRfc($datos['rfc'])) {
                return 'duplicado_rfc';
            }
            $respuesta = ModeloClientes::mdlAgregarCliente($datos);
            return $respuesta;
        }
        return null;
    }

    /**
     * Obtener todos los clientes para mostrarlos en la vista.
     */
    public static function ctrMostrarClientes() {
        return ModeloClientes::mdlMostrarClientes();
    }

    /**
     * Busca coincidencias de clientes por RFC o CURP.
     */
    public static function ctrBuscarClientePorRfcCurp(?string $rfc, ?string $curp): ?array
    {
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return null;
        }

        if (!in_array($_SESSION['permission'] ?? '', ['moderator','senior','owner','admin'], true)) {
            return null;
        }

        $normalizar = static function ($valor): string {
            if (!is_string($valor)) {
                return '';
            }
            $texto = trim($valor);
            if ($texto === '') {
                return '';
            }
            return function_exists('mb_strtoupper') ? mb_strtoupper($texto, 'UTF-8') : strtoupper($texto);
        };

        $rfcNormalizado = $normalizar($rfc);
        $curpNormalizado = $normalizar($curp);

        if ($rfcNormalizado === '' && $curpNormalizado === '') {
            return null;
        }

        return ModeloClientes::mdlBuscarPorRfcCurp(
            $rfcNormalizado !== '' ? $rfcNormalizado : null,
            $curpNormalizado !== '' ? $curpNormalizado : null
        );
    }

    /**
     * Editar un cliente existente. Recibe datos por POST con la acción editarCliente.
     *
     * @return string|null
     */
    public static function ctrEditarCliente() {
        if (isset($_POST['id_cliente']) && isset($_GET['accion']) && $_GET['accion'] === 'editarCliente') {
            // Validar token CSRF
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                return 'error_csrf';
            }
            // Verificar sesión iniciada con permisos suficientes
            if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'] ?? '', ['moderator','senior','owner','admin'], true)) {
                return 'error_permiso';
            }
            // Recolectar datos del formulario
            $datos = [
                'id'           => (int)$_POST['id_cliente'],
                'nombre'       => trim($_POST['nombre']),
                'nacionalidad' => trim($_POST['nacionalidad']),
                'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? null,
                'rfc'          => strtoupper(trim($_POST['rfc'] ?? '')),
                'curp'         => trim($_POST['curp']),
                'ine'          => trim($_POST['ine']),
                'estado_civil' => trim($_POST['estado_civil']),
                'ocupacion'    => trim($_POST['ocupacion']),
                'telefono'     => trim($_POST['telefono']),
                'domicilio'    => trim($_POST['domicilio']),
                'email'        => trim($_POST['email']),
                'beneficiario' => trim($_POST['beneficiario'])
            ];
            if ($datos['rfc'] === '') {
                return 'error_rfc';
            }
            if (ModeloClientes::mdlExisteRfc($datos['rfc'], $datos['id'])) {
                return 'duplicado_rfc';
            }
            $respuesta = ModeloClientes::mdlEditarCliente($datos);
            return $respuesta;
        }
        return null;
    }

    /**
     * Cambia el estado de un cliente a activo o archivado.
     * Emite una respuesta JSON y detiene la ejecución de la vista.
     */
    public static function ctrActualizarEstado(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_GET['accion'] ?? '') !== 'actualizarEstadoCliente') {
            return;
        }

        $respond = static function (int $status, array $payload): void {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8', true, $status);
            }

            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        };

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            $respond(401, ['status' => 'error', 'message' => 'Sesión no válida.']);
        }

        if (!in_array($_SESSION['permission'] ?? '', ['moderator', 'senior', 'owner', 'admin'], true)) {
            $respond(403, ['status' => 'error', 'message' => 'Permisos insuficientes.']);
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            $respond(403, ['status' => 'error', 'message' => 'Token CSRF inválido.']);
        }

        $clienteId = isset($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : 0;
        $estado = isset($_POST['estado']) ? (string)$_POST['estado'] : '';

        if ($clienteId <= 0) {
            $respond(400, ['status' => 'error', 'message' => 'Cliente no válido.']);
        }

        $estado = strtolower(trim($estado));
        if (!in_array($estado, ['activo', 'archivado'], true)) {
            $respond(400, ['status' => 'error', 'message' => 'Estado solicitado no válido.']);
        }

        if ($estado === 'archivado') {
            $contratosActivos = ModeloContratos::mdlContarContratosActivosPorCliente($clienteId);
            if ($contratosActivos > 0) {
                $respond(409, [
                    'status' => 'error',
                    'code' => 'CONTRATOS_ACTIVOS',
                    'message' => sprintf(
                        'El cliente tiene %d contrato(s) activo(s). Cancélalos antes de archivar.',
                        $contratosActivos
                    ),
                    'activos' => $contratosActivos,
                ]);
            }
        }

        $resultado = ModeloClientes::mdlActualizarEstado($clienteId, $estado);
        if ($resultado === 'ok') {
            $respond(200, [
                'status' => 'ok',
                'message' => $estado === 'archivado'
                    ? 'Cliente archivado correctamente.'
                    : 'Cliente reactivado correctamente.',
            ]);
        }

        $respond(500, ['status' => 'error', 'message' => 'No fue posible actualizar el cliente.']);
    }
}
