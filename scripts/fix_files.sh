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

# Make sure only root can run our script
if [[ $EUID -ne 0 ]]; then
    echo "This script must be run as root"
    exit 1
fi

source $DIR/../defaults.sh

#purge smarty cache
cd $NEWZPATH"/www/lib/smarty/templates_c/"
rm -fv *

#edit cleanup scripts
if [[ $CLEANUP_EDIT  == "true" ]]; then
    sed -i -e 's/^$echo =.*$/$echo = false;/' $TESTING_PATH/update_parsing.php
    sed -i -e 's/^$limited =.*$/$limited = false;/' $TESTING_PATH/update_parsing.php
    sed -i -e 's/^$echo =.*$/$echo = false;/' $TESTING_PATH/update_cleanup.php
    sed -i -e 's/^$limited =.*$/$limited = false;/' $TESTING_PATH/update_cleanup.php
fi

#import kevin123's compression mod
if [[ $KEVINS_COMP == "true" ]]; then
    cd $DIR"/kevin123"
    cp -frv * $NEWZPATH/www/lib/
fi

#set user/group to www
echo "Fixing permisions, this can take some time if you have a large set of releases"
if [[ $CHOWN_TRUE == "true" ]]; then
    chown -vv $WWW_USER $NEWZPATH/*
    chown -Rvv $WWW_USER $NEWZPATH/www/
    chown -Rvv $WWW_USER $NEWZPATH/db/
    chown -Rvv $WWW_USER $NEWZPATH/docs/
    chown -Rvv $WWW_USER $NEWZPATH/misc/
    chmod 775 $NEWZPATH/www/lib/smarty/templates_c
    chmod -R 775 $NEWZPATH/www/covers
    chmod 775 $NEWZPATH/www
    chmod 775 $NEWZPATH/www/install
else
    chmod 777 $NEWZPATH/www/lib/smarty/templates_c
    chmod -R 777 $NEWZPATH/www/covers
    chmod 777 $NEWZPATH/www
    chmod 777 $NEWZPATH/www/install
fi

echo -e "\033[38;5;160mCompleted\033[39m"

echo -e "\033[1;33m\n\nIf the nmon, bwg-nm windows close when you select networking, then you will need to use sudo or su."
echo -e "or add them to sudo for your user.\n"
echo -e "Tmux is very easy to use. To detach from the current session, use Ctrl-a d. You can select"
echo -e "simply by clicking in it and you can resize by dragging the borders.\n"
echo -e "To reattach to a running session, tmux att."
echo -e "To navigate between panes, Ctrl-a q then the number of the pane."
echo -e "To navigate between windows, Ctrl-a then the number of the window."
echo -e "To create a new window, Ctrl-a c \n\n\033[0m"
exit

