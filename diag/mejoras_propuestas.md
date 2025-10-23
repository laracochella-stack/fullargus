# Propuestas de mejora para Argus

## Configuración y despliegue
- **Variables de entorno para la base de datos.** Actualmente las credenciales están codificadas en `modelos/conexion.php`, lo que dificulta cambiar de entorno y supone un riesgo de seguridad. Se puede aprovechar la dependencia de `vlucas/phpdotenv` que ya está declarada en `composer.json` para cargar las credenciales desde un archivo `.env` y evitar exponer datos sensibles en el código fuente.
- **Autoloading coherente.** El archivo de entrada `index.php` requiere manualmente cada controlador y modelo. Esto incrementa el mantenimiento y puede provocar errores al añadir nuevas clases. Ajustar el `composer.json` para apuntar al espacio de nombres real del proyecto e incorporar `composer dump-autoload` permitiría cargar clases automáticamente siguiendo PSR-4.

## Dominio de negocio
- **Campo "referencias" en clientes.** Los métodos del modelo de clientes esperan el campo `referencias`, pero ni el controlador ni las vistas lo envían. Esto provoca inserciones con valores nulos y puede generar errores al momento de enlazar contratos. Añadir el campo a los formularios y sanitizarlo en el controlador mantendría la consistencia de los datos.

## Seguridad y calidad de código
- **Manejo de errores más robusto.** Ante fallos de conexión a la base de datos se invoca `die`, interrumpiendo la aplicación sin registro. Sustituirlo por excepciones controladas y logs centralizados facilitará el diagnóstico en producción. También se pueden envolver las operaciones críticas en try/catch para devolver mensajes amigables a la vista.
- **Cobertura de pruebas.** El proyecto no incluye pruebas automatizadas ni scripts en Composer. Incorporar pruebas unitarias para los modelos y pruebas de integración mínimas ayudaría a detectar regresiones en operaciones clave como la generación de contratos o la gestión de usuarios.

## Experiencia de usuario
- **Retroalimentación en formularios.** Las vistas dependen de SweetAlert para notificaciones generales, pero no muestran validación campo a campo. Agregar validaciones dinámicas y mensajes específicos (por ejemplo, longitud del RFC o formato de correo) reduciría errores de captura y llamadas al servidor innecesarias.
