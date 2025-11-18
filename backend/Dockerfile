FROM php:8.2-apache

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar módulos de Apache
RUN a2enmod rewrite headers

# Configurar Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configurar el DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copiar archivos del backend
COPY . /var/www/html/

# Crear archivo index.php de prueba
RUN echo '<?php phpinfo(); ?>' > /var/www/html/test.php

# Permisos
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Listar archivos para debug
RUN ls -la /var/www/html/
RUN ls -la /var/www/html/api/ || echo "No hay carpeta api"

EXPOSE 80

CMD ["apache2-foreground"]
