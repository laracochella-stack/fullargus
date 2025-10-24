<?php
if (!function_exists('ag_render_app_card')) {
    /**
     * Render an application card used both in the home grid and app drawer.
     *
     * @param array<string, mixed> $app    Module definition from AppNavigation::getAppCards().
     * @param array<string, mixed> $config Optional configuration (wrapper_class, card_class).
     */
    function ag_render_app_card(array $app, array $config = []): void
    {
        $wrapperClass = $config['wrapper_class'] ?? 'col-12 col-sm-6 col-lg-4 col-xl-3';
        $cardClass = $config['card_class'] ?? 'ag-app-card';

        $key = htmlspecialchars((string)($app['key'] ?? ''), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars((string)($app['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars((string)($app['icon'] ?? 'fas fa-circle'), ENT_QUOTES, 'UTF-8');
        $color = htmlspecialchars((string)($app['color'] ?? 'bg-primary'), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars((string)($app['route'] ? 'index.php?ruta=' . rawurlencode((string)$app['route']) : '#'), ENT_QUOTES, 'UTF-8');
        $keywords = $app['keywords'] ?? [];
        if (!is_array($keywords)) {
            $keywords = array_filter([$keywords]);
        }
        $searchTokens = strtolower(trim($label . ' ' . implode(' ', $keywords)));
        echo '<div class="' . $wrapperClass . ' ag-app-card-wrapper" data-app-key="' . $key . '" data-app-search="' . htmlspecialchars($searchTokens, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="' . $cardClass . '">';
        echo '<a class="ag-app-card__link" href="' . $url . '">';
        echo '<span class="ag-app-card__icon ' . $color . '"><i class="' . $icon . '"></i></span>';
        echo '<span class="ag-app-card__title">' . $label . '</span>';
        echo '<span class="stretched-link" aria-hidden="true"></span>';
        echo '</a>';
        echo '</div>';
        echo '</div>';
    }
}
