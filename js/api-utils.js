/**
 * ANUBIS BOX - API Utilities
 * Wrapper para llamadas fetch con manejo de errores centralizado
 */

/**
 * Realiza una llamada a la API
 * @param {string} endpoint - Endpoint de la API (sin 'api/' prefijo)
 * @param {string} metodo - GET, POST, PUT, DELETE
 * @param {Object} datos - Datos a enviar (para POST/PUT)
 * @returns {Promise<Object>} Respuesta JSON
 */
async function llamarAPI(endpoint, metodo = 'GET', datos = null) {
    try {
        const opciones = {
            method: metodo,
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if (datos && (metodo === 'POST' || metodo === 'PUT')) {
            opciones.body = JSON.stringify(datos);
        }
        
        const urlCompleta = endpoint.startsWith('api/') 
            ? endpoint 
            : `api/${endpoint}`;
        
        const respuesta = await fetch(urlCompleta, opciones);
        const json = await respuesta.json();
        
        if (!respuesta.ok) {
            throw new Error(json.error || `Error HTTP: ${respuesta.status}`);
        }
        
        return json;
    } catch (error) {
        console.error(`Error en API (${endpoint}):`, error);
        mostrarToast(`Error de conexión: ${error.message}`, 'err');
        throw error;
    }
}

/**
 * Alias para GET
 */
async function obtenerDelAPI(endpoint) {
    return llamarAPI(endpoint, 'GET');
}

/**
 * Alias para POST
 */
async function enviarAlAPI(endpoint, datos) {
    return llamarAPI(endpoint, 'POST', datos);
}

/**
 * Alias para PUT
 */
async function actualizarEnAPI(endpoint, datos) {
    return llamarAPI(endpoint, 'PUT', datos);
}

/**
 * Alias para DELETE
 */
async function eliminarDelAPI(endpoint) {
    return llamarAPI(endpoint, 'DELETE');
}

/**
 * Llamada simple GET para verificar sesión
 */
async function verificarSession() {
    try {
        const respuesta = await llamarAPI('session_check.php', 'GET');
        return respuesta.activa === true;
    } catch (e) {
        return false;
    }
}

/**
 * Wrapper para cargar datos con manejo de errores y UI
 * @param {Function} funcionCarga - Función async que carga datos
 * @param {Function} funcionRender - Función que renderiza los datos (opcional)
 * @param {string} mensajeError - Mensaje de error personalizado (opcional)
 */
async function cargarYRenderizar(funcionCarga, funcionRender = null, mensajeError = null) {
    try {
        const datos = await funcionCarga();
        if (funcionRender) {
            funcionRender(datos);
        }
        return datos;
    } catch (error) {
        mostrarToast(mensajeError || 'Error al cargar datos', 'err');
        console.error('Error:', error);
        return null;
    }
}

/**
 * Carga datos con debounce para búsqueda/filtro
 */
const cargarConDebounce = (funcionCarga, tiempo = UI_CONFIG.DEBOUNCE_DELAY) => {
    return debounce(funcionCarga, tiempo);
};
