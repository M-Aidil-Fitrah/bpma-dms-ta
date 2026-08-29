# syntax=docker/dockerfile:1

FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js tsconfig.json postcss.config.js tailwind.config.js ./
RUN npm run build


FROM php:8.3-fpm-bookworm AS app

ENV APP_ENV=production \
    COMPOSER_ALLOW_SUPERUSER=1 \
    HOME=/tmp

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install --yes --no-install-recommends \
        ca-certificates \
        ghostscript \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        libreoffice \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-ind \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        pcntl \
        zip \
    && { \
        echo 'expose_php=Off'; \
        echo 'upload_max_filesize=2048M'; \
        echo 'post_max_size=2098M'; \
        echo 'max_execution_time=300'; \
        echo 'max_input_time=300'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/bpma-dms-production.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --optimize-autoloader

COPY . ./
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --classmap-authoritative --no-dev --no-interaction --no-scripts \
    && php artisan package:discover --ansi \
    && php artisan storage:link --force \
    && chown -R www-data:www-data bootstrap/cache public storage vendor

USER www-data

EXPOSE 9000

CMD ["php-fpm"]


FROM nginx:1.27-alpine AS web

COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
