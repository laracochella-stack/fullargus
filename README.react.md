# Evaluación de React.js para Argus MVC

## Contexto actual del proyecto
- **Tecnología base:** PHP puro con un patrón MVC propio (`app/Controllers`, `app/Models`) y renderizado del lado del servidor mediante plantillas en `vistas/modulos`. Cada módulo incluye lógica de permisos y formularios completos en HTML/PHP (por ejemplo, `vistas/modulos/inicio.php`).
- **Front-end existente:** Se apoya en AdminLTE, Bootstrap 5, jQuery y componentes personalizados escritos en JavaScript vanilla dentro de `vistas/js/app.js`. Este archivo central controla validaciones, modales Bootstrap, DataTables y flujos AJAX específicos.
- **Gestión de activos:** Las hojas de estilo y scripts se versionan mediante `AssetVersion::url`, sin bundlers o pipeline de Node instalado.

## Pros de incorporar React
1. **Componentización reutilizable:** React permitiría encapsular widgets recurrentes (tablas, formularios, modales) con propiedades y estado controlados, reduciendo duplicidad y la necesidad de replicar estructuras HTML en múltiples vistas.
2. **Gestión avanzada de estado:** Casos como los formularios dinámicos de solicitudes/contratos y la interacción con DataTables podrían beneficiarse de un estado predecible (Hooks o libraries como Zustand/Redux), evitando listeners dispersos en `app.js`.
3. **Experiencias más reactivas:** React facilita flujos SPA o de refresco parcial sin recargar toda la vista de PHP. El enrutamiento cliente podría acelerar tareas como revisar múltiples solicitudes o contratos.
4. **Ecosistema amplio:** Disponibilidad de bibliotecas para formularios (React Hook Form), validaciones, tablas virtualizadas y herramientas de pruebas unitarias/componentes.

## Contras y riesgos
1. **Sobrecarga de build y despliegue:** El proyecto no cuenta con Node/bundler; introducir React implica configurar tooling (Vite/Webpack, Babel) y ajustar el ciclo de despliegue PHP para compilar assets.
2. **Duplicación de lógica existente:** Gran parte de la UI ya está resuelta con PHP y JS vanilla. Migrar forzaría a reescribir vistas completas y duplicar validaciones del lado del servidor.
3. **Integración con AdminLTE/DataTables:** Muchas interacciones actuales dependen de plugins jQuery/AdminLTE. Pasarlas a React obliga a envolver o reemplazar estos componentes, lo que puede generar inconsistencias visuales o comportamientos duplicados.
4. **Curva de aprendizaje y mantenimiento:** El equipo deberá dominar React, ecosistema de hooks, routing y testing; además, convivirá con la base legacy en PHP, aumentando la complejidad cognitiva.
5. **Coste en rendimiento inicial:** Una SPA añade descargas adicionales (bundle JS), y en usuarios con equipos modestos podría percibirse un mayor tiempo de carga si no se optimiza el code-splitting.

## Viabilidad
- **Viable parcialmente** para módulos nuevos que requieran alta interactividad (ej. paneles analíticos en tiempo real) siempre que se cree una capa API REST en los controladores (`ControladorSolicitudes`, `ControladorContratos`, etc.).
- **No recomendable** para un reemplazo completo inmediato: las vistas existen y contienen reglas de negocio server-side; migrarlas implicaría reescribir controladores y flujos de validación.
- **Enfoque sugerido:** adopción progresiva. Montar componentes React aislados en contenedores concretos (micro-frontends) respetando el layout de AdminLTE mientras se construyen endpoints JSON dedicados.

## Pasos recomendados si se decide implementar
1. **Preparar el entorno:**
   - Añadir Node.js al pipeline y configurar Vite (recomendado por velocidad) con TypeScript opcional.
   - Definir estructura `/resources/js` o similar para el código React, generando bundles versionados con `AssetVersion`.
2. **Diseñar una capa de API:**
   - Extender controladores PHP existentes para exponer endpoints JSON (por ejemplo, `ControladorSolicitudes::ctrListarSolicitudesJson()`), reutilizando la lógica actual pero desacoplada de la vista.
   - Asegurar autenticación y permisos reutilizando los checks existentes (`$_SESSION['permission']`).
3. **Integración gradual en las vistas:**
   - Insertar contenedores (`<div id="react-root-solicitudes"></div>`) en las plantillas donde se quiera enriquecer la UX.
   - Cargar el bundle compilado solo en las rutas necesarias mediante condicionales en `vistas/plantilla.php`.
4. **Interoperabilidad con Bootstrap/AdminLTE:**
   - Consumir los estilos globales existentes desde React para mantener coherencia visual.
   - Crear wrappers para modales o toasts que sigan utilizando Bootstrap JS o reemplazarlos con alternativas React (React-Bootstrap) respetando la estética.
5. **Manejo del estado y datos:**
   - Implementar servicios de datos centralizados (fetch wrappers) reutilizando las mejoras ya incluidas en `vistas/js/app.js` (por ejemplo, el interceptor `enhancedFetch`).
   - Sincronizar formularios React con validaciones server-side, mostrando errores devueltos por la API.
6. **Testing y despliegue:**
   - Añadir pruebas de componentes (Jest/Testing Library) y ajustar el pipeline de CI/CD para compilar el bundle antes de publicar.
   - Documentar cómo limpiar assets antiguos y cómo versionar los nuevos bundles para evitar caché obsoleta.

## Conclusión
React puede aportar valor en módulos específicos con alta interacción, pero introducirlo requiere infraestructura adicional y una estrategia de coexistencia con el stack PHP actual. Se recomienda empezar con componentes aislados y construir una API consistente antes de plantear una migración más amplia.
