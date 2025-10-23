<?php
use App\Controllers\ControladorContratos;
use App\Controllers\ControladorDesarrollos;
use App\Controllers\ControladorParametros;
use App\Controllers\ControladorSolicitudes;
use App\Models\ModeloClientes;
use App\Models\ModeloContratos;
/**
 * Vista para crear un cliente y su contrato de manera unificada.
 * Este módulo reemplaza a los formularios modales y se muestra como una página
 * independiente dentro del panel. Agrupa los campos en secciones para facilitar
 * la captura de información y utiliza la lógica existente para cargar
 * nacionalidades, tipos de contrato y desarrollos.
 */

if (!in_array($_SESSION['permission'] ?? '', ['moderator','senior','owner','admin'], true)) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger">No tiene permisos para acceder a esta sección.</div></div></section>';
    return;
}

// Procesar creación si se envía el formulario
ControladorContratos::ctrCrearContratoCompleto();

$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

// Obtener listas para los selects
$nacionalidades = [];
$tiposContrato = [];
if (class_exists(ControladorParametros::class)) {
    $nacionalidades = ControladorParametros::ctrMostrarVariables('nacionalidad');
    $tipos = ControladorParametros::ctrMostrarVariables('tipo_contrato');
    foreach ($tipos as $t) {
        $tiposContrato[$t['identificador']] = $t['nombre'];
    }
}
// Obtener desarrollos disponibles
$desarrollos = ControladorDesarrollos::ctrMostrarDesarrollos();

$clienteId = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
$clienteData = null;

if ($clienteId > 0) {
    $clienteData = ModeloClientes::mdlMostrarClientePorId($clienteId);
}

$contratoEditarId = isset($_GET['contrato_id']) ? intval($_GET['contrato_id']) : 0;
$estaEditando = $contratoEditarId > 0;
$verParam = isset($_GET['ver']) ? strtolower(trim((string)$_GET['ver'])) : '';
$esVistaSoloLectura = $estaEditando && $verParam !== '' && !in_array($verParam, ['0', 'false', 'no'], true);
$contratoExistente = null;
$estatusContrato = null;

$solicitudId = isset($_GET['solicitud_id']) ? intval($_GET['solicitud_id']) : 0;
$solicitudSeleccionada = null;
$clientePrefill = [];
$contratoPrefill = [];
$desarrolloPrefill = [];
$solicitudJson = '';
$jsonContrato = [];

$tituloPagina = $esVistaSoloLectura
    ? 'Ver contrato'
    : ($estaEditando ? 'Editar contrato' : 'Crear contrato');
$textoBoton = $esVistaSoloLectura
    ? 'Modo lectura'
    : ($estaEditando ? 'Actualizar contrato' : 'Guardar contrato');

