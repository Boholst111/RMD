# Railway-compatible PHP runtime image for Laravel 12
FROM php:8.2-fpm-alpine

RUN apk add --no-cache git curl icu-dev libzip-dev libxml2-dev oniguruma-dev zlib-dev shadow bash postgresql-dev postgresql-client $PHPIZE_DEPS \
    && docker-php-ext-configure zip \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring exif pcntl bcmath xml zip intl \
    && php -m | grep -i pdo_pgsql \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
RUN cp .env.example .env && php artisan key:generate --force && php artisan storage:link --force && rm -f .env
RUN chmod +x ./scripts/start.sh

EXPOSE 8080
CMD ["sh", "./scripts/start.sh"]
