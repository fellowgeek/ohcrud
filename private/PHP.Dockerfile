FROM php:8.4-fpm

# Install system dependencies for GD and other extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    git \
    mc \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd pdo pdo_mysql \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

# Create cache directory with proper ownership and permissions
RUN mkdir /ramdisk \
    && mkdir /data \
    && chown root:www-data /ramdisk \
    && chmod 0775 /ramdisk \
    && chown root:www-data /data \
    && chmod 0775 /data

# Copy configuration files
COPY ./private/PHP-Custom.ini /usr/local/etc/php/conf.d/custom.ini