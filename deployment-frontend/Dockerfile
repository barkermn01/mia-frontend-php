# Mia Frontend - PHP Docker Image
FROM php:8.5-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    libmemcached-dev \
    zlib1g-dev \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (only what we actually need)
RUN docker-php-ext-install mbstring zip

# Install memcached extension
RUN pecl install memcached && docker-php-ext-enable memcached

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers expires deflate

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure Apache virtual host
COPY deployment-frontend/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Configure PHP for file uploads
RUN echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_file_uploads = 20" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Create health check endpoint
RUN echo '<?php http_response_code(200); echo "OK"; ?>' > /var/www/html/health.php

# Expose port 3000 (we'll configure Apache to run on this port)
EXPOSE 3000

# Configure Apache to run on port 3000
RUN sed -i 's/Listen 80/Listen 3000/' /etc/apache2/ports.conf
RUN sed -i 's/:80/:3000/' /etc/apache2/sites-available/000-default.conf

# Start Apache
CMD ["apache2-foreground"]