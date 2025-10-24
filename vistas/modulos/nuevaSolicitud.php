<?php
use App\Controllers\ControladorClientes;
use App\Controllers\ControladorDesarrollos;
use App\Controllers\ControladorParametros;
use App\Controllers\ControladorSolicitudes;
use App\Support\AppNavigation;

$resultadoGuardado = ControladorSolicitudes::ctrGuardarSolicitud();
$csrfToken = $_SESSION['csrf_token'] ?? '';
$permisoActual = strtolower(trim((string)($_SESSION['permission'] ?? 'user')));
$puedeGestionar = in_array($permisoActual, ['moderator','senior','owner','admin'], true);
$solicitudActual = null;
$estadoActual = 'borrador';
$modoVisualizacion = isset($_GET['modo']) && strtolower((string)$_GET['modo']) === 'ver';
$nacionalidadesDisponibles = ControladorParametros::ctrMostrarVariables('nacionalidad');
$desarrollosDisponibles = ControladorDesarrollos::ctrMostrarDesarrollos();

if (isset($_GET['id'])) {
    $solicitudActual = ControladorSolicitudes::ctrObtenerSolicitudPorId((int)$_GET['id']);
    if ($solicitudActual) {
        $estadoActual = $solicitudActual['estado'];
    } else {
        echo "<script>Swal.fire({icon:'error',title:'No disponible',text:'No es posible acceder a la solicitud seleccionada.'}).then(()=>{window.location='index.php?ruta=solicitudes';});</script>";
        return;
    }
} else {
    $modoVisualizacion = false;
}

if ($modoVisualizacion && !$solicitudActual) {
    $modoVisualizacion = false;
}

if ($resultadoGuardado && isset($resultadoGuardado['tipo'], $resultadoGuardado['mensaje'])) {
    $icon = $resultadoGuardado['tipo'] === 'success' ? 'success' : ($resultadoGuardado['tipo'] === 'info' ? 'info' : 'error');
    $titulo = $resultadoGuardado['tipo'] === 'success' ? 'Éxito' : ($resultadoGuardado['tipo'] === 'info' ? 'Aviso' : 'Error');
    echo "<script>Swal.fire({icon:'{$icon}',title:'{$titulo}',text:'" . addslashes($resultadoGuardado['mensaje']) . "'}).then(()=>{window.location='index.php?ruta=solicitudes';});</script>";
}

$solicitudTieneContrato = $solicitudActual && !empty($solicitudActual['contrato_id']);
$soloLectura = ($solicitudActual && !$puedeGestionar && $estadoActual !== 'borrador') || $solicitudTieneContrato;
$soloLectura = $soloLectura || ($solicitudActual && $modoVisualizacion);
$albaceaActivo = !empty($solicitudActual['albacea_activo']);
$enforceRequired = false;

$camposFaltantes = [];
if (isset($_GET['faltantes'])) {
    $listaCampos = explode(',', (string)$_GET['faltantes']);
    foreach ($listaCampos as $campo) {
        $normalizado = strtolower(trim((string)$campo));
        $normalizado = preg_replace('/[^a-z0-9_]/', '', $normalizado);
        if ($normalizado !== '') {
            $camposFaltantes[] = $normalizado;
        }
    }
    $camposFaltantes = array_values(array_unique($camposFaltantes));
}

$esCampoFaltante = static function (string $campo) use ($camposFaltantes): bool {
    if ($campo === '') {
        return false;
    }
    return in_array(strtolower($campo), $camposFaltantes, true);
};
$hayCamposFaltantes = !empty($camposFaltantes);
$nacionalidadSeleccionadaId = 0;
$nacionalidadSeleccionadaNombre = '';
$desarrolloSeleccionadoId = 0;
$desarrolloSeleccionadoNombre = '';

if ($solicitudActual) {
    $nacionalidadSeleccionadaId = (int)($solicitudActual['nacionalidad_id'] ?? 0);
    $nacionalidadSeleccionadaNombre = trim((string)($solicitudActual['nacionalidad'] ?? ''));

    if ($nacionalidadSeleccionadaId === 0 && $nacionalidadSeleccionadaNombre !== '') {
        foreach ($nacionalidadesDisponibles as $nac) {
            if (strcasecmp((string)$nac['nombre'], $nacionalidadSeleccionadaNombre) === 0
                || strcasecmp((string)$nac['identificador'], $nacionalidadSeleccionadaNombre) === 0) {
                $nacionalidadSeleccionadaId = (int)$nac['id'];
                break;
            }
        }
    }

    $desarrolloSeleccionadoId = (int)($solicitudActual['desarrollo_id'] ?? 0);
    $desarrolloSeleccionadoNombre = trim((string)($solicitudActual['desarrollo'] ?? ''));

    if ($desarrolloSeleccionadoId === 0 && $desarrolloSeleccionadoNombre !== '') {
        foreach ($desarrollosDisponibles as $desarrollo) {
            if (strcasecmp((string)$desarrollo['nombre'], $desarrolloSeleccionadoNombre) === 0) {
                $desarrolloSeleccionadoId = (int)$desarrollo['id'];
                break;
            }
        }
    }
}

$obtenerValor = function (string $campo) use ($solicitudActual) {
    $valor = ControladorSolicitudes::valorParaFormulario($solicitudActual[$campo] ?? null);

    if (in_array($campo, ['pago_anual', 'identificacion_numero'], true) && $valor !== '') {
        $valorNormalizado = str_replace(',', '.', $valor);
        if (is_numeric($valorNormalizado) && abs((float)$valorNormalizado) < 0.0000001) {
            $valor = '';
        }
    }

    return htmlspecialchars($valor, ENT_QUOTES);
};

$obtenerValorCrudo = static function (string $campo) use ($solicitudActual): string {
    $valor = ControladorSolicitudes::valorParaFormulario($solicitudActual[$campo] ?? null);

    if (in_array($campo, ['pago_anual', 'identificacion_numero'], true) && $valor !== '') {
        $valorNormalizado = str_replace(',', '.', $valor);
        if (is_numeric($valorNormalizado) && abs((float)$valorNormalizado) < 0.0000001) {
            $valor = '';
        }
    }

    return trim($valor);
};

$identificacionSeleccionada = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificacionSeleccionada = strtoupper(trim((string)($_POST['identificacion'] ?? '')));
} else {
    $identificacionSeleccionada = strtoupper($obtenerValorCrudo('identificacion'));
}
$mostrarIdmex = $identificacionSeleccionada === 'INE';
$mostrarIdentificacionNumero = in_array($identificacionSeleccionada, ['PASAPORTE', 'CEDULA PROFESIONAL'], true);
if (!$mostrarIdmex && !$mostrarIdentificacionNumero) {
    $mostrarIdentificacionNumero = $obtenerValorCrudo('identificacion_numero') !== '';
    if (!$mostrarIdentificacionNumero) {
        $mostrarIdmex = $obtenerValorCrudo('idmex') !== '';
    }
}

