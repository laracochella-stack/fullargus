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

        if ($title === '') {
            $title = 'Panel';
        }

        if (!$breadcrumbs) {
            $breadcrumbs = [
                ['label' => 'Inicio', 'url' => 'index.php?ruta=inicio'],
                ['label' => $title],
            ];
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