if ($estaEditando) {
    $contratoExistente = ModeloContratos::mdlMostrarContratoPorId($contratoEditarId);
    if (!$contratoExistente) {
        echo '<section class="content"><div class="container-fluid"><div class="alert alert-warning">El contrato indicado no existe.</div></div></section>';
        return;
    }

    $estatusContrato = isset($contratoExistente['estatus']) ? (int)$contratoExistente['estatus'] : null;

    $jsonGuardado = json_decode($contratoExistente['datta_contrato'] ?? '[]', true);
    if (!is_array($jsonGuardado)) {
        $jsonGuardado = [];
    }

    $jsonCliente = $jsonGuardado['cliente'] ?? [];
    $jsonDesarrollo = $jsonGuardado['desarrollo'] ?? [];
    $jsonContrato = $jsonGuardado['contrato'] ?? [];

    $clienteId = (int)($contratoExistente['cliente_id'] ?? ($jsonCliente['id'] ?? 0));
    if ($clienteId > 0) {
        $clienteData = ModeloClientes::mdlMostrarClientePorId($clienteId) ?: $clienteData;
    }

    $clientePrefill = [
        'cliente_nombre' => strtoupper(trim((string)($jsonCliente['nombre'] ?? ''))),
        'cliente_nacionalidad' => trim((string)($jsonCliente['nacionalidad'] ?? '')),
        'cliente_genero' => trim((string)($jsonCliente['genero'] ?? '')),
        'cliente_fecha_nacimiento' => trim((string)($jsonCliente['fecha_nacimiento'] ?? ($jsonCliente['fecha'] ?? ''))),
        'cliente_rfc' => strtoupper(trim((string)($jsonCliente['rfc'] ?? ''))),
        'cliente_curp' => strtoupper(trim((string)($jsonCliente['curp'] ?? ''))),
        'cliente_identificacion' => strtoupper(trim((string)($jsonCliente['identificacion'] ?? ''))),
        'cliente_ine' => strtoupper(trim((string)($jsonCliente['ine'] ?? ''))),
        'cliente_estado_civil' => strtoupper(trim((string)($jsonCliente['estado_civil'] ?? ''))),
        'cliente_ocupacion' => strtoupper(trim((string)($jsonCliente['ocupacion'] ?? ''))),
        'dice_ser' => strtoupper(trim((string)($jsonCliente['dice_ser'] ?? ($jsonContrato['dice_ser'] ?? '')))),
        'cliente_telefono' => trim((string)($jsonCliente['telefono'] ?? '')),
        'telefono_cliente_visible' => trim((string)($jsonCliente['telefono'] ?? '')),
        'cliente_domicilio' => strtoupper(trim((string)($jsonCliente['domicilio'] ?? ''))),
        'cliente_email' => trim((string)($jsonCliente['email'] ?? '')),
        'cliente_beneficiario' => strtoupper(trim((string)($jsonCliente['beneficiario'] ?? ''))),
    ];

    $desarrolloPrefill = [
        'desarrollo_id' => (int)($contratoExistente['desarrollo_id'] ?? ($jsonDesarrollo['id'] ?? 0)),
        'contrato_superficie' => trim((string)($jsonContrato['superficie'] ?? '')),
        'superficie_fixed' => trim((string)($jsonContrato['superficie_fixed'] ?? ($jsonDesarrollo['superficie_fixed'] ?? ''))),
        'tipo_contrato_id' => trim((string)($jsonDesarrollo['tipo_contrato_id'] ?? ($jsonContrato['tipo_contrato'] ?? ''))),
        'tipo_contrato_nombre' => trim((string)($jsonDesarrollo['tipo_contrato_nombre'] ?? ($jsonDesarrollo['tipo_contrato'] ?? ($jsonContrato['tipo_contrato'] ?? '')))),
    ];

    $contratoPrefill = [
        'folio' => trim((string)($jsonContrato['folio'] ?? ($contratoExistente['folio'] ?? ''))),
        'fecha_contrato' => trim((string)($jsonContrato['fecha_contrato_date'] ?? $jsonContrato['fecha_contrato'] ?? '')),
        'fecha_contrato_fixed' => trim((string)($jsonContrato['fecha_contrato_fixed'] ?? '')),
        'fecha_contrato_texto' => trim((string)($jsonContrato['fecha_contrato'] ?? '')),
        'inicio_pagos' => trim((string)($jsonContrato['inicio_pagos_date'] ?? '')),
        'inicio_pagos_texto' => trim((string)($jsonContrato['inicio_pagos'] ?? '')),
        'fracciones' => trim((string)($jsonContrato['fraccion_vendida'] ?? '')),
        'habitacional' => trim((string)($jsonContrato['habitacional_colindancias'] ?? '')),
        'entrega_posecion_date' => trim((string)($jsonContrato['entrega_posecion_date'] ?? '')),
        'entrega_posecion' => trim((string)($jsonContrato['entrega_posecion'] ?? '')),
        'clausula_c_posecion' => trim((string)($jsonContrato['clausula_c_posecion'] ?? '')),
        'fecha_firma' => trim((string)($jsonContrato['fecha_firma_contrato_date'] ?? '')),
        'fecha_firma_texto' => trim((string)($jsonContrato['fecha_firma_contrato'] ?? '')),
        'rango_pago_inicio_date' => trim((string)($jsonContrato['rango_pago_inicio_date'] ?? '')),
        'rango_pago_inicio' => trim((string)($jsonContrato['rango_pago_inicio'] ?? '')),
        'rango_pago_fin_date' => trim((string)($jsonContrato['rango_pago_fin_date'] ?? '')),
        'rango_pago_fin' => trim((string)($jsonContrato['rango_pago_fin'] ?? '')),
        'rango_pago' => trim((string)($jsonContrato['rango_pago'] ?? $jsonContrato['rango_compromiso_pago'] ?? '')),
        'financiamiento_clusulas' => trim((string)($jsonContrato['financiamiento_clusulas'] ?? '')),
        'mensualidades' => trim((string)($jsonContrato['mensualidades'] ?? '')),
        'parcialidades_anuales' => trim((string)($jsonContrato['parcialidades_anuales'] ?? '')),
        'monto_inmueble' => (string)($jsonContrato['monto_precio_inmueble_valor'] ?? $jsonContrato['monto_precio_inmueble'] ?? ''),
        'monto_inmueble_fixed' => trim((string)($jsonContrato['monto_precio_inmueble_fixed'] ?? '')),
        'enganche' => (string)($jsonContrato['enganche_valor'] ?? $jsonContrato['enganche'] ?? ''),
        'enganche_fixed' => trim((string)($jsonContrato['enganche_fixed'] ?? '')),
        'saldo_pago' => (string)($jsonContrato['saldo_pago_valor'] ?? $jsonContrato['saldo_pago'] ?? ''),
        'saldo_pago_fixed' => trim((string)($jsonContrato['saldo_pago_fixed'] ?? '')),
        'pago_mensual' => (string)($jsonContrato['pago_mensual_valor'] ?? $jsonContrato['pago_mensual'] ?? ''),
        'pago_mensual_fixed' => trim((string)($jsonContrato['pago_mensual_fixed'] ?? '')),
        'penalizacion' => (string)($jsonContrato['penalizacion_10_valor'] ?? $jsonContrato['penalizacion_10'] ?? $jsonContrato['penalizacion'] ?? ''),
        'penalizacion_fixed' => trim((string)($jsonContrato['penalizacion_10_fixed'] ?? $jsonContrato['penalizacion_fixed'] ?? '')),
        'vigencia_pagare' => trim((string)($jsonContrato['vigencia_pagare_date'] ?? '')),
        'vigencia_pagare_texto' => trim((string)($jsonContrato['vigencia_pagare'] ?? '')),
        'observaciones' => trim((string)($jsonContrato['observaciones'] ?? $jsonContrato['referencias'] ?? '')),
        'superficie_fixed' => trim((string)($jsonContrato['superficie_fixed'] ?? '')),
    ];

    $solicitudId = (int)($jsonContrato['solicitud_origen_id'] ?? 0);
}