$usaPagoAnual = false;
$valorPagoAnual = $_POST['usa_pago_anual'] ?? ($solicitudActual['usa_pago_anual'] ?? null);
if ($valorPagoAnual !== null && $valorPagoAnual !== '') {
    $usaPagoAnualBool = filter_var($valorPagoAnual, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    if ($usaPagoAnualBool === null) {
        $usaPagoAnual = in_array((string)$valorPagoAnual, ['1', 'true', 'si', 'sí', 'on'], true);
    } else {
        $usaPagoAnual = $usaPagoAnualBool;
    }
} else {
    $usaPagoAnual = $obtenerValorCrudo('pago_anual') !== ''
        || $obtenerValorCrudo('fecha_pago_anual') !== ''
        || $obtenerValorCrudo('plazo_anual') !== '';
}

$textoRequisitoTelefono = 'Selecciona la lada y captura un número de 10 dígitos como mínimo.';
$textoHintTelefono = 'Incluye la clave lada del país. El número final se guardará con prefijo internacional.';

$renderTelefonoSolicitud = static function (array $config) use ($soloLectura, $textoHintTelefono, $textoRequisitoTelefono, $enforceRequired, $esCampoFaltante): string {
    $id = $config['id'] ?? '';
    $name = $config['name'] ?? '';
    if ($id === '' || $name === '') {
        return '';
    }

    $colClass = $config['col'] ?? 'col-md-3';
    $showLabel = array_key_exists('showLabel', $config) ? (bool)$config['showLabel'] : true;
    $label = '';
    if (array_key_exists('label', $config)) {
        $label = trim((string)$config['label']);
    }
    if ($showLabel && $label === '' && $name !== '') {
        $label = ucwords(str_replace(['_', '-'], [' ', ' '], $name));
    }
    $keepLabel = array_key_exists('keepLabel', $config) ? (bool)$config['keepLabel'] : true;
    $labelClasses = 'form-label';
    if ($keepLabel) {
        $labelClasses .= ' ag-keep-label';
    }
    if (!empty($config['labelClass'])) {
        $labelClasses .= ' ' . trim((string)$config['labelClass']);
    }
    $hintId = $config['hintId'] ?? ($id . 'Hint');
    $hintText = $config['hintText'] ?? $textoHintTelefono;
    $requirement = $config['requirement'] ?? $textoRequisitoTelefono;
    $invalidText = $config['invalidFeedback'] ?? 'Ingrese un número válido.';
    $hiddenId = $config['hiddenId'] ?? ($id . 'Hidden');
    $required = !empty($config['required']);
    $readonly = $soloLectura || !empty($config['readonly']);

    $value = $config['value'] ?? '';
    $visibleValue = array_key_exists('visibleValue', $config) ? $config['visibleValue'] : $value;
    $hiddenValue = array_key_exists('hiddenValue', $config) ? $config['hiddenValue'] : $value;

    $extraInputAttrs = $config['extraInputAttributes'] ?? [];
    $extraHiddenAttrs = $config['extraHiddenAttributes'] ?? [];
    $campoDestacado = $config['highlightField'] ?? $name;
    $esDestacado = false;
    if (is_array($campoDestacado)) {
        foreach ($campoDestacado as $campoIndividual) {
            if ($esCampoFaltante((string)$campoIndividual)) {
                $esDestacado = true;
                break;
            }
        }
    } elseif (is_string($campoDestacado) && $campoDestacado !== '') {
        $esDestacado = $esCampoFaltante($campoDestacado);
    }

    $extraClass = '';
    if (isset($extraInputAttrs['class'])) {
        $extraClass = ' ' . trim((string)$extraInputAttrs['class']);
        unset($extraInputAttrs['class']);
    }

    $inputAttributes = [
        'type' => 'tel',
        'id' => $id,
        'class' => trim('form-control' . $extraClass),
        'data-intl-hidden' => '#' . $hiddenId,
        'aria-describedby' => $hintId,
        'data-requirement' => $requirement,
        'inputmode' => 'tel',
        'autocomplete' => 'tel',
        'value' => $visibleValue,
    ];

    if ($required && $enforceRequired) {
        $inputAttributes['required'] = null;
    }
    if ($readonly) {
        $inputAttributes['readonly'] = null;
    }
    if ($esDestacado) {
        $inputAttributes['class'] = trim($inputAttributes['class'] . ' is-invalid');
    }

    foreach ($extraInputAttrs as $attr => $attrValue) {
        if (is_bool($attrValue)) {
            if ($attrValue) {
                $inputAttributes[$attr] = null;
            }
        } elseif ($attrValue !== null) {
            $inputAttributes[$attr] = $attrValue;
        }
    }

    $hiddenAttributes = [
        'type' => 'hidden',
        'name' => $name,
        'id' => $hiddenId,
        'value' => $hiddenValue,
    ];

    if (isset($extraHiddenAttrs['class'])) {
        $hiddenAttributes['class'] = trim((string)$extraHiddenAttrs['class']);
        unset($extraHiddenAttrs['class']);
    }

    foreach ($extraHiddenAttrs as $attr => $attrValue) {
        if (is_bool($attrValue)) {
            if ($attrValue) {
                $hiddenAttributes[$attr] = null;
            }
        } elseif ($attrValue !== null) {
            $hiddenAttributes[$attr] = $attrValue;
        }
    }

    $renderAttrs = static function (array $attrs): string {
        $parts = [];
        foreach ($attrs as $attr => $attrValue) {
            if ($attrValue === null) {
                $parts[] = $attr;
            } else {
                $parts[] = sprintf('%s="%s"', $attr, htmlspecialchars((string)$attrValue, ENT_QUOTES));
            }
        }
        return implode(' ', $parts);
    };

    ob_start();
    ?>
    <div class="<?php echo htmlspecialchars($colClass, ENT_QUOTES); ?>">
      <?php if ($showLabel && $label !== '') : ?>
        <label class="<?php echo htmlspecialchars($labelClasses, ENT_QUOTES); ?>" for="<?php echo htmlspecialchars($id, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></label>
      <?php endif; ?>
      <input <?php echo $renderAttrs($inputAttributes); ?>>
      <div class="invalid-feedback"><?php echo htmlspecialchars($invalidText, ENT_QUOTES); ?></div>
      <div id="<?php echo htmlspecialchars($hintId, ENT_QUOTES); ?>" class="form-text ag-field-hint" aria-hidden="true"><?php echo htmlspecialchars($hintText, ENT_QUOTES); ?></div>
      <input <?php echo $renderAttrs($hiddenAttributes); ?>>
    </div>
    <?php
    return ob_get_clean();
};
require_once 'vistas/partials/content_header.php';
$tituloSolicitud = $solicitudActual
    ? ($modoVisualizacion ? 'Detalle de la solicitud' : 'Editar solicitud')
    : 'Nueva solicitud';
$subtituloSolicitud = $solicitudActual
    ? ($modoVisualizacion
        ? 'Consulta la información capturada para esta solicitud.'
        : 'Actualiza la información capturada previamente.')
    : 'Completa los datos para generar una nueva solicitud.';
ag_render_content_header([
    'title' => $tituloSolicitud,
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Solicitudes', 'url' => 'index.php?ruta=solicitudes'],
        ['label' => $tituloSolicitud],
    ],
    'app' => AppNavigation::APP_SOLICITUDES,
    'route' => 'nuevaSolicitud',
]);
require_once 'vistas/partials/record_toolbar.php';

