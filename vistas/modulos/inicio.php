<?php
use App\Controllers\ControladorClientes;
use App\Controllers\ControladorConsola;
use App\Controllers\ControladorContratos;
use App\Controllers\ControladorDesarrollos;
use App\Controllers\ControladorParametros;
use App\Controllers\ControladorSolicitudes;
/**
 * Módulo de inicio (dashboard) con opciones principales.
 */
ControladorClientes::ctrAgregarCliente();
ControladorDesarrollos::ctrAgregarDesarrollo();

$permisoActual = $_SESSION['permission'] ?? 'user';
$usuarioId = (int)($_SESSION['id'] ?? 0);
$esModerador = in_array($permisoActual, ['moderator', 'senior', 'owner', 'admin'], true);
$esSenior = in_array($permisoActual, ['senior', 'owner', 'admin'], true);

$conteoSolicitudes = ['borrador' => 0, 'enviada' => 0, 'en_revision' => 0, 'aprobada' => 0, 'cancelada' => 0];
$conteo = [];
if ($esModerador) {
    $conteo = ControladorSolicitudes::ctrContarSolicitudesPorEstado();
} elseif ($usuarioId > 0) {
    $conteo = ControladorSolicitudes::ctrContarSolicitudesPorEstado($usuarioId);
}
if (is_array($conteo) && !empty($conteo)) {
    $conteoSolicitudes = array_replace($conteoSolicitudes, $conteo);
}
$estadosContabilizables = $esModerador
    ? array_keys($conteoSolicitudes)
    : ['enviada', 'en_revision', 'aprobada', 'cancelada'];
$solicitudesTotales = 0;
foreach ($estadosContabilizables as $estado) {
    $solicitudesTotales += (int)($conteoSolicitudes[$estado] ?? 0);
}
$etiquetaSolicitudes = $esModerador ? 'Solicitudes registradas' : 'Mis solicitudes ingresadas';

$conteoContratos = ['total' => 0];
if ($esModerador) {
    $conteoContratos = ControladorContratos::ctrContarContratosPorUsuario();
}

$anunciosVigentes = ControladorConsola::ctrObtenerAnunciosVigentes('dashboard');
$anunciosCallout = [];
foreach ($anunciosVigentes as $anuncioVigente) {
    $vigenciaTexto = '';
    $vigenciaIso = '';
    if (!empty($anuncioVigente['vigente_hasta'])) {
        try {
            $anuncioFecha = new \DateTimeImmutable($anuncioVigente['vigente_hasta']);
            $vigenciaTexto = $anuncioFecha->format('d/m/Y H:i');
            $vigenciaIso = $anuncioFecha->format(\DateTimeImmutable::ATOM);
        } catch (\Throwable $e) {
            $vigenciaTexto = (string)$anuncioVigente['vigente_hasta'];
            $vigenciaIso = (string)$anuncioVigente['vigente_hasta'];
        }
    }

    $anunciosCallout[] = [
        'id' => (int)($anuncioVigente['id'] ?? 0),
        'mensaje' => (string)($anuncioVigente['mensaje'] ?? ''),
        'vigencia_texto' => $vigenciaTexto,
        'vigencia_iso' => $vigenciaIso,
    ];
}

$nacionalidadesList = ControladorParametros::ctrMostrarVariables('nacionalidad');

