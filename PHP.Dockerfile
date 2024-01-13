FROM php:8.3-fpm

RUN docker-php-ext-install pdo pdo_mysql
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Create cache directory
RUN mkdir /ramdisk

# Install packages
RUN apt-get -y update
RUN apt-get -y install git mc
