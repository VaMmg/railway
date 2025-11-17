@echo off
REM ============================================
REM Ejecutar Respaldo Manual
REM ============================================

echo.
echo ============================================
echo RESPALDO MANUAL DE BASE DE DATOS
echo ============================================
echo.

REM Ruta de PHP (ajustar según tu instalación de XAMPP)
set PHP_PATH=C:\xampp\php\php.exe
set SCRIPT_PATH=%~dp0backup_database.php

if not exist "%PHP_PATH%" (
    echo ERROR: No se encontro PHP en %PHP_PATH%
    echo Por favor, edita este archivo y ajusta la ruta de PHP
    pause
    exit /b 1
)

echo Ejecutando respaldo...
echo.

"%PHP_PATH%" "%SCRIPT_PATH%"

echo.
echo Presiona cualquier tecla para salir...
pause > nul
