<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helper to centralize application navigation metadata and visibility.
 */
final class AppNavigation
{
    public const APP_INICIO = 'inicio';
    public const APP_SOLICITUDES = 'solicitudes';
    public const APP_CONTRATOS = 'contratos';
    public const APP_CLIENTES = 'clientes';
    public const APP_DESARROLLOS = 'desarrollos';
    public const APP_CONFIGURACION = 'configuracion';

    /**
     * Returns application modules filtered according to the current session.
     *
     * Each module definition contains:
     * - key: string identifier (array key)
     * - label: display name
     * - description: short text for the card/grid
     * - icon: FontAwesome classes
     * - color: contextual color class for icons/badges
     * - route: default route for the module (string|null)
     * - visible: whether the module can be displayed
     * - children: nested quick links/actions (each child may include `visible`)
     * - nav_peers: preferred ordering of modules in the contextual header nav
     * - keywords: optional search tokens for the app drawer/grid
     * - include_in_grid: whether this module should appear in the application grid
     *
     * @param array<string,mixed> $session Session data for permission checks
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getModules(array $session = []): array
    {
        $permission = self::normalizePermission($session['permission'] ?? null);

        $canClientes = self::canClientes($permission);
        $canContratos = $canClientes;
        $canDesarrollos = self::canDesarrollos($permission);
        $canParametros = self::canParametros($permission);
        $canUsuarios = self::canUsuarios($permission);
        $canConsola = self::canConsola($permission);

        $modules = [
            self::APP_INICIO => [
                'key' => self::APP_INICIO,
                'label' => 'Inicio',
                'description' => '',
                'icon' => 'fas fa-cubes',
                'color' => 'bg-primary',
                'route' => 'inicio',
                'visible' => true,
                'children' => [],
                'nav_peers' => [
                    self::APP_SOLICITUDES,
                    self::APP_CLIENTES,
                    self::APP_CONTRATOS,
                    self::APP_CONFIGURACION,
                    self::APP_DESARROLLOS,
                ],
                'keywords' => ['dashboard', 'home', 'apps'],
                'include_in_grid' => false,
            ],
            self::APP_SOLICITUDES => [
                'key' => self::APP_SOLICITUDES,
                'label' => 'Solicitudes',
                'description' => '',
                'icon' => 'fas fa-inbox',
                'color' => 'bg-primary',
                'route' => 'solicitudes',
                'visible' => true,
                'children' => [
                    [
                        'label' => 'Panel de solicitudes',
                        'route' => 'solicitudes',
                        'icon' => 'fas fa-clipboard-list',
                        'visible' => true,
                    ],
                    [
                        'label' => 'Nueva solicitud',
                        'route' => 'nuevaSolicitud',
                        'icon' => 'fas fa-paper-plane',
                        'visible' => true,
                    ],
                    [
                        'label' => 'Configurar plantillas',
                        'route' => 'solicitudesConfiguracion',
                        'icon' => 'fas fa-sliders-h',
                        'visible' => $canParametros,
                        'fragment' => 'parametros-plantillas-solicitud',
                    ],
                ],
                'config_menu' => [
                    [
                        'label' => 'Plantillas de solicitud',
                        'route' => 'solicitudesConfiguracion',
                        'icon' => 'fas fa-file-word',
                        'visible' => $canParametros,
                        'fragment' => 'parametros-plantillas-solicitud',
                    ],
                ],
                'nav_peers' => [
                    self::APP_SOLICITUDES,
                    self::APP_CLIENTES,
                    self::APP_CONTRATOS,
                    self::APP_CONFIGURACION,
                ],
                'keywords' => ['folios', 'seguimiento', 'captura'],
                'include_in_grid' => true,
            ],
            self::APP_CONTRATOS => [
                'key' => self::APP_CONTRATOS,
                'label' => 'Contratos',
                'description' => '',
                'icon' => 'fas fa-file-signature',
                'color' => 'bg-success',
                'route' => $canContratos ? 'contratos' : null,
                'visible' => $canContratos,
                'children' => [
                    [
                        'label' => 'Listado de contratos',
                        'route' => 'contratos',
                        'icon' => 'fas fa-file-contract',
                        'visible' => $canContratos,
                    ],
                    [
                        'label' => 'Generar contrato',
                        'route' => 'crearContrato',
                        'icon' => 'fas fa-pen-to-square',
                        'visible' => $canContratos,
                    ],
                    [
                        'label' => 'Configurar tipos',
                        'route' => 'contratosConfiguracion',
                        'icon' => 'fas fa-list-check',
                        'visible' => $canParametros,
                        'fragment' => 'parametros-tipos-contrato',
                    ],
                    [
                        'label' => 'Plantillas de contrato',
                        'route' => 'contratosConfiguracion',
                        'icon' => 'far fa-copy',
                        'visible' => $canParametros,
                        'fragment' => 'parametros-plantillas-contrato',
                    ],
                ],
                'config_menu' => [
                    [
                        'label' => 'Tipos de contrato',
                        'route' => 'contratosConfiguracion',
                        'icon' => 'fas fa-list-check',
                        'visible' => $canParametros,
                        'fragment' => 'parametros-tipos-contrato',
                    ],
                    [
                        'label' => 'Plantillas de contrato',
                        'route' => 'contratosConfiguracion',
                        'icon' => 'far fa-copy',
                        'visible' => $canParametros,
                        'fragment' => 'parametros-plantillas-contrato',
                    ],
                ],
                'nav_peers' => [
                    self::APP_CONTRATOS,
                    self::APP_CLIENTES,
                    self::APP_SOLICITUDES,
                    self::APP_CONFIGURACION,
                ],
                'keywords' => ['documentos', 'firmas', 'acuerdos'],
                'include_in_grid' => $canContratos,
            ],
            self::APP_CLIENTES => [
                'key' => self::APP_CLIENTES,
                'label' => 'Clientes',
                'description' => '',
                'icon' => 'fas fa-users',
                'color' => 'bg-warning text-dark',
                'route' => $canClientes ? 'clientes' : null,
                'visible' => $canClientes,
                'children' => [
                    [
                        'label' => 'Listado de clientes',
                        'route' => 'clientes',
                        'icon' => 'fas fa-address-book',
                        'visible' => $canClientes,
                    ],
                    [
                        'label' => 'Configurar nacionalidades',
                        'route' => 'clientesConfiguracion',
                        'icon' => 'fas fa-flag',
                        'visible' => $canParametros,
                        'fragment' => 'parametros-nacionalidades',
                    ],
                ],
                'config_menu' => [
                    [
                        'label' => 'Nacionalidades',
                        'route' => 'clientesConfiguracion',
                        'icon' => 'fas fa-flag',
                        'visible' => $canParametros,
                        'fragment' => 'parametros-nacionalidades',
                    ],
                ],
                'nav_peers' => [
                    self::APP_CLIENTES,
                    self::APP_SOLICITUDES,
                    self::APP_CONTRATOS,
                    self::APP_CONFIGURACION,
                ],
                'keywords' => ['personas', 'prospectos', 'cartera'],
                'include_in_grid' => $canClientes,
            ],
            self::APP_DESARROLLOS => [
                'key' => self::APP_DESARROLLOS,
                'label' => 'Desarrollos',
                'description' => '',
                'icon' => 'fas fa-city',
                'color' => 'bg-info',
                'route' => $canDesarrollos ? 'desarrollos' : null,
                'visible' => $canDesarrollos,
                'children' => [
                    [
                        'label' => 'Listado de desarrollos',
                        'route' => 'desarrollos',
                        'icon' => 'fas fa-city',
                        'visible' => $canDesarrollos,
                    ],
                ],
                'nav_peers' => [
                    self::APP_DESARROLLOS,
                    self::APP_CLIENTES,
                    self::APP_CONTRATOS,
                    self::APP_CONFIGURACION,
                ],
                'keywords' => ['inmuebles', 'proyectos'],
                'include_in_grid' => $canDesarrollos,
            ],
            self::APP_CONFIGURACION => [
                'key' => self::APP_CONFIGURACION,
                'label' => 'Configuración',
                'description' => '',
                'icon' => 'fas fa-sliders-h',
                'color' => 'bg-dark',
                'route' => $canParametros ? 'parametros' : ($canUsuarios ? 'roles' : ($canConsola ? 'consola' : null)),
                'visible' => $canParametros || $canUsuarios || $canConsola,
                'children' => [
                    [
                        'label' => 'Clientes · Nacionalidades',
                        'route' => 'clientesConfiguracion',
                        'icon' => 'fas fa-flag',
                        'visible' => $canParametros,
                    ],
                    [
                        'label' => 'Contratos · Tipos y plantillas',
                        'route' => 'contratosConfiguracion',
                        'icon' => 'fas fa-file-signature',
                        'visible' => $canParametros,
                    ],
                    [
                        'label' => 'Solicitudes · Plantillas',
                        'route' => 'solicitudesConfiguracion',
                        'icon' => 'fas fa-inbox',
                        'visible' => $canParametros,
                    ],
                    [
                        'type' => 'divider',
                        'visible' => $canParametros && ($canUsuarios || $canConsola),
                    ],
                    [
                        'label' => 'Parámetros del sistema',
                        'route' => 'parametros',
                        'icon' => 'fas fa-sliders-h',
                        'visible' => $canParametros,
                    ],
                    [
                        'label' => 'Usuarios y roles',
                        'route' => 'roles',
                        'icon' => 'fas fa-user-shield',
                        'visible' => $canUsuarios,
                    ],
                    [
                        'label' => 'Consola',
                        'route' => 'consola',
                        'icon' => 'fas fa-terminal',
                        'visible' => $canConsola,
                    ],
                ],
                'nav_peers' => [
                    self::APP_CONFIGURACION,
                    self::APP_SOLICITUDES,
                    self::APP_CLIENTES,
                    self::APP_CONTRATOS,
                ],
                'keywords' => ['ajustes', 'roles', 'parámetros', 'sistema'],
                'include_in_grid' => $canParametros || $canUsuarios || $canConsola,
            ],
        ];

        // Filter visibility and nested children.
        foreach ($modules as $key => &$module) {
            $module['key'] = $key;
            if (empty($module['visible'])) {
                unset($modules[$key]);
                continue;
            }

            $children = $module['children'] ?? [];
            if (!is_array($children)) {
                $children = [];
            }
            $module['children'] = [];
            foreach ($children as $child) {
                if (isset($child['visible']) && !$child['visible']) {
                    continue;
                }

                $type = isset($child['type']) ? strtolower((string)$child['type']) : 'link';
                if ($type === 'divider') {
                    $module['children'][] = ['type' => 'divider'];
                    continue;
                }

                $normalized = [
                    'type' => $type,
                    'label' => (string)($child['label'] ?? ''),
                    'route' => isset($child['route']) && $child['route'] !== ''
                        ? (string)$child['route']
                        : null,
                    'icon' => (string)($child['icon'] ?? ''),
                ];

                if (isset($child['url']) && is_string($child['url']) && trim($child['url']) !== '') {
                    $normalized['url'] = trim((string)$child['url']);
                }

                if (isset($child['fragment']) && trim((string)$child['fragment']) !== '') {
                    $normalized['fragment'] = trim((string)$child['fragment']);
                }

                if (isset($child['target']) && trim((string)$child['target']) !== '') {
                    $normalized['target'] = trim((string)$child['target']);
                }

                $module['children'][] = $normalized;
            }

            $configMenu = $module['config_menu'] ?? [];
            if (!is_array($configMenu)) {
                $configMenu = [];
            }

            $normalizedConfig = [];
            foreach ($configMenu as $entry) {
                if (isset($entry['visible']) && !$entry['visible']) {
                    continue;
                }

                $label = isset($entry['label']) ? trim((string)$entry['label']) : '';
                if ($label === '') {
                    continue;
                }

                $normalizedEntry = [
                    'label' => $label,
                    'route' => isset($entry['route']) && $entry['route'] !== ''
                        ? (string)$entry['route']
                        : null,
                    'icon' => (string)($entry['icon'] ?? ''),
                ];

                if (isset($entry['url']) && is_string($entry['url']) && trim($entry['url']) !== '') {
                    $normalizedEntry['url'] = trim($entry['url']);
                }

                if (isset($entry['fragment']) && trim((string)$entry['fragment']) !== '') {
                    $normalizedEntry['fragment'] = trim((string)$entry['fragment']);
                }

                if (isset($entry['target']) && trim((string)$entry['target']) !== '') {
                    $normalizedEntry['target'] = trim((string)$entry['target']);
                }

                $normalizedConfig[] = $normalizedEntry;
            }

            $module['config_menu'] = $normalizedConfig;
        }
        unset($module);

        return $modules;
    }

    /**
     * Returns configuration shortcuts for a module to be rendered as contextual actions.
     *
     * @param string $moduleKey   Identifier of the module to fetch.
     * @param array<string,mixed> $session Session data for permission checks.
     * @param string|null $currentRoute Optional current route to mark as active.
     *
     * @return array<int, array<string, mixed>> Normalized configuration entries.
     */
    public static function getModuleConfigurationMenu(string $moduleKey, array $session = [], ?string $currentRoute = null): array
    {
        $modules = self::getModules($session);
        if (!isset($modules[$moduleKey])) {
            return [];
        }

        $configMenu = $modules[$moduleKey]['config_menu'] ?? [];
        if (!is_array($configMenu) || $configMenu === []) {
            return [];
        }

        $items = [];
        foreach ($configMenu as $entry) {
            $route = $entry['route'] ?? null;
            $url = $entry['url'] ?? self::buildUrl($route);

            $fragment = isset($entry['fragment']) ? trim((string)$entry['fragment']) : '';
            if ($fragment !== '') {
                $url .= '#' . rawurlencode($fragment);
            }

            $items[] = [
                'label' => $entry['label'],
                'url' => $url,
                'icon' => $entry['icon'] ?? '',
                'active' => $currentRoute !== null && $route !== null && $route === $currentRoute,
                'target' => $entry['target'] ?? null,
            ];
        }

        return $items;
    }