$toolbarPrimary = [
    'label' => 'Nueva solicitud',
    'url' => 'index.php?ruta=nuevaSolicitud',
    'icon' => 'fas fa-plus',
    'class' => 'btn btn-primary'
];
$toolbarSecondary = [
    'label' => 'Volver a solicitudes',
    'url' => 'index.php?ruta=solicitudes',
    'icon' => 'fas fa-arrow-left',
    'class' => 'btn btn-outline-secondary'
];

$solicitudId = (int)($solicitudActual['id'] ?? 0);
$solicitudFolio = trim((string)($solicitudActual['folio'] ?? ''));
$solicitudNombre = trim((string)($solicitudActual['nombre_completo'] ?? ''));
$solicitudEstado = strtolower((string)$estadoActual);
$estadoBadgeClases = [
    'borrador' => 'badge bg-secondary',
    'enviada' => 'badge bg-primary',
    'en_revision' => 'badge bg-warning text-dark',
    'aprobada' => 'badge bg-success',
    'cancelada' => 'badge bg-dark',
    'rechazada' => 'badge bg-danger',
];
$toolbarBadges = [];
$toolbarMeta = [];

if ($solicitudActual) {
    $estadoEtiqueta = strtoupper(str_replace('_', ' ', $solicitudEstado));
    $toolbarBadges[] = [
        'label' => $estadoEtiqueta,
        'class' => $estadoBadgeClases[$solicitudEstado] ?? 'badge bg-secondary'
    ];
    if ($solicitudTieneContrato) {
        $contratoId = (int)($solicitudActual['contrato_id'] ?? 0);
        $contratoFolio = trim((string)($solicitudActual['contrato_folio'] ?? ''));
        $contratoBadge = 'Contrato #' . $contratoId;
        if ($contratoFolio !== '') {
            $contratoBadge .= ' · ' . $contratoFolio;
        }
        $toolbarBadges[] = [
            'label' => $contratoBadge,
            'class' => 'badge bg-dark'
        ];
    }
    if (!empty($solicitudActual['fecha'])) {
        $toolbarMeta[] = 'Fecha: ' . (string)$solicitudActual['fecha'];
    }
    $responsable = trim((string)($solicitudActual['nombre_corto'] ?? $solicitudActual['username'] ?? ''));
    if ($responsable !== '') {
        $toolbarMeta[] = 'Responsable: ' . $responsable;
    }
} else {
    $toolbarMeta[] = 'Estado inicial: Borrador';
}

$toolbarTitle = $solicitudActual
    ? ($solicitudFolio !== '' ? 'Solicitud ' . $solicitudFolio : 'Solicitud #' . $solicitudId)
    : 'Nueva solicitud';
$toolbarSubtitle = $solicitudActual
    ? ($solicitudNombre !== '' ? $solicitudNombre : 'Detalle del registro')
    : 'Completa los datos para generar una nueva solicitud.';

$menuAcciones = [];
$usuarioActualId = (int)($_SESSION['id'] ?? 0);
$esPropietario = $solicitudActual && (int)($solicitudActual['usuario_id'] ?? 0) === $usuarioActualId;
$permisoActualLower = strtolower((string)($_SESSION['permission'] ?? 'user'));
$esGestor = $puedeGestionar;
$puedeCancelarConContrato = in_array($permisoActualLower, ['owner', 'admin'], true);

