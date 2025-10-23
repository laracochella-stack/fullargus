<?php

declare(strict_types=1);

use App\Controllers\ControladorClientes;
use App\Controllers\ControladorContratos;
use App\Controllers\ControladorDesarrollos;
use App\Controllers\ControladorParametros;
use App\Controllers\ControladorSolicitudes;
use App\Controllers\ControladorUsuarios;

require __DIR__ . '/../bootstrap/app.php';

header('Content-Type: application/json; charset=utf-8');

function ag_json_response(array $payload, int $status = 200): void {
    http_response_code($status);

    $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    $json = json_encode($payload, $options);

    if ($json === false) {
        $fallback = [
            'error' => 'No se pudo generar la respuesta JSON solicitada.',
            'codigo' => json_last_error(),
            'mensaje' => json_last_error_msg(),
        ];

        $json = json_encode($fallback, $options);
    }

    echo $json === false ? '{"error":"Respuesta no disponible"}' : $json;
    exit;
}

if (!isset($_SESSION['iniciarSesion']) || $_SESSION['iniciarSesion'] !== 'ok') {
    ag_json_response(['error' => 'Sesión no válida'], 401);
}

$resource = isset($_GET['resource']) ? (string)$_GET['resource'] : '';
if ($resource === '') {
    ag_json_response(['error' => 'Recurso no especificado'], 400);
}

$permission = strtolower((string)($_SESSION['permission'] ?? 'user'));
$basePath = BASE_PATH;
$csrfToken = (string)($_SESSION['csrf_token'] ?? '');

