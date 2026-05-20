FROM php:7.4-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli

# Ensure storage is writable
RUN chown -R www-data:www-data /var/www/html
RUN a2enmod rewrite

# Allow .htaccess to override php_value
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
