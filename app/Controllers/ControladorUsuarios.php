<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ModeloUsuarios;

class ControladorUsuarios
{
    // Se elimina la contraseña maestra; la creación de usuarios se controla por rol de sesión
    private const ROLES_DISPONIBLES = ['user','moderator','senior','owner','admin'];

    private static function usuarioTieneRol(array $roles): bool
    {
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return false;
        }

        $permiso = $_SESSION['permission'] ?? '';
        if ($permiso === 'admin') {
            return true;
        }

        return in_array($permiso, $roles, true);
    }

    private static function validarPassword(string $password): bool
    {
        $tieneMayuscula = preg_match('/[A-ZÁÉÍÓÚÑ]/u', $password) === 1;
        $tieneNumero = preg_match('/\d/', $password) === 1;
        $tieneEspecial = preg_match('/[^A-Za-z0-9]/u', $password) === 1;

        return $tieneMayuscula && $tieneNumero && $tieneEspecial && strlen($password) >= 8;
    }

    private static function rolesPermitidosParaAsignar(): array
    {
        $permiso = $_SESSION['permission'] ?? '';

        if ($permiso === 'admin') {
            return self::ROLES_DISPONIBLES;
        }

        if ($permiso === 'owner') {
            return array_values(array_filter(
                self::ROLES_DISPONIBLES,
                static function (string $rol): bool {
                    return $rol !== 'admin';
                }
            ));
        }

        if ($permiso === 'senior') {
            return ['user'];
        }

        if ($permiso === 'moderator') {
            return ['user'];
        }

        return ['user'];
    }

    private static function normalizarRol(string $rol): string
    {
        $rol = strtolower(trim($rol));
        $permitidos = self::rolesPermitidosParaAsignar();

        return in_array($rol, $permitidos, true) ? $rol : 'user';
    }
    /**
     * Procesar inicio de sesión.
     */
    public static function ctrIngresoUsuario() {
        // Procesar inicio de sesión sólo cuando se envían credenciales por POST
        if (isset($_POST['ingUsuario'])) {
            // Validar token CSRF para evitar peticiones forjadas
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                echo '<script>Swal.fire({title: "Error de seguridad", text: "Token inválido. Recargue la página.", icon: "error"});</script>';
                return;
            }
            $tabla = 'argus_users';
            $item  = 'username';
            $valor = $_POST['ingUsuario'];
            $respuesta = ModeloUsuarios::mdlMostrarUsuario($tabla, $item, $valor);
            // Verificar que exista el usuario y que la contraseña coincida utilizando password_verify
            if ($respuesta && password_verify($_POST['ingPassword'], $respuesta['password'])) {
                // Autenticación exitosa: regenerar el ID de sesión para mitigar ataques de fijación de sesión
                if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                    session_regenerate_id(true);
                }
                $_SESSION['iniciarSesion'] = 'ok';
                $_SESSION['id'] = $respuesta['id'];
                $_SESSION['username'] = $respuesta['username'];
                $_SESSION['permission'] = $respuesta['permission'];
                $_SESSION['nombre_corto'] = $respuesta['nombre_corto'] ?? '';
                $_SESSION['nombre_completo'] = $respuesta['nombre_completo'] ?? ($respuesta['nombre_corto'] ?? $respuesta['username']);
                $_SESSION['email'] = $respuesta['email'] ?? '';
                $notificacionesActivas = 1;
                if (is_array($respuesta) && array_key_exists('notificaciones_activas', $respuesta)) {
                    $notificacionesActivas = !empty($respuesta['notificaciones_activas']) ? 1 : 0;
                }
                $_SESSION['notificaciones_activas'] = $notificacionesActivas;
                // Mostrar alerta de éxito y redirigir
                echo '<script>
                    Swal.fire({
                        title: "Bienvenido",
                        text: "Inicio de sesión exitoso",
                        icon: "success",
                        timer: 1000,
                        showConfirmButton: false,
                        timerProgressBar: true,
                        willClose: function() {
                            window.location = "index.php?ruta=inicio";
                        }
                    });
                    </script>';
            } else {
                // Credenciales incorrectas
                echo '<script>Swal.fire({title: "Error", text: "Usuario o contraseña incorrectos", icon: "error"});</script>';
            }
        }
    }

    /**
     * Procesar el registro de un nuevo usuario desde el módulo de roles.
     * Sólo usuarios con rol administrador pueden registrar nuevos usuarios.
     */
    public static function ctrRegistrarUsuario(): void
    {
        if (!isset($_POST['nuevoUsuario'])) {
            return;
        }

        $contexto = (string)($_POST['contexto_registro'] ?? 'gestion');
        $esPublico = $contexto === 'publico';

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo '<script>Swal.fire({title:"Error de seguridad", text:"Token inválido", icon:"error"});</script>';
            return;
        }

        if (!$esPublico && !self::usuarioTieneRol(['owner'])) {
            echo '<script>Swal.fire({title:"Sin permiso", text:"No tiene permisos para crear usuarios", icon:"error"});</script>';
            return;
        }

        $nombreCompleto = strip_tags((string)($_POST['nuevoNombreCompleto'] ?? ''));
        $nombreCompleto = preg_replace('/\s+/u', ' ', $nombreCompleto ?? '');
        $nombreCompleto = trim($nombreCompleto ?? '');

        $emailOriginal = trim((string)($_POST['nuevoEmail'] ?? ''));
        $email = filter_var($emailOriginal, FILTER_SANITIZE_EMAIL) ?: '';

        $usernameOriginal = trim((string)($_POST['nuevoUsuario'] ?? ''));
        $username = preg_replace('/[^A-Za-z0-9_.@-]/u', '', $usernameOriginal);
        $username = trim($username ?? '');

        $password = (string)($_POST['nuevoPassword'] ?? '');
        $password2 = (string)($_POST['repetirPassword'] ?? '');

        $permissionInput = $_POST['nuevoRol'] ?? 'user';
        if ($esPublico) {
            $permissionInput = 'user';
            $username = strtolower($email);
        }

        if ($nombreCompleto === '' || mb_strlen($nombreCompleto) < 3) {
            echo '<script>Swal.fire({title:"Error", text:"Capture un nombre completo válido", icon:"error"});</script>';
            return;
        }

        if ($username === '' || !preg_match('/^[A-Za-z0-9_.@-]{4,}$/', $username)) {
            echo '<script>Swal.fire({title:"Error", text:"El nombre de usuario debe tener al menos 4 caracteres y solo puede contener letras, números, @ y ._-", icon:"error"});</script>';
            return;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo '<script>Swal.fire({title:"Error", text:"Capture un correo electrónico válido", icon:"error"});</script>';
            return;
        }

        if ($password === '' || $password !== $password2) {
            echo '<script>Swal.fire({title:"Error", text:"Las contraseñas no coinciden", icon:"error"});</script>';
            return;
        }

        if (!self::validarPassword($password)) {
            echo '<script>Swal.fire({title:"Error", text:"La contraseña debe tener al menos 8 caracteres e incluir mayúsculas, números y caracteres especiales.", icon:"error"});</script>';
            return;
        }

        if (ModeloUsuarios::mdlExisteUsername($username)) {
            echo '<script>Swal.fire({title:"Error", text:"El nombre de usuario ya está en uso", icon:"error"});</script>';
            return;
        }

        if ($email !== '' && ModeloUsuarios::mdlExisteEmail($email)) {
            echo '<script>Swal.fire({title:"Error", text:"El correo electrónico ya está registrado", icon:"error"});</script>';
            return;
        }

        $permisoSolicitado = strtolower(trim((string)$permissionInput));
        $esAdminSesion = ($_SESSION['permission'] ?? '') === 'admin';
        if (!$esPublico && !$esAdminSesion && $permisoSolicitado === 'admin') {
            echo '<script>Swal.fire({title:"Permiso restringido", text:"Solo un administrador puede otorgar el permiso de administrador.", icon:"warning"});</script>';
            return;
        }

        $permission = $esPublico ? 'user' : self::normalizarRol($permissionInput);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $datos = [
            'username' => $username,
            'password' => $hash,
            'permission' => $permission,
            'nombre_completo' => $nombreCompleto,
            'email' => $email,
            'notificaciones_activas' => 1,
        ];

        $resultado = ModeloUsuarios::mdlAgregarUsuario($datos);

        if ($resultado === 'ok') {
            if ($esPublico) {
                echo '<script>Swal.fire({title:"Cuenta creada", text:"Tu cuenta se creó correctamente. Ahora puedes iniciar sesión.", icon:"success"}).then(function(){ window.location = "index.php?ruta=login"; });</script>';
            } else {
                echo '<script>Swal.fire({title:"Éxito", text:"Usuario creado correctamente", icon:"success"});</script>';
            }
            return;
        }

        echo '<script>Swal.fire({title:"Error", text:"No se pudo crear el usuario", icon:"error"});</script>';
    }

    public static function ctrEditarUsuario(): void
    {
        if (!isset($_POST['editarUsuario'])) {
            return;
        }

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['owner','admin'], true)) {
            echo '<script>Swal.fire({title:"Sin permiso", text:"No tiene permisos para editar usuarios", icon:"error"});</script>';
            return;
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo '<script>Swal.fire({title:"Error de seguridad", text:"Token inválido", icon:"error"});</script>';
            return;
        }

        $usuarioId = (int)($_POST['usuario_id'] ?? 0);
        $nombreCompleto = trim((string)($_POST['editarNombreCompleto'] ?? ''));
        $email = trim((string)($_POST['editarEmail'] ?? ''));
        $permisoSolicitado = (string)($_POST['editarRol'] ?? 'user');
        $notificaciones = isset($_POST['editarNotificaciones']) ? (int)$_POST['editarNotificaciones'] : 0;

        if ($usuarioId <= 0) {
            echo '<script>Swal.fire({title:"Error", text:"Usuario no válido", icon:"error"});</script>';
            return;
        }

        if ($nombreCompleto === '' || mb_strlen($nombreCompleto) < 3) {
            echo '<script>Swal.fire({title:"Error", text:"Capture un nombre válido", icon:"error"});</script>';
            return;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo '<script>Swal.fire({title:"Error", text:"Capture un correo válido", icon:"error"});</script>';
            return;
        }

        $usuarioActual = ModeloUsuarios::mdlObtenerUsuarioPorId($usuarioId);
        if (!$usuarioActual) {
            echo '<script>Swal.fire({title:"Error", text:"El usuario no existe", icon:"error"});</script>';
            return;
        }

        if (strcasecmp($usuarioActual['email'] ?? '', $email) !== 0 && ModeloUsuarios::mdlExisteEmail($email)) {
            echo '<script>Swal.fire({title:"Error", text:"El correo electrónico ya está registrado", icon:"error"});</script>';
            return;
        }

        $permisoSesionActual = $_SESSION['permission'] ?? '';
        $esAdminSesion = $permisoSesionActual === 'admin';
        $permisoSolicitadoNormalizado = strtolower(trim($permisoSolicitado));
        $permisoActualUsuario = strtolower((string)($usuarioActual['permission'] ?? 'user'));

        if (!$esAdminSesion) {
            if ($permisoSolicitadoNormalizado === 'admin' && $permisoActualUsuario !== 'admin') {
                echo '<script>Swal.fire({title:"Permiso restringido", text:"Solo un administrador puede otorgar el permiso de administrador.", icon:"warning"});</script>';
                return;
            }

            if ($permisoActualUsuario === 'admin' && $permisoSolicitadoNormalizado !== 'admin') {
                echo '<script>Swal.fire({title:"Permiso restringido", text:"Solo un administrador puede modificar el permiso de otro administrador.", icon:"warning"});</script>';
                return;
            }
        }

        $permiso = self::normalizarRol($permisoSolicitado);
        if (!$esAdminSesion && $permisoActualUsuario === 'admin') {
            $permiso = 'admin';
        }

        $datos = [
            'id' => $usuarioId,
            'nombre_completo' => $nombreCompleto,
            'email' => $email,
            'permission' => $permiso,
            'notificaciones_activas' => $notificaciones ? 1 : 0,
        ];

        if (!ModeloUsuarios::mdlActualizarUsuario($datos)) {
            echo '<script>Swal.fire({title:"Error", text:"No se pudo actualizar el usuario", icon:"error"});</script>';
            return;
        }

        if ($usuarioId === (int)($_SESSION['id'] ?? 0)) {
            $_SESSION['nombre_completo'] = $nombreCompleto;
            $_SESSION['email'] = $email;
            $_SESSION['permission'] = $permiso;
            $_SESSION['notificaciones_activas'] = $datos['notificaciones_activas'];
        }

        echo '<script>Swal.fire({title:"Actualizado", text:"Usuario modificado correctamente", icon:"success"}).then(function(){ window.location = window.location.href; });</script>';
    }

    /**
     * Obtener lista de usuarios para mostrar en la vista de roles.
     *
     * @return array
     */
    public static function ctrMostrarUsuarios() {
        return ModeloUsuarios::mdlMostrarUsuarios();
    }

    /**
     * Procesar la eliminación de un usuario. Requiere permisos de administrador
     * o moderador. El formulario debe incluir 'eliminarUsuario' y 'usuario_id'.
     */
    public static function ctrEliminarUsuario() {
        if (!isset($_POST['eliminarUsuario'])) {
            return;
        }
        // Verificar sesión y permisos
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['admin','owner'])) {
            echo '<script>Swal.fire({title:"Sin permiso", text:"No tiene permisos para eliminar usuarios", icon:"error"});</script>';
            return;
        }
        // Validar token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo '<script>Swal.fire({title:"Error de seguridad", text:"Token inválido", icon:"error"});</script>';
            return;
        }
        $id = intval($_POST['usuario_id'] ?? 0);
        // No permitir eliminar el propio usuario que está logueado
        if ($id === ($_SESSION['id'] ?? 0)) {
            echo '<script>Swal.fire({title:"Acción no permitida", text:"No puede eliminar su propia cuenta", icon:"warning"});</script>';
            return;
        }
        $resp = ModeloUsuarios::mdlEliminarUsuario($id);
        if ($resp === 'ok') {
            echo '<script>Swal.fire({title:"Eliminado", text:"Usuario eliminado correctamente", icon:"success"}).then(function(){ window.location = window.location.href; });</script>';
        } else {
            echo '<script>Swal.fire({title:"Error", text:"No se pudo eliminar el usuario", icon:"error"});</script>';
        }
    }

    public static function ctrActualizarPreferenciasNotificaciones(): ?array
    {
        if (!isset($_POST['actualizarPreferenciasNotificaciones'])) {
            return null;
        }

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return ['tipo' => 'error', 'mensaje' => 'Sesión no válida.'];
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            return ['tipo' => 'error', 'mensaje' => 'Token de seguridad inválido.'];
        }

        $usuarioId = (int)($_SESSION['id'] ?? 0);
        $activar = isset($_POST['notificaciones_estado']) && (int)$_POST['notificaciones_estado'] === 1;

        $actualizado = ModeloUsuarios::mdlActualizarPreferenciasNotificaciones($usuarioId, $activar);

        if (!$actualizado) {
            return ['tipo' => 'error', 'mensaje' => 'No se pudieron actualizar las preferencias.'];
        }

        $_SESSION['notificaciones_activas'] = $activar ? 1 : 0;

        return ['tipo' => 'success', 'mensaje' => $activar ? 'Notificaciones activadas.' : 'Notificaciones desactivadas.'];
    }

    public static function ctrObtenerPerfilUsuario(int $usuarioId): ?array
    {
        return ModeloUsuarios::mdlObtenerUsuarioPorId($usuarioId);
    }

    public static function ctrActualizarPassword(): ?array
    {
        if (!isset($_POST['cambiarPasswordUsuario'])) {
            return null;
        }

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
            return ['tipo' => 'error', 'mensaje' => 'Sesión no válida.'];
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            return ['tipo' => 'error', 'mensaje' => 'Token de seguridad inválido.'];
        }

        $passwordActual = $_POST['password_actual'] ?? '';
        $passwordNuevo = $_POST['password_nuevo'] ?? '';
        $passwordConfirmar = $_POST['password_confirmar'] ?? '';

        if ($passwordNuevo !== $passwordConfirmar) {
            return ['tipo' => 'error', 'mensaje' => 'Las contraseñas nuevas no coinciden.'];
        }

        if (!self::validarPassword($passwordNuevo)) {
            return ['tipo' => 'error', 'mensaje' => 'La contraseña debe incluir mayúsculas, números, caracteres especiales y tener al menos 8 caracteres.'];
        }

        $usuarioId = (int)($_SESSION['id'] ?? 0);
        $usuario = ModeloUsuarios::mdlObtenerUsuarioPorId($usuarioId);

        if (!$usuario || !password_verify($passwordActual, $usuario['password'] ?? '')) {
            return ['tipo' => 'error', 'mensaje' => 'La contraseña actual no es válida.'];
        }

        $hash = password_hash($passwordNuevo, PASSWORD_DEFAULT);
        $actualizado = ModeloUsuarios::mdlActualizarPassword($usuarioId, $hash);

        if ($actualizado) {
            return ['tipo' => 'success', 'mensaje' => 'Contraseña actualizada correctamente.'];
        }

        return ['tipo' => 'error', 'mensaje' => 'No fue posible actualizar la contraseña.'];
    }
}
