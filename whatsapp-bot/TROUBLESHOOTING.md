# ⚡ Solución de Problemas - WhatsApp Bot

## ❌ Error: "Could not find Chrome"

### Solución Rápida (30 segundos)

```bash
cd C:\xampp\htdocs\anubisbox\whatsapp-bot
npx puppeteer browsers install chrome@stable
npm start
```

### ¿Qué significa?
Puppeteer necesita Chrome/Chromium para funcionar. Este comando lo instala automáticamente.

---

## ❌ Error: "EADDRINUSE: address already in use :::3000"

El puerto 3000 ya está en uso por otra instancia del bot.

### Solución Rápida

**Opción 1: Matar el proceso (recomendado)**
```bash
netstat -ano | findstr :3000
taskkill /PID <NÚMERO> /F
npm start
```

**Opción 2: Usar otro puerto**
```bash
$env:PORT=3001
npm start
```

---

## ❌ Error: "Cannot find module 'whatsapp-web.js'"

Las dependencias no están instaladas.

### Solución
```bash
cd C:\xampp\htdocs\anubisbox\whatsapp-bot
npm install
npm start
```

---

## ❌ Error: "Timed out waiting for Chromium"

Chrome está tardando en iniciar o hay problema de permisos.

### Soluciones

**1. Ejecutar con permisos de administrador**
- Cierra PowerShell
- Click derecho en PowerShell
- Selecciona "Ejecutar como administrador"
- Intenta nuevamente

**2. Limpiar caché de Puppeteer**
```bash
Remove-Item -Path "C:\Users\TORRE\.cache\puppeteer" -Recurse -Force
npx puppeteer browsers install chrome@stable
npm start
```

**3. Usar Edge en lugar de Chrome**
```bash
$env:PUPPETEER_EXECUTABLE_PATH="C:\Program Files\Microsoft\Edge\Application\msedge.exe"
npm start
```

---

## ❌ El código QR no aparece

El bot está corriendo pero no ves el QR en la terminal.

### Soluciones

1. **Verifica que estés en la carpeta correcta**
   ```bash
   cd C:\xampp\htdocs\anubisbox\whatsapp-bot
   ```

2. **Busca el QR en la ventana del navegador que se abrió**
   - Se debe abrir una ventana de Chrome/Chromium
   - El QR también aparece en la terminal

3. **Borra la sesión anterior si existe**
   ```bash
   Remove-Item -Path ".wwebjs_auth" -Recurse -Force
   npm start
   ```

---

## ❌ El navegador no se abre

### Soluciones

1. **Ejecutar con permisos de administrador**

2. **Instalar Chrome manualmente**
   - Descarga desde: https://google.com/chrome
   - Ejecuta el instalador
   - Reinicia la terminal

3. **Usar Chrome del sistema**
   ```bash
   $env:PUPPETEER_EXECUTABLE_PATH="C:\Program Files\Google\Chrome\Application\chrome.exe"
   npm start
   ```

---

## ✅ Verificación de Salud

Después de iniciar el bot, verifica en otra terminal:

```bash
curl http://localhost:3000/api/status
```

Deberías ver:
```json
{
  "ok": true,
  "ready": true,
  "message": "Cliente listo"
}
```

---

## 📋 Checklist de Troubleshooting

- [ ] ¿Ejecutaste `npm install`?
- [ ] ¿Está Node.js instalado? (`node --version`)
- [ ] ¿Chrome está instalado? (Descárga desde https://google.com/chrome)
- [ ] ¿Ejecutaste con permisos de administrador?
- [ ] ¿El puerto 3000 está libre? (`netstat -ano | findstr :3000`)
- [ ] ¿Borraste `.wwebjs_auth` si necesitas re-escanear?

---

## 🆘 Último Recurso

Si nada funciona, resetea todo:

```bash
cd C:\xampp\htdocs\anubisbox\whatsapp-bot

# Limpiar todo
Remove-Item node_modules -Recurse -Force
Remove-Item package-lock.json -Force
Remove-Item .wwebjs_auth -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item C:\Users\TORRE\.cache\puppeteer -Recurse -Force -ErrorAction SilentlyContinue

# Reinstalar limpio
npm install

# Instalar Chrome
npx puppeteer browsers install chrome@stable

# Iniciar
npm start
```

---

## 📞 Información Técnica

- **Bot:** whatsapp-web.js v1.25.2
- **Node.js requerido:** v14+
- **Navegador requerido:** Chrome/Chromium/Edge
- **Puerto:** 3000 (configurable)
- **Sesión:** `.wwebjs_auth/` (local, no se sube a GitHub)

---

## 🔗 Recursos Útiles

- [Node.js](https://nodejs.org/)
- [Google Chrome](https://google.com/chrome)
- [whatsapp-web.js Docs](https://github.com/pedroslopez/whatsapp-web.js)
- [Puppeteer Docs](https://pptr.dev/)

