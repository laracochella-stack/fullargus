<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Controlador responsable de renderizar la plantilla principal.
 */
class ControladorPlantilla
{
    /**
     * Renderiza la plantilla principal de la aplicación.
     */
    public function ctrPlantilla(): void
    {
        include dirname(__DIR__, 2) . '/vistas/plantilla.php';
    }
}
