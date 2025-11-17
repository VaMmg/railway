# Sistema Credito - Docker

## Requisitos
- Docker Desktop 4+
- Puertos libres: 3002 (frontend), 8080 (backend), 3307 (MySQL), 8081 (phpMyAdmin)

## Primer arranque
```bash
# Desde la carpeta raiz del proyecto (sistemaCredito)
docker compose up -d --build
```

- Frontend: http://localhost:3002
- Backend (API): http://localhost:8080/backend/api/
- phpMyAdmin: http://localhost:8081 (host: db, user: app, pass: app)

## Variables importantes
- El frontend usa `REACT_APP_API_URL` en tiempo de build y está fijado a `http://localhost:8080/backend/api/` para que el navegador llegue al contenedor del backend.
- Si cambias el puerto del backend, actualiza `docker-compose.yml` y `frontend/.env.production`.

## Desarrollo
Puedes seguir desarrollando localmente. Para reconstruir el frontend tras cambios:
```bash
docker compose build frontend && docker compose up -d frontend
```

## Datos de MySQL
- Persisten en el volumen `db_data`.
- Cambia credenciales en `docker-compose.yml` si lo necesitas.

## Detener y limpiar
```bash
docker compose down
# Para borrar datos de MySQL
# docker volume rm sistemacredito_db_data
```
