<?php

declare(strict_types=1);

/**
 * Diagnóstico rápido para validar que el entorno de Hostinger cumpla
 * los requisitos mínimos de la aplicación Argus.
 *
 * Ejecuta este archivo desde CLI (`php diag/hostinger_preflight.php`)
 * o desde el navegador (`https://tu-dominio/diag/hostinger_preflight.php`).
 */

$basePath = dirname(__DIR__);
$isCli = PHP_SAPI === 'cli';

$requirements = [
    [
        'label' => 'PHP 8.1 o superior',
        'status' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'details' => 'Versión detectada: ' . PHP_VERSION,
        'help' => 'Selecciona PHP 8.1 o 8.2 desde hPanel > Avanzado > Configuración de PHP.',
    ],
];

$extensions = [
    'pdo' => 'Extensión base de PDO',
    'pdo_mysql' => 'Driver PDO MySQL',
    'mbstring' => 'Soporte de strings multibyte (dompdf/phpword)',
    'dom' => 'Manipulación de DOM/XML (dompdf/phpword)',
    'xml' => 'Extensión XML requerida por dompdf/phpword',
    'gd' => 'Procesado de imágenes (dompdf/phpword)',
    'zip' => 'Compresión ZIP (descargas DOCX/PDF)',
    'intl' => 'Normalización internacional (validación telefónica)',
    'json' => 'Serialización JSON',
    'ctype' => 'Validaciones de caracteres (vlucas/phpdotenv)',
    'iconv' => 'Conversión de charset (dompdf/phpword)',
];

$extensionChecks = [];
foreach ($extensions as $extension => $description) {
    $extensionChecks[] = [
        'label' => sprintf('Extensión %s', $extension),
        'status' => extension_loaded($extension),
        'details' => $description,
        'help' => sprintf('Habilita %s en hPanel > Configuración de PHP > Extensiones.', $extension),
    ];
}

$paths = [
    [
        'label' => 'Archivo vendor/autoload.php',
        'status' => file_exists($basePath . '/vendor/autoload.php'),
        'details' => 'Se genera con Composer.',
        'help' => 'Ejecuta `composer install --no-dev --optimize-autoloader` en la raíz del proyecto.',
    ],
    [
        'label' => 'Directorio tmp/ con permisos de escritura',
        'status' => is_dir($basePath . '/tmp') && is_writable($basePath . '/tmp'),
        'details' => 'Almacena archivos DOCX/PDF temporales.',
        'help' => 'Asigna permisos 775 o 777 a tmp/ desde el administrador de archivos.',
    ],
];

$envPath = $basePath . '/.env';
$envVariables = file_exists($envPath) ? parseEnvFile($envPath) : [];
$requiredEnvKeys = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
$missingEnvKeys = [];
foreach ($requiredEnvKeys as $key) {
    if (!array_key_exists($key, $envVariables) || $envVariables[$key] === '') {
        $missingEnvKeys[] = $key;
    }
}

$envChecks = [
    [
        'label' => 'Archivo .env configurado',
        'status' => file_exists($envPath),
        'details' => $envPath,
        'help' => 'Copia .env.example a .env y actualiza credenciales de base de datos.',
    ],
    [
        'label' => 'Variables de base de datos completas',
        'status' => empty($missingEnvKeys),
        'details' => empty($missingEnvKeys)
            ? 'Variables presentes: ' . implode(', ', $requiredEnvKeys)
            : 'Faltan: ' . implode(', ', $missingEnvKeys),
        'help' => 'Edita .env y define DB_HOST, DB_NAME, DB_USER y DB_PASS con los valores del servidor.',
    ],
];

bootstrapEnvironmentVariables($envVariables);

[$pdo, $databaseChecks] = diagnosticoBaseDatos($envVariables);
$featureSections = construirSeccionesFuncionales($pdo);

$allSections = [
    'Requisitos de PHP' => $requirements,
    'Extensiones obligatorias' => $extensionChecks,
    'Archivos y permisos' => $paths,
    'Configuración de entorno' => $envChecks,
];

if (!empty($databaseChecks)) {
    $allSections['Base de datos'] = $databaseChecks;
}

foreach ($featureSections as $titulo => $checks) {
    if (!empty($checks)) {
        $allSections[$titulo] = $checks;
    }
}

