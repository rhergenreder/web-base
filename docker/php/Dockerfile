FROM composer:latest AS composer
FROM php:7.4-fpm
WORKDIR "/application"
RUN mkdir -p /application/core/Configuration
RUN chown -R www-data:www-data /application
RUN apt-get update -y && apt-get install libyaml-dev libzip-dev -y && apt-get clean && \
    pecl install yaml && echo "extension=yaml.so" > /usr/local/etc/php/conf.d/ext-yaml.ini && \
    docker-php-ext-enable yaml
RUN docker-php-ext-install mysqli zip
COPY --from=composer /usr/bin/composer /usr/bin/composer
USER www-data