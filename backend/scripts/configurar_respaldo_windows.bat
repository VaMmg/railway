@echo off
REM ============================================
REM Configurar Tarea Programada de Respaldo
REM Para Windows
REM ============================================

echo.
echo ============================================
echo CONFIGURACION DE RESPALDO AUTOMATICO
echo ============================================
echo.
echo Este script configurara una tarea programada
echo para realizar respaldos diarios a las 23:59
echo.

REM Ruta de PHP (ajustar según tu instalación de XAMPP)
set PHP_PATH=C:\xampp\php\php.exe
set SCRIPT_PATH=%~dp0backup_database.php

echo Verificando PHP...
if not exist "%PHP_PATH%" (
    echo ERROR: No se encontro PHP en %PHP_PATH%
    echo Por favor, edita este archivo y ajusta la ruta de PHP
    pause
    exit /b 1
)

echo PHP encontrado: %PHP_PATH%
echo Script: %SCRIPT_PATH%
echo.

REM Crear tarea programada
echo Creando tarea programada...
schtasks /create /tn "Respaldo_Sistema_Creditos" /tr "\"%PHP_PATH%\" \"%SCRIPT_PATH%\"" /sc daily /st 23:59 /f

if %errorlevel% equ 0 (
    echo.
    echo ============================================
    echo CONFIGURACION COMPLETADA
    echo ============================================
    echo.
    echo La tarea programada se ejecutara diariamente
    echo a las 23:59 horas.
    echo.
    echo Para verificar: Panel de Control ^> Tareas Programadas
    echo Nombre de la tarea: Respaldo_Sistema_Creditos
    echo.
    echo Para probar el respaldo ahora, ejecuta:
    echo   php backup_database.php
    echo.
) else (
    echo.
    echo ERROR: No se pudo crear la tarea programada
    echo Asegurate de ejecutar este script como Administrador
    echo.
)

pause
