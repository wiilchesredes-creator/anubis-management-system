@echo off
:: ============================================
::   ANUBIS BOX — Launcher optimizado
:: ============================================

set XAMPP=C:\xampp
set URL=http://localhost/anubisbox/login.html?logout=1

:: ── Arrancar Apache si no corre ──
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I "httpd.exe" >NUL
if %ERRORLEVEL% NEQ 0 (
    start "" /MIN "%XAMPP%\apache\bin\httpd.exe"
)

:: ── Arrancar MySQL si no corre ──
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if %ERRORLEVEL% NEQ 0 (
    start "" /MIN "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini" --standalone
)

:: ── Esperar a que el puerto 80 esté activo ──
:esperar
powershell -NoProfile -Command "try { $c=New-Object Net.Sockets.TcpClient; $c.Connect('localhost',80); $c.Close(); exit 0 } catch { exit 1 }" >NUL 2>&1
if %ERRORLEVEL% NEQ 0 (
    timeout /t 1 /nobreak >NUL
    goto esperar
)

:: ── Abrir en navegador por defecto ──
start "" "%URL%"
exit /b 0