if (!$estaEditando && $solicitudId > 0) {
    $prefillSolicitud = ControladorContratos::obtenerPrefillSolicitud($solicitudId, [
        'nacionalidades' => $nacionalidades,
        'tiposContrato' => $tiposContrato,
        'desarrollos' => $desarrollos,
    ]);

    if (!$prefillSolicitud) {
        echo "<script>Swal.fire({icon:'error',title:'Solicitud no disponible',text:'No se encontró la solicitud seleccionada.'}).then(()=>{window.location='index.php?ruta=solicitudes';});</script>";
    } else {
        $solicitudSeleccionada = $prefillSolicitud['solicitud'] ?? null;

        if (!is_array($solicitudSeleccionada)) {
            echo "<script>Swal.fire({icon:'error',title:'Solicitud no disponible',text:'No se encontró la solicitud seleccionada.'}).then(()=>{window.location='index.php?ruta=solicitudes';});</script>";
        } else {
            $clientePrefill = array_merge($clientePrefill, $prefillSolicitud['cliente'] ?? []);
            $contratoPrefill = array_merge($contratoPrefill, $prefillSolicitud['contrato'] ?? []);
            if (!empty($prefillSolicitud['desarrollo'])) {
                $desarrolloPrefill = array_merge($desarrolloPrefill, $prefillSolicitud['desarrollo']);
            }

            $resumenPrefill = $prefillSolicitud['resumen'] ?? [];
            if (!empty($resumenPrefill['id'])) {
                $solicitudId = (int)$resumenPrefill['id'];
            }

            $solicitudJson = htmlspecialchars(json_encode($solicitudSeleccionada, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        }
    }
}

if (empty($desarrolloPrefill)) {
    $desarrolloPrefill = [
        'desarrollo_id' => 0,
        'contrato_superficie' => '',
        'tipo_contrato_id' => '',
        'tipo_contrato_nombre' => '',
    ];
}

$solicitudAsociada = $solicitudSeleccionada;
if ($solicitudAsociada === null && $solicitudId > 0) {
    $solicitudAsociada = ControladorSolicitudes::ctrObtenerSolicitudPorId($solicitudId);
}
$soloLecturaPorSolicitud = $estaEditando && $solicitudId > 0 && !$esVistaSoloLectura;
$soloLecturaCompleto = $soloLecturaPorSolicitud || $esVistaSoloLectura;
$puedeVincularSolicitud = !$soloLecturaCompleto && $solicitudSeleccionada === null;
$solicitudManualId = ($puedeVincularSolicitud && $solicitudId > 0) ? $solicitudId : 0;
$atributosSoloLectura = '';
if ($soloLecturaCompleto) {
    $atributosSoloLectura = ' data-readonly="1" data-view-mode="' . ($esVistaSoloLectura ? 'ver' : 'solicitud') . '"';
}

require_once 'vistas/partials/content_header.php';
$accionesHeader = [
    [
        'label' => 'Volver a contratos',
        'url' => 'index.php?ruta=contratos',
        'icon' => 'fas fa-arrow-left',
        'class' => 'btn-outline-secondary'
    ],
];
ag_render_content_header([
    'title' => $tituloPagina,
    'subtitle' => $estaEditando ? 'Actualiza los datos del contrato seleccionado.' : 'Completa la información para generar un nuevo contrato.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Contratos', 'url' => 'index.php?ruta=contratos'],
        ['label' => $tituloPagina],
    ],
    'actions' => $accionesHeader,
]);
require_once 'vistas/partials/record_toolbar.php';

