# ============================================================
# CSMS — Dockerfile for Coolify / Docker deployment
# PHP 8.2 + Apache
# ============================================================

FROM php:8.2-apache

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_mysql mysqli gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers deflate expires

# Set working directory
WORKDIR /var/www/html

# Copy all files
COPY . .

# Apache config — allow .htaccess
RUN echo '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/csms.conf \
    && a2enconf csms

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Create writable log directory
RUN mkdir -p /var/www/html/logs \
    && chown www-data:www-data /var/www/html/logs \
    && chmod 775 /var/www/html/logs

# PHP config for production
RUN echo "upload_max_filesize = 32M\n\
post_max_size = 32M\n\
max_execution_time = 120\n\
memory_limit = 256M\n\
default_charset = UTF-8\n\
date.timezone = Asia/Bangkok" > /usr/local/etc/php/conf.d/csms.ini

EXPOSE 80

CMD ["apache2-foreground"]
