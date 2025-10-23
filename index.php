<?php

declare(strict_types=1);

use App\Controllers\ControladorPlantilla;

require __DIR__ . '/bootstrap/app.php';

$plantilla = new ControladorPlantilla();
$plantilla->ctrPlantilla();
