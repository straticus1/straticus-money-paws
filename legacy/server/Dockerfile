# Money Paws Production Dockerfile
# Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>

FROM php:8.0-apache

# Set labels for better container management
LABEL maintainer="Ryan Coleman <coleman.ryan@gmail.com>"
LABEL description="Money Paws - Cryptocurrency-powered pet platform"
LABEL version="3.1.4"

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    default-mysql-client \
    cron \
    supervisor \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite ssl headers

# Create application directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/uploads/pets \
    && mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/database \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/logs

# Configure Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/php.ini

# Add health check endpoint
COPY docker/health-check.php /var/www/html/health-check.php

# Create supervisor configuration for background tasks
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Add cron job for scheduled tasks
COPY docker/crontab /etc/cron.d/money-paws-cron
RUN chmod 0644 /etc/cron.d/money-paws-cron \
    && crontab /etc/cron.d/money-paws-cron

# Create startup script
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/health-check.php || exit 1

# Use supervisor to manage multiple processes
CMD ["/usr/local/bin/start.sh"]
