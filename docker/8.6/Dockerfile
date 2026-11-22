ARG NODE_VERSION=22
ARG BUN_VERSION=1

FROM node:${NODE_VERSION} AS node
FROM oven/bun:${BUN_VERSION} AS bun
FROM ubuntu:24.04

LABEL maintainer="Fossil01"

ARG WWWGROUP
ARG NODE_VERSION
ARG BUN_VERSION
ARG POSTGRES_VERSION=17
ARG MYSQL_CLIENT="mariadb-client"

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND noninteractive

# Install base packages and add repositories
RUN apt-get update \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip unrar lame git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils \
    && curl -sSLo /etc/apt/keyrings/sury_php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/sury_php.gpg] https://packages.sury.org/php/ noble main" > /etc/apt/sources.list.d/sury_php.list \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | tee /etc/apt/keyrings/yarn.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/yarn.gpg] https://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list \
    && curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | tee /etc/apt/keyrings/pgdg.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt noble-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update

# Install core PHP packages. The package index is refreshed here because the
# cached layer above may list builds the repository has already replaced.
RUN apt-get update \
    && apt-get install -y php8.6 php8.6-cli php8.6-common php8.6-dev php8.6-fpm \
    php8.6-bcmath php8.6-bz2 php8.6-curl php8.6-gd php8.6-gmp \
    php8.6-intl php8.6-mbstring php8.6-mysql \
    php8.6-readline php8.6-soap php8.6-sqlite3 php8.6-xml php8.6-xsl \
    php8.6-zip php8.6-pgsql

# Install optional PHP extensions one at a time, so an extension that is not
# published for this PHP version is skipped without dropping the others.
RUN apt-get update \
    && for extension in sockets imap ldap amqp gnupg raphf http igbinary imagick \
        msgpack oauth redis tidy uploadprogress uuid xmlrpc yaml zstd memcached \
        pcov xdebug; do \
        apt-get install -y --no-install-recommends "php8.6-${extension}" \
            || echo "php8.6-${extension} is not available, skipping"; \
    done

# Fail the build when an extension the application depends on is missing.
RUN php -r 'foreach (["bcmath", "ctype", "curl", "exif", "fileinfo", "filter", "gd", "hash", "iconv", "intl", "json", "libxml", "mbstring", "mysqlnd", "openssl", "pcntl", "pcre", "pdo", "pdo_mysql", "pdo_sqlite", "session", "simplexml", "sockets", "sodium", "spl", "xmlwriter", "zip", "zlib", "redis", "imagick"] as $extension) { if (! extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: {$extension}\n"); exit(1); } }'

# Install other packages
RUN apt-get install -y \
    tmux iputils-ping net-tools ffmpeg 7zip \
    sudo jq htop fonts-powerline nano bash-completion time wget cron \
    jpegoptim webp optipng pngquant libavif-bin

# Install nginx from Ubuntu repositories
RUN apt-get install -y nginx \
    && rm -f /etc/nginx/conf.d/default.conf \
    && nginx -t

# Install mediainfo
RUN wget https://mediaarea.net/repo/deb/repo-mediaarea_1.0-25_all.deb \
    && dpkg -i repo-mediaarea_1.0-25_all.deb \
    && apt-get update \
    && apt-get install -y libmediainfo0v5 mediainfo libzen0v5 \
    && rm -f repo-mediaarea_1.0-25_all.deb

# Install Composer
RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Install Node.js tooling from official images
COPY --from=node /usr/local/ /usr/local/
COPY --from=bun /usr/local/bin/bun /usr/local/bin/bun
RUN corepack enable pnpm \
    && corepack prepare pnpm@latest --activate \
    && node --version \
    && npm --version \
    && bun --version \
    && pnpm --version

# Install remaining packages and cleanup
RUN apt-get install -y yarn $MYSQL_CLIENT postgresql-client-$POSTGRES_VERSION \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*


RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail
RUN echo "alias artisan=\"php artisan\"" >> /home/sail/.bashrc

COPY scheduler /etc/cron.d/scheduler
RUN chmod 0644 /etc/cron.d/scheduler \
    && crontab /etc/cron.d/scheduler

COPY start-container /usr/local/bin/start-container
COPY nginx.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY php.ini /etc/php/8.6/cli/conf.d/99-sail.ini

RUN chmod +x /usr/local/bin/start-container \
    && nginx -t

EXPOSE 80

#ENTRYPOINT ["tail", "-f", "/dev/null"] # Use only for debugging
ENTRYPOINT ["start-container"]
