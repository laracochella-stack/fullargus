<?php
if (!function_exists('ag_render_content_header')) {
    /**
     * Renderiza un encabezado consistente con título, migas de pan y acciones.
     *
     * @param array{
     *   title:string,
     *   subtitle?:string,
     *   breadcrumbs?:array<int, array{label:string,url?:string,icon?:string}>,
     *   actions?:array<int, array{label:string,url:string,class?:string,icon?:string,attributes?:array<string,string>}>
     * } $config
     */
    function ag_render_content_header(array $config): void
    {
        $title = isset($config['title']) ? trim((string)$config['title']) : '';
        $subtitle = isset($config['subtitle']) ? trim((string)$config['subtitle']) : '';
        $breadcrumbs = $config['breadcrumbs'] ?? [];
        $actions = $config['actions'] ?? [];
        $currentApp = isset($config['app']) ? trim((string)$config['app']) : '';
        $currentRoute = isset($config['route']) ? trim((string)$config['route']) : null;
        $appNavigation = $config['appNavigation'] ?? null;
        $showBreadcrumbs = array_key_exists('show_breadcrumbs', $config) ? (bool)$config['show_breadcrumbs'] : true;
        $showNavigation = array_key_exists('show_navigation', $config) ? (bool)$config['show_navigation'] : true;

        if ($title === '') {
            $title = 'Panel';
        }

        if (!$breadcrumbs && $showBreadcrumbs) {
            $breadcrumbs = [
                ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio'],
                ['label' => $title],
            ];
        }

        if ($appNavigation === null && $currentApp !== '') {
            $sessionData = $_SESSION ?? [];
            if (!is_array($sessionData)) {
                $sessionData = [];
            }
            try {
                $appNavigation = \App\Support\AppNavigation::getNavigationMenu($currentApp, $sessionData, $currentRoute);
            } catch (\Throwable $exception) {
                $appNavigation = [];
            }
        }

        if (!is_array($appNavigation)) {
            $appNavigation = [];
        }

        echo '<section class="content-header">';
        echo '<div class="container-fluid">';
        echo '<div class="row align-items-center">';
        echo '<div class="col-sm-7">';
        echo '<h1 class="m-0 ag-responsive-title">' . htmlspecialchars($title, ENT_QUOTES) . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="ag-responsive-subtitle mb-1">' . htmlspecialchars($subtitle, ENT_QUOTES) . '</p>';
        }
        if ($showBreadcrumbs && !empty($breadcrumbs)) {
            echo '<nav aria-label="breadcrumb" class="small">';
            echo '<ol class="breadcrumb m-0">';
            $lastIndex = count($breadcrumbs) - 1;
            foreach ($breadcrumbs as $index => $crumb) {
                $label = htmlspecialchars($crumb['label'] ?? '', ENT_QUOTES);
                $url = $crumb['url'] ?? null;
                $icon = trim((string)($crumb['icon'] ?? ''));
                $iconHtml = $icon !== '' ? '<i class="' . htmlspecialchars($icon, ENT_QUOTES) . ' me-1"></i>' : '';
                $isActive = $index === $lastIndex || empty($url);
                if ($isActive) {
                    echo '<li class="breadcrumb-item active" aria-current="page">' . $iconHtml . $label . '</li>';
                } else {
                    $href = htmlspecialchars($url, ENT_QUOTES);
                    echo '<li class="breadcrumb-item"><a href="' . $href . '">' . $iconHtml . $label . '</a></li>';
                }
            }
            echo '</ol>';
            echo '</nav>';
        }
        if ($showNavigation && !empty($appNavigation)) {
            echo '<div class="ag-app-navigation mt-3" role="navigation" aria-label="Aplicaciones relacionadas">';
            echo '<ul class="nav nav-pills ag-app-navigation__list flex-wrap">';
            foreach ($appNavigation as $item) {
                $hasChildren = !empty($item['children']);
                $isActive = !empty($item['active']);
                $itemLabel = htmlspecialchars((string)($item['label'] ?? ''), ENT_QUOTES);
                $itemUrl = htmlspecialchars((string)($item['url'] ?? '#'), ENT_QUOTES);
                $itemIcon = trim((string)($item['icon'] ?? ''));
                $iconHtml = $itemIcon !== '' ? '<i class="' . htmlspecialchars($itemIcon, ENT_QUOTES) . ' me-1"></i>' : '';
                $liClasses = ['nav-item'];
                $linkClasses = ['nav-link'];
                if ($hasChildren) {
                    $liClasses[] = 'dropdown';
                    $linkClasses[] = 'dropdown-toggle';
                }
                if ($isActive) {
                    $linkClasses[] = 'active';
                }
                $liClassAttr = htmlspecialchars(implode(' ', $liClasses), ENT_QUOTES);
                $linkClassAttr = htmlspecialchars(implode(' ', $linkClasses), ENT_QUOTES);
                $ariaCurrent = $isActive ? ' aria-current="page"' : '';
                $dataToggle = $hasChildren ? ' data-bs-toggle="dropdown" role="button" aria-expanded="false"' : '';
                echo '<li class="' . $liClassAttr . '">';
                echo '<a class="' . $linkClassAttr . '" href="' . $itemUrl . '"' . $ariaCurrent . $dataToggle . '>' . $iconHtml . $itemLabel . '</a>';
                if ($hasChildren) {
                    echo '<ul class="dropdown-menu">';
                    foreach ($item['children'] as $child) {
                        $childType = isset($child['type']) ? strtolower((string)$child['type']) : 'link';
                        if ($childType === 'divider') {
                            echo '<li><hr class="dropdown-divider"></li>';
                            continue;
                        }

                        $childLabel = htmlspecialchars((string)($child['label'] ?? ''), ENT_QUOTES);
                        if ($childLabel === '') {
                            continue;
                        }

                        $childUrl = htmlspecialchars((string)($child['url'] ?? '#'), ENT_QUOTES);
                        $childIcon = trim((string)($child['icon'] ?? ''));
                        $childIconHtml = $childIcon !== '' ? '<i class="' . htmlspecialchars($childIcon, ENT_QUOTES) . ' me-2"></i>' : '';
                        $childActive = !empty($child['active']);
                        $childClass = 'dropdown-item' . ($childActive ? ' active' : '');
                        $targetAttr = '';
                        if (!empty($child['target'])) {
                            $target = htmlspecialchars((string)$child['target'], ENT_QUOTES);
                            $targetAttr = ' target="' . $target . '"';
                        }
                        echo '<li><a class="' . htmlspecialchars($childClass, ENT_QUOTES) . '" href="' . $childUrl . '"' . $targetAttr . '>' . $childIconHtml . $childLabel . '</a></li>';
                    }
                    echo '</ul>';
                }
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        echo '</div>';
        echo '<div class="col-sm-5 text-sm-end mt-3 mt-sm-0">';
        if ($actions) {
            static $dropdownIndex = 0;
            echo '<div class="d-inline-flex flex-wrap gap-2 justify-content-end">';
            foreach ($actions as $action) {
                if (empty($action)) {
                    continue;
                }
                $type = isset($action['type']) ? strtolower((string)$action['type']) : 'link';
                $label = htmlspecialchars((string)($action['label'] ?? ''), ENT_QUOTES);
                $icon = trim((string)($action['icon'] ?? ''));
                $iconHtml = $icon !== '' ? '<i class="' . htmlspecialchars($icon, ENT_QUOTES) . ' me-1"></i>' : '';

                if ($type === 'dropdown') {
                    $items = [];
                    if (!empty($action['items']) && is_array($action['items'])) {
                        $items = $action['items'];
                    }
                    if (!$items) {
                        continue;
                    }
                    $dropdownIndex++;
                    $toggleId = 'agHeaderActionDropdown' . $dropdownIndex;
                    $buttonClass = 'btn btn-sm ' . htmlspecialchars((string)($action['class'] ?? 'btn-outline-secondary'), ENT_QUOTES);
                    echo '<div class="btn-group">';
                    echo '<button type="button" class="' . $buttonClass . ' dropdown-toggle" id="' . htmlspecialchars($toggleId, ENT_QUOTES) . '" data-bs-toggle="dropdown" aria-expanded="false">' . $iconHtml . $label . '</button>';
                    echo '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="' . htmlspecialchars($toggleId, ENT_QUOTES) . '">';
                    foreach ($items as $item) {
                        if (isset($item['type']) && strtolower((string)$item['type']) === 'divider') {
                            echo '<li><hr class="dropdown-divider"></li>';
                            continue;
                        }
                        $itemLabel = htmlspecialchars((string)($item['label'] ?? ''), ENT_QUOTES);
                        if ($itemLabel === '') {
                            continue;
                        }
                        $itemUrl = isset($item['url']) ? (string)$item['url'] : '#';
                        $itemUrl = htmlspecialchars($itemUrl, ENT_QUOTES);
                        $itemIcon = trim((string)($item['icon'] ?? ''));
                        $itemIconHtml = $itemIcon !== '' ? '<i class="' . htmlspecialchars($itemIcon, ENT_QUOTES) . ' me-2"></i>' : '';
                        $itemClass = 'dropdown-item';
                        if (!empty($item['class'])) {
                            $itemClass .= ' ' . trim((string)$item['class']);
                        }
                        $itemAttrs = '';
                        if (!empty($item['attributes']) && is_array($item['attributes'])) {
                            foreach ($item['attributes'] as $attrName => $attrValue) {
                                $itemAttrs .= ' ' . htmlspecialchars((string)$attrName, ENT_QUOTES) . '="' . htmlspecialchars((string)$attrValue, ENT_QUOTES) . '"';
                            }
                        }
                        echo '<li><a class="' . htmlspecialchars($itemClass, ENT_QUOTES) . '" href="' . $itemUrl . '"' . $itemAttrs . '>' . $itemIconHtml . $itemLabel . '</a></li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                    continue;
                }

                $url = htmlspecialchars((string)($action['url'] ?? '#'), ENT_QUOTES);
                $class = 'btn btn-sm ' . htmlspecialchars((string)($action['class'] ?? 'btn-primary'), ENT_QUOTES);
                $attrs = '';
                if (!empty($action['attributes']) && is_array($action['attributes'])) {
                    foreach ($action['attributes'] as $attrName => $attrValue) {
                        $attrs .= ' ' . htmlspecialchars((string)$attrName, ENT_QUOTES) . '="' . htmlspecialchars((string)$attrValue, ENT_QUOTES) . '"';
                    }
                }
                echo '<a class="' . $class . '" href="' . $url . '"' . $attrs . '>' . $iconHtml . $label . '</a>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</section>';
    }
}
