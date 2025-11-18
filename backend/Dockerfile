FROM php:8.2-cli

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install pdo pdo_mysql

# Crear directorio de trabajo
WORKDIR /app

# Copiar archivos del backend
COPY . /app/

# Exponer puerto (Render usa la variable $PORT)
EXPOSE 8080

# Comando para iniciar el servidor PHP
CMD php -S 0.0.0.0:${PORT:-8080} -t .
