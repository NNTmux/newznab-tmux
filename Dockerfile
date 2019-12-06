FROM ubuntu:18.04
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get -q update && \
    apt-get -q upgrade && \
    apt-get install -qy screen time sudo unzip software-properties-common nano git make automake build-essential pkg-config libevent-dev libncurses5-dev fonts-powerline powerline mariadb-client libmysqlclient-dev unrar p7zip-full mediainfo lame ffmpeg && \
    add-apt-repository ppa:ondrej/php && \
    apt-get -q update && \
    apt-get install -qy apache2 libapache2-mod-php7.3 php-pear php7.3 php7.3-cli php7.3-dev php7.3-common php7.3-curl php7.3-json php7.3-gd php7.3-mysql php7.3-mbstring php7.3-xml php7.3-intl php7.3-fpm php7.3-bcmath php7.3-zip php-imagick

RUN git clone https://github.com/tmux/tmux.git && \
    cd tmux && \
    git fetch --all --tags --prune && \
    git checkout 2.9 && \
    sh autogen.sh && \
    ./configure && make && \
    make install && \
    cd .. && \
    rm -rf tmux

ENV HASH a5c698ffe4b8e849a443b120cd5ba38043260d5c4023dbf93e1558871f1f07f58274fc6f4c93bcfd858c6bd0775cd8d1
RUN cd ~ && curl -sS https://getcomposer.org/installer -o composer-setup.php && php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && composer global require hirak/prestissimo
COPY docker/php.ini /etc/php/7.3/apache2/conf.d/99-nn-tmux.ini
COPY docker/php.ini /etc/php/7.3/cli/conf.d/99-nn-tmux.ini
COPY docker/NNTmux.conf /etc/apache2/sites-available/NNTmux.conf
RUN ln -sf /dev/stdout /var/log/apache2/access.log
RUN ln -sf /dev/stdout /var/log/apache2/other_vhosts_access.log
RUN ln -sf /dev/stderr /var/log/apache2/error.log
RUN a2dissite 000-default && a2ensite NNTmux && a2enmod rewrite && service apache2 restart
RUN chown -R www-data:www-data /var/www
RUN useradd -ms /bin/bash notroot && usermod -aG www-data notroot && usermod -aG notroot www-data

USER www-data
RUN mkdir /var/www/NNTmux/ && chmod 775 /var/www/NNTmux/
WORKDIR /var/www/NNTmux
COPY composer.json composer.lock /var/www/NNTmux/
RUN composer install --prefer-dist --no-scripts --no-dev --no-autoloader
COPY . ./
RUN composer dump-autoload

USER root
COPY docker/install.sh /tmp/install.sh
EXPOSE 80
