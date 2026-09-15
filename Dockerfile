# syntax=docker/dockerfile:1

# Shared base: FrankenPHP + the extensions every stage needs.
FROM dunglas/frankenphp:php8.4 AS base

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    opcache \
    zip

WORKDIR /app

ENV SERVER_NAME=:8000
COPY Caddyfile /etc/caddy/Caddyfile

EXPOSE 8000


# Dev image: what the meta-repo's docker-compose.yml builds (`target: dev`).
# Source is bind-mounted over /app at runtime, so this mainly provides Composer,
# the dev php.ini and a warm vendor/ volume.
FROM base AS dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative


# Prod image: what the deploy workflow builds and pushes to GHCR.
# No dev dependencies, no Composer binary, APP_ENV=prod baked in, cache warmed.
# Runtime config (APP_SECRET, DATABASE_URL, ...) comes from real env vars on the VM.
FROM base AS prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY composer.json composer.lock symfony.lock ./
RUN --mount=from=composer:2,source=/usr/bin/composer,target=/usr/bin/composer \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN --mount=from=composer:2,source=/usr/bin/composer,target=/usr/bin/composer \
    composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && composer dump-env prod \
    && composer run-script --no-dev post-install-cmd

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -fsS http://localhost:8000/api/hello > /dev/null || exit 1