$toolbarPrimary = [
    'label' => 'Nuevo contrato',
    'url' => 'index.php?ruta=crearContrato',
    'icon' => 'fas fa-file-signature',
    'class' => 'btn btn-primary'
];
$toolbarSecondary = [
    'label' => 'Volver a contratos',
    'url' => 'index.php?ruta=contratos',
    'icon' => 'fas fa-arrow-left',
    'class' => 'btn btn-outline-secondary'
];

$contratoIdActual = $estaEditando ? $contratoEditarId : 0;
$folioContrato = $estaEditando
    ? trim((string)($contratoExistente['folio'] ?? ''))
    : trim((string)($contratoPrefill['folio'] ?? ''));
$clienteNombreContrato = '';
if (is_array($clienteData) && !empty($clienteData['nombre'])) {
    $clienteNombreContrato = trim((string)$clienteData['nombre']);
} elseif (!empty($clientePrefill['cliente_nombre'])) {
    $clienteNombreContrato = trim((string)$clientePrefill['cliente_nombre']);
}
$estadoContratoValor = $estatusContrato !== null ? (int)$estatusContrato : 1;
$estadoContratoMap = [
    0 => ['label' => 'Archivado', 'class' => 'badge bg-secondary'],
    1 => ['label' => 'Activo', 'class' => 'badge bg-success'],
    2 => ['label' => 'Cancelado', 'class' => 'badge bg-danger'],
];
$toolbarBadges = [];
if (isset($estadoContratoMap[$estadoContratoValor])) {
    $toolbarBadges[] = [
        'label' => $estadoContratoMap[$estadoContratoValor]['label'],
        'class' => $estadoContratoMap[$estadoContratoValor]['class'],
    ];
}
if ($solicitudId > 0) {
    $toolbarBadges[] = [
        'label' => 'Solicitud #' . $solicitudId,
        'class' => 'badge bg-info text-dark'
    ];
}
$toolbarMeta = [];
if ($estaEditando && !empty($contratoExistente['created_at'])) {
    $toolbarMeta[] = 'Creado el ' . (string)$contratoExistente['created_at'];
}
if (!empty($contratoPrefill['tipo_contrato_nombre'] ?? '')) {
    $toolbarMeta[] = 'Tipo: ' . (string)$contratoPrefill['tipo_contrato_nombre'];
}

$toolbarTitle = $estaEditando
    ? ($folioContrato !== '' ? 'Contrato ' . $folioContrato : 'Contrato #' . $contratoIdActual)
    : 'Nuevo contrato';
$toolbarSubtitle = $clienteNombreContrato !== ''
    ? 'Cliente: ' . $clienteNombreContrato
    : ($estaEditando ? 'Detalle del contrato registrado.' : 'Captura los datos para generar el contrato.');

$menuAcciones = [];
$esCanceladoContrato = $estadoContratoValor === 2;

