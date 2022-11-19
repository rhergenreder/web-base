FROM composer:latest AS composer
FROM php:8.0-fpm
WORKDIR "/application"
RUN mkdir -p /application/core/Configuration /var/www/.gnupg && \
    chown -R www-data:www-data /application /var/www/ && \
    chmod 700 /var/www/.gnupg

# YAML + dev dependencies
RUN apt-get update -y &&  \
    apt-get install -y libyaml-dev libzip-dev libgmp-dev gnupg2 && \
    apt-get clean && \
    pecl install yaml && docker-php-ext-enable yaml

# Runkit (no stable release available)
RUN pecl install runkit7-4.0.0a3 && docker-php-ext-enable runkit7 && \
    echo "runkit.internal_override=1" >> /usr/local/etc/php/conf.d/docker-php-ext-runkit7.ini

# mysqli, zip, gmp
RUN docker-php-ext-install mysqli zip gmp

COPY --from=composer /usr/bin/composer /usr/bin/composer
USER www-data