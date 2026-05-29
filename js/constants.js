/**
 * ANUBIS BOX - Constantes Globales
 * Centraliza todas las keys y configuraciones del proyecto
 */

// ===== SESSION STORAGE KEYS =====
const SESSION_KEYS = {
    ACTIVA: 'ab_session_activa',
    USUARIO_ROLE: 'ab_usuario_rol',
    USUARIO_ID: 'ab_usuario_id',
    ADMIN_NOMBRE: 'ab_admin_nombre',
    ADMIN_EMAIL: 'ab_admin_email'
};

// ===== API ENDPOINTS =====
const API_BASE = 'api';
const API_ENDPOINTS = {
    AUTH: `${API_BASE}/auth.php`,
    SESSION_CHECK: `${API_BASE}/session_check.php`,
    CLIENTES: `${API_BASE}/clientes.php`,
    PLANES: `${API_BASE}/planes.php`,
    HORARIOS: `${API_BASE}/horarios.php`,
    ASISTENCIA: `${API_BASE}/asistencia.php`,
    TIENDA: `${API_BASE}/tienda.php`,
    INGRESOS: `${API_BASE}/ingresos.php`,
    EGRESOS: `${API_BASE}/egresos.php`,
    BALANCE: `${API_BASE}/balance.php`,
    ACTUALIZAR_CREDITOS: `${API_BASE}/actualizar_creditos.php`
};

// ===== ESTADOS Y OPCIONES =====
const ESTADOS = {
    ACTIVO: 'activo',
    INACTIVO: 'inactivo',
    SUSPENDIDO: 'suspendido',
    VENCIDO: 'vencido'
};

const TIPOS_MEMBRESIA = {
    BASICA: 'basica',
    PREMIUM: 'premium',
    VIP: 'vip',
    ELITE: 'elite'
};

const TIPOS_ASISTENCIA = {
    INGRESO: 'ingreso',
    SALIDA: 'salida'
};

// ===== CONFIGURACIÓN DE UI =====
const UI_CONFIG = {
    TOAST_TIMEOUT: 3500,
    MODAL_ANIMATION_DURATION: 300,
    DEBOUNCE_DELAY: 500
};

// ===== COLORES Y ESTILOS =====
const COLORES = {
    PRIMARY: '#c9952a',
    PRIMARY_RGBA: 'rgba(201,149,42,.15)',
    DARK: '#1a1a1a',
    LIGHT: '#f5f5f5',
    ERROR: '#dc3545',
    SUCCESS: '#28a745',
    WARNING: '#ffc107'
};

// ===== FORMATOS =====
const FORMATO_FECHA = {
    locale: 'es-CO',
    opciones: {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }
};

const FORMATO_MONEDA = {
    locale: 'es-CO',
    opciones: {
        style: 'currency',
        currency: 'COP'
    }
};
