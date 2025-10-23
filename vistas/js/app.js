/*
 * Script principal de Argus MVC.
 * Se utiliza para manejar eventos comunes en la vista, como mostrar modales
 * y realizar peticiones AJAX para la creación de registros.
*/

(function enhanceGlobalFetch() {
    if (typeof window === 'undefined' || typeof window.fetch !== 'function' || window.fetch.__ag_patched) {
        return;
    }

    const originalFetch = window.fetch.bind(window);
    const defaultTimeout = 30000;

    const dispatchLifecycleEvent = (name, detail) => {
        try {
            document.dispatchEvent(new CustomEvent(name, { detail }));
        } catch (error) {
            console.warn('No fue posible despachar el evento AJAX', name, error);
        }
    };

    window.fetch = function enhancedFetch(input, init) {
        const originalInit = init && typeof init === 'object' ? { ...init } : {};
        const skipOverlay = Object.prototype.hasOwnProperty.call(originalInit, 'agSkipOverlay')
            ? Boolean(originalInit.agSkipOverlay)
            : false;
        if (Object.prototype.hasOwnProperty.call(originalInit, 'agSkipOverlay')) {
            delete originalInit.agSkipOverlay;
        }
        const request = input instanceof Request ? input : null;
        const headers = new Headers(
            originalInit.headers
                || (request ? request.headers : undefined)
                || undefined
        );

        if (!headers.has('X-Requested-With')) {
            headers.set('X-Requested-With', 'XMLHttpRequest');
        }

        if (!headers.has('Accept')) {
            headers.set('Accept', 'application/json, text/plain, */*');
        }

        const controller = new AbortController();
        const timeoutMs = typeof originalInit.agTimeout === 'number' ? originalInit.agTimeout : defaultTimeout;
        if (Object.prototype.hasOwnProperty.call(originalInit, 'agTimeout')) {
            delete originalInit.agTimeout;
        }

        const AbortSignalCtor = typeof AbortSignal !== 'undefined' ? AbortSignal : null;
        const upstreamSignal = originalInit.signal || (request ? request.signal : undefined);
        if (AbortSignalCtor && upstreamSignal instanceof AbortSignalCtor) {
            if (upstreamSignal.aborted) {
                controller.abort();
            } else {
                upstreamSignal.addEventListener('abort', () => controller.abort(), { once: true });
            }
        }

        const options = {
            ...originalInit,
            headers,
            signal: controller.signal,
            credentials: originalInit.credentials
                || (request ? request.credentials : undefined)
                || 'same-origin',
        };

        let timeoutId = 0;
        if (Number.isFinite(timeoutMs) && timeoutMs > 0) {
            timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);
        }

        const startedAt = Date.now();
        if (!skipOverlay) {
            dispatchLifecycleEvent('ag:ajax:start', { input, options, startedAt });
        }

        return originalFetch(input, options).then((response) => {
            if (!skipOverlay) {
                dispatchLifecycleEvent('ag:ajax:complete', {
                    input,
                    options,
                    response,
                    duration: Date.now() - startedAt,
                });
            }
            return response;
        }).catch((error) => {
            if (!skipOverlay) {
                dispatchLifecycleEvent('ag:ajax:error', {
                    input,
                    options,
                    error,
                    duration: Date.now() - startedAt,
                });
            }
            throw error;
        }).finally(() => {
            if (timeoutId) {
                window.clearTimeout(timeoutId);
            }
        });
    };

    window.fetch.__ag_patched = true;
})();

const agSwalHelpers = (() => {
    const successDefaults = {
        timer: 1800,
        showConfirmButton: false,
        timerProgressBar: false,
    };

    const aplicarDefaultsSuccess = (config) => {
        if (!config || typeof config !== 'object') {
            return config;
        }

        const icon = typeof config.icon === 'string' ? config.icon.toLowerCase() : '';
        if (icon !== 'success') {
            return config;
        }

        const configuracion = { ...config, icon: 'success' };
        if (typeof configuracion.timer === 'undefined') {
            configuracion.timer = successDefaults.timer;
        }
        configuracion.showConfirmButton = successDefaults.showConfirmButton;
        if (typeof configuracion.timerProgressBar === 'undefined') {
            configuracion.timerProgressBar = successDefaults.timerProgressBar;
        }

        return configuracion;
    };

    const fallbackAlert = (title, message = '', defaultTitle = 'Aviso', icon = 'info') => {
        const titulo = title == null || String(title).trim() === '' ? defaultTitle : String(title);
        const descripcion = message == null ? '' : String(message);
        const cuerpoEsHtml = /<[^>]+>/.test(descripcion);

        if (typeof window !== 'undefined' && window.Swal && typeof window.Swal.fire === 'function') {
            const config = {
                title: titulo,
                icon,
            };

            if (descripcion !== '') {
                config[cuerpoEsHtml ? 'html' : 'text'] = descripcion;
            }

            return window.Swal.fire(config);
        }

        if (typeof window !== 'undefined' && (titulo || descripcion)) {
            const salto = descripcion ? `\n${descripcion}` : '';
            window.alert(`${titulo}${salto}`);
        }

        return Promise.resolve();
    };

    const helpers = {
        successDefaults,
        aplicarDefaultsSuccess,
        fallbackAlert,
        mostrarSwalSuccess(title, message = '', extraOptions = {}) {
            return fallbackAlert(title ?? 'Listo', message ?? '', 'Listo', 'success');
        },
        mostrarSwalError(title, message = '', extraOptions = {}) {
            return fallbackAlert(title ?? 'Error', message ?? '', 'Error', 'error');
        },
    };

    const patchSwalFire = () => {
        if (typeof window === 'undefined' || typeof window.Swal === 'undefined' || typeof window.Swal.fire !== 'function') {
            return false;
        }

        const { Swal } = window;
        if (Swal.__agSuccessDefaultsPatched) {
            return true;
        }

        const originalSwalFire = Swal.fire.bind(Swal);

        const patchedSwalFire = function patchedSwalFire(...args) {
            if (args.length === 1 && typeof args[0] === 'object' && args[0] !== null) {
                const configuracion = helpers.aplicarDefaultsSuccess(args[0]);
                return originalSwalFire(configuracion);
            }

            if (args.length >= 3 && String(args[2]).toLowerCase() === 'success') {
                const [title, body = '', , extra = {}] = args;
                const cuerpo = body == null ? '' : String(body);
                const isHtml = /<[^>]+>/.test(cuerpo);
                const configBase = {
                    title: title ?? 'Listo',
                    icon: 'success',
                    ...(isHtml ? { html: cuerpo } : { text: cuerpo }),
                };
                const merged = helpers.aplicarDefaultsSuccess({
                    ...configBase,
                    ...(typeof extra === 'object' && extra !== null ? extra : {}),
                });
                return originalSwalFire(merged);
            }

            return originalSwalFire(...args);
        };

        Swal.fire = patchedSwalFire;
        Swal.__agSuccessDefaultsPatched = true;

        helpers.mostrarSwalSuccess = (title, message = '', extraOptions = {}) => {
            const cuerpo = message == null ? '' : String(message);
            const isHtml = /<[^>]+>/.test(cuerpo);
            const config = helpers.aplicarDefaultsSuccess({
                title: title ?? 'Listo',
                icon: 'success',
                ...(isHtml ? { html: cuerpo } : { text: cuerpo }),
                ...(typeof extraOptions === 'object' && extraOptions !== null ? extraOptions : {}),
            });
            return originalSwalFire(config);
        };

        helpers.mostrarSwalError = (title, message = '', extraOptions = {}) => {
            const cuerpo = message == null ? '' : String(message);
            const isHtml = /<[^>]+>/.test(cuerpo);
            const config = {
                title: title ?? 'Error',
                icon: 'error',
                ...(isHtml ? { html: cuerpo } : { text: cuerpo }),
                ...(typeof extraOptions === 'object' && extraOptions !== null ? extraOptions : {}),
            };
            return originalSwalFire(config);
        };

        return true;
    };

    if (typeof window !== 'undefined') {
        if (!patchSwalFire()) {
            const onceSetup = () => {
                patchSwalFire();
            };
            document.addEventListener('DOMContentLoaded', onceSetup, { once: true });
            window.addEventListener('load', onceSetup, { once: true });
        }
        window.agSwalHelpers = helpers;
    }

    return helpers;
})();

