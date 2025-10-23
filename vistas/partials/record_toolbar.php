<?php
if (!function_exists('ag_html_attributes')) {
    function ag_html_attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $html .= ' ' . htmlspecialchars($name, ENT_QUOTES);
                continue;
            }
            $html .= sprintf(
                ' %s="%s"',
                htmlspecialchars((string)$name, ENT_QUOTES),
                htmlspecialchars((string)$value, ENT_QUOTES)
            );
        }
        return $html;
    }
}

if (!function_exists('ag_render_record_toolbar_icon')) {
    function ag_render_record_toolbar_icon(?string $icon): string
    {
        $icon = trim((string)$icon);
        if ($icon === '') {
            return '';
        }
        return '<i class="' . htmlspecialchars($icon, ENT_QUOTES) . ' me-2"></i>';
    }
}

if (!function_exists('ag_render_record_toolbar_menu_action')) {
    function ag_render_record_toolbar_menu_action(array $action): string
    {
        $type = isset($action['type']) ? strtolower((string)$action['type']) : 'link';
        if ($type === 'divider') {
            return '<div class="dropdown-divider"></div>';
        }

        if ($type === 'raw' && isset($action['html'])) {
            return (string)$action['html'];
        }

        $label = htmlspecialchars((string)($action['label'] ?? ''), ENT_QUOTES);
        $iconHtml = ag_render_record_toolbar_icon($action['icon'] ?? '');
        $extraHtml = '';
        $description = isset($action['description']) ? trim((string)$action['description']) : '';
        if ($description !== '') {
            $extraHtml = '<span class="ag-record-toolbar-action-description">' . htmlspecialchars($description, ENT_QUOTES) . '</span>';
        }

        $baseClass = 'dropdown-item ag-record-toolbar-menu-action';
        $classes = $baseClass;
        if (!empty($action['class'])) {
            $classes .= ' ' . trim((string)$action['class']);
        }

        $attributes = [];
        if (!empty($action['attributes']) && is_array($action['attributes'])) {
            $attributes = $action['attributes'];
        }
        if (!empty($action['data']) && is_array($action['data'])) {
            foreach ($action['data'] as $dataKey => $dataValue) {
                $attributes['data-' . $dataKey] = $dataValue;
            }
        }
        if (!empty($action['disabled'])) {
            $attributes['aria-disabled'] = 'true';
            $attributes['tabindex'] = '-1';
            if ($type === 'link') {
                $attributes['class'] = trim(($attributes['class'] ?? '') . ' disabled');
            } else {
                $attributes['disabled'] = true;
            }
        }

        switch ($type) {
            case 'form':
                $formMethod = strtolower((string)($action['method'] ?? 'post')) === 'get' ? 'get' : 'post';
                $formAction = htmlspecialchars((string)($action['action'] ?? '#'), ENT_QUOTES);
                $formClass = 'ag-record-toolbar-menu-form';
                if (!empty($action['form_class'])) {
                    $formClass .= ' ' . trim((string)$action['form_class']);
                }
                $buttonAttributes = $attributes;
                if (!empty($action['button_attributes']) && is_array($action['button_attributes'])) {
                    foreach ($action['button_attributes'] as $attrName => $attrValue) {
                        if ($attrValue === null) {
                            continue;
                        }
                        $buttonAttributes[$attrName] = $attrValue;
                    }
                }
                if (!empty($action['confirm'])) {
                    $buttonAttributes['data-confirm-text'] = $action['confirm'];
                }
                $inputsHtml = '';
                if (!empty($action['inputs']) && is_array($action['inputs'])) {
                    foreach ($action['inputs'] as $input) {
                        $inputName = htmlspecialchars((string)($input['name'] ?? ''), ENT_QUOTES);
                        if ($inputName === '') {
                            continue;
                        }
                        $inputType = htmlspecialchars((string)($input['type'] ?? 'hidden'), ENT_QUOTES);
                        $inputValue = htmlspecialchars((string)($input['value'] ?? ''), ENT_QUOTES);
                        $inputsHtml .= sprintf('<input type="%s" name="%s" value="%s">', $inputType, $inputName, $inputValue);
                    }
                }
                $buttonHtml = sprintf(
                    '<button type="submit" class="%s"%s>%s<span class="ag-record-toolbar-action-label">%s</span>%s</button>',
                    htmlspecialchars($classes, ENT_QUOTES),
                    ag_html_attributes($buttonAttributes),
                    $iconHtml,
                    $label,
                    $extraHtml
                );
                return sprintf('<form method="%s" action="%s" class="%s">%s%s</form>', $formMethod, $formAction, htmlspecialchars($formClass, ENT_QUOTES), $inputsHtml, $buttonHtml);

            case 'button':
                $buttonType = htmlspecialchars((string)($action['button_type'] ?? 'button'), ENT_QUOTES);
                $buttonAttributes = $attributes;
                if (!empty($action['confirm'])) {
                    $buttonAttributes['data-confirm-text'] = $action['confirm'];
                }
                return sprintf(
                    '<button type="%s" class="%s"%s>%s<span class="ag-record-toolbar-action-label">%s</span>%s</button>',
                    $buttonType,
                    htmlspecialchars($classes, ENT_QUOTES),
                    ag_html_attributes($buttonAttributes),
                    $iconHtml,
                    $label,
                    $extraHtml
                );

            case 'link':
            default:
                $href = htmlspecialchars((string)($action['url'] ?? '#'), ENT_QUOTES);
                return sprintf(
                    '<a href="%s" class="%s"%s>%s<span class="ag-record-toolbar-action-label">%s</span>%s</a>',
                    $href,
                    htmlspecialchars($classes, ENT_QUOTES),
                    ag_html_attributes($attributes),
                    $iconHtml,
                    $label,
                    $extraHtml
                );
        }
    }
}

