FROM ubuntu:24.04

LABEL maintainer="Fossil01"

ARG WWWGROUP
ARG NODE_VERSION=21
ARG POSTGRES_VERSION=16
ARG SEVENZIP_VERSION=2407
ARG MYSQL_CLIENT="mariadb-client"

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip unrar 7zip lame git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14aa40ec0831756756d7f66c4f4ea0aae5267a6c' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/nginx/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_nginx.list \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | tee /etc/apt/keyrings/yarn.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/yarn.gpg] https://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list \
    && curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | tee /etc/apt/keyrings/pgdg.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt noble-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
        && apt-get install -y php8.3 php8.3-cli php8.3-common php8.3-mysql php8.3-xdebug php8.3-dev \
        php8.3-amqp php8.3-bcmath php8.3-bz2 php8.3-curl php8.3-gd php8.3-gmp php8.3-gnupg \
        php8.3-http php8.3-igbinary php8.3-imagick php8.3-intl php8.3-mbstring php8.3-msgpack \
        php8.3-oauth php8.3-opcache php8.3-raphf php8.3-readline php8.3-redis php8.3-soap php8.3-sqlite3 \
        php8.3-swoole php8.3-tidy php8.3-uploadprogress php8.3-uuid php8.3-xml php8.3-xmlrpc php8.3-xsl \
        php8.3-yaml php8.3-zip php8.3-zstd php8.3-pgsql php8.3-sockets php8.3-imap php8.3-ldap php8.3-memcached php8.3-pcov \
        tmux iputils-ping net-tools ffmpeg \
        sudo jq htop fonts-powerline nano bash-completion time wget cron \
        nginx php8.3-fpm libnginx-mod-brotli \
        jpegoptim webp optipng pngquant libavif-bin \
    && wget https://mediaarea.net/repo/deb/repo-mediaarea_1.0-24_all.deb \
    && dpkg -i repo-mediaarea_1.0-24_all.deb \
    && apt-get update \
    && apt-get install -y libmediainfo0v5 mediainfo libzen0v5 \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -sLS https://deb.nodesource.com/setup_$NODE_VERSION.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm bun pnpm \
    && apt-get install -y yarn $MYSQL_CLIENT postgresql-client-$POSTGRES_VERSION \ 
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
COPY php.ini /etc/php/8.3/cli/conf.d/99-sail.ini

RUN chmod +x /usr/local/bin/start-container

EXPOSE 80

#ENTRYPOINT ["tail", "-f", "/dev/null"] # Use only for debugging
ENTRYPOINT ["start-container"]
