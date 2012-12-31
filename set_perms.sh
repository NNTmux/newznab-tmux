#!/bin/bash
source edit_these.sh

if [[ $AGREED == "no" ]]; then
        echo "Please edit the edit_these.sh file"
        exit
fi

cp $TESTING_PATH/nzb-importmodified.php $NEWZPATH/www/admin/

if [ -d "/home/$USERNAME" ]; then
        $SED -i -e "s/changeme/$NZBPATH/" conf/.tmux.conf
        cp conf/.tmux.conf /home/$USERNAME/.tmux.conf
fi

$SED -i -e 's/$this->processAdditional();/\/\/$this->>processAdditional();/' $NEWZPATH/www/lib/postprocess.php
$SED -i -e 's/$this->processNfos();/\/\/$this->processNfos();/' $NEWZPATH/www/lib/postprocess.php
$SED -i -e 's/$this->processUnwanted();/\/\/$this->processUnwanted();/' $NEWZPATH/www/lib/postprocess.php
$SED -i -e 's/$this->processMovies();/\/\/$this->processMovies();/' $NEWZPATH/www/lib/postprocess.php
$SED -i -e 's/$this->processMusic();/\/\/$this->processMusic();/' $NEWZPATH/www/lib/postprocess.php
$SED -i -e 's/$this->processBooks();/\/\/$this->processBooks();/' $NEWZPATH/www/lib/postprocess.php
$SED -i -e 's/$this->processGames();/\/\/$this->processGames();/' $NEWZPATH/www/lib/postprocess.php
$SED -i -e 's/$this->processTv();/\/\/$this->processTv();/' $NEWZPATH/www/lib/postprocess.php
$SED -i -e 's/$this->processMusicFromMediaInfo();/\/\/$this->processMusicFromMediaInfo();/' $NEWZPATH/www/lib/postprocess.php
$SED -i -e 's/$this->processOtherMiscCategory();/\/\/$this->processOtherMiscCategory();/' $NEWZPATH/www/lib/postprocess.php
$SED -i -e 's/$this->processUnknownCategory();/\/\/$this->processUnknownCategory();/' $NEWZPATH/www/lib/postprocess.php
chmod 777 $NEWZPATH/www/lib/smarty/templates_c
chmod -R 777 $NEWZPATH/www/covers
chmod 777 $NEWZPATH/www
chmod 777 $NEWZPATH/www/install
chmod -R 777 $NEWZPATH/nzbfiles
chown -R www-data:www-data /var/www/
chmod 777 $NEWZPATH/nzbfiles/tmpunrar/
