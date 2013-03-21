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

#edit this to allow script to run, be sure of the paths
#the ramdisk needs to be twice the size of the parts table max size, it needs room to copy itself during optimise
#i take no responsibility if this fails and you lose you db
#remove the next 2 lines when you have edited this file properlly
echo "Please edit this script, very carefully!"
exit

export PATH_RAMDISK="/var/ramdisk"
export MYSQL_PATH="/var/lib/mysql/newznab"
export SQL_BACKUP="/home/$USER/sql_backup"

/etc/init.d/mysql stop

if [ -h "$MYSQL_PATH/parts.frm" ]; then
    rm $MYSQL_PATH/parts.frm
    mv $PATH_RAMDISK/parts.frm $MYSQL_PATH/
fi
if [ -h "$MYSQL_PATH/parts.MYD" ]; then
    rm $MYSQL_PATH/parts.MYD
    mv $PATH_RAMDISK/parts.MYD $MYSQL_PATH/
fi
if [ -h "$MYSQL_PATH/parts.MYI" ]; then
    rm $MYSQL_PATH/parts.MYI
    mv $PATH_RAMDISK/parts.MYI $MYSQL_PATH/
fi


if [ -h "$MYSQL_PATH/partrepair.frm" ]; then
    rm $MYSQL_PATH/partrepair.frm
    mv $PATH_RAMDISK/partrepair.frm $MYSQL_PATH/
fi
if [ -h "$MYSQL_PATH/partrepair.MYD" ]; then
    rm $MYSQL_PATH/partrepair.MYD
    mv $PATH_RAMDISK/partrepair.MYD $MYSQL_PATH/
fi
if [ -h "$MYSQL_PATH/partrepair.MYI" ]; then
    rm $MYSQL_PATH/partrepair.MYI
    mv $PATH_RAMDISK/partrepair.MYI $MYSQL_PATH/
fi


if [ -h "$MYSQL_PATH/binaries.frm" ]; then
    rm $MYSQL_PATH/binaries.frm
    mv $PATH_RAMDISK/binaries.frm $MYSQL_PATH/
fi
if [ -h "$MYSQL_PATH/binaries.MYD" ]; then
    rm $MYSQL_PATH/binaries.MYD
    mv $PATH_RAMDISK/binaries.MYD $MYSQL_PATH/
fi
if [ -h "$MYSQL_PATH/binaries.MYI" ]; then
    rm $MYSQL_PATH/binaries.MYI
    mv $PATH_RAMDISK/binaries.MYI $MYSQL_PATH/
fi

chown -R mysql:mysql $MYSQL_PATH

#determine if ramdisk is in fstab
if [[ `mount | grep "$PATH_RAMDISK"` ]]; then
    umount "$PATH_RAMDISK"
fi

/etc/init.d/mysql start
exit