require_once 'vistas/partials/content_header.php';
ag_render_content_header([
    'title' => 'Panel de control',
    'subtitle' => 'Resumen general de solicitudes, contratos y clientes.',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio', 'icon' => 'fas fa-home'],
        ['label' => 'Panel de control'],
    ],
]);
?>
<section class="content">
  <div class="row g-3">
    <?php foreach ($anunciosCallout as $anuncio) : ?>
      <div class="col-12">
        <div class="callout callout-warning anuncio-general" id="anuncio-general-<?php echo (int)$anuncio['id']; ?>" data-expira="<?php echo htmlspecialchars($anuncio['vigencia_iso']); ?>">
          <h5 class="mb-2"><i class="fas fa-bullhorn me-2"></i>Anuncio general</h5>
          <p class="mb-1"><?php echo nl2br(htmlspecialchars($anuncio['mensaje'])); ?></p>
          <?php if ($anuncio['vigencia_texto'] !== '') : ?>
            <p class="text-muted mb-0">Disponible hasta: <?php echo htmlspecialchars($anuncio['vigencia_texto']); ?></p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php
      $dashboardBoxes = [
        [
          'key' => 'solicitudes',
          'show' => true,
          'bgClass' => 'bg-info',
          'iconClass' => 'fas fa-paper-plane',
          'title' => 'Solicitudes',
          'description' => $etiquetaSolicitudes,
          'actions' => [
            [
              'label' => 'Ver solicitudes',
              'icon' => 'fas fa-eye',
              'trailingIcon' => 'fas fa-arrow-right',
              'url' => 'index.php?ruta=solicitudes',
            ],
            [
              'label' => 'Crear solicitud',
              'icon' => 'fas fa-plus-circle',
              'trailingIcon' => 'fas fa-paper-plane',
              'url' => 'index.php?ruta=nuevaSolicitud',
              'class' => 'small-box-action-emphasis',
            ],
          ],
        ],
        [
          'key' => 'contratos',
          'show' => $esModerador,
          'bgClass' => 'bg-success',
          'iconClass' => 'fas fa-file-signature',
          'title' => 'Contratos',
          'description' => 'Contratos registrados',
          'actions' => [
            [
              'label' => 'Ver contratos',
              'icon' => 'fas fa-eye',
              'trailingIcon' => 'fas fa-arrow-right',
              'url' => 'index.php?ruta=contratos',
            ],
            [
              'label' => 'Crear contrato',
              'icon' => 'fas fa-plus-circle',
              'trailingIcon' => 'fas fa-pen-nib',
              'url' => 'index.php?ruta=crearContrato',
              'class' => 'small-box-action-emphasis',
            ],
          ],
        ],
        [
          'key' => 'clientes',
          'show' => $esModerador,
          'bgClass' => 'bg-warning text-dark',
          'iconClass' => 'fas fa-users',
          'title' => 'Clientes',
          'description' => 'Gestión y registro de clientes',
          'actions' => [
            [
              'label' => 'Ver clientes',
              'icon' => 'fas fa-address-book',
              'trailingIcon' => 'fas fa-arrow-right',
              'url' => 'index.php?ruta=clientes',
            ],
            [
              'label' => 'Crear cliente',
              'icon' => 'fas fa-user-plus',
              'trailingIcon' => 'fas fa-plus',
              'type' => 'modal',
              'target' => '#modalNuevoCliente',
              'class' => 'small-box-action-emphasis',
            ],
          ],
        ],
        [
          'key' => 'desarrollos',
          'show' => $esSenior,
          'bgClass' => 'bg-secondary',
          'iconClass' => 'fas fa-city',
          'title' => 'Desarrollos',
          'description' => 'Administración de proyectos',
          'actions' => [
            [
              'label' => 'Ver desarrollos',
              'icon' => 'fas fa-table-list',
              'trailingIcon' => 'fas fa-arrow-right',
              'url' => 'index.php?ruta=desarrollos',
            ],
            [
              'label' => 'Crear desarrollo',
              'icon' => 'fas fa-plus-circle',
              'trailingIcon' => 'fas fa-building',
              'url' => 'index.php?ruta=desarrollos&accion=agregarDesarrollo',
              'class' => 'small-box-action-emphasis',
            ],
          ],
        ],
      ];

      $buildAttributes = static function (array $attributes): string {
          $prepared = [];
          foreach ($attributes as $name => $value) {
              if ($value === null || $value === false || $value === '') {
                  continue;
              }
              if ($value === true) {
                  $prepared[] = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
                  continue;
              }
              $prepared[] = sprintf(
                  '%s="%s"',
                  htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8'),
                  htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
              );
          }

          return implode(' ', $prepared);
      };
    ?>

    <?php foreach ($dashboardBoxes as $box) :
      if (!($box['show'] ?? true)) {
          continue;
      }

      $actions = array_filter($box['actions'] ?? [], static function ($action) {
          return !isset($action['show']) || $action['show'];
      });

      $boxClasses = trim('small-box ag-dashboard-box ' . ($box['bgClass'] ?? 'bg-primary'));
      $title = $box['title'] ?? '';
      $description = $box['description'] ?? '';
      $iconClass = $box['iconClass'] ?? '';
    ?>
      <div class="col-xl-3 col-md-4 col-sm-6 col-12">
        <div class="<?php echo htmlspecialchars($boxClasses, ENT_QUOTES, 'UTF-8'); ?>" data-ag-animate="scale-fade">
          <div class="ag-dashboard-box__content">
            <div class="inner">
              <?php if ($title !== '') : ?>
                <h3 class="ag-dashboard-box__title"><?php echo htmlspecialchars($title); ?></h3>
              <?php endif; ?>
              <?php if ($description !== '') : ?>
                <p class="ag-dashboard-box__description"><?php echo htmlspecialchars($description); ?></p>
              <?php endif; ?>
            </div>
            <?php if ($iconClass !== '') : ?>
              <div class="icon">
                <i class="<?php echo htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
              </div>
            <?php endif; ?>
          </div>
          <?php if (!empty($actions)) : ?>
            <div class="small-box-actions" role="group" aria-label="Acciones rápidas">
              <?php foreach ($actions as $action) :
                $actionType = $action['type'] ?? 'link';
                $href = ($actionType === 'modal') ? '#!' : ($action['url'] ?? '#');
                $actionAttributes = [
                    'href' => $href,
                    'class' => trim('small-box-action ' . ($action['class'] ?? '')),
                    'data-ag-dashboard-action' => $actionType,
                ];
                if ($actionType === 'modal' && !empty($action['target'])) {
                    $actionAttributes['data-ag-dashboard-target'] = $action['target'];
                    $actionAttributes['role'] = 'button';
                }
                if (!empty($action['ariaLabel'])) {
                    $actionAttributes['aria-label'] = $action['ariaLabel'];
                }
                if (!empty($action['title'])) {
                    $actionAttributes['title'] = $action['title'];
                }
                $attributeString = $buildAttributes($actionAttributes);
              ?>
                <a <?php echo $attributeString; ?>>
                  <span class="small-box-action__label">
                    <?php if (!empty($action['icon'])) : ?>
                      <i class="<?php echo htmlspecialchars($action['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($action['label'] ?? ''); ?></span>
                  </span>
                  <?php if (!empty($action['trailingIcon'])) : ?>
                    <i class="<?php echo htmlspecialchars($action['trailingIcon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                  <?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!empty($anunciosCallout)) : ?>
  <script>
    (function gestionarVigenciaAnuncio() {
      const callouts = document.querySelectorAll('.anuncio-general[data-expira]');
      if (!callouts.length) {
        return;
      }

      callouts.forEach((callout) => {
        const expira = callout.getAttribute('data-expira');
        if (!expira) {
          return;
        }

        const fechaExpira = Date.parse(expira);
        if (Number.isNaN(fechaExpira)) {
          return;
        }

        const removerCallout = () => {
          callout.style.transition = 'opacity 0.3s ease';
          callout.style.opacity = '0';
          callout.addEventListener('transitionend', () => {
            if (callout && callout.parentElement) {
              callout.parentElement.removeChild(callout);
            }
          }, { once: true });
        };

        const milisegundosRestantes = fechaExpira - Date.now();
        if (milisegundosRestantes <= 0) {
          removerCallout();
          return;
        }

        window.setTimeout(removerCallout, milisegundosRestantes);
      });
    })();
  </script>