$overallStatus = true;
foreach ($allSections as $checks) {
    foreach ($checks as $check) {
        if (!$check['status']) {
            $overallStatus = false;
        }
    }
}

renderHeader($overallStatus, $isCli);
foreach ($allSections as $title => $checks) {
    renderSection($title, $checks, $isCli);
}
renderFooter($isCli);

/**
 * Parsea un archivo .env sencillo sin depender de vlucas/phpdotenv.
 *
 * @return array<string, string>
 */
function parseEnvFile(string $path): array
{
    $variables = [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $variables;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($value !== '') {
            if (
                ($value[0] === '"' && str_ends_with($value, '"')) ||
                ($value[0] === "'" && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
        }

        $variables[$key] = $value;
    }

    return $variables;
}

/**
 * Inyecta las variables obtenidas de .env en los superglobales para reutilizarlas.
 *
 * @param array<string, string> $variables
 */
function bootstrapEnvironmentVariables(array $variables): void
{
    foreach ($variables as $key => $value) {
        if ($key === '') {
            continue;
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }

        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

/**
 * Obtiene un valor de entorno buscando en getenv, $_ENV, $_SERVER y el .env parseado.
 *
 * @param array<string, string> $envVariables
 */
function obtenerValorEntorno(string $key, array $envVariables, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    if (array_key_exists($key, $_ENV)) {
        return (string)$_ENV[$key];
    }

    if (array_key_exists($key, $_SERVER)) {
        return (string)$_SERVER[$key];
    }

    if (array_key_exists($key, $envVariables)) {
        return $envVariables[$key];
    }

    return $default;
}

/**
 * Verifica la conexión a la base de datos y retorna el objeto PDO junto con los checks generados.
 *
 * @param array<string, string> $envVariables
 *
 * @return array{0: ?\PDO, 1: array<int, array<string, mixed>>}
 */
function diagnosticoBaseDatos(array $envVariables): array
{
    $checks = [];

    if (!extension_loaded('pdo_mysql')) {
        $checks[] = [
            'label' => 'Conexión a base de datos (MySQL)',
            'status' => false,
            'details' => 'La extensión pdo_mysql no está habilitada.',
            'help' => 'Activa pdo_mysql en hPanel > Configuración de PHP > Extensiones.',
        ];

        return [null, $checks];
    }

    $host = obtenerValorEntorno('DB_HOST', $envVariables, '127.0.0.1');
    $port = obtenerValorEntorno('DB_PORT', $envVariables, '3306');
    $dbName = obtenerValorEntorno('DB_NAME', $envVariables, 'argus');
    $user = obtenerValorEntorno('DB_USER', $envVariables, 'root');
    $password = obtenerValorEntorno('DB_PASS', $envVariables, '');
    $charset = obtenerValorEntorno('DB_CHARSET', $envVariables, 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', (string)$host, (string)$port, (string)$dbName, (string)$charset);

    try {
        $pdo = new \PDO($dsn, (string)$user, (string)$password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $serverVersion = '';
        try {
            $serverVersion = (string)$pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable $ignored) {
            $serverVersion = '';
        }

        $databaseName = '';
        try {
            $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        } catch (\Throwable $ignored) {
            $databaseName = '';
        }

        $detalle = 'Conexión exitosa';
        if ($databaseName !== '') {
            $detalle .= ' a ' . $databaseName;
        } elseif (is_string($dbName) && $dbName !== '') {
            $detalle .= ' a ' . $dbName;
        }

        if ($serverVersion !== '') {
            $detalle .= sprintf(' (MySQL %s)', $serverVersion);
        }

        $checks[] = [
            'label' => 'Conexión a base de datos (MySQL)',
            'status' => true,
            'details' => $detalle,
            'help' => '',
        ];

        return [$pdo, $checks];
    } catch (\PDOException $exception) {
        $checks[] = [
            'label' => 'Conexión a base de datos (MySQL)',
            'status' => false,
            'details' => 'Error: ' . $exception->getMessage(),
            'help' => 'Verifica host, usuario, contraseña y que el servidor MySQL esté activo.',
        ];
    } catch (\Throwable $throwable) {
        $checks[] = [
            'label' => 'Conexión a base de datos (MySQL)',
            'status' => false,
            'details' => 'Error inesperado: ' . $throwable->getMessage(),
            'help' => 'Confirma los parámetros de conexión y permisos del usuario MySQL.',
        ];
    }

    return [null, $checks];
}

/**
 * Construye secciones adicionales con diagnósticos de funcionalidades clave.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function construirSeccionesFuncionales(?\PDO $pdo): array
{
    return [
        'Funcionalidad: Notificaciones' => construirChecksNotificaciones($pdo),
        'Funcionalidad: Filtros y catálogos' => construirChecksCatalogos($pdo),
        'Tablas de gestión y reportes' => construirChecksTablasGestion($pdo),
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function construirChecksNotificaciones(?\PDO $pdo): array
{
    return [
        databaseCheckTable(
            $pdo,
            'argus_notifications',
            'Tabla principal para registrar alertas generadas por las solicitudes.',
            'Ejecuta el script sql/argus.sql para crear argus_notifications y su data inicial.'
        ),
        databaseCheckTable(
            $pdo,
            'argus_notification_destinatarios',
            'Relaciona cada alerta con los usuarios destinatarios.',
            'Importa las tablas de notificaciones incluidas en sql/argus.sql.'
        ),
        databaseCheckColumn(
            $pdo,
            'argus_notification_destinatarios',
            'entregado',
            'Indica si la alerta ya fue entregada al usuario.',
            'Aplica las migraciones de notificaciones para agregar la columna entregado.'
        ),
        databaseCheckColumn(
            $pdo,
            'argus_notification_destinatarios',
            'leido',
            'Permite saber si el usuario marcó la alerta como leída.',
            'Ejecuta la migración sql/migrations/20240701_add_leido_notificaciones.sql en la base de datos.'
        ),
        databaseCheckColumn(
            $pdo,
            'argus_users',
            'notificaciones_activas',
            'Controla si cada usuario tiene habilitadas las notificaciones en su perfil.',
            'Actualiza argus_users con el esquema completo incluido en sql/argus.sql.'
        ),
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function construirChecksCatalogos(?\PDO $pdo): array
{
    return [
        databaseCheckTable(
            $pdo,
            'argus_variables',
            'Contiene los catálogos utilizados para filtros y listas desplegables.',
            'Ejecuta el archivo sql/argus.sql para crear y poblar argus_variables.'
        ),
        databaseCheckCatalog(
            $pdo,
            'argus_variables',
            'tipo_contrato',
            'Tipos de contrato disponibles para plantillas y filtros.',
            'Agrega registros con tipo "tipo_contrato" en la tabla argus_variables.'
        ),
        databaseCheckCatalog(
            $pdo,
            'argus_variables',
            'nacionalidad',
            'Nacionalidades disponibles para las solicitudes.',
            'Agrega registros con tipo "nacionalidad" en la tabla argus_variables.'
        ),
        databaseCheckTable(
            $pdo,
            'argus_desarrollos',
            'Listado de desarrollos inmobiliarios mostrado en formularios y filtros.',
            'Importa la tabla argus_desarrollos desde sql/argus.sql.'
        ),
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function construirChecksTablasGestion(?\PDO $pdo): array
{
    return [
        databaseCheckTable(
            $pdo,
            'argus_solicitudes',
            'Almacena las solicitudes y su historial de cambios.',
            'Ejecuta el script principal sql/argus.sql para crear argus_solicitudes.'
        ),
        databaseCheckColumn(
            $pdo,
            'argus_solicitudes',
            'motivo_retorno',
            'Requerida para registrar devoluciones y generar notificaciones asociadas.',
            'Actualiza la tabla argus_solicitudes ejecutando el script sql/argus.sql más reciente.'
        ),
        databaseCheckColumn(
            $pdo,
            'argus_solicitudes',
            'devuelto_en',
            'Permite ordenar y filtrar devoluciones en los listados.',
            'Asegúrate de aplicar las últimas migraciones o el script sql/argus.sql.'
        ),
        databaseCheckTable(
            $pdo,
            'argus_contratos_data',
            'Contiene los contratos generados y la información estructurada de cada folio.',
            'Importa argus_contratos_data desde sql/argus.sql.'
        ),
        databaseCheckColumn(
            $pdo,
            'argus_contratos_data',
            'estatus',
            'Habilita los filtros de contratos activos, cancelados o archivados.',
            'Aplica las migraciones recientes sobre argus_contratos_data o el script sql/argus.sql.'
        ),
        databaseCheckColumn(
            $pdo,
            'argus_contratos_data',
            'folio',
            'Permite localizar contratos por folio desde la interfaz.',
            'Ejecuta sql/migrations/20240708_unique_folios_rfc.sql para crear la columna e índices asociados.'
        ),
        databaseCheckTable(
            $pdo,
            'argus_clientes',
            'Datos maestros de clientes vinculados a solicitudes y contratos.',
            'Importa la tabla argus_clientes desde sql/argus.sql.'
        ),
    ];
}

function databaseCheckTable(?\PDO $pdo, string $table, string $description, string $help): array
{
    $label = sprintf('Tabla %s', $table);

    if ($pdo === null) {
        return [
            'label' => $label,
            'status' => false,
            'details' => 'No se pudo comprobar la tabla porque no hay conexión a la base de datos.',
            'help' => 'Revisa la configuración de conexión en la sección anterior.',
        ];
    }

    try {
        if (!databaseHasTable($pdo, $table)) {
            return [
                'label' => $label,
                'status' => false,
                'details' => 'No se encontró la tabla en la base de datos.',
                'help' => $help,
            ];
        }

        $count = databaseRowCount($pdo, $table);
        $detail = rtrim($description, '.') . '.';
        if ($count !== null) {
            $detail .= sprintf(' Registros actuales: %d.', $count);
        }

        return [
            'label' => $label,
            'status' => true,
            'details' => $detail,
            'help' => '',
        ];
    } catch (\Throwable $throwable) {
        return [
            'label' => $label,
            'status' => false,
            'details' => 'Error al consultar la tabla: ' . $throwable->getMessage(),
            'help' => $help,
        ];
    }
}

function databaseCheckColumn(?\PDO $pdo, string $table, string $column, string $description, string $help): array
{
    $label = sprintf('Columna %s.%s', $table, $column);

    if ($pdo === null) {
        return [
            'label' => $label,
            'status' => false,
            'details' => 'No se pudo validar la columna porque no hay conexión a la base de datos.',
            'help' => 'Revisa la configuración de conexión en la sección anterior.',
        ];
    }

    try {
        if (!databaseHasTable($pdo, $table)) {
            return [
                'label' => $label,
                'status' => false,
                'details' => sprintf('La tabla %s no está disponible.', $table),
                'help' => $help,
            ];
        }

        $exists = databaseHasColumn($pdo, $table, $column);

        return [
            'label' => $label,
            'status' => $exists,
            'details' => $exists ? rtrim($description, '.') . '.' : 'No se encontró la columna en la tabla.',
            'help' => $exists ? '' : $help,
        ];
    } catch (\Throwable $throwable) {
        return [
            'label' => $label,
            'status' => false,
            'details' => 'Error al consultar la columna: ' . $throwable->getMessage(),
            'help' => $help,
        ];
    }
}

function databaseCheckCatalog(?\PDO $pdo, string $table, string $type, string $description, string $help): array
{
    $label = sprintf('Catálogo %s (%s)', $table, $type);

    if ($pdo === null) {
        return [
            'label' => $label,
            'status' => false,
            'details' => 'No se pudo validar el catálogo porque no hay conexión a la base de datos.',
            'help' => 'Revisa la configuración de conexión en la sección anterior.',
        ];
    }

    try {
        if (!databaseHasTable($pdo, $table)) {
            return [
                'label' => $label,
                'status' => false,
                'details' => sprintf('La tabla %s no existe en la base de datos.', $table),
                'help' => $help,
            ];
        }

        $count = databaseCountWhere($pdo, $table, 'tipo = :tipo', [':tipo' => $type]);
        $status = $count !== null && $count > 0;

        return [
            'label' => $label,
            'status' => $status,
            'details' => $status
                ? sprintf('%s Registros disponibles: %d.', rtrim($description, '.'), (int)$count)
                : 'No hay registros configurados para este tipo.',
            'help' => $status ? '' : $help,
        ];
    } catch (\Throwable $throwable) {
        return [
            'label' => $label,
            'status' => false,
            'details' => 'Error al consultar el catálogo: ' . $throwable->getMessage(),
            'help' => $help,
        ];
    }
}

function databaseHasTable(\PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    $stmt->bindValue(':table', $table, \PDO::PARAM_STR);
    $stmt->execute();

    return (int)$stmt->fetchColumn() > 0;
}

function databaseHasColumn(\PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->bindValue(':table', $table, \PDO::PARAM_STR);
    $stmt->bindValue(':column', $column, \PDO::PARAM_STR);
    $stmt->execute();

    return (int)$stmt->fetchColumn() > 0;
}

function databaseRowCount(\PDO $pdo, string $table): ?int
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return null;
    }

    $sql = sprintf('SELECT COUNT(*) FROM `%s`', $table);
    $stmt = $pdo->query($sql);
    if ($stmt === false) {
        return null;
    }

    $count = $stmt->fetchColumn();

    return $count === false ? null : (int)$count;
}

/**
 * @param array<string, scalar|null> $params
 */
function databaseCountWhere(\PDO $pdo, string $table, string $where, array $params): ?int
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return null;
    }

    $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $table, $where);
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    if (!$stmt->execute()) {
        return null;
    }

    $count = $stmt->fetchColumn();

    return $count === false ? null : (int)$count;
}

function renderHeader(bool $overallStatus, bool $isCli): void
{
    $statusText = $overallStatus ? 'LISTO PARA PRODUCCIÓN' : 'REQUERIMIENTOS PENDIENTES';
    $statusIcon = $overallStatus ? '✅' : '⚠️';

    if ($isCli) {
        echo PHP_EOL;
        echo str_repeat('=', 60) . PHP_EOL;
        echo sprintf('%s %s%s', $statusIcon, $statusText, PHP_EOL);
        echo str_repeat('=', 60) . PHP_EOL . PHP_EOL;
        return;
    }

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">';
    echo '<title>Diagnóstico Argus</title>';
    echo '<style>body{font-family:system-ui, sans-serif;background:#f9fafb;color:#111827;padding:24px;}';
    echo 'h1{font-size:1.5rem;margin-bottom:1rem;}section{margin-bottom:1.5rem;}';
    echo 'li{margin:0.25rem 0;}code{background:#eef2ff;padding:0.1rem 0.25rem;border-radius:4px;}';
    echo '.ok{color:#047857;}.fail{color:#b91c1c;} .help{color:#6b7280;font-size:0.9rem;margin-left:1.8rem;}';
    echo '</style></head><body>';
    echo sprintf('<h1>%s %s</h1>', $statusIcon, $statusText);
}

function renderSection(string $title, array $checks, bool $isCli): void
{
    if ($isCli) {
        echo strtoupper($title) . PHP_EOL;
        foreach ($checks as $check) {
            $statusIcon = $check['status'] ? '✅' : '❌';
            echo sprintf('  %s %s', $statusIcon, $check['label']) . PHP_EOL;
            if (!empty($check['details'])) {
                echo '      - ' . $check['details'] . PHP_EOL;
            }
            if (!$check['status'] && !empty($check['help'])) {
                echo '      > ' . $check['help'] . PHP_EOL;
            }
        }
        echo PHP_EOL;
        return;
    }

    echo '<section>';
    echo sprintf('<h2>%s</h2><ul>', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));
    foreach ($checks as $check) {
        $class = $check['status'] ? 'ok' : 'fail';
        $icon = $check['status'] ? '✅' : '❌';
        $label = htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8');
        $details = htmlspecialchars((string)($check['details'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo sprintf('<li class="%s">%s <strong>%s</strong>', $class, $icon, $label);
        if ($details !== '') {
            echo sprintf(' — %s', $details);
        }
        echo '</li>';
        if (!$check['status'] && !empty($check['help'])) {
            $help = htmlspecialchars((string)$check['help'], ENT_QUOTES, 'UTF-8');
            echo sprintf('<div class="help">%s</div>', $help);
        }
    }
    echo '</ul></section>';
}

function renderFooter(bool $isCli): void
{
    if ($isCli) {
        echo str_repeat('=', 60) . PHP_EOL;
        echo 'Ejecuta nuevamente este diagnóstico después de aplicar los cambios.' . PHP_EOL;
        echo str_repeat('=', 60) . PHP_EOL;
        return;
    }

    echo '<p>Ejecuta nuevamente este diagnóstico después de aplicar los cambios.</p>';
    echo '</body></html>';
}
