/**
 * auth_guard.js - Proteccion de paginas HTML por sesion y rol.
 */
(async function guardAuth() {
  const RUTA_INICIO = {
    ADMIN: 'index.html',
    AFILIADO: 'horarios.html',
  };

  const PERMISOS = {
    ADMIN: null,
    AFILIADO: ['asistencia.html', 'horarios.html'],
  };

  function paginaActual() {
    const path = window.location.pathname.split('/').pop();
    return path || 'index.html';
  }

  function rolPuedeEntrar(rol, pagina) {
    const permitidas = PERMISOS[rol];
    return permitidas === null || (permitidas || []).includes(pagina);
  }

  function aplicarVistaPorRol(rol, data) {
    sessionStorage.setItem('ab_usuario_rol', rol);
    sessionStorage.setItem('ab_admin_nombre', data.nombre || data.usuario || '');

    const pintarNombre = () => {
      const el = document.getElementById('adminNombre');
      if (el && data.usuario) {
        el.textContent = rol === 'AFILIADO'
          ? `${data.usuario} - Afiliado`
          : data.usuario;
      }
    };

    const filtrarMenu = () => {
      const permitidas = PERMISOS[rol];
      if (permitidas === null) return;

      document.querySelectorAll('.sidebar nav a').forEach(link => {
        const href = link.getAttribute('href') || '';
        if (!permitidas.includes(href)) link.remove();
      });
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        pintarNombre();
        filtrarMenu();
      });
    } else {
      pintarNombre();
      filtrarMenu();
    }
  }

  if (!sessionStorage.getItem('ab_session_activa')) {
    fetch('api/auth.php?logout=1', { cache: 'no-store' }).catch(() => {});
    window.location.replace('login.html');
    return;
  }

  try {
    const res = await fetch('api/auth.php?check=1', { cache: 'no-store' });
    const data = await res.json();

    if (!data.autenticado) {
      sessionStorage.removeItem('ab_session_activa');
      sessionStorage.removeItem('ab_usuario_rol');
      window.location.replace('login.html');
      return;
    }

    const rol = String(data.rol || 'ADMIN').toUpperCase();
    const pagina = paginaActual();
    if (!rolPuedeEntrar(rol, pagina)) {
      window.location.replace(RUTA_INICIO[rol] || 'login.html');
      return;
    }

    aplicarVistaPorRol(rol, data);
  } catch (e) {
    sessionStorage.removeItem('ab_session_activa');
    sessionStorage.removeItem('ab_usuario_rol');
    window.location.replace('login.html');
  }
})();
