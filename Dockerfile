FROM php:8.1-apache

RUN apt-get update && apt-get install -y libzip-dev unzip git zip \
    && docker-php-ext-install pdo pdo_mysql mysqli zip \
    && a2enmod rewrite

# Install composer from official composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html