    /**
     * Returns navigation items for the contextual header.
     *
     * @param string $currentApp   Module identifier to highlight.
     * @param array<string,mixed> $session Session data for permission checks.
     * @param string|null $currentRoute Optional current route to highlight child entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getNavigationMenu(string $currentApp, array $session = [], ?string $currentRoute = null): array
    {
        $modules = self::getModules($session);
        if (!isset($modules[$currentApp])) {
            // If the requested app is not visible fall back to the first module.
            $currentApp = array_key_first($modules) ?? self::APP_SOLICITUDES;
        }

        $preferredOrder = $modules[$currentApp]['nav_peers'] ?? [];
        if (!$preferredOrder) {
            $preferredOrder = [
                self::APP_SOLICITUDES,
                self::APP_CLIENTES,
                self::APP_CONTRATOS,
                self::APP_CONFIGURACION,
                self::APP_DESARROLLOS,
            ];
        }

        $order = array_unique([
            $currentApp,
            ...$preferredOrder,
        ]);

        $items = [];
        foreach ($order as $moduleKey) {
            if (!isset($modules[$moduleKey])) {
                continue;
            }
            $module = $modules[$moduleKey];
            $route = $module['route'] ?? null;
            $url = self::buildUrl($route);
            $children = [];
            foreach ($module['children'] as $child) {
                $type = $child['type'] ?? 'link';
                if ($type === 'divider') {
                    $children[] = ['type' => 'divider'];
                    continue;
                }

                $childRoute = $child['route'] ?? null;
                $childUrl = isset($child['url']) ? (string)$child['url'] : self::buildUrl($childRoute);
                $fragment = isset($child['fragment']) ? trim((string)$child['fragment']) : '';
                if ($fragment !== '') {
                    $childUrl .= '#' . rawurlencode($fragment);
                }

                $children[] = [
                    'type' => 'link',
                    'label' => $child['label'],
                    'url' => $childUrl,
                    'icon' => $child['icon'] ?? '',
                    'active' => $currentRoute !== null && $childRoute !== null && $childRoute === $currentRoute,
                    'target' => $child['target'] ?? null,
                ];
            }

            $items[] = [
                'key' => $moduleKey,
                'label' => $module['label'],
                'icon' => $module['icon'],
                'url' => $url,
                'active' => $moduleKey === $currentApp,
                'children' => $children,
            ];
        }

        return $items;
    }

    /**
     * Returns application cards metadata for grid/drawer rendering.
     *
     * @param array<string,mixed> $session
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAppCards(array $session = []): array
    {
        $modules = self::getModules($session);
        $cards = [];
        foreach ($modules as $key => $module) {
            if (!($module['include_in_grid'] ?? false)) {
                continue;
            }
            $cards[] = $module;
        }

        return $cards;
    }

    /**
     * Normalizes the permission string from session data.
     */
    private static function normalizePermission($permission): string
    {
        if (!is_string($permission)) {
            return 'user';
        }

        $permission = strtolower(trim($permission));
        return $permission !== '' ? $permission : 'user';
    }

    private static function canClientes(string $permission): bool
    {
        return in_array($permission, ['moderator', 'senior', 'owner', 'admin'], true);
    }

    private static function canDesarrollos(string $permission): bool
    {
        return in_array($permission, ['senior', 'owner', 'admin'], true);
    }

    private static function canParametros(string $permission): bool
    {
        return in_array($permission, ['senior', 'owner', 'admin'], true);
    }

    private static function canUsuarios(string $permission): bool
    {
        return in_array($permission, ['owner', 'admin'], true);
    }

    private static function canConsola(string $permission): bool
    {
        return $permission === 'admin';
    }

    private static function buildUrl(?string $route): string
    {
        if ($route === null || $route === '') {
            return '#';
        }

        return 'index.php?ruta=' . rawurlencode($route);
    }
}