if ($estaEditando) {
    if ($esVistaSoloLectura && !$esCanceladoContrato) {
        $menuAcciones[] = [
            'type' => 'link',
            'label' => 'Editar contrato',
            'icon' => 'fas fa-pen',
            'url' => 'index.php?ruta=crearContrato&contrato_id=' . $contratoIdActual,
        ];
    }

    $menuAcciones[] = [
        'type' => 'button',
        'label' => 'Ver placeholders',
        'icon' => 'fas fa-tags',
        'class' => 'btnVerPlaceholdersContrato',
        'data' => [
            'contrato-id' => $contratoIdActual,
        ],
    ];

    if ($solicitudId > 0) {
        $menuAcciones[] = [
            'type' => 'link',
            'label' => 'Ver solicitud de origen',
            'icon' => 'fas fa-link',
            'url' => 'index.php?ruta=solicitudes&solicitud_id=' . $solicitudId,
        ];
    }

    $menuAcciones[] = [
        'type' => 'button',
        'label' => 'Generar documentos',
        'icon' => 'fas fa-file-word',
        'class' => 'btnGenerarContrato',
        'data' => [
            'contrato-id' => $contratoIdActual,
        ],
        'attributes' => $esCanceladoContrato ? ['disabled' => true, 'aria-disabled' => 'true'] : [],
    ];

    if (!$esCanceladoContrato) {
        $menuAcciones[] = [
            'type' => 'form',
            'label' => 'Cancelar contrato',
            'icon' => 'fas fa-ban',
            'action' => 'index.php?ruta=contratos',
            'method' => 'post',
            'form_class' => 'formCancelarContrato',
            'inputs' => [
                ['name' => 'csrf_token', 'value' => $csrfToken],
                ['name' => 'cancelarContrato', 'value' => '1'],
                ['name' => 'contrato_id', 'value' => (string)$contratoIdActual],
                ['name' => 'motivo_cancelacion', 'value' => ''],
                ['name' => 'password_confirmacion', 'value' => ''],
            ],
            'confirm' => '¿Desea cancelar este contrato? Esta acción no se puede deshacer.'
        ];
    }
}

ag_render_record_toolbar([
    'primary_action' => $toolbarPrimary,
    'secondary_action' => $toolbarSecondary,
    'title' => $toolbarTitle,
    'subtitle' => $toolbarSubtitle,
    'badges' => $toolbarBadges,
    'meta' => $toolbarMeta,
    'menu_actions' => $menuAcciones,
]);
?>

