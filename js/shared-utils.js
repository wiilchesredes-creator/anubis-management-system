/**
 * ANUBIS BOX - Utilidades Compartidas
 * Funciones comunes reutilizables en todo el proyecto
 */

// ===== NOTIFICACIONES =====
/**
 * Muestra una notificación toast
 * @param {string} mensaje - Texto del mensaje
 * @param {string} tipo - Tipo: 'ok', 'err', 'warning' (default: 'ok')
 */
function mostrarToast(mensaje, tipo = 'ok') {
    const toastElement = document.getElementById('toast');
    if (!toastElement) return;
    
    toastElement.textContent = mensaje;
    toastElement.className = `toast ${tipo} show`;
    setTimeout(() => toastElement.classList.remove('show'), UI_CONFIG.TOAST_TIMEOUT);
}

// Alias para compatibilidad backward
const toast = mostrarToast;

// ===== MANEJO DEL SIDEBAR =====
/**
 * Toggle del sidebar (abrir/cerrar)
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    }
}

/**
 * Cierra el sidebar
 */
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    }
}

/**
 * Cierra sidebar si se hace click en el overlay
 */
function closeSidebarOnOverlayClick(event) {
    const overlay = document.getElementById('sidebarOverlay');
    if (event.target === overlay) {
        closeSidebar();
    }
}

// ===== MANEJO DE MODALES =====
/**
 * Abre un modal
 * @param {string} modalId - ID del modal
 */
function abrirModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
    }
}

/**
 * Cierra un modal
 * @param {string} modalId - ID del modal
 */
function cerrarModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Cierra modal si se hace click en el backdrop
 * @param {Event} event - Evento de click
 * @param {string} modalId - ID del modal
 */
function cerrarModalSiBackdrop(event, modalId) {
    const modal = document.getElementById(modalId);
    if (event.target === modal) {
        cerrarModal(modalId);
    }
}

// ===== VALIDACIÓN =====
/**
 * Valida que los elementos especificados tengan valor
 * @param {string[]} elementIds - Array de IDs de elementos
 * @returns {boolean} true si todos son válidos
 */
function validarCampos(elementIds = []) {
    let esValido = true;
    
    elementIds.forEach(id => {
        const elemento = document.getElementById(id);
        if (!elemento) return;
        
        if (!elemento.value || !elemento.value.trim()) {
            elemento.classList.add('error');
            esValido = false;
        } else {
            elemento.classList.remove('error');
        }
    });
    
    return esValido;
}

/**
 * Limpia los errores de validación de elementos
 * @param {string[]} elementIds - Array de IDs de elementos
 */
function limpiarErrores(elementIds = []) {
    elementIds.forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) {
            elemento.classList.remove('error');
        }
    });
}

// ===== FORMATEO DE DATOS =====
/**
 * Formatea una fecha al formato local
 * @param {string|Date} fecha - Fecha a formatear
 * @returns {string} Fecha formateada
 */
function formatearFecha(fecha) {
    try {
        const d = new Date(fecha);
        return d.toLocaleDateString(FORMATO_FECHA.locale, FORMATO_FECHA.opciones);
    } catch (e) {
        return fecha;
    }
}

/**
 * Formatea un número como moneda
 * @param {number} cantidad - Cantidad a formatear
 * @returns {string} Cantidad formateada
 */
function formatearMoneda(cantidad) {
    try {
        return new Intl.NumberFormat(FORMATO_MONEDA.locale, FORMATO_MONEDA.opciones).format(cantidad);
    } catch (e) {
        return '$' + cantidad.toLocaleString('es-CO');
    }
}

/**
 * Acortado: formatea moneda sin símbolo
 * @param {number} n - Cantidad
 * @returns {string} Número formateado
 */
function peso(n) {
    return Number(n).toLocaleString('es-CO');
}

/**
 * Calcula edad a partir de fecha de nacimiento
 * @param {string} fechaNacimiento - Fecha en formato YYYY-MM-DD
 * @returns {number} Edad en años
 */
function calcularEdad(fechaNacimiento) {
    const hoy = new Date();
    const nacimiento = new Date(fechaNacimiento);
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const mesActual = hoy.getMonth();
    const mesNacimiento = nacimiento.getMonth();
    
    if (mesActual < mesNacimiento || (mesActual === mesNacimiento && hoy.getDate() < nacimiento.getDate())) {
        edad--;
    }
    
    return edad;
}

/**
 * Obtiene fecha actual formateada
 * @returns {string} Fecha actual
 */
function obtenerFechaActual() {
    return new Date().toLocaleDateString(FORMATO_FECHA.locale, FORMATO_FECHA.opciones);
}

// ===== UTILIDADES DOM =====
/**
 * Obtiene valor de un elemento por ID
 * @param {string} id - ID del elemento
 * @returns {string} Valor del elemento
 */
function obtenerValor(id) {
    const elemento = document.getElementById(id);
    return elemento ? elemento.value : '';
}

/**
 * Establece valor de un elemento por ID
 * @param {string} id - ID del elemento
 * @param {string} valor - Valor a establecer
 */
function establecerValor(id, valor) {
    const elemento = document.getElementById(id);
    if (elemento) {
        elemento.value = valor;
    }
}

/**
 * Limpia un elemento select
 * @param {string} selectId - ID del select
 */
