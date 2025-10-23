<?php
/**
 * Plantilla principal de la aplicación Argus (MVC).
 * Estructura la cabecera, navegación y vista de contenido según la ruta.
 */

use App\Controllers\ControladorContratos;
use App\Controllers\ControladorSolicitudes;
use App\Support\AssetVersion;

$timezone = $_ENV['APP_TIMEZONE']
    ?? $_SERVER['APP_TIMEZONE']
    ?? getenv('APP_TIMEZONE')
    ?? 'America/Guatemala';

if (!@date_default_timezone_set($timezone)) {
    date_default_timezone_set('UTC');
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 1 Jul 2000 05:00:00 GMT');
// Usamos un nombre personalizado de sesión para evitar colisiones con otras apps
if (session_status() === PHP_SESSION_NONE) {
    session_name('argus_session');
    session_start();
}

// Generar un token CSRF si no existe en la sesión. Este token se insertará en los formularios y se validará en los controladores.
if (empty($_SESSION['csrf_token'])) {
    // bin2hex(random_bytes()) genera una cadena segura para usar como token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// === Interceptor para peticiones AJAX del flujo crearContrato ===
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['ruta'])
    && $_GET['ruta'] === 'crearContrato'
    && (
        isset($_POST['crearContratoCompleto'])
        || isset($_POST['editarContratoCompleto'])
    )
) {
    ControladorContratos::ctrCrearContratoCompleto();
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['ruta'])
    && $_GET['ruta'] === 'crearContrato'
    && isset($_POST['buscarSolicitudesContrato'])
) {
    ControladorContratos::ctrBuscarSolicitudesCompatibles();
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['ruta'])
    && $_GET['ruta'] === 'crearContrato'
    && isset($_POST['obtenerPrefillSolicitud'])
) {
    ControladorContratos::ctrObtenerPrefillSolicitudContrato();
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['ruta'])
    && $_GET['ruta'] === 'solicitudes'
    && isset($_POST['generarSolicitudDocx'])
) {
    ControladorSolicitudes::ctrGenerarSolicitudDocx();
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['ruta'])
    && $_GET['ruta'] === 'solicitudes'
    && isset($_GET['obtenerPlaceholdersSolicitud'])
) {
    ControladorSolicitudes::ctrObtenerPlaceholdersSolicitud();
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['ruta'])
    && $_GET['ruta'] === 'contratos'
    && isset($_GET['obtenerPlaceholdersContrato'])
) {
    ControladorContratos::ctrObtenerPlaceholdersContrato();
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="vistas/argus_ico.ico" type="image/x-icon">
    <!-- Cambiar título global -->
    <title data-base-title="Contratos Grupo Argus">Contratos Grupo Argus</title>
    <!-- CSS principales -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.4/css/dataTables.dataTables.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.7/css/responsive.dataTables.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(AssetVersion::url('vistas/css/custom.css'), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Summernote WYSIWYG editor -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs5.min.css">

    <!-- Cargar SweetAlert2 antes de que se ejecute cualquier script que lo utilice -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>
    
</head>
<?php
$rutaActual = isset($_GET['ruta']) ? trim((string)$_GET['ruta']) : 'inicio';
if ($rutaActual === '') {
    $rutaActual = 'inicio';
}
$sesionActiva = isset($_SESSION['iniciarSesion']) && $_SESSION['iniciarSesion'] === 'ok';
$collapseSidebar = $sesionActiva && $rutaActual !== 'inicio';
$bodyClasses = 'hold-transition sidebar-mini';
if ($collapseSidebar) {
    $bodyClasses .= ' sidebar-collapse';
}
?>
<body class="<?php echo htmlspecialchars($bodyClasses, ENT_QUOTES, 'UTF-8'); ?>">
<script>
    (function enforceSidebarDefaultState() {
        var shouldCollapse = <?php echo $collapseSidebar ? 'true' : 'false'; ?>;
        try {
            if (shouldCollapse) {
                window.localStorage.setItem('AdminLTE:SidebarCollapse', '1');
            } else {
                window.localStorage.removeItem('AdminLTE:SidebarCollapse');
            }
        } catch (error) {
            console.warn('No fue posible actualizar la preferencia del menú lateral.', error);
        }

        if (shouldCollapse) {
            document.body.classList.add('sidebar-collapse');
        } else {
            document.body.classList.remove('sidebar-collapse');
        }
    })();
</script>
<?php
if ($sesionActiva) {
    echo '<div class="wrapper">';
    include 'modulos/cabezote.php';
    include 'modulos/menu.php';
    // Envolvemos el contenido en un content-wrapper para respetar la estructura de AdminLTE
    echo '<div class="content-wrapper">';
    // Determinar la ruta solicitada
    if (isset($_GET['ruta'])) {
        $ruta = $_GET['ruta'];
        $permitidas = ['inicio','clientes','contratos','desarrollos','roles','parametros','crearContrato','solicitudes','nuevaSolicitud','perfil','salir','consola'];
        if (in_array($ruta, $permitidas)) {
            include 'modulos/' . $ruta . '.php';
        } else {
            include 'modulos/404.php';
        }
    } else {
        include 'modulos/inicio.php';
    }
    echo '</div>'; // cierre de content-wrapper
    include 'modulos/footer.php';
    echo '</div>';
} else {
    $rutaPublica = $_GET['ruta'] ?? 'login';
    $modulosPublicos = ['login', 'crearCuenta'];
    if (in_array($rutaPublica, $modulosPublicos, true)) {
        include 'modulos/' . $rutaPublica . '.php';
    } else {
        include 'modulos/login.php';
    }
}
?>
<!-- JS principales -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/2.3.4/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.3.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.7/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.7/js/responsive.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Summernote JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs5.min.js"></script>
<script>
    window.AG_NOTIFICATIONS = {
        habilitadas: <?php
            $notificacionesActivas = !empty($_SESSION['notificaciones_activas']);
            echo $notificacionesActivas ? 'true' : 'false';
        ?>,
        usuarioId: <?php echo (int)($_SESSION['id'] ?? 0); ?>,
        sonido: 'vistas/media/notificacion.mp3'
    };
</script>
<script src="<?php echo htmlspecialchars(AssetVersion::url('vistas/js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

