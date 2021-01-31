FROM archlinux

ENV SHELL=/bin/zsh
ENV TERM=xterm-256color

RUN echo "[multilib]" >> /etc/pacman.conf && \
    echo "Include = /etc/pacman.d/mirrorlist" >> /etc/pacman.conf && \
    sed -i "s/#MAKEFLAGS=\"-j2\"/MAKEFLAGS=\"-j$(nproc)\"/" /etc/makepkg.conf

RUN pacman --noconfirm -Syu \
    git \
    vim \
    tmux \
    zsh

RUN chsh -s /bin/zsh root && \
    curl -Lo- http://bit.ly/2pztvLf | bash

RUN pacman --noconfirm -Syu \
    bc \
    binutils \
    bwm-ng \
    composer \
    fakeroot \
    ffmpeg \
    gcc \
    htop \
    jq \
    lame \
    make \
    mariadb-clients \
    mariadb \
    mediainfo \
    mytop \
    nginx \
    nmon \
    p7zip \
    php7-fpm \
    php7-gd \
    php7-intl \
    powerline-fonts \
    sudo \
    supervisor \
    the_silver_searcher \
    time \
    unrar \
    unzip \
    vnstat \
    wget \
    which

# Temporary, until nntmux is ready for php8
RUN mv /usr/sbin/php /usr/sbin/php8 && \
    mv /usr/sbin/php7 /usr/sbin/php

RUN wget http://pear.php.net/go-pear.phar -O /tmp/go-pear.phar && \
    php /tmp/go-pear.phar && \
    rm /tmp/go-pear.phar

RUN mkdir -p /var/log/php7 && \
    ln -sf /dev/stderr /var/log/php7/php-fpm.error.log && \
    ln -sf /dev/stdout /var/log/php7/php-fpm.access.log

RUN sed -i  \
        -e "s/user = http/user = nntmux/" \
        -e "s/group = http/group = nntmux/" \
        -e "s#;access.log = log/\$pool.access.log#access.log = /var/log/php7/php-fpm.access.log#" \
        /etc/php7/php-fpm.d/www.conf && \
    sed -i "s#error_log = syslog#error_log = /var/log/php7/php-fpm.error.log#" /etc/php7/php-fpm.conf

RUN groupadd --gid 1000 nntmux && \
    useradd --create-home --system --shell /usr/sbin/zsh --uid 1000 --gid 1000 nntmux && \
    passwd -d nntmux && \
    chsh -s /bin/zsh nntmux && \
    echo 'nntmux ALL=(ALL) ALL' > /etc/sudoers.d/nntmux && \
    mkdir -p /home/nntmux/.gnupg && \
    echo 'standard-resolver' > /home/nntmux/.gnupg/dirmngr.conf && \
    chown -R nntmux:nntmux /home/nntmux

USER nntmux

RUN git clone --depth 1 https://aur.archlinux.org/yay.git /tmp/yay && \
    cd /tmp/yay && \
    makepkg --noconfirm -si && \
    yay --afterclean --removemake --save && \
    rm -rf /tmp/yay

RUN yay --noconfirm -Sy \
    php7-imagick \
    php7-memcached \
    php7-sodium \
    tcptrack

RUN curl -Lo- http://bit.ly/2pztvLf | bash

# We disable it here because we enable it in the overrides file which affects
# both php7-fpm as well as cli
RUN sudo sed -i 's/^extension=curl/;extension=curl/' /etc/php7/php.ini

WORKDIR /site

CMD ["sudo", "/usr/sbin/supervisord", "--nodaemon"]
