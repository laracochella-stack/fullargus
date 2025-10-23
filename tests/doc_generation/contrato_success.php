<?php

declare(strict_types=1);

use App\Controllers\ControladorContratos;

require __DIR__ . '/stubs.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

require BASE_PATH . '/app/Controllers/ControladorContratos.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('argus_session');
    session_start();
}

$_SESSION['iniciarSesion'] = 'ok';
$_SESSION['permission'] = 'admin';
$_SESSION['id'] = 77;

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['contrato_id'] = '456';

$templateDir = BASE_PATH . '/tmp';
if (!is_dir($templateDir)) {
    mkdir($templateDir, 0775, true);
}

$templatePath = $templateDir . '/test_contrato_template.docx';
$templateContent = str_repeat('Contrato ${CONTRATO_FOLIO} para ${CLIENTE_NOMBRE}' . PHP_EOL, 80);
file_put_contents($templatePath, $templateContent);

class StubModeloContratos
{
    public static function mdlMostrarContratoPorId(int $id): ?array
    {
        $data = [
            'cliente' => [
                'nombre' => 'Ana Cliente',
            ],
            'desarrollo' => [
                'nombre' => 'Desarrollo Uno',
                'tipo_contrato' => 'VENTA',
            ],
            'contrato' => [
                'folio' => 'CONT-001',
                'tipo_contrato' => 'VENTA',
                'fecha_contrato' => '2025-02-01',
            ],
        ];

        return [
            'id' => $id,
            'datta_contrato' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ];
    }
}

class StubModeloPlantillas
{
    public static function mdlObtenerPlantillaPorTipo(string $tipo): ?array
    {
        return [
            'ruta_archivo' => 'tmp/test_contrato_template.docx',
        ];
    }
}

if (!class_exists('App\Models\ModeloContratos', false)) {
    class_alias(StubModeloContratos::class, 'App\Models\ModeloContratos');
}
if (!class_exists('App\Models\ModeloPlantillas', false)) {
    class_alias(StubModeloPlantillas::class, 'App\Models\ModeloPlantillas');
}

ControladorContratos::ctrGenerarDocumento();
