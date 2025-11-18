# Despliegue del Backend en Render.com

## Pasos para desplegar:

### 1. Preparar el repositorio
- Asegúrate de que todos los cambios estén en Git
- Sube el código a GitHub/GitLab/Bitbucket

### 2. Crear cuenta en Render.com
- Ve a https://render.com
- Crea una cuenta gratuita (puedes usar GitHub)

### 3. Crear nuevo Web Service
1. Click en "New +" → "Web Service"
2. Conecta tu repositorio
3. Configuración:
   - **Name**: sistema-creditos-backend
   - **Region**: Oregon (US West) o el más cercano
   - **Branch**: main (o tu rama principal)
   - **Root Directory**: backend
   - **Environment**: PHP
   - **Build Command**: `composer install --no-dev --optimize-autoloader || echo "No composer needed"`
   - **Start Command**: `php -S 0.0.0.0:$PORT -t .`
   - **Plan**: Free

### 4. Configurar Variables de Entorno
En la sección "Environment Variables", agrega:

```
DB_HOST=tu-mysql-host-de-railway.railway.app
DB_PORT=3306
DB_NAME=railway
DB_USER=root
DB_PASSWORD=tu-password-de-railway
```

**IMPORTANTE**: Usa las credenciales de tu base de datos MySQL que ya está funcionando en Railway.

### 5. Desplegar
- Click en "Create Web Service"
- Render automáticamente:
  - Clonará tu repositorio
  - Instalará dependencias
  - Iniciará el servidor PHP
  - Te dará una URL pública (ej: https://sistema-creditos-backend.onrender.com)

### 6. Actualizar Frontend
Una vez que tengas la URL del backend de Render, actualiza el archivo `frontend/.env.production`:

```
REACT_APP_API_URL=https://sistema-creditos-backend.onrender.com
```

Y redespliega el frontend en Railway.

## Verificar que funciona

Prueba estos endpoints:

1. **Health check**: https://tu-backend.onrender.com/
2. **API test**: https://tu-backend.onrender.com/api/usuarios.php

## Ventajas de Render vs Railway para PHP

✅ Soporte nativo de PHP sin configuración compleja
✅ Servidor PHP integrado funciona perfectamente
✅ Variables de entorno fáciles de configurar
✅ Logs claros y accesibles
✅ SSL automático
✅ Plan gratuito generoso

## Notas importantes

- El plan gratuito de Render "duerme" después de 15 minutos de inactividad
- La primera petición después de dormir puede tardar 30-60 segundos
- Para mantenerlo activo 24/7, necesitarías el plan de pago ($7/mes)
- O puedes usar un servicio de "ping" gratuito como UptimeRobot

## Troubleshooting

Si algo no funciona:
1. Revisa los logs en el dashboard de Render
2. Verifica que las variables de entorno estén correctas
3. Asegúrate de que el MySQL de Railway permita conexiones externas
4. Prueba la conexión a la base de datos desde los logs
