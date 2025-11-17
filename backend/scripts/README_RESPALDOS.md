# Sistema de Respaldo Automático de Base de Datos

## Descripción

Sistema automático de respaldo diario de la base de datos del Sistema de Créditos.

## Archivos

- `backup_database.php` - Script principal de respaldo
- `configurar_respaldo_windows.bat` - Configurar tarea programada (Windows)
- `ejecutar_respaldo_manual.bat` - Ejecutar respaldo manualmente
- `../copias_de_respaldo/` - Carpeta donde se guardan los respaldos

## Configuración Inicial

### Windows (XAMPP)

1. **Abrir como Administrador** el archivo `configurar_respaldo_windows.bat`
2. El script creará una tarea programada que se ejecuta diariamente a las 23:59
3. ¡Listo! Los respaldos se harán automáticamente

### Configuración Manual

Si necesitas ajustar la configuración, edita `backup_database.php`:

```php
// Configuración de la base de datos
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'sistema_creditos';

// Configuración de respaldo
$backupDir = __DIR__ . '/../copias_de_respaldo';
$maxBackups = 30; // Mantener últimos 30 días
```

## Uso

### Respaldo Automático
- Se ejecuta automáticamente todos los días a las 23:59
- No requiere intervención manual

### Respaldo Manual
1. Doble clic en `ejecutar_respaldo_manual.bat`
2. O ejecutar desde terminal:
   ```bash
   php backup_database.php
   ```

## Ubicación de Respaldos

Los respaldos se guardan en:
```
backend/copias_de_respaldo/
├── backup_2025-11-17_23-59-00.sql.gz
├── backup_2025-11-18_23-59-00.sql.gz
├── backup_2025-11-19_23-59-00.sql.gz
└── backup_log.txt
```

## Compresión

Los respaldos se comprimen automáticamente con GZIP para ahorrar espacio:
- Archivo SQL: ~10 MB
- Archivo comprimido: ~1-2 MB (ahorro del 80-90%)

## Limpieza Automática

El sistema mantiene automáticamente los últimos 30 respaldos y elimina los más antiguos.

## Características

- Respaldo completo de la base de datos
- Compresión automática (GZIP)
- Limpieza de respaldos antiguos
- Log de respaldos
- Nombres con fecha y hora
- Verificación de integridad

## Verificar Tarea Programada

### Windows
1. Abrir "Programador de tareas" (Task Scheduler)
2. Buscar: "Respaldo_Sistema_Creditos"
3. Ver historial de ejecuciones

## Log de Respaldos

El archivo `backup_log.txt` contiene el historial:
```
[2025-11-17_23-59-00] EXITOSO - Tamaño: 1.5 MB
[2025-11-18_23-59-00] EXITOSO - Tamaño: 1.6 MB
[2025-11-19_23-59-00] EXITOSO - Tamaño: 1.7 MB
```

## Restaurar un Respaldo

Para restaurar un respaldo:

1. Descomprimir el archivo .gz:
   ```bash
   gunzip backup_2025-11-17_23-59-00.sql.gz
   ```

2. Importar a MySQL:
   ```bash
   mysql -u root -p sistema_creditos < backup_2025-11-17_23-59-00.sql
   ```

O desde phpMyAdmin:
1. Abrir phpMyAdmin
2. Seleccionar base de datos "sistema_creditos"
3. Ir a "Importar"
4. Seleccionar el archivo .sql
5. Ejecutar

## Importante

- Los respaldos se guardan en el servidor local
- Se recomienda copiar los respaldos a un almacenamiento externo periódicamente
- Verificar que los respaldos se estén generando correctamente
- Probar la restauración de un respaldo ocasionalmente

## Solución de Problemas

### Error: "mysqldump no encontrado"
- Editar `backup_database.php`
- Ajustar la ruta de `$mysqldumpPath`
- Para XAMPP: `C:/xampp/mysql/bin/mysqldump`

### Error: "Permiso denegado"
- Ejecutar como Administrador
- Verificar permisos de la carpeta `copias_de_respaldo`

### Respaldo vacío o muy pequeño
- Verificar credenciales de base de datos
- Verificar que la base de datos existe
- Revisar el archivo `backup_log.txt`

## Soporte

Para más ayuda, revisar el log de errores en:
- `copias_de_respaldo/backup_log.txt`
