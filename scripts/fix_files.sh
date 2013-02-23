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
if [[ $CHOWN_TRUE == "true" ]]; then
    chown -c $WWW_USER $NEWZPATH/*
    chown -Rc $WWW_USER $NEWZPATH/www/
    chown -Rc $WWW_USER $NEWZPATH/db/
    chown -Rc $WWW_USER $NEWZPATH/docs/
    chown -Rc $WWW_USER $NEWZPATH/misc/
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

