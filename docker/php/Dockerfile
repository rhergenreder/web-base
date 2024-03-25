FROM composer:latest AS composer
FROM php:8.1-fpm
WORKDIR "/application"

ARG ADDITIONAL_PACKAGES
ARG ADDITIONAL_SCRIPT

RUN mkdir -p /application/Core/Configuration /var/www/.gnupg && \
    chown -R www-data:www-data /application /var/www/ && \
    chmod 700 /var/www/.gnupg

# YAML + dev dependencies + additional packages
RUN apt-get update -y &&  \
    apt-get install -y libyaml-dev libzip-dev libgmp-dev libpng-dev libmagickwand-dev gnupg2 $ADDITIONAL_PACKAGES && \
    printf "\n" | pecl install yaml imagick && docker-php-ext-enable yaml imagick && \
    docker-php-ext-install gd

# Browscap
RUN mkdir -p /usr/local/etc/php/extra/ && \
    curl -s "https://browscap.org/stream?q=Full_PHP_BrowsCapINI" -o /usr/local/etc/php/extra/browscap.ini

# NodeJS
RUN curl -sL https://deb.nodesource.com/setup_14.x | bash - && \
    apt-get update && \
    apt-get -y install nodejs && \
    curl -sL https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | tee /usr/share/keyrings/yarnkey.gpg >/dev/null && \
    echo "deb [signed-by=/usr/share/keyrings/yarnkey.gpg] https://dl.yarnpkg.com/debian stable main" | tee /etc/apt/sources.list.d/yarn.list && \
    apt-get update && apt-get -y install yarn

# Runkit for unit testing (no stable release available)
RUN pecl install runkit7-4.0.0a6  && docker-php-ext-enable runkit7 && \
    echo "runkit.internal_override=1" >> /usr/local/etc/php/conf.d/docker-php-ext-runkit7.ini

# mysqli, zip, gmp
RUN docker-php-ext-install mysqli zip gmp

# clean cache
RUN apt-get clean

# run additional scripts
RUN if [[ ! -z "$ADDITIONAL_SCRIPT" ]]; then $ADDITIONAL_SCRIPT; fi

COPY --from=composer /usr/bin/composer /usr/bin/composer
USER www-data