<?php
/**
 * Servicio AJAX para convertir un número a su representación en letras.
 * Permite convertir montos monetarios y superficies en metros cuadrados.
 */

header('Content-Type: text/plain; charset=UTF-8');

$tipo = isset($_POST['tipo']) ? strtolower(trim((string)$_POST['tipo'])) : 'moneda';
$entradaNumero = $_POST['num'] ?? 0;

function normalizarNumero($valor): float
{
    if (is_string($valor)) {
        $limpio = str_replace(',', '', $valor);
        $limpio = preg_replace('/[^0-9.\-]/', '', $limpio);
        if ($limpio === '' || $limpio === '-' || $limpio === '.') {
            return 0.0;
        }
        return (float)$limpio;
    }

    if (is_numeric($valor)) {
        return (float)$valor;
    }

    return 0.0;
}

function formatearMoneda(float $numero): string
{
    if (class_exists('NumberFormatter')) {
        try {
            $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
            $entero = (int)floor($numero);
            $decimales = (int)round(($numero - $entero) * 100);
            $numeroFormateado = number_format($numero, 2, '.', ',');
            $letrasEntero = mb_strtoupper($formatter->format($entero));
            $decimalesTexto = str_pad((string)abs($decimales), 2, '0', STR_PAD_LEFT);

            return '$' . $numeroFormateado . ' (' . $letrasEntero . ' PESOS ' . $decimalesTexto . '/100 M.N.)';
        } catch (\Throwable $e) {
            // Ignorar y usar el fallback simple.
        }
    }

    return number_format($numero, 2, '.', ',');
}

function formatearSuperficie(float $numero): string
{
    $numero = max(0, $numero);
    $numeroBase = rtrim(rtrim(number_format($numero, 2, '.', ','), '0'), '.');
    if ($numeroBase === '') {
        $numeroBase = '0';
    }

    $unidad = abs($numero - 1.0) < 0.005 ? 'METRO CUADRADO' : 'METROS CUADRADOS';

    if (class_exists('NumberFormatter')) {
        try {
            $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
            $entero = (int)floor($numero);
            $decimales = (int)round(($numero - $entero) * 100);
            $textoEntero = mb_strtoupper($formatter->format($entero));
            if ($textoEntero === '') {
                $textoEntero = 'CERO';
            }

            $texto = $textoEntero;
            if ($decimales > 0) {
                $textoDecimales = mb_strtoupper($formatter->format($decimales));
                $texto .= ' PUNTO ' . $textoDecimales;
            }

            return $numeroBase . ' M2 ' . $texto . ' ' . $unidad;
        } catch (\Throwable $e) {
            // Fallback numérico si no se puede convertir.
        }
    }

    return $numeroBase . ' M2 ' . $unidad;
}

$numeroNormalizado = normalizarNumero($entradaNumero);

if ($tipo === 'superficie') {
    echo formatearSuperficie($numeroNormalizado);
    return;
}

echo formatearMoneda($numeroNormalizado);
