FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install necessary system dependencies for PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    libssl-dev \
    && docker-php-ext-install mysqli zip \
    && rm -rf /var/lib/apt/lists/*

# Install MongoDB and Redis extensions via PECL
RUN pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis

# Suppress Apache ServerName warning and enable AllowOverride
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory to Apache document root
WORKDIR /var/www/html/

# Copy project files
COPY . /var/www/html/

# Ensure script executability
RUN chmod +x /var/www/html/docker-entrypoint.sh

# Copy Composer from the official Composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies via Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Expose port (Internal documentation)
EXPOSE 80

# Use the entrypoint script for runtime setup
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
