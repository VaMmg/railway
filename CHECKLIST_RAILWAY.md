# ✅ Checklist para Railway

Marca cada paso cuando lo completes:

## Antes de Empezar

- [ ] Tengo cuenta en GitHub (https://github.com)
- [ ] Tengo Git instalado en mi computadora
- [ ] Mi proyecto funciona localmente con Docker

## Paso 1: Preparar Repositorio

- [ ] Ejecuté `verificar_railway.bat` y todo está OK
- [ ] Inicialicé Git: `git init`
- [ ] Agregué archivos: `git add .`
- [ ] Hice commit: `git commit -m "Listo para Railway"`
- [ ] Creé repositorio en GitHub
- [ ] Conecté mi repo: `git remote add origin URL_DE_GITHUB`
- [ ] Subí código: `git push -u origin main`

## Paso 2: Railway - Base de Datos

- [ ] Creé cuenta en Railway.app
- [ ] Creé nuevo proyecto
- [ ] Agregué MySQL: "+ New" → "Database" → "MySQL"
- [ ] Copié las variables de MySQL:
  - [ ] MYSQLHOST: ___________________________
  - [ ] MYSQLPORT: ___________________________
  - [ ] MYSQLDATABASE: _______________________
  - [ ] MYSQLUSER: ___________________________
  - [ ] MYSQLPASSWORD: _______________________

## Paso 3: Railway - Backend

- [ ] Agregué servicio: "+ New" → "GitHub Repo"
- [ ] Seleccioné mi repositorio
- [ ] Configuré Root Directory: `backend`
- [ ] Agregué variables de entorno:
  - [ ] DB_HOST
  - [ ] DB_PORT
  - [ ] DB_NAME
  - [ ] DB_USER
  - [ ] DB_PASSWORD
  - [ ] JWT_SECRET
  - [ ] APP_ENV=production
  - [ ] APP_DEBUG=false
  - [ ] CORS_ALLOWED_ORIGINS=*
- [ ] Desplegué el servicio
- [ ] Generé dominio público
- [ ] Copié URL del backend: _________________________________

## Paso 4: Railway - Frontend

- [ ] Agregué servicio: "+ New" → "GitHub Repo" (mismo repo)
- [ ] Configuré Root Directory: `frontend`
- [ ] Agregué variable REACT_APP_API_URL con la URL del backend + /backend/api/
- [ ] Desplegué el servicio
- [ ] Generé dominio público
- [ ] Copié URL del frontend: _________________________________

## Paso 5: Importar Base de Datos

Elige UNA opción:

### Opción A: Railway CLI
- [ ] Instalé Railway CLI: `npm install -g @railway/cli`
- [ ] Hice login: `railway login`
- [ ] Conecté proyecto: `railway link`
- [ ] Importé BD: `railway run mysql ... < backend/sistema_creditos2.sql`

### Opción B: MySQL Workbench
- [ ] Abrí MySQL Workbench
- [ ] Creé nueva conexión con datos de Railway
- [ ] Importé archivo `backend/sistema_creditos2.sql`

## Paso 6: Verificar

- [ ] Abrí URL del frontend en navegador
- [ ] La página carga correctamente
- [ ] Puedo iniciar sesión
- [ ] Puedo ver datos (clientes, créditos, etc.)
- [ ] No hay errores en la consola del navegador (F12)

## Paso 7: Monitoreo

- [ ] Revisé el uso en Railway Dashboard
- [ ] Configuré alertas si es necesario
- [ ] Guardé las URLs en un lugar seguro

## 🎉 ¡Listo!

Tu sistema está en la nube. URLs importantes:

- **Frontend**: _________________________________
- **Backend**: _________________________________
- **Railway Dashboard**: https://railway.app/dashboard

## Próximos Pasos (Opcional)

- [ ] Configurar dominio personalizado
- [ ] Configurar backups automáticos
- [ ] Agregar monitoreo de errores
- [ ] Configurar SSL/HTTPS (Railway lo hace automático)
- [ ] Documentar credenciales de acceso

## Notas

Escribe aquí cualquier problema o nota importante:

_________________________________________________________________

_________________________________________________________________

_________________________________________________________________