function limpiarSelect(selectId) {
    const select = document.getElementById(selectId);
    if (select) {
        select.innerHTML = '<option value="">Selecciona una opción</option>';
    }
}

/**
 * Agrega opciones a un select
 * @param {string} selectId - ID del select
 * @param {Array} opciones - Array de objetos {value, text}
 */
function agregarOpcionesSelect(selectId, opciones = []) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    opciones.forEach(opcion => {
        const option = document.createElement('option');
        option.value = opcion.value;
        option.textContent = opcion.text;
        select.appendChild(option);
    });
}

/**
 * Obtiene datos de formulario como objeto
 * @param {string[]} campos - Array de IDs de campos
 * @returns {Object} Objeto con datos del formulario
 */
function obtenerDatosFormulario(campos = []) {
    const datos = {};
    campos.forEach(campo => {
        const elemento = document.getElementById(campo);
        if (elemento) {
            datos[campo] = elemento.value;
        }
    });
    return datos;
}

/**
 * Llena formulario con datos
 * @param {Object} datos - Objeto con datos
 */
function llenarFormulario(datos = {}) {
    Object.keys(datos).forEach(key => {
        const elemento = document.getElementById(key);
        if (elemento) {
            elemento.value = datos[key];
        }
    });
}

/**
 * Limpia todos los campos de un formulario
 * @param {string[]} campos - Array de IDs de campos
 */
function limpiarFormulario(campos = []) {
    campos.forEach(campo => {
        const elemento = document.getElementById(campo);
        if (elemento) {
            elemento.value = '';
            elemento.classList.remove('error');
        }
    });
}

// ===== UTILIDADES GENERALES =====
/**
 * Realiza un delay asíncrono
 * @param {number} ms - Milisegundos a esperar
 * @returns {Promise}
 */
function esperar(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Debounce para funciones
 * @param {Function} func - Función a ejecutar
 * @param {number} ms - Milisegundos de espera
 * @returns {Function} Función debounced
 */
function debounce(func, ms = UI_CONFIG.DEBOUNCE_DELAY) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), ms);
    };
}

/**
 * Copia texto al portapapeles
 * @param {string} texto - Texto a copiar
 */
function copiarAlPortapapeles(texto) {
    navigator.clipboard.writeText(texto).then(() => {
        mostrarToast('Copiado al portapapeles', 'ok');
    }).catch(err => {
        mostrarToast('Error al copiar', 'err');
    });
}

// ===== SESSION / AUTH =====
/**
 * Obtiene un valor de sessionStorage
 * @param {string} key - Clave
 * @returns {string} Valor
 */
function obtenerDelSession(key) {
    return sessionStorage.getItem(key);
}

/**
 * Establece un valor en sessionStorage
 * @param {string} key - Clave
 * @param {string} valor - Valor
 */
function guardarEnSession(key, valor) {
    sessionStorage.setItem(key, valor);
}

/**
 * Obtiene nombre del admin
 * @returns {string} Nombre del admin
 */
function obtenerNombreAdmin() {
    return sessionStorage.getItem(SESSION_KEYS.ADMIN_NOMBRE) || 'Admin';
}

/**
 * Obtiene rol del usuario
 * @returns {string} Rol del usuario
 */
function obtenerRolUsuario() {
    return sessionStorage.getItem(SESSION_KEYS.USUARIO_ROLE) || '';
}

/**
 * Verifica si hay sesión activa
 * @returns {boolean}
 */
function haySessionActiva() {
    return sessionStorage.getItem(SESSION_KEYS.ACTIVA) === 'true';
}

/**
 * Cierra sesión
 */
function cerrarSession() {
    sessionStorage.clear();
    window.location.href = 'logout.php';
}

// ===== INICIALIZACIÓN =====
/**
 * Inicia los event listeners comunes
 */
function inicializarEventosComunes() {
    // Esperar a que todos los elementos estén disponibles
    if (document.readyState === 'loading') {
        console.warn('[shared-utils] DOM aún cargando, esperando DOMContentLoaded');
        return;
    }

    console.log('[shared-utils] Inicializando event listeners');

    // Toggle sidebar - botón menú
    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
        console.log('[shared-utils] ✓ menuToggle inicializado');
    } else {
        console.warn('[shared-utils] ✗ menuToggle no encontrado');
    }
    
    // Cerrar sidebar al hacer click en overlay
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function(e) {
            if (e.target === this) {
                closeSidebar();
            }
        });
        console.log('[shared-utils] ✓ sidebarOverlay inicializado');
    } else {
        console.warn('[shared-utils] ✗ sidebarOverlay no encontrado');
    }
    
    // Cerrar sidebar al hacer click en un enlace de navegación
    const sidebarLinks = document.querySelectorAll('.sidebar a, aside a');
    if (sidebarLinks.length > 0) {
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeSidebar();
            });
        });
        console.log(`[shared-utils] ✓ ${sidebarLinks.length} enlaces del sidebar inicializados`);
    }

    // Cerrar sidebar con tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });
    console.log('[shared-utils] ✓ Evento Escape inicializado');
}

// Inicializar cuando el DOM esté completamente listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarEventosComunes);
} else if (document.readyState === 'interactive') {
    // Si estamos en interactive, esperar a complete
    setTimeout(inicializarEventosComunes, 100);
} else {
    // DOM ya está completo
    inicializarEventosComunes();
}
