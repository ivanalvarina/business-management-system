# ---------- Stage 1: Build frontend ----------
FROM php:8.3-cli AS assets

WORKDIR /app

RUN apt-get update && apt-get install -y git unzip curl libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
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


# ---------- Stage 2: Production ----------
FROM dunglas/frankenphp:1-php8.3

WORKDIR /app

RUN install-php-extensions pdo_mysql zip gd opcache pcntl

# Production PHP settings
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=192"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.validate_timestamps=0"; \
    echo "memory_limit=256M"; \
    echo "expose_php=0"; \
} > /usr/local/etc/php/conf.d/prod.ini

COPY --from=assets /app /app
RUN rm -rf node_modules \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV SERVER_NAME=":8080"
EXPOSE 8080

CMD ["sh", "-c", "php artisan storage:link --force && php artisan migrate --force && php artisan optimize && frankenphp php-server --root public --listen :${PORT:-8080}"]