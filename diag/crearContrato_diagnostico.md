# Diagnóstico actualizado del flujo `crearContrato`

Este análisis se centra en el fallo "Respuesta inválida del servidor. (Código: PARSE-001)" reportado al enviar el formulario completo de creación de contratos. Se revisaron la vista `crearContrato.php`, su JavaScript asociado, el controlador `ControladorContratos` y los modelos implicados para rastrear la causa, validar la composición del `FormData`/JSON y enumerar correcciones necesarias.

## 1. Ruteo y ciclo de vida de la respuesta
- **Salida HTML antes del JSON.** La plantilla principal imprime `<!DOCTYPE html>`, cabecera y apertura del `<body>` *antes* de incluir `modulos/crearContrato.php`. Cuando la petición es AJAX, el controlador genera JSON pero la salida ya contiene HTML, por lo que `response.json()` falla con PARSE-001.【F:vistas/plantilla.php†L1-L60】【F:vistas/modulos/crearContrato.php†L1-L44】
- **Controlador dentro de la vista.** `crearContrato.php` invoca `ControladorContratos::ctrCrearContratoCompleto()` al cargarse. Si la respuesta AJAX llega por `fetch`, el método imprime JSON y hace `exit;`, pero queda precedido por la estructura HTML emitida por la plantilla. Es indispensable mover la respuesta JSON antes de que se empiece a renderizar la plantilla.【F:vistas/modulos/crearContrato.php†L11-L44】

## 2. Composición del formulario y del `FormData`
- **Campos duales de fecha.** Cada fecha se captura en formato `date` y en un oculto `*_texto`. `app.js` sincroniza ambas representaciones mediante `sincronizarFechaLarga`, por lo que el `FormData` viaja con los dos valores requeridos (DB + texto).【F:vistas/partials/form_cliente.php†L17-L26】【F:vistas/partials/form_contrato.php†L8-L88】【F:vistas/js/app.js†L35-L52】【F:vistas/js/app.js†L1326-L1346】
- **Fracciones/lotes.** El campo oculto `fracciones` se rellena con una lista separada por comas o JSON. El controlador contempla ambas variantes, por lo que no causa el parseo fallido.【F:vistas/partials/form_contrato.php†L19-L33】【F:app/Controllers/ControladorContratos.php†L326-L347】【F:app/Controllers/ControladorContratos.php†L405-L426】
- **Campos independientes del JSON.** `dice_ser` se captura desde la sección del cliente y viaja junto con sus datos, mientras que las nuevas `observaciones` permanecen en el bloque del contrato sin ligarse a información de la solicitud, manteniéndose disponibles para la serialización final.【F:vistas/partials/form_cliente.php†L60-L69】【F:vistas/partials/form_contrato.php†L154-L156】【F:app/Controllers/ControladorContratos.php†L397-L406】

## 3. Serialización y construcción del JSON
- **Dato `cliente.fecha` ausente.** El JSON generado en ambos flujos asigna `fecha_nacimiento` pero omite la clave histórica `fecha` que todavía consumen plantillas (`CLIENTE_FECHA`). Esto provoca huecos en documentos y debe restaurarse junto con la versión en texto largo.【F:app/Controllers/ControladorContratos.php†L370-L406】【F:app/Controllers/ControladorContratos.php†L431-L459】【F:app/Controllers/ControladorContratos.php†L598-L642】
- **Errores de serialización silenciosos.** `json_encode` se invoca sin manejo de errores; ante un byte inválido devuelve `false`, el controlador responde cadena vacía y el frontend lanza PARSE-001 sin detalles. Debe activarse `JSON_THROW_ON_ERROR` y capturar `JsonException` para retornar un JSON de error explícito.【F:app/Controllers/ControladorContratos.php†L403-L457】