if (!function_exists('ag_render_record_toolbar')) {
    function ag_render_record_toolbar(array $config): void
    {
        $primary = $config['primary_action'] ?? null;
        $secondary = $config['secondary_action'] ?? null;
        $title = trim((string)($config['title'] ?? ''));
        $subtitle = trim((string)($config['subtitle'] ?? ''));
        $badges = [];
        if (!empty($config['badges']) && is_array($config['badges'])) {
            foreach ($config['badges'] as $badge) {
                $label = trim((string)($badge['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $class = 'badge bg-secondary';
                if (!empty($badge['class'])) {
                    $class = trim((string)$badge['class']);
                }
                $badges[] = sprintf('<span class="%s">%s</span>', htmlspecialchars($class, ENT_QUOTES), htmlspecialchars($label, ENT_QUOTES));
            }
        }
        $menuActions = [];
        if (!empty($config['menu_actions']) && is_array($config['menu_actions'])) {
            foreach ($config['menu_actions'] as $action) {
                if (empty($action)) {
                    continue;
                }
                $menuActions[] = ag_render_record_toolbar_menu_action($action);
            }
        }
        $meta = [];
        if (!empty($config['meta']) && is_array($config['meta'])) {
            foreach ($config['meta'] as $metaLine) {
                $metaLine = trim((string)$metaLine);
                if ($metaLine !== '') {
                    $meta[] = '<span class="ag-record-toolbar-meta-item">' . htmlspecialchars($metaLine, ENT_QUOTES) . '</span>';
                }
            }
        }
        $extraHtml = isset($config['extra_html']) ? (string)$config['extra_html'] : '';

        echo '<div class="ag-record-toolbar card shadow-sm border-0 mb-4">';
        echo '<div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">';

        echo '<div class="ag-record-toolbar-section ag-record-toolbar-primary">';
        if (is_array($primary) && !empty($primary['label'])) {
            $label = htmlspecialchars((string)$primary['label'], ENT_QUOTES);
            $iconHtml = ag_render_record_toolbar_icon($primary['icon'] ?? '');
            $class = 'btn btn-primary';
            if (!empty($primary['class'])) {
                $class = trim((string)$primary['class']);
            }
            $attributes = !empty($primary['attributes']) && is_array($primary['attributes']) ? $primary['attributes'] : [];
            $href = htmlspecialchars((string)($primary['url'] ?? '#'), ENT_QUOTES);
            echo sprintf('<a href="%s" class="%s"%s>%s%s</a>', $href, htmlspecialchars($class, ENT_QUOTES), ag_html_attributes($attributes), $iconHtml, $label);
        }
        echo '</div>';

        echo '<div class="ag-record-toolbar-section ag-record-toolbar-info">';
        echo '<div class="ag-record-toolbar-summary">';
        if ($title !== '') {
            echo '<div class="ag-record-toolbar-title">' . htmlspecialchars($title, ENT_QUOTES) . '</div>';
        }
        if ($subtitle !== '' || $badges) {
            echo '<div class="ag-record-toolbar-subtitle">';
            if ($subtitle !== '') {
                echo '<span>' . htmlspecialchars($subtitle, ENT_QUOTES) . '</span>';
            }
            if ($badges) {
                echo '<span class="ag-record-toolbar-badges">' . implode('', $badges) . '</span>';
            }
            echo '</div>';
        }
        if ($meta) {
            echo '<div class="ag-record-toolbar-meta">' . implode('', $meta) . '</div>';
        }
        if ($extraHtml !== '') {
            echo '<div class="ag-record-toolbar-extra">' . $extraHtml . '</div>';
        }
        echo '</div>';
        if ($menuActions) {
            echo '<div class="dropdown ag-record-toolbar-actions">';
            echo '<button class="btn btn-outline-secondary ag-record-toolbar-gear" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones del registro">';
            echo '<i class="fas fa-cog"></i>';
            echo '</button>';
            echo '<div class="dropdown-menu dropdown-menu-end ag-record-toolbar-dropdown">' . implode('', $menuActions) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="ag-record-toolbar-section ag-record-toolbar-secondary">';
        if (is_array($secondary) && !empty($secondary['label'])) {
            $label = htmlspecialchars((string)$secondary['label'], ENT_QUOTES);
            $iconHtml = ag_render_record_toolbar_icon($secondary['icon'] ?? '');
            $class = 'btn btn-outline-secondary';
            if (!empty($secondary['class'])) {
                $class = trim((string)$secondary['class']);
            }
            $attributes = !empty($secondary['attributes']) && is_array($secondary['attributes']) ? $secondary['attributes'] : [];
            $href = htmlspecialchars((string)($secondary['url'] ?? '#'), ENT_QUOTES);
            echo sprintf('<a href="%s" class="%s"%s>%s%s</a>', $href, htmlspecialchars($class, ENT_QUOTES), ag_html_attributes($attributes), $iconHtml, $label);
        }
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }
}
