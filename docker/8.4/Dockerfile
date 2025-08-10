FROM ubuntu:24.04

LABEL maintainer="Fossil01"

ARG WWWGROUP
ARG NODE_VERSION=22
ARG POSTGRES_VERSION=17
ARG SEVENZIP_VERSION=2409
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
        && apt-get install -y php8.4 php8.4-cli php8.4-common php8.4-mysql php8.4-xdebug php8.4-dev \
        php8.4-amqp php8.4-bcmath php8.4-bz2 php8.4-curl php8.4-gd php8.4-gmp php8.4-gnupg \
        php8.4-http php8.4-igbinary php8.4-imagick php8.4-intl php8.4-mbstring php8.4-msgpack \
        php8.4-oauth php8.4-opcache php8.4-raphf php8.4-readline php8.4-redis php8.4-soap php8.4-sqlite3 \
        php8.4-swoole php8.4-tidy php8.4-uploadprogress php8.4-uuid php8.4-xml php8.4-xmlrpc php8.4-xsl \
        php8.4-yaml php8.4-zip php8.4-zstd php8.4-pgsql php8.4-sockets php8.4-imap php8.4-ldap php8.4-memcached php8.4-pcov \
        tmux iputils-ping net-tools ffmpeg \
        sudo jq htop fonts-powerline nano bash-completion time wget cron \
        nginx php8.4-fpm \
        jpegoptim webp optipng pngquant libavif-bin \
    && wget https://mediaarea.net/repo/deb/repo-mediaarea_1.0-25_all.deb \
    && dpkg -i repo-mediaarea_1.0-25_all.deb \
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
COPY php.ini /etc/php/8.4/cli/conf.d/99-sail.ini

RUN chmod +x /usr/local/bin/start-container

EXPOSE 80

#ENTRYPOINT ["tail", "-f", "/dev/null"] # Use only for debugging
ENTRYPOINT ["start-container"]
