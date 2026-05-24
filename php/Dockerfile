FROM php:8.2-apache

# Install system dependencies
RUN apt-get update -y && \
    apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    zip \
    unzip && \
    docker-php-ext-install mysqli pdo pdo_mysql && \
    docker-php-ext-enable mysqli && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

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

# Set DirectoryIndex
RUN sed -i 's/DirectoryIndex index.html/DirectoryIndex index.php index.html/' /etc/apache2/mods-enabled/dir.conf
