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

#updates to newest svn
svn co --force --username svnplus --password $SVN_PASSWORD svn://svn.newznab.com/nn/branches/nnplus $NEWZPATH/
sleep 2

#force download/overwrite of current svn
svn export --force --username svnplus --password $SVN_PASSWORD svn://svn.newznab.com/nn/branches/nnplus $NEWZPATH/

#update db to current rev
cd $NEWZPATH"/misc/update_scripts"
$PHP update_database_version.php

rm -fr $NEWZPATH"/www/install"

cd $DIR/scripts
./fix_files.sh
