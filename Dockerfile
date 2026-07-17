# =========================================================================
# Reimbursement Management System — production image (Phase 22)
# Multi-stage: (1) build asset Vite, (2) vendor composer, (3) runtime FPM.
# =========================================================================

# ---- Stage 1: build front-end (Vite) ------------------------------------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js tailwind.config.js postcss.config.js jsconfig.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- Stage 2: dependensi PHP (tanpa dev) ---------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist \
    --optimize-autoloader --no-scripts

# ---- Stage 3: runtime PHP-FPM --------------------------------------------
FROM php:8.3-fpm-alpine

RUN apk add --no-cache postgresql-dev icu-dev libzip-dev libpng-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip gd bcmath opcache

COPY docker/php/app.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN php artisan storage:link || true \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
