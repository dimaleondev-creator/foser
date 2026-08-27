FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --classmap-authoritative

FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund
COPY resources resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.2-fpm-alpine
RUN apk add --no-cache libpq libzip icu-libs oniguruma su-exec \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql bcmath intl mbstring opcache \
    && pecl install redis \
    && docker-php-ext-enable redis opcache \
    && apk del .build-deps

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/foser-entrypoint
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
RUN chmod +x /usr/local/bin/foser-entrypoint \
    && mkdir -p /opt/foser-public \
    && cp -a public/. /opt/foser-public/ \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["foser-entrypoint"]
CMD ["php-fpm", "-F"]
