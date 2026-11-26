# syntax=docker/dockerfile:1

FROM php:8.4-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev unzip \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_pgsql opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

FROM php-base AS source

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY artisan ./artisan

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction --no-scripts \
    && php artisan package:discover --no-interaction

FROM node:24-alpine AS assets

WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY vite.config.js ./
COPY --from=source /var/www/html/resources ./resources
COPY --from=source /var/www/html/vendor ./vendor
COPY --from=source /var/www/html/public ./public
RUN npm run build

FROM php-base AS app

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    DB_CONNECTION=pgsql

COPY --from=source /var/www/html ./
COPY --from=assets /var/www/html/public ./public
COPY php-fpm.conf /usr/local/etc/php-fpm.d/zz-tennis.conf
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm", "-F"]

FROM nginx:stable-alpine AS nginx

COPY nginx.conf /etc/nginx/nginx.conf
COPY --from=assets /var/www/html/public /var/www/html/public

EXPOSE 80
ENTRYPOINT ["nginx"]
CMD ["-g", "daemon off;"]
