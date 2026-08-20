# syntax=docker/dockerfile:1.7

FROM php:8.4-fpm-bookworm AS php-base

ARG UID=1000
ARG GID=1000

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    XDG_CONFIG_HOME=/tmp/config

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        chromium \
        curl \
        git \
        pdftk-java \
        unzip \
        libcurl4-openssl-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libzip-dev \
        poppler-utils \
        postgresql-client \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/roo.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-roo.conf
COPY docker/php/healthcheck.sh /usr/local/bin/php-fpm-healthcheck

RUN chmod +x /usr/local/bin/php-fpm-healthcheck

RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g www-data www-data \
    && mkdir -p /var/www/html /tmp/composer /tmp/composer-cache /tmp/config \
    && chown -R www-data:www-data /var/www/html /tmp/composer /tmp/composer-cache /tmp/config

WORKDIR /var/www/html

FROM php-base AS development

USER www-data

FROM node:22-bookworm-slim AS frontend-builder

WORKDIR /app

COPY src/package*.json ./
RUN npm ci

COPY src/ ./
RUN npm run build

FROM php-base AS production

COPY --chown=www-data:www-data src/composer.json src/composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

COPY --chown=www-data:www-data src/ ./
COPY --from=frontend-builder --chown=www-data:www-data /app/public/build ./public/build

RUN php artisan package:discover --ansi

RUN mkdir -p storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

FROM node:22-bookworm-slim AS node

WORKDIR /app
