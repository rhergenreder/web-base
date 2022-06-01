FROM composer:latest AS composer
FROM php:7.4-fpm
WORKDIR "/application"
RUN mkdir -p /application/core/Configuration
RUN chown -R www-data:www-data /application

# YAML + dev dependencies
RUN apt-get update -y && apt-get install libyaml-dev libzip-dev libgmp-dev -y && apt-get clean && \
    pecl install yaml && docker-php-ext-enable yaml

# Runkit (no stable release available)
RUN pecl install runkit7-4.0.0a3 && docker-php-ext-enable runkit7 && \
    echo "runkit.internal_override=1" >> /usr/local/etc/php/conf.d/docker-php-ext-runkit7.ini

# mysqli, zip, gmp
RUN docker-php-ext-install mysqli zip gmp

COPY --from=composer /usr/bin/composer /usr/bin/composer
USER www-data