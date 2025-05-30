FROM php:8.3-fpm

RUN docker-php-ext-install pdo pdo_mysql
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Create cache directory with proper ownership and permissions
RUN mkdir /ramdisk \
    && chown root:www-data /ramdisk \
    && chmod 0775 /ramdisk

# Install packages
RUN apt-get -y update
RUN apt-get -y install git mc

# Copy configuration files
COPY ./private/PHP-Custom.ini /usr/local/etc/php/conf.d/custom.ini