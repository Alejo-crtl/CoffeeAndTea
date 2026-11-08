@echo off
setlocal
title CoffeeAndtea - copia del escritorio

rem Esta copia se abre con su propio servidor PHP para no depender de Apache.
set "PHP="
for /d %%D in ("C:\laragon\bin\php\php-*") do set "PHP=%%~fD\php.exe"
if not defined PHP (
    where php >nul 2>nul
    if not errorlevel 1 set "PHP=php"
)
if not defined PHP (
    echo No se encontro PHP.
    echo Instala Laragon o abre el proyecto desde C:\laragon\www\CoffeeAndtea
    pause
    exit /b 1
)

cd /d "%~dp0"

rem Busca un puerto libre a partir de 8080.
set "PUERTO=8080"
for /f %%P in ('powershell -NoProfile -Command "$p=8080; while (Get-NetTCPConnection -State Listen -LocalPort $p -ErrorAction SilentlyContinue) { $p++ }; $p"') do set "PUERTO=%%P"

echo.
echo  CoffeeAndtea se abrira en http://localhost:%PUERTO%/
echo  Manten esta ventana abierta mientras usas el sistema.
echo  Para detenerlo, cierra esta ventana o pulsa Ctrl+C.
echo.

start "" powershell -NoProfile -WindowStyle Hidden -Command "Start-Sleep -Seconds 2; Start-Process 'http://localhost:%PUERTO%/'"
"%PHP%" -S 127.0.0.1:%PUERTO% -t . scripts\router.php
if errorlevel 1 pause
