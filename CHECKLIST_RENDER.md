# ✅ Checklist: Desplegar Backend en Render

## Antes de empezar
- [ ] Tengo cuenta en GitHub (el código ya está subido ✅)
- [ ] Tengo las credenciales de MySQL de Railway

---

## Paso 1: Render.com
- [ ] Crear cuenta en https://render.com
- [ ] Conectar con GitHub
- [ ] Autorizar acceso al repositorio

## Paso 2: Crear Web Service
- [ ] Click en "New +" → "Web Service"
- [ ] Seleccionar repositorio "railway"
- [ ] Click en "Connect"

## Paso 3: Configuración
- [ ] Name: `sistema-creditos-backend`
- [ ] Region: Oregon (US West)
- [ ] Branch: `main`
- [ ] Root Directory: `backend` ⚠️
- [ ] Runtime: PHP
- [ ] Build Command: `composer install --no-dev --optimize-autoloader || echo "No composer needed"`
- [ ] Start Command: `php -S 0.0.0.0:$PORT -t .`
- [ ] Plan: Free

## Paso 4: Variables de Entorno
Obtener de Railway (servicio MySQL → Variables):
- [ ] DB_HOST (copiar MYSQLHOST o usar host público)
- [ ] DB_PORT (normalmente 3306)
- [ ] DB_NAME (normalmente "railway")
- [ ] DB_USER (normalmente "root")
- [ ] DB_PASSWORD (copiar MYSQLPASSWORD)

## Paso 5: Desplegar
- [ ] Click en "Create Web Service"
- [ ] Esperar 2-3 minutos
- [ ] Estado cambia a "Live" (verde)

## Paso 6: Probar Backend
Abrir en navegador (reemplaza con tu URL):
- [ ] `https://tu-backend.onrender.com/` → Ver JSON con info del API
- [ ] `https://tu-backend.onrender.com/test_db.php` → Ver "success": true
- [ ] `https://tu-backend.onrender.com/api/usuarios.php` → Ver lista de usuarios

## Paso 7: Actualizar Frontend
- [ ] Copiar URL del backend de Render
- [ ] Editar `frontend/.env.production`
- [ ] Cambiar `REACT_APP_API_URL` a la URL de Render
- [ ] En Railway, redesplegar el frontend (Settings → Redeploy)
- [ ] Esperar 2-3 minutos

## Paso 8: Verificación Final
- [ ] Abrir frontend en Railway
- [ ] Hacer login (admin / admin123)
- [ ] Navegar por el sistema
- [ ] ¡TODO FUNCIONA! 🎉

---

## Si algo falla:
1. Revisar logs en Render (pestaña "Logs")
2. Verificar variables de entorno
3. Probar `test_db.php` para verificar conexión a BD
4. Revisar consola del navegador (F12) para errores de frontend

---

## URLs Finales:
- Backend: `https://_____________________.onrender.com`
- Frontend: `https://_____________________.up.railway.app`
- Base de datos: MySQL en Railway (interno)