<?php if ($solicitudSeleccionada): ?>
<div class="modal fade" id="modalSolicitudContrato" tabindex="-1" aria-labelledby="modalSolicitudContratoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalSolicitudContratoLabel">Detalle de la solicitud</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body ag-modal-body-scroll">
        <div class="row g-3" id="detalleSolicitudContrato"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<section class="content">
  <div class="container-fluid">
    <?php
    $contratoCancelado = $estaEditando && ($estatusContrato === 2
        || strtolower((string)($jsonContrato['estado'] ?? '')) === 'cancelado');
    if ($contratoCancelado) {
        $motivoCancelacionContrato = trim((string)($jsonContrato['motivo_cancelacion'] ?? ''));
        $fechaCancelacionContrato = trim((string)($jsonContrato['cancelado_en'] ?? ''));
        ?>
      <div class="callout callout-danger">
        <h5 class="mb-2"><i class="fas fa-ban me-2"></i>Contrato cancelado</h5>
        <?php if ($motivoCancelacionContrato !== '') : ?>
          <p class="mb-2"><?php echo nl2br(htmlspecialchars($motivoCancelacionContrato)); ?></p>
        <?php else : ?>
          <p class="mb-2">Este contrato fue cancelado.</p>
        <?php endif; ?>
        <?php if ($fechaCancelacionContrato !== '') : ?>
          <p class="mb-0 small text-muted">Cancelado el <?php echo htmlspecialchars($fechaCancelacionContrato); ?>.</p>
        <?php endif; ?>
      </div>
    <?php } ?>
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
          <h2 class="h4 mb-0">Configuración del contrato</h2>
          <?php if ($puedeVincularSolicitud): ?>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnVincularSolicitudContrato">
              <i class="fas fa-magnifying-glass me-1"></i>Buscar solicitud relacionada
            </button>
          <?php endif; ?>
        </div>
        <?php if ($soloLecturaPorSolicitud): ?>
          <div class="callout callout-warning">
            <h5 class="mb-2">Contrato vinculado a una solicitud</h5>
            <?php if ($solicitudAsociada): ?>
              <p class="mb-1">
                Este contrato está asociado con la solicitud
                <strong>#<?php echo (int)($solicitudAsociada['id'] ?? $solicitudId); ?></strong>
                <?php if (!empty($solicitudAsociada['folio'])): ?>
                  (<span class="text-uppercase"><?php echo htmlspecialchars((string)$solicitudAsociada['folio']); ?></span>)
                <?php endif; ?>
                de <?php echo htmlspecialchars((string)($solicitudAsociada['nombre_completo'] ?? '')); ?>.
                <a class="fw-bold" href="index.php?ruta=solicitudes&amp;solicitud_id=<?php echo (int)($solicitudAsociada['id'] ?? $solicitudId); ?>">
                  Ver solicitud vinculada
                </a>
              </p>
            <?php else: ?>
              <p class="mb-1">
                Este contrato indica una solicitud vinculada (#<?php echo (int)$solicitudId; ?>),
                pero no se pudo localizar el registro de la solicitud.
              </p>
            <?php endif; ?>
            <p class="mb-0 text-muted">
              Los campos permanecen en modo de solo lectura para preservar la información capturada desde la solicitud.
            </p>
          </div>
        <?php endif; ?>
        <?php if ($esVistaSoloLectura): ?>
          <div class="callout callout-info">
            <h5 class="mb-2">Consulta del contrato</h5>
            <p class="mb-1">Este contrato se muestra en modo de solo lectura. Utiliza los botones del encabezado para editarlo o iniciar un nuevo contrato.</p>
            <?php if ($estatusContrato === 2): ?>
              <p class="mb-0 text-danger"><i class="fas fa-ban me-1"></i>El contrato está cancelado, por lo que no es posible editarlo.</p>
            <?php else: ?>
              <p class="mb-0 text-muted">Todos los campos del formulario están bloqueados para evitar cambios accidentales.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($solicitudSeleccionada): ?>
          <div class="callout callout-info shadow-sm mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
              <div>
                <h5 class="mb-2 text-info">
                  <i class="fas fa-file-signature me-2"></i>Solicitud vinculada
                </h5>
                <ul class="list-unstyled mb-0 text-muted small">
                  <li class="mb-1">
                    <i class="fas fa-hashtag me-2 text-info"></i>
                    Folio: <span class="fw-semibold text-body"><?php echo htmlspecialchars($solicitudSeleccionada['folio'] ?? ''); ?></span>
                  </li>
                  <li class="mb-1">
                    <i class="fas fa-user me-2 text-info"></i>
                    Cliente: <span class="fw-semibold text-body"><?php echo htmlspecialchars($solicitudSeleccionada['nombre_completo'] ?? ''); ?></span>
                  </li>
                  <?php if (!empty($solicitudSeleccionada['creado_en'])): ?>
                    <li>
                      <i class="fas fa-calendar-day me-2 text-info"></i>
                      Capturada el <span class="fw-semibold text-body"><?php echo htmlspecialchars((string)$solicitudSeleccionada['creado_en']); ?></span>
                    </li>
                  <?php endif; ?>
                </ul>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-info" id="btnVerSolicitudContrato" data-solicitud="<?php echo $solicitudJson; ?>">
                  <i class="fas fa-eye me-1"></i>Ver solicitud
                </button>
                <a href="index.php?ruta=solicitudes" class="btn btn-outline-secondary">
                  <i class="fas fa-exchange-alt me-1"></i>Cambiar solicitud
                </a>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Usar la misma ruta crearContrato para enviar el formulario. El controlador identificará
             la operación a través del campo oculto crearContratoCompleto -->
        <form id="formCrearContratoCompleto" class="ag-form-layout" method="post" action="index.php?ruta=crearContrato"<?php echo $atributosSoloLectura; ?>>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <?php if ($estaEditando && !$esVistaSoloLectura): ?>
            <input type="hidden" name="editarContratoCompleto" value="1">
          <?php elseif (!$estaEditando): ?>
            <input type="hidden" name="crearContratoCompleto" value="1">
          <?php endif; ?>
          <?php if ($estaEditando): ?>
            <input type="hidden" name="contrato_id" value="<?php echo (int)$contratoEditarId; ?>">
          <?php endif; ?>
          <?php if ($clienteId > 0): ?>
            <input type="hidden" name="cliente_id" value="<?php echo (int)$clienteId; ?>">
          <?php endif; ?>
          <input type="hidden" name="solicitud_id_origen" id="solicitudIdOrigenInput" value="<?php echo $solicitudId > 0 ? (int)$solicitudId : ''; ?>">

          <?php $mostrarResumenCliente = !$estaEditando && $clienteId > 0 && $clienteData; ?>
          <div class="ag-form-card card shadow-sm mb-4">
            <div class="card-header ag-card-header">
              <div class="ag-card-header-text">
                <h5 class="card-title mb-1">Datos del cliente</h5>
                <hr>
                <p class="card-subtitle text-muted small mb-0">Verifica o captura los datos del cliente titular del contrato.</p>
              </div>
              <span class="badge bg-light text-muted">Requeridos</span>
            </div>
            <div class="card-body">
            <?php if ($mostrarResumenCliente): ?>
              <div class="callout callout-info shadow-sm mb-0">
                <div class="d-flex align-items-start gap-3">
                  <div class="flex-shrink-0 text-info">
                    <i class="fas fa-user-check fa-2x"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="text-info text-uppercase fw-bold mb-2">Cliente existente vinculado</h6>
                    <dl class="row mb-0 small text-muted">
                      <dt class="col-sm-4">Nombre</dt>
                      <dd class="col-sm-8 text-body fw-semibold"><?php echo htmlspecialchars($clienteData['nombre']); ?></dd>
                      <?php if (!empty($clienteData['rfc'])): ?>
                        <dt class="col-sm-4">RFC</dt>
                        <dd class="col-sm-8 text-body fw-semibold text-uppercase"><?php echo htmlspecialchars($clienteData['rfc']); ?></dd>
                      <?php endif; ?>
                      <?php if (!empty($clienteData['telefono'])): ?>
                        <dt class="col-sm-4">Teléfono</dt>
                        <dd class="col-sm-8 text-body fw-semibold"><?php echo htmlspecialchars($clienteData['telefono']); ?></dd>
                      <?php endif; ?>
                      <?php if (!empty($clienteData['email'])): ?>
                        <dt class="col-sm-4">Correo</dt>
                        <dd class="col-sm-8 text-body fw-semibold text-lowercase"><?php echo htmlspecialchars($clienteData['email']); ?></dd>
                      <?php endif; ?>
                    </dl>
                  </div>
                </div>
              </div>
              <input type="hidden" id="clienteRfc" value="<?php echo htmlspecialchars(strtoupper(trim((string)($clienteData['rfc'] ?? ''))), ENT_QUOTES); ?>">
              <input type="hidden" id="clienteCurp" value="<?php echo htmlspecialchars(strtoupper(trim((string)($clienteData['curp'] ?? ''))), ENT_QUOTES); ?>">
            <?php else: ?>
              <div class="row g-3">
                <?php include "vistas/partials/form_cliente.php"; ?>
              </div>
            <?php endif; ?>
            </div>
          </div>

          <div class="ag-form-card card shadow-sm mb-4">
            <div class="card-header ag-card-header">
              <div class="ag-card-header-text">
                <h5 class="card-title mb-1">Datos del desarrollo</h5>
                <hr>
                <p class="card-subtitle text-muted small mb-0">Selecciona el desarrollo y confirma los detalles del lote.</p>
              </div>
              <span class="badge bg-light text-muted">Selecciona una opción</span>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <?php include "vistas/partials/form_desarrollo.php"; ?>
              </div>
            </div>
          </div>

          <div class="ag-form-card card shadow-sm mb-4">
            <div class="card-header ag-card-header">
              <div class="ag-card-header-text">
                <h5 class="card-title mb-1">Datos del contrato</h5>
                <hr>
                <p class="card-subtitle text-muted small mb-0">Completa la información económica y administrativa del contrato.</p>
              </div>
              <span class="badge bg-light text-muted">Completa la información</span>
            </div>
            <div class="card-body">
              <?php if ($puedeVincularSolicitud): ?>
                <div id="contratoSolicitudVinculadaManual" class="alert alert-secondary small<?php echo $solicitudManualId > 0 ? '' : ' d-none'; ?> mb-3" role="alert">
                  <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <div>
                      <strong>Solicitud a vincular:</strong>
                      <span id="textoSolicitudVinculada" class="d-block fw-semibold"><?php echo $solicitudManualId > 0 ? 'Solicitud #' . (int)$solicitudManualId : ''; ?></span>
                      <small id="detalleSolicitudVinculada" class="text-muted d-block"></small>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnQuitarSolicitudContrato" title="Quitar solicitud">
                      <i class="fas fa-unlink me-1"></i>Quitar
                    </button>
                  </div>
                  <p class="mb-0 mt-2 text-muted">Al guardar, el contrato se vinculará con la solicitud indicada.</p>
                </div>
              <?php endif; ?>
              <div class="row g-3">
                <?php include "vistas/partials/form_contrato.php"; ?>
              </div>
            </div>
          </div>

          <div id="crearContratoFeedback" class="alert d-none" role="alert"></div>

          <div class="text-end mt-4">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($textoBoton); ?></button>
          </div>
        </form>

        
      </div>
    </div>
  </div>
</section>

<?php require_once 'vistas/partials/modal_placeholders_contrato.php'; ?>

<?php if ($puedeVincularSolicitud): ?>
<div class="modal fade" id="modalBuscarSolicitudContrato" tabindex="-1" aria-labelledby="modalBuscarSolicitudContratoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalBuscarSolicitudContratoLabel">Solicitudes compatibles</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3 text-muted small">Se mostrarán las solicitudes que coincidan con el folio, RFC o CURP capturados en el contrato. Solo se listan solicitudes sin contrato vinculado.</p>
          <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2 mb-3">
            <label for="filtroRapidoSolicitudes" class="form-label mb-0 small text-muted">Filtrar resultados</label>
            <input type="search" id="filtroRapidoSolicitudes" class="form-control form-control-sm" placeholder="Buscar por folio, RFC, CURP o nombre" data-filtro-rapido>
          </div>
        <div class="alert alert-info d-none" data-estado-modal></div>
        <div class="table-responsive d-none" data-contenedor-tabla>
          <table class="table table-sm align-middle mb-0" aria-describedby="modalBuscarSolicitudContratoLabel">
            <caption class="visually-hidden">Resultados de solicitudes compatibles con el contrato</caption>
            <thead>
              <tr>
                <th>ID</th>
                <th>Folio</th>
                <th>Nombre</th>
                <th>RFC</th>
                <th>CURP</th>
                <th>Estado</th>
                <th>Creada</th>
                <th>Coincidencias</th>
                <th></th>
              </tr>
            </thead>
            <tbody data-resultados-solicitudes></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-reintentar-busqueda>
          <i class="fas fa-rotate me-1"></i>Buscar nuevamente
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php if ($soloLecturaCompleto): ?>
<style>
  .ag-readonly-field {
    background-color: #f8f9fa !important;
    cursor: not-allowed;
  }
</style>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formCrearContratoCompleto');
    if (!form || form.dataset.readonly !== '1') {
      return;
    }

    const modoLectura = form.getAttribute('data-view-mode') || '';

    const elementos = form.querySelectorAll('input, select, textarea');
    elementos.forEach((el) => {
      if (!el || el.type === 'hidden' || el.name === 'csrf_token') {
        return;
      }

      const tag = el.tagName.toLowerCase();
      if (tag === 'select') {
        el.setAttribute('disabled', 'disabled');
        el.classList.add('ag-readonly-field');
        return;
      }

      if (tag === 'textarea') {
        el.setAttribute('readonly', 'readonly');
        el.classList.add('ag-readonly-field');
        return;
      }

      if (tag === 'input') {
        const tipo = (el.getAttribute('type') || '').toLowerCase();
        if (['checkbox', 'radio', 'file'].includes(tipo)) {
          el.setAttribute('disabled', 'disabled');
        } else {
          el.setAttribute('readonly', 'readonly');
        }
        el.classList.add('ag-readonly-field');
      }
    });

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.classList.remove('btn-primary');
      submitBtn.classList.add('btn-outline-secondary');
      submitBtn.textContent = modoLectura === 'ver' ? 'Solo lectura' : 'Edición bloqueada';
    }
  });
</script>
<?php endif; ?>
