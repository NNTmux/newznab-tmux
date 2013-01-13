#!/usr/bin/env bash
SOURCE="${BASH_SOURCE[0]}"
DIR="$( dirname "$SOURCE" )"
while [ -h "$SOURCE" ]
do
  SOURCE="$(readlink "$SOURCE")"
  [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE"
  DIR="$( cd -P "$( dirname "$SOURCE"  )" && pwd )"
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

source edit_these.sh
clear

if [[ $AGREED == "no" ]]; then
        echo "Please edit the edit_these.sh file"
        exit
fi

echo -e "\033[38;5;148mcp $TESTING_PATH/nzb-importmodified.php $NEWZPATH/www/admin/"
cp $TESTING_PATH/nzb-importmodified.php $NEWZPATH/www/admin/

if [ -d "/home/$USERNAME" ]; then
	echo "cp conf/.tmux.conf /home/$USERNAME/.tmux.conf"
        cp conf/.tmux.conf /home/$USERNAME/.tmux.conf
        $SED -i 's,'changeme,"$NZBS"',' "/home/$USERNAME/.tmux.conf"
fi
if [ -d "$HOME" ]; then
        echo "cp conf/.tmux.conf $HOME/.tmux.conf"
        cp conf/.tmux.conf $HOME/.tmux.conf
        $SED -i 's,'changeme,"$NZBS"',' "$HOME/.tmux.conf"
fi

echo "Editing $NEWZPATH/www/lib/postprocess.php"
if [ ! -f $NEWZPATH/www/lib/postprocess.php.orig ]; then
	cp $NEWZPATH/www/lib/postprocess.php $NEWZPATH/www/lib/postprocess.php.orig
fi
if ! grep -q '//$this->processAdditional();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processAdditional();/\/\/$this->processAdditional();/' $NEWZPATH/www/lib/postprocess.php
fi
if ! grep -q '//$this->processNfos();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processNfos();/\/\/$this->processNfos();/' $NEWZPATH/www/lib/postprocess.php
fi
if ! grep -q '//$this->processUnwanted();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processUnwanted();/\/\/$this->processUnwanted();/' $NEWZPATH/www/lib/postprocess.php
fi
if ! grep -q '//$this->processMovies();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processMovies();/\/\/$this->processMovies();/' $NEWZPATH/www/lib/postprocess.php
fi
if ! grep -q '//$this->processMusic();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processMusic();/\/\/$this->processMusic();/' $NEWZPATH/www/lib/postprocess.php
fi
if ! grep -q '//$this->processBooks();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processBooks();/\/\/$this->processBooks();/' $NEWZPATH/www/lib/postprocess.php
fi
if ! grep -q '//$this->processGames();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processGames();/\/\/$this->processGames();/' $NEWZPATH/www/lib/postprocess.php
fi
if ! grep -q '//$this->processTv();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processTv();/\/\/$this->processTv();/' $NEWZPATH/www/lib/postprocess.php
fi
if ! grep -q '//$this->processMusicFromMediaInfo();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processMusicFromMediaInfo();/\/\/$this->processMusicFromMediaInfo();/' $NEWZPATH/www/lib/postprocess.php
fi
if ! grep -q '//$this->processOtherMiscCategory();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processOtherMiscCategory();/\/\/$this->processOtherMiscCategory();/' $NEWZPATH/www/lib/postprocess.php
fi
if ! grep -q '//$this->processUnknownCategory();' "$NEWZPATH/www/lib/postprocess.php" ; then
	$SED -i -e 's/$this->processUnknownCategory();/\/\/$this->processUnknownCategory();/' $NEWZPATH/www/lib/postprocess.php
fi

echo "Fixing permisions, this can take some time if you have a large set of releases"
chmod 777 $NEWZPATH/www/lib/smarty/templates_c
chmod -R 777 $NEWZPATH/www/covers
chmod 777 $NEWZPATH/www
chmod 777 $NEWZPATH/www/install
mkdir -p $NEWZPATH/nzbfiles/tmpunrar2/
mkdir -p $NEWZPATH/nzbfiles/tmpunrar3/
chmod -R 777 $NEWZPATH/nzbfiles

echo -e "\033[38;5;160mCompleted\033[39m"

echo -e "This script includes nmon and mytop. Please install them prior to running ./start.sh."
echo -e "If the nmon pane close when you select networking, then you will need to  use sudo or su."
echo -e "Tmux is very easy to use. To detach from the current session, use Ctrl-a d. You can select"
echo -e "simply by clicking in it and you can resize by dragging the borders."


exit

