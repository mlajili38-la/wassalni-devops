# Image de l'application WASSALNI : construction en 3 etapes (multi-stage build).
#
#   base    : PHP 8.3 + Apache + extensions (intl, pdo_mysql), sans outils de compilation ;
#   build   : base + Composer + unzip -> dependances PHP, autoloader optimise, assets compiles ;
#   runtime : base + le resultat du build UNIQUEMENT. Composer, unzip et les fichiers de travail
#             ne sont pas dans l'image publiee : image plus petite, surface d'attaque reduite.

# ---------------------------------------------------------------------------------------------
# Etape 1 : base commune (identique pour le build et l'execution)
# ---------------------------------------------------------------------------------------------
FROM php:8.3-apache AS base

# Securite (Lot 5, voir docs/SECURITE.md) :
# - apt-get upgrade : applique les correctifs Debian publies (ex. Apache CVE CRITICAL, zlib HIGH) ;
# - les outils de compilation ($PHPIZE_DEPS, paquets -dev) ne servent qu'a compiler les extensions :
#   ils sont supprimes ensuite, seules les bibliotheques utilisees a l'execution sont conservees.
RUN set -eux; \
    apt-get update; \
    apt-get upgrade -y; \
    apt-get install -y --no-install-recommends curl libicu-dev; \
    docker-php-ext-install -j"$(nproc)" intl pdo_mysql; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual apache2 curl ca-certificates > /dev/null; \
    find /usr/local -type f -executable -exec ldd '{}' ';' 2>/dev/null \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search | grep -v '^diversion by' | cut -d: -f1 | sort -u | xargs -r apt-mark manual > /dev/null; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    a2enmod rewrite; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

ENV APP_ENV=prod \
    APP_DEBUG=0

# ---------------------------------------------------------------------------------------------
# Etape 2 : build (outils de construction, jamais publies)
# ---------------------------------------------------------------------------------------------
FROM base AS build

# unzip : extraction des paquets Composer ; Composer : installation des dependances.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip; \
    rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Dependances d'abord (couche mise en cache tant que composer.lock ne change pas).
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
    && APP_SECRET=build-only-placeholder DATABASE_URL="mysql://build:build@database:3306/build?serverVersion=8.0.32" php bin/console importmap:install --env=prod \
    && APP_SECRET=build-only-placeholder DATABASE_URL="mysql://build:build@database:3306/build?serverVersion=8.0.32" php bin/console asset-map:compile --env=prod

# ---------------------------------------------------------------------------------------------
# Etape 3 : runtime (image publiee dans GHCR)
# ---------------------------------------------------------------------------------------------
FROM base AS runtime

# Code applicatif + vendor/ + assets compiles, recuperes du build (proprietaire root : lecture seule pour Apache).
COPY --from=build /var/www/html /var/www/html

RUN printf '%s\n' \
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

# Entrypoint : attente MySQL + migrations Doctrine au demarrage (secrets fournis au runtime).
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint \
    && chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://localhost/health || exit 1

# Version figee dans l'image : /health l'expose, quel que soit le tag utilise (SHA, latest) au deploiement.
# Declaree en dernier : un nouveau SHA ne reconstruit que ces couches, pas les precedentes (cache).
ARG APP_VERSION=dev
LABEL org.opencontainers.image.revision="${APP_VERSION}"
ENV APP_VERSION="${APP_VERSION}"

ENTRYPOINT ["docker-entrypoint"]
CMD ["apache2-foreground"]