if ($solicitudActual) {
    $puedeEditarSolicitud = false;
    if (!$solicitudTieneContrato) {
        if ($esGestor && in_array($solicitudEstado, ['borrador', 'en_revision'], true)) {
            $puedeEditarSolicitud = true;
        } elseif ($esPropietario && $solicitudEstado === 'borrador') {
            $puedeEditarSolicitud = true;
        }
    }

    if ($modoVisualizacion && $puedeEditarSolicitud) {
        $menuAcciones[] = [
            'type' => 'link',
            'label' => 'Editar solicitud',
            'icon' => 'fas fa-pen',
            'url' => sprintf('index.php?ruta=nuevaSolicitud&id=%d', $solicitudId),
        ];
    }

    if ($esGestor) {
        $menuAcciones[] = [
            'type' => 'button',
            'label' => 'Ver placeholders',
            'icon' => 'fas fa-tags',
            'class' => 'btnVerPlaceholdersSolicitud',
            'data' => [
                'solicitud-id' => $solicitudId,
            ],
        ];

        if ($solicitudEstado === 'aprobada') {
            $menuAcciones[] = [
                'type' => 'button',
                'label' => 'Generar solicitud (Word)',
                'icon' => 'fas fa-file-word',
                'class' => 'btnGenerarSolicitudDocx',
                'data' => [
                    'solicitud-id' => $solicitudId,
                    'csrf' => $csrfToken,
                ],
            ];
        }
    }

    if ($solicitudTieneContrato) {
        $menuAcciones[] = [
            'type' => 'link',
            'label' => 'Ver contrato vinculado',
            'icon' => 'fas fa-file-contract',
            'url' => sprintf('index.php?ruta=crearContrato&contrato_id=%d', (int)$solicitudActual['contrato_id']),
        ];
    }

    if ($esGestor && $solicitudEstado === 'aprobada' && !$solicitudTieneContrato) {
        $urlCrearContrato = sprintf('index.php?ruta=crearContrato&solicitud_id=%d', $solicitudId);
        $normalizarId = static function ($valor): string {
            if ($valor === null) {
                return '';
            }
            $texto = strtoupper(trim((string)$valor));
            return $texto;
        };
        $solicitudRfc = $normalizarId($solicitudActual['rfc'] ?? null);
        $solicitudCurp = $normalizarId($solicitudActual['curp'] ?? null);
        $clienteCoincidencia = null;
        $matchTexto = '';
        if ($solicitudRfc !== '' || $solicitudCurp !== '') {
            $clienteEncontrado = ControladorClientes::ctrBuscarClientePorRfcCurp(
                $solicitudRfc !== '' ? $solicitudRfc : null,
                $solicitudCurp !== '' ? $solicitudCurp : null
            );
            if (is_array($clienteEncontrado)) {
                $clienteCoincidencia = $clienteEncontrado;
                $coincideRfc = $solicitudRfc !== '' && strcasecmp((string)($clienteEncontrado['rfc'] ?? ''), $solicitudRfc) === 0;
                $coincideCurp = $solicitudCurp !== '' && strcasecmp((string)($clienteEncontrado['curp'] ?? ''), $solicitudCurp) === 0;
                if ($coincideRfc && $coincideCurp) {
                    $matchTexto = 'RFC y CURP';
                } elseif ($coincideRfc) {
                    $matchTexto = 'RFC';
                } elseif ($coincideCurp) {
                    $matchTexto = 'CURP';
                } else {
                    $matchTexto = 'los datos proporcionados';
                }
            }
        }
        $crearContratoData = [
            'solicitud-id' => $solicitudId,
            'url-base' => $urlCrearContrato,
        ];
        if ($solicitudRfc !== '') {
            $crearContratoData['rfc'] = $solicitudRfc;
        }
        if ($solicitudCurp !== '') {
            $crearContratoData['curp'] = $solicitudCurp;
        }
        if ($clienteCoincidencia && (int)($clienteCoincidencia['id'] ?? 0) > 0) {
            $clienteIdCoincidencia = (int)$clienteCoincidencia['id'];
            $crearContratoData['cliente-id'] = $clienteIdCoincidencia;
            $crearContratoData['cliente-nombre'] = trim((string)($clienteCoincidencia['nombre'] ?? ''));
            $crearContratoData['cliente-estado'] = trim((string)($clienteCoincidencia['estado'] ?? ''));
            if ($matchTexto !== '') {
                $crearContratoData['cliente-match'] = $matchTexto;
            }
            if (!empty($clienteCoincidencia['rfc'])) {
                $crearContratoData['cliente-rfc'] = strtoupper(trim((string)$clienteCoincidencia['rfc']));
            }
            if (!empty($clienteCoincidencia['curp'])) {
                $crearContratoData['cliente-curp'] = strtoupper(trim((string)$clienteCoincidencia['curp']));
            }
            $crearContratoData['url-cliente'] = sprintf('%s&cliente_id=%d', $urlCrearContrato, $clienteIdCoincidencia);
        }

        $menuAcciones[] = [
            'type' => 'link',
            'label' => 'Crear contrato',
            'icon' => 'fas fa-file-signature',
            'url' => $urlCrearContrato,
            'class' => 'btnCrearContratoSolicitud',
            'data' => $crearContratoData,
        ];
    }

    if ($esPropietario && $solicitudEstado === 'borrador' && !$solicitudTieneContrato) {
        $menuAcciones[] = [
            'type' => 'form',
            'label' => 'Enviar solicitud',
            'icon' => 'fas fa-paper-plane',
            'action' => 'index.php?ruta=solicitudes',
            'method' => 'post',
            'form_class' => 'formCambiarEstadoSolicitud',
            'inputs' => [
                ['name' => 'csrf_token', 'value' => $csrfToken],
                ['name' => 'cambiar_estado_solicitud', 'value' => '1'],
                ['name' => 'solicitud_id', 'value' => (string)$solicitudId],
                ['name' => 'nuevo_estado', 'value' => 'enviada'],
            ],
            'confirm' => '¿Enviar solicitud?'
        ];
    }

    if ($esGestor && !$solicitudTieneContrato && in_array($solicitudEstado, ['enviada', 'en_revision'], true)) {
        $menuAcciones[] = [
            'type' => 'button',
            'label' => 'Regresar a borrador',
            'icon' => 'fas fa-undo-alt',
            'class' => 'btnRegresarBorrador',
            'attributes' => [
                'data-bs-toggle' => 'modal',
                'data-bs-target' => '#modalRegresarBorrador',
                'data-solicitud-id' => (string)$solicitudId,
                'data-solicitud-folio' => $solicitudFolio,
                'data-solicitud-nombre' => $solicitudNombre,
            ],
        ];
    }

    if ($esGestor && !$solicitudTieneContrato) {
        $estadoCambios = [
            'en_revision' => ['label' => 'Marcar en revisión', 'icon' => 'fas fa-clipboard-check'],
            'aprobada' => ['label' => 'Aprobar solicitud', 'icon' => 'fas fa-check-circle'],
            'cancelada' => ['label' => 'Cancelar solicitud', 'icon' => 'fas fa-ban'],
        ];
        foreach ($estadoCambios as $estadoCambio => $datosEstado) {
            if ($solicitudEstado === $estadoCambio) {
                continue;
            }
            $menuAcciones[] = [
                'type' => 'form',
                'label' => $datosEstado['label'],
                'icon' => $datosEstado['icon'],
                'action' => 'index.php?ruta=solicitudes',
                'method' => 'post',
                'form_class' => 'formCambiarEstadoSolicitud',
                'inputs' => [
                    ['name' => 'csrf_token', 'value' => $csrfToken],
                    ['name' => 'cambiar_estado_solicitud', 'value' => '1'],
                    ['name' => 'solicitud_id', 'value' => (string)$solicitudId],
                    ['name' => 'nuevo_estado', 'value' => $estadoCambio],
                    ['name' => 'motivo_cancelacion', 'value' => ''],
                    ['name' => 'password_confirmacion', 'value' => ''],
                ],
                'confirm' => 'Cambiar estado a ' . strtoupper(str_replace('_', ' ', $estadoCambio)) . '?'
            ];
        }
    } elseif ($solicitudTieneContrato && $solicitudEstado !== 'cancelada' && $puedeCancelarConContrato) {
        $menuAcciones[] = [
            'type' => 'form',
            'label' => 'Cancelar solicitud vinculada',
            'icon' => 'fas fa-ban',
            'action' => 'index.php?ruta=solicitudes',
            'method' => 'post',
            'form_class' => 'formCambiarEstadoSolicitud',
            'inputs' => [
                ['name' => 'csrf_token', 'value' => $csrfToken],
                ['name' => 'cambiar_estado_solicitud', 'value' => '1'],
                ['name' => 'solicitud_id', 'value' => (string)$solicitudId],
                ['name' => 'nuevo_estado', 'value' => 'cancelada'],
                ['name' => 'motivo_cancelacion', 'value' => ''],
                ['name' => 'password_confirmacion', 'value' => ''],
            ],
            'confirm' => '¿Cancelar la solicitud vinculada al contrato?'
        ];
    }
}

