# Integración de notificaciones para solicitudes y contratos

Este documento describe los preparativos técnicos y los pasos sugeridos para habilitar un sistema de notificaciones automatizadas cuando:

1. Una **solicitud** cambia a estado `aprobada`.
2. Se **crea un contrato** nuevo o se actualiza la información de uno existente.

El objetivo es permitir dos canales de salida:

* **Webhooks hacia n8n**, para orquestar flujos complejos.
* **Mensajes directos mediante Chatwoot / WhatsApp API**, reutilizando plantillas aprobadas.

## 1. Contexto del código existente

- El cambio de estado de las solicitudes se gestiona en `ControladorSolicitudes::ctrCambiarEstado()`. Desde aquí se valida el flujo y se registra la transición (`enviada`, `en_revision`, `aprobada`, etc.).【F:app/Controllers/ControladorSolicitudes.php†L308-L506】
- La creación y edición integral de contratos pasa por `ControladorContratos::ctrCrearContrato()` (flujo rápido) y `ControladorContratos::ctrCrearContratoCompleto()` (flujo con alta de cliente/desarrollo). Ambos generan el JSON que se almacena en `argus_contratos_data` y que se usa para construir los placeholders del DOCX.【F:app/Controllers/ControladorContratos.php†L940-L1104】【F:app/Controllers/ControladorContratos.php†L1202-L1536】
- El JSON del contrato ahora incluye la sección `solicitud`, con subgrupos `albacea` y `anualidad`. Esta estructura se construye en `normalizarSolicitudContrato()` y se inserta al guardar o actualizar contratos.【F:app/Controllers/ControladorContratos.php†L286-L382】【F:app/Controllers/ControladorContratos.php†L1008-L1041】【F:app/Controllers/ControladorContratos.php†L1482-L1525】
- La generación de placeholders para DOCX aprovecha la misma estructura y expone claves como `CONTRATO_ALBACEA_NOMBRE`, `CONTRATO_ANUALIDAD_PAGO_ANUAL`, o `SOLICITUD_ALBACEA_ACTIVO` gracias al nuevo recorrido recursivo en `construirPlaceholdersContrato()`.【F:app/Controllers/ControladorContratos.php†L2374-L2646】

Con esta base se pueden añadir disparadores sin modificar la lógica de negocio existente.

## 2. Preparativos generales

1. **Definir un almacén de configuración** para credenciales de n8n y Chatwoot (por ejemplo variables de entorno en `config.php` o tabla dedicada).
2. **Crear tablas de auditoría/envío** si se requiere llevar un historial de webhooks/mensajes (estatus, intento, respuesta HTTP).
3. **Centralizar el envío** en un servicio (por ejemplo `ControladorNotificacionesExternas`) para evitar duplicar lógica HTTP y permitir reintentos.
4. **Preparar plantillas de mensaje** en Chatwoot/WhatsApp. Identificar placeholders que llegarán desde los JSON (`CONTRATO_FOLIO`, `SOLICITUD_ALBACEA_NOMBRE`, etc.).
5. **Asegurar el manejo de errores**: registrar respuestas fallidas, exponer métricas básicas y definir política de reintentos (p. ej. 3 intentos exponenciales).

## 3. Webhooks para n8n

### Eventos recomendados

| Evento | Punto de enganche | Datos sugeridos |
| --- | --- | --- |
| Solicitud aprobada | Después de `ControladorSolicitudes::ctrCambiarEstado()` cuando `$nuevoEstado === 'aprobada'`. | `solicitud` completa, resumen del cliente, id de usuario que aprobó, timestamp. |
| Contrato creado/actualizado | En `ControladorContratos::ctrCrearContrato()` y `ctrCrearContratoCompleto()` una vez que el `jsonData` se guardó exitosamente. | JSON del contrato (`cliente`, `desarrollo`, `contrato`, `solicitud`), `contrato_id`, `folio`, usuario que realizó la acción. |

### Pasos

1. **Crear un helper** `NotificadorWebhooks::disparar($evento, array $payload)` que:
   - Resuelva la URL objetivo desde la configuración.
   - Envíe `POST` JSON (por ejemplo con cURL) y devuelva éxito/fracaso.
   - Registre en bitácora el código HTTP y el cuerpo de respuesta.
2. **Invocar el helper** inmediatamente después de los `commit`/`mdlCrearContrato`/`mdlEditarContrato` o tras el `mdlActualizarEstado()` exitoso en solicitudes.
3. **Diseñar el payload** utilizando el JSON ya armado. Ejemplo:
   ```json
   {
     "evento": "contrato.creado",
     "contrato_id": 123,
     "folio": "F-2025-001",
     "cliente": { ... },
     "solicitud": { "albacea": { ... }, "anualidad": { ... } },
     "generado_en": "2025-02-10T15:30:00-06:00"
   }
   ```
4. **Configurar n8n** para recibir el webhook, parsear el JSON y encaminarlo (envío de correo, CRM, etc.).
5. **Añadir pruebas manuales/automáticas** que simulen la llamada y verifiquen la respuesta de n8n (usar ambientes de staging cuando sea posible).

## 4. Mensajes con Chatwoot + WhatsApp API

### Flujo sugerido

1. **Resolver el contacto**: usar el teléfono del cliente (`CLIENTE_TELEFONO`) o del albacea (`CONTRATO_ALBACEA_CELULAR`) según el caso.
2. **Seleccionar la plantilla**: definir plantillas en Chatwoot/WhatsApp como `solicitud_aprobada` y `contrato_listo`. Cada plantilla debe mapear placeholders que serán reemplazados con datos del contrato/solicitud.
3. **Enviar mensaje**:
   - Consumir la API de Chatwoot (`POST /api/v1/accounts/{id}/conversations`) o la capa interna si ya existe integración.
   - Incluir en el payload los parámetros de plantilla (folio, fecha de firma, datos de anualidad, etc.).
4. **Registrar resultado** en la tabla de auditoría para posibles reintentos o trazabilidad.

### Integración en código

- Crear un servicio `MensajeriaContratos::enviarContratoListo($registroContrato)` que reciba el arreglo generado por `construirPlaceholdersContrato()` para reutilizar los valores formateados.
- Invocar el servicio después de guardar el contrato y antes de responder al usuario. Asegurarse de envolver el envío en `try/catch` para no bloquear la operación principal.
- Para solicitudes, un método `MensajeriaSolicitudes::enviarAprobacion($solicitud)` que obtenga los datos desde `ctrCambiarEstado()`.

## 5. Consideraciones adicionales

- **Idempotencia**: incluir identificadores únicos (p. ej. `evento_id`) en los payloads para que n8n o Chatwoot puedan ignorar duplicados.
- **Seguridad**: firmar los webhooks con un token HMAC y validar certificados TLS cuando se consuman las APIs externas.
- **Monitoreo**: añadir logs estructurados (JSON) y, si es posible, métricas (promedio de respuesta, número de intentos) para detectar fallos tempranamente.
- **Pruebas**: montar ambientes de prueba que simulen aprobaciones de solicitudes y creación de contratos, verificando que los placeholders `CONTRATO_ALBACEA_*`, `CONTRATO_ANUALIDAD_*` y `SOLICITUD_*` lleguen correctamente al mensaje.

Con estos pasos la aplicación quedará lista para orquestar comunicaciones automáticas mediante n8n y Chatwoot, aprovechando la nueva estructura de datos consolidada en los contratos.
