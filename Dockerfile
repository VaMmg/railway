FROM php:8.2-apache

# Instalar extensiones necesarias
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar mod_rewrite y mod_headers
RUN a2enmod rewrite headers

# Configurar Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    echo "<Directory /var/www/html>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>" >> /etc/apache2/apache2.conf

# Limpiar directorio por defecto
RUN rm -rf /var/www/html/*

# Copiar archivos del backend
WORKDIR /var/www/html
COPY backend/ .

# Verificar que los archivos se copiaron (para debug)
RUN ls -la /var/www/html/ && \
    echo "=== Archivos copiados ===" && \
    ls -la /var/www/html/*.php || echo "No hay archivos PHP en raiz"

# Permisos
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
