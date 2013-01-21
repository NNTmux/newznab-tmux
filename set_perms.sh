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

echo -e "\033[38;5;148mEditing $NEWZPATH/www/lib/postprocess.php"
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
mkdir -p $NEWZPATH/nzbfiles/tmpunrar4/
chmod -R 777 $NEWZPATH/nzbfiles

echo -e "\033[38;5;160mCompleted\033[39m"

echo -e "\033[1;33m\n\nIf the nmon, bwg-nm windows close when you select networking, then you will need to  use sudo or su."
echo -e "Tmux is very easy to use. To detach from the current session, use Ctrl-a d. You can select"
echo -e "simply by clicking in it and you can resize by dragging the borders."
echo -e "To reattach to a running session, tmux att."
echo -e "To navigate between panes, Ctrl-a q then the number of the pane."
echo -e "To navigate between windows, Ctrl-a then the number of the window.\n\n\033[0m"

exit

