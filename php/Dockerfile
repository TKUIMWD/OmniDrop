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

# Create startup script to ensure vendor directory exists
RUN mkdir -p /usr/local/bin && \
    echo '#!/bin/bash' > /usr/local/bin/docker-entrypoint.sh && \
    echo 'set -e' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '# Install composer dependencies if vendor does not exist' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'if [ ! -d "/var/www/html/vendor" ]; then' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '  echo "Installing Composer dependencies..."' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '  cd /var/www/html && composer install --no-dev --optimize-autoloader' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'fi' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '# Ensure proper permissions' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'chown -R www-data:www-data /var/www/html/src' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'chmod -R 755 /var/www/html/src' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'chmod 644 /var/www/html/src/*.php 2>/dev/null || true' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "Container started successfully. Listening on port 80"' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'exec apache2-foreground' >> /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

# Ensure storage is writable
RUN chown -R www-data:www-data /var/www/html

# Enable required Apache modules
RUN a2enmod rewrite

# Allow .htaccess to override php_value
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Set DirectoryIndex
RUN sed -i 's/DirectoryIndex index.html/DirectoryIndex index.php index.html/' /etc/apache2/mods-enabled/dir.conf

# Create VirtualHost configuration
RUN mkdir -p /etc/apache2/sites-available && \
    echo '<VirtualHost *:80>' > /etc/apache2/sites-available/omnidrop.conf && \
    echo '    ServerName localhost' >> /etc/apache2/sites-available/omnidrop.conf && \
    echo '    DocumentRoot /var/www/html/src' >> /etc/apache2/sites-available/omnidrop.conf && \
    echo '    ' >> /etc/apache2/sites-available/omnidrop.conf && \
    echo '    <Directory /var/www/html/src>' >> /etc/apache2/sites-available/omnidrop.conf && \
    echo '        Options Indexes FollowSymLinks' >> /etc/apache2/sites-available/omnidrop.conf && \
    echo '        AllowOverride All' >> /etc/apache2/sites-available/omnidrop.conf && \
    echo '        Require all granted' >> /etc/apache2/sites-available/omnidrop.conf && \
    echo '    </Directory>' >> /etc/apache2/sites-available/omnidrop.conf && \
    echo '</VirtualHost>' >> /etc/apache2/sites-available/omnidrop.conf

# Enable VirtualHost and disable default site
RUN a2dissite 000-default && a2ensite omnidrop

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