document.addEventListener('DOMContentLoaded', () => {
    const sweetAlertDisponible = typeof Swal !== 'undefined' && typeof Swal.fire === 'function';

    const swalHelpers = (typeof window !== 'undefined' && window.agSwalHelpers)
        ? window.agSwalHelpers
        : null;

    const fallbackSwalAlert = swalHelpers && typeof swalHelpers.fallbackAlert === 'function'
        ? swalHelpers.fallbackAlert
        : (title, message = '', defaultTitle = 'Aviso', icon = 'info') => {
            const titulo = title == null || String(title).trim() === '' ? defaultTitle : String(title);
            const descripcion = message == null ? '' : String(message);
            const cuerpoEsHtml = /<[^>]+>/.test(descripcion);

            if (sweetAlertDisponible) {
                const config = {
                    title: titulo,
                    icon,
                };
                if (descripcion !== '') {
                    config[cuerpoEsHtml ? 'html' : 'text'] = descripcion;
                }
                return Swal.fire(config);
            }

            if (titulo || descripcion) {
                const salto = descripcion ? `\n${descripcion}` : '';
                window.alert(`${titulo}${salto}`);
            }

            return Promise.resolve();
        };

    const aplicarDefaultsSuccess = swalHelpers && typeof swalHelpers.aplicarDefaultsSuccess === 'function'
        ? swalHelpers.aplicarDefaultsSuccess
        : (config) => config;

    let mostrarSwalSuccess = swalHelpers && typeof swalHelpers.mostrarSwalSuccess === 'function'
        ? swalHelpers.mostrarSwalSuccess
        : (title, message = '') => fallbackSwalAlert(title ?? 'Listo', message ?? '', 'Listo', 'success');

    let mostrarSwalError = swalHelpers && typeof swalHelpers.mostrarSwalError === 'function'
        ? swalHelpers.mostrarSwalError
        : (title, message) => fallbackSwalAlert(title ?? 'Error', message ?? '', 'Error', 'error');

    const mostrarConfirmacion = async (configuracion) => {
        if (sweetAlertDisponible) {
            return Swal.fire(configuracion);
        }

        const mensaje = configuracion.text || configuracion.title || '¿Confirmas la acción?';
        const confirmado = window.confirm(mensaje);
        return { isConfirmed: confirmado };
    };

    document.addEventListener('submit', (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form) {
            return;
        }

        const mensaje = form.getAttribute('data-ag-confirm');
        if (!mensaje) {
            return;
        }

        if (form.__agConfirmando) {
            form.__agConfirmando = false;
            return;
        }

        event.preventDefault();

        const titulo = form.getAttribute('data-ag-confirm-title') || '¿Confirmas la acción?';
        const icono = form.getAttribute('data-ag-confirm-icon') || 'warning';
        const textoAceptar = form.getAttribute('data-ag-confirm-accept') || 'Sí, continuar';
        const textoCancelar = form.getAttribute('data-ag-confirm-cancel') || 'Cancelar';

        mostrarConfirmacion({
            title: titulo,
            text: mensaje,
            icon: icono,
            showCancelButton: true,
            confirmButtonText: textoAceptar,
            cancelButtonText: textoCancelar,
        }).then((resultado) => {
            if (resultado && resultado.isConfirmed) {
                form.__agConfirmando = true;
                form.submit();
            }
        });
    }, true);

    const procesarRespuestaJson = async (response) => {
        const texto = await response.text();
        let payload = {};

        if (texto.trim() !== '') {
            try {
                payload = JSON.parse(texto);
            } catch (error) {
                throw new Error('Respuesta inválida del servidor.');
            }
        }

        const estado = (payload && typeof payload === 'object' && 'status' in payload)
            ? String(payload.status)
            : (response.ok ? 'ok' : 'error');

        if (!response.ok || estado !== 'ok') {
            const mensaje = (payload && typeof payload === 'object' && payload.message)
                ? String(payload.message)
                : 'No se pudo completar la solicitud.';
            const error = new Error(mensaje);
            error.payload = payload;
            error.status = response.status;
            throw error;
        }

        return payload;
    };

    window.agMostrarSwalSuccess = mostrarSwalSuccess;
    window.agMostrarSwalError = mostrarSwalError;
    window.agProcesarRespuestaJson = procesarRespuestaJson;
    window.agMostrarConfirmacion = mostrarConfirmacion;

    const ensureAjaxOverlay = (() => {
        let overlayRef = null;
        let activeRequests = 0;
        let listenersAttached = false;

        const toggle = () => {
            if (overlayRef) {
                overlayRef.classList.toggle('is-active', activeRequests > 0);
            }
        };

        const increment = () => {
            activeRequests += 1;
            toggle();
        };

        const decrement = () => {
            activeRequests = Math.max(0, activeRequests - 1);
            toggle();
        };

        return () => {
            if (!overlayRef) {
                overlayRef = document.querySelector('.ag-ajax-overlay');
                if (!overlayRef) {
                    overlayRef = document.createElement('div');
                    overlayRef.className = 'ag-ajax-overlay';
                    overlayRef.innerHTML = '<div class="ag-ajax-overlay__content" role="status" aria-live="polite"><div class="ag-ajax-spinner" aria-hidden="true"></div><span class="ag-ajax-spinner__label">Procesando</span></div>';
                    document.body.appendChild(overlayRef);
                }
            }

            if (!listenersAttached) {
                listenersAttached = true;
                document.addEventListener('ag:ajax:start', increment);
                document.addEventListener('ag:ajax:complete', decrement);
                document.addEventListener('ag:ajax:error', decrement);
            }
        };
    })();

    const initAnimatedElements = (() => {
        let observer = null;

        return () => {
            const elements = document.querySelectorAll('[data-ag-animate]:not([data-ag-animate-bound="1"])');
            if (!elements.length) {
                return;
            }

            if (typeof window.IntersectionObserver !== 'function') {
                elements.forEach((el) => {
                    el.classList.add('is-visible');
                    el.setAttribute('data-ag-animate-bound', '1');
                });
                return;
            }

            if (!observer) {
                observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting || entry.intersectionRatio > 0) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.2, rootMargin: '0px 0px -10%' });
            }

            elements.forEach((el) => {
                el.setAttribute('data-ag-animate-bound', '1');
                observer.observe(el);
            });
        };
    })();

    const initDashboardActionInteractions = () => {
        const actions = document.querySelectorAll('.small-box-action');
        actions.forEach((action) => {
            if (action.dataset.agHoverBound === '1') {
                return;
            }
            action.dataset.agHoverBound = '1';

            const updateCoords = (event) => {
                if (!event || typeof event.clientX !== 'number') {
                    return;
                }
                const rect = action.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;
                action.style.setProperty('--ag-action-x', `${x}px`);
                action.style.setProperty('--ag-action-y', `${y}px`);
                action.classList.add('is-active');
            };

            action.addEventListener('pointerenter', updateCoords);
            action.addEventListener('pointermove', updateCoords);
            action.addEventListener('pointerleave', () => {
                action.style.removeProperty('--ag-action-x');
                action.style.removeProperty('--ag-action-y');
                action.classList.remove('is-active');
            });
        });
    };

    ensureAjaxOverlay();
    initAnimatedElements();
    initDashboardActionInteractions();

    document.addEventListener('ag:content:updated', () => {
        initAnimatedElements();
        initDashboardActionInteractions();
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-ag-dashboard-action]');
        if (!trigger) {
            return;
        }

        const actionType = trigger.getAttribute('data-ag-dashboard-action');
        if (actionType !== 'modal') {
            return;
        }

        event.preventDefault();
        const targetSelector = trigger.getAttribute('data-ag-dashboard-target');
        if (!targetSelector) {
            return;
        }

        const modalElement = document.querySelector(targetSelector);
        if (!modalElement) {
            return;
        }

        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
            modalInstance.show();
        } else {
            modalElement.classList.add('show');
            modalElement.removeAttribute('aria-hidden');
        }
    });

    // Función para decodificar entidades HTML (para data-lotes)
    const decodeHtml = (html) => {
        return html
            .replace(/&quot;/g, '"')
            .replace(/&#039;/g, "'")
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&amp;/g, '&');
    };
    // Formateo numérico
    const formatNumber = (value) => {
        const num = parseFloat(value.toString().replace(/[^0-9.]/g, ''));
        if (isNaN(num)) return '';
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const cleanNumberString = (value) => {
        return value.toString().replace(/[^0-9.]/g, '');
    };

    function evitarAutocompletarPassword(input) {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        input.setAttribute('autocomplete', 'new-password');
        input.setAttribute('autocapitalize', 'off');
        input.setAttribute('autocorrect', 'off');
        input.setAttribute('spellcheck', 'false');
        input.setAttribute('data-lpignore', 'true');
        input.setAttribute('data-1p-ignore', 'true');

        if (input.type !== 'hidden') {
            input.value = '';
            if (!input.hasAttribute('readonly')) {
                input.setAttribute('readonly', 'readonly');
                input.addEventListener('focus', () => {
                    input.removeAttribute('readonly');
                }, { once: true });
            }
        }
    }

    const inicializarHints = () => {
        document.querySelectorAll('.ag-field-hint').forEach((hint) => {
            hint.classList.remove('ag-field-hint-visible');
            hint.setAttribute('aria-hidden', 'true');
        });
    };

    inicializarHints();

    const actualizarIconoPassword = (boton, visible) => {
        if (!boton) {
            return;
        }
        const icono = boton.querySelector('i, span');
        if (!icono) {
            return;
        }
        icono.classList.toggle('fa-eye', !visible);
        icono.classList.toggle('fa-eye-slash', visible);
    };

    const inicializarTogglesPassword = () => {
        const botones = document.querySelectorAll('[data-password-toggle]');
        botones.forEach((boton) => {
            if (boton.dataset.passwordToggleInicializado === '1') {
                return;
            }
            boton.dataset.passwordToggleInicializado = '1';

            const grupo = boton.closest('.password-toggle-group');
            const input = grupo ? grupo.querySelector('[data-password-input]') : null;
            if (!input) {
                return;
            }

            boton.addEventListener('click', () => {
                const esPassword = input.type === 'password';
                input.type = esPassword ? 'text' : 'password';
                boton.setAttribute('aria-pressed', esPassword ? 'true' : 'false');
                actualizarIconoPassword(boton, esPassword);
                if (esPassword) {
                    input.focus();
                    const longitud = input.value.length;
                    input.setSelectionRange(longitud, longitud);
                }
            });

            boton.setAttribute('aria-pressed', 'false');
            actualizarIconoPassword(boton, false);
        });
    };

    inicializarTogglesPassword();

    const passwordRequirementChecks = {
        length: {
            test: (value) => typeof value === 'string' && value.length >= 8,
        },
        uppercase: {
            test: (value) => /[A-ZÁÉÍÓÚÑ]/u.test(value || ''),
        },
        number: {
            test: (value) => /\d/.test(value || ''),
        },
        special: {
            test: (value) => /[^A-Za-z0-9]/u.test(value || ''),
        },
    };

    const initPasswordModules = () => {
        const modules = document.querySelectorAll('[data-password-module]');
        modules.forEach((module) => {
            if (module.dataset.passwordModuleInitialized === '1') {
                return;
            }
            module.dataset.passwordModuleInitialized = '1';

            const passwordField = module.querySelector('[data-password-strength]');
            const confirmField = module.querySelector('[data-password-confirm]');
            const requirementsContainer = module.querySelector('[data-password-requirements]');
            const requirementsItems = requirementsContainer
                ? Array.from(requirementsContainer.querySelectorAll('[data-requirement]'))
                : [];
            const alertBox = module.querySelector('[data-password-alert]');
            const alertText = module.querySelector('[data-password-alert-text]');
            const progressBar = module.querySelector('[data-password-meter-bar]');
            const strengthLabel = module.querySelector('[data-password-strength-label]');
            const defaultStrengthText = strengthLabel
                ? strengthLabel.textContent.trim()
                : 'Ingresa una contraseña para evaluar su seguridad.';
            const matchFeedback = module.querySelector('[data-password-match-feedback]');
            const requirementKeys = Object.keys(passwordRequirementChecks);
            const requirementCount = requirementsItems.length || requirementKeys.length || 1;

            const actualizarRequisitos = (value) => {
                let cumplidos = 0;

                if (requirementsItems.length === 0) {
                    requirementKeys.forEach((clave) => {
                        const verificador = passwordRequirementChecks[clave];
                        if (verificador && verificador.test(value)) {
                            cumplidos += 1;
                        }
                    });
                } else {
                    requirementsItems.forEach((item) => {
                        const clave = item.dataset.requirement;
                        const icono = item.querySelector('i');
                        const verificador = passwordRequirementChecks[clave];
                        const cumple = verificador ? verificador.test(value) : false;

                        item.classList.toggle('is-met', cumple);
                        if (icono) {
                            icono.className = `fas ${cumple ? 'fa-check-circle text-success' : 'fa-circle text-muted'}`;
                        }

                        if (cumple) {
                            cumplidos += 1;
                        }
                    });
                }

                if (progressBar) {
                    const porcentaje = requirementCount > 0 ? Math.round((cumplidos / requirementCount) * 100) : 0;
                    progressBar.style.width = `${porcentaje}%`;
                    progressBar.setAttribute('aria-valuenow', String(porcentaje));
                    progressBar.classList.remove('bg-danger', 'bg-warning', 'bg-info', 'bg-success', 'bg-secondary');

                    let claseBarra = 'bg-danger';
                    let textoNivel = 'Débil';

                    if (!value) {
                        claseBarra = 'bg-secondary';
                        textoNivel = defaultStrengthText || 'Ingresa una contraseña para evaluar su seguridad.';
                    } else if (porcentaje < 50) {
                        claseBarra = 'bg-danger';
                        textoNivel = 'Débil';
                    } else if (porcentaje < 75) {
                        claseBarra = 'bg-warning';
                        textoNivel = 'Aceptable';
                    } else if (porcentaje < 100) {
                        claseBarra = 'bg-info';
                        textoNivel = 'Fuerte';
                    } else {
                        claseBarra = 'bg-success';
                        textoNivel = 'Excelente';
                    }

                    progressBar.classList.add(claseBarra);
                    if (strengthLabel) {
                        strengthLabel.textContent = textoNivel;
                    }
                } else if (strengthLabel) {
                    strengthLabel.textContent = value ? strengthLabel.textContent : defaultStrengthText;
                }

                if (alertBox && alertText) {
                    if (!value || cumplidos >= requirementCount) {
                        alertBox.classList.add('d-none');
                    } else {
                        const pendiente = requirementsItems.find((item) => !item.classList.contains('is-met'));
                        const mensajePendiente = pendiente
                            ? (pendiente.dataset.requirementMessage || pendiente.textContent || '').trim()
                            : 'Revisa los requisitos de contraseña.';
                        alertText.textContent = mensajePendiente || 'Revisa los requisitos de contraseña.';
                        alertBox.classList.remove('d-none');
                    }
                }

                return cumplidos;
            };

            const actualizarCoincidencia = () => {
                if (!confirmField) {
                    return;
                }

                const valorPassword = passwordField ? passwordField.value : '';
                const valorConfirmacion = confirmField.value;

                if (!valorConfirmacion) {
                    confirmField.classList.remove('is-valid', 'is-invalid');
                    if (matchFeedback) {
                        matchFeedback.classList.add('d-none');
                    }
                    return;
                }

                const coincide = valorPassword === valorConfirmacion && valorPassword.length > 0;
                confirmField.classList.toggle('is-valid', coincide);
                confirmField.classList.toggle('is-invalid', !coincide);
                if (matchFeedback) {
                    matchFeedback.classList.toggle('d-none', coincide);
                }
            };

            const manejarCambioPassword = () => {
                const valor = passwordField ? passwordField.value : '';
                actualizarRequisitos(valor);
                actualizarCoincidencia();
            };

            if (passwordField) {
                passwordField.addEventListener('input', manejarCambioPassword);
                manejarCambioPassword();
            }

            if (confirmField) {
                confirmField.addEventListener('input', actualizarCoincidencia);
            }
        });
    };

    const initEmailUsernameSync = () => {
        document.querySelectorAll('[data-sync-email-to-username]').forEach((emailInput) => {
            if (emailInput.dataset.syncEmailInitialized === '1') {
                return;
            }
            emailInput.dataset.syncEmailInitialized = '1';

            const sincronizar = () => {
                const form = emailInput.form || emailInput.closest('form');
                if (!form) {
                    return;
                }
                const destino = form.querySelector('[data-email-username-target]');
                if (!destino) {
                    return;
                }
                const valor = emailInput.value.trim().toLowerCase();
                destino.value = valor;
            };

            emailInput.addEventListener('input', sincronizar);
            sincronizar();
        });
    };

    initEmailUsernameSync();
    initPasswordModules();

    document.addEventListener('shown.bs.modal', () => {
        inicializarTogglesPassword();
        initEmailUsernameSync();
        initPasswordModules();
        inicializarCamposMayusculas();
    });

    const mesesEnLetras = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];

    const fechaALetras = (valor) => {
        if (!valor) return '';
        const partes = valor.split('-');
        if (partes.length !== 3) return valor;
        const [anio, mes, dia] = partes;
        const mesNombre = mesesEnLetras[parseInt(mes, 10) - 1] || '';
        const diaNumero = parseInt(dia, 10);
        if (!mesNombre || Number.isNaN(diaNumero)) return valor;
        return `${diaNumero} DE ${mesNombre} DE ${anio}`;
    };

    const sincronizarFechaLarga = (inputSelector, hiddenSelector) => {
        const input = typeof inputSelector === 'string' ? document.getElementById(inputSelector) : inputSelector;
        const hidden = typeof hiddenSelector === 'string' ? document.getElementById(hiddenSelector) : hiddenSelector;
        if (!input || !hidden) return;

        const actualizar = () => {
            hidden.value = fechaALetras(input.value);
        };

        input.addEventListener('change', actualizar);
        input.addEventListener('input', actualizar);
        actualizar();
    };

    const crearContratoFeedbackBox = document.getElementById('crearContratoFeedback');
    const actualizarCrearContratoFeedback = (tipo = 'info', mensaje = '', detalle = '') => {
        if (!crearContratoFeedbackBox) {
            if (tipo === 'error') {
                console.error(mensaje, detalle);
            } else {
                console.info(mensaje, detalle);
            }
            return;
        }

        crearContratoFeedbackBox.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-danger', 'alert-warning');

        const clase = tipo === 'error'
            ? 'alert-danger'
            : tipo === 'success'
                ? 'alert-success'
                : tipo === 'warning'
                    ? 'alert-warning'
                    : 'alert-info';

        crearContratoFeedbackBox.classList.add('alert', clase);
        const contenido = detalle ? `${mensaje}\n${detalle}` : mensaje;
        crearContratoFeedbackBox.textContent = contenido;
    };

    const esCampoInteractivo = (field) => {
        if (!field || !['INPUT', 'TEXTAREA', 'SELECT'].includes(field.tagName)) {
            return false;
        }
        if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
            return false;
        }
        if (field.disabled || field.hasAttribute('data-ignore-requirement')) {
            return false;
        }
        return true;
    };

    const obtenerEtiquetaCampo = (field) => {
        if (!field) return 'Campo';
        const form = field.form || field.closest('form');
        let label = null;
        if (field.id && form) {
            label = form.querySelector(`label[for="${field.id}"]`);
        }
        if (!label) {
            const contenedor = field.closest('.form-group, .col-md-12, .col-md-9, .col-md-6, .col-md-4, .col-md-3, .mb-3, .col-12');
            if (contenedor) {
                label = contenedor.querySelector('label');
            }
        }
        const texto = label ? label.textContent.trim() : (field.placeholder || field.name || field.id || 'Campo');
        return texto.replace(/[:*]/g, '').trim();
    };

    const obtenerHintsAsociados = (field) => {
        if (!field) return [];
        const describedBy = field.getAttribute('aria-describedby');
        if (!describedBy) return [];
        return describedBy
            .split(/\s+/)
            .map((id) => document.getElementById(id))
            .filter((hint) => hint && hint.classList && hint.classList.contains('ag-field-hint'));
    };

    const debeMostrarHint = (field, visible) => {
        if (!visible || !field) {
            return false;
        }

        if (field.type === 'hidden' || field.type === 'button' || field.type === 'submit') {
            return false;
        }

        const esRequerido = field.required || field.getAttribute('aria-required') === 'true';
        if (!esRequerido) {
            return false;
        }

        if (field.type === 'checkbox' || field.type === 'radio') {
            return !field.checked;
        }

        const valor = typeof field.value === 'string' ? field.value.trim() : field.value;
        const tieneValor = valor !== '' && valor !== null && valor !== undefined;

        if (field.validity) {
            if (field.validity.valueMissing) {
                return true;
            }
            if (!field.validity.valid) {
                return true;
            }
        }

        if (field.tagName === 'SELECT') {
            return !tieneValor || field.selectedIndex === -1;
        }

        return !tieneValor;
    };

    const actualizarHintCampo = (field, visible) => {
        const hints = obtenerHintsAsociados(field);
        if (hints.length === 0) {
            return;
        }

        const mostrarHint = debeMostrarHint(field, visible);
        hints.forEach((hint) => {
            hint.classList.toggle('ag-field-hint-visible', mostrarHint);
            hint.setAttribute('aria-hidden', mostrarHint ? 'false' : 'true');
        });
    };

    const registrarLimpiezaCampos = (form) => {
        if (!form) return;
        form.querySelectorAll('input, select, textarea').forEach((field) => {
            actualizarHintCampo(field, false);
            ['input', 'change'].forEach(evento => {
                field.addEventListener(evento, () => {
                    const esValido = field.checkValidity();
                    if (esValido) {
                        field.classList.remove('is-invalid');
                    }
                    actualizarHintCampo(field, !esValido && esCampoInteractivo(field));
                });
            });
        });
    };

    const obtenerCamposInvalidos = (form) => {
        const invalidos = [];
        if (!form) return invalidos;
        Array.from(form.elements).forEach((field) => {
            if (!esCampoInteractivo(field)) {
                return;
            }
            if (!field.checkValidity()) {
                invalidos.push(field);
                field.classList.add('is-invalid');
                actualizarHintCampo(field, true);
            } else {
                field.classList.remove('is-invalid');
                actualizarHintCampo(field, false);
            }
        });
        return invalidos;
    };

    const mostrarSwalRequisitos = (campos, titulo = 'Completa la información requerida') => {
        if (!Array.isArray(campos) || campos.length === 0 || typeof Swal === 'undefined') {
            return;
        }
        const items = campos.map((field) => {
            const requisito = field.dataset.requirement || field.title || field.placeholder || 'Revisa el formato requerido.';
            const etiqueta = obtenerEtiquetaCampo(field);
            return `<li>Campo <strong>${etiqueta}</strong> faltante. ${requisito}</li>`;
        }).join('');

        Swal.fire({
            icon: 'warning',
            title: titulo,
            html: `<ul class="text-start mb-0">${items}</ul>`,
            confirmButtonText: 'Entendido'
        });
    };

    const aplicarRequisitosCampos = (form, definiciones) => {
        if (!form || !definiciones) {
            return;
        }
        Object.entries(definiciones).forEach(([clave, config]) => {
            const opciones = typeof config === 'string' ? { text: config } : (config || {});
            const selector = opciones.selector || `[name="${clave}"]`;
            const field = form.querySelector(selector);
            if (!field) {
                return;
            }
            const texto = opciones.text || field.dataset.requirement || '';
            if (texto) {
                field.dataset.requirement = texto;
            }
            if (opciones.aria !== false) {
                const hintId = opciones.hintId || `${field.id || clave}HintAuto`;
                if (!field.getAttribute('aria-describedby')) {
                    field.setAttribute('aria-describedby', hintId);
                }
                if (opciones.addHint !== false && !form.querySelector(`#${hintId}`)) {
                    const hintEl = document.createElement('div');
                    hintEl.className = opciones.hintClass || 'form-text ag-field-hint';
                    hintEl.id = hintId;
                    hintEl.textContent = texto;
                    hintEl.setAttribute('aria-hidden', 'true');
                    const inputGroup = field.closest('.input-group');
                    if (inputGroup && !opciones.forceAfterField) {
                        inputGroup.insertAdjacentElement('afterend', hintEl);
                    } else if (opciones.hintContainerSelector) {
                        const cont = field.closest(opciones.hintContainerSelector);
                        if (cont) {
                            cont.appendChild(hintEl);
                        } else {
                            field.insertAdjacentElement(opciones.insertPosition || 'afterend', hintEl);
                        }
                    } else {
                        field.insertAdjacentElement(opciones.insertPosition || 'afterend', hintEl);
                    }
                }
            }
        });
    };

    const camposSolicitudOrden = [
        { clave: 'estado', etiqueta: 'Estado' },
        { clave: 'folio', etiqueta: 'Folio' },
        { clave: 'fecha', etiqueta: 'Fecha' },
        { clave: 'fecha_firma', etiqueta: 'Fecha de firma' },
        { clave: 'nombre_completo', etiqueta: 'Nombre completo' },
        { clave: 'fecha_nacimiento', etiqueta: 'Fecha de nacimiento' },
        { clave: 'edad_actual', etiqueta: 'Edad actual' },
        { clave: 'identificacion', etiqueta: 'Identificación' },
        { clave: 'identificacion_numero', etiqueta: 'Número identificación' },
        { clave: 'idmex', etiqueta: 'IDMEX' },
        { clave: 'curp', etiqueta: 'CURP' },
        { clave: 'rfc', etiqueta: 'RFC' },
        { clave: 'celular', etiqueta: 'Celular' },
        { clave: 'telefono', etiqueta: 'Teléfono' },
        { clave: 'email', etiqueta: 'Correo' },
        { clave: 'domicilio', etiqueta: 'Domicilio' },
        { clave: 'estado_civil', etiqueta: 'Estado civil' },
        { clave: 'regimen', etiqueta: 'Régimen' },
        { clave: 'ocupacion', etiqueta: 'Ocupación' },
        { clave: 'empresa', etiqueta: 'Empresa' },
        { clave: 'testigo_contrato', etiqueta: 'Testigo de firma' },
        { clave: 'celular_testigo_contrato', etiqueta: 'Celular testigo' },
        { clave: 'nombre_referencia_1', etiqueta: 'Nombre referencia 1' },
        { clave: 'celular_referencia_1', etiqueta: 'Celular referencia 1' },
        { clave: 'nombre_referencia_2', etiqueta: 'Nombre referencia 2' },
        { clave: 'celular_referencia_2', etiqueta: 'Celular referencia 2' },
        { clave: 'beneficiario', etiqueta: 'Beneficiario' },
        { clave: 'edad_beneficiario', etiqueta: 'Edad beneficiario' },
        { clave: 'parentesco_beneficiario', etiqueta: 'Parentesco beneficiario' },
        { clave: 'celular_beneficiario', etiqueta: 'Celular beneficiario' },
        { clave: 'albacea_activo', etiqueta: '¿Cuenta con albacea?' },
        { clave: 'albacea_nombre', etiqueta: 'Nombre del albacea' },
        { clave: 'albacea_edad', etiqueta: 'Edad del albacea' },
        { clave: 'albacea_parentesco', etiqueta: 'Parentesco del albacea' },
        { clave: 'albacea_celular', etiqueta: 'Celular del albacea' },
        { clave: 'desarrollo', etiqueta: 'Desarrollo' },
        { clave: 'ubicacion', etiqueta: 'Ubicación' },
        { clave: 'lote_manzana', etiqueta: 'Lote y manzana' },
        { clave: 'deslinde', etiqueta: 'Deslinde' },
        { clave: 'superficie', etiqueta: 'Superficie' },
        { clave: 'costo_total', etiqueta: 'Costo total' },
        { clave: 'enganche', etiqueta: 'Enganche' },
        { clave: 'saldo', etiqueta: 'Saldo' },
        { clave: 'plazo_mensualidades', etiqueta: 'Plazo mensualidades' },
        { clave: 'apartado', etiqueta: 'Apartado' },
        { clave: 'complemento_enganche', etiqueta: 'Complemento de enganche' },
        { clave: 'fecha_liquidacion_enganche', etiqueta: 'Fecha liquidación enganche' },
        { clave: 'pago_mensual', etiqueta: 'Pago mensual' },
        { clave: 'fecha_pago_mensual', etiqueta: 'Fecha pago mensual' },
        { clave: 'usa_pago_anual', etiqueta: '¿Pago anual?' },
        { clave: 'pago_anual', etiqueta: 'Pago anual' },
        { clave: 'fecha_pago_anual', etiqueta: 'Fecha pago anual' },
        { clave: 'plazo_anual', etiqueta: 'Plazo anual (años)' },
        { clave: 'created_at', etiqueta: 'Registrada' },
        { clave: 'updated_at', etiqueta: 'Actualizada' }
    ];

    const escapeHtml = (valor) => {
        if (valor === null || valor === undefined) {
            return '';
        }
        return String(valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    const etiquetasSolicitud = new Map();
    camposSolicitudOrden.forEach(item => etiquetasSolicitud.set(item.clave, item.etiqueta));
    etiquetasSolicitud.set('nacionalidad', 'Nacionalidad');
    etiquetasSolicitud.set('nacionalidad_identificador', 'Identificador nacionalidad');
    etiquetasSolicitud.set('propietario', 'Propietario');
    etiquetasSolicitud.set('contrato_id', 'Contrato vinculado');
    etiquetasSolicitud.set('contrato_folio', 'Folio del contrato');
    etiquetasSolicitud.set('contrato_estado', 'Estado del contrato');
    etiquetasSolicitud.set('desarrollo_tipo_contrato', 'Tipo de contrato del desarrollo');
    etiquetasSolicitud.set('desarrollo_id', 'ID del desarrollo');

    const seccionesDetalleSolicitud = [
        { titulo: 'Resumen', campos: ['propietario', 'estado', 'folio', 'fecha', 'fecha_firma', 'created_at', 'updated_at', 'contrato_id', 'contrato_folio', 'contrato_estado'] },
        { titulo: 'Datos del solicitante', campos: ['nombre_completo', 'nacionalidad', 'nacionalidad_identificador', 'fecha_nacimiento', 'edad_actual', 'estado_civil', 'regimen', 'ocupacion', 'empresa'] },
        { titulo: 'Identificación y contacto', campos: ['identificacion', 'identificacion_numero', 'idmex', 'curp', 'rfc', 'celular', 'telefono', 'email', 'domicilio'] },
        { titulo: 'Referencias y beneficiarios', campos: ['testigo_contrato', 'celular_testigo_contrato', 'nombre_referencia_1', 'celular_referencia_1', 'nombre_referencia_2', 'celular_referencia_2', 'beneficiario', 'edad_beneficiario', 'parentesco_beneficiario', 'celular_beneficiario'] },
        { titulo: 'Albacea', campos: ['albacea_activo', 'albacea_nombre', 'albacea_edad', 'albacea_parentesco', 'albacea_celular'] },
        { titulo: 'Proyecto y ubicación', campos: ['desarrollo', 'desarrollo_tipo_contrato', 'desarrollo_id', 'ubicacion', 'lote_manzana', 'deslinde', 'superficie'] },
        { titulo: 'Información financiera', campos: ['costo_total', 'enganche', 'complemento_enganche', 'apartado', 'saldo', 'plazo_mensualidades', 'fecha_liquidacion_enganche', 'pago_mensual', 'fecha_pago_mensual', 'usa_pago_anual', 'pago_anual', 'fecha_pago_anual', 'plazo_anual'] }
    ];

    const formSolicitud = document.getElementById('formSolicitud');
    if (formSolicitud) {
        const requisitosSolicitud = {
            folio: 'Identificador interno de hasta 50 caracteres. Usa letras y números.',
            fecha: 'Fecha de captura en formato DD-MM-AAAA.',
            fecha_firma: 'Fecha comprometida para firmar la solicitud (DD-MM-AAAA).',
            nombre_completo: 'Nombre completo del solicitante en mayúsculas.',
            nacionalidad_id: 'Selecciona la nacionalidad registrada del solicitante.',
            fecha_nacimiento: 'Fecha de nacimiento en formato DD-MM-AAAA.',
            edad_actual: 'Edad en años completos (entre 18 y 120).',
            identificacion: 'Tipo de identificación presentada (ej. INE, PASAPORTE).',
            identificacion_numero: 'Número de la identificación sin espacios.',
            idmex: 'IDMEX de la credencial (13 caracteres).',
            curp: 'CURP oficial en mayúsculas (18 caracteres).',
            rfc: 'RFC en mayúsculas (12 o 13 caracteres).',
            celular: 'Número celular de 10 a 15 dígitos (puede incluir +).',
            telefono: 'Teléfono de contacto de 10 a 15 dígitos.',
            email: 'Correo electrónico válido para notificaciones.',
            domicilio: 'Domicilio completo: calle, número, colonia y ciudad.',
            estado_civil: 'Estado civil actual del solicitante.',
            regimen: 'Régimen matrimonial correspondiente (opcional).',
            ocupacion: 'Ocupación o actividad económica principal.',
            empresa: 'Empresa o lugar de trabajo (opcional).',
            testigo_contrato: 'Nombre completo del testigo de firma.',
            celular_testigo_contrato: 'Celular del testigo (10 a 15 dígitos).',
            nombre_referencia_1: 'Nombre completo de la referencia personal 1.',
            celular_referencia_1: 'Celular de la referencia 1 (10 a 15 dígitos).',
            nombre_referencia_2: 'Nombre completo de la referencia personal 2.',
            celular_referencia_2: 'Celular de la referencia 2 (10 a 15 dígitos).',
            beneficiario: 'Nombre completo del beneficiario en mayúsculas.',
            edad_beneficiario: 'Edad del beneficiario (0 a 120).',
            parentesco_beneficiario: 'Parentesco del beneficiario con el solicitante.',
            celular_beneficiario: 'Celular del beneficiario (10 a 15 dígitos).',
            albacea_nombre: { text: 'Nombre del albacea si aplica.', selector: '[name="albacea_nombre"]' },
            albacea_edad: { text: 'Edad del albacea (18 a 120).', selector: '[name="albacea_edad"]' },
            albacea_parentesco: { text: 'Parentesco del albacea con el solicitante.', selector: '[name="albacea_parentesco"]' },
            albacea_celular: { text: 'Celular del albacea (10 a 15 dígitos).', selector: '[name="albacea_celular"]' },
            desarrollo_id: { text: 'Selecciona el desarrollo asociado a la solicitud.', selector: '[name="desarrollo_id"]' },
            ubicacion: 'Ubicación del lote dentro del desarrollo.',
            lote_manzana: 'Clave de lote y manzana asignados.',
            deslinde: 'Describe el deslinde del predio (opcional).',
            superficie: 'Superficie del lote en metros cuadrados (puede incluir decimales).',
            costo_total: 'Costo total del lote en pesos con dos decimales.',
            enganche: 'Monto del enganche en pesos con dos decimales.',
            saldo: 'Saldo restante en pesos con dos decimales.',
            plazo_mensualidades: 'Número total de mensualidades (1 a 360).',
            apartado: 'Monto del apartado en pesos.',
            complemento_enganche: 'Monto del complemento del enganche en pesos.',
            fecha_liquidacion_enganche: 'Fecha límite para liquidar el enganche (DD-MM-AAAA).',
            pago_mensual: 'Monto del pago mensual en pesos.',
            fecha_pago_mensual: 'Fecha programada del pago mensual (DD-MM-AAAA).',
            pago_anual: 'Monto del pago anual en pesos (obligatorio si se activa la opción de pago anual).',
            fecha_pago_anual: 'Fecha del pago anual (DD-MM-AAAA, obligatoria si se activa el pago anual).',
            plazo_anual: 'Plazo anual en años (0 a 50, obligatorio si se activa el pago anual).'
        };

        aplicarRequisitosCampos(formSolicitud, requisitosSolicitud);
        registrarLimpiezaCampos(formSolicitud);
        formSolicitud.setAttribute('novalidate', true);
        formSolicitud.addEventListener('submit', (event) => {
            const invalidos = obtenerCamposInvalidos(formSolicitud);
            if (invalidos.length) {
                event.preventDefault();
                mostrarSwalRequisitos(invalidos, 'Completa la información de la solicitud');
                invalidos[0].focus();
            }
        });
    }

    const renderDetalleSolicitud = (contenedor, datos) => {
        if (!contenedor || !datos) {
            return;
        }
        contenedor.innerHTML = '';
        const obtenerDato = (clave) => {
            if (Object.prototype.hasOwnProperty.call(datos, clave)) {
                return datos[clave];
            }
            const alterno = `solicitud_datta_${clave}`;
            if (Object.prototype.hasOwnProperty.call(datos, alterno)) {
                return datos[alterno];
            }
            return null;
        };

        const propietario = datos.nombre_corto || datos.username || '';
        const contratoId = Number(datos.contrato_id || 0);
        const contratoFolio = datos.contrato_folio || '';
        const contratoEstado = (datos.contrato_estado || '').toString();
        const contratoEstadoTexto = contratoEstado ? contratoEstado.replace(/_/g, ' ').toUpperCase() : '';

        const normalizarBooleano = (valor) => (valor && valor !== '0' && valor !== 'false' ? 'Sí' : 'No');

        const formatearValor = (clave) => {
            switch (clave) {
                case 'propietario':
                    return propietario || '';
                case 'estado': {
                    const estado = obtenerDato('estado');
                    return estado ? estado.toString().replace(/_/g, ' ').toUpperCase() : '';
                }
                case 'albacea_activo':
                case 'usa_pago_anual':
                    return normalizarBooleano(obtenerDato(clave));
                case 'contrato_id':
                    return contratoId > 0 ? `#${contratoId}` : '';
                case 'contrato_folio':
                    return contratoId > 0 ? (contratoFolio || '') : '';
                case 'contrato_estado':
                    return contratoId > 0 ? contratoEstadoTexto : '';
                default: {
                    const valor = obtenerDato(clave);
                    if (valor === null || valor === undefined) {
                        return '';
                    }
                    return String(valor);
                }
            }
        };

        let html = '';
        seccionesDetalleSolicitud.forEach((seccion) => {
            const filas = [];
            seccion.campos.forEach((clave) => {
                const etiqueta = etiquetasSolicitud.get(clave);
                if (!etiqueta) {
                    return;
                }
                const valor = formatearValor(clave);
                if (valor === null) {
                    return;
                }
                const contenido = valor === '' ? '—' : valor;
                filas.push(`<dt class="col-sm-4">${escapeHtml(etiqueta)}</dt><dd class="col-sm-8">${escapeHtml(contenido)}</dd>`);
            });
            if (filas.length) {
                html += `<div class="mb-3"><h6 class="fw-semibold">${escapeHtml(seccion.titulo)}</h6><dl class="row">${filas.join('')}</dl></div>`;
            }
        });

        if (html === '') {
            html = '<p class="text-muted mb-0">No hay información disponible.</p>';
        }

        contenedor.innerHTML = html;
    };

    // Manejar formularios dinámicamente
    // Nuevo cliente
    const formCliente = document.getElementById('formCliente');
    if (formCliente) {
        formCliente.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(formCliente);
            // Usar la acción definida en el formulario para enviar la petición
            const url = formCliente.getAttribute('action');
            fetch(url, {
                method: 'POST',
                body: formData
            }).then(r => r.text()).then(resp => {
                // Mostrar mensaje de éxito o error dependiendo de la respuesta
                let title = 'Guardado';
                let text = 'Cliente registrado correctamente.';
                let icon = 'success';
                let shouldReload = true;
                const respuesta = (resp || '').toString().trim();
                if (respuesta.includes('duplicado_rfc')) {
                    title = 'RFC duplicado';
                    text = 'Ya existe un cliente registrado con el RFC proporcionado.';
                    icon = 'warning';
                    shouldReload = false;
                } else if (respuesta.includes('error_rfc')) {
                    title = 'RFC requerido';
                    text = 'Capture un RFC válido para continuar.';
                    icon = 'warning';
                    shouldReload = false;
                } else if (respuesta.includes('error')) {
                    title = 'Error';
                    text = 'No se pudo guardar.';
                    icon = 'error';
                }
                Swal.fire(title, text, icon).then(() => {
                    if (shouldReload) {
                        window.location.reload();
                    }
                });
            }).catch(() => {
                Swal.fire('Error', 'No se pudo guardar.', 'error');
            });
        });
    }

    const formCrearUsuario = document.getElementById('formCrearUsuario');
    if (formCrearUsuario) {
        const correoInput = formCrearUsuario.querySelector('input[name="nuevoEmail"]');
        const usuarioInput = formCrearUsuario.querySelector('input[name="nuevoUsuario"]');

        if (correoInput && usuarioInput) {
            let usuarioAutogenerado = usuarioInput.value.trim() === '';
            const sincronizarUsuario = () => {
                if (!usuarioAutogenerado) {
                    return;
                }
                usuarioInput.value = correoInput.value.trim();
            };

            correoInput.addEventListener('input', sincronizarUsuario);
            correoInput.addEventListener('change', sincronizarUsuario);

            usuarioInput.addEventListener('input', () => {
                const correoActual = correoInput.value.trim();
                const usuarioActual = usuarioInput.value.trim();
                if (usuarioActual === '' || usuarioActual === correoActual) {
                    usuarioAutogenerado = true;
                } else {
                    usuarioAutogenerado = false;
                }
            });

            sincronizarUsuario();
        }

        formCrearUsuario.addEventListener('submit', (event) => {
            const passInput = formCrearUsuario.querySelector('input[name="nuevoPassword"]');
            const confirmInput = formCrearUsuario.querySelector('input[name="repetirPassword"]');

            const pass = passInput ? passInput.value : '';
            const confirm = confirmInput ? confirmInput.value : '';

            const regexPassword = /(?=.*[A-ZÁÉÍÓÚÑ])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}/;

            if (pass !== confirm) {
                event.preventDefault();
                Swal.fire('Contraseñas distintas', 'Las contraseñas deben coincidir.', 'warning');
                return;
            }

            if (!regexPassword.test(pass)) {
                event.preventDefault();
                Swal.fire('Contraseña inválida', 'Incluya una mayúscula, un número y un carácter especial con mínimo 8 caracteres.', 'warning');
                passInput?.focus();
                return;
            }

            if (correoInput && correoInput.value.trim() === '') {
                event.preventDefault();
                Swal.fire('Correo requerido', 'Capture un correo electrónico válido.', 'warning');
                correoInput.focus();
            }
        });
    }

    const formCambioPassword = document.getElementById('formCambioPassword');
    if (formCambioPassword) {
        formCambioPassword.addEventListener('submit', (event) => {
            const passActual = formCambioPassword.querySelector('input[name="password_actual"]');
            const passNuevo = formCambioPassword.querySelector('input[name="password_nuevo"]');
            const passConfirmar = formCambioPassword.querySelector('input[name="password_confirmar"]');

            const nuevoValor = passNuevo ? passNuevo.value : '';
            const confirmarValor = passConfirmar ? passConfirmar.value : '';

            const regexPassword = /(?=.*[A-ZÁÉÍÓÚÑ])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}/;

            if (!passActual || passActual.value.trim() === '') {
                event.preventDefault();
                Swal.fire('Contraseña requerida', 'Ingrese su contraseña actual.', 'warning');
                passActual?.focus();
                return;
            }

            if (nuevoValor !== confirmarValor) {
                event.preventDefault();
                Swal.fire('Contraseñas distintas', 'La confirmación no coincide con la nueva contraseña.', 'warning');
                passConfirmar?.focus();
                return;
            }

            if (!regexPassword.test(nuevoValor)) {
                event.preventDefault();
                Swal.fire('Contraseña inválida', 'Incluya una mayúscula, un número y un carácter especial con mínimo 8 caracteres.', 'warning');
                passNuevo?.focus();
            }
        });
    }

    // Nuevo desarrollo
    const formDesarrollo = document.getElementById('formDesarrollo');
    if (formDesarrollo) {
        formDesarrollo.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(formDesarrollo);
            const url = formDesarrollo.getAttribute('action') || window.location.href;
            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            })
                .then(procesarRespuestaJson)
                .then((data) => {
                    const mensaje = (data && typeof data === 'object' && data.message)
                        ? String(data.message)
                        : 'Desarrollo registrado correctamente.';
                    mostrarSwalSuccess('Guardado', mensaje).then(() => {
                        window.location.reload();
                    });
                })
                .catch((error) => {
                    const mensaje = error && error.message ? error.message : 'No se pudo guardar.';
                    mostrarSwalError('Error', mensaje);
                });
        });
    }

    // Manejar lotes dinámicos en la creación de desarrollos
    const inputLoteNuevo = document.getElementById('inputLoteNuevo');
    const contenedorLotesNuevo = document.getElementById('contenedorLotesNuevo');
    const inputHiddenLotesNuevo = document.getElementById('lotesDisponiblesNuevo');
    let lotesNuevo = [];
    if (inputLoteNuevo && contenedorLotesNuevo && inputHiddenLotesNuevo) {
        // Inicializar campo oculto con un arreglo vacío
        inputHiddenLotesNuevo.value = JSON.stringify(lotesNuevo);
        // Función para renderizar las etiquetas de lotes y actualizar el valor oculto
        const renderLotesNuevo = () => {
            contenedorLotesNuevo.innerHTML = '';
            lotesNuevo.forEach((lote, idx) => {
                const badge = document.createElement('span');
                // Estilos inline para asegurar el formato de pill
                badge.style.display = 'inline-flex';
                badge.style.alignItems = 'center';
                badge.style.borderRadius = '12px';
                badge.style.backgroundColor = '#f0f2f5';
                badge.style.color = '#333';
                badge.style.padding = '4px 8px';
                badge.style.margin = '2px';
                badge.style.fontSize = '0.8rem';
                badge.textContent = lote;
                // botón (x) para eliminar
                const removeSpan = document.createElement('span');
                removeSpan.style.marginLeft = '6px';
                removeSpan.style.color = '#dc3545';
                removeSpan.style.cursor = 'pointer';
                removeSpan.textContent = '×';
                removeSpan.addEventListener('click', () => {
                    lotesNuevo.splice(idx, 1);
                    renderLotesNuevo();
                });
                badge.appendChild(removeSpan);
                contenedorLotesNuevo.appendChild(badge);
            });
            inputHiddenLotesNuevo.value = JSON.stringify(lotesNuevo);
        };
        // Agregar lote cuando el usuario presiona Enter
        inputLoteNuevo.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const valor = this.value.trim();
                if (valor && !lotesNuevo.includes(valor)) {
                    lotesNuevo.push(valor);
                    renderLotesNuevo();
                }
                this.value = '';
            }
        });
    }

    // Editar desarrollo
    const modalEditar = document.getElementById('modalEditarDesarrollo');
    const formEditarDesarrollo = document.getElementById('formEditarDesarrollo');
    if (modalEditar && formEditarDesarrollo) {
        const rellenarFormularioEdicion = (trigger) => {
            if (!trigger) {
                return;
            }

            const id = trigger.getAttribute('data-id');
            const nombre = trigger.getAttribute('data-nombre');
            const tipocontratoId = trigger.getAttribute('data-tipocontrato-id');
            const descripcion = trigger.getAttribute('data-descripcion');
            const superficie = trigger.getAttribute('data-superficie');
            const clave = trigger.getAttribute('data-clave');
            let lotesStr = trigger.getAttribute('data-lotes') || '[]';
            // Decodificar entidades HTML para obtener JSON válido
            lotesStr = decodeHtml(lotesStr);
            const precioLote = trigger.getAttribute('data-preciolote');
            const precioTotal = trigger.getAttribute('data-preciototal');

            const inputId = document.getElementById('editarIdDesarrollo');
            const inputNombre = document.getElementById('editarNombreDesarrollo');
            const selectTipoContrato = document.getElementById('editarTipoContrato');
            const inputDescripcion = document.getElementById('editarDescripcion');
            const inputSuperficie = document.getElementById('editarSuperficie');
            const inputClave = document.getElementById('editarClaveCatastral');
            const inputPrecioLote = document.getElementById('editarPrecioLote');
            const inputPrecioTotal = document.getElementById('editarPrecioTotal');

            if (inputId) inputId.value = id || '';
            if (inputNombre) inputNombre.value = nombre || '';
            if (selectTipoContrato) {
                selectTipoContrato.value = tipocontratoId || '';
            }
            if (inputDescripcion) inputDescripcion.value = descripcion || '';
            if (inputSuperficie) inputSuperficie.value = superficie || '';
            if (inputClave) inputClave.value = clave || '';
            if (inputPrecioLote) inputPrecioLote.value = precioLote || '';
            if (inputPrecioTotal) inputPrecioTotal.value = precioTotal || '';

            // Parsear lotes existentes (JSON) y mostrarlos como etiquetas
            lotesEditar = [];
            try {
                const arr = JSON.parse(lotesStr);
                if (Array.isArray(arr)) {
                    lotesEditar = arr;
                }
            } catch (err) {
                if (lotesStr) {
                    lotesEditar = lotesStr.split(',').map((l) => l.trim()).filter(Boolean);
                }
            }
            renderLotesEditar();
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('.btnEditarDesarrollo');
            if (!trigger) {
                return;
            }
            rellenarFormularioEdicion(trigger);
        });
        // Enviar formulario de edición via fetch
        formEditarDesarrollo.addEventListener('submit', function (e) {
            e.preventDefault();
            mostrarConfirmacion({
                title: '¿Estás seguro de modificar los datos?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, modificar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (!result || !result.isConfirmed) {
                    return;
                }

                const formData = new FormData(formEditarDesarrollo);
                const url = formEditarDesarrollo.getAttribute('action') || window.location.href;
                fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                })
                    .then(procesarRespuestaJson)
                    .then((data) => {
                        const mensaje = (data && typeof data === 'object' && data.message)
                            ? String(data.message)
                            : 'Desarrollo actualizado correctamente.';
                        mostrarSwalSuccess('Guardado', mensaje).then(() => {
                            window.location.reload();
                        });
                    })
                    .catch((error) => {
                        const mensaje = error && error.message ? error.message : 'No se pudo actualizar.';
                        mostrarSwalError('Error', mensaje);
                    });
            });
        });
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-form-eliminar-desarrollo')) {
            return;
        }

        event.preventDefault();

        const nombre = (form.getAttribute('data-nombre') || '').trim();
        const textoConfirmacion = nombre
            ? `Se eliminará el desarrollo "${nombre}". Esta acción no se puede deshacer.`
            : 'Se eliminará el desarrollo seleccionado. Esta acción no se puede deshacer.';

        mostrarConfirmacion({
            title: 'Eliminar desarrollo',
            text: textoConfirmacion,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        }).then((result) => {
            if (!result || !result.isConfirmed) {
                return;
            }

            const formData = new FormData(form);
            const url = form.getAttribute('action') || window.location.href;
            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            })
                .then(procesarRespuestaJson)
                .then((data) => {
                    const mensaje = (data && typeof data === 'object' && data.message)
                        ? String(data.message)
                        : 'El desarrollo se eliminó correctamente.';
                    mostrarSwalSuccess('Desarrollo eliminado', mensaje).then(() => {
                        window.location.reload();
                    });
                })
                .catch((error) => {
                    const mensaje = error && error.message ? error.message : 'No se pudo eliminar el desarrollo.';
                    mostrarSwalError('Error', mensaje);
                });
        });
    });

    // Manejar lotes dinámicos en la edición de desarrollos
    const inputLoteEditar = document.getElementById('inputLoteEditar');
    const contenedorLotesEditar = document.getElementById('contenedorLotesEditar');
    const inputHiddenLotesEditar = document.getElementById('lotesDisponiblesEditar');
    var lotesEditar = [];
    // Si el campo oculto para lotes en edición existe, inicializarlo como arreglo vacío
    if (inputHiddenLotesEditar) {
        inputHiddenLotesEditar.value = JSON.stringify(lotesEditar);
    }
    // Función para renderizar los lotes en edición y actualizar el valor oculto
    function renderLotesEditar() {
        if (!contenedorLotesEditar) return;
        contenedorLotesEditar.innerHTML = '';
        lotesEditar.forEach((lote, idx) => {
            const badge = document.createElement('span');
            // Estilos inline para asegurar el formato de pill
            badge.style.display = 'inline-flex';
            badge.style.alignItems = 'center';
            badge.style.borderRadius = '12px';
            badge.style.backgroundColor = '#f0f2f5';
            badge.style.color = '#333';
            badge.style.padding = '4px 8px';
            badge.style.margin = '2px';
            badge.style.fontSize = '0.8rem';
            badge.textContent = lote;
            const removeSpan = document.createElement('span');
            removeSpan.style.marginLeft = '6px';
            removeSpan.style.color = '#dc3545';
            removeSpan.style.cursor = 'pointer';
            removeSpan.textContent = '×';
            removeSpan.addEventListener('click', () => {
                lotesEditar.splice(idx, 1);
                renderLotesEditar();
            });
            badge.appendChild(removeSpan);
            contenedorLotesEditar.appendChild(badge);
        });
        if (inputHiddenLotesEditar) {
            inputHiddenLotesEditar.value = JSON.stringify(lotesEditar);
        }
    }
    if (inputLoteEditar) {
        inputLoteEditar.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const valor = this.value.trim();
                if (valor && !lotesEditar.includes(valor)) {
                    lotesEditar.push(valor);
                    renderLotesEditar();
                }
                this.value = '';
            }
        });
    }

    // Ver desarrollo
    const modalVer = document.getElementById('modalVerDesarrollo');
    if (modalVer) {
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('.btnVerDesarrollo');
            if (!trigger) {
                return;
            }

            const nombre = trigger.getAttribute('data-nombre');
            const tipocontratoNombre = trigger.getAttribute('data-tipocontrato-nombre');
            const descripcion = trigger.getAttribute('data-descripcion');
            const superficie = trigger.getAttribute('data-superficie');
            const clave = trigger.getAttribute('data-clave');
            let lotesStr = trigger.getAttribute('data-lotes') || '[]';
            lotesStr = decodeHtml(lotesStr);
            const precioLote = trigger.getAttribute('data-preciolote');
            const precioTotal = trigger.getAttribute('data-preciototal');

            const inputNombre = document.getElementById('verNombreDesarrollo');
            const inputTipoContrato = document.getElementById('verTipoContrato');
            const inputDescripcion = document.getElementById('verDescripcion');
            const inputSuperficie = document.getElementById('verSuperficie');
            const inputClave = document.getElementById('verClaveCatastral');
            const inputPrecioLote = document.getElementById('verPrecioLote');
            const inputPrecioTotal = document.getElementById('verPrecioTotal');

            if (inputNombre) inputNombre.value = nombre || '';
            if (inputTipoContrato) inputTipoContrato.value = tipocontratoNombre || '';
            if (inputDescripcion) inputDescripcion.value = descripcion || '';
            if (inputSuperficie) inputSuperficie.value = superficie || '';
            if (inputClave) inputClave.value = clave || '';

            if (inputPrecioLote) {
                inputPrecioLote.value = precioLote ? '$' + formatNumber(precioLote) : '';
            }
            if (inputPrecioTotal) {
                inputPrecioTotal.value = precioTotal ? '$' + formatNumber(precioTotal) : '';
            }

            const contenedorVer = document.getElementById('contenedorLotesVer');
            if (contenedorVer) {
                contenedorVer.innerHTML = '';
                let arrVer = [];
                try {
                    const arr = JSON.parse(lotesStr);
                    if (Array.isArray(arr)) arrVer = arr;
                } catch (err) {
                    if (lotesStr) {
                        arrVer = lotesStr.split(',').map((l) => l.trim()).filter(Boolean);
                    }
                }
                arrVer.forEach((lote) => {
                    const span = document.createElement('span');
                    span.style.display = 'inline-flex';
                    span.style.alignItems = 'center';
                    span.style.borderRadius = '12px';
                    span.style.backgroundColor = '#f0f2f5';
                    span.style.color = '#333';
                    span.style.padding = '4px 8px';
                    span.style.margin = '2px';
                    span.style.fontSize = '0.8rem';
                    span.textContent = lote;
                    contenedorVer.appendChild(span);
                });
            }
        });
    }

    // Ver y editar cliente (eventos delegados para funcionar con DataTables dinámicas)
    const modalVerCliente = document.getElementById('modalVerCliente');
    const formEditarCliente = document.getElementById('formEditarCliente');

    const setInputValue = (id, value) => {
        const input = document.getElementById(id);
        if (!input) {
            return;
        }
        input.value = value !== null && value !== undefined ? value : '';
    };

    const llenarModalVerCliente = (trigger) => {
        if (!modalVerCliente || !trigger) {
            return;
        }

        const obtener = (attr, fallback = '') => {
            const valor = trigger.getAttribute(attr);
            return valor !== null ? valor : fallback;
        };

        setInputValue('verNombreCliente', obtener('data-nombre'));
        setInputValue('verNacionalidadCliente', obtener('data-nacionalidad-nombre'));
        const fechaTexto = obtener('data-fecha-texto', obtener('data-fecha', ''));
        setInputValue('verFechaCliente', fechaTexto);
        setInputValue('verRfcCliente', obtener('data-rfc'));
        setInputValue('verCurpCliente', obtener('data-curp'));
        setInputValue('verIneCliente', obtener('data-ine'));
        setInputValue('verEstadoCivilCliente', obtener('data-estado_civil'));
        setInputValue('verOcupacionCliente', obtener('data-ocupacion'));
        setInputValue('verTelefonoCliente', obtener('data-telefono'));
        setInputValue('verDomicilioCliente', obtener('data-domicilio'));
        setInputValue('verEmailCliente', obtener('data-email'));
        setInputValue('verBeneficiarioCliente', obtener('data-beneficiario'));
    };

    const llenarModalEditarCliente = (trigger) => {
        if (!formEditarCliente || !trigger) {
            return;
        }

        const obtener = (attr, fallback = '') => {
            const valor = trigger.getAttribute(attr);
            return valor !== null ? valor : fallback;
        };

        setInputValue('editarIdCliente', obtener('data-id'));
        setInputValue('editarNombreCliente', obtener('data-nombre'));

        const nacionalidadId = obtener('data-nacionalidad-id');
        const nacionalidadNombre = obtener('data-nacionalidad-nombre');
        const nacionalidadIdLimpia = nacionalidadId !== '' ? decodeHtml(nacionalidadId) : '';
        const nacionalidadNombreLimpia = nacionalidadNombre !== '' ? decodeHtml(nacionalidadNombre) : '';
        const selectNac = document.getElementById('editarNacionalidadCliente');
        if (selectNac) {
            selectNac.querySelectorAll('option[data-dynamic-option="1"]').forEach((opcion) => opcion.remove());
            let asignado = false;
            if (nacionalidadIdLimpia !== '') {
                selectNac.value = nacionalidadIdLimpia;
                asignado = selectNac.value === nacionalidadIdLimpia;
            }

            if (!asignado && nacionalidadNombreLimpia !== '') {
                const normalizarTexto = (valor) => {
                    if (valor === null || valor === undefined) {
                        return '';
                    }
                    let texto = String(valor).trim();
                    if (texto === '') {
                        return '';
                    }
                    if (typeof texto.normalize === 'function') {
                        texto = texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    }
                    return texto.toUpperCase();
                };

                const objetivo = normalizarTexto(nacionalidadNombreLimpia);
                if (objetivo !== '') {
                    const opciones = Array.from(selectNac.options);
                    for (const opcion of opciones) {
                        if (!opcion) {
                            continue;
                        }
                        const valorOpcion = normalizarTexto(opcion.value);
                        const textoOpcion = normalizarTexto(opcion.textContent);
                        if (valorOpcion === objetivo || textoOpcion === objetivo) {
                            selectNac.value = opcion.value;
                            asignado = true;
                            break;
                        }
                    }
                }
            }

            if (!asignado) {
                const valorTemporal = nacionalidadIdLimpia !== ''
                    ? nacionalidadIdLimpia
                    : (nacionalidadNombreLimpia !== '' ? nacionalidadNombreLimpia : '');
                const textoTemporal = nacionalidadNombreLimpia !== ''
                    ? nacionalidadNombreLimpia
                    : (valorTemporal !== '' ? valorTemporal : '');

                if (valorTemporal !== '' || textoTemporal !== '') {
                    const opcionTemporal = document.createElement('option');
                    opcionTemporal.value = valorTemporal !== '' ? valorTemporal : textoTemporal;
                    opcionTemporal.textContent = textoTemporal !== '' ? textoTemporal : opcionTemporal.value;
                    opcionTemporal.dataset.dynamicOption = '1';
                    selectNac.appendChild(opcionTemporal);
                    selectNac.value = opcionTemporal.value;
                    asignado = selectNac.value === opcionTemporal.value;
                }
            }

            if (!asignado) {
                selectNac.value = '';
            }
        }

        const fechaIso = obtener('data-fecha-iso', obtener('data-fecha', ''));
        setInputValue('editarFechaCliente', fechaIso);
        setInputValue('editarRfcCliente', obtener('data-rfc'));
        setInputValue('editarCurpCliente', obtener('data-curp'));
        setInputValue('editarIneCliente', obtener('data-ine'));
        setInputValue('editarEstadoCivilCliente', obtener('data-estado_civil'));
        setInputValue('editarOcupacionCliente', obtener('data-ocupacion'));

        const telefonoCliente = obtener('data-telefono');
        const telefonoNormalizado = typeof telefonoCliente === 'string' ? telefonoCliente.trim() : '';
        const telefonoClienteHidden = document.getElementById('editarTelefonoClienteHidden');
        const telefonoInput = document.getElementById('editarTelefonoCliente');
        let instanciaTel = null;

        const limpiarNumeroTelefono = (valor) => {
            if (typeof valor !== 'string' || valor.trim() === '') {
                return '';
            }
            const digitos = valor.replace(/[^0-9]/g, '');
            if (digitos.length <= 10) {
                return digitos;
            }
            const sinPrefijoMex = digitos.replace(/^52(?=\d{10}$)/, '');
            if (sinPrefijoMex.length === 10) {
                return sinPrefijoMex;
            }
            return digitos.slice(-10);
        };

        if (telefonoInput) {
            const obtenerInstanciaTel = () => {
                if (window.intlTelInputGlobals && typeof window.intlTelInputGlobals.getInstance === 'function') {
                    try {
                        return window.intlTelInputGlobals.getInstance(telefonoInput);
                    } catch (error) {
                        console.warn('No se pudo obtener la instancia de intl-tel-input', error);
                    }
                }
                return null;
            };

            instanciaTel = obtenerInstanciaTel();
            if (instanciaTel && typeof instanciaTel.setNumber === 'function') {
                instanciaTel.setNumber(telefonoNormalizado);
                telefonoInput.dispatchEvent(new Event('input', { bubbles: true }));
            } else {
                telefonoInput.value = limpiarNumeroTelefono(telefonoNormalizado);
            }
        } else {
            setInputValue('editarTelefonoCliente', limpiarNumeroTelefono(telefonoNormalizado));
        }

        if (telefonoClienteHidden) {
            if (instanciaTel) {
                if (!telefonoClienteHidden.value && telefonoNormalizado !== '') {
                    telefonoClienteHidden.value = telefonoNormalizado;
                }
            } else {
                telefonoClienteHidden.value = telefonoNormalizado;
            }
        }

        setInputValue('editarDomicilioCliente', obtener('data-domicilio'));
        setInputValue('editarEmailCliente', obtener('data-email'));
        setInputValue('editarBeneficiarioCliente', obtener('data-beneficiario'));
    };

    if (modalVerCliente || formEditarCliente) {
        document.addEventListener('click', (event) => {
            const triggerVer = event.target.closest('.btnVerCliente, .verClienteNombre');
            if (triggerVer) {
                if (triggerVer.tagName && triggerVer.tagName.toLowerCase() === 'a') {
                    event.preventDefault();
                }
                llenarModalVerCliente(triggerVer);
            }

            const triggerEditar = event.target.closest('.btnEditarCliente');
            if (triggerEditar) {
                llenarModalEditarCliente(triggerEditar);
            }
        });
    }

    if (formEditarCliente) {
        formEditarCliente.addEventListener('submit', function (e) {
            e.preventDefault();
            // Confirmación antes de enviar
            Swal.fire({
                title: '¿Estás seguro de modificar los datos?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, modificar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData(formEditarCliente);
                    const url = formEditarCliente.getAttribute('action');
                    fetch(url, {
                        method: 'POST',
                        body: formData
                    }).then(r => r.text()).then(resp => {
                        let title = 'Guardado';
                        let text = 'Cliente actualizado correctamente.';
                        let icon = 'success';
                        if (resp.includes('error')) {
                            title = 'Error';
                            text = 'No se pudo actualizar.';
                            icon = 'error';
                        }
                        Swal.fire(title, text, icon).then(() => {
                            window.location.reload();
                        });
                    }).catch(() => {
                        Swal.fire('Error', 'No se pudo actualizar.', 'error');
                    });
                }
            });
        });
    }

    const manejarCambioEstadoCliente = async (boton) => {
        if (!boton) {
            return;
        }

        const clienteId = boton.getAttribute('data-id');
        const estadoDestino = boton.getAttribute('data-estado-destino');
        const nombre = boton.getAttribute('data-nombre') || 'este cliente';

        if (!clienteId || !estadoDestino) {
            return;
        }

        let confirmar = { isConfirmed: true };
        if (typeof Swal !== 'undefined') {
            const accion = estadoDestino === 'archivado' ? 'Archivar' : 'Reactivar';
            const mensaje = estadoDestino === 'archivado'
                ? 'El cliente no podrá recibir nuevos contratos hasta reactivarlo.'
                : 'El cliente volverá a estar disponible para nuevos contratos.';
            confirmar = await Swal.fire({
                title: `${accion} ${nombre}?`,
                text: mensaje,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `Sí, ${accion.toLowerCase()}`,
                cancelButtonText: 'Cancelar'
            });
        }

        if (!confirmar.isConfirmed) {
            return;
        }

        const params = new URLSearchParams();
        params.set('id_cliente', clienteId);
        params.set('estado', estadoDestino);
        params.set('csrf_token', obtenerCsrfToken());

        boton.disabled = true;
        boton.setAttribute('aria-busy', 'true');

        try {
            const respuesta = await fetch('index.php?ruta=clientes&accion=actualizarEstadoCliente', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: params.toString()
            });

            const bruto = await respuesta.text();
            let data = null;
            if (bruto) {
                try {
                    data = JSON.parse(bruto);
                } catch (error) {
                    data = null;
                }
            }

            const estadoRespuesta = data && typeof data.status === 'string' ? data.status : null;
            const exito = respuesta.ok && (estadoRespuesta === null || estadoRespuesta === 'ok');

            if (exito) {
                const mensajeExito = data && typeof data.message === 'string'
                    ? data.message
                    : 'Estado actualizado correctamente.';
                if (typeof Swal !== 'undefined') {
                    await Swal.fire('Hecho', mensajeExito, 'success');
                }
                document.dispatchEvent(new CustomEvent('ag:datatable:reload', {
                    detail: { target: '#tablaClientes', resetPaging: false }
                }));
                return;
            }

            const mensajeError = data && typeof data.message === 'string'
                ? data.message
                : (bruto && bruto.trim() !== '' ? bruto.trim() : 'No fue posible actualizar el estado del cliente.');
            const icono = data && data.code === 'CONTRATOS_ACTIVOS' ? 'warning' : 'error';
            if (typeof Swal !== 'undefined') {
                await Swal.fire('No se pudo completar', mensajeError, icono);
            }
        } catch (error) {
            console.error('Error al cambiar el estado del cliente', error);
            if (typeof Swal !== 'undefined') {
                await Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
            }
        } finally {
            boton.disabled = false;
            boton.removeAttribute('aria-busy');
        }
    };

    document.addEventListener('click', (event) => {
        const boton = event.target.closest('.btnCambiarEstadoCliente');
        if (!boton) {
            return;
        }
        event.preventDefault();
        manejarCambioEstadoCliente(boton);
    });

    // Crear contrato
    const btnsCrearContrato = document.querySelectorAll('.btnCrearContrato');
    const formCrearContrato = document.getElementById('formCrearContrato');
    const selectDesarrolloContrato = document.getElementById('selectDesarrolloContrato');
    const contratoSuperficie = document.getElementById('contratoSuperficie');
    // Identificador y nombre del tipo de contrato en la creación
    const contratoTipoId = document.getElementById('contratoTipoId');
    const contratoTipoNombre = document.getElementById('contratoTipoNombre');

    // === Campos adicionales para manejo financiero de contratos (creación) ===
    const montoInmueble = document.getElementById('montoInmueble');
    const montoInmuebleFixed = document.getElementById('montoInmuebleFixed');
    const enganche = document.getElementById('enganche');
    const engancheFixed = document.getElementById('engancheFixed');
    const saldoPago = document.getElementById('saldoPago');
    const saldoPagoFixed = document.getElementById('saldoPagoFixed');
    const penalizacion = document.getElementById('penalizacion');
    const penalizacionFixed = document.getElementById('penalizacionFixed');

    /*
     * Convierte un número a letras solicitando al backend el resultado.
     * Actualiza el input oculto asociado con el resultado devuelto.
     * Si el número no es válido, se limpia el input de destino.
     * @param {number|string} num Valor numérico a convertir
     * @param {HTMLElement} target Elemento input hidden donde se colocará el resultado
     */
    function convertirNumeroALetras(num, target) {
        if (!target) return;

        const normalizado = typeof num === 'number'
            ? num
            : parseFloat(cleanNumberString(num || ''));

        if (!Number.isFinite(normalizado) || normalizado === 0) {
            target.value = '';
            return;
        }

        const tipo = (target.dataset.numeroALetras || 'moneda').toLowerCase();
        const formData = new URLSearchParams();
        formData.append('num', normalizado);
        formData.append('tipo', tipo);

        fetch('ajax/numero_a_letras.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
            .then((r) => r.text())
            .then((res) => {
                target.value = res.trim();
            })
            .catch(() => {
                if (tipo === 'superficie') {
                    target.value = `${normalizado} M2`;
                } else {
                    target.value = normalizado.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                }
            });
    }

    

    /*
     * Actualiza los campos derivados (saldo y penalización) en tiempo real
     * para el formulario de creación de contratos. Calcula saldo como
     * monto - enganche y penalización como 10% del monto. También
     * actualiza los campos ocultos con las cantidades en letras.
     
    */
    

    function actualizarCalculosContrato() {
        const montoVal = parseFloat(cleanNumberString(montoInmueble && montoInmueble.value ? montoInmueble.value : '0')) || 0;
        const engancheVal = parseFloat(cleanNumberString(enganche && enganche.value ? enganche.value : '0')) || 0;
        const saldoVal = montoVal - engancheVal;
        const penalVal = montoVal * 0.20;

        if (saldoPago) {
            saldoPago.value = saldoVal.toFixed(2);
        }
        if (penalizacion) {
            penalizacion.value = penalVal.toFixed(2);
        }

        convertirNumeroALetras(montoVal, montoInmuebleFixed);
        convertirNumeroALetras(engancheVal, engancheFixed);
        convertirNumeroALetras(saldoVal, saldoPagoFixed);
        convertirNumeroALetras(penalVal, penalizacionFixed);
    }

    const parseJsonAttribute = (value) => {
        if (!value) {
            return {};
        }

        if (typeof value === 'object') {
            return { ...value };
        }

        if (typeof value !== 'string') {
            return {};
        }

        const trimmed = value.trim();
        if (trimmed === '') {
            return {};
        }

        try {
            return JSON.parse(trimmed);
        } catch (error) {
            console.warn('No fue posible interpretar data-dt-params como JSON.', error);
            return {};
        }
    };

    const mergePlainParams = (base = {}, updates = {}) => {
        const resultado = { ...base };
        Object.entries(updates || {}).forEach(([key, value]) => {
            if (value === null || typeof value === 'undefined') {
                delete resultado[key];
                return;
            }
            resultado[key] = value;
        });
        return resultado;
    };

    const obtenerCsrfToken = () => {
        const selectores = [
            '#formEditarCliente input[name="csrf_token"]',
            '#formCliente input[name="csrf_token"]',
            'form input[name="csrf_token"]'
        ];

        for (const selector of selectores) {
            const input = document.querySelector(selector);
            if (input && typeof input.value === 'string' && input.value.trim() !== '') {
                return input.value.trim();
            }
        }

        return '';
    };

    const getDataTableManager = (() => {
        let managerInstance = null;

        const toElement = (input) => {
            if (!input) {
                return null;
            }
            if (input instanceof Element) {
                return input;
            }
            if (typeof input === 'string') {
                return document.querySelector(input);
            }
            if (input.jquery && input.length) {
                return input[0];
            }
            return null;
        };

        return () => {
            if (managerInstance) {
                return managerInstance;
            }

            const existing = typeof window !== 'undefined' ? window.AGDataTables : undefined;
            const registry = existing && existing.__registry instanceof Map
                ? existing.__registry
                : new Map();

            managerInstance = Object.assign(existing || {}, {
                __initialized: true,
                __registry: registry,
                register(element, state) {
                    const el = toElement(element);
                    if (!el || !state) {
                        return;
                    }
                    state.element = el;
                    registry.set(el, state);
                    el.__agDtState = state;
                },
                unregister(element) {
                    const el = toElement(element);
                    if (!el) {
                        return;
                    }
                    registry.delete(el);
                    if (el.__agDtState) {
                        delete el.__agDtState;
                    }
                },
                getState(element) {
                    const el = toElement(element);
                    if (!el) {
                        return null;
                    }
                    return registry.get(el) || el.__agDtState || null;
                },
                get(element) {
                    const state = this.getState(element);
                    return state ? state.instance || null : null;
                },
                updateParams(element, updates) {
                    const state = this.getState(element);
                    if (!state) {
                        return null;
                    }
                    state.extraParams = mergePlainParams(state.extraParams || {}, updates);
                    if (state.element) {
                        try {
                            state.element.setAttribute('data-dt-params', JSON.stringify(state.extraParams));
                        } catch (error) {
                            console.warn('No fue posible serializar los parámetros de DataTable.', error);
                        }
                    }
                    return state.extraParams;
                },
                reload(element, updates, resetPaging = true) {
                    const state = this.getState(element);
                    if (!state || !state.instance) {
                        return null;
                    }
                    if (updates && typeof updates === 'object') {
                        this.updateParams(state.element, updates);
                    }
                    state.instance.ajax.reload(null, resetPaging);
                    return state.instance;
                },
                list() {
                    return Array.from(registry.values()).map((state) => state.instance).filter(Boolean);
                }
            });

            if (typeof window !== 'undefined') {
                window.AGDataTables = managerInstance;
            }

            return managerInstance;
        };
    })();

    const ensureFeedbackElement = (state) => {
        if (!state) {
            return null;
        }

        if (!state.feedbackEl) {
            state.feedbackEl = document.createElement('div');
            state.feedbackEl.className = 'ag-datatable-feedback alert alert-info d-none mt-2';
        }

        if (state.instance && typeof state.instance.table === 'function') {
            const container = state.instance.table().container();
            if (container && container.parentNode && !state.feedbackEl.isConnected) {
                container.parentNode.insertBefore(state.feedbackEl, container.nextSibling);
            }
        } else if (state.element && state.element.parentNode && !state.feedbackEl.isConnected) {
            state.element.parentNode.insertBefore(state.feedbackEl, state.element.nextSibling);
        }

        return state.feedbackEl;
    };

    const updateDataTableFeedback = (state, type, message) => {
        const feedback = ensureFeedbackElement(state);
        if (!feedback) {
            return;
        }

        if (!type || !message) {
            feedback.classList.add('d-none');
            feedback.textContent = '';
            state.feedbackType = null;
            state.feedbackMessage = '';
            return;
        }

        const validTypes = ['info', 'success', 'warning', 'danger'];
        const chosenType = validTypes.includes(type) ? type : 'info';
        feedback.className = `ag-datatable-feedback alert alert-${chosenType} mt-2`;
        feedback.textContent = message;
        feedback.classList.remove('d-none');
        state.feedbackType = chosenType;
        state.feedbackMessage = message;
    };

    const processDataTableResponse = (state, json) => {
        if (!json || typeof json !== 'object') {
            updateDataTableFeedback(state, 'danger', 'Respuesta no válida del servidor.');
            return [];
        }

        if (typeof json.error === 'string' && json.error !== '') {
            updateDataTableFeedback(state, 'warning', json.error);
        } else if (Array.isArray(json.warnings) && json.warnings.length > 0) {
            updateDataTableFeedback(state, 'warning', json.warnings.join(' '));
        } else if (typeof json.message === 'string' && json.message !== '') {
            updateDataTableFeedback(state, 'info', json.message);
        } else {
            updateDataTableFeedback(state, null, '');
        }

        const data = Array.isArray(json.data) ? json.data : [];
        state.lastJson = json;
        return data;
    };

    const handleDataTablesError = (state, xhr, textStatus, errorThrown) => {
        const responseJson = xhr && xhr.responseJSON ? xhr.responseJSON : null;
        const mensaje = responseJson && typeof responseJson.error === 'string' && responseJson.error !== ''
            ? responseJson.error
            : (errorThrown || textStatus || 'No fue posible cargar la información.');
        updateDataTableFeedback(state, 'danger', mensaje);
        state.lastError = mensaje;
        if (state.element) {
            state.element.removeAttribute('aria-busy');
        }
        console.error('Error al cargar DataTable', { mensaje, xhr, textStatus, errorThrown });
    };

    // Inicializar DataTables con la configuración por defecto del plugin
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        const dataTableManager = getDataTableManager();
        const dispatchDataTableEvent = (name, detail) => {
            document.dispatchEvent(new CustomEvent(name, { detail }));
        };

        if ($.fn.dataTable.ext && $.fn.dataTable.ext.errMode) {
            $.fn.dataTable.ext.errMode = 'none';
        }

        const obtenerCeldaLegacy = (row, index) => {
            if (!row) {
                return '';
            }

            if (Array.isArray(row.cells)) {
                return row.cells[index] ?? '';
            }

            if (Array.isArray(row)) {
                return row[index] ?? '';
            }

            if (row.cells && typeof row.cells === 'object') {
                return row.cells[index] ?? '';
            }

            if (row && typeof row === 'object') {
                return row[index] ?? '';
            }

            return '';
        };

        const rendererResponsive = $.fn.dataTable.Responsive
            && $.fn.dataTable.Responsive.renderer
            && $.fn.dataTable.Responsive.renderer.tableAll
            ? $.fn.dataTable.Responsive.renderer.tableAll({
                tableClass: 'table table-sm table-borderless mb-0'
            })
            : ($.fn.dataTable.Responsive
                && $.fn.dataTable.Responsive.renderer
                && $.fn.dataTable.Responsive.renderer.listHidden
                    ? $.fn.dataTable.Responsive.renderer.listHidden()
                    : null);

        const idiomaTabla = {
            decimal: ',',
            thousands: '.',
            processing: 'Procesando...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            infoPostFix: '',
            loadingRecords: 'Cargando...',
            zeroRecords: 'No se encontraron resultados',
            emptyTable: 'No hay datos disponibles en la tabla',
            paginate: {
                first: 'Primero',
                previous: 'Anterior',
                next: 'Siguiente',
                last: 'Último'
            },
            aria: {
                sortAscending: ': activar para ordenar la columna de manera ascendente',
                sortDescending: ': activar para ordenar la columna de manera descendente'
            }
        };

        const responsiveConfig = $.fn.dataTable.Responsive
            ? {
                details: {
                    type: 'column',
                    target: 'td.dtr-control',
                    renderer: rendererResponsive || ($.fn.dataTable.Responsive.renderer && $.fn.dataTable.Responsive.renderer.listHidden
                        ? $.fn.dataTable.Responsive.renderer.listHidden()
                        : null)
                }
            }
            : false;

        const opcionesPorDefecto = {
            responsive: responsiveConfig,
            autoWidth: false,
            order: [],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Todos']],
            language: idiomaTabla,
            columnDefs: [
                { targets: 'no-sort', orderable: false },
                { targets: 'no-search', searchable: false }
            ]
        };

        const tablaConfiguraciones = {
            '#tablaClientes': {
                resource: 'clientes',
                columns: [
                    { data: null, defaultContent: '', className: 'dtr-control dt-control', orderable: false, searchable: false, responsivePriority: 1 },
                    { data: 'id', className: 'text-nowrap', responsivePriority: 2 },
                    { data: 'nombre', responsivePriority: 1 },
                    { data: 'email', responsivePriority: 3 },
                    { data: 'estado', responsivePriority: 2 },
                    { data: 'acciones', orderable: false, searchable: false, responsivePriority: 1 }
                ]
            },
            '#tablaDesarrollos': {
                resource: 'desarrollos',
                columns: [
                    { data: null, defaultContent: '', className: 'dtr-control dt-control', orderable: false, searchable: false, responsivePriority: 1 },
                    { data: 'id', className: 'text-nowrap', responsivePriority: 2 },
                    { data: 'nombre', responsivePriority: 1 },
                    { data: 'tipo', responsivePriority: 3 },
                    { data: 'acciones', orderable: false, searchable: false, responsivePriority: 1 }
                ]
            },
            '#tablaNacionalidades': {
                resource: 'nacionalidades',
                columns: [
                    { data: null, defaultContent: '', className: 'dtr-control dt-control', orderable: false, searchable: false, responsivePriority: 1 },
                    { data: 'id', className: 'text-nowrap', responsivePriority: 2 },
                    { data: 'identificador', responsivePriority: 1 },
                    { data: 'nombre', responsivePriority: 1 },
                    { data: 'acciones', orderable: false, searchable: false, responsivePriority: 1 }
                ]
            },
            '#tablaTipos': {
                resource: 'tipos_contrato',
                columns: [
                    { data: null, defaultContent: '', className: 'dtr-control dt-control', orderable: false, searchable: false, responsivePriority: 1 },
                    { data: 'id', className: 'text-nowrap', responsivePriority: 2 },
                    { data: 'identificador', responsivePriority: 1 },
                    { data: 'nombre', responsivePriority: 1 },
                    { data: 'acciones', orderable: false, searchable: false, responsivePriority: 1 }
                ]
            },
            '#tablaPlantillas': {
                resource: 'plantillas_contrato',
                columns: [
                    { data: null, defaultContent: '', className: 'dtr-control dt-control', orderable: false, searchable: false, responsivePriority: 1 },
                    { data: 'id', className: 'text-nowrap', responsivePriority: 3 },
                    { data: 'tipo', responsivePriority: 2 },
                    { data: 'nombre', responsivePriority: 1 },
                    { data: 'archivo', responsivePriority: 2 },
                    { data: 'acciones', orderable: false, searchable: false, responsivePriority: 1 }
                ]
            },
            '#tablaContratos': {
                resource: 'contratos',
                columns: [
                    { data: null, defaultContent: '', className: 'dtr-control dt-control', orderable: false, searchable: false, responsivePriority: 1 },
                    { data: 'seleccion', orderable: false, searchable: false, className: 'text-center', responsivePriority: 2 },
                    { data: 'id', className: 'text-nowrap', responsivePriority: 3 },
                    { data: 'creado', responsivePriority: 4 },
                    { data: 'propietario', responsivePriority: 3 },
                    { data: 'folio', responsivePriority: 1 },
                    { data: 'cliente', responsivePriority: 2 },
                    { data: 'desarrollo', responsivePriority: 2 },
                    { data: 'estado', responsivePriority: 1 },
                    { data: 'acciones', orderable: false, searchable: false, responsivePriority: 1 }
                ]
            },
            '#tablaSolicitudes': {
                resource: 'solicitudes',
                columns: [
                    { data: null, defaultContent: '', className: 'dtr-control dt-control', orderable: false, searchable: false, responsivePriority: 1 },
                    { data: 'folio', responsivePriority: 3 },
                    { data: 'nombre', responsivePriority: 1 },
                    { data: 'estado', responsivePriority: 2 },
                    { data: 'fecha', responsivePriority: 4 },
                    { data: 'responsable', responsivePriority: 3 },
                    { data: 'contrato', responsivePriority: 2, searchable: false },
                    { data: 'acciones', orderable: false, searchable: false, responsivePriority: 1 }
                ]
            },
            '#tablaPlantillasSolicitud': {
                resource: 'plantillas_solicitud',
                columns: [
                    { data: null, defaultContent: '', className: 'dtr-control dt-control', orderable: false, searchable: false, responsivePriority: 1 },
                    { data: 'id', className: 'text-nowrap', responsivePriority: 3 },
                    { data: 'tipo', responsivePriority: 2 },
                    { data: 'nombre', responsivePriority: 1 },
                    { data: 'archivo', responsivePriority: 2 },
                    { data: 'acciones', orderable: false, searchable: false, responsivePriority: 1 }
                ]
            },
            '#tablaUsuarios': {
                resource: 'usuarios',
                columns: [
                    { data: null, defaultContent: '', className: 'dtr-control dt-control', orderable: false, searchable: false, responsivePriority: 1 },
                    { data: 'id', className: 'text-nowrap', responsivePriority: 2 },
                    { data: 'nombre', responsivePriority: 1 },
                    { data: 'username', responsivePriority: 3 },
                    { data: 'email', responsivePriority: 4 },
                    { data: 'permiso', className: 'text-center', responsivePriority: 2, searchable: false },
                    { data: 'notificaciones', className: 'text-center', orderable: false, searchable: false, responsivePriority: 3 },
                    { data: 'fecha', className: 'text-nowrap', responsivePriority: 5, searchable: false },
                    { data: 'acciones', className: 'text-center', orderable: false, searchable: false, responsivePriority: 1 }
                ]
            }
        };

        Object.entries(tablaConfiguraciones).forEach(([selector, config]) => {
            const $tabla = $(selector);
            if (!$tabla.length) {
                return;
            }

            $tabla.each(function inicializarDataTableConfigurada() {
                if ($.fn.dataTable.isDataTable(this)) {
                    return;
                }

                const $table = $(this);
                const rawOptionsData = $table.data('datatableOptions') || $table.data('datatable-options');
                const opcionesExtra = (() => {
                    if (rawOptionsData && typeof rawOptionsData === 'object') {
                        return rawOptionsData;
                    }

                    if (typeof rawOptionsData === 'string') {
                        return parseJsonAttribute(rawOptionsData);
                    }

                    const attrOptions = $table.attr('data-datatable-options');
                    return attrOptions ? parseJsonAttribute(attrOptions) : {};
                })();
                const resource = config.resource || $table.data('dtResource') || $table.data('dt-resource');
                const ajaxUrl = config.ajaxUrl || (resource ? `ajax/datatables.php?resource=${resource}` : null);

                const paramsAttr = parseJsonAttribute($table.attr('data-dt-params'));
                const paramsData = parseJsonAttribute($table.data('dtParams'));
                const configParams = config.params && typeof config.params === 'object' ? config.params : {};
                const initialParams = mergePlainParams(configParams, mergePlainParams(paramsAttr, paramsData));

                const state = {
                    element: $table[0],
                    selector,
                    resource,
                    extraParams: initialParams,
                    instance: null,
                    feedbackEl: null,
                    feedbackType: null,
                    feedbackMessage: '',
                    lastError: null,
                    lastJson: null
                };

                dataTableManager.register($table[0], state);

                if (state.element && state.extraParams && Object.keys(state.extraParams).length > 0) {
                    try {
                        state.element.setAttribute('data-dt-params', JSON.stringify(state.extraParams));
                    } catch (error) {
                        console.warn('No fue posible preparar los parámetros iniciales de la tabla.', error);
                    }
                }

                const columnas = (config.columns || []).map((col) => {
                    let dataProp;
                    if (Object.prototype.hasOwnProperty.call(col, 'data')) {
                        dataProp = col.data;
                    } else if (typeof col.index === 'number') {
                        dataProp = function obtenerDesdeIndice(row) {
                            return obtenerCeldaLegacy(row, col.index);
                        };
                    } else {
                        dataProp = null;
                    }

                    const columnaConfigurada = {
                        data: dataProp,
                        defaultContent: Object.prototype.hasOwnProperty.call(col, 'defaultContent') ? col.defaultContent : '',
                        className: col.className || '',
                        orderable: typeof col.orderable === 'boolean' ? col.orderable : true,
                        searchable: typeof col.searchable === 'boolean' ? col.searchable : true,
                        responsivePriority: col.responsivePriority
                    };

                    if (typeof col.render === 'function') {
                        columnaConfigurada.render = col.render;
                    }

                    if (typeof col.name === 'string') {
                        columnaConfigurada.name = col.name;
                    }

                    if (typeof col.width === 'string') {
                        columnaConfigurada.width = col.width;
                    }

                    return columnaConfigurada;
                });

                const ajaxOptions = ajaxUrl ? {
                    url: ajaxUrl,
                    type: 'GET',
                    dataType: 'json',
                    cache: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    data: function (data) { return data; },
                    dataSrc: function (json) {
                        return processDataTableResponse(state, json);
                    },
                    error: function (xhr, textStatus, errorThrown) {
                        handleDataTablesError(state, xhr, textStatus, errorThrown);
                    }
                } : undefined;

                const baseConfig = {
                    ajax: ajaxOptions,
                    columns: columnas,
                    createdRow: function createdRow(row, data) {
                        const attrs = data && typeof data === 'object'
                            ? (data.DT_RowAttr || data.rowAttrs)
                            : null;

                        if (attrs && typeof attrs === 'object') {
                            Object.entries(attrs).forEach(([attr, value]) => {
                                if (typeof value === 'string' && value !== '') {
                                    row.setAttribute(attr, value);
                                }
                            });
                        }

                        const rowClass = data && typeof data === 'object'
                            ? (data.DT_RowClass || data.rowClass)
                            : null;

                        if (typeof rowClass === 'string' && rowClass.trim() !== '') {
                            rowClass.split(/\s+/).forEach((cls) => {
                                if (cls) {
                                    row.classList.add(cls);
                                }
                            });
                        }
                    }
                };

                const configuracion = $.extend(true, {}, opcionesPorDefecto, baseConfig, opcionesExtra);

                if (configuracion.ajax) {
                    configuracion.ajax = Object.assign({
                        type: 'GET',
                        dataType: 'json',
                        cache: false
                    }, configuracion.ajax);

                    configuracion.ajax.headers = Object.assign(
                        { 'X-Requested-With': 'XMLHttpRequest' },
                        configuracion.ajax.headers || {}
                    );

                    const userDataFn = configuracion.ajax.data;
                    const userDataSrc = configuracion.ajax.dataSrc;
                    const userError = configuracion.ajax.error;
                    const userBeforeSend = configuracion.ajax.beforeSend;
                    const userComplete = configuracion.ajax.complete;

                    configuracion.ajax.data = function (data) {
                        Object.entries(state.extraParams || {}).forEach(([key, value]) => {
                            data[key] = value;
                        });
                        if (typeof userDataFn === 'function') {
                            const resultado = userDataFn.call(this, data);
                            if (resultado && typeof resultado === 'object') {
                                Object.entries(resultado).forEach(([key, value]) => {
                                    data[key] = value;
                                });
                            }
                        }
                        return data;
                    };

                    configuracion.ajax.dataSrc = function (json) {
                        const procesado = processDataTableResponse(state, json);
                        if (typeof userDataSrc === 'function') {
                            const resultadoUsuario = userDataSrc.call(this, json, procesado);
                            if (Array.isArray(resultadoUsuario)) {
                                return resultadoUsuario;
                            }
                        }
                        return procesado;
                    };

                    configuracion.ajax.error = function (xhr, textStatus, errorThrown) {
                        handleDataTablesError(state, xhr, textStatus, errorThrown);
                        if (typeof userError === 'function') {
                            userError.call(this, xhr, textStatus, errorThrown);
                        }
                    };

                    configuracion.ajax.beforeSend = function (xhr, settings) {
                        if (state.element) {
                            state.element.setAttribute('aria-busy', 'true');
                        }
                        if (typeof userBeforeSend === 'function') {
                            userBeforeSend.call(this, xhr, settings);
                        }
                    };

                    configuracion.ajax.complete = function (xhr, status) {
                        if (state.element) {
                            state.element.removeAttribute('aria-busy');
                        }
                        if (typeof userComplete === 'function') {
                            userComplete.call(this, xhr, status);
                        }
                    };
                }

                const userInitComplete = configuracion.initComplete;
                configuracion.initComplete = function (settings, json) {
                    state.instance = this.api();
                    if (typeof userInitComplete === 'function') {
                        userInitComplete.call(this, settings, json);
                    }
                };

                const dataTable = $table.DataTable(configuracion);
                state.instance = dataTable;
                updateDataTableFeedback(state, null, state.feedbackMessage);

                $table.on('xhr.dt', (event, settings, json) => {
                    state.lastJson = json || null;
                    if (json && typeof json.error === 'string' && json.error !== '') {
                        updateDataTableFeedback(state, 'warning', json.error);
                        state.lastError = json.error;
                    } else {
                        updateDataTableFeedback(state, null, '');
                        state.lastError = null;
                    }
                    dispatchDataTableEvent('ag:datatable:data', {
                        element: $table[0],
                        selector,
                        instance: dataTable,
                        json,
                        settings
                    });
                });

                $table.on('error.dt', (event, settings, techNote, message) => {
                    const texto = typeof message === 'string' && message !== '' ? message : 'Error al cargar la tabla.';
                    updateDataTableFeedback(state, 'danger', texto);
                    state.lastError = texto;
                    dispatchDataTableEvent('ag:datatable:error', {
                        element: $table[0],
                        selector,
                        instance: dataTable,
                        message: texto,
                        settings,
                        techNote
                    });
                });

                dispatchDataTableEvent('ag:datatable:ready', {
                    element: $table[0],
                    selector,
                    instance: dataTable,
                    state
                });
            });
        });
    }

    document.addEventListener('ag:datatable:reload', (event) => {
        const detalle = event && typeof event.detail === 'object' ? event.detail : {};
        const destino = detalle.target || detalle.selector || detalle.element;
        if (!destino) {
            return;
        }

        const manager = getDataTableManager();
        if (!manager || typeof manager.reload !== 'function') {
            return;
        }

        const params = detalle.params || detalle.extraParams || {};
        const resetPaging = typeof detalle.resetPaging === 'boolean' ? detalle.resetPaging : true;
        manager.reload(destino, params, resetPaging);
    });

    const tablaSolicitudes = document.getElementById('tablaSolicitudes');
    const switchCanceladas = document.getElementById('switchCanceladas');
    const filtrosSolicitudesForm = document.getElementById('filtrosSolicitudesForm');
    const estadoSelectSolicitudes = document.getElementById('filtroEstadoSolicitudes');
    const propietarioSelectSolicitudes = document.getElementById('filtroPropietarioSolicitudes');
    const esGestorTablaSolicitudes = tablaSolicitudes && tablaSolicitudes.getAttribute('data-es-gestor') === '1';

    const obtenerFiltrosSolicitudes = () => {
        const filtros = {};
        const estadosPermitidos = new Set(['todos', 'activos', 'borrador', 'enviada', 'en_revision', 'aprobada', 'cancelada']);
        const propietariosPermitidos = new Set(['todos', 'propios', 'otros']);

        if (estadoSelectSolicitudes) {
            const valorEstado = (estadoSelectSolicitudes.value || '').toLowerCase().trim();
            filtros.estado = estadosPermitidos.has(valorEstado) ? valorEstado : 'todos';
        }

        if (switchCanceladas) {
            filtros.verCanceladas = switchCanceladas.checked ? '1' : '0';
        }

        if (esGestorTablaSolicitudes && propietarioSelectSolicitudes) {
            const valorPropietario = (propietarioSelectSolicitudes.value || '').toLowerCase().trim();
            filtros.propietario = propietariosPermitidos.has(valorPropietario) ? valorPropietario : 'todos';
        }

        return filtros;
    };

    const actualizarUrlFiltrosSolicitudes = (filtros) => {
        if (typeof window === 'undefined' || !window.history || !window.location) {
            return;
        }

        try {
            const url = new URL(window.location.href);
            if (Object.prototype.hasOwnProperty.call(filtros, 'verCanceladas')) {
                if (filtros.verCanceladas === '1') {
                    url.searchParams.set('verCanceladas', '1');
                } else {
                    url.searchParams.delete('verCanceladas');
                }
            }

            if (Object.prototype.hasOwnProperty.call(filtros, 'estado')) {
                if (filtros.estado && filtros.estado !== 'todos') {
                    url.searchParams.set('estado', filtros.estado);
                } else {
                    url.searchParams.delete('estado');
                }
            }

            if (esGestorTablaSolicitudes && Object.prototype.hasOwnProperty.call(filtros, 'propietario')) {
                if (filtros.propietario && filtros.propietario !== 'todos') {
                    url.searchParams.set('propietario', filtros.propietario);
                } else {
                    url.searchParams.delete('propietario');
                }
            }

            window.history.replaceState({}, '', url.toString());
        } catch (error) {
            console.warn('No se pudo actualizar la URL del historial.', error);
        }
    };

    const aplicarFiltrosSolicitudes = (resetPaging = true) => {
        if (!tablaSolicitudes) {
            return;
        }

        const manager = getDataTableManager();
        if (!manager || typeof manager.reload !== 'function') {
            return;
        }

        const filtros = obtenerFiltrosSolicitudes();
        actualizarUrlFiltrosSolicitudes(filtros);
        manager.reload(tablaSolicitudes, filtros, resetPaging);
    };

    if (filtrosSolicitudesForm) {
        filtrosSolicitudesForm.addEventListener('submit', (event) => {
            event.preventDefault();
        });
    }

    if (estadoSelectSolicitudes) {
        estadoSelectSolicitudes.addEventListener('change', () => {
            if (estadoSelectSolicitudes.value === 'cancelada' && switchCanceladas && !switchCanceladas.checked) {
                switchCanceladas.checked = true;
            }
            aplicarFiltrosSolicitudes(true);
        });
    }

    if (propietarioSelectSolicitudes) {
        propietarioSelectSolicitudes.addEventListener('change', () => {
            aplicarFiltrosSolicitudes(true);
        });
    }

    if (switchCanceladas) {
        switchCanceladas.addEventListener('change', (event) => {
            event.preventDefault();
            aplicarFiltrosSolicitudes(true);
        });
    }

    const handleCambioEstadoSolicitud = (form, event) => {
        event.preventDefault();
        const boton = form.querySelector('[data-confirm-text]');
        const mensaje = boton ? boton.getAttribute('data-confirm-text') : '¿Confirmar acción?';
        const nuevoEstado = (form.querySelector('input[name="nuevo_estado"]')?.value || '').toLowerCase();
        const motivoInput = form.querySelector('input[name="motivo_cancelacion"]');
        const passwordInput = form.querySelector('input[name="password_confirmacion"]');

        form.setAttribute('autocomplete', 'off');

        if (passwordInput) {
            evitarAutocompletarPassword(passwordInput);
        }
        if (motivoInput) {
            motivoInput.value = '';
        }
        if (passwordInput) {
            passwordInput.value = '';
        }

        const enviarFormulario = () => {
            if (document.contains(form)) {
                form.submit();
                return;
            }

            const formClonado = document.createElement('form');
            formClonado.method = form.getAttribute('method') || 'post';
            formClonado.action = form.getAttribute('action') || window.location.href;
            formClonado.style.display = 'none';

            const datos = new FormData(form);
            for (const [key, valor] of datos.entries()) {
                if (valor instanceof File) {
                    continue;
                }
                const campo = document.createElement('input');
                campo.type = 'hidden';
                campo.name = key;
                campo.value = typeof valor === 'string' ? valor : String(valor ?? '');
                formClonado.appendChild(campo);
            }

            document.body.appendChild(formClonado);
            formClonado.submit();
        };

        const confirmarEnvio = () => {
            if (typeof Swal === 'undefined') {
                if (window.confirm(mensaje)) {
                    enviarFormulario();
                }
                return;
            }

            Swal.fire({
                title: 'Confirmar',
                text: mensaje,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarFormulario();
                }
            });
        };

        if (nuevoEstado === 'cancelada' && motivoInput) {
            if (typeof Swal === 'undefined') {
                const respuesta = window.prompt(`${mensaje}\n\nDescribe el motivo de la cancelación:`);
                if (respuesta === null) {
                    return;
                }
                const texto = String(respuesta).trim();
                if (texto.length < 5) {
                    window.alert('Describe el motivo de la cancelación con al menos 5 caracteres.');
                    return;
                }
                const passwordRespuesta = window.prompt('Para confirmar la cancelación, ingresa tu contraseña actual:');
                if (passwordRespuesta === null) {
                    return;
                }
                const passwordTexto = String(passwordRespuesta);
                if (passwordTexto.trim() === '') {
                    window.alert('Debes ingresar tu contraseña para confirmar la cancelación.');
                    return;
                }
                const motivoFinal = texto.length > 500 ? texto.slice(0, 500) : texto;
                motivoInput.value = motivoFinal;
                if (passwordInput) {
                    passwordInput.value = passwordTexto;
                }
                enviarFormulario();
                return;
            }

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            }[char] || char));
            const mensajeSeguro = escapeHtml(mensaje);

            Swal.fire({
                title: 'Cancelar solicitud',
                html: `
                    <p class="mb-3 text-start">${mensajeSeguro}</p>
                    <div class="text-start">
                        <label for="swalMotivoCancelacion" class="form-label fw-semibold">Motivo de la cancelación</label>
                        <textarea id="swalMotivoCancelacion" class="swal2-textarea" rows="4" placeholder="Describe el motivo de la cancelación"></textarea>
                    </div>
                    <div class="text-start mt-3">
                        <label for="swalPasswordConfirmacion" class="form-label fw-semibold">Confirma tu contraseña</label>
                        <input type="password" id="swalPasswordConfirmacion" class="swal2-input" placeholder="Contraseña actual" autocomplete="new-password" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" maxlength="150">
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Cancelar solicitud',
                cancelButtonText: 'Cerrar',
                focusConfirm: false,
                preConfirm: () => {
                    const motivoField = document.getElementById('swalMotivoCancelacion');
                    const passwordField = document.getElementById('swalPasswordConfirmacion');
                    const texto = typeof motivoField?.value === 'string' ? motivoField.value.trim() : '';
                    if (texto.length < 5) {
                        Swal.showValidationMessage('Describe el motivo de la cancelación (mínimo 5 caracteres).');
                        return false;
                    }
                    const passwordValor = typeof passwordField?.value === 'string' ? passwordField.value : '';
                    if (passwordValor.trim() === '') {
                        Swal.showValidationMessage('Ingresa tu contraseña para confirmar la cancelación.');
                        return false;
                    }
                    const motivoFinal = texto.length > 500 ? texto.slice(0, 500) : texto;
                    return {
                        motivo: motivoFinal,
                        password: passwordValor,
                    };
                },
                didOpen: () => {
                    const motivoField = document.getElementById('swalMotivoCancelacion');
                    const passwordField = document.getElementById('swalPasswordConfirmacion');
                    if (motivoField) {
                        motivoField.setAttribute('maxlength', '500');
                        motivoField.setAttribute('autocapitalize', 'sentences');
                        motivoField.focus();
                    }
                    if (passwordField) {
                        evitarAutocompletarPassword(passwordField);
                    }
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const motivoValor = typeof result.value.motivo === 'string' ? result.value.motivo.trim() : '';
                    const passwordValor = typeof result.value.password === 'string' ? result.value.password : '';
                    motivoInput.value = motivoValor;
                    if (passwordInput) {
                        passwordInput.value = passwordValor;
                    }
                    enviarFormulario();
                }
            });
            return;
        }

        confirmarEnvio();
    };

    document.addEventListener('submit', (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form || !form.classList.contains('formCambiarEstadoSolicitud')) {
            return;
        }
        handleCambioEstadoSolicitud(form, event);
    }, true);

    const manejarGenerarSolicitudDocx = async (boton) => {
        const solicitudId = boton.getAttribute('data-solicitud-id');
        const csrf = boton.getAttribute('data-csrf') || '';
        if (!solicitudId || boton.disabled) {
            return;
        }

        const originalHtml = boton.innerHTML;
        boton.disabled = true;
        boton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

        let progresoSolicitud = 0;
        let barraSolicitud = null;
        let intervaloSolicitud = null;

        const iniciarProgresoSolicitud = () => {
            if (typeof Swal === 'undefined') {
                return;
            }
            Swal.fire({
                title: 'Generando solicitud',
                html: '<div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="barraProgresoSolicitud"></div></div>',
                showConfirmButton: false,
                allowOutsideClick: false,
            });
            barraSolicitud = document.getElementById('barraProgresoSolicitud');
            intervaloSolicitud = window.setInterval(() => {
                progresoSolicitud = Math.min(progresoSolicitud + 12, 94);
                if (barraSolicitud) {
                    barraSolicitud.style.width = progresoSolicitud + '%';
                }
            }, 400);
        };

        const finalizarProgresoSolicitud = (delay = 400) => new Promise(resolve => {
            if (intervaloSolicitud) {
                clearInterval(intervaloSolicitud);
                intervaloSolicitud = null;
            }
            if (barraSolicitud) {
                barraSolicitud.style.width = '100%';
            }
            if (typeof Swal === 'undefined') {
                resolve();
                return;
            }
            setTimeout(() => {
                Swal.close();
                resolve();
            }, delay);
        });

        iniciarProgresoSolicitud();

        try {
            const params = new URLSearchParams();
            params.set('generarSolicitudDocx', '1');
            params.set('solicitud_id', solicitudId);
            params.set('csrf_token', csrf);

            const response = await fetch('index.php?ruta=solicitudes', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: params.toString(),
            });

            let data;
            try {
                data = await response.json();
            } catch (error) {
                throw new Error('Respuesta inválida del servidor.');
            }

            if (!response.ok || !data || data.status !== 'ok') {
                console.error('Error al generar la solicitud DOCX', {
                    httpStatus: response.status,
                    response: data
                });
                if (data && data.error_details) {
                    console.error('Detalles del error (solicitud):', data.error_details);
                }
                if (data && data.error_trace) {
                    console.error('Traza del error (solicitud):', data.error_trace);
                }
                const mensaje = data && data.message ? data.message : 'No se pudo generar la solicitud.';
                await finalizarProgresoSolicitud(0);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', mensaje, 'error');
                }
                return;
            }

            await finalizarProgresoSolicitud();
            if (data.docx) {
                const url = data.docx + (data.docx.includes('?') ? '&' : '?') + 't=' + Date.now();
                window.open(url, '_blank');
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Listo', 'La solicitud se generó correctamente.', 'success');
                }
            } else if (typeof Swal !== 'undefined') {
                Swal.fire('Aviso', 'La solicitud se generó pero no se recibió el archivo.', 'warning');
            }
        } catch (error) {
            console.error('Error al generar la solicitud', error);
            await finalizarProgresoSolicitud(0);
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', error.message || 'No se pudo generar la solicitud.', 'error');
            }
        } finally {
            boton.disabled = false;
            boton.innerHTML = originalHtml;
        }
    };

    document.addEventListener('click', (event) => {
        const boton = event.target.closest('.btnGenerarSolicitudDocx');
        if (!boton) {
            return;
        }
        event.preventDefault();
        manejarGenerarSolicitudDocx(boton);
    });

    const modalPlaceholdersEl = document.getElementById('modalPlaceholdersSolicitud');
    const contenedorPlaceholders = modalPlaceholdersEl ? modalPlaceholdersEl.querySelector('[data-placeholder-list]') : null;
    const modalPlaceholders = (modalPlaceholdersEl && typeof bootstrap !== 'undefined' && bootstrap.Modal)
        ? bootstrap.Modal.getOrCreateInstance(modalPlaceholdersEl)
        : null;
    const manejarVerPlaceholdersSolicitud = async (boton) => {
        const solicitudId = boton.getAttribute('data-solicitud-id');
        if (!solicitudId || boton.disabled || !contenedorPlaceholders) {
            return;
        }

        const originalHtml = boton.innerHTML;
        boton.disabled = true;
        boton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

        try {
            const params = new URLSearchParams();
            params.set('ruta', 'solicitudes');
            params.set('obtenerPlaceholdersSolicitud', '1');
            params.set('solicitud_id', solicitudId);
            params.set('t', Date.now().toString());

            const response = await fetch('index.php?' + params.toString(), {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            let data;
            try {
                data = await response.json();
            } catch (error) {
                throw new Error('Respuesta inválida del servidor.');
            }

            if (!response.ok || !data || data.status !== 'ok') {
                console.error('Error al obtener los placeholders de la solicitud', {
                    httpStatus: response.status,
                    response: data
                });
                const mensaje = data && data.message ? data.message : 'No se pudieron obtener los placeholders.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', mensaje, 'error');
                }
                return;
            }

            const placeholders = Array.isArray(data.placeholders) ? data.placeholders : [];
            contenedorPlaceholders.innerHTML = '';

            const total = data.total || placeholders.length;
            const resumen = document.createElement('p');
            resumen.className = 'small text-muted';
            resumen.textContent = 'Total de placeholders: ' + total;
            contenedorPlaceholders.appendChild(resumen);

            if (!placeholders.length) {
                const vacio = document.createElement('p');
                vacio.className = 'text-muted';
                vacio.textContent = 'No hay placeholders registrados para esta solicitud.';
                contenedorPlaceholders.appendChild(vacio);
            } else {
                const tabla = document.createElement('table');
                tabla.className = 'table table-striped table-hover table-sm align-middle mb-0';

                const thead = document.createElement('thead');
                const filaEncabezado = document.createElement('tr');
                const thClave = document.createElement('th');
                thClave.className = 'text-uppercase small';
                thClave.textContent = 'Placeholder';
                const thValor = document.createElement('th');
                thValor.className = 'text-uppercase small';
                thValor.textContent = 'Valor de ejemplo';
                filaEncabezado.appendChild(thClave);
                filaEncabezado.appendChild(thValor);
                thead.appendChild(filaEncabezado);

                const tbody = document.createElement('tbody');

                placeholders.forEach(item => {
                    const fila = document.createElement('tr');
                    const celdaClave = document.createElement('td');
                    celdaClave.className = 'font-monospace';
                    celdaClave.textContent = item && item.clave ? item.clave : '';
                    const celdaValor = document.createElement('td');
                    const valor = item ? item.valor : '';
                    celdaValor.textContent = valor !== undefined && valor !== null && valor !== '' ? valor : '—';
                    fila.appendChild(celdaClave);
                    fila.appendChild(celdaValor);
                    tbody.appendChild(fila);
                });

                tabla.appendChild(thead);
                tabla.appendChild(tbody);
                contenedorPlaceholders.appendChild(tabla);
            }

            if (modalPlaceholders) {
                modalPlaceholders.show();
            }
        } catch (error) {
            console.error('Error al obtener los placeholders de la solicitud', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', error.message || 'No se pudieron obtener los placeholders.', 'error');
            }
        } finally {
            boton.disabled = false;
            boton.innerHTML = originalHtml;
        }
    };

    if (modalPlaceholdersEl && contenedorPlaceholders) {
        document.addEventListener('click', (event) => {
            const boton = event.target.closest('.btnVerPlaceholdersSolicitud');
            if (!boton) {
                return;
            }
            event.preventDefault();
            manejarVerPlaceholdersSolicitud(boton);
        });
    }

    const modalClienteCoincidenteEl = document.getElementById('modalClienteCoincidenteSolicitud');
    const modalClienteCoincidente = (modalClienteCoincidenteEl && typeof bootstrap !== 'undefined' && bootstrap.Modal)
        ? bootstrap.Modal.getOrCreateInstance(modalClienteCoincidenteEl)
        : null;
    const mensajeClienteCoincidente = modalClienteCoincidenteEl
        ? modalClienteCoincidenteEl.querySelector('[data-mensaje-cliente]')
        : null;
    const detalleClienteCoincidente = modalClienteCoincidenteEl
        ? modalClienteCoincidenteEl.querySelector('[data-detalle-cliente]')
        : null;
    const btnConfirmarClienteCoincidente = document.getElementById('btnConfirmarClienteCoincidente');

    if (modalClienteCoincidenteEl && modalClienteCoincidenteEl.addEventListener) {
        modalClienteCoincidenteEl.addEventListener('hidden.bs.modal', () => {
            if (detalleClienteCoincidente) {
                detalleClienteCoincidente.innerHTML = '';
            }
            if (mensajeClienteCoincidente) {
                mensajeClienteCoincidente.textContent = '';
            }
            if (btnConfirmarClienteCoincidente) {
                delete btnConfirmarClienteCoincidente.dataset.urlCliente;
                delete btnConfirmarClienteCoincidente.dataset.urlFallback;
                btnConfirmarClienteCoincidente.disabled = false;
            }
        });
    }

    document.addEventListener('click', (event) => {
        const boton = event.target.closest('.btnCrearContratoSolicitud');
        if (!boton) {
            return;
        }
        event.preventDefault();

        const urlBase = boton.getAttribute('data-url-base') || boton.getAttribute('href') || '';
        const clienteId = boton.getAttribute('data-cliente-id') || '';

        if (!clienteId) {
            if (urlBase) {
                window.location.href = urlBase;
            }
            return;
        }

        const urlCliente = boton.getAttribute('data-url-cliente') || '';

        if (!modalClienteCoincidente) {
            const destino = urlCliente || urlBase;
            if (destino) {
                window.location.href = destino;
            }
            return;
        }

        const nombre = boton.getAttribute('data-cliente-nombre') || '';
        const estado = boton.getAttribute('data-cliente-estado') || '';
        const match = boton.getAttribute('data-cliente-match') || 'los datos capturados';
        const rfc = boton.getAttribute('data-cliente-rfc') || '';
        const curp = boton.getAttribute('data-cliente-curp') || '';

        if (mensajeClienteCoincidente) {
            mensajeClienteCoincidente.textContent = `Se detectó un cliente registrado que coincide por ${match}.`;
        }

        if (detalleClienteCoincidente) {
            detalleClienteCoincidente.innerHTML = '';
            const detalles = [];
            if (nombre) {
                detalles.push(`Cliente: ${nombre}`);
            }
            if (estado) {
                const estadoFormateado = estado.charAt(0).toUpperCase() + estado.slice(1);
                detalles.push(`Estado: ${estadoFormateado}`);
            }
            if (rfc) {
                detalles.push(`RFC: ${rfc}`);
            }
            if (curp) {
                detalles.push(`CURP: ${curp}`);
            }

            if (detalles.length) {
                detalles.forEach((texto) => {
                    const item = document.createElement('li');
                    item.textContent = texto;
                    detalleClienteCoincidente.appendChild(item);
                });
            }
        }

        if (btnConfirmarClienteCoincidente) {
            btnConfirmarClienteCoincidente.dataset.urlCliente = urlCliente || '';
            btnConfirmarClienteCoincidente.dataset.urlFallback = urlBase || '';
            btnConfirmarClienteCoincidente.disabled = !urlCliente && !urlBase;
        }

        modalClienteCoincidente.show();
    });

    if (btnConfirmarClienteCoincidente) {
        btnConfirmarClienteCoincidente.addEventListener('click', () => {
            const destino = btnConfirmarClienteCoincidente.dataset.urlCliente
                || btnConfirmarClienteCoincidente.dataset.urlFallback
                || '';
            if (destino) {
                window.location.href = destino;
            }
        });
    }

    const modalPlaceholdersContratoEl = document.getElementById('modalPlaceholdersContrato');
    const contenedorPlaceholdersContrato = modalPlaceholdersContratoEl ? modalPlaceholdersContratoEl.querySelector('[data-placeholder-list]') : null;
    const modalPlaceholdersContrato = (modalPlaceholdersContratoEl && typeof bootstrap !== 'undefined' && bootstrap.Modal)
        ? bootstrap.Modal.getOrCreateInstance(modalPlaceholdersContratoEl)
        : null;
    const manejarVerPlaceholdersContrato = async (boton) => {
        const contratoId = boton.getAttribute('data-contrato-id');
        if (!contratoId || boton.disabled || !contenedorPlaceholdersContrato) {
            return;
        }

        const originalHtml = boton.innerHTML;
        boton.disabled = true;
        boton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

        try {
            const params = new URLSearchParams();
            params.set('ruta', 'contratos');
            params.set('obtenerPlaceholdersContrato', '1');
            params.set('contrato_id', contratoId);
            params.set('t', Date.now().toString());

            const response = await fetch('index.php?' + params.toString(), {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            let data;
            try {
                data = await response.json();
            } catch (error) {
                throw new Error('Respuesta inválida del servidor.');
            }

            if (!response.ok || !data || data.status !== 'ok') {
                console.error('Error al obtener los placeholders del contrato', {
                    httpStatus: response.status,
                    response: data
                });
                const mensaje = data && data.message ? data.message : 'No se pudieron obtener los placeholders.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', mensaje, 'error');
                }
                return;
            }

            const placeholders = Array.isArray(data.placeholders) ? data.placeholders : [];
            contenedorPlaceholdersContrato.innerHTML = '';

            const total = data.total || placeholders.length;
            const resumen = document.createElement('p');
            resumen.className = 'small text-muted';
            resumen.textContent = 'Total de placeholders: ' + total;
            contenedorPlaceholdersContrato.appendChild(resumen);

            if (!placeholders.length) {
                const vacio = document.createElement('p');
                vacio.className = 'text-muted';
                vacio.textContent = 'No hay placeholders registrados para este contrato.';
                contenedorPlaceholdersContrato.appendChild(vacio);
            } else {
                const tabla = document.createElement('table');
                tabla.className = 'table table-striped table-hover table-sm align-middle mb-0';

                const thead = document.createElement('thead');
                const filaEncabezado = document.createElement('tr');
                const thClave = document.createElement('th');
                thClave.className = 'text-uppercase small';
                thClave.textContent = 'Placeholder';
                const thValor = document.createElement('th');
                thValor.className = 'text-uppercase small';
                thValor.textContent = 'Valor de ejemplo';
                filaEncabezado.appendChild(thClave);
                filaEncabezado.appendChild(thValor);
                thead.appendChild(filaEncabezado);

                const tbody = document.createElement('tbody');

                placeholders.forEach(item => {
                    const fila = document.createElement('tr');
                    const celdaClave = document.createElement('td');
                    celdaClave.className = 'font-monospace';
                    celdaClave.textContent = item && item.clave ? item.clave : '';
                    const celdaValor = document.createElement('td');
                    const valor = item ? item.valor : '';
                    celdaValor.textContent = valor !== undefined && valor !== null && valor !== '' ? valor : '—';
                    fila.appendChild(celdaClave);
                    fila.appendChild(celdaValor);
                    tbody.appendChild(fila);
                });

                tabla.appendChild(thead);
                tabla.appendChild(tbody);
                contenedorPlaceholdersContrato.appendChild(tabla);
            }

            if (modalPlaceholdersContrato) {
                modalPlaceholdersContrato.show();
            }
        } catch (error) {
            console.error('Error al obtener los placeholders del contrato', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', error.message || 'No se pudieron obtener los placeholders.', 'error');
            }
        } finally {
            boton.disabled = false;
            boton.innerHTML = originalHtml;
        }
    };

    if (modalPlaceholdersContratoEl && contenedorPlaceholdersContrato) {
        document.addEventListener('click', (event) => {
            const boton = event.target.closest('.btnVerPlaceholdersContrato');
            if (!boton) {
                return;
            }
            event.preventDefault();
            manejarVerPlaceholdersContrato(boton);
        });
    }

    const inicializarPagoAnualSwitch = (switchEl) => {
        if (!switchEl) {
            return;
        }

        const targetSelector = switchEl.getAttribute('data-target');
        const container = targetSelector ? document.querySelector(targetSelector) : null;
        const fields = container ? container.querySelectorAll('[data-pago-anual-field]') : [];
        const hiddenInput = document.getElementById('usaPagoAnualHidden');
        const enforceRequired = switchEl.getAttribute('data-enforce-required') === '1';
        const esSoloLectura = switchEl.dataset.readonly === 'true';

        const actualizarHidden = (activo) => {
            if (hiddenInput) {
                hiddenInput.value = activo ? '1' : '0';
            }
        };

        const limpiarCampo = (field) => {
            if (!field) {
                return;
            }
            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = false;
            } else {
                field.value = '';
            }
            const eventos = ['input', 'change'];
            eventos.forEach(evento => {
                field.dispatchEvent(new Event(evento, { bubbles: true }));
            });
        };

        const aplicarEstado = (activo, opciones = {}) => {
            const esInicial = !!opciones.init;
            if (container) {
                container.classList.toggle('d-none', !activo);
                container.setAttribute('aria-hidden', activo ? 'false' : 'true');
            }

            if (switchEl) {
                switchEl.setAttribute('aria-expanded', activo ? 'true' : 'false');
            }

            fields.forEach((field) => {
                if (field.hasAttribute('data-pago-anual-required')) {
                    field.required = enforceRequired && activo && !esSoloLectura;
                }
                if (!activo && !esInicial && !esSoloLectura) {
                    limpiarCampo(field);
                }
            });

            actualizarHidden(activo);
        };

        aplicarEstado(switchEl.checked, { init: true });

        if (switchEl.disabled || esSoloLectura) {
            actualizarHidden(switchEl.checked);
            return;
        }

        switchEl.addEventListener('change', () => {
            aplicarEstado(switchEl.checked);
        });
    };

    const inicializarAlbaceaSwitch = (switchEl) => {
        if (!switchEl) {
            return;
        }
        const targetSelector = switchEl.getAttribute('data-albacea-target');
        const hiddenId = switchEl.getAttribute('data-albacea-input');
        const container = targetSelector ? document.querySelector(targetSelector) : null;
        const hiddenInput = hiddenId ? document.getElementById(hiddenId) : null;
        const campos = container ? container.querySelectorAll('[data-albacea-field]') : [];
        const esSoloLectura = switchEl.dataset.readonly === 'true';
        const enforceRequired = switchEl.dataset.enforceRequired === '1';

        const aplicarEstado = (activo, opciones = {}) => {
            const esInicial = !!opciones.init;
            if (container) {
                container.classList.toggle('d-none', !activo);
                container.setAttribute('aria-hidden', activo ? 'false' : 'true');
            }
            if (hiddenInput) {
                hiddenInput.value = activo ? '1' : '0';
            }
            switchEl.setAttribute('aria-expanded', activo ? 'true' : 'false');
            campos.forEach(campo => {
                const requerido = campo.hasAttribute('data-albacea-required');
                if (!esSoloLectura) {
                    campo.toggleAttribute('readonly', !activo && campo.hasAttribute('data-albacea-lock'));
                }
                if (requerido) {
                    campo.required = enforceRequired && activo && !esSoloLectura;
                }
                if (!activo && !esInicial && !esSoloLectura) {
                    campo.value = '';
                }
            });
        };

        aplicarEstado(switchEl.checked, { init: true });

        if (switchEl.disabled || esSoloLectura) {
            if (hiddenInput) {
                hiddenInput.value = switchEl.checked ? '1' : '0';
            }
            return;
        }

        switchEl.addEventListener('change', () => {
            if (switchEl.checked) {
                if (typeof Swal === 'undefined') {
                    aplicarEstado(true);
                    return;
                }
                Swal.fire({
                    icon: 'question',
                    title: 'Confirmar albacea',
                    text: 'Al activar esta opción la solicitud y contrato contarán con albacea. ¿Desea continuar?',
                    showCancelButton: true,
                    confirmButtonText: 'Continuar',
                    cancelButtonText: 'Cancelar'
                }).then(resultado => {
                    if (resultado.isConfirmed) {
                        aplicarEstado(true);
                    } else {
                        switchEl.checked = false;
                        aplicarEstado(false);
                    }
                });
            } else {
                aplicarEstado(false);
            }
        });
    };

    document.querySelectorAll('[data-role="pago-anual-switch"]').forEach(inicializarPagoAnualSwitch);
    document.querySelectorAll('[data-role="albacea-switch"]').forEach(inicializarAlbaceaSwitch);

    /*
     * Formatear campos de precio en formularios de desarrollos.
     * Estos inputs muestran un símbolo de pesos en un grupo y permiten la entrada con
     * separadores de miles. Se formatean al escribir y se limpian antes de enviar el formulario.
     */
    const priceInputs = document.querySelectorAll('input[name="precio_lote"], input[name="precio_total"], #crearPrecioLote, #crearPrecioTotal, #editarPrecioLote, #editarPrecioTotal');
    priceInputs.forEach(input => {
        // Formatear al cargar si ya tiene valor
        if (input.value) {
            input.value = formatNumber(input.value);
        }
        // Al enfocarse, eliminar formato para facilitar la edición
        input.addEventListener('focus', function () {
            const cleaned = cleanNumberString(this.value);
            this.value = cleaned ? parseFloat(cleaned).toString() : '';
        });
        // Al perder el foco, aplicar formato con separadores y dos decimales
        input.addEventListener('blur', function () {
            const cleaned = cleanNumberString(this.value);
            if (cleaned) {
                this.value = formatNumber(cleaned);
            } else {
                this.value = '';
            }
        });
    });

    // Limpia los separadores antes de enviar formularios de desarrollos
    if (formDesarrollo) {
        formDesarrollo.addEventListener('submit', function () {
            const precioLote = formDesarrollo.querySelector('input[name="precio_lote"]');
            const precioTotal = formDesarrollo.querySelector('input[name="precio_total"]');
            if (precioLote) precioLote.value = cleanNumberString(precioLote.value);
            if (precioTotal) precioTotal.value = cleanNumberString(precioTotal.value);
        }, true);
    }
    if (formEditarDesarrollo) {
        formEditarDesarrollo.addEventListener('submit', function () {
            const precioLote = formEditarDesarrollo.querySelector('input[name="precio_lote"]');
            const precioTotal = formEditarDesarrollo.querySelector('input[name="precio_total"]');
            if (precioLote) precioLote.value = cleanNumberString(precioLote.value);
            if (precioTotal) precioTotal.value = cleanNumberString(precioTotal.value);
        }, true);
    }

    /*
     * Gestión de parámetros: variables y plantillas
     */
    const interpretarRespuestaParametros = (raw, opciones = {}) => {
        const config = {
            icon: 'success',
            title: opciones.successTitle || 'Listo',
            text: opciones.successText || 'La operación se completó correctamente.',
            reload: opciones.reload !== undefined ? opciones.reload : true
        };

        const texto = typeof raw === 'string' ? raw.trim() : (raw ?? '').toString().trim();

        if (texto !== '') {
            try {
                const parsed = JSON.parse(texto);
                if (parsed && typeof parsed === 'object') {
                    if (typeof parsed.title === 'string' && parsed.title.trim() !== '') {
                        config.title = parsed.title.trim();
                    }
                    const mensaje = (typeof parsed.message === 'string' ? parsed.message : parsed.text);
                    if (typeof mensaje === 'string' && mensaje.trim() !== '') {
                        config.text = mensaje.trim();
                    }
                    if (typeof parsed.icon === 'string' && parsed.icon.trim() !== '') {
                        config.icon = parsed.icon.trim();
                    }
                    const status = typeof parsed.status === 'string' ? parsed.status.toLowerCase() : '';
                    if (status === 'error' || parsed.success === false) {
                        config.icon = 'error';
                        config.reload = false;
                    } else if (parsed.success === true && config.icon !== 'error') {
                        config.icon = 'success';
                    }
                    if (typeof parsed.reload === 'boolean') {
                        config.reload = parsed.reload;
                    }
                    if (config.icon !== 'success') {
                        config.reload = false;
                    }
                    return config;
                }
            } catch (error) {
                // No es JSON, continuar con la interpretación por códigos.
            }
        }

        const catalogo = {
            ok: {},
            success: {},
            'error': {
                icon: 'error',
                title: 'No se pudo completar',
                text: 'Ocurrió un error al procesar la solicitud.',
                reload: false
            },
            'error-permiso': {
                icon: 'warning',
                title: 'Permiso restringido',
                text: 'No cuentas con permisos para realizar esta acción.',
                reload: false
            },
            'error-token': {
                icon: 'warning',
                title: 'Sesión expirada',
                text: 'Actualiza la página e inténtalo nuevamente.',
                reload: false
            },
            'error-datos': {
                icon: 'warning',
                title: 'Datos incompletos',
                text: 'Revisa la información proporcionada e inténtalo de nuevo.',
                reload: false
            },
            'error-archivo': {
                icon: 'error',
                title: 'Archivo no recibido',
                text: 'Selecciona un archivo válido y vuelve a intentarlo.',
                reload: false
            },
            'error-tamano': {
                icon: 'warning',
                title: 'Archivo demasiado grande',
                text: 'El archivo supera el límite permitido (150 MB).',
                reload: false
            },
            'error-extension': {
                icon: 'warning',
                title: 'Extensión no permitida',
                text: 'Solo se admiten archivos con extensión .docx.',
                reload: false
            },
            'error-guardar': {
                icon: 'error',
                title: 'No se pudo guardar el archivo',
                text: 'Inténtalo nuevamente más tarde.',
                reload: false
            }
        };

        const clave = texto.toLowerCase();
        if (catalogo[clave]) {
            return { ...config, ...catalogo[clave] };
        }

        if (clave.startsWith('ok')) {
            return config;
        }

        if (clave.startsWith('error')) {
            return { ...config, ...catalogo['error'] };
        }

        if (config.icon !== 'success') {
            config.reload = false;
        }

        return config;
    };

    const mostrarAlertaParametros = (config) => {
        const parametros = config && typeof config === 'object' ? config : {};
        const { reload = false, ...alerta } = parametros;
        const icono = typeof alerta.icon === 'string' ? alerta.icon.toLowerCase() : 'info';
        const titulo = alerta.title || (icono === 'success' ? 'Listo' : 'Aviso');
        const texto = alerta.text || '';
        const html = typeof alerta.html === 'string' ? alerta.html : undefined;

        const ejecutarRecarga = () => {
            if (reload) {
                window.location.reload();
            }
        };

        if (icono === 'success') {
            return Promise.resolve(
                mostrarSwalSuccess(titulo, html !== undefined ? html : texto, {
                    ...alerta,
                    icon: 'success',
                })
            ).then(ejecutarRecarga);
        }

        if (typeof Swal === 'undefined') {
            ejecutarRecarga();
            return Promise.resolve();
        }

        const opcionesSwal = {
            icon: icono || 'info',
            title: titulo,
            confirmButtonText: typeof alerta.confirmButtonText === 'string' && alerta.confirmButtonText.trim() !== ''
                ? alerta.confirmButtonText
                : 'Aceptar',
            allowOutsideClick: typeof alerta.allowOutsideClick === 'boolean' ? alerta.allowOutsideClick : false,
            allowEscapeKey: typeof alerta.allowEscapeKey === 'boolean' ? alerta.allowEscapeKey : true,
        };

        if (html !== undefined && html !== '') {
            opcionesSwal.html = html;
        } else if (texto !== '') {
            opcionesSwal.text = texto;
        }

        return Swal.fire(opcionesSwal).then(ejecutarRecarga);
    };

    const mostrarErrorConexionParametros = () => mostrarAlertaParametros({
        icon: 'error',
        title: 'Error de conexión',
        text: 'No se pudo conectar con el servidor. Intenta nuevamente.',
        reload: false
    });

    const procesarFormularioParametros = (form, opciones) => {
        if (!form) {
            return;
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            fetch('index.php?ruta=parametros', {
                method: 'POST',
                body: formData
            })
                .then((respuesta) => respuesta.text())
                .then((texto) => mostrarAlertaParametros(
                    interpretarRespuestaParametros(texto, opciones)
                ))
                .catch(() => mostrarErrorConexionParametros());
        });
    };

    // Formulario agregar nacionalidad o tipo de contrato (comparten clase)
    const formAddNacionalidad = document.getElementById('formAddNacionalidad');
    const formAddTipo = document.getElementById('formAddTipo');
    procesarFormularioParametros(formAddNacionalidad, {
        successTitle: 'Guardado',
        successText: 'Registro añadido correctamente.'
    });
    procesarFormularioParametros(formAddTipo, {
        successTitle: 'Guardado',
        successText: 'Registro añadido correctamente.'
    });

    // Botones editar variable
    const formEditarVariable = document.getElementById('formEditarVariable');
    if (formEditarVariable) {
        document.addEventListener('click', (event) => {
            const btn = event.target.closest('.btnEditarVariable');
            if (!btn || !btn.isConnected) {
                return;
            }

            const id = btn.getAttribute('data-id') || '';
            const ident = btn.getAttribute('data-identificador') || '';
            const nombre = btn.getAttribute('data-nombre') || '';

            const idInput = document.getElementById('editarVariableId');
            const identificadorInput = document.getElementById('editarVariableIdentificador');
            const nombreInput = document.getElementById('editarVariableNombre');

            if (idInput) {
                idInput.value = id;
            }
            if (identificadorInput) {
                identificadorInput.value = ident;
            }
            if (nombreInput) {
                nombreInput.value = nombre;
            }
        });

        formEditarVariable.addEventListener('submit', (event) => {
            event.preventDefault();
            Swal.fire({
                title: '¿Estás seguro de modificar los datos?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, modificar',
                cancelButtonText: 'Cancelar'
            }).then((resultado) => {
                if (!resultado.isConfirmed) {
                    return;
                }
                const formData = new FormData(formEditarVariable);
                fetch('index.php?ruta=parametros', {
                    method: 'POST',
                    body: formData
                })
                    .then((respuesta) => respuesta.text())
                    .then((texto) => mostrarAlertaParametros(
                        interpretarRespuestaParametros(texto, {
                            successTitle: 'Guardado',
                            successText: 'Registro actualizado correctamente.'
                        })
                    ))
                    .catch(() => mostrarErrorConexionParametros());
            });
        });
    }

    // Subir plantilla
    const formSubirPlantilla = document.getElementById('formSubirPlantilla');
    if (formSubirPlantilla) {
        formSubirPlantilla.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(formSubirPlantilla);
            fetch('index.php?ruta=parametros', {
                method: 'POST',
                body: formData
            })
                .then((respuesta) => respuesta.text())
                .then((texto) => mostrarAlertaParametros(
                    interpretarRespuestaParametros(texto, {
                        successTitle: 'Guardado',
                        successText: 'Plantilla subida correctamente.'
                    })
                ))
                .catch(() => mostrarErrorConexionParametros());
        });
    }

    // Editar plantilla
    const formEditarPlantilla = document.getElementById('formEditarPlantilla');
    if (formEditarPlantilla) {
        document.addEventListener('click', (event) => {
            const btn = event.target.closest('.btnEditarPlantilla');
            if (!btn || !btn.isConnected) {
                return;
            }

            const modalIdInput = document.getElementById('editarPlantillaId');
            const modalTipoSelect = document.getElementById('editarPlantillaTipo');

            if (modalIdInput) {
                modalIdInput.value = btn.getAttribute('data-id') || '';
            }
            if (modalTipoSelect) {
                modalTipoSelect.value = btn.getAttribute('data-tipo-id') || '';
            }
        });

        formEditarPlantilla.addEventListener('submit', (event) => {
            event.preventDefault();
            Swal.fire({
                title: '¿Actualizar plantilla?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, actualizar',
                cancelButtonText: 'Cancelar'
            }).then((resultado) => {
                if (!resultado.isConfirmed) {
                    return;
                }
                const formData = new FormData(formEditarPlantilla);
                fetch('index.php?ruta=parametros', {
                    method: 'POST',
                    body: formData
                })
                    .then((respuesta) => respuesta.text())
                    .then((texto) => mostrarAlertaParametros(
                        interpretarRespuestaParametros(texto, {
                            successTitle: 'Actualizado',
                            successText: 'Plantilla actualizada correctamente.'
                        })
                    ))
                    .catch(() => mostrarErrorConexionParametros());
            });
        });
    }

    const formEditarPlantillaSolicitud = document.getElementById('formEditarPlantillaSolicitud');
    if (formEditarPlantillaSolicitud) {
        const idInputSolicitud = document.getElementById('editarPlantillaSolicitudId');
        const tipoSelectSolicitud = document.getElementById('editarPlantillaSolicitudTipo');
        const nombreActualSolicitud = document.getElementById('editarPlantillaSolicitudNombre');

        document.addEventListener('click', (event) => {
            const btn = event.target.closest('.btnEditarPlantillaSolicitud');
            if (!btn || !btn.isConnected) {
                return;
            }

            if (idInputSolicitud) {
                idInputSolicitud.value = btn.getAttribute('data-id') || '';
            }
            if (tipoSelectSolicitud) {
                tipoSelectSolicitud.value = btn.getAttribute('data-tipo') || '';
            }
            if (nombreActualSolicitud) {
                nombreActualSolicitud.value = btn.getAttribute('data-nombre') || '';
            }
        });

        formEditarPlantillaSolicitud.addEventListener('submit', (event) => {
            event.preventDefault();
            Swal.fire({
                title: '¿Actualizar plantilla?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, actualizar',
                cancelButtonText: 'Cancelar'
            }).then((resultado) => {
                if (!resultado.isConfirmed) {
                    return;
                }
                const formData = new FormData(formEditarPlantillaSolicitud);
                fetch('index.php?ruta=parametros', {
                    method: 'POST',
                    body: formData
                })
                    .then((respuesta) => respuesta.text())
                    .then((texto) => mostrarAlertaParametros(
                        interpretarRespuestaParametros(texto, {
                            successTitle: 'Actualizado',
                            successText: 'Plantilla actualizada correctamente.'
                        })
                    ))
                    .catch(() => mostrarErrorConexionParametros());
            });
        });
    }



    /*
     * Generación de contratos: manejar clic en botón
     *
     * Al pulsar el botón de generación se muestra una barra de progreso utilizando
     * SweetAlert2. Se envía una solicitud fetch al endpoint AJAX y, una vez
     * completada la generación, se descarga automáticamente el archivo ZIP.
     */
    const manejarGenerarContrato = (boton) => {
        const contratoId = boton.getAttribute('data-contrato-id');
        if (!contratoId || boton.disabled) {
            return;
        }

        if (typeof Swal === 'undefined') {
            window.alert('No se puede generar el contrato porque no está disponible la librería de notificaciones.');
            return;
        }

        Swal.fire({
            title: 'Generando contrato',
            html: '<div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="barraProgresoContrato"></div></div>',
            showConfirmButton: false,
            allowOutsideClick: false
        });
        const barra = document.getElementById('barraProgresoContrato');
        let progreso = 0;
        const intervalo = setInterval(() => {
            progreso = Math.min(progreso + 10, 90);
            if (barra) barra.style.width = progreso + '%';
        }, 500);

        fetch('ajax/generar_contrato.php?contrato_id=' + contratoId)
            .then(async response => {
                let data = null;
                try {
                    data = await response.json();
                } catch (error) {
                    console.error('Respuesta inválida al generar contrato', error);
                }
                return { ok: response.ok, status: response.status, payload: data };
            })
            .then(({ ok, status, payload }) => {
                clearInterval(intervalo);
                if (barra) barra.style.width = '100%';
                if (ok && payload && payload.status === 'ok' && payload.docx) {
                    setTimeout(() => {
                        Swal.close();
                        const enlace = document.createElement('a');
                        enlace.href = payload.docx;
                        enlace.download = payload.nombre || 'contrato.docx';
                        document.body.appendChild(enlace);
                        enlace.click();
                        enlace.remove();
                    }, 600);
                } else {
                    console.error('Error al generar contrato', {
                        httpStatus: status,
                        response: payload
                    });
                    if (payload && payload.error_details) {
                        console.error('Detalles del error (contrato):', payload.error_details);
                    }
                    if (payload && payload.error_trace) {
                        console.error('Traza del error (contrato):', payload.error_trace);
                    }
                    const mensaje = payload && (payload.msg || payload.message) ? (payload.msg || payload.message) : 'No se pudo generar el contrato';
                    Swal.fire('Error', mensaje, 'error');
                }
            })
            .catch((error) => {
                clearInterval(intervalo);
                console.error('Error de red al generar contrato', error);
                Swal.fire('Error', 'No se pudo generar el contrato', 'error');
            });
    };

    document.addEventListener('click', (event) => {
        const boton = event.target.closest('.btnGenerarContrato');
        if (!boton) {
            return;
        }
        event.preventDefault();
        manejarGenerarContrato(boton);
    });


    const selectDesarrolloSolicitud = document.getElementById('selectDesarrolloSolicitud');
    if (selectDesarrolloSolicitud) {
        const hiddenNombre = document.getElementById('desarrolloNombreSolicitud');
        const hiddenTipo = document.getElementById('desarrolloTipoContratoSolicitud');

        const actualizarHiddenDesarrollo = () => {
            const option = selectDesarrolloSolicitud.options[selectDesarrolloSolicitud.selectedIndex];
            if (!option) {
                if (hiddenNombre) hiddenNombre.value = '';
                if (hiddenTipo) hiddenTipo.value = '';
                return;
            }

            if (hiddenNombre) {
                hiddenNombre.value = option.getAttribute('data-nombre') || '';
            }
            if (hiddenTipo) {
                hiddenTipo.value = option.getAttribute('data-tipo') || '';
            }
        };

        actualizarHiddenDesarrollo();
        selectDesarrolloSolicitud.addEventListener('change', actualizarHiddenDesarrollo);
    }

    
    
    /*
     * === Gestión de formulario Crear Contrato (página completa) ===
     * Este bloque se activa en la página crearContrato.php. Maneja la selección del desarrollo,
     * muestra el tipo de contrato y superficie, gestiona la selección de fracciones (lotes) y
     * permite al usuario agregar manualmente fracciones como etiquetas. También actualiza en tiempo
     * real los cálculos financieros (saldo y penalización) y convierte los números a letras para
     * los campos "fixed" utilizando el servicio AJAX numero_a_letras.php.
     * Configuración de la página Crear contrato. Esta sección gestiona la
     * selección de desarrollo, la carga de lotes disponibles, el ingreso de
     * fracciones manuales y las operaciones aritméticas y de conversión
     * numérica a letras para los campos financieros. Utiliza IDs de la vista
     * crearContrato.php.
     */
    (function () {
        const formCrearContrato = document.getElementById('formCrearContratoCompleto');
        if (formCrearContrato && formCrearContrato.dataset.readonly === '1') {
            return;
        }

        const inputSolicitudManual = document.getElementById('solicitudIdOrigenInput');
        const alertaSolicitudManual = document.getElementById('contratoSolicitudVinculadaManual');
        const textoSolicitudManual = document.getElementById('textoSolicitudVinculada');
        const detalleSolicitudManual = document.getElementById('detalleSolicitudVinculada');
        const btnVincularSolicitud = document.getElementById('btnVincularSolicitudContrato');
        const btnQuitarSolicitud = document.getElementById('btnQuitarSolicitudContrato');
        const modalBuscarSolicitudEl = document.getElementById('modalBuscarSolicitudContrato');
        const modalBuscarSolicitud = modalBuscarSolicitudEl && typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(modalBuscarSolicitudEl)
            : null;
        const contenedorTablaModal = modalBuscarSolicitudEl ? modalBuscarSolicitudEl.querySelector('[data-contenedor-tabla]') : null;
        const tablaResultadosModal = modalBuscarSolicitudEl ? modalBuscarSolicitudEl.querySelector('[data-resultados-solicitudes]') : null;
        const mensajeModal = modalBuscarSolicitudEl ? modalBuscarSolicitudEl.querySelector('[data-estado-modal]') : null;
        const botonReintentarModal = modalBuscarSolicitudEl ? modalBuscarSolicitudEl.querySelector('[data-reintentar-busqueda]') : null;
        const filtroRapidoSolicitudes = modalBuscarSolicitudEl ? modalBuscarSolicitudEl.querySelector('[data-filtro-rapido]') : null;

        let solicitudesModalDisponibles = [];
        let aplicarPrefillSolicitud = null;
        if (filtroRapidoSolicitudes) {
            filtroRapidoSolicitudes.value = '';
            filtroRapidoSolicitudes.disabled = true;
        }

        const obtenerPrefillSolicitudContrato = async (solicitudId) => {
            const idTexto = String(solicitudId ?? '').trim();
            if (!idTexto) {
                throw new Error('Identificador de solicitud inválido.');
            }

            const form = document.getElementById('formCrearContratoCompleto');
            const csrfInput = form ? form.querySelector('input[name="csrf_token"]') : null;
            const params = new URLSearchParams();
            params.set('obtenerPrefillSolicitud', '1');
            params.set('solicitud_id', idTexto);
            if (csrfInput && csrfInput.value) {
                params.set('csrf_token', csrfInput.value);
            }

            const response = await fetch('index.php?ruta=crearContrato', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: params.toString(),
            });

            const raw = await response.text();
            let data;
            try {
                data = raw ? JSON.parse(raw) : null;
            } catch (error) {
                throw new Error('Respuesta inválida del servidor.');
            }

            if (!response.ok || !data || data.status !== 'ok') {
                const mensaje = data && data.message
                    ? String(data.message)
                    : 'No fue posible obtener la solicitud seleccionada.';
                const detalle = new Error(mensaje);
                detalle.payload = data;
                throw detalle;
            }

            return data;
        };

        const formatearFechaCorta = (valor) => {
            const texto = String(valor ?? '').trim();
            if (!texto) {
                return '';
            }
            const fecha = texto.split(' ')[0];
            const partes = fecha.split('-');
            if (partes.length !== 3) {
                return texto;
            }
            return `${partes[2]}/${partes[1]}/${partes[0]}`;
        };

        let resumenSolicitudActual = null;

        const actualizarResumenSolicitud = (detalle) => {
            if (!alertaSolicitudManual || !textoSolicitudManual) {
                return;
            }

            const resumen = detalle && typeof detalle === 'object' ? detalle : null;
            resumenSolicitudActual = resumen && Number.isFinite(parseInt(String(resumen.id ?? '').trim(), 10))
                ? {
                    id: parseInt(String(resumen.id).trim(), 10),
                    folio: String(resumen.folio ?? '').trim(),
                    nombre: String(resumen.nombre ?? resumen.nombre_completo ?? '').trim(),
                    estado: String(resumen.estado ?? '').trim(),
                    created_at: String(resumen.created_at ?? '').trim(),
                }
                : null;

            if (!resumenSolicitudActual || resumenSolicitudActual.id <= 0) {
                textoSolicitudManual.textContent = '';
                if (detalleSolicitudManual) {
                    detalleSolicitudManual.textContent = '';
                    detalleSolicitudManual.classList.add('d-none');
                }
                alertaSolicitudManual.classList.add('d-none');
                return;
            }

            const partes = [`Solicitud #${resumenSolicitudActual.id}`];
            if (resumenSolicitudActual.folio) {
                partes.push(resumenSolicitudActual.folio);
            }
            if (resumenSolicitudActual.nombre) {
                partes.push(resumenSolicitudActual.nombre);
            }
            textoSolicitudManual.textContent = partes.join(' · ');
            alertaSolicitudManual.classList.remove('d-none');

            if (detalleSolicitudManual) {
                const extras = [];
                if (resumenSolicitudActual.estado) {
                    extras.push(`Estado: ${resumenSolicitudActual.estado.toUpperCase()}`);
                }
                if (resumenSolicitudActual.created_at) {
                    extras.push(`Creada: ${formatearFechaCorta(resumenSolicitudActual.created_at)}`);
                }
                if (extras.length > 0) {
                    detalleSolicitudManual.textContent = extras.join(' · ');
                    detalleSolicitudManual.classList.remove('d-none');
                } else {
                    detalleSolicitudManual.textContent = '';
                    detalleSolicitudManual.classList.add('d-none');
                }
            }
        };

        const establecerSolicitudSeleccionada = (solicitud) => {
            if (!inputSolicitudManual) {
                return;
            }

            const identificador = solicitud && solicitud.id ? parseInt(String(solicitud.id).trim(), 10) : 0;
            if (!Number.isFinite(identificador) || identificador <= 0) {
                inputSolicitudManual.value = '';
                inputSolicitudManual.removeAttribute('data-solicitud-resumen');
                actualizarResumenSolicitud(null);
                return;
            }

            const resumen = {
                id: identificador,
                folio: String(solicitud.folio ?? '').trim(),
                nombre: String(solicitud.nombre ?? solicitud.nombre_completo ?? '').trim(),
                estado: String(solicitud.estado ?? '').trim(),
                created_at: String(solicitud.created_at ?? '').trim(),
            };

            inputSolicitudManual.value = String(identificador);
            try {
                inputSolicitudManual.setAttribute('data-solicitud-resumen', JSON.stringify(resumen));
            } catch (error) {
                inputSolicitudManual.setAttribute('data-solicitud-resumen', '');
            }
            actualizarResumenSolicitud(resumen);
        };

        const vincularSolicitud = async (solicitud, triggerEl = null) => {
            const identificador = solicitud && solicitud.id ? parseInt(String(solicitud.id).trim(), 10) : 0;
            if (!Number.isFinite(identificador) || identificador <= 0) {
                const mensaje = 'Seleccione una solicitud válida para vincular.';
                actualizarCrearContratoFeedback('error', mensaje);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', mensaje, 'error');
                } else {
                    window.alert(mensaje);
                }
                return;
            }

            let originalHtml = '';
            if (triggerEl) {
                originalHtml = triggerEl.innerHTML;
                triggerEl.disabled = true;
                triggerEl.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                triggerEl.setAttribute('aria-busy', 'true');
            }

            try {
                const data = await obtenerPrefillSolicitudContrato(identificador);
                const resumen = data && typeof data.resumen === 'object' ? { ...data.resumen } : { id: identificador };
                const solicitudCruda = data && typeof data.solicitud === 'object' ? data.solicitud : {};

                if (!resumen.id) {
                    resumen.id = identificador;
                }
                if (!resumen.folio) {
                    resumen.folio = String(solicitudCruda.folio ?? solicitud.folio ?? '');
                }
                const nombreBase = resumen.nombre || resumen.nombre_completo || solicitudCruda.nombre_completo
                    || solicitudCruda.nombre || solicitud.nombre || solicitud.nombre_completo || '';
                if (nombreBase) {
                    resumen.nombre = String(nombreBase);
                    resumen.nombre_completo = String(nombreBase);
                }
                if (!resumen.estado) {
                    resumen.estado = String(solicitudCruda.estado ?? solicitud.estado ?? '');
                }
                if (!resumen.created_at) {
                    resumen.created_at = String(solicitudCruda.created_at ?? solicitud.created_at ?? '');
                }

                establecerSolicitudSeleccionada(resumen);

                if (typeof aplicarPrefillSolicitud === 'function') {
                    aplicarPrefillSolicitud({
                        cliente: data && typeof data.cliente === 'object' ? data.cliente : {},
                        contrato: data && typeof data.contrato === 'object' ? data.contrato : {},
                        desarrollo: data && typeof data.desarrollo === 'object' ? data.desarrollo : {},
                    });
                }

                if (modalBuscarSolicitud) {
                    modalBuscarSolicitud.hide();
                }

                const mensajeExito = data && typeof data.message === 'string' && data.message !== ''
                    ? data.message
                    : `Se vinculó la solicitud #${identificador}.`;
                actualizarCrearContratoFeedback('success', mensajeExito);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Solicitud vinculada', mensajeExito, 'success');
                } else {
                    console.info(mensajeExito);
                }
            } catch (error) {
                const mensaje = error instanceof Error && error.message
                    ? error.message
                    : 'No fue posible vincular la solicitud seleccionada.';
                actualizarCrearContratoFeedback('error', mensaje);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', mensaje, 'error');
                } else {
                    window.alert(mensaje);
                }
                throw error;
            } finally {
                if (triggerEl) {
                    triggerEl.disabled = false;
                    triggerEl.innerHTML = originalHtml;
                    triggerEl.removeAttribute('aria-busy');
                }
            }
        };

        const obtenerResumenInicial = () => {
            if (!inputSolicitudManual) {
                return null;
            }
            const atributo = inputSolicitudManual.getAttribute('data-solicitud-resumen');
            if (atributo) {
                try {
                    const parsed = JSON.parse(atributo);
                    if (parsed && typeof parsed === 'object') {
                        return parsed;
                    }
                } catch (error) {
                    // Ignorar parseos inválidos
                }
            }
            const numero = parseInt(String(inputSolicitudManual.value ?? '').trim(), 10);
            if (Number.isFinite(numero) && numero > 0) {
                return { id: numero };
            }
            return null;
        };

        const obtenerCriteriosBusqueda = () => {
            const obtenerValor = (id) => {
                const elemento = document.getElementById(id);
                return elemento && typeof elemento.value === 'string'
                    ? elemento.value.trim().toUpperCase()
                    : '';
            };
            return {
                folio: obtenerValor('crearFolio'),
                rfc: obtenerValor('clienteRfc'),
                curp: obtenerValor('clienteCurp'),
            };
        };

        const construirFilaSolicitud = (solicitud) => {
            const fila = document.createElement('tr');

            const agregarCeldaTexto = (valor, placeholder = '—', clase = '') => {
                const td = document.createElement('td');
                if (clase) {
                    td.className = clase;
                }
                const texto = String(valor ?? '').trim();
                td.textContent = texto !== '' ? texto : placeholder;
                fila.appendChild(td);
            };

            agregarCeldaTexto(solicitud.id);
            agregarCeldaTexto(solicitud.folio);
            agregarCeldaTexto(solicitud.nombre_completo);
            agregarCeldaTexto(solicitud.rfc);
            agregarCeldaTexto(solicitud.curp);
            agregarCeldaTexto((solicitud.estado || '').toString().toUpperCase(), '—');
            agregarCeldaTexto(formatearFechaCorta(solicitud.created_at));

            const coincidenciasTd = document.createElement('td');
            coincidenciasTd.className = 'text-center';
            const coincidencias = Number.parseInt(String(solicitud.coincidencias ?? '').trim(), 10) || 0;
            const badge = document.createElement('span');
            badge.className = coincidencias > 1 ? 'badge bg-info text-dark' : 'badge bg-secondary';
            badge.textContent = String(coincidencias);
            coincidenciasTd.appendChild(badge);
            fila.appendChild(coincidenciasTd);

            const accionesTd = document.createElement('td');
            accionesTd.className = 'text-end';
            const btnSeleccionar = document.createElement('button');
            btnSeleccionar.type = 'button';
            btnSeleccionar.className = 'btn btn-sm btn-primary';
            btnSeleccionar.innerHTML = '<i class="fas fa-link me-1"></i>Elegir';
            btnSeleccionar.addEventListener('click', () => {
                vincularSolicitud(solicitud, btnSeleccionar).catch(() => {});
            });
            accionesTd.appendChild(btnSeleccionar);
            fila.appendChild(accionesTd);

            return fila;
        };

        const renderSolicitudesModal = (lista, termino = '') => {
            if (!tablaResultadosModal) {
                return;
            }

            tablaResultadosModal.innerHTML = '';
            const terminoNormalizado = String(termino ?? '').trim().toUpperCase();
            const resultados = terminoNormalizado
                ? lista.filter((solicitud) => {
                    const campos = [
                        solicitud.id,
                        solicitud.folio,
                        solicitud.nombre,
                        solicitud.nombre_completo,
                        solicitud.rfc,
                        solicitud.curp,
                    ];
                    const texto = campos
                        .map((valor) => String(valor ?? '').trim().toUpperCase())
                        .filter((valor) => valor !== '')
                        .join(' ');
                    return texto.includes(terminoNormalizado);
                })
                : [...lista];

            if (resultados.length === 0) {
                const filaVacia = document.createElement('tr');
                const celda = document.createElement('td');
                celda.colSpan = 9;
                celda.className = 'text-center text-muted py-3';
                celda.innerHTML = terminoNormalizado
                    ? '<i class="fas fa-inbox"></i>No se encontraron coincidencias para el filtro aplicado.'
                    : '<i class="fas fa-inbox"></i>No hay solicitudes para mostrar.';
                filaVacia.appendChild(celda);
                tablaResultadosModal.appendChild(filaVacia);
                return;
            }

            resultados.forEach((solicitud) => {
                tablaResultadosModal.appendChild(construirFilaSolicitud(solicitud));
            });
        };

        const ejecutarBusquedaSolicitudes = async () => {
            if (!modalBuscarSolicitudEl || !tablaResultadosModal || !mensajeModal) {
                return;
            }

            const criterios = obtenerCriteriosBusqueda();
            const hayDatos = Object.values(criterios).some((valor) => valor !== '');

            solicitudesModalDisponibles = [];
            tablaResultadosModal.innerHTML = '';
            if (contenedorTablaModal) {
                contenedorTablaModal.classList.add('d-none');
            }
            if (filtroRapidoSolicitudes) {
                filtroRapidoSolicitudes.value = '';
                filtroRapidoSolicitudes.disabled = true;
            }

            if (!hayDatos) {
                mensajeModal.className = 'alert alert-warning';
                mensajeModal.textContent = 'Capture folio, RFC o CURP antes de buscar.';
                mensajeModal.classList.remove('d-none');
                return;
            }

            mensajeModal.className = 'alert alert-info';
            mensajeModal.textContent = 'Buscando solicitudes coincidentes...';
            mensajeModal.classList.remove('d-none');

            const form = document.getElementById('formCrearContratoCompleto');
            const csrfInput = form ? form.querySelector('input[name="csrf_token"]') : null;
            const params = new URLSearchParams();
            params.set('buscarSolicitudesContrato', '1');
            params.set('folio', criterios.folio);
            params.set('rfc', criterios.rfc);
            params.set('curp', criterios.curp);
            params.set('csrf_token', csrfInput && csrfInput.value ? csrfInput.value : '');

            try {
                const response = await fetch('index.php?ruta=crearContrato', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    body: params.toString(),
                });

                let data;
                try {
                    data = await response.json();
                } catch (error) {
                    throw new Error('Respuesta inválida del servidor.');
                }

                if (!response.ok || !data || data.status !== 'ok') {
                    const mensaje = data && data.message ? data.message : 'No fue posible obtener las solicitudes.';
                    throw new Error(mensaje);
                }

                const solicitudes = Array.isArray(data.solicitudes) ? data.solicitudes : [];
                if (!solicitudes.length) {
                    mensajeModal.className = 'alert alert-warning';
                    mensajeModal.textContent = data.message
                        ? String(data.message)
                        : 'No se encontraron solicitudes que coincidan con los datos capturados.';
                    mensajeModal.classList.remove('d-none');
                    return;
                }

                if (contenedorTablaModal) {
                    contenedorTablaModal.classList.remove('d-none');
                }
                mensajeModal.classList.add('d-none');
                solicitudesModalDisponibles = solicitudes;
                renderSolicitudesModal(solicitudesModalDisponibles);
                if (filtroRapidoSolicitudes) {
                    filtroRapidoSolicitudes.disabled = solicitudesModalDisponibles.length === 0;
                    if (!filtroRapidoSolicitudes.disabled) {
                        setTimeout(() => {
                            filtroRapidoSolicitudes.focus({ preventScroll: true });
                        }, 120);
                    }
                }
            } catch (error) {
                mensajeModal.className = 'alert alert-danger';
                mensajeModal.textContent = error instanceof Error
                    ? error.message
                    : 'Ocurrió un error al buscar solicitudes.';
                mensajeModal.classList.remove('d-none');
            }
        };

        if (filtroRapidoSolicitudes) {
            filtroRapidoSolicitudes.addEventListener('input', () => {
                renderSolicitudesModal(solicitudesModalDisponibles, filtroRapidoSolicitudes.value || '');
            });
        }

        const solicitarIdManual = () => {
            if (!inputSolicitudManual) {
                return;
            }
            const valorActual = inputSolicitudManual.value ? parseInt(String(inputSolicitudManual.value).trim(), 10) : '';

            if (typeof Swal === 'undefined') {
                const respuesta = window.prompt('Ingresa el ID de la solicitud que deseas vincular con el contrato:', valorActual || '');
                if (respuesta === null) {
                    return;
                }
                const numero = parseInt(String(respuesta).trim(), 10);
                if (!Number.isFinite(numero) || numero <= 0) {
                    window.alert('Ingresa un identificador numérico mayor a cero.');
                    return;
                }
                vincularSolicitud({ id: numero }).catch(() => {});
                return;
            }

            Swal.fire({
                title: 'Vincular solicitud',
                text: 'Captura el identificador numérico de la solicitud que deseas vincular.',
                icon: 'info',
                input: 'number',
                inputLabel: 'ID de la solicitud',
                inputValue: Number.isFinite(valorActual) && valorActual > 0 ? valorActual : '',
                inputAttributes: {
                    min: 1,
                    step: 1,
                },
                showCancelButton: true,
                confirmButtonText: 'Vincular',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => {
                    const numero = parseInt(String(value ?? '').trim(), 10);
                    if (!Number.isFinite(numero) || numero <= 0) {
                        return 'Ingresa un identificador válido (mayor a cero).';
                    }
                    return null;
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    const numero = parseInt(String(result.value ?? '').trim(), 10);
                    if (Number.isFinite(numero) && numero > 0) {
                        vincularSolicitud({ id: numero }).catch(() => {});
                    }
                }
            });
        };

        if (inputSolicitudManual) {
            actualizarResumenSolicitud(obtenerResumenInicial());
        }

        if (btnVincularSolicitud && inputSolicitudManual) {
            btnVincularSolicitud.addEventListener('click', () => {
                if (modalBuscarSolicitud) {
                    modalBuscarSolicitud.show();
                    return;
                }
                solicitarIdManual();
            });
        }

        if (modalBuscarSolicitudEl && modalBuscarSolicitud) {
            modalBuscarSolicitudEl.addEventListener('shown.bs.modal', () => {
                ejecutarBusquedaSolicitudes();
            });
        }

        if (botonReintentarModal) {
            botonReintentarModal.addEventListener('click', () => {
                ejecutarBusquedaSolicitudes();
            });
        }

        if (btnQuitarSolicitud && inputSolicitudManual) {
            btnQuitarSolicitud.addEventListener('click', () => {
                const ejecutarLimpiar = () => {
                    establecerSolicitudSeleccionada(null);
                };

                if (!resumenSolicitudActual || !resumenSolicitudActual.id) {
                    ejecutarLimpiar();
                    return;
                }

                if (typeof Swal === 'undefined') {
                    ejecutarLimpiar();
                    return;
                }

                Swal.fire({
                    title: 'Quitar solicitud',
                    text: '¿Desea quitar la solicitud vinculada al contrato?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, quitar',
                    cancelButtonText: 'Cancelar',
                }).then((result) => {
                    if (result.isConfirmed) {
                        ejecutarLimpiar();
                    }
                });
            });
        }

        // Identificar elementos de la nueva página de creación de contrato
        const selectDesarrollo   = document.getElementById('selectDesarrolloCrear');
        const inputTipoId        = document.getElementById('crearTipoId');
        const inputTipoNombre    = document.getElementById('crearTipoNombre');
        const inputSuperficie    = document.getElementById('crearSuperficie');
        const inputSuperficieFixed = document.getElementById('crearSuperficieFixed');
        const inputFraccion      = document.getElementById('inputFraccionCrear');
        const contenedorFracciones = document.getElementById('contenedorFraccionesCrear');
        const listaFraccionesDisponibles = document.getElementById('listaFraccionesDisponiblesCrear');
        const hiddenFracciones   = document.getElementById('hiddenFraccionesCrear');
        const crearMontoInmueble      = document.getElementById('crearMontoInmueble');
        const crearMontoInmuebleFixed = document.getElementById('crearMontoInmuebleFixed');
        const crearEnganche           = document.getElementById('crearEnganche');
        const crearEngancheFixed      = document.getElementById('crearEngancheFixed');
        const crearSaldoPago          = document.getElementById('crearSaldoPago');
        const crearSaldoPagoFixed     = document.getElementById('crearSaldoPagoFixed');
        const crearPenalizacion       = document.getElementById('crearPenalizacion');
        const crearPenalizacionFixed  = document.getElementById('crearPenalizacionFixed');
        // Arreglos para almacenar fracciones
        let fraccionesSeleccionadas = [];
        let fraccionesDisponibles = [];
        if (selectDesarrollo) {
            const fraccionesPrefill = hiddenFracciones ? hiddenFracciones.value : '';
            const superficiePrefill = inputSuperficie ? inputSuperficie.value : '';
            const montoPrefill = crearMontoInmueble ? crearMontoInmueble.value : '';
            const enganchePrefill = crearEnganche ? crearEnganche.value : '';
            // Renderizar etiquetas de fracciones seleccionadas
            const renderFraccionesCrear = () => {
                if (!contenedorFracciones) return;
                contenedorFracciones.innerHTML = '';
                fraccionesSeleccionadas.forEach((frac, idx) => {
                    const badge = document.createElement('span');
                    badge.style.display = 'inline-flex';
                    badge.style.alignItems = 'center';
                    badge.style.borderRadius = '12px';
                    badge.style.backgroundColor = '#f0f2f5';
                    badge.style.color = '#333';
                    badge.style.padding = '4px 8px';
                    badge.style.margin = '2px';
                    badge.style.fontSize = '0.8rem';
                    badge.textContent = frac;
                    const removeSpan = document.createElement('span');
                    removeSpan.style.marginLeft = '6px';
                    removeSpan.style.color = '#dc3545';
                    removeSpan.style.cursor = 'pointer';
                    removeSpan.textContent = '×';
                    removeSpan.addEventListener('click', () => {
                        fraccionesSeleccionadas.splice(idx, 1);
                        renderFraccionesCrear();
                    });
                    badge.appendChild(removeSpan);
                    contenedorFracciones.appendChild(badge);
                });
                if (hiddenFracciones) {
                    hiddenFracciones.value = fraccionesSeleccionadas.join(',');
                }
                // Actualizar lista disponible
                renderListaDisponiblesCrear();
                if (inputFraccion) {
                    inputFraccion.classList.remove('is-invalid');
                }
            };
            // Renderizar lista de fracciones disponibles
            const renderListaDisponiblesCrear = () => {
                if (!listaFraccionesDisponibles) return;
                listaFraccionesDisponibles.innerHTML = '';
                if (Array.isArray(fraccionesDisponibles)) {
                    fraccionesDisponibles.forEach(frac => {
                        if (!fraccionesSeleccionadas.includes(frac)) {
                            const item = document.createElement('span');
                            item.style.display = 'inline-block';
                            item.style.margin = '2px';
                            item.style.padding = '4px 8px';
                            item.style.borderRadius = '12px';
                            item.style.backgroundColor = '#e2e6ea';
                            item.style.color = '#333';
                            item.style.cursor = 'pointer';
                            item.style.fontSize = '0.8rem';
                            item.textContent = frac;
                            item.addEventListener('click', () => {
                                agregarFraccionSeleccionada(frac);
                            });
                            listaFraccionesDisponibles.appendChild(item);
                        }
                    });
                }
            };
            // Al cambiar de desarrollo actualizar tipo, superficie y lotes disponibles
            selectDesarrollo.addEventListener('change', function () {
                const selected = this.selectedOptions[0];
                if (selected) {
                    const tipoId    = selected.getAttribute('data-tipo-id') || '';
                    const tipoNombre= selected.getAttribute('data-tipo-nombre') || '';
                    const superficie= selected.getAttribute('data-superficie') || '';
                    const lotesAttr = selected.getAttribute('data-lotes') || '';
                    if (inputTipoId) inputTipoId.value = tipoId;
                    if (inputTipoNombre) inputTipoNombre.value = tipoNombre;
                    if (inputSuperficie) {
                        inputSuperficie.value = superficie;
                    }
                    if (inputSuperficieFixed) {
                        const supVal = parseFloat(cleanNumberString(superficie || '0')) || 0;
                        convertirNumeroALetras(supVal, inputSuperficieFixed);
                    }
                    // Parsear lotes
                    fraccionesDisponibles = [];
                    if (lotesAttr) {
                        let decoded = decodeHtml(lotesAttr);
                        try {
                            const parsed = JSON.parse(decoded);
                            if (Array.isArray(parsed)) {
                                fraccionesDisponibles = parsed;
                            }
                        } catch (err) {
                            fraccionesDisponibles = decoded.split(',').map(l => l.trim()).filter(Boolean);
                        }
                    }
                    fraccionesSeleccionadas = [];
                    renderFraccionesCrear();
                }
            });
            const agregarFraccionSeleccionada = (valorCrudo) => {
                const normalizado = typeof valorCrudo === 'string' ? valorCrudo.trim().toUpperCase() : '';
                if (normalizado === '') {
                    return false;
                }

                if (!fraccionesSeleccionadas.includes(normalizado)) {
                    fraccionesSeleccionadas.push(normalizado);
                    renderFraccionesCrear();
                    if (inputFraccion) {
                        inputFraccion.classList.remove('is-invalid');
                    }
                    return true;
                }

                if (inputFraccion) {
                    inputFraccion.classList.add('is-invalid');
                }

                return false;
            };

            const procesarFraccionDesdeInput = () => {
                if (!inputFraccion) {
                    return false;
                }

                const agregado = agregarFraccionSeleccionada(inputFraccion.value);
                if (agregado || inputFraccion.value.trim() === '') {
                    inputFraccion.value = '';
                }

                return agregado;
            };

            // Ingreso manual de fracciones
            if (inputFraccion) {
                inputFraccion.addEventListener('input', () => {
                    if (inputFraccion.classList.contains('is-invalid')) {
                        inputFraccion.classList.remove('is-invalid');
                    }
                });

                inputFraccion.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        procesarFraccionDesdeInput();
                    }
                });

                inputFraccion.addEventListener('blur', () => {
                    if (inputFraccion.value.trim() === '') {
                        inputFraccion.value = '';
                        inputFraccion.classList.remove('is-invalid');
                        return;
                    }

                    procesarFraccionDesdeInput();
                });
            }
            // Calcular saldo y penalización y convertir a letras
            const actualizarCalculosCrear = () => {
                const montoVal    = parseFloat(crearMontoInmueble && crearMontoInmueble.value ? crearMontoInmueble.value : 0) || 0;
                const engancheVal = parseFloat(crearEnganche && crearEnganche.value ? crearEnganche.value : 0) || 0;
                const saldoVal    = montoVal - engancheVal;
                const penalVal    = montoVal * 0.20;
                if (crearSaldoPago) {
                    crearSaldoPago.value = saldoVal.toFixed(2);
                }
                if (crearPenalizacion) {
                    crearPenalizacion.value = penalVal.toFixed(2);
                }
                convertirNumeroALetras(montoVal, crearMontoInmuebleFixed);
                convertirNumeroALetras(engancheVal, crearEngancheFixed);
                convertirNumeroALetras(saldoVal, crearSaldoPagoFixed);
                convertirNumeroALetras(penalVal, crearPenalizacionFixed);
            };
            if (crearMontoInmueble) crearMontoInmueble.addEventListener('input', actualizarCalculosCrear);
            if (crearEnganche) crearEnganche.addEventListener('input', actualizarCalculosCrear);

            const asignarValorCampo = (elemento, valor, opciones = {}) => {
                if (!elemento) {
                    return;
                }

                let nuevoValor = valor !== undefined && valor !== null ? String(valor) : '';
                if (elemento instanceof HTMLInputElement && elemento.type === 'date' && nuevoValor) {
                    const coincide = nuevoValor.match(/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/);
                    if (coincide) {
                        const [, dia, mes, anio] = coincide;
                        nuevoValor = `${anio}-${mes}-${dia}`;
                    }
                }
                const actual = typeof elemento.value === 'string' ? elemento.value : '';
                const debeActualizar = opciones.forzar || actual !== nuevoValor;
                if (debeActualizar) {
                    elemento.value = nuevoValor;
                }

                if (opciones.omitirEvento) {
                    return;
                }

                if (!debeActualizar && !opciones.forzarEvento) {
                    return;
                }

                const eventos = Array.isArray(opciones.eventos) && opciones.eventos.length
                    ? opciones.eventos
                    : [elemento.tagName === 'SELECT' ? 'change' : 'input'];
                eventos.forEach((nombre) => {
                    elemento.dispatchEvent(new Event(nombre, { bubbles: true }));
                });
            };

            aplicarPrefillSolicitud = (prefillDatos) => {
                if (!prefillDatos || typeof prefillDatos !== 'object') {
                    return;
                }

                const datosCliente = typeof prefillDatos.cliente === 'object' && prefillDatos.cliente !== null
                    ? prefillDatos.cliente
                    : {};
                const datosContrato = typeof prefillDatos.contrato === 'object' && prefillDatos.contrato !== null
                    ? prefillDatos.contrato
                    : {};
                const datosDesarrollo = typeof prefillDatos.desarrollo === 'object' && prefillDatos.desarrollo !== null
                    ? prefillDatos.desarrollo
                    : {};

                const desarrolloValor = datosDesarrollo.desarrollo_id !== undefined && datosDesarrollo.desarrollo_id !== null
                    ? String(datosDesarrollo.desarrollo_id)
                    : '';
                if (selectDesarrollo) {
                    if (selectDesarrollo.value !== desarrolloValor) {
                        selectDesarrollo.value = desarrolloValor;
                        selectDesarrollo.dispatchEvent(new Event('change', { bubbles: true }));
                    } else if (desarrolloValor === '') {
                        selectDesarrollo.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }

                if (inputTipoId && datosDesarrollo.tipo_contrato_id !== undefined) {
                    inputTipoId.value = datosDesarrollo.tipo_contrato_id;
                }
                if (inputTipoNombre && datosDesarrollo.tipo_contrato_nombre !== undefined) {
                    inputTipoNombre.value = datosDesarrollo.tipo_contrato_nombre;
                }

                if (inputSuperficie && datosContrato.contrato_superficie !== undefined) {
                    asignarValorCampo(inputSuperficie, datosContrato.contrato_superficie);
                    if (inputSuperficieFixed) {
                        const superficieNumero = parseFloat(cleanNumberString(datosContrato.contrato_superficie || '0')) || 0;
                        if (Number.isFinite(superficieNumero) && superficieNumero !== 0) {
                            convertirNumeroALetras(superficieNumero, inputSuperficieFixed);
                        } else {
                            inputSuperficieFixed.value = '';
                        }
                    }
                }

                const fraccionesValor = datosContrato.fracciones;
                if (typeof fraccionesValor === 'string') {
                    fraccionesSeleccionadas = fraccionesValor.split(',').map((valor) => valor.trim()).filter(Boolean);
                    renderFraccionesCrear();
                } else if (Array.isArray(fraccionesValor)) {
                    fraccionesSeleccionadas = fraccionesValor.map((valor) => String(valor).trim()).filter(Boolean);
                    renderFraccionesCrear();
                }

                asignarValorCampo(document.getElementById('clienteNombre'), datosCliente.cliente_nombre);

                const selectNacionalidad = document.getElementById('clienteNacionalidad');
                if (selectNacionalidad && datosCliente.cliente_nacionalidad !== undefined) {
                    const valorNacionalidad = String(datosCliente.cliente_nacionalidad || '');
                    let existe = false;
                    Array.from(selectNacionalidad.options).forEach((option) => {
                        if (option.value === valorNacionalidad) {
                            existe = true;
                        }
                    });
                    selectNacionalidad.value = existe ? valorNacionalidad : '';
                    selectNacionalidad.dispatchEvent(new Event('change', { bubbles: true }));
                }

                asignarValorCampo(document.getElementById('clienteFechaNacimiento'), datosCliente.cliente_fecha_nacimiento, { eventos: ['change', 'input'], forzarEvento: true });
                asignarValorCampo(document.getElementById('clienteRfc'), datosCliente.cliente_rfc);
                asignarValorCampo(document.getElementById('clienteCurp'), datosCliente.cliente_curp);

                const selectIdentificacion = document.getElementById('clienteIdentificacion');
                if (selectIdentificacion && datosCliente.cliente_identificacion !== undefined) {
                    selectIdentificacion.value = String(datosCliente.cliente_identificacion || '');
                    selectIdentificacion.dispatchEvent(new Event('change', { bubbles: true }));
                }

                asignarValorCampo(document.getElementById('clienteIne'), datosCliente.cliente_ine);
                asignarValorCampo(document.getElementById('clienteEstadoCivil'), datosCliente.cliente_estado_civil);
                asignarValorCampo(document.getElementById('clienteOcupacion'), datosCliente.cliente_ocupacion);
                asignarValorCampo(document.getElementById('clienteEmail'), datosCliente.cliente_email);
                asignarValorCampo(document.getElementById('clienteDomicilio'), datosCliente.cliente_domicilio);
                asignarValorCampo(document.getElementById('clienteBeneficiario'), datosCliente.cliente_beneficiario);
                asignarValorCampo(document.getElementById('clienteDiceSer'), datosCliente.dice_ser);

                const telVisible = document.getElementById('telefono_cliente');
                const telOculto = document.getElementById('cliente_telefono');
                const telefonoPrefill = String(datosCliente.telefono_cliente_visible ?? datosCliente.cliente_telefono ?? '');
                if (telVisible) {
                    if (window.intlTelInputGlobals && typeof window.intlTelInputGlobals.getInstance === 'function') {
                        const instancia = window.intlTelInputGlobals.getInstance(telVisible);
                        if (instancia) {
                            instancia.setNumber(telefonoPrefill);
                            telVisible.dispatchEvent(new Event('input', { bubbles: true }));
                        } else {
                            asignarValorCampo(telVisible, telefonoPrefill, { forzarEvento: true });
                        }
                    } else {
                        asignarValorCampo(telVisible, telefonoPrefill, { forzarEvento: true });
                    }
                }
                if (telOculto && datosCliente.cliente_telefono !== undefined) {
                    telOculto.value = String(datosCliente.cliente_telefono ?? '');
                }

                asignarValorCampo(document.getElementById('crearFolio'), datosContrato.folio);
                asignarValorCampo(document.getElementById('crearFechaContrato'), datosContrato.fecha_contrato, { eventos: ['change', 'input'], forzarEvento: true });
                asignarValorCampo(document.getElementById('crearInicioPagos'), datosContrato.inicio_pagos, { eventos: ['change', 'input'], forzarEvento: true });
                asignarValorCampo(document.getElementById('crearHabitacional'), datosContrato.habitacional);
                asignarValorCampo(document.getElementById('crearEntrega'), datosContrato.entrega_posecion_date, { eventos: ['change', 'input'], forzarEvento: true });
                if (datosContrato.entrega_posecion !== undefined) {
                    const entregaTexto = document.getElementById('crearEntregaTexto');
                    if (entregaTexto) {
                        entregaTexto.value = String(datosContrato.entrega_posecion ?? '');
                    }
                }
                asignarValorCampo(document.getElementById('crearClausulaPosecion'), datosContrato.clausula_c_posecion);
                asignarValorCampo(document.getElementById('crearFechaFirma'), datosContrato.fecha_firma, { eventos: ['change', 'input'], forzarEvento: true });
                asignarValorCampo(document.getElementById('rangoPagoInicio'), datosContrato.rango_pago_inicio_date, { eventos: ['change', 'input'], forzarEvento: true });
                if (datosContrato.rango_pago_inicio !== undefined) {
                    const inicioTexto = document.getElementById('rangoPagoInicioTexto');
                    if (inicioTexto) {
                        inicioTexto.value = String(datosContrato.rango_pago_inicio ?? '');
                    }
                }
                asignarValorCampo(document.getElementById('rangoPagoFin'), datosContrato.rango_pago_fin_date, { eventos: ['change', 'input'], forzarEvento: true });
                if (datosContrato.rango_pago_fin !== undefined) {
                    const finTexto = document.getElementById('rangoPagoFinTexto');
                    if (finTexto) {
                        finTexto.value = String(datosContrato.rango_pago_fin ?? '');
                    }
                }
                asignarValorCampo(document.getElementById('crearRangoPago'), datosContrato.rango_pago);
                asignarValorCampo(document.getElementById('crearClausulas'), datosContrato.financiamiento_clusulas);
                asignarValorCampo(document.getElementById('crearMensualidades'), datosContrato.mensualidades);
                asignarValorCampo(document.getElementById('crearParcialidadesAnuales'), datosContrato.parcialidades_anuales);
                asignarValorCampo(document.getElementById('crearMontoInmueble'), datosContrato.monto_inmueble);
                asignarValorCampo(document.getElementById('crearEnganche'), datosContrato.enganche);
                if (datosContrato.saldo_pago !== undefined) {
                    asignarValorCampo(document.getElementById('crearSaldoPago'), datosContrato.saldo_pago, { forzarEvento: true });
                }
                asignarValorCampo(document.getElementById('crearPagoMensual'), datosContrato.pago_mensual, { eventos: ['input'], forzarEvento: true });
                if (datosContrato.penalizacion !== undefined) {
                    asignarValorCampo(document.getElementById('crearPenalizacion'), datosContrato.penalizacion, { forzarEvento: true });
                }
                asignarValorCampo(document.getElementById('crearObservaciones'), datosContrato.observaciones);

                if (typeof actualizarCalculosCrear === 'function') {
                    actualizarCalculosCrear();
                }

                if (datosContrato.saldo_pago !== undefined && crearSaldoPago) {
                    crearSaldoPago.value = String(datosContrato.saldo_pago ?? '');
                }
                if (datosContrato.penalizacion !== undefined && crearPenalizacion) {
                    crearPenalizacion.value = String(datosContrato.penalizacion ?? '');
                }
            };

            if (selectDesarrollo.value) {
                selectDesarrollo.dispatchEvent(new Event('change'));
            }

            if (hiddenFracciones && fraccionesPrefill) {
                fraccionesSeleccionadas = fraccionesPrefill.split(',').map(f => f.trim()).filter(Boolean);
                renderFraccionesCrear();
            }

            if (inputSuperficie && superficiePrefill) {
                inputSuperficie.value = superficiePrefill;
                if (inputSuperficieFixed) {
                    const supVal = parseFloat(cleanNumberString(superficiePrefill || '0')) || 0;
                    convertirNumeroALetras(supVal, inputSuperficieFixed);
                }
            }

            if (inputSuperficie && inputSuperficieFixed) {
                inputSuperficie.addEventListener('input', () => {
                    const supVal = parseFloat(cleanNumberString(inputSuperficie.value || '0')) || 0;
                    convertirNumeroALetras(supVal, inputSuperficieFixed);
                });
            }

            if (crearMontoInmueble && montoPrefill) {
                crearMontoInmueble.value = montoPrefill;
            }
            if (crearEnganche && enganchePrefill) {
                crearEnganche.value = enganchePrefill;
            }

            actualizarCalculosCrear();
        }
    })();

    
    
    /*
     * CONFIRMACIÓN DE ENVÍO PARA CREAR CONTRATO
     * Antes de enviar el formulario completo de contrato, se mostrará una
     * alerta de confirmación para que el usuario revise la información. Si
     * confirma, se envía el formulario; de lo contrario, puede seguir
     * editando. Esto no afecta a otros formularios.
     */
    (function confirmarEnvioCrearContrato() {
        const formCrear = document.getElementById('formCrearContratoCompleto');
        if (!formCrear || formCrear.dataset.readonly === '1') {
            return;
        }

        if (formCrear) {
            const esEdicion = formCrear.querySelector('input[name="editarContratoCompleto"]') !== null;
            registrarLimpiezaCampos(formCrear);
            formCrear.setAttribute('novalidate', true);

            formCrear.addEventListener('submit', function (e) {
                e.preventDefault();

                const camposInvalidos = obtenerCamposInvalidos(formCrear);
                if (camposInvalidos.length) {
                    actualizarCrearContratoFeedback('error', 'Hay campos obligatorios sin completar.', 'Revise los campos resaltados.');
                    mostrarSwalRequisitos(camposInvalidos, 'Completa la información del contrato');
                    console.error('Formulario crear contrato: validación HTML5 fallida.', camposInvalidos.map(campo => campo.name || campo.id));
                    return;
                }

                const fNac = document.getElementById('clienteFechaNacimiento');
                if (fNac && fNac.value) {
                    const fn  = new Date(fNac.value);
                    const hoy = new Date();
                    let edad  = hoy.getFullYear() - fn.getFullYear();
                    const m   = hoy.getMonth() - fn.getMonth();
                    if (m < 0 || (m === 0 && hoy.getDate() < fn.getDate())) edad--;
                    if (edad < 18) {
                        actualizarCrearContratoFeedback('error', 'El cliente debe ser mayor de edad para generar un contrato.');
                        console.error('Edad inválida para contrato.', { fechaNacimiento: fNac.value, edadCalculada: edad });
                        return;
                    }
                }

                Swal.fire({
                    title: 'Confirmar envío',
                    text: 'Verifique que la información capturada es correcta antes de continuar.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, enviar',
                    cancelButtonText: 'Revisar información'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData(formCrear);
                        const url = formCrear.getAttribute('action');
                        const submitBtn = formCrear.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.disabled = true;
                        }

                        fetch(url, {
                            method: 'POST',
                            body: formData
                        })
                            .then(async (response) => {
                                const rawText = await response.text();
                                let payload = null;

                                if (rawText) {
                                    try {
                                        payload = JSON.parse(rawText);
                                    } catch (jsonError) {
                                        const parseError = new Error('PARSE_ERROR');
                                        parseError.code = 'PARSE-001';
                                        parseError.status = response.status;
                                        parseError.raw = rawText;
                                        throw parseError;
                                    }
                                }

                                if (!response.ok) {
                                    const backendError = new Error('BACKEND_ERROR');
                                    backendError.code = payload?.code || response.status;
                                    backendError.backendMessage = payload?.message || `No se pudo ${esEdicion ? 'actualizar' : 'crear'} el contrato.`;
                                    backendError.payload = payload;
                                    throw backendError;
                                }

                                if (!payload || payload.status !== 'ok') {
                                    const logicalError = new Error('BACKEND_ERROR');
                                    logicalError.code = payload?.code || 'CTR-400';
                                    logicalError.backendMessage = payload?.message || `No se pudo ${esEdicion ? 'actualizar' : 'crear'} el contrato.`;
                                    logicalError.payload = payload;
                                    throw logicalError;
                                }

                                return payload;
                            })
                            .then((data) => {
                                const successMessage = data.message || (esEdicion ? 'Contrato actualizado correctamente.' : 'Contrato creado correctamente.');
                                actualizarCrearContratoFeedback('success', successMessage);
                                console.info(esEdicion ? 'Contrato actualizado correctamente.' : 'Contrato creado correctamente.', data);

                                const clienteDestino = data.cliente_id || formCrear.querySelector('input[name="cliente_id"]')?.value || '';
                                const contratoDestino = data.contrato_id || '';

                                let destinoUrl = 'index.php?ruta=contratos';
                                if (clienteDestino) {
                                    destinoUrl += `&cliente_id=${encodeURIComponent(clienteDestino)}`;
                                } else if (contratoDestino) {
                                    destinoUrl += `&contrato_id=${encodeURIComponent(contratoDestino)}`;
                                }

                                Swal.fire(esEdicion ? 'Contrato actualizado' : 'Contrato creado', successMessage, 'success').then(() => {
                                    window.location.href = destinoUrl;
                                });
                            })
                            .catch((error) => {
                                const accion = esEdicion ? 'actualizar' : 'crear';
                                let message = 'No se pudo conectar con el servidor.';
                                let code = 'NET-001';
                                let detalle = '';

                                if (error.message === 'BACKEND_ERROR') {
                                    code = error.code || 'CTR-500';
                                    message = error.backendMessage || message;
                                    detalle = error.payload && error.payload.detail ? `Detalle: ${error.payload.detail}` : '';
                                    console.error(`El backend respondió con error al ${accion} el contrato.`, error.payload || error);
                                } else if (error.message === 'PARSE_ERROR') {
                                    code = error.code || 'PARSE-001';
                                    message = 'Respuesta inválida del servidor.';
                                    detalle = 'Se recibió una respuesta no JSON. Revise la consola para más detalles.';
                                    console.error('Respuesta no parseable del backend.', { raw: error.raw, status: error.status });
                                } else {
                                    console.error('Fallo al enviar el contrato.', error);
                                }

                                const detalleMensaje = detalle ? `Código: ${code}\n${detalle}` : `Código: ${code}`;
                                actualizarCrearContratoFeedback('error', message, detalleMensaje);
                                Swal.fire('Error', `${message} (${code})`, 'error');
                            })
                            .finally(() => {
                                const submitBtnFinal = formCrear.querySelector('button[type="submit"]');
                                if (submitBtnFinal) {
                                    submitBtnFinal.disabled = false;
                                }
                            });
                    } else {
                        actualizarCrearContratoFeedback('warning', 'Envío cancelado. Puede seguir editando el formulario.');
                        console.info('El usuario canceló el envío del contrato.');
                    }
                });
            });
        }
    })();

    


    /*
     * CALCULAR EDAD DEL CLIENTE
     * Cuando el usuario selecciona una fecha de nacimiento en la página de
     * crear contrato, se calculará la edad en años completos y se almacenará
     * en el campo oculto correspondiente (#clienteEdad). Esta lógica se
     * ejecutará solo si los elementos existen en la página actual.
     */
    (function calcularEdadCliente() {
        const fechaNacimiento = document.getElementById('clienteFechaNacimiento');
        const campoEdad       = document.getElementById('clienteEdad');
        if (fechaNacimiento && campoEdad) {
            fechaNacimiento.addEventListener('change', () => {
                const fechaStr = fechaNacimiento.value;
                if (!fechaStr) {
                    campoEdad.value = '';
                    return;
                }
                const fn = new Date(fechaStr);
                const hoy = new Date();
                let edad = hoy.getFullYear() - fn.getFullYear();
                const m = hoy.getMonth() - fn.getMonth();
                if (m < 0 || (m === 0 && hoy.getDate() < fn.getDate())) {
                    edad--;
                }
                campoEdad.value = edad.toString();
            });
        }
    })();

    /*
     * ACTUALIZAR PAGO MENSUAL Y SU REPRESENTACIÓN EN LETRAS
     * Al ingresar un valor en el campo de pago mensual, se convertirá a un
     * texto con la función convertirNumeroALetras y se almacenará en su
     * correspondiente campo oculto. Esto se ejecuta solo si los campos
     * existen en la página de creación de contrato.
     */
    (function manejarPagoMensual() {
        const pagoMensual       = document.getElementById('crearPagoMensual');
        const pagoMensualFixed  = document.getElementById('crearPagoMensualFixed');
        if (pagoMensual && pagoMensualFixed) {
            const actualizarPago = () => {
                const val = parseFloat(pagoMensual.value || '0');
                convertirNumeroALetras(val, pagoMensualFixed);
            };
            pagoMensual.addEventListener('input', actualizarPago);
        }
    })();

  
    /*
     * ACTUALIZAR FECHA DE CONTRATO FIJA
     * Convierte una fecha seleccionada en el formato YYYY-MM-DD al formato
     * "DIA DÍAS DE MES DE {MES} DEL AÑO {AÑO}" y lo almacena en un campo
     * oculto. Aplica para la creación y edición de contratos.
     */
    (function manejarFechaContrato() {
        const crearFecha = document.getElementById('crearFechaContrato');
        const crearFechaFixed = document.getElementById('crearFechaContratoFixed');
        const crearFechaTexto = document.getElementById('crearFechaContratoTexto');
        if (crearFecha) {
            const actualizarCrear = () => {
                const val = crearFecha.value;
                if (!val) {
                    if (crearFechaFixed) crearFechaFixed.value = '';
                    if (crearFechaTexto) crearFechaTexto.value = '';
                    return;
                }
                const [anio, mes, dia] = val.split('-');
                const mesNombre = mesesEnLetras[parseInt(mes, 10) - 1] || '';
                const diaNum = parseInt(dia, 10);
                if (crearFechaFixed) {
                    crearFechaFixed.value = `${diaNum} DÍAS DEL MES DE ${mesNombre} DEL AÑO ${anio}`;
                }
                if (crearFechaTexto) {
                    crearFechaTexto.value = fechaALetras(val);
                }
                const crearDiaInicio = document.getElementById('crearDiaInicio');
                if (crearDiaInicio) {
                    crearDiaInicio.value = diaNum;
                }
            };
            crearFecha.addEventListener('change', actualizarCrear);
            crearFecha.addEventListener('input', actualizarCrear);
            actualizarCrear();
        }
        const editarFecha = document.getElementById('editarFechaContrato');
        const editarFechaFixed = document.getElementById('editarFechaContratoFixed');
        const editarFechaTexto = document.getElementById('editarFechaContratoTexto');
        if (editarFecha) {
            const actualizarEditar = () => {
                const val = editarFecha.value;
                if (!val) {
                    if (editarFechaFixed) editarFechaFixed.value = '';
                    if (editarFechaTexto) editarFechaTexto.value = '';
                    return;
                }
                const [anio, mes, dia] = val.split('-');
                const mesNombre = mesesEnLetras[parseInt(mes, 10) - 1] || '';
                const diaNum = parseInt(dia, 10);
                if (editarFechaFixed) {
                    editarFechaFixed.value = `${diaNum} DÍAS DEL MES DE ${mesNombre} DEL AÑO ${anio}`;
                }
                if (editarFechaTexto) {
                    editarFechaTexto.value = fechaALetras(val);
                }
                const editarDiaInicio = document.getElementById('editarDiaInicio');
                if (editarDiaInicio) {
                    editarDiaInicio.value = diaNum;
                }
            };
            editarFecha.addEventListener('change', actualizarEditar);
            editarFecha.addEventListener('input', actualizarEditar);
            actualizarEditar();
        }
    })();

    sincronizarFechaLarga('clienteFechaNacimiento', 'clienteFechaNacimientoTexto');
    sincronizarFechaLarga(document.querySelector('input[name="inicio_pagos"]'), 'crearInicioPagosTexto');
    sincronizarFechaLarga(document.querySelector('input[name="entrega_posecion"]'), 'crearEntregaTexto');
    sincronizarFechaLarga('rangoPagoInicio', 'rangoPagoInicioTexto');
    sincronizarFechaLarga('rangoPagoFin', 'rangoPagoFinTexto');
    sincronizarFechaLarga('crearFechaFirma', 'crearFechaFirmaTexto');

    (function gestionarIdentificacionSolicitud() {
        const select = document.querySelector('[data-identificacion-select]');
        if (!select) {
            return;
        }

        const containers = {
            numero: document.querySelector('[data-identificacion-container="numero"]'),
            idmex: document.querySelector('[data-identificacion-container="idmex"]'),
        };

        const inputs = {
            numero: containers.numero ? containers.numero.querySelector('[data-identificacion-input="numero"]') : null,
            idmex: containers.idmex ? containers.idmex.querySelector('[data-identificacion-input="idmex"]') : null,
        };

        const actualizarVisibilidad = (clave, visible) => {
            const contenedor = containers[clave];
            if (contenedor) {
                contenedor.classList.toggle('d-none', !visible);
                contenedor.setAttribute('aria-hidden', visible ? 'false' : 'true');
            }

            const input = inputs[clave];
            if (!input) {
                return;
            }

            const controlarRequerido = input.dataset.requiredWhenVisible === '1';
            if (controlarRequerido) {
                if (visible && !input.readOnly && !input.disabled) {
                    input.setAttribute('required', '');
                } else {
                    input.removeAttribute('required');
                }
            }
        };

        const actualizarEstado = () => {
            const valorSeleccionado = (select.value || '').toUpperCase();
            let mostrarIdmex = valorSeleccionado === 'INE';
            let mostrarNumero = valorSeleccionado === 'PASAPORTE' || valorSeleccionado === 'CEDULA PROFESIONAL';
            if (!mostrarIdmex && !mostrarNumero) {
                mostrarNumero = Boolean(inputs.numero && inputs.numero.value && inputs.numero.value.trim() !== '');
                if (!mostrarNumero) {
                    mostrarIdmex = Boolean(inputs.idmex && inputs.idmex.value && inputs.idmex.value.trim() !== '');
                }
            }
            actualizarVisibilidad('idmex', mostrarIdmex);
            actualizarVisibilidad('numero', mostrarNumero);
        };

        select.addEventListener('change', actualizarEstado);
        actualizarEstado();
    })();



    const inicializarCamposMayusculas = () => {
        const aplicarMayusculas = (elemento) => {
            if (!elemento || elemento.dataset.uppercaseInit === '1') {
                return;
            }

            elemento.dataset.uppercaseInit = '1';

            const transformar = () => {
                const valorOriginal = elemento.value || '';
                const valorMayusculas = valorOriginal.toUpperCase();
                if (valorOriginal === valorMayusculas) {
                    return;
                }

                const { selectionStart, selectionEnd } = elemento;
                elemento.value = valorMayusculas;

                if (document.activeElement === elemento
                    && typeof selectionStart === 'number'
                    && typeof selectionEnd === 'number'
                    && typeof elemento.setSelectionRange === 'function') {
                    try {
                        elemento.setSelectionRange(selectionStart, selectionEnd);
                    } catch (error) {
                        // Algunos navegadores pueden lanzar excepción si el elemento no soporta selección.
                    }
                }
            };

            elemento.addEventListener('input', transformar);

            if (elemento.value) {
                elemento.value = elemento.value.toUpperCase();
            }
        };

        document.querySelectorAll('input[type="text"]:not([data-allow-lowercase])').forEach((input) => {
            if (!input.hasAttribute('maxlength') || input.maxLength > 150 || input.maxLength <= 0) {
                input.setAttribute('maxlength', '150');
            }
            aplicarMayusculas(input);
        });

        document.querySelectorAll('textarea:not([data-allow-lowercase])').forEach((area) => {
            if (!area.hasAttribute('maxlength') || area.maxLength > 1000 || area.maxLength <= 0) {
                area.setAttribute('maxlength', '1000');
            }
            aplicarMayusculas(area);
        });
    };

    inicializarCamposMayusculas();

    // === Limitar números a valores no negativos ===
    (function normalizarNumericos() {
        const numeros = document.querySelectorAll('input[type="number"]');
        numeros.forEach((input) => {
            if (!input.hasAttribute('min') || Number(input.getAttribute('min')) < 0) {
                input.setAttribute('min', '0');
            }
        });
    })();

    // === Forzar apertura del datepicker al enfocar ===
    (function habilitarDatepickerAutomatico() {
        const camposFecha = document.querySelectorAll('input[type="date"]');
        camposFecha.forEach((input) => {
            const abrirPickerSiSoloLectura = (event) => {
                if (!input.readOnly && !input.disabled) {
                    return;
                }
                if (typeof input.showPicker === 'function') {
                    event.preventDefault();
                    input.showPicker();
                }
            };
            input.addEventListener('click', abrirPickerSiSoloLectura);
        });
    })();

    // === Teléfonos con intl-tel-input (configuración global) ===
    (function initTelefonosInternacionales() {
        const telInputs = document.querySelectorAll('input[type="tel"]');
        if (telInputs.length === 0) {
            return;
        }

        const mostrarErrorTelefono = (mensaje) => {
            if (typeof actualizarCrearContratoFeedback === 'function') {
                actualizarCrearContratoFeedback('error', mensaje);
            }
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', mensaje, 'error');
            } else {
                console.error(mensaje);
            }
        };

        telInputs.forEach((input) => {
            if (input.dataset.intlInit === '1') {
                return;
            }

            const hiddenSelector = input.getAttribute('data-intl-hidden') || '';
            const hiddenField = hiddenSelector ? document.querySelector(hiddenSelector) : null;
            const form = input.form;
            let iti = null;

            if (window.intlTelInput) {
                iti = window.intlTelInput(input, {
                    initialCountry: 'mx',
                    separateDialCode: true,
                    preferredCountries: ['mx', 'us', 'es', 'co', 'ar'],
                    utilsScript: 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js'
                });

                const sincronizarDestino = () => {
                    if (!iti || typeof iti.getNumber !== 'function') {
                        return;
                    }
                    const numero = iti.getNumber();
                    if (hiddenField) {
                        hiddenField.value = numero;
                    } else {
                        input.value = numero;
                    }
                };

                const valorInicial = hiddenField && hiddenField.value ? hiddenField.value : input.value;
                if (valorInicial) {
                    iti.setNumber(valorInicial);
                    sincronizarDestino();
                }

                input.addEventListener('countrychange', sincronizarDestino);
                input.addEventListener('blur', sincronizarDestino);
                input.addEventListener('input', sincronizarDestino);

                if (form) {
                    form.addEventListener('submit', (evento) => {
                        if (input.disabled || input.readOnly) {
                            sincronizarDestino();
                            return;
                        }
                        sincronizarDestino();
                        const esObligatorio = input.required || (hiddenField && hiddenField.hasAttribute('required'));
                        if (esObligatorio && !iti.isValidNumber()) {
                            evento.preventDefault();
                            input.classList.add('is-invalid');
                            input.classList.remove('is-valid');
                            mostrarErrorTelefono('Número de teléfono inválido.');
                        } else {
                            input.classList.remove('is-invalid');
                            if (esObligatorio) {
                                input.classList.add('is-valid');
                            }
                        }
                    });
                }
            } else if (form) {
                form.addEventListener('submit', (evento) => {
                    if (input.disabled || input.readOnly) {
                        return;
                    }
                    const destino = hiddenField || input;
                    const raw = ((destino ? destino.value : input.value) || '').replace(/[^0-9]/g, '');
                    const esObligatorio = input.required || (hiddenField && hiddenField.hasAttribute('required'));
                    if (esObligatorio && raw.length < 10) {
                        evento.preventDefault();
                        input.classList.add('is-invalid');
                        mostrarErrorTelefono('Ingrese un teléfono válido de al menos 10 dígitos.');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });
            }

            input.dataset.intlInit = '1';
        });
    })();



        // tooltip y sincronización de mensualidades y rango de pago


    // === Relación entre rango de fechas, mensualidades y años de financiamiento ===
    (function () {
    const inicioInput = document.getElementById('rangoPagoInicio');
    const finInput = document.getElementById('rangoPagoFin');
    const mensualidadesInput = document.querySelector('input[name="mensualidades"]');
    const rangoAniosInput = document.getElementById('crearRangoPago');

    // 🔹 Solo normalizar texto de años/meses (sin tocar mensualidades)
    if (rangoAniosInput) {
        rangoAniosInput.addEventListener('blur', function () {
        const interpretacion = interpretarTexto(this.value);
        if (interpretacion.totalMeses > 0) {
            this.value = formatearTiempo(interpretacion.anios, interpretacion.meses);
        }
        });
    }

    // 🔹 Calcular meses automáticamente según rango de fechas
    if (inicioInput && finInput && mensualidadesInput && rangoAniosInput) {
        function calcularMeses() {
        const inicioVal = inicioInput.value;
        const finVal = finInput.value;
        if (!inicioVal || !finVal) return;

        const inicio = new Date(inicioVal);
        const fin = new Date(finVal);

        if (isNaN(inicio) || isNaN(fin) || fin < inicio) return;

        let meses = (fin.getFullYear() - inicio.getFullYear()) * 12;
        meses += fin.getMonth() - inicio.getMonth();

        if (fin.getDate() < inicio.getDate()) {
            meses -= 1;
        }

        if (meses < 1) meses = 1;

        // 🔸 Aquí SÍ calculamos mensualidades y años/meses
        mensualidadesInput.value = meses;
        rangoAniosInput.value = formatearTiempo(
            Math.floor(meses / 12),
            meses % 12
        );
        }

        inicioInput.addEventListener('change', calcularMeses);
        finInput.addEventListener('change', calcularMeses);
    }

    // Helpers
    function interpretarTexto(texto) {
        texto = texto.toLowerCase().trim();
        let anios = 0, meses = 0;

        const matchAnios = texto.match(/(\d+)\s*(a|años|año)/);
        const matchMeses = texto.match(/(\d+)\s*(m|meses|mes)/);

        if (matchAnios) anios = parseInt(matchAnios[1], 10);
        if (matchMeses) meses = parseInt(matchMeses[1], 10);

        if (!matchAnios && !matchMeses) {
        const numeros = texto.match(/\d+/g) || [];
        if (numeros.length === 1) meses = parseInt(numeros[0], 10);
        else if (numeros.length >= 2) {
            anios = parseInt(numeros[0], 10);
            meses = parseInt(numeros[1], 10);
        }
        }

        const totalMeses = (anios * 12) + meses;
        return { anios: Math.floor(totalMeses / 12), meses: totalMeses % 12, totalMeses };
    }

    function formatearTiempo(anios, meses) {
        let texto = '';
        if (anios > 0) texto += anios + (anios === 1 ? ' AÑO ' : ' AÑOS ');
        if (meses > 0) texto += meses + (meses === 1 ? ' MES' : ' MESES');
        return texto.trim();
    }
    })();

    const modalSolicitudContratoEl = document.getElementById('modalSolicitudContrato');
    const btnVerSolicitudContrato = document.getElementById('btnVerSolicitudContrato');
    if (modalSolicitudContratoEl && btnVerSolicitudContrato && typeof bootstrap !== 'undefined') {
        const modalSolicitudContrato = new bootstrap.Modal(modalSolicitudContratoEl);
        const detalleSolicitudContrato = modalSolicitudContratoEl.querySelector('#detalleSolicitudContrato');
        btnVerSolicitudContrato.addEventListener('click', () => {
            const dataset = btnVerSolicitudContrato.getAttribute('data-solicitud');
            if (!dataset) {
                return;
            }
            try {
                const datos = JSON.parse(dataset);
                renderDetalleSolicitud(detalleSolicitudContrato, datos);
                modalSolicitudContrato.show();
            } catch (error) {
                console.error('No fue posible mostrar la solicitud asociada al contrato', error);
                Swal.fire('Error', 'No fue posible mostrar la información de la solicitud.', 'error');
            }
        });
    }

        
        // === Acciones masivas en contratos (cancelar, reactivar, editar) ===
        /*
         * En la vista de contratos, permite seleccionar múltiples contratos y
         * realizar acciones masivas como cancelar, reactivar o editar. Los
         * botones se muestran u ocultan según el estado de los contratos
         * seleccionados.
         */
        
           // === Gestión de archivado / desarchivado en tablaContratos ===
        (() => {
            const tabla = document.getElementById("tablaContratos");
            const acciones = document.getElementById("accionesContrato");
            const contBotones = document.getElementById("contenedorBotones");
            const selCount = document.getElementById("selCount");
            const formAccion = document.getElementById("formContratosAccion"); // form oculto con action a contratos.controlador.php

            if (!tabla || !acciones || !contBotones || !selCount || !formAccion) return; 
            // 🚨 Si alguno no existe, salimos y no rompemos el resto del JS

            const seleccionados = new Set();
            let estatusSeleccion = null;

            function renderAcciones() {
                const count = seleccionados.size;
                selCount.textContent = count;

                if (count === 0) {
                acciones.style.display = "none";
                contBotones.innerHTML = "";
                estatusSeleccion = null;
                return;
                }

                acciones.style.display = "block";

                if (estatusSeleccion === 1) {
                contBotones.innerHTML = `
                    <button class="btn btn-warning btn-sm" id="btnArchivar">
                    <i class="fas fa-box-archive"></i> Archivar
                    </button>`;
                } else {
                contBotones.innerHTML = `
                    <button class="btn btn-success btn-sm" id="btnDesarchivar">
                    <i class="fas fa-rotate-left"></i> Desarchivar
                    </button>`;
                }
            }

            // Selección de filas
            tabla.addEventListener("change", function (e) {
                if (!e.target.classList.contains("select-contrato")) return;

                const fila = e.target.closest("tr");
                const id = fila.dataset.contratoId;
                const estatus = parseInt(fila.dataset.estatus || "0", 10);

                if (e.target.checked) {
                if (seleccionados.size === 0) {
                    estatusSeleccion = estatus;
                    seleccionados.add(id);
                } else {
                    if (estatus !== estatusSeleccion) {
                    e.target.checked = false;
                    Swal.fire(
                        "Selección inválida",
                        "Solo puedes seleccionar filas con el mismo estatus.",
                        "warning"
                    );
                    return;
                    }
                    seleccionados.add(id);
                }
                } else {
                seleccionados.delete(id);
                if (seleccionados.size === 0) estatusSeleccion = null;
                }
                renderAcciones();
            });

            // Acciones (archivar/desarchivar)
            document.addEventListener("click", function (e) {
                const btn = e.target.closest("#btnArchivar, #btnDesarchivar");
                if (!btn) return;

                const ids = Array.from(seleccionados);
                const nuevoEstatus = btn.id === "btnArchivar" ? 0 : 1;
                const accionTexto = nuevoEstatus === 0 ? "Archivar" : "Desarchivar";

                Swal.fire({
                title: `${accionTexto} ${ids.length} contrato(s)?`,
                icon: "question",
                showCancelButton: true,
                confirmButtonText: `Sí, ${accionTexto.toLowerCase()}`,
                cancelButtonText: "Cancelar"
                }).then((result) => {
                if (!result.isConfirmed) return;

                // Preparamos el formData
                const formData = new FormData(formAccion);
                formData.append("actualizarEstatusMasivo", "1");
                formData.append("ids", ids.join(","));
                formData.append("nuevo_estatus", String(nuevoEstatus));

                const url = formAccion.getAttribute("action");

                fetch(url, {
                    method: "POST",
                    body: formData
                })
                    .then(r => r.text())
                    .then(resp => {
                    let title, text, icon;
                    if (resp.includes("ok")) {
                        title = "Hecho";
                        text = `Contratos ${accionTexto.toLowerCase()}dos correctamente.`;
                        icon = "success";

                        // Refrescar visualmente
                        ids.forEach(id => {
                        const tr = tabla.querySelector(`tr[data-contrato-id="${id}"]`);
                        if (tr) {
                            tr.dataset.estatus = String(nuevoEstatus);
                            const cb = tr.querySelector(".select-contrato");
                            if (cb) cb.checked = false;
                        }
                        });

                        seleccionados.clear();
                        estatusSeleccion = null;
                        renderAcciones();

                        // Refrescamos página completa
                        window.location.reload();
                    } else {
                        title = "Error";
                        text = "No se pudo actualizar.";
                        icon = "error";
                    }
                    Swal.fire(title, text, icon);
                    })
                    .catch(() => {
                    Swal.fire("Error", "No se pudo conectar con el servidor.", "error");
                    });
                });
            });
            })();

            // El detalle del contrato se muestra ahora en la vista de formulario completo.

            const manejarCancelarContrato = (form, event) => {
                event.preventDefault();

                const mensaje = form.querySelector('[data-confirm-text]')?.getAttribute('data-confirm-text')
                    || '¿Desea cancelar este contrato? Esta acción no se puede deshacer.';
                const motivoInput = form.querySelector('input[name="motivo_cancelacion"]');
                const passwordInput = form.querySelector('input[name="password_confirmacion"]');
                if (passwordInput) {
                    evitarAutocompletarPassword(passwordInput);
                }
                if (motivoInput) {
                    motivoInput.value = '';
                }
                if (passwordInput) {
                    passwordInput.value = '';
                }

                const enviarFormulario = (motivo, password) => {
                    const motivoFinal = typeof motivo === 'string' ? motivo.trim().slice(0, 500) : '';
                    const passwordFinal = typeof password === 'string' ? password.trim() : '';

                    if (motivoInput) {
                        motivoInput.value = motivoFinal;
                    }
                    if (passwordInput) {
                        passwordInput.value = passwordFinal;
                    }

                    const ejecutarEnvio = () => {
                        if (document.contains(form)) {
                            form.submit();
                            return;
                        }

                        const formClonado = document.createElement('form');
                        formClonado.method = form.getAttribute('method') || 'post';
                        formClonado.action = form.getAttribute('action') || window.location.href;

                        const datos = new FormData(form);
                        datos.set('motivo_cancelacion', motivoFinal);
                        datos.set('password_confirmacion', passwordFinal);

                        for (const [key, valor] of datos.entries()) {
                            const campo = document.createElement('input');
                            campo.type = 'hidden';
                            campo.name = key;
                            campo.value = valor instanceof File ? '' : String(valor ?? '');
                            formClonado.appendChild(campo);
                        }

                        document.body.appendChild(formClonado);
                        formClonado.submit();
                    };

                    if (typeof Swal === 'undefined') {
                        ejecutarEnvio();
                        return;
                    }

                    Swal.fire({
                        title: 'Cancelando contrato',
                        html: '<p class="mb-0 text-start">Estamos procesando tu solicitud…</p>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                            ejecutarEnvio();
                        }
                    });
                };

                if (typeof Swal === 'undefined') {
                    const respuesta = window.prompt(`${mensaje}\n\nDescribe el motivo de la cancelación:`);
                    if (respuesta === null) {
                        return;
                    }
                    const texto = String(respuesta).trim();
                    if (texto.length < 5) {
                        window.alert('Describe el motivo de la cancelación con al menos 5 caracteres.');
                        return;
                    }
                    const passwordRespuesta = window.prompt('Para confirmar la cancelación, ingresa tu contraseña actual:');
                    if (passwordRespuesta === null) {
                        return;
                    }
                    const passwordTexto = String(passwordRespuesta);
                    if (passwordTexto.trim() === '') {
                        window.alert('Debes ingresar tu contraseña para confirmar la cancelación.');
                        return;
                    }
                    const motivo = texto.length > 500 ? texto.slice(0, 500) : texto;
                    enviarFormulario(motivo, passwordTexto);
                    return;
                }

                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                }[char] || char));
                const mensajeSeguro = escapeHtml(mensaje);

                Swal.fire({
                    title: 'Cancelar contrato',
                    html: `
                        <p class="mb-3 text-start">${mensajeSeguro}</p>
                        <div class="text-start">
                            <label for="swalMotivoCancelacionContrato" class="form-label fw-semibold">Motivo de la cancelación</label>
                            <textarea id="swalMotivoCancelacionContrato" class="swal2-textarea" rows="4" placeholder="Describe el motivo de la cancelación"></textarea>
                        </div>
                        <div class="text-start mt-3">
                            <label for="swalPasswordCancelacionContrato" class="form-label fw-semibold">Confirma tu contraseña</label>
                            <input type="password" id="swalPasswordCancelacionContrato" class="swal2-input" placeholder="Contraseña actual" autocomplete="new-password" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" maxlength="150">
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Cancelar contrato',
                    cancelButtonText: 'Cerrar',
                    focusConfirm: false,
                    willOpen: () => {
                        const overlay = document.querySelector('.swal2-container');
                        if (overlay) {
                            overlay.classList.add('ag-swal-cancelar-contrato');
                        }
                    },
                    preConfirm: () => {
                        const motivoField = document.getElementById('swalMotivoCancelacionContrato');
                        const passwordField = document.getElementById('swalPasswordCancelacionContrato');
                        const texto = typeof motivoField?.value === 'string' ? motivoField.value.trim() : '';
                        if (texto.length < 5) {
                            Swal.showValidationMessage('Describe el motivo de la cancelación (mínimo 5 caracteres).');
                            return false;
                        }
                        const passwordValor = typeof passwordField?.value === 'string' ? passwordField.value : '';
                        if (passwordValor.trim() === '') {
                            Swal.showValidationMessage('Ingresa tu contraseña para confirmar la cancelación.');
                            return false;
                        }
                        const motivoFinal = texto.length > 500 ? texto.slice(0, 500) : texto;
                        return {
                            motivo: motivoFinal,
                            password: passwordValor,
                        };
                    },
                    didOpen: () => {
                        const motivoField = document.getElementById('swalMotivoCancelacionContrato');
                        const passwordField = document.getElementById('swalPasswordCancelacionContrato');
                        if (motivoField) {
                            motivoField.setAttribute('maxlength', '500');
                            motivoField.setAttribute('autocapitalize', 'sentences');
                            motivoField.focus();
                        }
                        if (passwordField) {
                            evitarAutocompletarPassword(passwordField);
                        }
                    }
                }).then(result => {
                    if (result.isConfirmed && result.value) {
                        const motivo = typeof result.value.motivo === 'string' ? result.value.motivo.trim() : '';
                        const passwordValor = typeof result.value.password === 'string' ? result.value.password : '';
                        enviarFormulario(motivo, passwordValor);
                    }
                });
            };

            document.addEventListener('submit', (event) => {
                const form = event.target instanceof HTMLFormElement ? event.target : null;
                if (!form || !form.classList.contains('formCancelarContrato')) {
                    return;
                }
                manejarCancelarContrato(form, event);
            }, true);


            // === Inputs tipo="number" que aceptan sólo números ===
            function onlyNumbers(el, trigger) {
            const original = el.value;
            el.value = el.value.replace(/\D+/g, "");            
            }

            document.querySelectorAll("input.number").forEach(el => {
            el.addEventListener("input", () => onlyNumbers(el, "input"));
            el.addEventListener("change", () => onlyNumbers(el, "change"));
            });

            // === Inputs tipo="number" que aceptan enteros y decimales ===
            function onlyDecimals(el, trigger) {
            const original = el.value;
            // Reemplaza todo excepto números y punto
            el.value = el.value.replace(/[^0-9.]/g, "");
            
            // Evitar más de un punto decimal
            const parts = el.value.split(".");
            if (parts.length > 2) {
                el.value = parts[0] + "." + parts.slice(1).join("").replace(/\./g, "");
            }

            console.log(`[onlyDecimals:${trigger}]`, original, "→", el.value);
            }

            // Aplica a todos los inputs con class="number_dec"
            document.querySelectorAll("input.number_dec").forEach(el => {
            el.addEventListener("input", () => onlyDecimals(el, "input"));
            el.addEventListener("change", () => onlyDecimals(el, "change"));
            });


            // Toggle mostrar/ocultar form de contraseña por fila
            document.querySelectorAll('.btnTogglePwd').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const row = document.getElementById(`rowPwd-${id}`);
                if (row) row.style.display = (row.style.display === 'none' || row.style.display === '') ? 'table-row' : 'none';
            });
            });

            // Cancelar edición
            document.querySelectorAll('.btnCancelarPwd').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const row = document.getElementById(`rowPwd-${id}`);
                if (row) row.style.display = 'none';
            });
            });

            // Envío AJAX de cambio de contraseña
            document.querySelectorAll('.formCambiarPassword').forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                const url = 'index.php?ruta=usuarios&accion=cambiarPassword';

                fetch(url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(resp => {
                    if (resp.status === 'ok') {
                    Swal.fire({ title: 'Éxito', text: resp.message, icon: 'success', timer: 1800, showConfirmButton: false })
                    .then(() => { window.location.reload(); });
                    } else {
                    Swal.fire('Error', resp.message || 'No se pudo actualizar', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'No se pudo conectar con el servidor', 'error'));
            });
            });

           
                        // Auto-llenado de "nombre_desarrollo" a partir del texto del select "tipo_contrato"
            function initNombreDesarrolloFromTipo(
            selectSelector = '#tipo_contrato',
            inputSelector  = 'input[name="nombre_desarrollo"]'
            ) {
            const selectEl = document.querySelector(selectSelector);
            const inputEl  = document.querySelector(inputSelector);

            if (!selectEl || !inputEl) {
                console.warn('[initNombreDesarrolloFromTipo] Elementos no encontrados:', { selectSelector, inputSelector });
                return;
            }

            console.log('[initNombreDesarrolloFromTipo] Inicializado OK');

            const syncNombre = () => {
                const opt = selectEl.options[selectEl.selectedIndex];
                const texto = opt ? (opt.text || '').trim() : '';
                inputEl.value = texto;
                console.log('[initNombreDesarrolloFromTipo] Asignado:', texto);
            };

            // Sincroniza al cargar si ya hay una opción seleccionada
            syncNombre();

            // Actualiza cada vez que cambie el tipo de contrato
            selectEl.addEventListener('change', syncNombre);
            }



    const modalEditarUsuarioEl = document.getElementById('modalEditarUsuario');
    if (modalEditarUsuarioEl) {
        const rolSelect = document.getElementById('editarRol');
        if (rolSelect && rolSelect.dataset.restrictedInit !== '1') {
            rolSelect.dataset.restrictedInit = '1';
            const restringeAdmin = rolSelect.dataset.sessionEsAdmin !== '1';

            rolSelect.addEventListener('change', () => {
                const originalValue = rolSelect.dataset.valorOriginal || '';
                const selectedValue = rolSelect.value;
                const selectedOption = rolSelect.options[rolSelect.selectedIndex];

                if (restringeAdmin) {
                    if (selectedOption && selectedOption.dataset.restrictedRole === 'admin') {
                        rolSelect.value = originalValue || 'user';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Permiso restringido', 'Solo un administrador puede otorgar el permiso de administrador.', 'warning');
                        }
                        return;
                    }

                    if (originalValue === 'admin' && selectedValue !== 'admin') {
                        rolSelect.value = originalValue;
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Permiso restringido', 'Solo un administrador puede modificar el permiso de otro administrador.', 'warning');
                        }
                        return;
                    }
                }

                rolSelect.dataset.valorOriginal = selectedValue;
            });
        }

        modalEditarUsuarioEl.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            if (!button) return;

            const usuarioId = button.getAttribute('data-id') || '';
            const nombre = button.getAttribute('data-nombre') || '';
            const email = button.getAttribute('data-email') || '';
            const rol = button.getAttribute('data-rol') || 'user';
            const notificaciones = button.getAttribute('data-notificaciones') === '1';

            const idInput = document.getElementById('editarUsuarioId');
            const nombreInput = document.getElementById('editarNombreCompleto');
            const emailInput = document.getElementById('editarEmail');
            const notifHidden = document.getElementById('editarNotificacionesHidden');
            const notifSwitch = document.getElementById('editarNotificacionesSwitch');

            if (idInput) idInput.value = usuarioId;
            if (nombreInput) nombreInput.value = nombre;
            if (emailInput) emailInput.value = email;
            if (rolSelect) {
                rolSelect.value = rol;
                rolSelect.dataset.valorOriginal = rol;
            }
            if (notifHidden) notifHidden.value = notificaciones ? '1' : '0';
            if (notifSwitch) notifSwitch.checked = notificaciones;
        });

        const notifSwitch = document.getElementById('editarNotificacionesSwitch');
        const notifHidden = document.getElementById('editarNotificacionesHidden');
        if (notifSwitch && notifHidden) {
            notifSwitch.addEventListener('change', () => {
                notifHidden.value = notifSwitch.checked ? '1' : '0';
            });
        }
    }

    const modalRegresarBorradorEl = document.getElementById('modalRegresarBorrador');
    if (modalRegresarBorradorEl) {
        modalRegresarBorradorEl.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) {
                return;
            }

            const solicitudId = button.getAttribute('data-solicitud-id') || '';
            const folio = button.getAttribute('data-solicitud-folio') || 'sin folio';
            const nombre = button.getAttribute('data-solicitud-nombre') || '';

            const idInput = modalRegresarBorradorEl.querySelector('#regresarBorradorSolicitudId');
            const motivoInput = modalRegresarBorradorEl.querySelector('#regresarBorradorMotivo');
            const resumenEl = modalRegresarBorradorEl.querySelector('#regresarBorradorResumen');

            if (idInput) {
                idInput.value = solicitudId;
            }
            if (motivoInput) {
                motivoInput.value = '';
            }
            if (resumenEl) {
                const partes = [`Solicitud folio ${folio}`];
                if (nombre) {
                    partes.push(`Cliente ${nombre}`);
                }
                resumenEl.textContent = partes.join(' • ');
            }
        });

        modalRegresarBorradorEl.addEventListener('hidden.bs.modal', () => {
            const formulario = modalRegresarBorradorEl.querySelector('#formRegresarBorrador');
            if (formulario) {
                formulario.reset();
            }
        });
    }


    const configNotificaciones = window.AG_NOTIFICATIONS || {};
    const badgeEl = document.getElementById('badgeNotificaciones');
    const listaEl = document.getElementById('listaNotificaciones');
    const vacioEl = document.getElementById('notificacionesVacio');
    const dropdownEl = document.getElementById('dropdownNotificaciones');
    const textoBadge = document.getElementById('textoBadgeNotificaciones');
    const contadorNotificacionesEl = dropdownEl ? dropdownEl.querySelector('[data-role="contador-notificaciones"]') : null;

    if (badgeEl && listaEl && vacioEl && dropdownEl) {
        let pendientesNoVistos = 0;
        let historialCargado = false;
        let cargandoHistorial = false;
        let notificacionesHabilitadas = Boolean(configNotificaciones.habilitadas);
        const mensajeListaDefault = vacioEl.dataset.defaultText || 'Sin notificaciones pendientes';
        const mensajeListaDesactivadas = vacioEl.dataset.disabledText || 'Notificaciones desactivadas. Actívalas desde tu perfil.';
        const triggerEl = dropdownEl.querySelector('.nav-link');
        const notificacionesDescartadas = new Set();

        const rutaSonidoNotificacion = (typeof configNotificaciones.sonido === 'string'
            && configNotificaciones.sonido.trim() !== '')
            ? configNotificaciones.sonido.trim()
            : 'vistas/media/notificacion.mp3';

        let audioNotificacion = null;
        if (rutaSonidoNotificacion) {
            try {
                audioNotificacion = new Audio(rutaSonidoNotificacion);
                audioNotificacion.preload = 'auto';
                audioNotificacion.volume = 0.75;
                audioNotificacion.addEventListener('error', () => {
                    audioNotificacion = null;
                }, { once: true });
            } catch (error) {
                console.warn('No se pudo inicializar el sonido de notificación', error);
                audioNotificacion = null;
            }
        }

        const titleElement = document.querySelector('title');
        const tituloBaseDocumento = titleElement
            ? (titleElement.getAttribute('data-base-title') || titleElement.textContent || document.title)
            : document.title;
        let ultimoConteoTitulo = -1;

        const actualizarTituloNotificaciones = (cantidad) => {
            if (!titleElement) {
                return;
            }
            const conteo = Number.isFinite(cantidad) ? Math.max(0, Math.trunc(cantidad)) : 0;
            if (ultimoConteoTitulo === conteo) {
                return;
            }
            ultimoConteoTitulo = conteo;
            const nuevoTitulo = conteo > 0 ? `(${conteo}) ${tituloBaseDocumento}` : tituloBaseDocumento;
            titleElement.textContent = nuevoTitulo;
            if (document.title !== nuevoTitulo) {
                document.title = nuevoTitulo;
            }
        };

        const reproducirSonidoNotificacion = () => {
            if (!audioNotificacion) {
                return;
            }
            try {
                const instancia = audioNotificacion.cloneNode(true);
                const reproduccion = instancia.play();
                if (reproduccion && typeof reproduccion.catch === 'function') {
                    reproduccion.catch(error => {
                        console.warn('No se pudo reproducir el sonido de notificación', error);
                    });
                }
            } catch (error) {
                console.warn('No se pudo reproducir el sonido de notificación', error);
            }
        };

        const normalizarIdNotificacion = (valor) => {
            if (typeof valor === 'number' && Number.isFinite(valor)) {
                return String(valor);
            }
            if (typeof valor === 'string') {
                return valor.trim();
            }
            return '';
        };

        const idsMarcadosParaEnvio = new Set();

        const marcarNotificacionesEntregadas = (ids) => {
            if (!Array.isArray(ids) || ids.length === 0) {
                return;
            }
            const normalizados = ids
                .map((id) => normalizarIdNotificacion(id))
                .filter((id) => id && !idsMarcadosParaEnvio.has(id));

            if (normalizados.length === 0) {
                return;
            }

            normalizados.forEach((id) => idsMarcadosParaEnvio.add(id));

            fetch('ajax/notificaciones_marcar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notifications: normalizados }),
                cache: 'no-store',
                keepalive: true,
                agSkipOverlay: true,
            }).then((resp) => {
                if (!resp.ok) {
                    normalizados.forEach((id) => idsMarcadosParaEnvio.delete(id));
                }
            }).catch(() => {
                normalizados.forEach((id) => idsMarcadosParaEnvio.delete(id));
            });
        };

        const mostrarMensajeLista = (mensaje) => {
            if (!vacioEl) {
                return;
            }
            vacioEl.textContent = mensaje;
            vacioEl.style.display = 'block';
        };

        const ocultarMensajeLista = () => {
            if (!vacioEl) {
                return;
            }
            vacioEl.style.display = 'none';
            vacioEl.textContent = mensajeListaDefault;
        };

        const limpiarListadoNotificaciones = (mensaje = mensajeListaDefault) => {
            notificacionesDescartadas.clear();
            listaEl.querySelectorAll('.notificacion-item').forEach(el => el.remove());
            mostrarMensajeLista(mensaje);
        };

        const descartarNotificacion = (item) => {
            if (!item || !item.parentElement) {
                return;
            }
            const notificationId = normalizarIdNotificacion(item.dataset.notificationId);
            if (notificationId) {
                notificacionesDescartadas.add(notificationId);
                marcarNotificacionesEntregadas([notificationId]);
                if (pendientesNoVistos > 0) {
                    pendientesNoVistos = Math.max(0, pendientesNoVistos - 1);
                    actualizarBadge(pendientesNoVistos);
                }
            }
            item.remove();
            if (listaEl.querySelectorAll('.notificacion-item').length === 0) {
                mostrarMensajeLista(mensajeListaDefault);
            } else {
                actualizarTiemposRelativos();
            }
        };

        const manejarAccionNotificacion = (event) => {
            const item = event.target.closest('.notificacion-item');
            if (!item) {
                return;
            }

            const dismissControl = event.target.closest('.notificacion-dismiss');
            if (dismissControl) {
                event.preventDefault();
                event.stopPropagation();
                descartarNotificacion(item);
                return;
            }

            descartarNotificacion(item);
        };

        listaEl.addEventListener('click', manejarAccionNotificacion);
        listaEl.addEventListener('auxclick', (event) => {
            if (event.button !== 1) {
                return;
            }
            manejarAccionNotificacion(event);
        });

        const actualizarBadge = (cantidad) => {
            const textoCantidad = cantidad > 9 ? '9+' : String(cantidad);
            if (cantidad > 0) {
                badgeEl.style.display = 'inline';
                badgeEl.textContent = textoCantidad;
                if (contadorNotificacionesEl) {
                    contadorNotificacionesEl.style.display = 'inline-flex';
                    contadorNotificacionesEl.textContent = textoCantidad;
                }
            } else {
                badgeEl.style.display = 'none';
                badgeEl.textContent = '0';
                if (contadorNotificacionesEl) {
                    contadorNotificacionesEl.style.display = 'none';
                    contadorNotificacionesEl.textContent = '0';
                }
            }
            if (textoBadge) {
                textoBadge.textContent = cantidad === 1
                    ? 'Una notificación sin leer'
                    : `${cantidad} notificaciones sin leer`;
            }
            actualizarTituloNotificaciones(cantidad);
        };

        actualizarTituloNotificaciones(pendientesNoVistos);

        actualizarBadge(pendientesNoVistos);

        const formateadorRelativo = (typeof Intl !== 'undefined' && typeof Intl.RelativeTimeFormat === 'function')
            ? new Intl.RelativeTimeFormat('es', { numeric: 'auto' })
            : null;

        const normalizarFechaNotificacion = (valor) => {
            if (!valor) {
                return null;
            }
            if (valor instanceof Date && !Number.isNaN(valor.getTime())) {
                return valor;
            }
            if (typeof valor === 'number' && Number.isFinite(valor)) {
                const fechaDesdeNumero = new Date(valor);
                return Number.isNaN(fechaDesdeNumero.getTime()) ? null : fechaDesdeNumero;
            }
            if (typeof valor === 'string') {
                const isoCompat = valor.replace(' ', 'T');
                let fecha = new Date(isoCompat);
                if (!Number.isNaN(fecha.getTime())) {
                    return fecha;
                }
                fecha = new Date(valor);
                if (!Number.isNaN(fecha.getTime())) {
                    return fecha;
                }
            }
            return null;
        };

        const obtenerTiempoRelativo = (fecha) => {
            if (!formateadorRelativo || !(fecha instanceof Date)) {
                return '';
            }

            const ahora = new Date();
            const diferenciaSegundos = Math.round((fecha.getTime() - ahora.getTime()) / 1000);

            const divisores = {
                year: 31536000,
                month: 2592000,
                week: 604800,
                day: 86400,
                hour: 3600,
                minute: 60,
                second: 1,
            };

            for (const unidad of Object.keys(divisores)) {
                const divisor = divisores[unidad];
                if (Math.abs(diferenciaSegundos) >= divisor || unidad === 'second') {
                    const cantidad = Math.round(diferenciaSegundos / divisor);
                    return formateadorRelativo.format(cantidad, unidad);
                }
            }
            return '';
        };

        const actualizarTiemposRelativos = () => {
            const elementos = listaEl.querySelectorAll('.notificacion-item');
            elementos.forEach(el => {
                const timestamp = el.dataset.notificationTimestamp;
                if (!timestamp) {
                    return;
                }
                const fecha = normalizarFechaNotificacion(Number(timestamp));
                if (!fecha) {
                    return;
                }
                const tiempoEl = el.querySelector('.notificacion-tiempo');
                if (tiempoEl) {
                    tiempoEl.textContent = obtenerTiempoRelativo(fecha);
                }
            });
        };

        setInterval(actualizarTiemposRelativos, 60000);

        const crearElementoNotificacion = (item) => {
            if (!item || !item.mensaje) {
                return null;
            }

            const idNormalizado = normalizarIdNotificacion(item.id);
            if (idNormalizado && notificacionesDescartadas.has(idNormalizado)) {
                return null;
            }

            const contenedor = document.createElement('div');
            contenedor.className = 'list-group-item list-group-item-action notificacion-item d-flex align-items-start gap-3 position-relative';
            contenedor.dataset.notificationId = item.id || '';
            contenedor.dataset.notificationType = item.tipo || 'solicitud';

            const enlace = document.createElement('a');
            enlace.className = 'notificacion-enlace flex-grow-1 text-decoration-none stretched-link';
            let destino = '';
            if (typeof item.url === 'string') {
                destino = item.url.trim();
            }
            if (!destino) {
                destino = item.solicitud_id
                    ? `index.php?ruta=solicitudes&solicitud_id=${encodeURIComponent(item.solicitud_id)}`
                    : 'index.php?ruta=solicitudes';
            }
            enlace.href = destino;

            const mensaje = document.createElement('span');
            mensaje.className = 'd-block notificacion-mensaje';
            mensaje.textContent = item.mensaje;
            enlace.appendChild(mensaje);

            const fechaObjeto = normalizarFechaNotificacion(item.created_at);
            if (fechaObjeto) {
                contenedor.dataset.notificationTimestamp = String(fechaObjeto.getTime());
                const tiempo = document.createElement('span');
                tiempo.className = 'notificacion-tiempo';
                tiempo.textContent = obtenerTiempoRelativo(fechaObjeto);
                enlace.appendChild(tiempo);
            }

            contenedor.appendChild(enlace);

            const botonCerrar = document.createElement('button');
            botonCerrar.type = 'button';
            botonCerrar.className = 'btn-close notificacion-dismiss';
            botonCerrar.setAttribute('aria-label', 'Descartar notificación');
            contenedor.appendChild(botonCerrar);

            return contenedor;
        };

        const renderizarHistorial = (items) => {
            notificacionesDescartadas.clear();
            listaEl.querySelectorAll('.notificacion-item').forEach(el => el.remove());

            if (!Array.isArray(items) || items.length === 0) {
                mostrarMensajeLista(mensajeListaDefault);
                return;
            }

            ocultarMensajeLista();

            let agregados = 0;
            items.forEach(item => {
                const enlace = crearElementoNotificacion(item);
                if (!enlace) {
                    return;
                }

                listaEl.appendChild(enlace);
                agregados += 1;
            });

            if (agregados === 0) {
                mostrarMensajeLista(mensajeListaDefault);
            }

            actualizarTiemposRelativos();
        };

        const agregarNotificacionesPendientes = (items) => {
            if (!Array.isArray(items) || items.length === 0) {
                return { agregados: 0, nuevos: [] };
            }

            ocultarMensajeLista();

            const existentes = new Set(
                Array.from(listaEl.querySelectorAll('.notificacion-item'))
                    .map(el => normalizarIdNotificacion(el.dataset.notificationId))
                    .filter(id => id)
            );

            let agregados = 0;
            const nuevos = [];

            items.forEach(item => {
                const idNormalizado = normalizarIdNotificacion(item && item.id);
                if (!idNormalizado || existentes.has(idNormalizado)) {
                    return;
                }

                const enlace = crearElementoNotificacion(item);
                if (!enlace) {
                    return;
                }

                const primerItem = listaEl.querySelector('.notificacion-item');
                if (primerItem) {
                    listaEl.insertBefore(enlace, primerItem);
                } else {
                    listaEl.appendChild(enlace);
                }
                existentes.add(idNormalizado);
                agregados += 1;
                nuevos.push(item);
            });

            if (agregados === 0 && listaEl.querySelectorAll('.notificacion-item').length === 0) {
                mostrarMensajeLista(mensajeListaDefault);
            }

            if (agregados > 0) {
                actualizarTiemposRelativos();
            }

            return { agregados, nuevos };
        };

        const actualizarEstadoVisual = (estado) => {
            const estadoAnterior = notificacionesHabilitadas;
            notificacionesHabilitadas = Boolean(estado);
            dropdownEl.dataset.notificationsEnabled = notificacionesHabilitadas ? '1' : '0';
            dropdownEl.classList.toggle('notificaciones-desactivadas', !notificacionesHabilitadas);
            if (triggerEl) {
                triggerEl.classList.toggle('text-muted', !notificacionesHabilitadas);
            }

            if (!notificacionesHabilitadas) {
                pendientesNoVistos = 0;
                actualizarBadge(0);
                limpiarListadoNotificaciones(mensajeListaDesactivadas);
                historialCargado = false;
                return;
            }

            if (!estadoAnterior) {
                pendientesNoVistos = 0;
                actualizarBadge(0);
                limpiarListadoNotificaciones(mensajeListaDefault);
            }
        };

        const cargarHistorial = () => {
            if (cargandoHistorial || !notificacionesHabilitadas) {
                return;
            }
            cargandoHistorial = true;
            fetch('ajax/notificaciones_historial.php?limite=10', { cache: 'no-store', agSkipOverlay: true })
                .then(async resp => {
                    let payload = null;
                    try {
                        payload = await resp.json();
                    } catch (error) {
                        console.error('Respuesta inválida al consultar historial de notificaciones', error);
                    }
                    return { ok: resp.ok, status: resp.status, payload };
                })
                .then(({ ok, status, payload }) => {
                    if (!ok || !payload || payload.status !== 'ok') {
                        console.error('Error al consultar historial de notificaciones', {
                            httpStatus: status,
                            response: payload
                        });
                        return;
                    }
                    if (typeof payload.notifications_enabled === 'boolean') {
                        actualizarEstadoVisual(payload.notifications_enabled);
                        if (!payload.notifications_enabled) {
                            return;
                        }
                    }
                    renderizarHistorial(payload.notifications || []);
                    historialCargado = true;
                })
                .catch(error => {
                    console.error('Error de red al consultar historial de notificaciones', error);
                })
                .finally(() => {
                    cargandoHistorial = false;
                });
        };

        const mostrarToast = (item) => {
            if (!item || typeof item.mensaje !== 'string' || item.mensaje.trim() === '' || typeof Swal === 'undefined') {
                return;
            }

            const tipo = typeof item.tipo === 'string' ? item.tipo.toLowerCase() : 'solicitud';
            const titulo = tipo === 'anuncio' ? 'Nuevo anuncio' : 'Actualización de solicitud';
            const configuracion = {
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: titulo,
                text: item.mensaje,
                timer: 6000,
                timerProgressBar: true,
                showConfirmButton: false,
            };

            if (tipo === 'anuncio') {
                configuracion.background = '#fff9db';
                configuracion.color = '#5c3d00';
            } else {
                configuracion.background = '#f0f9ff';
                configuracion.color = '#0c5460';
            }

            Swal.fire(configuracion);
        };

        const consultarNotificaciones = () => {
            fetch('ajax/notificaciones_pendientes.php', { cache: 'no-store', agSkipOverlay: true })
                .then(async resp => {
                    let payload = null;
                    try {
                        payload = await resp.json();
                    } catch (error) {
                        console.error('Respuesta inválida al consultar notificaciones', error);
                    }
                    return { ok: resp.ok, status: resp.status, payload };
                })
                .then(({ ok, status, payload }) => {
                    if (!ok || !payload || payload.status !== 'ok') {
                        console.error('Error al consultar notificaciones', {
                            httpStatus: status,
                            response: payload
                        });
                        return;
                    }

                    if (typeof payload.notifications_enabled === 'boolean') {
                        actualizarEstadoVisual(payload.notifications_enabled);
                    }

                    if (!notificacionesHabilitadas) {
                        return;
                    }

                    if (!historialCargado) {
                        cargarHistorial();
                    }

                    if (!Array.isArray(payload.notifications)) {
                        return;
                    }

                    if (typeof payload.pending_total === 'number' && Number.isFinite(payload.pending_total)) {
                        pendientesNoVistos = Math.max(0, Math.trunc(payload.pending_total));
                    }

                    if (payload.notifications.length > 0) {
                        const { agregados, nuevos } = agregarNotificacionesPendientes(payload.notifications);

                        if (!(typeof payload.pending_total === 'number' && Number.isFinite(payload.pending_total))) {
                            pendientesNoVistos = Math.max(0, pendientesNoVistos + agregados);
                        }

                        actualizarBadge(pendientesNoVistos);

                        if (agregados > 0) {
                            reproducirSonidoNotificacion();
                            nuevos.forEach(item => {
                                if (item && item.mensaje) {
                                    mostrarToast(item);
                                }
                            });
                        }
                    } else {
                        actualizarBadge(pendientesNoVistos);
                    }
                })
                .catch((error) => {
                    console.error('Error de red al consultar notificaciones', error);
                });
        };

        dropdownEl.addEventListener('shown.bs.dropdown', () => {
            if (!notificacionesHabilitadas) {
                actualizarBadge(0);
                return;
            }
            pendientesNoVistos = 0;
            actualizarBadge(0);
        });

        actualizarEstadoVisual(notificacionesHabilitadas);
        if (notificacionesHabilitadas) {
            cargarHistorial();
        }
        consultarNotificaciones();
        setInterval(consultarNotificaciones, 10000);
    }







            
                            


           
    const iniciarRelojPie = () => {
        const clockEl = document.getElementById('appFooterClock');
        if (!clockEl) {
            return;
        }

        const valueEl = clockEl.querySelector('[data-clock-value]');
        if (!valueEl) {
            return;
        }

        const timezone = clockEl.getAttribute('data-app-timezone') || '';
        const formatoHora = { hour: '2-digit', minute: '2-digit', hour12: false };
        const locales = ['es-GT', 'es'];
        const soportaIntl = typeof Intl === 'object' && typeof Intl.DateTimeFormat === 'function';
        let timezoneWarningShown = false;

        const obtenerHora = () => {
            const ahora = new Date();
            if (timezone && soportaIntl) {
                try {
                    const opciones = Object.assign({}, formatoHora, { timeZone: timezone });
                    const formatter = new Intl.DateTimeFormat(locales, opciones);
                    return formatter.format(ahora);
                } catch (error) {
                    if (!timezoneWarningShown) {
                        console.warn('No se pudo aplicar la zona horaria configurada.', error);
                        timezoneWarningShown = true;
                    }
                }
            }
            if (soportaIntl) {
                return ahora.toLocaleTimeString(locales, formatoHora);
            }
            return ahora.toLocaleTimeString();
        };

        const actualizar = () => {
            const hora = obtenerHora();
            if (hora) {
                valueEl.textContent = hora;
            }
        };

        const programarActualizacion = () => {
            actualizar();
            const ahora = new Date();
            const transcurrido = (ahora.getSeconds() * 1000) + ahora.getMilliseconds();
            let retraso = 60000 - transcurrido;
            if (!Number.isFinite(retraso) || retraso <= 0) {
                retraso = 60000;
            }
            window.setTimeout(() => {
                programarActualizacion();
            }, retraso);
        };

        programarActualizacion();
    };

    iniciarRelojPie();

});
