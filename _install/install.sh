#!/bin/bash

echo "NNTmux Installer"
echo "----------------------"
echo ""

echo "Getting the NNTmux app from GitHub"
echo ""
git clone https://github.com/NNTmux/newznab-tmux.git nntmux
cd nntmux
echo ""

if type -p composer >/dev/null 2>&1; then
	composer install
	else if [ -f "composer.phar" ]; then
		php composer.phar install
	else
		echo ""
		echo "Getting Composer for you..."
		curl -sS https://getcomposer.org/installer | php
		php composer.phar install
	fi
fi

echo ""
echo "Setting cache directory permissions for you..."
sudo chown -R www-data:www-data /var/lib/php/sessions/
chmod 755 ./
chmod -R 755 app/Libraries
chmod -R 777 app/resources
chmod -R 755 libraries
chmod -R 755 resources
chmod -R 777 www
chmod +x ./tmux
alias tmux='./tmux'
echo ""

echo ""
echo "Installation complete."
echo "Now run the setup via the web site's /install page."
echo "------------------------"
