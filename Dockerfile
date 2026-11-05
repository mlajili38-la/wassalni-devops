FROM php:8.3-apache

ARG APP_VERSION=dev

LABEL org.opencontainers.image.revision="${APP_VERSION}"

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libicu-dev libpq-dev unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

COPY . .

RUN composer dump-autoload --classmap-authoritative --no-dev --no-scripts \
    && APP_SECRET=build-only-placeholder DATABASE_URL=postgresql://app:app@database:5432/app php bin/console importmap:install --env=prod \
    && APP_SECRET=build-only-placeholder DATABASE_URL=postgresql://app:app@database:5432/app php bin/console asset-map:compile --env=prod \
    && printf '%s\n' \
        '<VirtualHost *:80>' \
        '    DocumentRoot /var/www/html/public' \
        '    <Directory /var/www/html/public>' \
        '        AllowOverride None' \
        '        Require all granted' \
        '        FallbackResource /index.php' \
        '    </Directory>' \
        '    ErrorLog /proc/self/fd/2' \
        '    CustomLog /proc/self/fd/1 combined' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/000-default.conf \
    && mkdir -p var \
    && chown -R www-data:www-data var

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://localhost/health || exit 1

CMD ["apache2-foreground"]
