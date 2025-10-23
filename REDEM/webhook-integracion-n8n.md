# Revisión global y lineamientos para webhook con n8n

## Panorama general
- **Controlador de solicitudes**. Centraliza el guardado de borradores, validaciones de negocio y transiciones de estado. Valida sesión, CSRF y normaliza campos antes de insertar o actualizar registros. Al aprobar cambios dispara notificaciones internas para autores y gestores.【F:app/Controllers/ControladorSolicitudes.php†L201-L317】【F:app/Controllers/ControladorSolicitudes.php†L323-L503】
- **Controlador de contratos**. Gestiona el prellenado desde solicitudes, cancelaciones con controles de permisos y generación de documentos (DOCX/PDF). Expone utilidades masivas para actualizar estatus y contar contratos por usuario.【F:app/Controllers/ControladorContratos.php†L522-L657】【F:app/Controllers/ControladorContratos.php†L1962-L2050】【F:app/Controllers/ControladorContratos.php†L2175-L2234】

## Fortalezas detectadas
- Reglas de validación robustas que previenen operaciones sin sesión, sin token o sin los roles adecuados, tanto en solicitudes como en contratos.【F:app/Controllers/ControladorSolicitudes.php†L205-L341】【F:app/Controllers/ControladorContratos.php†L558-L595】
- Normalización consistente de datos (fechas, folios, montos, identificadores) antes de persistirlos, lo cual facilita reutilizar la información en plantillas o integraciones posteriores.【F:app/Controllers/ControladorSolicitudes.php†L213-L279】【F:app/Controllers/ControladorContratos.php†L678-L753】
- Flujos de notificación ya encapsulados para eventos clave (envío, aprobación, regreso a borrador), ideales como puntos de enganche para publicar eventos externos.【F:app/Controllers/ControladorSolicitudes.php†L481-L499】
- Generación de contratos desacoplada en un método dedicado, con control de memoria y manejo de errores centralizado, lo que simplifica encapsular la salida en un webhook o job asíncrono.【F:app/Controllers/ControladorContratos.php†L1963-L2049】

## Áreas de oportunidad
- Los controladores continúan dependiendo de `$_POST`/`$_GET` y mezclan normalización, autorización y persistencia en métodos extensos; esto complica reutilizar la lógica desde procesos externos o pruebas unitarias.【F:app/Controllers/ControladorSolicitudes.php†L201-L317】【F:app/Controllers/ControladorContratos.php†L558-L657】
- No existe una capa de eventos o colas; las notificaciones están acopladas al flujo sin confirmación de entrega, por lo que un fallo en n8n podría pasar desapercibido.
- Falta traza centralizada de cambios (auditoría) para respaldar reintentos o depuraciones si el webhook falla.

## Requerimientos para un webhook hacia n8n
1. **Puntos de disparo**
   - Guardado de solicitud (crear/actualizar) y envíos de estado (`ctrGuardarSolicitud`, `ctrCambiarEstado`) para notificar capturas, envíos y aprobaciones.【F:app/Controllers/ControladorSolicitudes.php†L201-L317】【F:app/Controllers/ControladorSolicitudes.php†L323-L503】
   - Cancelación de contratos, generación de documentos y actualizaciones masivas (`ctrCancelarContrato`, `ctrGenerarDocumento`, `ctrActualizarEstatusMasivo`) para reflejar cambios en expedientes o documentación externa.【F:app/Controllers/ControladorContratos.php†L556-L657】【F:app/Controllers/ControladorContratos.php†L1962-L2050】【F:app/Controllers/ControladorContratos.php†L2175-L2234】

2. **Servicio emisor**
   - Crear una clase `WebhookPublisher` (o similar) que reciba un nombre de evento y payload normalizado. Esta clase deberá manejar reintentos, timeouts y registro de errores (log + base de datos) antes de enviar peticiones `POST` firmadas hacia n8n.
   - Inyectar este servicio en los puntos de disparo reemplazando o complementando a las llamadas de notificación existentes, garantizando que cualquier excepción no rompa el flujo principal.

3. **Estructura del payload**
   - Incluir metadatos (`evento`, `timestamp`, `usuario_id`, `origen`) y el objeto afectado (`solicitud` o `contrato`) con los campos normalizados ya disponibles en los controladores. Aprovechar los campos agregados tras las normalizaciones para reducir transformaciones adicionales.【F:app/Controllers/ControladorSolicitudes.php†L213-L279】【F:app/Controllers/ControladorContratos.php†L678-L753】
   - Adjuntar contexto de estado previo/nuevo cuando corresponda (por ejemplo, envío vs. aprobación) usando los datos que ya se recuperan al notificar.【F:app/Controllers/ControladorSolicitudes.php†L471-L499】

4. **Resiliencia y monitoreo**
   - Registrar cada intento en una tabla `webhook_eventos` con columnas para estado, respuesta y número de reintentos.
   - Programar un job periódico (cron) que reintente envíos pendientes hacia n8n para evitar pérdida de información cuando el flujo externo esté caído.

## Próximos pasos sugeridos
- Refactorizar los controladores para extraer validaciones y normalizaciones a servicios reutilizables, facilitando su uso desde procesos CLI o colas.
- Implementar capa de eventos internos (observer/publisher) que desacople los controladores de cualquier destino (notificaciones, webhooks, colas).
- Documentar contratos de payload (esquemas JSON) y autenticación esperada por n8n, asegurando compatibilidad con flujos existentes.
