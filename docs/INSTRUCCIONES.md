# 🔐 ANUBIS BOX — Sistema de Login: Guía de integración

## Archivos entregados

| Archivo | Destino en XAMPP | Descripción |
|---|---|---|
| `login.html` | `/anubisbox/login.html` | Página de inicio de sesión |
| `api/auth.php` | `/anubisbox/api/auth.php` | Endpoint de autenticación |
| `api/session_check.php` | `/anubisbox/api/session_check.php` | Guard PHP reutilizable |
| `auth_guard.js` | `/anubisbox/auth_guard.js` | Guard JS para páginas HTML |
| `setup_admin.php` | `/anubisbox/setup_admin.php` | Setup inicial (eliminar después) |
| `logout.php` | `/anubisbox/logout.php` | Cierre de sesión |

---

## PASO 1 — Crear el administrador

1. Copia todos los archivos a tu carpeta `/anubisbox/` en XAMPP.
2. Abre en el navegador: `http://localhost/anubisbox/setup_admin.php`
3. Llena el formulario y crea tu usuario administrador.
4. ⚠️ **Elimina o renombra `setup_admin.php` después de crearlo.** Ejemplo: renómbralo a `setup_admin.php.bak`

---

## PASO 2 — Proteger cada archivo PHP de la API

Al inicio de cada `api/*.php`, agrega esta línea **justo después** del `require_once '../config/database.php';`:

```php
require_once __DIR__ . '/session_check.php';
```

### Archivos a modificar:
- `api/clientes.php`
- `api/planes.php`
- `api/ingresos.php`
- `api/egresos.php`
- `api/balance.php`
- `api/actualizar_creditos.php`

### Ejemplo — cómo queda `balance.php`:
```php
<?php
require_once '../config/database.php';
require_once __DIR__ . '/session_check.php';   // ← AGREGAR ESTA LÍNEA

header('Content-Type: application/json; charset=utf-8');
// ... resto del archivo igual
```

---

## PASO 3 — Proteger cada página HTML

En **cada HTML** (`index.html`, `finanzas.html`, `membresias.html`, `planes.html`), agrega dentro del `<head>`:

```html
<script src="auth_guard.js"></script>
```

Ponlo como **primer script** del `<head>`, antes de cualquier otro `<script>`.

---

## PASO 4 — Agregar botón de cerrar sesión al header

En cada HTML, dentro del `<header>`, agrega en la tercera columna del grid (después de `.header-logo`):

```html
<div class="nav-right" style="grid-column:3;display:flex;justify-content:flex-end;align-items:center;gap:12px">
  <span id="adminNombre" style="font-size:12px;color:#555"></span>
  <a href="logout.php"
     style="background:transparent;border:1px solid #2a2d3a;color:#888;padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;transition:all .2s"
     onmouseover="this.style.borderColor='#f87171';this.style.color='#f87171'"
     onmouseout="this.style.borderColor='#2a2d3a';this.style.color='#888'">
    🚪 Salir
  </a>
</div>
```

---

## PASO 5 — Verificar

1. Abre `http://localhost/anubisbox/index.html` → debe redirigir a `login.html`
2. Ingresa con tu usuario y contraseña → debe entrar a `index.html`
3. Abre otra pestaña en privado e intenta abrir `index.html` directamente → debe redirigir a login
4. Haz clic en "Salir" → debe cerrar sesión y volver al login
5. Intenta 5 veces con contraseña incorrecta → debe bloquearse 30 segundos

---

## Seguridades implementadas

| Medida | Descripción |
|---|---|
| `password_hash` bcrypt (cost 12) | Las contraseñas nunca se guardan en texto plano |
| Sesiones PHP del servidor | El token vive en el servidor, no en el navegador |
| `session_regenerate_id` | Previene ataques de session fixation |
| Timeout de 1 hora | Sesión expira por inactividad |
| Bloqueo por intentos | 5 intentos fallidos → 30s de bloqueo (cliente + servidor) |
| Mismo mensaje de error | No revela si el usuario existe o no |
| `HttpOnly` cookie | JavaScript no puede leer la cookie de sesión |
| `SameSite: Strict` | Protección CSRF básica |
| Guard PHP en API | Sin sesión → 401, los datos nunca se sirven |
| Guard JS en HTML | Sin sesión → redirige al login antes de renderizar |

---

## Cambiar contraseña (futuro)

Para cambiar la contraseña de un admin existente, puedes ejecutar en phpMyAdmin:

```sql
UPDATE admins
SET password_hash = '$2y$12$HASH_AQUI'
WHERE usuario = 'tu_usuario';
```

O agregar una página de cambio de contraseña más adelante.
