# 🤖 AnubisBox WhatsApp Bot

Automatización de WhatsApp Web para enviar notificaciones automáticas a clientes de AnubisBox.

## ✨ Características

✅ **Escaneo QR una sola vez** - No necesita rescannear cada vez  
✅ **Envío automático de mensajes** - Notificaciones programadas  
✅ **API REST integrada** - Fácil integración con PHP  
✅ **Notificaciones de vencimiento** - Alerta a clientes 1 día antes  
✅ **Manejo de sesiones** - Autenticación persistente  

## 📋 Requisitos Previos

- **Node.js 14+** → Descargar desde [nodejs.org](https://nodejs.org/)
- **npm** (viene con Node.js)
- **Windows 7+** (también funciona en Linux/Mac)

## 🚀 Instalación

### Opción 1: Instalación Automática (Recomendado)

```bash
cd c:\xampp\htdocs\anubisbox\whatsapp-bot
double-click install.bat
```

O en terminal:
```bash
npm install
```

### Opción 2: Instalación Manual

```bash
cd c:\xampp\htdocs\anubisbox\whatsapp-bot
npm install whatsapp-web.js qrcode-terminal express cors dotenv axios
```

## ▶️ Iniciar el Bot

```bash
cd c:\xampp\htdocs\anubisbox\whatsapp-bot
npm start
```

### Primera ejecución:
1. Se abrirá una ventana del navegador
2. Verás un código QR en la terminal
3. Abre WhatsApp en tu teléfono
4. Ve a Configuración → Dispositivos conectados → Conectar dispositivo
5. Escanea el código QR
6. ¡Listo! El bot estará automatizado

### Ejecuciones posteriores:
- El bot recordará tu sesión
- No necesitarás rescannear
- Se iniciará automáticamente

## 🔗 API REST

El bot expone los siguientes endpoints:

### 1. Verificar estado
```bash
GET http://localhost:3000/api/status
```

**Respuesta:**
```json
{
  "ok": true,
  "ready": true,
  "message": "Cliente listo",
  "timestamp": "2026-06-08T10:30:00.000Z"
}
```

### 2. Enviar mensaje (manual)
```bash
POST http://localhost:3000/api/send-message
Content-Type: application/json

{
  "phoneNumber": "573001234567",
  "message": "Hola, este es un mensaje de prueba"
}
```

**Respuesta:**
```json
{
  "ok": true,
  "messageId": "wamid.xyz123",
  "timestamp": "2026-06-08T10:30:00.000Z"
}
```

### 3. Notificar vencimiento de plan
```bash
POST http://localhost:3000/api/notify-vencimiento
Content-Type: application/json

{
  "clienteId": 5,
  "clienteNombre": "Juan Pérez",
  "clienteCelular": "573001234567",
  "planNombre": "FULL"
}
```

## 📱 Integración con AnubisBox

### Agregar botón para enviar mensaje manual

En `index.html`, agregar en la sección de acciones del cliente:

```html
<button onclick="enviarWhatsApp(cliente.id, cliente.celular, cliente.nombre)" 
        class="btn-small">
  📱 WhatsApp
</button>
```

### Script en `index.html`

```javascript
async function enviarWhatsApp(clienteId, celular, nombre) {
  const mensaje = prompt(`Enviar mensaje a ${nombre}:\n\n(Dejar vacío para mensaje predeterminado)`, '');
  
  if (mensaje === null) return; // Cancelar
  
  const msgFinal = mensaje.trim() || `¡Hola ${nombre}! Desde AnubisBox.`;
  
  try {
    const response = await fetch('http://localhost:3000/api/send-message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        phoneNumber: celular,
        message: msgFinal
      })
    });
    
    const result = await response.json();
    
    if (result.ok) {
      alert('✅ Mensaje enviado a ' + nombre);
    } else {
      alert('❌ Error: ' + result.error);
    }
  } catch (error) {
    alert('❌ Error de conexión: ' + error.message);
  }
}
```

### Notificaciones automáticas (cada 6 horas)

El bot automáticamente:
1. Consulta el endpoint `/api/clientes.php?notificacion_dias=1`
2. Obtiene clientes que necesitan notificación (1 día antes del vencimiento)
3. Envía mensajes personalizados
4. Marca como notificados en la base de datos

## ⚙️ Configuración

Editar `.env` para personalizar:

```env
PORT=3000                              # Puerto del servidor
PHP_API=http://localhost/anubisbox/api # URL del API PHP
NODE_ENV=development                   # development o production
```

## 🔧 Troubleshooting

### Error: "Cannot find module 'whatsapp-web.js'"
```bash
npm install whatsapp-web.js
```

### El navegador no se abre para el QR
Ejecutar con permisos de administrador:
```bash
npm start
```

### Mensaje: "Client not ready"
Significa que el bot aún se está inicializando. Espera 10 segundos y reintenta.

### WhatsApp cierra la sesión
Es normal. Rescannea el código QR cuando aparezca.

## 📊 Monitoreo

Ver logs en tiempo real:
```bash
GET http://localhost:3000/api/logs
```

## 🔐 Seguridad

⚠️ **IMPORTANTE:**
- El bot almacena la sesión en `.wwebjs_auth/`
- Protege esta carpeta (contiene tus datos de sesión)
- No commits a GitHub esta carpeta (`.gitignore` ya configurado)

## 📞 Formatos de teléfono soportados

- `573001234567` ✅
- `+573001234567` ✅
- `(300) 123-4567` ✅
- `300.123.4567` ✅

El bot automáticamente agrega el código de país (+57) si no está presente.

## 🎯 Casos de uso

1. **Notificación de vencimiento** → 1 día antes del vencimiento de plan
2. **Recordatorios** → Antes de clases/eventos
3. **Confirmaciones** → Al registrar nuevo cliente
4. **Alertas** → Cuando se acaban los créditos

## 📚 Documentación adicional

- [whatsapp-web.js docs](https://docs.infracost.io/)
- [Express.js docs](https://expressjs.com/)

## 📝 Licencia

MIT - Uso libre para AnubisBox

---

**Versión:** 1.0.0  
**Última actualización:** Junio 2026  
**Autor:** AnubisBox Development Team