## 4. JavaScript (`app.js`)
- **Diagnóstico incompleto en PARSE-001.** La promesa `fetch` intenta `response.json()` directamente. Cuando falla, solo se lanza `Error('INVALID_JSON')` y se muestra el mensaje genérico sin registrar el cuerpo real; eso impide saber qué devolvió el servidor. Es necesario conservar la respuesta en texto y volcarla en consola/feedback para depurar.【F:vistas/js/app.js†L1123-L1184】
- **SweetAlert limitado.** Las alertas de confirmación funcionan, pero al caer en PARSE-001 nunca llegan a ejecutarse otros `Swal.fire`. Al exponer el error en el panel y consola se podrá depurar y reutilizar SweetAlert para el flujo feliz.【F:vistas/js/app.js†L1111-L1184】

## 5. Modelo y transacciones
- **Transacción sí comparte conexión.** Gracias a `Conexion::conectar()` (singleton), el `BEGIN` del controlador abarca las inserciones en clientes/contratos; no obstante, al no propagarse códigos de error detallados (`errorInfo`) desde los modelos, el controlador termina devolviendo mensajes genéricos cuando algo falla en DB, dificultando el análisis de datos inválidos.【F:app/Database/Conexion.php†L16-L44】【F:app/Models/ModeloClientes.php†L34-L55】【F:app/Controllers/ControladorContratos.php†L470-L520】

## 6. Acciones recomendadas
1. Interceptar las peticiones POST del flujo `crearContrato` en la plantilla antes de emitir cualquier HTML y delegar al controlador para garantizar respuestas JSON limpias.
2. Actualizar `ctrCrearContratoCompleto` (y el flujo abreviado) para incluir `cliente.fecha`/`cliente.fecha_texto` y usar `JSON_THROW_ON_ERROR`, retornando errores estructurados cuando falle la serialización.
3. Mejorar el `catch` del `fetch` registrando el cuerpo bruto y mostrando el código en pantalla; reutilizar SweetAlert solo para confirmaciones/éxitos.
4. Aprovechar el `errorInfo` de PDO para adjuntar códigos específicos en las respuestas JSON de error.

Con estos ajustes el flujo debería dejar de responder HTML en solicitudes AJAX, eliminar el PARSE-001 y proporcionar mensajes claros cuando la base de datos rechace los datos capturados.
# Diagnóstico del flujo `crearContrato`

Este documento resume los hallazgos al revisar el flujo completo para crear un contrato nuevo (vista `crearContrato.php`, controlador `ControladorContratos`, modelos relacionados y la lógica JS).

## 1. Controlador `ControladorContratos::ctrCrearContratoCompleto`

### Errores críticos

- **Inserción del cliente con fecha inválida**. El arreglo `$datosCliente` guarda la fecha de nacimiento formateada en texto largo (`"24 DE SEPTIEMBRE DE 2025"`) y se envía como valor de la columna `fecha_nacimiento` (`:fecha`) cuando se inserta el cliente. La columna es de tipo `DATE`, por lo que la sentencia falla y toda la creación del contrato devuelve `error`.【F:app/Controllers/ControladorContratos.php†L205-L233】【F:app/Models/ModeloClientes.php†L49-L69】
- **Referencias no mapeadas**. El controlador siempre incluye `referencias` y `dice_ser` en `$datosCliente`, pero el esquema `argus_clientes` distribuido en `sql/argus.sql` no tiene una columna `referencias` ni `dice_ser`. El `INSERT` del modelo falla en entornos que todavía usan esta estructura.【F:app/Controllers/ControladorContratos.php†L222-L225】【F:app/Models/ModeloClientes.php†L49-L65】【F:sql/argus.sql†L30-L45】
- **Clientes existentes duplicados**. Aun cuando la vista recibe `cliente_id` (cliente ya registrado), el controlador ignora ese id y ejecuta siempre `mdlAgregarClienteRetId`, generando duplicados y repitiendo el problema de fecha/columnas. Se debe bifurcar la lógica para reutilizar el cliente existente.【F:vistas/modulos/crearContrato.php†L41-L64】【F:app/Controllers/ControladorContratos.php†L227-L233】

### Riesgos adicionales

