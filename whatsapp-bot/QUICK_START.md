# ⚡ Guía Rápida: Activar WhatsApp Bot en AnubisBox

## 📋 Paso 1: Verificar Node.js (2 minutos)

```bash
node --version
npm --version
```

Si no aparecen versiones, descarga Node.js desde: https://nodejs.org/

## 🔧 Paso 2: Instalar Dependencias (5-10 minutos)

Abre PowerShell y ejecuta:

```bash
cd C:\xampp\htdocs\anubisbox\whatsapp-bot
npm install
```

O simplemente haz doble click en `install.bat`

## ▶️ Paso 3: Iniciar el Bot

```bash
npm start
```

Verás algo como:
```
🤖 AnubisBox WhatsApp Bot v1.0
📁 Session directory: C:\xampp\htdocs\anubisbox\whatsapp-bot\.wwebjs_auth

🔄 Inicializando cliente de WhatsApp...
```

## 📱 Paso 4: Escanear QR (Primera vez)

1. Se abrirá una ventana del navegador
2. Verás un código QR en la terminal
3. Abre **WhatsApp en tu teléfono**
4. Toca: ⋮ (Más) → Dispositivos conectados → Conectar dispositivo
5. Escanea el código QR
6. Espera a que aparezca "✅ ¡Cliente de WhatsApp listo!"

## ✅ Paso 5: Verificar que Funciona

En otra terminal (sin cerrar la primera), ejecuta:

```bash
# Windows - PowerShell
$response = Invoke-WebRequest -Uri "http://localhost:3000/api/status"
$response.Content | ConvertFrom-Json | ConvertTo-Json

# O en el navegador abre:
# http://localhost:3000/api/status
```

Deberías ver:
```json
{
  "ok": true,
  "ready": true,
  "message": "Cliente listo"
}
```

## 🧪 Paso 6: Enviar Mensaje de Prueba

```bash
curl -X POST http://localhost:3000/api/send-message `
  -H "Content-Type: application/json" `
  -d '{
    "phoneNumber": "573001234567",
    "message": "Hola, este es un mensaje de prueba de AnubisBox"
  }'
```

O accede desde PHP:

```php
<?php
require_once 'api/whatsapp.php';

// Verificar estado
// GET http://localhost/anubisbox/api/whatsapp.php?action=status

// Enviar prueba
// GET http://localhost/anubisbox/api/whatsapp.php?action=send-test&phone=573001234567

// Notificar cliente
// POST http://localhost/anubisbox/api/whatsapp.php?action=notify
?>
```

## 🔄 Cómo funciona la automatización

El bot:
1. ✅ Se inicia con `npm start`
2. ✅ Verifica cada 6 horas si hay clientes para notificar
3. ✅ Lee: `/api/clientes.php?notificacion_dias=1`
4. ✅ Envía mensajes automáticamente
5. ✅ Marca clientes como notificados

## 📱 Formato de números

El bot acepta estos formatos:
- `573001234567` ✅
- `+573001234567` ✅
- `(300) 123-4567` ✅
- `300.123.4567` ✅

## 🛑 Detener el Bot

En la terminal donde corre, presiona: **Ctrl + C**

## 🚀 Ejecutar en Background (Opcional)

Para que siga funcionando aunque cierres la terminal:

```bash
# Instalar pm2 (gestor de procesos)
npm install -g pm2

# Ejecutar bot en background
pm2 start C:\xampp\htdocs\anubisbox\whatsapp-bot\server.js --name "whatsapp-bot"

# Ver estado
pm2 status

# Ver logs
pm2 logs whatsapp-bot

# Detener
pm2 stop whatsapp-bot
```

## ⚠️ Problemas Comunes

### "Cannot find module 'whatsapp-web.js'"
```bash
npm install whatsapp-web.js
```

### "Error: Server not responding"
- Verifica que `npm start` está ejecutándose en otra terminal
- Espera 10 segundos después de iniciar

### WhatsApp cierra sesión automáticamente
Es normal. El bot descarga actualizaciones de WhatsApp.  
Rescannea el QR cuando aparezca.

### El navegador no se abre
Ejecuta con permisos de administrador

## 📞 API desde PHP

```php
<?php
// Verificar disponibilidad
$response = file_get_contents('http://localhost:3000/api/status');
$status = json_decode($response, true);

if ($status['ok']) {
    echo "Bot disponible ✅";
} else {
    echo "Bot no disponible ❌";
}
?>
```

## 📊 Monitoreo

Ver logs en tiempo real:
```
http://localhost:3000/api/logs
```

## ✅ Checklist de Configuración

- [ ] Node.js instalado (`node --version` funciona)
- [ ] npm instalado (`npm --version` funciona)
- [ ] Dependencias instaladas (`npm install` ejecutado)
- [ ] Bot inicia (`npm start` funciona)
- [ ] QR escaneado correctamente
- [ ] Status endpoint responde: `http://localhost:3000/api/status`
- [ ] Mensaje de prueba enviado exitosamente

## 🎯 Próximos pasos

Una vez todo funcione:

1. **Agregar botón WhatsApp en index.html**
   - Botón "Enviar WhatsApp" en cada cliente

2. **Integrar notificaciones automáticas**
   - Activar en actualizar_creditos.php

3. **Personalizar mensajes**
   - Editar templates en server.js

---

**¿Necesitas ayuda?**  
Revisa el archivo README.md para más detalles técnicos.
