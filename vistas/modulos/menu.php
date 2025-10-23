<?php
/**
 * Menú lateral de navegación.
 */
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <a href="index.php?ruta=inicio" class="brand-link text-center">
    <!-- Cambiar nombre de la marca en el menú lateral -->
    <span class="brand-text font-weight-light">Contratos Grupo Argus</span>
  </a>
  <div class="sidebar">
    <nav class="mt-2">
      <?php
      $permisoMenu = $_SESSION['permission'] ?? 'user';
      $puedeClientes = in_array($permisoMenu, ['moderator','senior','owner','admin'], true);
      $puedeContratos = $puedeClientes;
      $puedeDesarrollos = in_array($permisoMenu, ['senior','owner','admin'], true);
      $puedeParametros = in_array($permisoMenu, ['senior','owner','admin'], true);
      $puedeUsuarios = in_array($permisoMenu, ['owner','admin'], true);
      $puedeConsola = ($permisoMenu === 'admin');
      ?>
      <?php
      $rutaActual = isset($_GET['ruta']) ? trim((string)$_GET['ruta']) : 'inicio';
      if ($rutaActual === '') {
          $rutaActual = 'inicio';
      }

      $menuItems = [
          [
              'label' => 'Inicio',
              'icon' => 'fas fa-gauge-high',
              'route' => 'inicio',
              'visible' => true,
          ],
          [
              'label' => 'Solicitudes',
              'icon' => 'fas fa-inbox',
              'route' => 'solicitudes',
              'visible' => true,
              'children' => [
                  [
                      'label' => 'Panel de solicitudes',
                      'icon' => 'fas fa-clipboard-list',
                      'route' => 'solicitudes',
                      'visible' => true,
                  ],
                  [
                      'label' => 'Nueva solicitud',
                      'icon' => 'fas fa-paper-plane',
                      'route' => 'nuevaSolicitud',
                      'visible' => true,
                  ],
              ],
          ],
          [
              'label' => 'Contratos',
              'icon' => 'fas fa-file-signature',
              'route' => 'contratos',
              'visible' => $puedeContratos,
              'children' => [
                  [
                      'label' => 'Listado de contratos',
                      'icon' => 'fas fa-file-contract',
                      'route' => 'contratos',
                      'visible' => $puedeContratos,
                  ],
                  [
                      'label' => 'Generar contrato',
                      'icon' => 'fas fa-pen-to-square',
                      'route' => 'crearContrato',
                      'visible' => $puedeContratos,
                  ],
              ],
          ],
          [
              'label' => 'Registros',
              'icon' => 'fas fa-people-group',
              'route' => null,
              'visible' => ($puedeClientes || $puedeDesarrollos),
              'children' => [
                  [
                      'label' => 'Clientes',
                      'icon' => 'fas fa-users',
                      'route' => 'clientes',
                      'visible' => $puedeClientes,
                  ],
                  [
                      'label' => 'Desarrollos',
                      'icon' => 'fas fa-city',
                      'route' => 'desarrollos',
                      'visible' => $puedeDesarrollos,
                  ],
              ],
          ],
          [
              'label' => 'Configuración',
              'icon' => 'fas fa-sliders-h',
              'route' => null,
              'visible' => ($puedeParametros || $puedeUsuarios || $puedeConsola),
              'children' => [
                  [
                      'label' => 'Parámetros del sistema',
                      'icon' => 'fas fa-sliders-h',
                      'route' => 'parametros',
                      'visible' => $puedeParametros,
                  ],
                  [
                      'label' => 'Usuarios y roles',
                      'icon' => 'fas fa-user-shield',
                      'route' => 'roles',
                      'visible' => $puedeUsuarios,
                  ],
                  [
                      'label' => 'Consola',
                      'icon' => 'fas fa-terminal',
                      'route' => 'consola',
                      'visible' => $puedeConsola,
                  ],
              ],
          ],
      ];

      $filtrarVisibles = static function (array $items) use (&$filtrarVisibles): array {
          $resultado = [];
          foreach ($items as $item) {
              $visible = $item['visible'] ?? true;
              if (!$visible) {
                  continue;
              }

              if (isset($item['children']) && is_array($item['children'])) {
                  $item['children'] = $filtrarVisibles($item['children']);
                  $sinRuta = !isset($item['route']) || $item['route'] === null || $item['route'] === '';
                  if (empty($item['children']) && $sinRuta) {
                      continue;
                  }
              }

              $resultado[] = $item;
          }

          return $resultado;
      };

      $menuItems = $filtrarVisibles($menuItems);

      $renderMenu = static function (array $items, bool $esSubmenu = false) use (&$renderMenu, $rutaActual): void {
          foreach ($items as $item) {
              $children = isset($item['children']) && is_array($item['children']) ? $item['children'] : [];
              $tieneHijos = !empty($children);
              $esActivo = !empty($item['route']) && $rutaActual === $item['route'];
              $hijoActivo = false;

              if ($tieneHijos) {
                  foreach ($children as $child) {
                      if (!empty($child['route']) && $rutaActual === $child['route']) {
                          $hijoActivo = true;
                          break;
                      }
                  }
              }

              $liClases = ['nav-item'];
              if ($tieneHijos) {
                  $liClases[] = 'has-treeview';
                  if ($hijoActivo || $esActivo) {
                      $liClases[] = 'menu-open';
                      $liClases[] = 'menu-is-opening';
                  }
              }

              $linkClases = ['nav-link'];
              if ($esSubmenu) {
                  $linkClases[] = 'nav-sub-link';
              }
              if ($esActivo || $hijoActivo) {
                  $linkClases[] = 'active';
              }

              $href = '#';
              if (!empty($item['route'])) {
                  $href = 'index.php?ruta=' . rawurlencode((string)$item['route']);
              }

              $icono = $item['icon'] ?? 'fas fa-circle';
              $etiqueta = $item['label'] ?? '';

              $liClassAttr = htmlspecialchars(implode(' ', $liClases), ENT_QUOTES, 'UTF-8');
              $linkClassAttr = htmlspecialchars(implode(' ', $linkClases), ENT_QUOTES, 'UTF-8');
              $hrefAttr = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
              $iconAttr = htmlspecialchars($icono, ENT_QUOTES, 'UTF-8');
              $labelAttr = htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8');

              $ariaCurrent = ($esActivo || $hijoActivo) ? ' aria-current="page"' : '';
              $ariaExpanded = $tieneHijos ? sprintf(' aria-expanded="%s"', ($hijoActivo || $esActivo) ? 'true' : 'false') : '';

              echo '<li class="' . $liClassAttr . '">';
              echo '<a href="' . $hrefAttr . '" class="' . $linkClassAttr . '"' . $ariaCurrent . $ariaExpanded . '>';
              echo '<i class="nav-icon ' . $iconAttr . '"></i>';
              echo '<p>' . $labelAttr;
              if ($tieneHijos) {
                  echo '<i class="fas fa-angle-left right"></i>';
              }
              echo '</p>';
              echo '</a>';

              if ($tieneHijos) {
                  $subMenuClasses = ['nav', 'nav-treeview'];
                  $subMenuClassAttr = htmlspecialchars(implode(' ', $subMenuClasses), ENT_QUOTES, 'UTF-8');
                  $estaAbierto = ($hijoActivo || $esActivo);
                  $subMenuVisible = $estaAbierto ? 'true' : 'false';
                  $subMenuVisibleAttr = htmlspecialchars($subMenuVisible, ENT_QUOTES, 'UTF-8');
                  $ariaHiddenValue = $estaAbierto ? 'false' : 'true';
                  $ariaHidden = ' aria-hidden="' . htmlspecialchars($ariaHiddenValue, ENT_QUOTES, 'UTF-8') . '"';

                  echo '<ul class="' . $subMenuClassAttr . '" data-menu-visible="' . $subMenuVisibleAttr . '"' . $ariaHidden . '>';
                  $renderMenu($children, true);
                  echo '</ul>';
              }

              echo '</li>';
          }
      };
      ?>
      <ul class="nav nav-pills nav-sidebar flex-column ag-nav-compact" data-widget="treeview" data-accordion="false" role="menu">
        <?php $renderMenu($menuItems); ?>
      </ul>
    </nav>
  </div>
</aside>
