
FROM composer:latest AS composer-base
FROM dunglas/frankenphp:1-php8.3
LABEL maintainer="PyRowMan"
ENV SERVER_NAME=:${APP_PORT:-80}
ARG MYSQL_CLIENT="mariadb-client"

WORKDIR /app


COPY --from=node:21 /usr/local/ /usr/local/
COPY --from=composer-base --link /usr/bin/composer /usr/bin/composer

RUN apt update \
 && apt install -y --no-install-recommends \
     unrar-free lame libcap2-bin python3 gettext-base \
     curl zip unzip git nano bash-completion sudo wget tmux time fonts-powerline \
     gnupg libpng-dev dnsutils jq htop iputils-ping net-tools ffmpeg \
     jpegoptim webp optipng pngquant libavif-bin watch iproute2 nmon \
     libonig-dev libxml2-dev libicu-dev libjpeg-dev libfreetype6-dev libxslt-dev $MYSQL_CLIENT libcurl4-openssl-dev \
 && wget https://mediaarea.net/repo/deb/repo-mediaarea_1.0-24_all.deb \
 && dpkg -i repo-mediaarea_1.0-24_all.deb \
 && apt update \
 && apt install -y libmediainfo0v5 mediainfo libzen0v5
RUN install-php-extensions imagick/imagick@master
RUN docker-php-ext-install \
     bcmath \
     exif \
     gd \
     intl \
     pdo_mysql \
     sockets \
     pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apt clean \
 && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY ./docker/8.3/php.ini "$PHP_INI_DIR/conf.d/custom-conf.ini"

COPY --chmod=755 ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint

COPY . /app

RUN rm -Rf tests/

RUN composer install

RUN chmod -R 755 /app/vendor/
RUN chmod -R 777 /app/storage/
RUN chmod -R 777 /app/resources/
RUN chmod -R 777 /app/public/

EXPOSE ${APP_PORT:-80}

CMD ["--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
ENTRYPOINT ["docker-entrypoint"]


