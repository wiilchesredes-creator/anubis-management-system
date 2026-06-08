@echo off
REM Script mejorado de inicio para WhatsApp Bot en Windows
REM Incluye detección de problemas y soluciones automáticas

echo.
echo ╔════════════════════════════════════════════════════════╗
echo ║   AnubisBox WhatsApp Bot - Iniciador                  ║
echo ║   Versión 1.0                                         ║
echo ╚════════════════════════════════════════════════════════╝
echo.

REM Verificar Node.js
node --version >nul 2>&1
if errorlevel 1 (
    echo.
    echo ❌ ERROR: Node.js no está instalado
    echo Descárgalo desde: https://nodejs.org/
    echo.
    pause
    exit /b 1
)

echo ✅ Node.js detectado:
node --version
echo.

REM Verificar que estamos en la carpeta correcta
if not exist "server.js" (
    echo ❌ ERROR: No estamos en la carpeta del bot
    echo Navega a: C:\xampp\htdocs\anubisbox\whatsapp-bot
    echo.
    pause
    exit /b 1
)

echo ✅ Carpeta correcta detectada
echo.

REM Limpiar caché vieja si existe
if exist "node_modules\.cache" (
    echo 🧹 Limpiando caché...
    rmdir /s /q "node_modules\.cache" 2>nul
)

echo.
echo 🚀 Iniciando WhatsApp Bot...
echo.
echo ═══════════════════════════════════════════════════════
echo.

node server.js

if errorlevel 1 (
    echo.
    echo ❌ Error al iniciar el bot
    echo.
    echo 🔧 Soluciones:
    echo.
    echo 1. Instalar Chrome:
    echo    npx puppeteer browsers install chrome@stable
    echo.
    echo 2. Limpiar caché:
    echo    rmdir C:\Users\TORRE\.cache\puppeteer /s /q
    echo.
    echo 3. Reinstalar dependencias:
    echo    npm install
    echo.
    pause
    exit /b 1
)
