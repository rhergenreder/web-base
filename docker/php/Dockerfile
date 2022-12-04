FROM composer:latest AS composer
FROM php:8.0-fpm
WORKDIR "/application"
RUN mkdir -p /application/core/Configuration /var/www/.gnupg && \
    chown -R www-data:www-data /application /var/www/ && \
    chmod 700 /var/www/.gnupg

# YAML + dev dependencies
RUN apt-get update -y &&  \
    apt-get install -y libyaml-dev libzip-dev libgmp-dev libpng-dev gnupg2d nodejs npm && \
    apt-get clean && \
    pecl install yaml && docker-php-ext-enable yaml && \
    docker-php-ext-install gd && \
    npm install --global yarn && ln -s /usr/local/bin/yarn /usr/bin/yarn

# Runkit for unit testing (no stable release available)
RUN pecl install runkit7-4.0.0a6  && docker-php-ext-enable runkit7 && \
    echo "runkit.internal_override=1" >> /usr/local/etc/php/conf.d/docker-php-ext-runkit7.ini

# mysqli, zip, gmp
RUN docker-php-ext-install mysqli zip gmp

COPY --from=composer /usr/bin/composer /usr/bin/composer
USER www-data