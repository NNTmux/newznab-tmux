# syntax=docker/dockerfile:1
FROM composer:2.8 AS composer
FROM node:22-bookworm AS node
FROM dunglas/frankenphp:1-php8.5-bookworm AS build
WORKDIR /app
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/ /usr/local/
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates git unzip curl tmux unrar-free lame ffmpeg mediainfo \
    jpegoptim webp optipng pngquant libavif-bin python3 time procps \
    mariadb-client libmagic1 \
    && rm -rf /var/lib/apt/lists/*
RUN install-php-extensions bcmath exif gd intl mbstring pdo_mysql pdo_sqlite sockets pcntl redis imagick zip
COPY composer.json composer.lock /app/
RUN --mount=type=cache,target=/root/.composer/cache composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY package*.json /app/
RUN --mount=type=cache,target=/root/.npm npm install --no-audit --no-fund
COPY . /app
RUN composer dump-autoload --no-dev --no-scripts --optimize \
    && mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && touch /tmp/build.sqlite \
    && APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=/tmp/build.sqlite CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array php artisan package:discover --ansi \
    && APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=/tmp/build.sqlite CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array npm run build \
    && rm -rf node_modules /tmp/build.sqlite bootstrap/cache/*.php

FROM dunglas/frankenphp:1-php8.5-bookworm AS production
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl unzip tmux unrar-free lame ffmpeg mediainfo jpegoptim webp \
    optipng pngquant libavif-bin python3 time procps mariadb-client libmagic1 \
    && rm -rf /var/lib/apt/lists/*
RUN install-php-extensions bcmath exif gd intl mbstring pdo_mysql pdo_sqlite sockets pcntl redis imagick zip
COPY --from=build --chown=www-data:www-data /app /app
COPY docker/8.5/php.ini /usr/local/etc/php/conf.d/99-nntmux.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && mkdir -p /app/_install /app/storage/covers /app/storage/app/public \
    && ln -s ../storage/covers /app/public/covers \
    && ln -s ../storage/app/public /app/public/storage \
    && chmod +x /app/deploy/cloud/*.sh \
    && chown -R www-data:www-data /app/_install /app/storage /app/bootstrap/cache
USER www-data
EXPOSE 80
ENTRYPOINT ["/app/deploy/cloud/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/app/deploy/cloud/Frankenphp.Caddyfile", "--adapter", "caddyfile"]
