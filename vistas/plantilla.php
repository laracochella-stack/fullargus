<?php
/**
 * Plantilla principal de la aplicación Argus (MVC).
 * Estructura la cabecera, navegación y vista de contenido según la ruta.
 */

use App\Controllers\ControladorContratos;
use App\Controllers\ControladorSolicitudes;
use App\Support\AppNavigation;
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
$bodyClasses = 'hold-transition layout-top-nav';
$navigationModules = [];
if ($sesionActiva) {
    try {
        $navigationModules = AppNavigation::getAppCards($_SESSION ?? []);
    } catch (\Throwable $exception) {
        $navigationModules = [];
    }
}
?>
<body class="<?php echo htmlspecialchars($bodyClasses, ENT_QUOTES, 'UTF-8'); ?>">
<?php
if ($sesionActiva) {
    echo '<div class="wrapper">';
    include 'modulos/cabezote.php';
    if (!empty($navigationModules)) {
        echo '<div id="agAppDrawer" class="ag-app-drawer" data-visible="0" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Selector de aplicaciones">';
        echo '<div class="ag-app-drawer__backdrop" data-ag-app-close="true"></div>';
        echo '<div class="ag-app-drawer__panel">';
        echo '<div class="ag-app-drawer__header">';
        echo '<h2 class="ag-app-drawer__title"><i class="fas fa-cubes me-2"></i>Aplicaciones</h2>';
        echo '<button type="button" class="btn-close" aria-label="Cerrar" data-ag-app-close="true"></button>';
        echo '</div>';
        echo '<div class="ag-app-drawer__search input-group">';
        echo '<span class="input-group-text"><i class="fas fa-search"></i></span>';
        echo '<input type="search" class="form-control" placeholder="Buscar aplicación" autocomplete="off" data-ag-app-filter="drawer">';
        echo '</div>';
        echo '<div class="ag-app-drawer__content">';
        echo '<div class="row g-3" data-ag-app-grid="drawer">';
        require_once __DIR__ . '/partials/app_card.php';
        foreach ($navigationModules as $module) {
            ag_render_app_card($module, [
                'wrapper_class' => 'col-12 col-sm-6',
                'card_class' => 'ag-app-card ag-app-card--drawer',
                'action_class' => 'ag-app-card__action ag-app-card__action--drawer',
            ]);
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    // Envolvemos el contenido en un content-wrapper para respetar la estructura de AdminLTE
    echo '<div class="content-wrapper">';
    echo '<div class="content">';
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
    echo '</div>';
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

