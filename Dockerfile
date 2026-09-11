FROM dunglas/frankenphp:php8.4

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    opcache \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV SERVER_NAME=:8000
COPY Caddyfile /etc/caddy/Caddyfile

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

EXPOSE 8000