<?php endif; ?>

<!-- Modal nuevo cliente -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="formCliente" method="post" action="index.php?ruta=inicio&accion=agregar">
        <!-- Token CSRF oculto para proteger el formulario de altas -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <div class="modal-header">
          <h5 class="modal-title">Crear nuevo cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre completo</label>
              <input type="text" class="form-control form-control-sm" name="nombre" maxlength="50" pattern="[A-Za-zñÑÁÉÍÓÚ\s]{1,50}" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nacionalidad</label>
              <select class="form-select form-select-sm" name="nacionalidad" required>
                <option value="" disabled selected>Seleccione</option>
                <?php if (!empty($nacionalidadesList)) : ?>
                  <?php foreach ($nacionalidadesList as $nac) : ?>
                    <option value="<?php echo htmlspecialchars($nac['identificador'], ENT_QUOTES); ?>">
                      <?php echo htmlspecialchars($nac['nombre']); ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Fecha de nacimiento</label>
              <input type="date" class="form-control form-control-sm" name="fecha_nacimiento" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">RFC</label>
              <input type="text" class="form-control form-control-sm" name="rfc" pattern="^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$" maxlength="13" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">CURP</label>
              <input type="text" class="form-control form-control-sm" name="curp" maxlength="18" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">INE (IDMEX)</label>
              <input type="text" class="form-control form-control-sm" name="ine" maxlength="50" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">Estado civil y régimen matrimonial</label>
              <input type="text" class="form-control form-control-sm" name="estado_civil" maxlength="50" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">Ocupación</label>
              <input type="text" class="form-control form-control-sm" name="ocupacion" maxlength="50" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="nuevoTelefonoCliente">Teléfono</label>
              <input type="tel" class="form-control form-control-sm" id="nuevoTelefonoCliente" data-intl-hidden="#nuevoTelefonoClienteHidden" required data-requirement="Selecciona la lada y captura un número de 10 dígitos como mínimo." aria-describedby="nuevoTelefonoClienteHint">
              <div class="invalid-feedback">Ingrese un número válido.</div>
              <div id="nuevoTelefonoClienteHint" class="form-text ag-field-hint">Incluye la clave lada del país. El número final se guardará con prefijo internacional.</div>
            </div>
            <input type="hidden" name="telefono" id="nuevoTelefonoClienteHidden">
            <div class="col-md-6">
              <label class="form-label">Domicilio</label>
              <input type="text" class="form-control form-control-sm" name="domicilio" maxlength="50" required oninput="this.value = this.value.toUpperCase();">
            </div>
            <div class="col-md-6">
              <label class="form-label">Correo electrónico</label>
              <input type="email" class="form-control form-control-sm" name="email" required maxlength="100">
            </div>
            <div class="col-md-6">
              <label class="form-label">Beneficiario</label>
              <input type="text" class="form-control form-control-sm" name="beneficiario" maxlength="50" required oninput="this.value = this.value.toUpperCase();">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
