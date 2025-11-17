# Configuración de Tareas Programadas

## Actualización Automática de Cuotas Vencidas

Este script actualiza automáticamente el estado de las cuotas de "Pendiente" a "Vencida" cuando la fecha programada ya pasó.

### Opción 1: Ejecución Automática (Ya implementada)
El sistema actualiza automáticamente los estados cada vez que se realiza una consulta de cliente.

### Opción 2: Tarea Programada en Windows (Recomendado para producción)

1. Abrir "Programador de tareas" de Windows
2. Crear tarea básica
3. Nombre: "Actualizar Cuotas Vencidas"
4. Desencadenador: Diariamente a las 00:01
5. Acción: Iniciar un programa
6. Programa: `C:\xampp\php\php.exe`
7. Argumentos: `C:\xampp\htdocs\sistemaCredito\backend\cron\actualizar_cuotas_vencidas.php`

### Opción 3: Ejecución Manual

Ejecutar en navegador:
```
http://localhost/sistemaCredito/backend/cron/actualizar_cuotas_vencidas.php
```

O desde línea de comandos:
```
php C:\xampp\htdocs\sistemaCredito\backend\cron\actualizar_cuotas_vencidas.php
```

### Opción 4: Desde el Frontend (Solo Gerente)

Llamar al endpoint:
```
POST http://localhost/sistemaCredito/backend/api/actualizar_estados.php
```
