@echo off
REM Script de instalación para WhatsApp Bot en Windows
REM Ejecutar como administrador

echo.
echo ╔════════════════════════════════════════════════════════╗
echo ║   AnubisBox WhatsApp Bot - Instalación (Windows)      ║
echo ╚════════════════════════════════════════════════════════╝
echo.

REM Verificar si Node.js está instalado
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

REM Verificar si npm está disponible
npm --version >nul 2>&1
if errorlevel 1 (
    echo.
    echo ❌ ERROR: npm no está disponible
    echo.
    pause
    exit /b 1
)

echo ✅ npm detectado:
npm --version
echo.

REM Instalar dependencias
echo 📦 Instalando dependencias (esto puede tardar 5-10 minutos)...
echo.
call npm install

if errorlevel 1 (
    echo.
    echo ❌ ERROR en la instalación de dependencias
    echo.
    pause
    exit /b 1
)

echo.
echo ✅ ¡Instalación completada!
echo.
echo 📝 Próximos pasos:
echo.
echo 1. Abre la terminal en esta carpeta
echo 2. Ejecuta: npm start
echo 3. Se abrirá una ventana del navegador
echo 4. Escanea el código QR con tu teléfono (WhatsApp)
echo 5. ¡Listo! El bot estará automatizado
echo.
pause
