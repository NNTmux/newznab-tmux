FROM ubuntu:24.04

LABEL maintainer="Fossil01"

ARG WWWGROUP
ARG NODE_VERSION=22
ARG POSTGRES_VERSION=17
ARG SEVENZIP_VERSION=2409
ARG MYSQL_CLIENT="mariadb-client"

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND noninteractive

# Install base packages and add repositories
RUN apt-get update \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip unrar 7zip lame git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14aa40ec0831756756d7f66c4f4ea0aae5267a6c' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/nginx/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_nginx.list \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | tee /etc/apt/keyrings/yarn.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/yarn.gpg] https://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list \
    && curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | tee /etc/apt/keyrings/pgdg.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt noble-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update

# Install core PHP packages
RUN apt-get install -y php8.5 php8.5-cli php8.5-common php8.5-dev php8.5-fpm \
    php8.5-bcmath php8.5-bz2 php8.5-curl php8.5-gd php8.5-gmp \
    php8.5-intl php8.5-mbstring php8.5-mysql \
    php8.5-readline php8.5-soap php8.5-sqlite3 php8.5-xml php8.5-xsl \
    php8.5-zip php8.5-pgsql

# Install optional PHP extensions (skip if not available)
RUN apt-get install -y --no-install-recommends \
    php8.5-sockets php8.5-imap php8.5-ldap \
    php8.5-amqp php8.5-gnupg php8.5-http php8.5-igbinary php8.5-imagick \
    php8.5-msgpack php8.5-oauth php8.5-raphf php8.5-redis php8.5-swoole \
    php8.5-tidy php8.5-uploadprogress php8.5-uuid php8.5-xmlrpc \
    php8.5-yaml php8.5-zstd php8.5-memcached php8.5-pcov php8.5-xdebug \
    || true

# Install other packages
RUN apt-get install -y \
    tmux iputils-ping net-tools ffmpeg \
    sudo jq htop fonts-powerline nano bash-completion time wget cron \
    nginx jpegoptim webp optipng pngquant libavif-bin

# Install mediainfo
RUN wget https://mediaarea.net/repo/deb/repo-mediaarea_1.0-25_all.deb \
    && dpkg -i repo-mediaarea_1.0-25_all.deb \
    && apt-get update \
    && apt-get install -y libmediainfo0v5 mediainfo libzen0v5 \
    && rm -f repo-mediaarea_1.0-25_all.deb

# Install Composer
RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Install Node.js
RUN curl -sLS https://deb.nodesource.com/setup_$NODE_VERSION.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm bun pnpm

# Install remaining packages and cleanup
RUN apt-get install -y yarn $MYSQL_CLIENT postgresql-client-$POSTGRES_VERSION \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Determine ARCH and download and extract the appropriate version of 7-Zip
RUN ARCH="$(dpkg --print-architecture)" && \
    if [ "$ARCH" = "amd64" ]; then \
        SZIP_URL="https://www.7-zip.org/a/7z$SEVENZIP_VERSION-linux-x64.tar.xz"; \
    fi && \
    if [ "$ARCH" = "arm64" ]; then \
        SZIP_URL="https://www.7-zip.org/a/7z$SEVENZIP_VERSION-linux-arm64.tar.xz"; \
    fi && \
    wget "$SZIP_URL" -O /tmp/7z.tar.xz && \
    tar -xf /tmp/7z.tar.xz -C /tmp/ && \
    mv /tmp/7zz /usr/bin/7zz && \
    rm -f /tmp/7z.tar.xz && rm -f /tmp/7zzs

RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail
RUN echo "alias artisan=\"php artisan\"" >> /home/sail/.bashrc

COPY scheduler /etc/cron.d/scheduler
RUN chmod 0644 /etc/cron.d/scheduler \
    && crontab /etc/cron.d/scheduler

COPY start-container /usr/local/bin/start-container
COPY nginx.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY php.ini /etc/php/8.5/cli/conf.d/99-sail.ini

RUN chmod +x /usr/local/bin/start-container

EXPOSE 80

#ENTRYPOINT ["tail", "-f", "/dev/null"] # Use only for debugging
ENTRYPOINT ["start-container"]
