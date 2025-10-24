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

        if ($title === '') {
            $title = 'Panel';
        }

        if (!$breadcrumbs) {
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
        if (!empty($appNavigation)) {
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
                        $childLabel = htmlspecialchars((string)($child['label'] ?? ''), ENT_QUOTES);
                        if ($childLabel === '') {
                            continue;
                        }
                        $childUrl = htmlspecialchars((string)($child['url'] ?? '#'), ENT_QUOTES);
                        $childIcon = trim((string)($child['icon'] ?? ''));
                        $childIconHtml = $childIcon !== '' ? '<i class="' . htmlspecialchars($childIcon, ENT_QUOTES) . ' me-2"></i>' : '';
                        $childActive = !empty($child['active']);
                        $childClass = 'dropdown-item' . ($childActive ? ' active' : '');
                        echo '<li><a class="' . htmlspecialchars($childClass, ENT_QUOTES) . '" href="' . $childUrl . '">' . $childIconHtml . $childLabel . '</a></li>';
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
            echo '<div class="btn-group" role="group" aria-label="Acciones del módulo">';
            foreach ($actions as $action) {
                $label = htmlspecialchars($action['label'] ?? '', ENT_QUOTES);
                $url = htmlspecialchars($action['url'] ?? '#', ENT_QUOTES);
                $class = 'btn btn-sm ' . htmlspecialchars($action['class'] ?? 'btn-primary', ENT_QUOTES);
                $icon = trim((string)($action['icon'] ?? ''));
                $attrs = '';
                if (!empty($action['attributes']) && is_array($action['attributes'])) {
                    foreach ($action['attributes'] as $attrName => $attrValue) {
                        $attrs .= ' ' . htmlspecialchars($attrName, ENT_QUOTES) . '="' . htmlspecialchars($attrValue, ENT_QUOTES) . '"';
                    }
                }
                $iconHtml = $icon !== '' ? '<i class="' . htmlspecialchars($icon, ENT_QUOTES) . ' me-1"></i>' : '';
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
