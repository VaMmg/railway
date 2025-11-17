# 🚀 Guía de Despliegue en Railway

## Preparación del Proyecto

### 1. Subir a GitHub

```bash
# Inicializar git (si no lo has hecho)
git init

# Agregar todos los archivos
git add .

# Hacer commit
git commit -m "Preparar proyecto para Railway"

# Crear repositorio en GitHub y conectarlo
git remote add origin https://github.com/TU_USUARIO/TU_REPOSITORIO.git
git branch -M main
git push -u origin main
```

## Despliegue en Railway

### 2. Crear Cuenta en Railway
1. Ve a https://railway.app
2. Haz clic en "Start a New Project"
3. Inicia sesión con GitHub

### 3. Crear Servicios

Railway necesita 3 servicios separados:

#### A. Base de Datos MySQL
1. En Railway, haz clic en "+ New"
2. Selecciona "Database" → "MySQL"
3. Railway creará automáticamente la base de datos
4. Anota las variables de entorno que genera (las usarás después)

#### B. Backend (PHP)
1. Haz clic en "+ New" → "GitHub Repo"
2. Selecciona tu repositorio
3. Railway detectará el Dockerfile automáticamente
4. Configura las siguientes variables de entorno:

```
DB_HOST=<MYSQL_HOST de Railway>
DB_PORT=3306
DB_NAME=railway
DB_USER=root
DB_PASSWORD=<MYSQL_ROOT_PASSWORD de Railway>
JWT_SECRET=tu_clave_secreta_super_segura_cambiala
JWT_EXPIRATION=3600
APP_ENV=production
APP_DEBUG=false
CORS_ALLOWED_ORIGINS=*
```

5. En "Settings" → "Root Directory" → Pon: `backend`
6. En "Settings" → "Start Command" → Deja vacío (usa el del Dockerfile)
7. Despliega el servicio

#### C. Frontend (React)
1. Haz clic en "+ New" → "GitHub Repo"
2. Selecciona el mismo repositorio
3. Configura las siguientes variables de entorno:

```
REACT_APP_API_URL=https://TU-BACKEND-URL.railway.app/backend/api/
```

4. En "Settings" → "Root Directory" → Pon: `frontend`
5. En "Settings" → "Build Command" → Pon: `npm install --legacy-peer-deps && npm run build`
6. En "Settings" → "Start Command" → Pon: `npx serve -s build -l $PORT`
7. Despliega el servicio

### 4. Importar Base de Datos

Una vez que MySQL esté corriendo:

1. Descarga Railway CLI:
```bash
npm install -g @railway/cli
```

2. Inicia sesión:
```bash
railway login
```

3. Conecta a tu proyecto:
```bash
railway link
```

4. Importa tu base de datos:
```bash
railway run mysql -h <MYSQL_HOST> -u root -p<MYSQL_ROOT_PASSWORD> railway < backend/sistema_creditos2.sql
```

O usa un cliente MySQL como MySQL Workbench con las credenciales de Railway.

### 5. Verificar Despliegue

1. Railway te dará URLs públicas para cada servicio
2. Copia la URL del backend y actualiza `REACT_APP_API_URL` en el frontend
3. Redespliega el frontend
4. Accede a la URL del frontend y prueba tu aplicación

## Variables de Entorno Importantes

### Backend
- `DB_HOST`: Host de MySQL de Railway
- `DB_PORT`: 3306
- `DB_NAME`: railway (nombre por defecto)
- `DB_USER`: root
- `DB_PASSWORD`: Password generado por Railway
- `JWT_SECRET`: Clave secreta para tokens (cámbiala)

### Frontend
- `REACT_APP_API_URL`: URL completa del backend + /backend/api/

## Solución de Problemas

### Error de conexión a base de datos
- Verifica que las variables DB_* estén correctas
- Asegúrate de que el servicio MySQL esté corriendo

### CORS Error
- Verifica que `CORS_ALLOWED_ORIGINS` incluya la URL del frontend
- O usa `*` para permitir todos los orígenes (solo para pruebas)

### Frontend no se conecta al backend
- Verifica que `REACT_APP_API_URL` tenga la URL correcta del backend
- Debe terminar en `/backend/api/`
- Debe usar `https://` no `http://`

## Costos

Railway ofrece:
- **$5 USD de crédito gratis al mes** (sin tarjeta de crédito)
- Suficiente para proyectos pequeños/medianos
- Monitorea tu uso en el dashboard

## Dominios Personalizados

Para usar tu propio dominio:
1. Ve a Settings del servicio frontend
2. Haz clic en "Generate Domain" o "Custom Domain"
3. Sigue las instrucciones para configurar DNS

## Notas Importantes

- Railway asigna URLs automáticas tipo: `tu-proyecto.railway.app`
- Los servicios se reinician automáticamente si fallan
- Puedes ver logs en tiempo real en el dashboard
- El plan gratuito tiene límites de uso, monitorea tu consumo
