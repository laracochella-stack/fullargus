<?php
if (!function_exists('ag_render_app_card')) {
    /**
     * Render an application card used both in the home grid and app drawer.
     *
     * @param array<string, mixed> $app    Module definition from AppNavigation::getAppCards().
     * @param array<string, mixed> $config Optional configuration (wrapper_class, size, show_actions).
     */
    function ag_render_app_card(array $app, array $config = []): void
    {
        $wrapperClass = $config['wrapper_class'] ?? 'col-12 col-sm-6 col-lg-4 col-xl-3';
        $showActions = $config['show_actions'] ?? true;
        $cardClass = $config['card_class'] ?? 'ag-app-card';
        $actionClass = $config['action_class'] ?? 'ag-app-card__action';

        $key = htmlspecialchars((string)($app['key'] ?? ''), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars((string)($app['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars((string)($app['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars((string)($app['icon'] ?? 'fas fa-circle'), ENT_QUOTES, 'UTF-8');
        $color = htmlspecialchars((string)($app['color'] ?? 'bg-primary'), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars((string)($app['route'] ? 'index.php?ruta=' . rawurlencode((string)$app['route']) : '#'), ENT_QUOTES, 'UTF-8');
        $keywords = $app['keywords'] ?? [];
        if (!is_array($keywords)) {
            $keywords = array_filter([$keywords]);
        }
        $children = $app['children'] ?? [];
        if (!is_array($children)) {
            $children = [];
        }

        $searchTokens = strtolower(trim($label . ' ' . $description . ' ' . implode(' ', $keywords)));
        echo '<div class="' . $wrapperClass . ' ag-app-card-wrapper" data-app-key="' . $key . '" data-app-search="' . htmlspecialchars($searchTokens, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="' . $cardClass . '">';
        echo '<a class="ag-app-card__link" href="' . $url . '">';
        echo '<span class="ag-app-card__icon ' . $color . '"><i class="' . $icon . '"></i></span>';
        echo '<span class="ag-app-card__title">' . $label . '</span>';
        if ($description !== '') {
            echo '<span class="ag-app-card__description">' . $description . '</span>';
        }
        echo '<span class="stretched-link" aria-hidden="true"></span>';
        echo '</a>';
        if ($showActions && !empty($children)) {
            echo '<div class="ag-app-card__actions" role="group" aria-label="Acciones rápidas">';
            foreach ($children as $child) {
                $childLabel = htmlspecialchars((string)($child['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                if ($childLabel === '') {
                    continue;
                }
                $childIcon = htmlspecialchars((string)($child['icon'] ?? ''), ENT_QUOTES, 'UTF-8');
                $childUrl = '#';
                if (!empty($child['route'])) {
                    $childUrl = 'index.php?ruta=' . rawurlencode((string)$child['route']);
                }
                $childUrlAttr = htmlspecialchars($childUrl, ENT_QUOTES, 'UTF-8');
                echo '<a class="' . $actionClass . '" href="' . $childUrlAttr . '">';
                if ($childIcon !== '') {
                    echo '<i class="' . $childIcon . ' me-1"></i>';
                }
                echo $childLabel;
                echo '</a>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }
}
