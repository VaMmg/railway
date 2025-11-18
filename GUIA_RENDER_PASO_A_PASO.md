# 🚀 Guía Paso a Paso: Desplegar Backend en Render.com

## ✅ Preparación Completada
- ✅ Archivos de configuración creados
- ✅ Código subido a GitHub
- ✅ Base de datos MySQL funcionando en Railway

---

## 📋 PASO 1: Crear Cuenta en Render

1. Ve a: **https://render.com**
2. Click en **"Get Started"** o **"Sign Up"**
3. Elige **"Sign up with GitHub"** (más fácil)
4. Autoriza a Render para acceder a tus repositorios

---

## 📋 PASO 2: Crear Web Service

1. En el dashboard de Render, click en **"New +"** (botón azul arriba a la derecha)
2. Selecciona **"Web Service"**
3. Conecta tu repositorio:
   - Si no aparece, click en **"Configure account"** y autoriza el repositorio
   - Busca: **railway** (tu repositorio)
   - Click en **"Connect"**

---

## 📋 PASO 3: Configurar el Servicio

Llena el formulario con estos valores EXACTOS:

### Información Básica:
- **Name**: `sistema-creditos-backend`
- **Region**: `Oregon (US West)` (o el más cercano a ti)
- **Branch**: `main`
- **Root Directory**: `backend` ⚠️ MUY IMPORTANTE

### Build & Deploy:
- **Runtime**: Selecciona **"PHP"**
- **Build Command**: 
  ```
  composer install --no-dev --optimize-autoloader || echo "No composer needed"
  ```
- **Start Command**: 
  ```
  php -S 0.0.0.0:$PORT -t .
  ```

### Plan:
- Selecciona **"Free"** (gratis)

---

## 📋 PASO 4: Configurar Variables de Entorno

⚠️ **IMPORTANTE**: Necesitas las credenciales de tu MySQL de Railway

### Obtener credenciales de Railway:
1. Ve a tu proyecto en Railway
2. Click en el servicio **MySQL**
3. Ve a la pestaña **"Variables"**
4. Copia estos valores:
   - `MYSQLHOST`
   - `MYSQLPORT` (debería ser 3306)
   - `MYSQLDATABASE` (debería ser "railway")
   - `MYSQLUSER` (debería ser "root")
   - `MYSQLPASSWORD`

### Agregar en Render:
En la sección **"Environment Variables"**, agrega estas variables:

| Key | Value |
|-----|-------|
| `DB_HOST` | [Pega MYSQLHOST de Railway] |
| `DB_PORT` | `3306` |
| `DB_NAME` | `railway` |
| `DB_USER` | `root` |
| `DB_PASSWORD` | [Pega MYSQLPASSWORD de Railway] |

---

## 📋 PASO 5: Desplegar

1. Revisa que todo esté correcto
2. Click en **"Create Web Service"** (botón azul abajo)
3. Render comenzará a desplegar:
   - Clonando repositorio...
   - Instalando dependencias...
   - Iniciando servidor...
   - ⏱️ Esto toma 2-3 minutos

4. Espera a que el estado cambie a **"Live"** (verde)

---

## 📋 PASO 6: Obtener URL y Probar

1. Una vez desplegado, verás tu URL en la parte superior:
   ```
   https://sistema-creditos-backend.onrender.com
   ```

2. **Prueba estos endpoints** (abre en tu navegador):

   **Health Check:**
   ```
   https://tu-backend.onrender.com/
   ```
   Deberías ver un JSON con información del API

   **Test de Base de Datos:**
   ```
   https://tu-backend.onrender.com/test_db.php
   ```
   Deberías ver: `"success": true` y el conteo de usuarios

   **Test de API:**
   ```
   https://tu-backend.onrender.com/api/usuarios.php
   ```

---

## 📋 PASO 7: Actualizar Frontend

1. Copia tu URL de Render (ej: `https://sistema-creditos-backend.onrender.com`)

2. Edita el archivo `frontend/.env.production`:
   ```env
   REACT_APP_API_URL=https://sistema-creditos-backend.onrender.com
   ```

3. En Railway, ve a tu servicio de **Frontend**

4. Ve a **"Settings"** → **"Redeploy"**

5. Espera a que se redespliegue (2-3 minutos)

---

## ✅ Verificación Final

Una vez que todo esté desplegado:

1. **Abre tu frontend en Railway**
2. **Intenta hacer login** con:
   - Usuario: `admin`
   - Contraseña: `admin123`

3. Si funciona, ¡LISTO! 🎉

---

## 🔧 Troubleshooting

### Si el backend no funciona:

1. **Revisa los logs en Render:**
   - Ve a tu servicio en Render
   - Click en **"Logs"** (pestaña superior)
   - Busca errores en rojo

2. **Verifica variables de entorno:**
   - Ve a **"Environment"** (pestaña)
   - Asegúrate de que todas las variables estén correctas
   - Si cambias algo, click en **"Save Changes"** y se redespliegará automáticamente

3. **Verifica conexión a base de datos:**
   - Abre: `https://tu-backend.onrender.com/test_db.php`
   - Si dice "success: false", el problema es la conexión a MySQL
   - Verifica que Railway permita conexiones externas

### Si el frontend no conecta con el backend:

1. Verifica que `REACT_APP_API_URL` en `.env.production` sea correcto
2. Asegúrate de haber redespliegado el frontend después del cambio
3. Abre la consola del navegador (F12) y busca errores de CORS

---

## 📊 Información Importante

### Plan Gratuito de Render:
- ✅ 750 horas/mes gratis
- ✅ SSL automático (HTTPS)
- ✅ Despliegues ilimitados
- ⚠️ El servicio "duerme" después de 15 minutos sin uso
- ⚠️ Primera petición después de dormir tarda 30-60 segundos

### Para mantenerlo activo 24/7:
- Opción 1: Upgrade a plan de pago ($7/mes)
- Opción 2: Usar un servicio de "ping" como **UptimeRobot** (gratis)

---

## 🎯 Resumen de URLs

Después de completar todo, tendrás:

- **Backend**: `https://sistema-creditos-backend.onrender.com`
- **Frontend**: `https://tu-frontend.up.railway.app`
- **Base de datos**: MySQL en Railway (interno)

---

## 💡 Próximos Pasos

Una vez que todo funcione:

1. Cambia las contraseñas por defecto
2. Configura un servicio de ping para mantener el backend activo
3. Considera hacer backups regulares de la base de datos
4. Monitorea los logs para detectar errores

---

¿Necesitas ayuda? Revisa los logs y busca mensajes de error específicos.
