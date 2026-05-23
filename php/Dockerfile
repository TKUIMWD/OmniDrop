FROM php:7.4-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    curl \
    git \
    zip \
    unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql curl \
    && docker-php-ext-enable mysqli

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Install project dependencies via Composer
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader 2>&1 | grep -v "^Deprecation Warning" || true

# Ensure storage is writable
RUN chown -R www-data:www-data /var/www/html
RUN a2enmod rewrite

# Allow .htaccess to override php_value
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
