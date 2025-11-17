# 🚀 Pasos Rápidos para Subir a Railway

## 1️⃣ Subir a GitHub (5 minutos)

```bash
# Si no tienes git inicializado
git init
git add .
git commit -m "Sistema de créditos listo para Railway"

# Crea un repositorio en GitHub.com y luego:
git remote add origin https://github.com/TU_USUARIO/TU_REPO.git
git branch -M main
git push -u origin main
```

## 2️⃣ Crear Proyecto en Railway (10 minutos)

### A. Crear Base de Datos
1. Ve a https://railway.app y crea cuenta (gratis con GitHub)
2. Click en "New Project" → "Provision MySQL"
3. Espera que se cree (1-2 minutos)
4. Click en MySQL → "Variables" → Copia estas variables:
   - `MYSQLHOST`
   - `MYSQLPORT`
   - `MYSQLDATABASE`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`

### B. Crear Backend
1. En el mismo proyecto, click "+ New" → "GitHub Repo"
2. Selecciona tu repositorio
3. Click en el servicio creado → "Settings"
4. En "Root Directory" escribe: `backend`
5. Click en "Variables" → Agrega estas:

```
DB_HOST=<pega MYSQLHOST>
DB_PORT=<pega MYSQLPORT>
DB_NAME=<pega MYSQLDATABASE>
DB_USER=<pega MYSQLUSER>
DB_PASSWORD=<pega MYSQLPASSWORD>
JWT_SECRET=mi_clave_super_secreta_123456
APP_ENV=production
APP_DEBUG=false
CORS_ALLOWED_ORIGINS=*
```

6. Click "Deploy" (arriba a la derecha)
7. Espera 2-3 minutos
8. Click en "Settings" → "Networking" → "Generate Domain"
9. **COPIA LA URL** (ejemplo: `backend-production-xxxx.up.railway.app`)

### C. Crear Frontend
1. Click "+ New" → "GitHub Repo" (mismo repo)
2. Click en el servicio → "Settings"
3. En "Root Directory" escribe: `frontend`
4. Click en "Variables" → Agrega:

```
REACT_APP_API_URL=https://TU-URL-BACKEND.railway.app/backend/api/
```

⚠️ **IMPORTANTE**: Reemplaza `TU-URL-BACKEND` con la URL que copiaste del backend

5. Click "Deploy"
6. Espera 3-4 minutos
7. Click en "Settings" → "Networking" → "Generate Domain"
8. **COPIA LA URL DEL FRONTEND**

## 3️⃣ Importar Base de Datos (5 minutos)

### Opción A: Usando Railway CLI (Recomendado)

```bash
# Instalar Railway CLI
npm install -g @railway/cli

# Login
railway login

# Conectar al proyecto
railway link

# Selecciona el servicio MySQL
railway service

# Importar la base de datos
railway run mysql -h <MYSQLHOST> -u <MYSQLUSER> -p<MYSQLPASSWORD> <MYSQLDATABASE> < backend/sistema_creditos2.sql
```

### Opción B: Usando MySQL Workbench o phpMyAdmin

1. Abre MySQL Workbench
2. Nueva conexión con los datos de Railway:
   - Host: `MYSQLHOST`
   - Port: `MYSQLPORT`
   - User: `MYSQLUSER`
   - Password: `MYSQLPASSWORD`
3. Importa el archivo `backend/sistema_creditos2.sql`

## 4️⃣ Probar tu Sistema

1. Abre la URL del frontend en tu navegador
2. Intenta iniciar sesión
3. ¡Listo! Tu sistema está en la nube 🎉

## 📊 Monitorear Uso

- Railway te da **$5 USD gratis al mes**
- Ve a tu proyecto → "Usage" para ver cuánto has usado
- Con $5 puedes correr el sistema ~500 horas al mes

## ⚠️ Problemas Comunes

### "Cannot connect to database"
- Verifica que las variables DB_* estén correctas en el backend
- Asegúrate de que MySQL esté corriendo (debe tener luz verde)

### "CORS Error" en el navegador
- Verifica que `CORS_ALLOWED_ORIGINS=*` esté en las variables del backend
- Redespliega el backend

### Frontend no carga datos
- Verifica que `REACT_APP_API_URL` tenga la URL correcta del backend
- Debe terminar en `/backend/api/`
- Debe usar `https://` no `http://`
- Redespliega el frontend después de cambiar variables

### "502 Bad Gateway"
- Espera 1-2 minutos, el servicio está iniciando
- Si persiste, revisa los logs: Click en el servicio → "Deployments" → Click en el último → "View Logs"

## 🔄 Actualizar tu Sistema

Cada vez que hagas cambios:

```bash
git add .
git commit -m "Descripción de cambios"
git push
```

Railway detectará los cambios y redesplegar automáticamente (2-3 minutos).

## 💡 Tips

- Usa el plan gratuito para probar
- Si necesitas más recursos, Railway cobra ~$5-10/mes
- Puedes agregar un dominio personalizado gratis
- Los logs están disponibles en tiempo real en Railway
- Puedes pausar servicios que no uses para ahorrar créditos

## 🆘 Ayuda

Si algo no funciona:
1. Revisa los logs en Railway
2. Verifica que todas las variables estén configuradas
3. Asegúrate de que la base de datos esté importada
4. Verifica que los 3 servicios estén corriendo (luz verde)
