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

#edit powerprocess.php
if [[ $FIX_POSIX  == "true" ]]; then
    echo "editing powerprocess"
    $SED -i -e 's/case SIGSTKFLT:/\/\/case SIGSTKFLT:/' $NEWZPATH/www/lib/powerprocess.php
    $SED -i -e 's/return 'SIGSTKFLT';/\/\/return 'SIGSTKFLT';/' $NEWZPATH/www/lib/powerprocess.php
    $SED -i -e 's/case SIGCLD:/\/\/case SIGCLD:/' $NEWZPATH/www/lib/powerprocess.php
    $SED -i -e 's/return 'SIGCLD';/\/\/return 'SIGCLD';/' $NEWZPATH/www/lib/powerprocess.php
    $SED -i -e 's/case SIGPOLL:/\/\/case SIGPOLL:/' $NEWZPATH/www/lib/powerprocess.php
    $SED -i -e 's/return 'SIGPOLL';/\/\/return 'SIGPOLL';/' $NEWZPATH/www/lib/powerprocess.php
    $SED -i -e 's/case SIGPWR:/\/\/case SIGPWR:/' $NEWZPATH/www/lib/powerprocess.php
    $SED -i -e 's/return 'SIGPWR';/\/\/return 'SIGPWR';/' $NEWZPATH/www/lib/powerprocess.php
fi

#attempt to get english only from IMDB
if [[ $EN_IMDB == "true" ]]; then
    echo "edit movie language"
    $SED -i -e 's/akas.imdb/www.imdb/g' $NEWZPATH/www/lib/movie.php
    $SED -i -e 's/curl_setopt($ch, CURLOPT_URL, $url);/curl_setopt($ch, CURLOPT_URL, $url);\
    $header[] = "Accept-Language: en-us";\
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);/' $NEWZPATH/www/lib/util.php
fi

#import kevin123's compression mod
if [[ $KEVIN_SAFER == "true" ]] || [[ $PARSING_MOD == "true" ]]; then
    cd $DIR"/kevin123"
    cp -frv * $NEWZPATH/www/lib/
fi
#copy needed files for hash_decrypt and fixReleaseNames scripts
#if [[ $HASH == "true" ]] || [[ $FIXRELEASES == "true" ]]; then
#	cd $DIR"/test/files to copy/www/lib"
#	cp -frv * $NEWZPATH/www/lib/
#fi	

#set user/group to www
echo "Fixing permisions, this can take some time if you have a large set of releases"
if [[ $CHOWN_TRUE == "true" ]]; then
    chown $WWW_USER $NEWZPATH/*
    chown -R $WWW_USER $NEWZPATH/www/
    chown -R $WWW_USER $NEWZPATH/db/
    chown -R $WWW_USER $NEWZPATH/misc/
    chmod 775 $NEWZPATH/www/lib/smarty/templates_c
    chmod -R 775 $NEWZPATH/www/covers
    chmod 775 $NEWZPATH/www
    #chmod 775 $NEWZPATH/www/install
else
    chmod 777 $NEWZPATH/www/lib/smarty/templates_c
    chmod -R 777 $NEWZPATH/www/covers
    chmod 777 $NEWZPATH/www
    #chmod 777 $NEWZPATH/www/install
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