ag_render_record_toolbar([
    'primary_action' => $toolbarPrimary,
    'secondary_action' => $toolbarSecondary,
    'title' => $toolbarTitle,
    'badges' => $toolbarBadges,
    'meta' => $toolbarMeta,
    'menu_actions' => $menuAcciones,
]);
?>
<section class="content">
  <div class="container-fluid">
    <?php if ($solicitudActual && ($solicitudActual['estado'] ?? '') === 'borrador' && !empty($solicitudActual['motivo_retorno'] ?? '')) : ?>
      <div class="callout <?php echo $puedeGestionar ? 'callout-info' : 'callout-warning'; ?>">
        <h5 class="mb-2"><i class="fas fa-undo-alt me-2"></i><?php echo $puedeGestionar ? 'Motivo del regreso a borrador' : 'Solicitud devuelta a borrador'; ?></h5>
        <p class="mb-2"><?php echo nl2br(htmlspecialchars($solicitudActual['motivo_retorno'])); ?></p>
        <?php if (!empty($solicitudActual['devuelto_en'])) : ?>
          <p class="mb-0 small text-muted">Actualizado el <?php echo htmlspecialchars($solicitudActual['devuelto_en']); ?>.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php
    $solicitudCancelada = $solicitudActual && ($solicitudActual['estado'] ?? '') === 'cancelada';
    if ($solicitudCancelada) {
        $detalleCancelacion = $solicitudActual['cancelacion'] ?? [];
        if (!is_array($detalleCancelacion)) {
            $detalleCancelacion = [];
        }
        $motivoCancelacion = trim((string)($detalleCancelacion['motivo'] ?? ($solicitudActual['motivo_cancelacion'] ?? '')));
        $fechaCancelacion = trim((string)($detalleCancelacion['cancelada_en'] ?? ''));
        ?>
      <div class="callout callout-danger">
        <h5 class="mb-2"><i class="fas fa-ban me-2"></i>Solicitud cancelada</h5>
        <?php if ($motivoCancelacion !== '') : ?>
          <p class="mb-2"><?php echo nl2br(htmlspecialchars($motivoCancelacion)); ?></p>
        <?php else : ?>
          <p class="mb-2">Esta solicitud fue cancelada.</p>
        <?php endif; ?>
        <?php if ($fechaCancelacion !== '') : ?>
          <p class="mb-0 small text-muted">Cancelada el <?php echo htmlspecialchars($fechaCancelacion); ?>.</p>
        <?php endif; ?>
      </div>
    <?php } ?>
    <?php if ($hayCamposFaltantes) : ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Completa los campos resaltados en rojo para poder enviar la solicitud.</div>
    <?php endif; ?>
    <?php if ($soloLectura) : ?>
      <div class="alert alert-warning">
        <i class="fas fa-lock me-2"></i>
        <?php if ($modoVisualizacion) : ?>
          Esta vista es de solo lectura. Utiliza las acciones del encabezado si necesitas editar o derivar esta solicitud.
        <?php elseif ($solicitudTieneContrato) : ?>
          Esta solicitud está vinculada a un contrato y no puede modificarse. Si necesitas realizar cambios solicita apoyo al equipo administrador.
        <?php else : ?>
          Esta solicitud ya fue enviada. Solo el equipo administrador puede modificarla.
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="index.php?ruta=nuevaSolicitud&amp;accion=guardarSolicitud" id="formSolicitud" class="ag-form-layout"<?php echo $modoVisualizacion ? ' data-view-only="1"' : ''; ?>>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
      <input type="hidden" name="solicitud_id" value="<?php echo htmlspecialchars($solicitudActual['id'] ?? ''); ?>">

      <div class="ag-form-card card shadow-sm mb-4">
        <div class="card-header ag-card-header">
          <div class="ag-card-header-text">
            <h3 class="card-title mb-1">Datos del solicitante</h3>
            <hr>
            <p class="card-subtitle text-muted small mb-0">Completa la información principal de la persona solicitante.</p>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6 col-md-4 col-sm-6">
              <label class="form-label">Folio</label>
              <input type="text" name="folio" class="form-control form-control-sm" maxlength="50" value="<?php echo $obtenerValor('folio'); ?>" <?php echo $soloLectura ? 'readonly' : ''; ?>>
            </div>
            <div class="col-6 col-sm-6 col-md-4">
              <label class="form-label">Fecha</label>
              <input type="date" name="fecha" class="form-control form-control-sm<?php echo $esCampoFaltante('fecha') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('fecha'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha de firma</label>
              <input type="date" name="fecha_firma" class="form-control form-control-sm<?php echo $esCampoFaltante('fecha_firma') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('fecha_firma'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-12">
              <label class="form-label">Nombre completo</label>
              <input type="text" name="nombre_completo" class="form-control form-control-sm<?php echo $esCampoFaltante('nombre_completo') ? ' is-invalid' : ''; ?>" maxlength="150" value="<?php echo $obtenerValor('nombre_completo'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-6 col-sm-6 col-md-3">
              <label class="form-label">Nacionalidad</label>
              <select name="nacionalidad_id" class="form-select form-select-sm<?php echo $esCampoFaltante('nacionalidad_id') ? ' is-invalid' : ''; ?>" <?php echo $soloLectura ? 'disabled' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
                <option value="">Seleccione</option>
                <?php foreach ($nacionalidadesDisponibles as $nac) : ?>
                  <option value="<?php echo (int)$nac['id']; ?>" <?php echo $nacionalidadSeleccionadaId === (int)$nac['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($nac['nombre']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if ($soloLectura) : ?>
                <input type="hidden" name="nacionalidad_id" value="<?php echo $nacionalidadSeleccionadaId ?: ''; ?>">
              <?php endif; ?>
            </div>
            <div class="col-6 col-sm-6 col-md-3">
              <label class="form-label">Fecha de nacimiento</label>
              <input type="date" name="fecha_nacimiento" class="form-control form-control-sm<?php echo $esCampoFaltante('fecha_nacimiento') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('fecha_nacimiento'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-3 col-sm-2 col-md-2">
              <label class="form-label">Edad actual</label>
              <input type="number" name="edad_actual" class="form-control form-control-sm<?php echo $esCampoFaltante('edad_actual') ? ' is-invalid' : ''; ?>" min="18" max="120" value="<?php echo $obtenerValor('edad_actual'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-9 col-sm-6 col-md-2">
              <label class="form-label ag-keep-label" for="solicitudTipoIdentificacion" id="solicitudTipoIdentificacionLabel">Identificación</label>
              <select name="identificacion" id="solicitudTipoIdentificacion" class="form-select form-select-sm<?php echo $esCampoFaltante('identificacion') ? ' is-invalid' : ''; ?>" data-identificacion-select <?php echo $soloLectura ? 'disabled' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
                <option value="">Seleccione</option>
                <option value="INE" <?php echo $identificacionSeleccionada === 'INE' ? 'selected' : ''; ?>>INE</option>
                <option value="PASAPORTE" <?php echo $identificacionSeleccionada === 'PASAPORTE' ? 'selected' : ''; ?>>PASAPORTE</option>
                <option value="CEDULA PROFESIONAL" <?php echo $identificacionSeleccionada === 'CEDULA PROFESIONAL' ? 'selected' : ''; ?>>CEDULA PROFESIONAL</option>
              </select>
              <?php if ($soloLectura) : ?>
                <input type="hidden" name="identificacion" value="<?php echo htmlspecialchars($identificacionSeleccionada, ENT_QUOTES); ?>">
              <?php endif; ?>
            </div>
            <div class="col-6 col-sm-6 col-md-2<?php echo $mostrarIdentificacionNumero ? '' : ' d-none'; ?>" data-identificacion-container="numero" aria-hidden="<?php echo $mostrarIdentificacionNumero ? 'false' : 'true'; ?>">
              <label class="form-label ag-keep-label" for="solicitudIdentificacionNumero" id="solicitudIdentificacionNumeroLabel">Número identificación</label>
              <input type="text" id="solicitudIdentificacionNumero" name="identificacion_numero" class="form-control form-control-sm<?php echo $esCampoFaltante('identificacion_numero') ? ' is-invalid' : ''; ?>" maxlength="100" value="<?php echo $obtenerValor('identificacion_numero'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo (!$soloLectura && $mostrarIdentificacionNumero) ? ' required' : ''; ?> data-identificacion-input="numero" data-required-when-visible="1">
            </div>
            <div class="col-6 col-sm-6 col-md-2<?php echo $mostrarIdmex ? '' : ' d-none'; ?>" data-identificacion-container="idmex" aria-hidden="<?php echo $mostrarIdmex ? 'false' : 'true'; ?>">
              <label class="form-label ag-keep-label" for="solicitudIdmex" id="solicitudIdmexLabel">IDMEX/No.Identificación</label>
              <input type="text" id="solicitudIdmex" name="idmex" class="form-control form-control-sm<?php echo $esCampoFaltante('idmex') ? ' is-invalid' : ''; ?>" maxlength="50" value="<?php echo $obtenerValor('idmex'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo (!$soloLectura && $mostrarIdmex) ? ' required' : ''; ?> data-identificacion-input="idmex" data-required-when-visible="1" oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-3">
              <label class="form-label">CURP</label>
              <input type="text" name="curp" class="form-control form-control-sm<?php echo $esCampoFaltante('curp') ? ' is-invalid' : ''; ?>" maxlength="18" pattern="^[A-Z]{4}\d{6}[A-Z]{6}[0-9A-Z]{2}$" value="<?php echo $obtenerValor('curp'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">RFC</label>
              <input type="text" name="rfc" class="form-control form-control-sm<?php echo $esCampoFaltante('rfc') ? ' is-invalid' : ''; ?>" maxlength="13" pattern="^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$" value="<?php echo $obtenerValor('rfc'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <?php echo $renderTelefonoSolicitud([
              'col' => 'col-12 col-sm-6 col-md-2',
              'id' => 'solicitudCelular',
              'name' => 'celular',
              'hiddenId' => 'solicitudCelularHidden',
              'label' => 'Celular principal',
              'value' => $obtenerValorCrudo('celular'),
              'hintId' => 'solicitudCelularHint',
              'required' => true,
            ]); ?>
            <?php
            $telefonoFijoValor = preg_replace('/\D+/', '', $obtenerValorCrudo('telefono'));
            ?>
            <div class="col-12 col-sm-6 col-md-2">
              <label class="form-label ag-keep-label" for="solicitudTelefono">Teléfono principal</label>
              <input
                type="tel"
                id="solicitudTelefono"
                name="telefono"
                class="form-control form-control-sm<?php echo $esCampoFaltante('telefono') ? ' is-invalid' : ''; ?>"
                value="<?php echo htmlspecialchars($telefonoFijoValor, ENT_QUOTES); ?>"
                inputmode="numeric"
                pattern="\d+"
                maxlength="15"
                aria-describedby="solicitudTelefonoHint"
                <?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
              <div class="invalid-feedback">Ingrese un número válido.</div>
              <div id="solicitudTelefonoHint" class="form-text ag-field-hint" aria-hidden="true">Ingresa únicamente dígitos para el teléfono fijo.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Correo electrónico</label>
              <input type="email" name="email" class="form-control form-control-sm<?php echo $esCampoFaltante('email') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('email'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-12 col-sm-6 col-md-9">
              <label class="form-label">Domicilio</label>
              <input type="text" name="domicilio" class="form-control form-control-sm<?php echo $esCampoFaltante('domicilio') ? ' is-invalid' : ''; ?>" maxlength="255" value="<?php echo $obtenerValor('domicilio'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-6 col-sm-6 col-md-3">
              <label class="form-label">Estado civil</label>
              <input type="text" name="estado_civil" class="form-control form-control-sm<?php echo $esCampoFaltante('estado_civil') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('estado_civil'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-6 col-sm-6 col-md-3">
              <label class="form-label">Régimen</label>
              <input type="text" name="regimen" class="form-control form-control-sm" value="<?php echo $obtenerValor('regimen'); ?>" <?php echo $soloLectura ? 'readonly' : ''; ?>>
            </div>
            <div class="col-6 col-sm-6 col-md-3">
              <label class="form-label">Ocupación</label>
              <input type="text" name="ocupacion" class="form-control form-control-sm<?php echo $esCampoFaltante('ocupacion') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('ocupacion'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-6 col-sm-6 col-md-3">
              <label class="form-label">Empresa</label>
              <input type="text" name="empresa" class="form-control form-control-sm" value="<?php echo $obtenerValor('empresa'); ?>" <?php echo $soloLectura ? 'readonly' : ''; ?>>
            </div>
            <div class="col-12">
              <div class="ag-subsection-card">
                <div class="ag-subsection-header">
                  <h4 class="h6 mb-1">Testigo</h4>
                  <hr>
                  <p class="text-muted small mb-0">Datos del testigo que respaldará la firma del contrato.</p>
                </div>
                <div class="row g-3">
                  <div class="col-12 col-md-9">
                    <label class="form-label">Testigo de firma de contrato</label>
                    <input type="text" name="testigo_contrato" class="form-control form-control-sm<?php echo $esCampoFaltante('testigo_contrato') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('testigo_contrato'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
                  </div>
                  <?php echo $renderTelefonoSolicitud([
                    'col' => 'col-12 col-md-3',
                    'id' => 'celularTestigoContrato',
                    'name' => 'celular_testigo_contrato',
                    'hiddenId' => 'celularTestigoContratoHidden',
                    'label' => 'Celular testigo',
                    'value' => $obtenerValorCrudo('celular_testigo_contrato'),
                    'hintId' => 'celularTestigoContratoHint',
                    'required' => true,
                  ]); ?>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="ag-subsection-card">
                <div class="ag-subsection-header">
                  <h4 class="h6 mb-1">Referencias</h4>
                  <hr>
                  <p class="text-muted small mb-0">Proporciona dos referencias personales con su teléfono de contacto.</p>
                </div>
                <div class="row g-3">
                  <div class="col-12 col-md-9">
                    <label class="form-label">Nombre referencia 1</label>
                    <input type="text" name="nombre_referencia_1" class="form-control form-control-sm<?php echo $esCampoFaltante('nombre_referencia_1') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('nombre_referencia_1'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
                  </div>
                  <?php echo $renderTelefonoSolicitud([
                    'col' => 'col-12 col-md-3',
                    'id' => 'celularReferencia1',
                    'name' => 'celular_referencia_1',
                    'hiddenId' => 'celularReferencia1Hidden',
                    'label' => 'Celular referencia 1',
                    'value' => $obtenerValorCrudo('celular_referencia_1'),
                    'hintId' => 'celularReferencia1Hint',
                    'required' => true,
                  ]); ?>
                  <div class="col-12 col-md-9">
                    <label class="form-label">Nombre referencia 2</label>
                    <input type="text" name="nombre_referencia_2" class="form-control form-control-sm<?php echo $esCampoFaltante('nombre_referencia_2') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('nombre_referencia_2'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
                  </div>
                  <?php echo $renderTelefonoSolicitud([
                    'col' => 'col-12 col-md-3',
                    'id' => 'celularReferencia2',
                    'name' => 'celular_referencia_2',
                    'hiddenId' => 'celularReferencia2Hidden',
                    'label' => 'Celular referencia 2',
                    'value' => $obtenerValorCrudo('celular_referencia_2'),
                    'hintId' => 'celularReferencia2Hint',
                    'required' => true,
                  ]); ?>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="ag-subsection-card">
                <div class="ag-subsection-header">
                  <h4 class="h6 mb-1">Beneficiario</h4>
                  <hr>
                  <p class="text-muted small mb-0">Registra a la persona que quedará como beneficiaria.</p>
                </div>
                <div class="row g-3">
                  <div class="col-md-5">
                    <label class="form-label">Nombre beneficiario</label>
                    <input type="text" name="beneficiario" class="form-control form-control-sm<?php echo $esCampoFaltante('beneficiario') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('beneficiario'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Edad beneficiario</label>
                    <input type="number" name="edad_beneficiario" class="form-control form-control-sm<?php echo $esCampoFaltante('edad_beneficiario') ? ' is-invalid' : ''; ?>" min="0" max="120" value="<?php echo $obtenerValor('edad_beneficiario'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Parentesco</label>
                    <input type="text" name="parentesco_beneficiario" class="form-control form-control-sm<?php echo $esCampoFaltante('parentesco_beneficiario') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('parentesco_beneficiario'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?> aria-describedby="parentescoBeneficiarioHint">
                    <div id="parentescoBeneficiarioHint" class="form-text ag-field-hint">El valor capturado se propondrá automáticamente en el campo “DICE SER” al generar el contrato.</div>
                  </div>
                  <?php echo $renderTelefonoSolicitud([
                    'col' => 'col-md-3',
                    'id' => 'celularBeneficiario',
                    'name' => 'celular_beneficiario',
                    'hiddenId' => 'celularBeneficiarioHidden',
                    'label' => 'Celular beneficiario',
                    'value' => $obtenerValorCrudo('celular_beneficiario'),
                    'hintId' => 'celularBeneficiarioHint',
                  ]); ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="ag-form-card card shadow-sm mb-4">
        <div class="card-header ag-card-header">
          <div class="ag-card-header-text">
            <h4 class="card-title h5 mb-1">Albacea</h4>
            <hr>
            <p class="card-subtitle text-muted small mb-0">Activa esta sección solo si la solicitud requiere designar albacea.</p>
          </div>
          <div class="form-check form-switch ag-card-toggle mb-0">
            <input class="form-check-input" type="checkbox" id="switchAlbaceaSolicitud" data-role="albacea-switch" data-albacea-target="#camposAlbaceaSolicitud" data-albacea-input="albaceaActivoSolicitud" data-readonly="<?php echo $soloLectura ? 'true' : 'false'; ?>" data-enforce-required="<?php echo $enforceRequired ? '1' : '0'; ?>" <?php echo $albaceaActivo ? 'checked' : ''; ?> <?php echo $soloLectura ? 'disabled' : ''; ?> aria-controls="camposAlbaceaSolicitud" aria-expanded="<?php echo $albaceaActivo ? 'true' : 'false'; ?>">
            <label class="form-check-label" for="switchAlbaceaSolicitud">¿La solicitud cuenta con albacea?</label>
          </div>
        </div>
        <div id="camposAlbaceaSolicitud" class="card-body<?php echo $albaceaActivo ? '' : ' d-none'; ?>" data-albacea-container aria-hidden="<?php echo $albaceaActivo ? 'false' : 'true'; ?>">
          <input type="hidden" name="albacea_activo" id="albaceaActivoSolicitud" value="<?php echo $albaceaActivo ? '1' : '0'; ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Nombre del albacea</label>
              <input type="text" name="albacea_nombre" class="form-control form-control-sm<?php echo $esCampoFaltante('albacea_nombre') ? ' is-invalid' : ''; ?>" maxlength="150" data-albacea-field data-albacea-required value="<?php echo $obtenerValor('albacea_nombre'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?>>
            </div>
            <div class="col-md-2">
              <label class="form-label">Edad del albacea</label>
              <input type="number" name="albacea_edad" class="form-control form-control-sm<?php echo $esCampoFaltante('albacea_edad') ? ' is-invalid' : ''; ?>" min="18" max="120" data-albacea-field data-albacea-required value="<?php echo $obtenerValor('albacea_edad'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Parentesco</label>
              <input type="text" name="albacea_parentesco" class="form-control form-control-sm<?php echo $esCampoFaltante('albacea_parentesco') ? ' is-invalid' : ''; ?>" maxlength="100" data-albacea-field data-albacea-required value="<?php echo $obtenerValor('albacea_parentesco'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?>>
            </div>
            <?php echo $renderTelefonoSolicitud([
              'col' => 'col-md-3',
              'id' => 'albaceaCelular',
              'name' => 'albacea_celular',
              'hiddenId' => 'albaceaCelularHidden',
              'label' => 'Celular del albacea',
              'value' => $obtenerValorCrudo('albacea_celular'),
              'hintId' => 'albaceaCelularHint',
              'extraInputAttributes' => [
                'data-albacea-field' => true,
                'data-albacea-required' => true,
              ],
            ]); ?>
          </div>
        </div>
      </div>

      <div class="ag-form-card card shadow-sm mb-4">
        <div class="card-header ag-card-header">
          <div class="ag-card-header-text">
            <h4 class="card-title h5 mb-1">Datos del desarrollo</h4>
            <hr>
            <p class="card-subtitle text-muted small mb-0">Confirma el desarrollo y la información del lote asociado.</p>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Desarrollo</label>
              <select name="desarrollo_id" id="selectDesarrolloSolicitud" class="form-select form-select-sm<?php echo $esCampoFaltante('desarrollo_id') ? ' is-invalid' : ''; ?>" <?php echo $soloLectura ? 'disabled' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
                <option value="">Seleccione</option>
                <?php foreach ($desarrollosDisponibles as $desarrollo) : ?>
                  <option value="<?php echo (int)$desarrollo['id']; ?>" data-nombre="<?php echo htmlspecialchars($desarrollo['nombre']); ?>" data-tipo="<?php echo htmlspecialchars($desarrollo['tipo_contrato']); ?>" <?php echo $desarrolloSeleccionadoId === (int)$desarrollo['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($desarrollo['nombre']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if ($soloLectura) : ?>
                <input type="hidden" name="desarrollo_id" value="<?php echo $desarrolloSeleccionadoId ?: ''; ?>">
              <?php endif; ?>
              <input type="hidden" name="desarrollo" id="desarrolloNombreSolicitud" value="<?php echo htmlspecialchars($desarrolloSeleccionadoNombre); ?>">
              <input type="hidden" name="desarrollo_tipo_contrato" id="desarrolloTipoContratoSolicitud" value="<?php echo htmlspecialchars($solicitudActual['desarrollo_tipo_contrato'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Ubicación</label>
              <input type="text" name="ubicacion" class="form-control form-control-sm<?php echo $esCampoFaltante('ubicacion') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('ubicacion'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-4">
              <label class="form-label">Lote y manzana</label>
              <input type="text" name="lote_manzana" class="form-control form-control-sm<?php echo $esCampoFaltante('lote_manzana') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('lote_manzana'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Deslinde</label>
              <input type="text" name="deslinde" class="form-control form-control-sm" value="<?php echo $obtenerValor('deslinde'); ?>" <?php echo $soloLectura ? 'readonly' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Superficie</label>
              <input type="text" name="superficie" class="form-control form-control-sm<?php echo $esCampoFaltante('superficie') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('superficie'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Costo total</label>
              <input type="number" step="0.01" name="costo_total" class="form-control form-control-sm<?php echo $esCampoFaltante('costo_total') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('costo_total'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Enganche</label>
              <input type="number" step="0.01" name="enganche" class="form-control form-control-sm<?php echo $esCampoFaltante('enganche') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('enganche'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Saldo</label>
              <input type="number" step="0.01" name="saldo" class="form-control form-control-sm<?php echo $esCampoFaltante('saldo') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('saldo'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Plazo mensualidades</label>
              <input type="number" name="plazo_mensualidades" class="form-control form-control-sm<?php echo $esCampoFaltante('plazo_mensualidades') ? ' is-invalid' : ''; ?>" min="1" max="360" value="<?php echo $obtenerValor('plazo_mensualidades'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Apartado</label>
              <input type="number" step="0.01" name="apartado" class="form-control form-control-sm<?php echo $esCampoFaltante('apartado') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('apartado'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Complemento de enganche</label>
              <input type="number" step="0.01" name="complemento_enganche" class="form-control form-control-sm<?php echo $esCampoFaltante('complemento_enganche') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('complemento_enganche'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha de liquidación del enganche</label>
              <input type="date" name="fecha_liquidacion_enganche" class="form-control form-control-sm" value="<?php echo $obtenerValor('fecha_liquidacion_enganche'); ?>" <?php echo $soloLectura ? 'readonly' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Pago mensual</label>
              <input type="number" step="0.01" name="pago_mensual" class="form-control form-control-sm<?php echo $esCampoFaltante('pago_mensual') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('pago_mensual'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha del pago mensual</label>
              <input type="date" name="fecha_pago_mensual" class="form-control form-control-sm<?php echo $esCampoFaltante('fecha_pago_mensual') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('fecha_pago_mensual'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo $enforceRequired ? ' required' : ''; ?>>
            </div>
          </div>
        </div>
      </div>

      <div class="ag-form-card card shadow-sm mb-4">
        <div class="card-header ag-card-header">
          <div class="ag-card-header-text">
            <h4 class="card-title h5 mb-1">Condiciones económicas adicionales</h4>
            <hr>
            <p class="card-subtitle text-muted small mb-0">Gestiona los pagos anuales y la información complementaria del financiamiento.</p>
          </div>
          <div class="form-check form-switch ag-card-toggle mb-0">
            <input class="form-check-input" type="checkbox" id="togglePagoAnual" value="1"<?php echo $usaPagoAnual ? ' checked' : ''; ?><?php echo $soloLectura ? ' disabled' : ''; ?> data-role="pago-anual-switch" data-target="#camposPagoAnual" data-enforce-required="<?php echo $enforceRequired ? '1' : '0'; ?>" data-readonly="<?php echo $soloLectura ? 'true' : 'false'; ?>" aria-controls="camposPagoAnual" aria-expanded="<?php echo $usaPagoAnual ? 'true' : 'false'; ?>">
            <label class="form-check-label" for="togglePagoAnual">¿Pago anual?</label>
          </div>
        </div>
        <div class="card-body">
          <input type="hidden" name="usa_pago_anual" id="usaPagoAnualHidden" value="<?php echo $usaPagoAnual ? '1' : '0'; ?>">
          <div id="camposPagoAnual" class="row g-3<?php echo $usaPagoAnual ? '' : ' d-none'; ?>" data-pago-anual-container aria-hidden="<?php echo $usaPagoAnual ? 'false' : 'true'; ?>">
            <div class="col-md-4">
              <label class="form-label">Pago anual</label>
              <input type="number" step="0.01" name="pago_anual" class="form-control form-control-sm<?php echo $esCampoFaltante('pago_anual') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('pago_anual'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo ($usaPagoAnual && $enforceRequired) ? ' required' : ''; ?> data-pago-anual-field data-pago-anual-required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha del pago anual</label>
              <input type="date" name="fecha_pago_anual" class="form-control form-control-sm<?php echo $esCampoFaltante('fecha_pago_anual') ? ' is-invalid' : ''; ?>" value="<?php echo $obtenerValor('fecha_pago_anual'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo ($usaPagoAnual && $enforceRequired) ? ' required' : ''; ?> data-pago-anual-field data-pago-anual-required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Plazo anual (años)</label>
              <input type="number" name="plazo_anual" class="form-control form-control-sm<?php echo $esCampoFaltante('plazo_anual') ? ' is-invalid' : ''; ?>" min="0" max="50" value="<?php echo $obtenerValor('plazo_anual'); ?>"<?php echo $soloLectura ? ' readonly' : ''; ?><?php echo ($usaPagoAnual && $enforceRequired) ? ' required' : ''; ?> data-pago-anual-field data-pago-anual-required>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex justify-content-end gap-2">
        <?php if (!$soloLectura) : ?>
          <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Guardar borrador</button>
        <?php else : ?>
          <span class="badge bg-secondary p-3">Estado actual: <?php echo strtoupper(str_replace('_', ' ', $estadoActual)); ?></span>
        <?php endif; ?>
      </div>
    </form>
  </div>
</section>
<?php
require_once 'vistas/partials/modal_placeholders_solicitud.php';
require_once 'vistas/partials/modal_regresar_borrador.php';
require_once 'vistas/partials/modal_cliente_coincidente_solicitud.php';
?>
