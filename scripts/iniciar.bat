@echo off
rem ============================================================
rem  CoffeeAndtea - Servidor local de respaldo (sin Laragon)
rem  Sirve SOLO la carpeta public/, que es el docroot.
rem  Acceso: http://localhost:8000/
rem  Deten el servidor con Ctrl+C y cierra esta ventana.
rem ============================================================
setlocal
set "RAIZ=%~dp0.."

if not defined PHP (
    where php >nul 2>&1
    if errorlevel 1 (
        set "PHP=C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe"
    ) else (
        set "PHP=php"
    )
)

if /I "%PHP%"=="php" goto :php_listo
if not exist "%PHP%" (
    echo No se encontro PHP.
    echo Instala PHP, agregalo al PATH o define la variable PHP con la ruta a php.exe.
    pause
    exit /b 1
)
:php_listo

if not exist "%RAIZ%\public\index.php" (
    echo No se encontro public\index.php en: %RAIZ%
    pause
    exit /b 1
)

if not exist "%RAIZ%\.env" (
    echo AVISO: no existe .env. Copia .env.example a .env y configura la base.
    echo.
)

cd /d "%RAIZ%"
echo Servidor CoffeeAndtea: http://localhost:8000/
echo Presiona Ctrl+C para detenerlo.
echo.
start "" /b powershell -NoProfile -WindowStyle Hidden -Command "Start-Sleep -Seconds 2; Start-Process 'http://localhost:8000/'"
"%PHP%" -S localhost:8000 -t public

endlocal
