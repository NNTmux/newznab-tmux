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
   echo "This script must be run as root" 1>&2
   exit 1
fi

source ../defaults.sh
eval $( $SED -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" "$NEWZPATH"/www/config.php )

if [ -f $NEWZPATH/www/lib/postprocess.php ]; then
  sudo $SED -i -e 's/\/\/$this->processAdditional();/$this->processAdditional();/' $NEWZPATH/www/lib/postprocess.php
  sudo $SED -i -e 's/\/\/$this->processNfos();/$this->processNfos();/' $NEWZPATH/www/lib/postprocess.php
  sudo $SED -i -e 's/\/\/$this->processUnwanted();/$this->processUnwanted();/' $NEWZPATH/www/lib/postprocess.php
  sudo $SED -i -e 's/\/\/$this->processMovies();/$this->processMovies();/' $NEWZPATH/www/lib/postprocess.php
  sudo $SED -i -e 's/\/\/$this->processMusic();/$this->processMusic();/' $NEWZPATH/www/lib/postprocess.php
  sudo $SED -i -e 's/\/\/$this->processBooks();/$this->processBooks();/' $NEWZPATH/www/lib/postprocess.php
  sudo $SED -i -e 's/\/\/$this->processGames();/$this->processGames();/' $NEWZPATH/www/lib/postprocess.php 
  sudo $SED -i -e 's/\/\/$this->processTv();/$this->processTv();/' $NEWZPATH/www/lib/postprocess.php
  sudo $SED -i -e 's/\/\/$this->processMusicFromMediaInfo();/$this->processMusicFromMediaInfo();/' $NEWZPATH/www/lib/postprocess.php
  sudo $SED -i -e 's/\/\/$this->processOtherMiscCategory();/$this->processOtherMiscCategory();/' $NEWZPATH/www/lib/postprocess.php
  sudo $SED -i -e 's/\/\/$this->processUnknownCategory();/$this->processUnknownCategory();/' $NEWZPATH/www/lib/postprocess.php
fi

#Get the path to tmpunrar
TMPUNRAR_QUERY="SELECT value from site where setting = \"tmpunrarpath\";"
TMPUNRAR_PATH=`$MYSQL --defaults-file=../conf/my.cnf -u$DB_USER -h$DB_HOST $DB_NAME -s -N -e "${TMPUNRAR_QUERY}"`
TMPUNRAR_PATH=$TMPUNRAR_PATH"1"

#remove the ramdisk
if [[ ! `mountpoint -q $TMPUNRAR_PATH` ]]; then
  umount $TMPUNRAR_PATH &> /dev/null
fi

#remove the temp folder
rm -r $TMPUNRAR_PATH

echo "My edits have been removed"

exit
