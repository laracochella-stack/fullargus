<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ModeloPlantillas;
use App\Models\ModeloPlantillasSolicitudes;
use App\Models\ModeloVariables;

/**
 * Controlador para la gestión de parámetros y plantillas.
 * Incluye CRUD para variables (nacionalidades, tipos de contrato) y subida de plantillas.
 */



class ControladorParametros
{
    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    }

    private static function rutaAbsoluta(?string $ruta): ?string
    {
        if ($ruta === null) {
            return null;
        }

        $ruta = trim(str_replace('\\', '/', $ruta));
        if ($ruta === '') {
            return null;
        }

        if (str_starts_with($ruta, '/') || preg_match('/^[A-Za-z]:[\\\/]/', $ruta)) {
            return $ruta;
        }

        return self::basePath() . '/' . ltrim($ruta, '/');
    }

    static public function ctrGenerarIdentificador() {
        // Prefijo con fecha actual + número aleatorio de 6 dígitos
        $identificadorID = 'ID-' . date('Ymd') . '-' . mt_rand(100000, 999999);

        return $identificadorID;
    }
    
    /**
     * Procesa el envío del formulario para agregar una variable. Debe venir con
     * los campos 'tipo', 'identificador' y 'nombre'.
     */
    static public function ctrAgregarVariable()
    {

        
        if (!isset($_POST['agregarVariable'])) {
            return;
        }
        // Comprobar permisos: sólo admin o moderator
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['senior','owner','admin'])) {
            echo 'error-permiso';
            return;
        }
        // Validar token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo 'error-token';
            return;
        }
        $tipo = trim($_POST['tipo']);
        $nombre = trim($_POST['nombre']);
        $identificador = $identificadorID = self::ctrGenerarIdentificador();
        if ($tipo && $identificador && $nombre) {
            $datos = [
                'tipo' => $tipo,
                'identificador' => $identificador,
                'nombre' => $nombre
            ];
            $resp = ModeloVariables::mdlAgregarVariable($datos);
            echo $resp;
        } else {
            echo 'error';
        }
    }

    /**
     * Procesa la edición de una variable existente. Se envía desde un formulario
     * con 'editarVariable' y contiene 'id', 'identificador' y 'nombre'.
     */
    static public function ctrEditarVariable()
    {
        if (!isset($_POST['editarVariable'])) {
            return;
        }
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['senior','owner','admin'])) {
            echo 'error-permiso';
            return;
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo 'error-token';
            return;
        }
        $id = intval($_POST['id']);
        $identificador = trim($_POST['identificador']);
        $nombre = trim($_POST['nombre']);
        $datos = [
            'id' => $id,
            'identificador' => $identificador,
            'nombre' => $nombre
        ];
        $resp = ModeloVariables::mdlEditarVariable($datos);
        echo $resp;
    }

    /**
     * Elimina una variable existente (nacionalidad, tipo de contrato, etc.).
     */
    static public function ctrEliminarVariable()
    {
        if (!isset($_POST['eliminarVariable'])) {
            return;
        }

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['senior','owner','admin'])) {
            echo 'error-permiso';
            return;
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo 'error-token';
            return;
        }

        $id = isset($_POST['variable_id']) ? (int)$_POST['variable_id'] : 0;
        $tipo = trim((string)($_POST['variable_tipo'] ?? ''));

        if ($id <= 0 || !in_array($tipo, ['nacionalidad', 'tipo_contrato'], true)) {
            echo 'error-datos';
            return;
        }

        $resultado = ModeloVariables::mdlEliminarVariable($id);
        echo $resultado;
    }

    /**
     * Devuelve un listado de variables para un tipo.
     */
    static public function ctrMostrarVariables($tipo)
    {
        return ModeloVariables::mdlMostrarVariables($tipo);
    }

    /**
     * Procesa la subida de una plantilla. Debe enviarse con un archivo
     * 'plantilla' y un 'tipo_contrato_id'.
     */
    static public function ctrSubirPlantilla()
    {
        if (!isset($_POST['subirPlantilla'])) {
            return;
        }
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['senior','owner','admin'])) {
            echo 'error-permiso';
            return;
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo 'error-token';
            return;
        }
        $tipoId = intval($_POST['tipo_contrato_id']);
        if (!$tipoId) {
            echo 'error';
            return;
        }
        // Comprobar archivo
        if (!isset($_FILES['plantilla']) || $_FILES['plantilla']['error'] !== UPLOAD_ERR_OK) {
            echo 'error-archivo';
            return;
        }
        $file = $_FILES['plantilla'];
        // Validar tamaño (máx. 150MB)
        if ($file['size'] > 150 * 1024 * 1024) {
            echo 'error-tamano';
            return;
        }
        // Validar extensión
        $allowedExt = ['docx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) {
            echo 'error-extension';
            return;
        }
        // Crear directorio de plantillas si no existe
        $basePath = self::basePath();
        $uploadDir = $basePath . '/vistas/plantillas';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        // Nombre único para el archivo
        $nuevoNombre = uniqid('tpl_') . '.' . $ext;
        $rutaRelativa = 'vistas/plantillas/' . $nuevoNombre;
        $destino = $basePath . '/' . $rutaRelativa;
        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            echo 'error-guardar';
            return;
        }
        $datos = [
            'tipo_contrato_id' => $tipoId,
            'nombre_archivo' => $file['name'],
            'ruta_archivo' => $rutaRelativa
        ];
        $resp = ModeloPlantillas::mdlAgregarPlantilla($datos);
        echo $resp;
    }

    static public function ctrSubirPlantillaSolicitud()
    {
        if (!isset($_POST['subirPlantillaSolicitud'])) {
            return;
        }
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['senior','owner','admin'])) {
            echo 'error-permiso';
            return;
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo 'error-token';
            return;
        }

        $tipo = strtolower(trim((string)($_POST['plantilla_tipo'] ?? '')));
        if (!in_array($tipo, ['default','albacea'], true)) {
            echo 'error-tipo';
            return;
        }

        if (!isset($_FILES['plantilla']) || $_FILES['plantilla']['error'] !== UPLOAD_ERR_OK) {
            echo 'error-archivo';
            return;
        }

        $file = $_FILES['plantilla'];
        if ($file['size'] > 150 * 1024 * 1024) {
            echo 'error-tamano';
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'docx') {
            echo 'error-extension';
            return;
        }

        $basePath = self::basePath();
        $uploadDir = $basePath . '/vistas/plantillas/solicitudes';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $anterior = ModeloPlantillasSolicitudes::mdlObtenerPlantillaPorTipo($tipo);

        $nuevoNombre = uniqid('sol_tpl_') . '.' . $ext;
        $rutaRelativa = 'vistas/plantillas/solicitudes/' . $nuevoNombre;
        $destino = $basePath . '/' . $rutaRelativa;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            echo 'error-guardar';
            return;
        }

        if (!ModeloPlantillasSolicitudes::mdlGuardarPlantilla($tipo, $file['name'], $rutaRelativa)) {
            @unlink($destino);
            echo 'error';
            return;
        }

        if ($anterior && !empty($anterior['ruta_archivo']) && $anterior['ruta_archivo'] !== $rutaRelativa) {
            $rutaAnterior = self::rutaAbsoluta($anterior['ruta_archivo']);
            if ($rutaAnterior && is_file($rutaAnterior)) {
                @unlink($rutaAnterior);
            }
        }

        echo 'ok';
    }

    /**
     * Permite actualizar una plantilla de solicitud existente.
     *
     * Valida permisos y token CSRF, admite reemplazo opcional del archivo
     * original y actualiza los metadatos en base de datos, eliminando la
     * versión previa cuando aplica.
     */
    public static function ctrEditarPlantillaSolicitud(): void
    {
        if (!isset($_POST['editarPlantillaSolicitud'])) {
            return;
        }

        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['senior','owner','admin'], true)) {
            echo 'error-permiso';
            return;
        }

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo 'error-token';
            return;
        }

        $id = (int)($_POST['plantilla_solicitud_id'] ?? 0);
        $tipo = strtolower(trim((string)($_POST['plantilla_tipo'] ?? '')));

        if ($id <= 0 || !in_array($tipo, ['default', 'albacea'], true)) {
            echo 'error-datos';
            return;
        }

        $plantillaActual = ModeloPlantillasSolicitudes::mdlObtenerPlantillaPorId($id);
        if (!$plantillaActual) {
            echo 'error-noexiste';
            return;
        }

        if (($plantillaActual['tipo'] ?? '') !== $tipo) {
            $plantillaPrevioTipo = ModeloPlantillasSolicitudes::mdlObtenerPlantillaPorTipo($tipo);
            if ($plantillaPrevioTipo && (int)$plantillaPrevioTipo['id'] !== $id) {
                $rutaPrevioTipo = $plantillaPrevioTipo['ruta_archivo'] ?? '';
                if (!ModeloPlantillasSolicitudes::mdlEliminarPlantilla((int)$plantillaPrevioTipo['id'])) {
                    echo 'error-limpiar';
                    return;
                }
                if ($rutaPrevioTipo) {
                    $rutaEliminar = self::rutaAbsoluta($rutaPrevioTipo);
                    if ($rutaEliminar && is_file($rutaEliminar)) {
                        @unlink($rutaEliminar);
                    }
                }
            }
        }

        $nombreArchivo = $plantillaActual['nombre_archivo'] ?? '';
        $rutaActual = $plantillaActual['ruta_archivo'] ?? '';
        $rutaRelativa = $rutaActual;

        $hayArchivoNuevo = isset($_FILES['plantilla']) && is_array($_FILES['plantilla']) && ($_FILES['plantilla']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hayArchivoNuevo) {
            $file = $_FILES['plantilla'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                echo 'error-archivo';
                return;
            }

            if ($file['size'] > 150 * 1024 * 1024) {
                echo 'error-tamano';
                return;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'docx') {
                echo 'error-extension';
                return;
            }

            $basePath = self::basePath();
            $uploadDir = $basePath . '/vistas/plantillas/solicitudes';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $nuevoNombre = uniqid('sol_tpl_') . '.' . $ext;
            $rutaRelativa = 'vistas/plantillas/solicitudes/' . $nuevoNombre;
            $destino = $basePath . '/' . $rutaRelativa;

            if (!move_uploaded_file($file['tmp_name'], $destino)) {
                echo 'error-guardar';
                return;
            }

            if ($rutaActual) {
                $rutaAnterior = self::rutaAbsoluta($rutaActual);
                if ($rutaAnterior && is_file($rutaAnterior)) {
                    @unlink($rutaAnterior);
                }
            }

            $nombreArchivo = $file['name'];
        }

        if (!ModeloPlantillasSolicitudes::mdlActualizarPlantilla($id, $tipo, $nombreArchivo, $rutaRelativa)) {
            echo 'error-actualizar';
            return;
        }

        echo 'ok';
    }

    /**
     * Procesa la edición de una plantilla existente. Permite reemplazar el archivo
     * subido y cambiar el tipo de contrato asociado. Requiere permisos de
     * administrador o moderador.
     */
    static public function ctrEditarPlantilla()
    {
        if (!isset($_POST['editarPlantilla'])) {
            return;
        }
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['senior','owner','admin'])) {
            echo 'error-permiso';
            return;
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo 'error-token';
            return;
        }
        $id = intval($_POST['plantilla_id'] ?? 0);
        $tipoId = intval($_POST['tipo_contrato_id'] ?? 0);
        if (!$id || !$tipoId) {
            echo 'error';
            return;
        }
        // Recuperar plantilla actual para eliminar archivo anterior si es reemplazado
        $plantillaActual = null;
        $todas = ModeloPlantillas::mdlMostrarPlantillas();
        foreach ($todas as $tpl) {
            if ((int)$tpl['id'] === $id) {
                $plantillaActual = $tpl;
                break;
            }
        }
        if ($plantillaActual && (int)($plantillaActual['tipo_contrato_id'] ?? 0) !== $tipoId) {
            $plantillaDuplicada = ModeloPlantillas::mdlObtenerPlantillaPorTipoExcluyendoId($tipoId, $id);
            if ($plantillaDuplicada) {
                $rutaDuplicada = $plantillaDuplicada['ruta_archivo'] ?? '';
                if (ModeloPlantillas::mdlEliminarPlantilla((int)$plantillaDuplicada['id']) !== 'ok') {
                    echo 'error-limpiar';
                    return;
                }
                if ($rutaDuplicada) {
                    $rutaEliminar = self::rutaAbsoluta($rutaDuplicada);
                    if ($rutaEliminar && is_file($rutaEliminar)) {
                        @unlink($rutaEliminar);
                    }
                }
            }
        }
        // Manejar archivo nuevo si se sube
        $nuevoNombre = $plantillaActual['nombre_archivo'] ?? '';
        $rutaRelativa = $plantillaActual['ruta_archivo'] ?? '';
        if (isset($_FILES['plantilla']) && $_FILES['plantilla']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['plantilla'];
            // Validar tamaño y extensión
            if ($file['size'] > 150 * 1024 * 1024) {
                echo 'error-tamano';
                return;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['docx','pdf'])) {
                echo 'error-extension';
                return;
            }
            // Directorio de plantillas
            $uploadDir = self::basePath() . '/vistas/plantillas';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $nuevoArchivoNombre = uniqid('tpl_') . '.' . $ext;
            $rutaRel = 'vistas/plantillas/' . $nuevoArchivoNombre;
            $destino = self::basePath() . '/' . $rutaRel;
            if (!move_uploaded_file($file['tmp_name'], $destino)) {
                echo 'error-guardar';
                return;
            }
            // Eliminar archivo anterior si existe
            if ($plantillaActual && !empty($plantillaActual['ruta_archivo'])) {
                $oldPath = self::rutaAbsoluta($plantillaActual['ruta_archivo']);
                if ($oldPath && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $nuevoNombre = $file['name'];
            $rutaRelativa = $rutaRel;
        }
        // Construir datos para actualizar
        $datos = [
            'id' => $id,
            'tipo_contrato_id' => $tipoId,
            'nombre_archivo' => $nuevoNombre,
            'ruta_archivo' => $rutaRelativa
        ];
        $resp = ModeloPlantillas::mdlEditarPlantilla($datos);
        echo $resp;
    }

    /**
     * Procesa la eliminación de una plantilla. Sólo para administradores y
     * moderadores. Elimina el registro en la base de datos y borra el archivo
     * asociado.
     */
    static public function ctrEliminarPlantilla()
    {
        if (!isset($_POST['eliminarPlantilla'])) {
            return;
        }
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['senior','owner','admin'])) {
            echo 'error-permiso';
            return;
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo 'error-token';
            return;
        }
        $id = intval($_POST['plantilla_id'] ?? 0);
        if (!$id) {
            echo 'error';
            return;
        }
        // Obtener plantilla actual para eliminar archivo
        $plantillaActual = null;
        $todas = ModeloPlantillas::mdlMostrarPlantillas();
        foreach ($todas as $tpl) {
            if ((int)$tpl['id'] === $id) {
                $plantillaActual = $tpl;
                break;
            }
        }
        // Eliminar registro
        $resp = ModeloPlantillas::mdlEliminarPlantilla($id);
        if ($resp === 'ok' && $plantillaActual && !empty($plantillaActual['ruta_archivo'])) {
            $ruta = self::rutaAbsoluta($plantillaActual['ruta_archivo']);
            if ($ruta && is_file($ruta)) {
                @unlink($ruta);
            }
        }
        echo $resp;
    }

    static public function ctrEliminarPlantillaSolicitud()
    {
        if (!isset($_POST['eliminarPlantillaSolicitud'])) {
            return;
        }
        if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok' || !in_array($_SESSION['permission'], ['senior','owner','admin'])) {
            echo 'error-permiso';
            return;
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            echo 'error-token';
            return;
        }

        $id = (int)($_POST['plantilla_solicitud_id'] ?? 0);
        if ($id <= 0) {
            echo 'error';
            return;
        }

        $plantilla = ModeloPlantillasSolicitudes::mdlObtenerPlantillaPorId($id);
        if (!ModeloPlantillasSolicitudes::mdlEliminarPlantilla($id)) {
            echo 'error';
            return;
        }

        if ($plantilla && !empty($plantilla['ruta_archivo'])) {
            $ruta = self::rutaAbsoluta($plantilla['ruta_archivo']);
            if ($ruta && is_file($ruta)) {
                @unlink($ruta);
            }
        }

        echo 'ok';
    }

    /**
     * Devuelve todas las plantillas con su tipo de contrato.
     *
     * @return array
     */
    static public function ctrMostrarPlantillas()
    {
        return ModeloPlantillas::mdlMostrarPlantillas();
    }

    static public function ctrMostrarPlantillasSolicitud(): array
    {
        return ModeloPlantillasSolicitudes::mdlObtenerPlantillas();
    }
}
