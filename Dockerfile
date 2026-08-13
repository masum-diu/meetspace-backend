FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        libxml2-dev \
        libonig-dev \
    && docker-php-ext-install pdo pdo_sqlite mbstring bcmath xml \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN mkdir -p database \
    && touch database/database.sqlite \
    && chmod -R 775 storage bootstrap/cache database

EXPOSE 10000
ENTRYPOINT ["docker/entrypoint.sh"]
