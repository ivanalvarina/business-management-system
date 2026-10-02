# ---------- Base: PHP + extensions (isang beses lang i-compile) ----------
FROM dunglas/frankenphp:1-php8.3 AS base

WORKDIR /app

ENV IPE_PROCESSOR_COUNT=1
RUN install-php-extensions pdo_mysql zip gd


# ---------- Stage 1: Build vendor + frontend ----------
FROM base AS assets

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize --no-dev
RUN npm run build
RUN rm -rf node_modules


# ---------- Stage 2: Production ----------
FROM base

RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=192"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.validate_timestamps=0"; \
    echo "memory_limit=256M"; \
    echo "expose_php=0"; \
} > /usr/local/etc/php/conf.d/prod.ini

COPY --from=assets /app /app

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

CMD ["sh", "-c", "php artisan storage:link --force && php artisan optimize && frankenphp php-server --root public --listen :${PORT:-8080}"]