$escape = static function (?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$formatDate = static function (?string $date): string {
    if (empty($date)) {
        return '';
    }

    $timestamp = strtotime((string)$date);
    if ($timestamp === false) {
        return (string)$date;
    }

    return date('d-m-Y', $timestamp);
};

$buildAttrString = static function (array $attributes): string {
    $string = '';
    foreach ($attributes as $attribute => $value) {
        if ($value === null) {
            continue;
        }

        $string .= sprintf(' %s="%s"', $attribute, (string)$value);
    }

    return $string;
};

$renderActionButtons = static function (array $items, array $options = []): string {
    static $actionGroupCounter = 0;

    $filteredItems = [];
    foreach ($items as $item) {
        $trimmed = trim((string)$item);
        if ($trimmed !== '') {
            $filteredItems[] = $trimmed;
        }
    }

    if (empty($filteredItems)) {
        return '';
    }

    $actionGroupCounter++;

    $primaryCount = isset($options['primary']) ? (int)$options['primary'] : 2;
    if ($primaryCount < 0) {
        $primaryCount = 0;
    }

    $toolbarClass = $options['toolbar_class'] ?? 'ag-action-toolbar';
    $primaryGroupClass = $options['group_class'] ?? 'ag-action-primary';
    $secondaryGroupClass = $options['secondary_group_class'] ?? 'dropdown ag-action-secondary';
    $dropdownMenuClass = $options['dropdown_menu_class'] ?? 'dropdown-menu dropdown-menu-end ag-actions-dropdown';
    $dropdownItemWrapperClass = $options['dropdown_item_wrapper_class'] ?? 'dropdown-item-text ag-actions-dropdown-item';

    $primary = $primaryCount > 0 ? array_slice($filteredItems, 0, $primaryCount) : [];
    $secondary = $primaryCount > 0 ? array_slice($filteredItems, $primaryCount) : $filteredItems;

    if ($primaryCount === 0) {
        $primary = [];
        $secondary = $filteredItems;
    }

    $html = sprintf('<div class="%s" role="toolbar" aria-label="Acciones">', $toolbarClass);

    if (!empty($primary)) {
        $primaryGroupClasses = trim($primaryGroupClass . ' d-none d-md-flex');
        $html .= sprintf('<div class="%s" role="group">', $primaryGroupClasses);
        foreach ($primary as $buttonHtml) {
            $html .= '<div class="ag-action-item">' . $buttonHtml . '</div>';
        }
        $html .= '</div>';
    }

    $hasSecondary = !empty($secondary);
    if (!empty($primary) || $hasSecondary) {
        $toggleId = sprintf('agActionsToggle%d', $actionGroupCounter);
        $dropdownGroupClasses = $secondaryGroupClass;
        $toggleClasses = 'btn btn-outline-secondary btn-sm dropdown-toggle ag-actions-dropdown-toggle';

        if (!$hasSecondary) {
            $dropdownGroupClasses .= ' d-inline-flex d-md-none';
            $toggleClasses .= ' d-inline-flex d-md-none';
        }

        $dropdownGroupClasses = trim($dropdownGroupClasses);
        $html .= sprintf('<div class="%s" role="group">', $dropdownGroupClasses);
        $html .= sprintf(
            '<button type="button" class="%s" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-haspopup="true" id="%s">Más</button>',
            $toggleClasses,
            $toggleId
        );
        $html .= sprintf('<div class="%s" aria-labelledby="%s">', $dropdownMenuClass, $toggleId);

        foreach ($primary as $primaryHtml) {
            $primaryDropdownClass = trim($dropdownItemWrapperClass . ' d-md-none ag-actions-dropdown-item-primary');
            $html .= sprintf('<div class="%s"><div class="ag-action-item">%s</div></div>', $primaryDropdownClass, $primaryHtml);
        }

        foreach ($secondary as $secondaryHtml) {
            $html .= sprintf('<div class="%s"><div class="ag-action-item">%s</div></div>', $dropdownItemWrapperClass, $secondaryHtml);
        }

        $html .= '</div></div>';
    }

    $html .= '</div>';

    return $html;
};

$renderActionMenu = static function (array $items): string {
    $bootstrapClasses = [
        'btn',
        'btn-sm',
        'btn-xs',
        'btn-primary',
        'btn-secondary',
        'btn-success',
        'btn-danger',
        'btn-warning',
        'btn-info',
        'btn-light',
        'btn-dark',
        'btn-outline-primary',
        'btn-outline-secondary',
        'btn-outline-success',
        'btn-outline-danger',
        'btn-outline-warning',
        'btn-outline-info',
        'btn-outline-light',
        'btn-outline-dark',
    ];

    $menuItems = [];

    foreach ($items as $itemHtml) {
        $itemHtml = trim((string)$itemHtml);
        if ($itemHtml === '') {
            continue;
        }

        $wrapperHtml = '<div>' . $itemHtml . '</div>';
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        if (!$doc->loadHTML($wrapperHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            continue;
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $container = $doc->documentElement;
        if (!$container) {
            continue;
        }

        $actionElement = null;
        foreach ($container->childNodes as $childNode) {
            if ($childNode->nodeType === XML_ELEMENT_NODE) {
                $actionElement = $childNode;
                break;
            }
        }

        if (!$actionElement) {
            continue;
        }

        $controlElement = $actionElement;
        if (strcasecmp($actionElement->nodeName, 'form') === 0) {
            foreach ($actionElement->childNodes as $childNode) {
                if ($childNode->nodeType === XML_ELEMENT_NODE) {
                    $controlElement = $childNode;
                    break;
                }
            }
        }

        if (!$controlElement) {
            continue;
        }

        $classAttr = $controlElement->getAttribute('class');
        $classList = $classAttr !== '' ? preg_split('/\s+/', $classAttr) : [];
        $classList = array_filter($classList, static function ($class) use ($bootstrapClasses) {
            return $class !== '' && !in_array($class, $bootstrapClasses, true);
        });
        $classList[] = 'ag-action-menu-trigger-control';
        $classList = array_values(array_unique($classList));
        $controlElement->setAttribute('class', implode(' ', $classList));

        if (strcasecmp($controlElement->nodeName, 'button') === 0 && !$controlElement->hasAttribute('type')) {
            $controlElement->setAttribute('type', 'button');
        }

        $labelElement = $controlElement;
        if (strcasecmp($controlElement->nodeName, 'form') === 0) {
            $labelElement = $controlElement;
        }

        if (!$labelElement) {
            continue;
        }

        $label = trim((string)$labelElement->getAttribute('title'));
        if ($label === '') {
            $label = trim((string)$labelElement->textContent);
        }
        if ($label === '') {
            $label = 'Acción';
        }

        $textNodes = [];
        foreach ($controlElement->childNodes as $childNode) {
            if ($childNode->nodeType === XML_TEXT_NODE) {
                $texto = trim((string)$childNode->textContent);
                if ($texto !== '') {
                    $textNodes[] = $childNode;
                }
            }
        }

        foreach ($textNodes as $textNode) {
            $controlElement->removeChild($textNode);
        }

        $hasVisualContent = false;
        foreach ($controlElement->childNodes as $childNode) {
            if ($childNode->nodeType === XML_ELEMENT_NODE) {
                $hasVisualContent = true;
                break;
            }
            if ($childNode->nodeType === XML_TEXT_NODE && trim((string)$childNode->textContent) !== '') {
                $hasVisualContent = true;
                break;
            }
        }

        if (!$hasVisualContent && $label !== '') {
            $initial = function_exists('mb_substr')
                ? mb_substr($label, 0, 1, 'UTF-8')
                : substr($label, 0, 1);
            if ($initial === false) {
                $initial = '';
            }
            $initial = function_exists('mb_strtoupper')
                ? mb_strtoupper($initial, 'UTF-8')
                : strtoupper((string)$initial);
            if ($initial !== '') {
                $initialSpan = $doc->createElement('span', $initial);
                if ($initialSpan) {
                    $initialSpan->setAttribute('class', 'ag-action-menu-trigger-initial');
                    $controlElement->appendChild($initialSpan);
                }
            }
        }

        $actionMarkup = $doc->saveHTML($actionElement);
        if ($actionMarkup === false) {
            continue;
        }

        $menuItems[] = sprintf(
            '<li class="ag-action-menu-item" tabindex="0">%s<div class="ag-action-menu-label">%s</div></li>',
            $actionMarkup,
            htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }

    if (empty($menuItems)) {
        return '';
    }

    return '<ul class="ag-action-menu list-unstyled mb-0">' . implode('', $menuItems) . '</ul>';
};

switch ($resource) {
    case 'clientes':
        if (!in_array($permission, ['moderator', 'senior', 'owner', 'admin'], true)) {
            ag_json_response(['error' => 'Permisos insuficientes'], 403);
        }

        $clientes = ControladorClientes::ctrMostrarClientes();

        $nacionalidades = [];
        if (class_exists(ControladorParametros::class)) {
            $variables = ControladorParametros::ctrMostrarVariables('nacionalidad');
            foreach ($variables as $item) {
                $nacionalidades[$item['identificador']] = $item['nombre'];
            }
        }

        $estadoFiltro = isset($_GET['estado']) ? strtolower((string)$_GET['estado']) : 'activos';
        if (!in_array($estadoFiltro, ['activos', 'archivados', 'todos'], true)) {
            $estadoFiltro = 'activos';
        }

        $nacionalidadFiltro = '';
        if (isset($_GET['nacionalidad'])) {
            $valorNac = trim((string)$_GET['nacionalidad']);
            if ($valorNac !== '') {
                foreach ($nacionalidades as $identificador => $nombreNacionalidad) {
                    if (strcasecmp($valorNac, (string)$identificador) === 0 || strcasecmp($valorNac, (string)$nombreNacionalidad) === 0) {
                        $nacionalidadFiltro = (string)$identificador;
                        break;
                    }
                }
            }
        }

        $textoFiltro = '';
        if (isset($_GET['q'])) {
            $textoFiltro = (string)$_GET['q'];
        } elseif (isset($_GET['busqueda'])) {
            $textoFiltro = (string)$_GET['busqueda'];
        }
        $textoFiltro = trim(preg_replace('/\s+/u', ' ', $textoFiltro));
        if (mb_strlen($textoFiltro) > 120) {
            $textoFiltro = mb_substr($textoFiltro, 0, 120);
        }

        $data = [];
        foreach ($clientes as $cliente) {
            $id = (int)($cliente['id'] ?? 0);
            $nombre = $escape($cliente['nombre'] ?? '');
            $email = $escape($cliente['email'] ?? '');
            $nacionalidadId = $escape($cliente['nacionalidad'] ?? '');
            $nacionalidadNombre = $escape($nacionalidades[$cliente['nacionalidad']] ?? (string)($cliente['nacionalidad'] ?? ''));
            $fechaIso = $escape($cliente['fecha_nacimiento'] ?? '');
            $fechaTexto = $escape($formatDate($cliente['fecha_nacimiento'] ?? ''));
            $estado = strtolower($escape($cliente['estado'] ?? 'activo'));
            $estadoSeguro = in_array($estado, ['archivado', 'activo'], true) ? $estado : 'activo';
            $badgeClass = $estadoSeguro === 'archivado' ? 'bg-secondary' : 'bg-success';
            $estadoTexto = $estadoSeguro === 'archivado' ? 'Archivado' : 'Activo';
            $estadoHtml = sprintf('<span class="badge %s">%s</span>', $badgeClass, $estadoTexto);

            if ($estadoFiltro === 'activos' && $estadoSeguro !== 'activo') {
                continue;
            }
            if ($estadoFiltro === 'archivados' && $estadoSeguro !== 'archivado') {
                continue;
            }

            if ($nacionalidadFiltro !== '') {
                if (strcasecmp((string)($cliente['nacionalidad'] ?? ''), $nacionalidadFiltro) !== 0) {
                    continue;
                }
            }

            if ($textoFiltro !== '') {
                $camposBusqueda = [
                    (string)($cliente['nombre'] ?? ''),
                    (string)($cliente['email'] ?? ''),
                    (string)($cliente['rfc'] ?? ''),
                    (string)($cliente['curp'] ?? ''),
                    (string)($cliente['ine'] ?? ''),
                    (string)($cliente['telefono'] ?? ''),
                ];

                $coincide = false;
                foreach ($camposBusqueda as $campoBusqueda) {
                    if ($campoBusqueda !== '' && stripos($campoBusqueda, $textoFiltro) !== false) {
                        $coincide = true;
                        break;
                    }
                }

                if (!$coincide) {
                    continue;
                }
            }

        $rowAttrs = [
            'data-id' => (string)$id,
            'data-nombre' => $nombre,
            'data-nacionalidad-id' => $nacionalidadId,
            'data-nacionalidad-nombre' => $nacionalidadNombre,
            'data-fecha' => $fechaIso,
            'data-fecha-iso' => $fechaIso,
            'data-fecha-texto' => $fechaTexto,
            'data-rfc' => $escape($cliente['rfc'] ?? ''),
            'data-curp' => $escape($cliente['curp'] ?? ''),
            'data-ine' => $escape($cliente['ine'] ?? ''),
            'data-estado_civil' => $escape($cliente['estado_civil'] ?? ''),
            'data-ocupacion' => $escape($cliente['ocupacion'] ?? ''),
            'data-telefono' => $escape($cliente['telefono'] ?? ''),
            'data-domicilio' => $escape($cliente['domicilio'] ?? ''),
            'data-email' => $email,
            'data-beneficiario' => $escape($cliente['beneficiario'] ?? ''),
            'data-estado' => $estadoSeguro,
        ];

        $attrString = $buildAttrString($rowAttrs);

            $nombreLink = sprintf(
                '<a href="#" class="verClienteNombre" data-bs-toggle="modal" data-bs-target="#modalVerCliente"%s>%s</a>',
                $attrString,
                $nombre
            );

            $accionesBotones = [];
            $accionesBotones[] = sprintf(
                '<button type="button" class="btn btn-warning btn-sm btnVerCliente" data-bs-toggle="modal" data-bs-target="#modalVerCliente"%s>'
                . '<i class="fas fa-eye"></i>'
                . '</button>',
                $attrString
            );
            $accionesBotones[] = sprintf(
                '<button type="button" class="btn btn-primary btn-sm btnEditarCliente" data-bs-toggle="modal" data-bs-target="#modalEditarCliente"%s>'
                . '<i class="fas fa-pencil-alt"></i>'
                . '</button>',
                $attrString
            );
            $accionesBotones[] = sprintf(
                '<a href="index.php?ruta=crearContrato&amp;cliente_id=%d" class="btn btn-success btn-sm">Crear contrato</a>',
                $id
            );
            $accionesBotones[] = sprintf(
                '<a href="index.php?ruta=contratos&amp;cliente_id=%d" class="btn btn-info btn-sm">Ver contratos</a>',
                $id
            );

            if (in_array($permission, ['moderator', 'senior', 'owner', 'admin'], true)) {
                $accionesBotones[] = sprintf(
                    '<button type="button" class="btn btn-sm %s btnCambiarEstadoCliente" data-id="%d" data-nombre="%s" data-estado-actual="%s" data-estado-destino="%s">'
                    . '<i class="fas %s"></i>'
                    . '</button>',
                    $estadoSeguro === 'archivado' ? 'btn-outline-success' : 'btn-outline-secondary',
                    $id,
                    $nombre,
                    $estadoSeguro,
                    $estadoSeguro === 'archivado' ? 'activo' : 'archivado',
                    $estadoSeguro === 'archivado' ? 'fa-rotate-left' : 'fa-box-archive'
                );
            }

            $accionesMenu = $renderActionMenu($accionesBotones);
            $acciones = $renderActionButtons($accionesBotones, ['primary' => 3]);

        $data[] = [
            'id' => (string)$id,
            'nombre' => $nombreLink,
            'email' => $email,
            'estado' => $estadoHtml,
            'acciones' => $acciones,
            'acciones_menu' => $accionesMenu,
            'DT_RowAttr' => $rowAttrs,
            'DT_RowClass' => $estadoSeguro === 'archivado' ? 'table-secondary ag-cliente-archivado' : '',
        ];
        }

        ag_json_response(['data' => $data]);

    case 'desarrollos':
        if (!in_array($permission, ['senior', 'owner', 'admin'], true)) {
            ag_json_response(['error' => 'Permisos insuficientes'], 403);
        }

        $desarrollos = ControladorDesarrollos::ctrMostrarDesarrollos();
        $tiposContrato = [];
        if (class_exists(ControladorParametros::class)) {
            $varsTipo = ControladorParametros::ctrMostrarVariables('tipo_contrato');
            foreach ($varsTipo as $var) {
                $tiposContrato[$var['identificador']] = $var['nombre'];
            }
        }

        $data = [];
        foreach ($desarrollos as $des) {
            $id = (int)($des['id'] ?? 0);
            $nombre = $escape($des['nombre'] ?? '');
            $tipoId = $escape($des['tipo_contrato'] ?? '');
            $tipoNombre = $escape($tiposContrato[$des['tipo_contrato']] ?? (string)($des['tipo_contrato'] ?? ''));
            $rowAttrs = [
                'data-id' => (string)$id,
                'data-nombre' => $nombre,
                'data-tipocontrato-id' => $tipoId,
                'data-tipocontrato-nombre' => $tipoNombre,
                'data-descripcion' => $escape($des['descripcion'] ?? ''),
                'data-superficie' => $escape($des['superficie'] ?? ''),
                'data-clave' => $escape($des['clave_catastral'] ?? ''),
                'data-lotes' => $escape($des['lotes_disponibles'] ?? ''),
                'data-preciolote' => $escape((string)($des['precio_lote'] ?? '')),
                'data-preciototal' => $escape((string)($des['precio_total'] ?? '')),
            ];
            $attrString = $buildAttrString($rowAttrs);

            $accionesBotones = [
                sprintf(
                    '<button type="button" class="btn btn-warning btn-sm btnVerDesarrollo" data-bs-toggle="modal" data-bs-target="#modalVerDesarrollo"%s>'
                    . '<i class="fas fa-eye"></i>'
                    . '</button>',
                    $attrString
                ),
                sprintf(
                    '<button type="button" class="btn btn-primary btn-sm btnEditarDesarrollo" data-bs-toggle="modal" data-bs-target="#modalEditarDesarrollo"%s>'
                    . '<i class="fas fa-pencil-alt"></i>'
                    . '</button>',
                    $attrString
                ),
            ];

            if ($permission === 'admin') {
                $accionesBotones[] = sprintf(
                    '<form method="post" action="index.php?ruta=desarrollos&accion=eliminarDesarrollo"'
                    . ' class="d-inline js-form-eliminar-desarrollo" data-nombre="%1$s">'
                    . '<input type="hidden" name="csrf_token" value="%2$s">'
                    . '<input type="hidden" name="eliminarDesarrollo" value="1">'
                    . '<input type="hidden" name="desarrollo_id" value="%3$d">'
                    . '<button type="submit" class="btn btn-danger btn-sm" title="Eliminar desarrollo">'
                    . '<i class="fas fa-trash"></i>'
                    . '</button>'
                    . '</form>',
                    $escape($des['nombre'] ?? ''),
                    $escape($csrfToken),
                    $id
                );
            }

            $accionesMenu = $renderActionMenu($accionesBotones);
            $acciones = $renderActionButtons($accionesBotones, ['primary' => 2]);

            $data[] = [
                'id' => (string)$id,
                'nombre' => $nombre,
                'tipo' => $tipoNombre,
                'acciones' => $acciones,
                'acciones_menu' => $accionesMenu,
                'DT_RowAttr' => $rowAttrs,
            ];
        }

        ag_json_response(['data' => $data]);

    case 'nacionalidades':
        if (!in_array($permission, ['senior', 'owner', 'admin'], true)) {
            ag_json_response(['error' => 'Permisos insuficientes'], 403);
        }

        $nacionalidades = ControladorParametros::ctrMostrarVariables('nacionalidad');
        $data = [];
        foreach ($nacionalidades as $nac) {
            $id = (int)($nac['id'] ?? 0);
            $identificador = $escape($nac['identificador'] ?? '');
            $nombre = $escape($nac['nombre'] ?? '');
            $accionesBotones = [
                sprintf(
                    '<button type="button" class="btn btn-primary btn-sm btnEditarVariable" data-bs-toggle="modal" data-bs-target="#modalEditarVariable"'
                    . ' data-id="%1$d" data-identificador="%2$s" data-nombre="%3$s" title="Editar nacionalidad">'
                    . '<i class="fas fa-pencil-alt"></i>'
                    . '</button>',
                    $id,
                    $identificador,
                    $nombre
                ),
            ];

            if (in_array($permission, ['senior', 'owner', 'admin'], true)) {
                $accionesBotones[] = sprintf(
                    '<form method="post" class="d-inline" data-ag-confirm="¿Eliminar esta nacionalidad?"'
                    . ' data-ag-confirm-title="Eliminar nacionalidad" data-ag-confirm-icon="warning"'
                    . ' data-ag-confirm-accept="Sí, eliminar" data-ag-confirm-cancel="Cancelar">'
                    . '<input type="hidden" name="csrf_token" value="%4$s">'
                    . '<input type="hidden" name="eliminarVariable" value="1">'
                    . '<input type="hidden" name="variable_tipo" value="nacionalidad">'
                    . '<input type="hidden" name="variable_id" value="%1$d">'
                    . '<button type="submit" class="btn btn-danger btn-sm" title="Eliminar nacionalidad"><i class="fas fa-trash"></i></button>'
                    . '</form>',
                    $id,
                    $identificador,
                    $nombre,
                    $escape($csrfToken)
                );
            }

            $accionesMenu = $renderActionMenu($accionesBotones);
            $acciones = $renderActionButtons($accionesBotones, ['primary' => 1]);

            $data[] = [
                'id' => (string)$id,
                'identificador' => $identificador,
                'nombre' => $nombre,
                'acciones' => $acciones,
                'acciones_menu' => $accionesMenu,
            ];
        }

        ag_json_response(['data' => $data]);

    case 'tipos_contrato':
        if (!in_array($permission, ['senior', 'owner', 'admin'], true)) {
            ag_json_response(['error' => 'Permisos insuficientes'], 403);
        }

        $tipos = ControladorParametros::ctrMostrarVariables('tipo_contrato');
        $data = [];
        foreach ($tipos as $tip) {
            $id = (int)($tip['id'] ?? 0);
            $identificador = $escape($tip['identificador'] ?? '');
            $nombre = $escape($tip['nombre'] ?? '');
            $accionesBotones = [
                sprintf(
                    '<button type="button" class="btn btn-primary btn-sm btnEditarVariable" data-bs-toggle="modal" data-bs-target="#modalEditarVariable"'
                    . ' data-id="%1$d" data-identificador="%2$s" data-nombre="%3$s" title="Editar tipo de contrato">'
                    . '<i class="fas fa-pencil-alt"></i>'
                    . '</button>',
                    $id,
                    $identificador,
                    $nombre
                ),
            ];

            if (in_array($permission, ['senior', 'owner', 'admin'], true)) {
                $accionesBotones[] = sprintf(
                    '<form method="post" class="d-inline" data-ag-confirm="¿Eliminar este tipo de contrato?"'
                    . ' data-ag-confirm-title="Eliminar tipo de contrato" data-ag-confirm-icon="warning"'
                    . ' data-ag-confirm-accept="Sí, eliminar" data-ag-confirm-cancel="Cancelar">'
                    . '<input type="hidden" name="csrf_token" value="%4$s">'
                    . '<input type="hidden" name="eliminarVariable" value="1">'
                    . '<input type="hidden" name="variable_tipo" value="tipo_contrato">'
                    . '<input type="hidden" name="variable_id" value="%1$d">'
                    . '<button type="submit" class="btn btn-danger btn-sm" title="Eliminar tipo de contrato"><i class="fas fa-trash"></i></button>'
                    . '</form>',
                    $id,
                    $identificador,
                    $nombre,
                    $escape($csrfToken)
                );
            }

            $accionesMenu = $renderActionMenu($accionesBotones);
            $acciones = $renderActionButtons($accionesBotones, ['primary' => 1]);

            $data[] = [
                'id' => (string)$id,
                'identificador' => $identificador,
                'nombre' => $nombre,
                'acciones' => $acciones,
                'acciones_menu' => $accionesMenu,
            ];
        }

        ag_json_response(['data' => $data]);

    case 'plantillas_contrato':
        if (!in_array($permission, ['senior', 'owner', 'admin'], true)) {
            ag_json_response(['error' => 'Permisos insuficientes'], 403);
        }

        $plantillas = ControladorParametros::ctrMostrarPlantillas();
        $data = [];
        foreach ($plantillas as $plantilla) {
            $id = (int)($plantilla['id'] ?? 0);
            $tipo = $escape($plantilla['nombre_tipo'] ?? '');
            $nombre = $escape($plantilla['nombre_archivo'] ?? '');
            $ruta = (string)($plantilla['ruta_archivo'] ?? '');
            $rutaNormalizada = '/' . ltrim($ruta, '/');
            $existe = $ruta !== '' && is_file($basePath . '/' . ltrim($ruta, '/'));
            $archivoHtml = $existe
                ? sprintf('<a href="%1$s" target="_blank" rel="noopener" download="%2$s">Descargar</a>', $escape($rutaNormalizada), $nombre)
                : '<span class="text-danger">Archivo no disponible</span>';

            $accionesBotones = [
                sprintf(
                    '<button type="button" class="btn btn-primary btn-sm btnEditarPlantilla" data-bs-toggle="modal" data-bs-target="#modalEditarPlantilla"'
                    . ' data-id="%1$d" data-tipo-id="%2$d" data-nombre="%3$s" title="Editar plantilla">'
                    . '<i class="fas fa-pencil-alt"></i>'
                    . '</button>',
                    $id,
                    (int)($plantilla['tipo_contrato_id'] ?? 0),
                    $nombre
                ),
                sprintf(
                    '<form method="post" class="d-inline-block" data-ag-confirm="¿Estás seguro de eliminar esta plantilla?"'
                    . ' data-ag-confirm-title="Eliminar plantilla" data-ag-confirm-icon="warning"'
                    . ' data-ag-confirm-accept="Sí, eliminar" data-ag-confirm-cancel="Cancelar">'
                    . '<input type="hidden" name="csrf_token" value="%1$s">'
                    . '<input type="hidden" name="eliminarPlantilla" value="1">'
                    . '<input type="hidden" name="plantilla_id" value="%2$d">'
                    . '<button type="submit" class="btn btn-danger btn-sm" title="Eliminar plantilla"><i class="fas fa-trash"></i></button>'
                    . '</form>',
                    $escape($csrfToken),
                    $id
                ),
            ];

            $accionesMenu = $renderActionMenu($accionesBotones);
            $acciones = $renderActionButtons($accionesBotones, ['primary' => 1]);

            $data[] = [
                'id' => (string)$id,
                'tipo' => $tipo,
                'nombre' => $nombre,
                'archivo' => $archivoHtml,
                'acciones' => $acciones,
                'acciones_menu' => $accionesMenu,
            ];
        }

        ag_json_response(['data' => $data]);

    case 'plantillas_solicitud':
        if (!in_array($permission, ['senior', 'owner', 'admin'], true)) {
            ag_json_response(['error' => 'Permisos insuficientes'], 403);
        }

        $plantillasSolicitud = ControladorParametros::ctrMostrarPlantillasSolicitud();
        $data = [];
        foreach ($plantillasSolicitud as $plantilla) {
            $id = (int)($plantilla['id'] ?? 0);
            $tipoOriginal = strtolower((string)($plantilla['tipo'] ?? ''));
            $tipoAttr = $escape($tipoOriginal);
            $tipoEtiqueta = $tipoOriginal !== '' ? strtoupper($tipoOriginal) : '';
            $tipo = $escape($tipoEtiqueta);
            $nombre = $escape($plantilla['nombre_archivo'] ?? '');
            $ruta = (string)($plantilla['ruta_archivo'] ?? '');
            $rutaNormalizada = '/' . ltrim($ruta, '/');
            $existe = $ruta !== '' && is_file($basePath . '/' . ltrim($ruta, '/'));
            $archivoHtml = $existe
                ? sprintf('<a href="%1$s" target="_blank" rel="noopener" download="%2$s">Descargar</a>', $escape($rutaNormalizada), $nombre)
                : '<span class="text-danger">Archivo no disponible</span>';

            $accionesBotones = [
                sprintf(
                    '<button type="button" class="btn btn-secondary btn-sm btnEditarPlantillaSolicitud" data-bs-toggle="modal" data-bs-target="#modalEditarPlantillaSolicitud"'
                    . ' data-id="%1$d" data-tipo="%2$s" data-nombre="%3$s" title="Editar plantilla de solicitud">'
                    . '<i class="fas fa-edit"></i>'
                    . '</button>',
                    $id,
                    $tipoAttr,
                    $nombre
                ),
                sprintf(
                    '<form method="post" class="d-inline-block" data-ag-confirm="¿Eliminar plantilla de solicitud?"'
                    . ' data-ag-confirm-title="Eliminar plantilla" data-ag-confirm-icon="warning"'
                    . ' data-ag-confirm-accept="Sí, eliminar" data-ag-confirm-cancel="Cancelar">'
                    . '<input type="hidden" name="csrf_token" value="%1$s">'
                    . '<input type="hidden" name="eliminarPlantillaSolicitud" value="1">'
                    . '<input type="hidden" name="plantilla_solicitud_id" value="%2$d">'
                    . '<button type="submit" class="btn btn-danger btn-sm" title="Eliminar plantilla"><i class="fas fa-trash"></i></button>'
                    . '</form>',
                    $escape($csrfToken),
                    $id
                ),
            ];

            $accionesMenu = $renderActionMenu($accionesBotones);
            $acciones = $renderActionButtons($accionesBotones, ['primary' => 1]);

            $data[] = [
                'id' => (string)$id,
                'tipo' => $tipo,
                'nombre' => $nombre,
                'archivo' => $archivoHtml,
                'acciones' => $acciones,
                'acciones_menu' => $accionesMenu,
            ];
        }

        ag_json_response(['data' => $data]);

    case 'usuarios':
        if (!in_array($permission, ['owner', 'admin'], true)) {
            ag_json_response(['error' => 'Permisos insuficientes'], 403);
        }

        $usuarios = ControladorUsuarios::ctrMostrarUsuarios();
        $rolesEtiquetas = [
            'user' => 'Usuario',
            'moderator' => 'Moderador',
            'senior' => 'Senior',
            'owner' => 'Owner',
            'admin' => 'Administrador',
        ];

        $sesionId = (int)($_SESSION['id'] ?? 0);
        $csrf = $escape($csrfToken);

        $data = [];
        foreach ($usuarios as $usuario) {
            $id = (int)($usuario['id'] ?? 0);
            $nombreCompleto = trim((string)($usuario['nombre_completo'] ?? ($usuario['nombre_corto'] ?? '')));
            $nombreMostrar = $nombreCompleto !== '' ? $nombreCompleto : (string)($usuario['nombre_corto'] ?? '');
            $username = (string)($usuario['username'] ?? '');
            $email = (string)($usuario['email'] ?? '');
            $permiso = strtolower((string)($usuario['permission'] ?? 'user'));
            $permisoEtiqueta = $rolesEtiquetas[$permiso] ?? ucfirst($permiso);
            $permisoHtml = sprintf('<span class="badge bg-light text-dark fw-semibold">%s</span>', $escape($permisoEtiqueta));
            $notificacionesActivas = !empty($usuario['notificaciones_activas']);
            $notificacionesHtml = $notificacionesActivas
                ? '<span class="badge bg-success">Activas</span>'
                : '<span class="badge bg-secondary">Inactivas</span>';

            $fechaRaw = trim((string)($usuario['created_at'] ?? ''));
            $fechaLegible = $formatDate($fechaRaw);
            $fechaHtml = $fechaLegible !== ''
                ? sprintf('<time datetime="%s">%s</time>', $escape($fechaRaw), $escape($fechaLegible))
                : '—';

            $accionesBotones = [];
            $atributosBoton = sprintf(
                ' data-id="%1$d" data-nombre="%2$s" data-email="%3$s" data-rol="%4$s" data-notificaciones="%5$s"',
                $id,
                $escape($nombreCompleto),
                $escape($email),
                $escape($permiso),
                $notificacionesActivas ? '1' : '0'
            );

            $accionesBotones[] = sprintf(
                '<button type="button" class="btn btn-warning btn-sm btnEditarUsuario"%s data-bs-toggle="modal" data-bs-target="#modalEditarUsuario" title="Editar usuario">'
                . '<i class="fas fa-user-edit"></i>'
                . '</button>',
                $atributosBoton
            );

            if ($id !== $sesionId) {
                $accionesBotones[] = sprintf(
                    '<form method="post" class="d-inline" data-ag-confirm="¿Está seguro de eliminar este usuario?"'
                    . ' data-ag-confirm-title="Eliminar usuario" data-ag-confirm-icon="warning"'
                    . ' data-ag-confirm-accept="Sí, eliminar" data-ag-confirm-cancel="Cancelar">'
                    . '<input type="hidden" name="csrf_token" value="%1$s">'
                    . '<input type="hidden" name="eliminarUsuario" value="1">'
                    . '<input type="hidden" name="usuario_id" value="%2$d">'
                    . '<button type="submit" class="btn btn-danger btn-sm" title="Eliminar usuario">'
                    . '<i class="fas fa-trash"></i>'
                    . '</button>'
                    . '</form>',
                    $csrf,
                    $id
                );
            }

            $accionesMenu = $renderActionMenu($accionesBotones);
            $acciones = $renderActionButtons($accionesBotones, ['primary' => 1]);

            $data[] = [
                'id' => $id > 0 ? $escape((string)$id) : '—',
                'nombre' => $nombreMostrar !== '' ? $escape($nombreMostrar) : '—',
                'username' => $username !== '' ? $escape($username) : '—',
                'email' => $email !== '' ? $escape($email) : '—',
                'permiso' => $permisoHtml,
                'notificaciones' => $notificacionesHtml,
                'fecha' => $fechaHtml,
                'acciones' => $acciones,
                'acciones_menu' => $accionesMenu,
            ];
        }

        ag_json_response(['data' => $data]);

    case 'contratos':
        if (!in_array($permission, ['moderator', 'senior', 'owner', 'admin'], true)) {
            ag_json_response(['error' => 'Permisos insuficientes'], 403);
        }

        $estado = isset($_GET['estado']) ? strtolower((string)$_GET['estado']) : 'activos';
        $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : null;
        $contratos = ControladorContratos::ctrMostrarContratos($clienteId);

        $estadosValidos = ['activos', 'archivados', 'cancelados', 'todos'];
        if (!in_array($estado, $estadosValidos, true)) {
            $estado = 'activos';
        }

        $filtrados = array_values(array_filter($contratos, static function ($contrato) use ($estado) {
            $estatus = (int)($contrato['estatus'] ?? 1);
            return match ($estado) {
                'archivados' => $estatus === 0,
                'cancelados' => $estatus === 2,
                'todos' => true,
                default => $estatus === 1,
            };
        }));

        $data = [];
        foreach ($filtrados as $contrato) {
            $id = (int)($contrato['id'] ?? 0);
            $estatusValor = (int)($contrato['estatus'] ?? 1);
            $esCancelado = $estatusValor === 2;
            $folio = $escape($contrato['folio'] ?? '');
            $solicitudId = (int)($contrato['solicitud_origen_id'] ?? 0);
            $badgeSolicitud = $solicitudId > 0 ? '<span class="badge bg-info text-dark ms-1">Solicitud</span>' : '';
            $estadoTexto = $escape($contrato['estatus_texto'] ?? '');
            $badgeEstadoClase = $estatusValor === 1 ? 'bg-success' : ($estatusValor === 2 ? 'bg-danger' : 'bg-secondary');
            $checkbox = $esCancelado
                ? '<input type="checkbox" class="select-contrato" disabled>'
                : '<input type="checkbox" class="select-contrato">';

            $accionesBotones = [
                sprintf('<a href="index.php?ruta=crearContrato&amp;contrato_id=%1$d&amp;ver=1" class="btn btn-info btn-sm" title="Ver contrato"><i class="fas fa-eye"></i></a>', $id),
                sprintf('<button type="button" class="btn btn-outline-info btn-sm btnVerPlaceholdersContrato" title="Ver placeholders" data-contrato-id="%1$d"><i class="fas fa-tags"></i></button>', $id),
                sprintf('<a href="index.php?ruta=crearContrato&amp;contrato_id=%1$d" class="btn btn-primary btn-sm%2$s" title="Editar"%3$s><i class="fas fa-pen"></i></a>', $id, $esCancelado ? ' disabled' : '', $esCancelado ? ' aria-disabled="true" tabindex="-1"' : ''),
            ];

            if ($solicitudId > 0) {
                $accionesBotones[] = sprintf('<a href="index.php?ruta=solicitudes&amp;solicitud_id=%1$d" class="btn btn-outline-secondary btn-sm" title="Ver solicitud de origen"><i class="fas fa-link"></i></a>', $solicitudId);
            }

            if (!$esCancelado) {
                $accionesBotones[] = sprintf(
                    '<form method="post" class="formCancelarContrato" action="index.php?ruta=contratos">'
                    . '<input type="hidden" name="csrf_token" value="%1$s">'
                    . '<input type="hidden" name="cancelarContrato" value="1">'
                    . '<input type="hidden" name="contrato_id" value="%2$d">'
                    . '<input type="hidden" name="motivo_cancelacion" value="">'
                    . '<input type="hidden" name="password_confirmacion" value="">'
                    . '<button type="submit" class="btn btn-warning btn-sm" title="Cancelar contrato" data-confirm-text="¿Desea cancelar este contrato? Esta acción no se puede deshacer."><i class="fas fa-ban"></i></button>'
                    . '</form>',
                    $escape($csrfToken),
                    $id
                );
            }

            $accionesBotones[] = sprintf('<button type="button" class="btn btn-success btn-sm btnGenerarContrato" title="Generar documentos" data-contrato-id="%1$d"%2$s><i class="fas fa-file-word"></i></button>', $id, $esCancelado ? ' disabled' : '');

            $accionesMenu = $renderActionMenu($accionesBotones);
            $acciones = $renderActionButtons($accionesBotones, ['primary' => 3]);

            $jsonContrato = json_encode(
                $contrato,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );

            $rowAttrs = [
                'data-contrato-id' => (string)$id,
                'data-estatus' => (string)$estatusValor,
                'data-contrato' => $jsonContrato !== false ? $jsonContrato : '{}',
            ];

            $data[] = [
                'seleccion' => '<div class="text-center">' . $checkbox . '</div>',
                'id' => (string)$id,
                'creado' => $escape($contrato['created_at'] ?? ''),
                'propietario' => $escape($contrato['nombre_corto'] ?? ($contrato['username'] ?? '—')),
                'folio' => $folio . $badgeSolicitud,
                'cliente' => $escape($contrato['nombre_cliente'] ?? ''),
                'desarrollo' => $escape($contrato['nombre_desarrollo'] ?? ''),
                'estado' => sprintf('<span class="badge %1$s">%2$s</span>', $badgeEstadoClase, $estadoTexto),
                'acciones' => $acciones,
                'acciones_menu' => $accionesMenu,
                'DT_RowAttr' => $rowAttrs,
            ];
        }

        ag_json_response(['data' => $data]);

    case 'solicitudes':
        $esGestor = in_array($permission, ['moderator', 'senior', 'owner', 'admin'], true);
        $estadosPermitidos = ['todos', 'activos', 'borrador', 'enviada', 'en_revision', 'aprobada', 'cancelada'];
        $estadoFiltro = isset($_GET['estado']) ? strtolower(trim((string)$_GET['estado'])) : 'todos';
        if (!in_array($estadoFiltro, $estadosPermitidos, true)) {
            $estadoFiltro = 'todos';
        }
        $propietarioFiltro = 'propios';
        if ($esGestor) {
            $propietariosPermitidos = ['todos', 'propios', 'otros'];
            $propietarioFiltro = isset($_GET['propietario']) ? strtolower(trim((string)$_GET['propietario'])) : 'todos';
            if (!in_array($propietarioFiltro, $propietariosPermitidos, true)) {
                $propietarioFiltro = 'todos';
            }
        }
        $verCanceladas = $esGestor && isset($_GET['verCanceladas']) && $_GET['verCanceladas'] === '1';
        if ($esGestor && $estadoFiltro === 'cancelada') {
            $verCanceladas = true;
        }
        if (!$esGestor && $estadoFiltro === 'cancelada') {
            $estadoFiltro = 'todos';
        }
        if (!$esGestor) {
            $verCanceladas = false;
        }
        $solicitudId = isset($_GET['solicitud_id']) ? (int)$_GET['solicitud_id'] : null;
        $filtrosSolicitudes = ['estado' => $estadoFiltro];
        if ($esGestor) {
            $filtrosSolicitudes['propietario'] = $propietarioFiltro;
        }
        $solicitudes = ControladorSolicitudes::ctrListarSolicitudes($verCanceladas, $solicitudId, true, $filtrosSolicitudes);

        $clientesCoincidenciaCache = [];
        $normalizarIdentificador = static function ($valor): string {
            if ($valor === null) {
                return '';
            }

            $texto = trim((string)$valor);
            if ($texto === '') {
                return '';
            }

            return function_exists('mb_strtoupper') ? mb_strtoupper($texto, 'UTF-8') : strtoupper($texto);
        };

        $estadoColores = [
            'borrador' => 'secondary',
            'enviada' => 'primary',
            'en_revision' => 'warning',
            'aprobada' => 'success',
            'rechazada' => 'danger',
            'cancelada' => 'dark',
        ];

        $permisoActualLower = strtolower((string)($_SESSION['permission'] ?? 'user'));
        $puedeCancelarConContrato = in_array($permisoActualLower, ['owner', 'admin'], true);
        $usuarioActual = (int)($_SESSION['id'] ?? 0);

        $data = [];
        foreach ($solicitudes as $solicitud) {
            $estado = (string)($solicitud['estado'] ?? 'borrador');
            $badge = $estadoColores[$estado] ?? 'secondary';
            $contratoId = (int)($solicitud['contrato_id'] ?? 0);
            $contratoFolio = trim((string)($solicitud['contrato_folio'] ?? ''));
            $contratoEstado = trim((string)($solicitud['contrato_estado'] ?? ''));
            $tieneContrato = $contratoId > 0;
            $contratoEstadoTexto = $contratoEstado !== '' ? strtoupper(str_replace('_', ' ', $contratoEstado)) : '';
            $esPropietario = (int)($solicitud['usuario_id'] ?? 0) === $usuarioActual;
            $esDevuelta = $estado === 'borrador' && !empty($solicitud['motivo_retorno']);
            $solicitudRfc = $normalizarIdentificador($solicitud['rfc'] ?? null);
            $solicitudCurp = $normalizarIdentificador($solicitud['curp'] ?? null);

            $contratoHtml = $tieneContrato
                ? '<div class="d-flex flex-column gap-1">'
                    . sprintf('<span class="badge bg-dark">#%d</span>', $contratoId)
                    . ($contratoFolio !== '' ? '<span class="small text-muted">Folio ' . $escape($contratoFolio) . '</span>' : '')
                    . ($contratoEstadoTexto !== '' ? '<span class="small text-muted">' . $escape($contratoEstadoTexto) . '</span>' : '')
                    . '</div>'
                : '<span class="text-muted">—</span>';

            $accionesPartes = [];
            $accionesPartes[] = sprintf('<a href="index.php?ruta=nuevaSolicitud&amp;id=%1$d&amp;modo=ver" class="btn btn-info btn-sm" title="Ver solicitud"><i class="fas fa-eye"></i></a>', (int)($solicitud['id'] ?? 0));

            if ($tieneContrato && $contratoId > 0) {
                $accionesPartes[] = sprintf('<a href="index.php?ruta=crearContrato&amp;contrato_id=%1$d" class="btn btn-outline-dark btn-sm" title="Ver contrato vinculado"><i class="fas fa-file-contract"></i></a>', $contratoId);
            }

            if ($esGestor) {
                $accionesPartes[] = sprintf('<button type="button" class="btn btn-outline-info btn-sm btnVerPlaceholdersSolicitud" data-solicitud-id="%1$d" title="Ver placeholders disponibles"><i class="fas fa-tags"></i></button>', (int)($solicitud['id'] ?? 0));
                if ($estado === 'aprobada') {
                    $accionesPartes[] = sprintf('<button type="button" class="btn btn-outline-primary btn-sm btnGenerarSolicitudDocx" data-solicitud-id="%1$d" data-csrf="%2$s" title="Generar solicitud en Word"><i class="fas fa-file-word"></i></button>', (int)($solicitud['id'] ?? 0), $escape($csrfToken));
                }
                if ($estado === 'aprobada' && !$tieneContrato) {
                    $urlCrearContrato = sprintf('index.php?ruta=crearContrato&solicitud_id=%d', (int)($solicitud['id'] ?? 0));
                    $clienteCoincidencia = null;
                    if ($solicitudRfc !== '' || $solicitudCurp !== '') {
                        $cacheKey = $solicitudRfc . '|' . $solicitudCurp;
                        if (!array_key_exists($cacheKey, $clientesCoincidenciaCache)) {
                            $clienteEncontrado = ControladorClientes::ctrBuscarClientePorRfcCurp(
                                $solicitudRfc !== '' ? $solicitudRfc : null,
                                $solicitudCurp !== '' ? $solicitudCurp : null
                            );
                            if (is_array($clienteEncontrado)) {
                                $coincideRfc = $solicitudRfc !== ''
                                    && strcasecmp((string)($clienteEncontrado['rfc'] ?? ''), $solicitudRfc) === 0;
                                $coincideCurp = $solicitudCurp !== ''
                                    && strcasecmp((string)($clienteEncontrado['curp'] ?? ''), $solicitudCurp) === 0;
                                if ($coincideRfc && $coincideCurp) {
                                    $matchTexto = 'RFC y CURP';
                                } elseif ($coincideRfc) {
                                    $matchTexto = 'RFC';
                                } elseif ($coincideCurp) {
                                    $matchTexto = 'CURP';
                                } else {
                                    $matchTexto = 'los datos proporcionados';
                                }

                                $clientesCoincidenciaCache[$cacheKey] = [
                                    'id' => (int)($clienteEncontrado['id'] ?? 0),
                                    'nombre' => trim((string)($clienteEncontrado['nombre'] ?? '')),
                                    'rfc' => trim((string)($clienteEncontrado['rfc'] ?? '')),
                                    'curp' => trim((string)($clienteEncontrado['curp'] ?? '')),
                                    'estado' => trim((string)($clienteEncontrado['estado'] ?? '')),
                                    'match' => $matchTexto,
                                ];
                            } else {
                                $clientesCoincidenciaCache[$cacheKey] = null;
                            }
                        }
                        $clienteCoincidencia = $clientesCoincidenciaCache[$cacheKey];
                    }

                    $crearContratoAttrs = [
                        'href' => $escape($urlCrearContrato),
                        'class' => 'btn btn-dark btn-sm btnCrearContratoSolicitud',
                        'title' => 'Crear contrato',
                        'data-url-base' => $escape($urlCrearContrato),
                        'data-solicitud-id' => (int)($solicitud['id'] ?? 0),
                        'data-rfc' => $solicitudRfc !== '' ? $escape($solicitudRfc) : null,
                        'data-curp' => $solicitudCurp !== '' ? $escape($solicitudCurp) : null,
                    ];

                    if ($clienteCoincidencia !== null && $clienteCoincidencia['id'] > 0) {
                        $urlConCliente = sprintf('%s&cliente_id=%d', $urlCrearContrato, $clienteCoincidencia['id']);
                        $crearContratoAttrs['data-cliente-id'] = (string)$clienteCoincidencia['id'];
                        $crearContratoAttrs['data-cliente-nombre'] = $clienteCoincidencia['nombre'] !== '' ? $escape($clienteCoincidencia['nombre']) : null;
                        $crearContratoAttrs['data-cliente-estado'] = $clienteCoincidencia['estado'] !== '' ? $escape($clienteCoincidencia['estado']) : null;
                        $crearContratoAttrs['data-cliente-match'] = $clienteCoincidencia['match'] !== '' ? $escape($clienteCoincidencia['match']) : null;
                        $crearContratoAttrs['data-cliente-rfc'] = $clienteCoincidencia['rfc'] !== '' ? $escape($clienteCoincidencia['rfc']) : null;
                        $crearContratoAttrs['data-cliente-curp'] = $clienteCoincidencia['curp'] !== '' ? $escape($clienteCoincidencia['curp']) : null;
                        $crearContratoAttrs['data-url-cliente'] = $escape($urlConCliente);
                    }

                    $accionesPartes[] = sprintf(
                        '<a%s><i class="fas fa-file-signature"></i></a>',
                        $buildAttrString($crearContratoAttrs)
                    );
                }
            }

            $puedeEditarSolicitud = false;
            if (!$tieneContrato) {
                if ($esGestor && in_array($estado, ['borrador', 'en_revision'], true)) {
                    $puedeEditarSolicitud = true;
                } elseif ($esPropietario && $estado === 'borrador') {
                    $puedeEditarSolicitud = true;
                }
            }

            if ($puedeEditarSolicitud) {
                $accionesPartes[] = sprintf('<a href="index.php?ruta=nuevaSolicitud&amp;id=%1$d" class="btn btn-primary btn-sm" title="Editar solicitud"><i class="fas fa-pen"></i></a>', (int)($solicitud['id'] ?? 0));
            }

            if ($esPropietario && $estado === 'borrador' && !$tieneContrato) {
                $accionesPartes[] = sprintf(
                    '<form method="post" class="formCambiarEstadoSolicitud" action="index.php?ruta=solicitudes">'
                    . '<input type="hidden" name="csrf_token" value="%1$s">'
                    . '<input type="hidden" name="cambiar_estado_solicitud" value="1">'
                    . '<input type="hidden" name="solicitud_id" value="%2$d">'
                    . '<input type="hidden" name="nuevo_estado" value="enviada">'
                    . '<button type="submit" class="btn btn-success btn-sm" data-confirm-text="¿Enviar solicitud?"><i class="fas fa-paper-plane"></i></button>'
                    . '</form>',
                    $escape($csrfToken),
                    (int)($solicitud['id'] ?? 0)
                );
            }

            if ($esGestor) {
                if (!$tieneContrato) {
                    if (in_array($estado, ['enviada', 'en_revision'], true)) {
                        $accionesPartes[] = sprintf(
                            '<button type="button" class="btn btn-outline-warning btn-sm btnRegresarBorrador" data-bs-toggle="modal" data-bs-target="#modalRegresarBorrador" data-solicitud-id="%1$d" data-solicitud-folio="%2$s" data-solicitud-nombre="%3$s" title="Regresar a borrador">'
                            . '<i class="fas fa-undo-alt"></i>'
                            . '</button>',
                            (int)($solicitud['id'] ?? 0),
                            $escape($solicitud['folio'] ?? ''),
                            $escape($solicitud['nombre_completo'] ?? '')
                        );
                    }
                }
            }

            $accionesMenu = $renderActionMenu($accionesPartes);
            $acciones = $renderActionButtons($accionesPartes, ['primary' => 3]);

            $rowAttrs = [
                'data-estado' => $escape($estado),
                'data-contrato-id' => (string)$contratoId,
                'data-has-contrato' => $tieneContrato ? '1' : '0',
            ];

            $data[] = [
                'folio' => $escape($solicitud['folio'] ?? ''),
                'nombre' => $escape($solicitud['nombre_completo'] ?? ''),
                'estado' => sprintf('<span class="badge bg-%1$s text-uppercase">%2$s</span>', $badge, $escape(str_replace('_', ' ', $estado))),
                'fecha' => $escape($solicitud['fecha_formateada'] ?? $formatDate($solicitud['fecha'] ?? '')),
                'responsable' => $escape(($solicitud['nombre_corto'] ?? '') !== '' ? $solicitud['nombre_corto'] : ($solicitud['username'] ?? '')),
                'contrato' => $contratoHtml,
                'acciones' => $acciones,
                'acciones_menu' => $accionesMenu,
                'DT_RowAttr' => $rowAttrs,
                'DT_RowClass' => $esDevuelta ? 'solicitud-devuelta' : null,
            ];
        }

        ag_json_response(['data' => $data]);

    default:
        ag_json_response(['error' => 'Recurso no disponible'], 404);
}
