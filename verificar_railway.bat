@echo off
echo ========================================
echo Verificacion para Despliegue en Railway
echo ========================================
echo.

echo [1/5] Verificando Git...
git --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [X] Git no esta instalado
    echo     Descarga desde: https://git-scm.com/download/win
) else (
    echo [OK] Git instalado
)
echo.

echo [2/5] Verificando Node.js...
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [X] Node.js no esta instalado
    echo     Descarga desde: https://nodejs.org
) else (
    echo [OK] Node.js instalado
    node --version
)
echo.

echo [3/5] Verificando archivos necesarios...
if exist "backend\Dockerfile" (
    echo [OK] backend\Dockerfile existe
) else (
    echo [X] backend\Dockerfile NO existe
)

if exist "frontend\Dockerfile" (
    echo [OK] frontend\Dockerfile existe
) else (
    echo [X] frontend\Dockerfile NO existe
)

if exist "backend\sistema_creditos2.sql" (
    echo [OK] backend\sistema_creditos2.sql existe
) else (
    echo [X] backend\sistema_creditos2.sql NO existe
)
echo.

echo [4/5] Verificando repositorio Git...
git status >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] No hay repositorio Git inicializado
    echo     Ejecuta: git init
) else (
    echo [OK] Repositorio Git inicializado
    git remote -v | findstr "origin" >nul 2>&1
    if %errorlevel% neq 0 (
        echo [!] No hay remote configurado
        echo     Crea un repo en GitHub y ejecuta:
        echo     git remote add origin https://github.com/TU_USUARIO/TU_REPO.git
    ) else (
        echo [OK] Remote configurado
        git remote -v
    )
)
echo.

echo [5/5] Verificando Railway CLI...
railway --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] Railway CLI no esta instalado (opcional)
    echo     Para instalar: npm install -g @railway/cli
) else (
    echo [OK] Railway CLI instalado
    railway --version
)
echo.

echo ========================================
echo Verificacion completada
echo ========================================
echo.
echo Siguiente paso: Lee PASOS_RAPIDOS.md
echo.
pause
