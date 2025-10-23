<?php

declare(strict_types=1);

use App\Controllers\ControladorSolicitudes;

require __DIR__ . '/stubs.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

require BASE_PATH . '/app/Controllers/ControladorSolicitudes.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('argus_session');
    session_start();
}

$_SESSION['iniciarSesion'] = 'ok';
$_SESSION['csrf_token'] = 'test-token';
$_SESSION['permission'] = 'admin';
$_SESSION['id'] = 999;

$_SERVER['REQUEST_METHOD'] = 'POST';

$_POST = [
    'generarSolicitudDocx' => '1',
    'solicitud_id' => '123',
    'csrf_token' => 'test-token',
];

$templateDir = BASE_PATH . '/tmp';
if (!is_dir($templateDir)) {
    mkdir($templateDir, 0775, true);
}

$templatePath = $templateDir . '/test_solicitud_template.docx';
$templateContent = str_repeat('Solicitud para ${SOL_NOMBRE_COMPLETO} (${SOLICITUD_ID})' . PHP_EOL, 80);
file_put_contents($templatePath, $templateContent);

class StubModeloSolicitudes
{
    public static function mdlObtenerSolicitudPorId(int $id): ?array
    {
        return [
            'id' => $id,
            'folio' => 'SOL-001',
            'fecha' => '2025-01-15',
            'fecha_firma' => '2025-01-15',
            'nombre_completo' => 'Juan Pérez',
            'nacionalidad' => 'MEXICANA',
            'albacea_activo' => 0,
            'estado' => 'borrador',
            'usuario_id' => 999,
            'nombre_corto' => 'ADMIN',
            'username' => 'admin',
        ];
    }
}

class StubModeloPlantillasSolicitudes
{
    public static function mdlObtenerPlantillaPorTipo(string $tipo): ?array
    {
        return [
            'ruta_archivo' => 'tmp/test_solicitud_template.docx',
        ];
    }
}

if (!class_exists('App\Models\ModeloSolicitudes', false)) {
    class_alias(StubModeloSolicitudes::class, 'App\Models\ModeloSolicitudes');
}
if (!class_exists('App\Models\ModeloPlantillasSolicitudes', false)) {
    class_alias(StubModeloPlantillasSolicitudes::class, 'App\Models\ModeloPlantillasSolicitudes');
}

ControladorSolicitudes::ctrGenerarSolicitudDocx();
