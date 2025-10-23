# Ajuste manual del campo "Dice ser" en contratos

Esta guía describe cómo cambiar manualmente la relación entre el campo **Parentesco** capturado en las solicitudes y el campo **Dice ser** que aparece en el formulario de creación de contratos. Úsala si necesitas modificar o desactivar el llenado automático implementado en el sistema.

## Flujo actual
1. En `vistas/modulos/nuevaSolicitud.php` se captura el valor de `parentesco_beneficiario` durante el registro de la solicitud.
2. Cuando se genera un contrato desde una solicitud, `vistas/modulos/crearContrato.php` toma ese valor, lo normaliza en mayúsculas y lo pasa como sugerencia al formulario del cliente (`$clientePrefill['dice_ser']`).
3. El parcial `vistas/partials/form_cliente.php` muestra el campo **Dice ser** precargado con la sugerencia y permite que el usuario la ajuste antes de guardar.

## Cambiar el comportamiento
### 1. Usar un campo distinto como origen
Modifica la sección donde se calcula `$diceSerSugerido` en `vistas/modulos/crearContrato.php`:
```php
$diceSerSugerido = $upper($solicitudSeleccionada['parentesco_beneficiario'] ?? '');
```
Sustituye `parentesco_beneficiario` por la clave que quieras emplear (por ejemplo, `ocupacion`).

### 2. Deshabilitar la sugerencia automática
Si deseas que el formulario deje el campo vacío siempre:
1. Ajusta `$diceSerSugerido` en `crearContrato.php` para que devuelva una cadena vacía.
2. Elimina la lógica de respaldo en `vistas/partials/form_cliente.php` que toma `parentesco_beneficiario` cuando `dice_ser` viene vacío.

### 3. Hacer el campo de solo lectura
En `vistas/partials/form_cliente.php`, añade el atributo `readonly` al `<input>` de `name="dice_ser"` y proporciona un mensaje aclaratorio para los usuarios.

## Validar cambios
Después de modificar cualquiera de los pasos anteriores:
1. Limpia la caché del navegador si existe almacenamiento local involucrado.
2. Genera una nueva solicitud de prueba y crea un contrato desde ella para verificar el comportamiento.
3. Si los datos provienen de migraciones o scripts externos, confirma que se guardan en la base de datos y se muestran correctamente en el contrato final.

> **Nota:** Mantén un registro de las personalizaciones locales. Así podrás re aplicarlas tras actualizaciones o despliegues del sistema.
