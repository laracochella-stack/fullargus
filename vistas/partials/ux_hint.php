<?php
if (!function_exists('ag_render_gear_actions_hint')) {
    /**
     * Renderiza un aviso compacto o en formato callout sobre el icono de engrane.
     *
     * @param array{
     *   layout?:'inline'|'callout',
     *   badge?:string,
     *   title?:string,
     *   message?:string,
     *   icon?:string,
     *   class?:string,
     *   id?:string
     * } $options
     */
    function ag_render_gear_actions_hint(array $options = []): void
    {
        $layout = $options['layout'] ?? 'callout';
        if ($layout !== 'inline' && $layout !== 'callout') {
            $layout = 'callout';
        }

        $badge = trim((string)($options['badge'] ?? 'Nuevo'));
        $title = trim((string)($options['title'] ?? 'Icono de engrane'));
        $message = trim((string)($options['message'] ?? 'El botón con el icono de engrane concentra los botones de acción disponibles para el elemento seleccionado.'));
        $icon = trim((string)($options['icon'] ?? 'fas fa-cog'));
        $extraClass = trim((string)($options['class'] ?? ''));
        $id = trim((string)($options['id'] ?? ''));

        $idAttr = $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"' : '';
        $classAttr = $extraClass !== '' ? ' ' . $extraClass : '';
        $badgeHtml = $badge !== ''
            ? '<span class="ag-ux-hint-badge badge bg-primary text-uppercase">' . htmlspecialchars($badge, ENT_QUOTES) . '</span>'
            : '';
        $iconHtml = $icon !== ''
            ? '<span class="ag-ux-hint-icon"><i class="' . htmlspecialchars($icon, ENT_QUOTES) . '"></i></span>'
            : '';
        $titleHtml = $title !== ''
            ? '<strong class="ag-ux-hint-title">' . htmlspecialchars($title, ENT_QUOTES) . '</strong>'
            : '';
        $messageHtml = htmlspecialchars($message, ENT_QUOTES);

        if ($layout === 'inline') {
            echo '<div class="ag-ux-hint ag-ux-hint-inline' . $classAttr . '"' . $idAttr . '>';
            if ($badgeHtml !== '') {
                echo $badgeHtml;
            }
            if ($iconHtml !== '') {
                echo $iconHtml;
            }
            echo '<span class="ag-ux-hint-text">';
            if ($titleHtml !== '') {
                echo $titleHtml . ' ';
            }
            echo $messageHtml . '</span>';
            echo '</div>';
            return;
        }

        echo '<div class="callout callout-info ag-ux-hint' . $classAttr . '"' . $idAttr . '>';
        echo '<div class="ag-ux-hint-header">';
        if ($badgeHtml !== '') {
            echo $badgeHtml;
        }
        if ($iconHtml !== '') {
            echo $iconHtml;
        }
        if ($titleHtml !== '') {
            echo $titleHtml;
        }
        echo '</div>';
        echo '<p class="ag-ux-hint-message mb-0">' . $messageHtml . '</p>';
        echo '</div>';
    }
}