- **`catch (Exception $e)` sin importar la clase**. Al estar en un `namespace`, `Exception` debería ser `\Exception` o importarse; si en el futuro se activa `JSON_THROW_ON_ERROR`, el `catch` produciría un error fatal al no encontrar `App\Controllers\Exception`.【F:app/Controllers/ControladorContratos.php†L251-L255】
- **Inserción sin transacción**. Se inserta primero el cliente y luego el contrato; si la segunda operación falla quedan clientes huérfanos. Conviene envolver ambos pasos en una transacción o revertir el alta del cliente cuando `mdlCrearContrato` devuelva error.【F:app/Controllers/ControladorContratos.php†L227-L358】
- **Validación de sesión/token con `return; exit;`**. Hay `return; exit;` consecutivos que nunca se ejecutan y pueden eliminarse para claridad.【F:app/Controllers/ControladorContratos.php†L176-L185】

## 2. Modelo `ModeloClientes`

- **Consulta desactualizada**. El `INSERT` y `UPDATE` esperan columnas `referencias` que no existen en el SQL base; además no contemplan `dice_ser` ni `edad`. Se debe alinear el modelo con el esquema real o migrar la base de datos.【F:app/Models/ModeloClientes.php†L20-L105】【F:sql/argus.sql†L30-L63】

## 3. Modelo `ModeloContratos`

- **Campos inexistentes en SELECT**. Los métodos `mdlMostrarContratos` y `mdlMostrarContratoPorId` devuelven `estatus`, pero la tabla `argus_contratos_data` publicada no tiene esa columna. Puede provocar errores SQL si la estructura no está migrada.【F:app/Models/ModeloContratos.php†L87-L156】【F:sql/argus.sql†L58-L76】
- **Mensajes de error ambiguos**. `mdlCrearContrato` retorna `"error: fallo execute()"` sin exponer el motivo real. Sería mejor propagar `PDOException` o registrar el error para depurar más rápido.【F:app/Models/ModeloContratos.php†L37-L52】

## 4. Vista `crearContrato.php` y parciales

- La vista intenta soportar reutilización de clientes existentes (`cliente_id` oculto), pero el backend no la respeta actualmente.【F:vistas/modulos/crearContrato.php†L41-L64】
- El formulario del contrato ofrece un campo de `observaciones` opcional y ya no se alimenta automáticamente con referencias de la solicitud; es necesario validar que los usuarios capturen manualmente cualquier indicación relevante.【F:vistas/partials/form_contrato.php†L154-L156】

## 5. JavaScript (`vistas/js/app.js`)

- **Respuesta del fetch**. El código interpreta la respuesta como texto y sólo busca la cadena `"ok"`. Cuando el backend devuelva JSON (lo cual ya hace si detecta `XMLHttpRequest`), la cadena contiene `"error"` dentro del mensaje o cambia el formato, provocando falsos negativos. Se recomienda forzar `fetch(...).then(r => r.json())` y validar `status`.【F:vistas/js/app.js†L1080-L1128】
- **Validación telefónica dependiente de CDN**. Si la librería `intl-tel-input` no carga desde la CDN, nunca se llena `cliente_telefono`, provocando el error de campo vacío al insertar. Considerar validación server-side de respaldo.【F:vistas/js/app.js†L1245-L1292】【F:vistas/partials/form_cliente.php†L48-L55】
- **Cálculo de mensualidades/rango**. El script sincroniza fechas y mensualidades, pero si el usuario edita manualmente el campo `mensualidades`, se sobreescribe al cambiar las fechas sin avisar. Documentar este comportamiento o bloquear la edición manual.【F:vistas/js/app.js†L1299-L1338】

## 6. Recomendaciones prioritarias

1. **Corregir la inserción de clientes**: conservar la fecha en formato `YYYY-MM-DD`, respetar `cliente_id` existente y ajustar las columnas utilizadas (`referencias`, `dice_ser`, `edad`).
2. **Migrar o actualizar el esquema**: asegurarse de que las columnas mencionadas en los modelos existan (en especial `referencias` y `estatus`).
3. **Implementar transacción** para el alta combinada cliente/contrato y registrar errores de manera explícita.
4. **Alinear el frontend con el backend**: revisar los campos obligatorios, mejorar el manejo de la respuesta `fetch` y agregar validaciones de respaldo para librerías externas.

Con estos ajustes se debe restablecer el envío de contratos y facilitar el mantenimiento del módulo.
