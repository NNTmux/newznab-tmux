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

export USER="username"
export PATH_RAMDISK="/var/ramdisk"
export MYSQL_PATH="/var/lib/mysql/newznab"
export SQL_BACKUP="/home/$USER/sql_backup"

/etc/init.d/mysql stop

if ! grep -q '#RAMDISK' "/etc/fstab" ; then
  echo "" | sudo tee -a /etc/fstab
  echo "#RAMDISK" | sudo tee -a /etc/fstab
  echo "tmpfs $PATH_RAMDISK tmpfs user,nodev,nodiratime,nosuid,size=5G,mode=777 0 0" | sudo tee -a /etc/fstab
fi

mkdir -p $PATH_RAMDISK

#determine if ramdisk is in fstab
if [[ ! `mount | grep "$PATH_RAMDISK"` ]]; then
    mount "$PATH_RAMDISK"
fi

if [ ! -d "$SQL_BACKUP" ]; then
    mkdir $SQL_BACKUP
fi


if [ ! -h "$MYSQL_PATH/parts.frm" ]; then
    cp $MYSQL_PATH/parts.frm $SQL_BACKUP/
    mv $MYSQL_PATH/parts.frm $PATH_RAMDISK/
    ln -s $PATH_RAMDISK/parts.frm $MYSQL_PATH/
fi
if [ ! -h "$MYSQL_PATH/parts.MYD" ]; then
    cp $MYSQL_PATH/parts.MYD $SQL_BACKUP/
    mv $MYSQL_PATH/parts.MYD $PATH_RAMDISK/
    ln -s $PATH_RAMDISK/parts.MYD $MYSQL_PATH/
fi
if [ ! -h "$MYSQL_PATH/parts.MYI" ]; then
    cp $MYSQL_PATH/parts.MYI $SQL_BACKUP/
    mv $MYSQL_PATH/parts.MYI $PATH_RAMDISK/
    ln -s $PATH_RAMDISK/parts.MYI $MYSQL_PATH/
fi


if [ ! -h "$MYSQL_PATH/partrepair.frm" ]; then
    cp $MYSQL_PATH/partrepair.frm $SQL_BACKUP/
    mv $MYSQL_PATH/partrepair.frm $PATH_RAMDISK/
    ln -s $PATH_RAMDISK/partrepair.frm $MYSQL_PATH/
fi
if [ ! -h "$MYSQL_PATH/partrepair.MYD" ]; then
    cp $MYSQL_PATH/partrepair.MYD $SQL_BACKUP/
    mv $MYSQL_PATH/partrepair.MYD $PATH_RAMDISK/
    ln -s $PATH_RAMDISK/partrepair.MYD $MYSQL_PATH/
fi
if [ ! -h "$MYSQL_PATH/partrepair.MYI" ]; then
    cp $MYSQL_PATH/partrepair.MYI $SQL_BACKUP/
    mv $MYSQL_PATH/partrepair.MYI $PATH_RAMDISK/
    ln -s $PATH_RAMDISK/partrepair.MYI $MYSQL_PATH/
fi


if [ ! -h "$MYSQL_PATH/binaries.frm" ]; then
    cp $MYSQL_PATH/binaries.frm $SQL_BACKUP/
    mv $MYSQL_PATH/binaries.frm $PATH_RAMDISK/
    ln -s $PATH_RAMDISK/binaries.frm $MYSQL_PATH/
fi
if [ ! -h "$MYSQL_PATH/binaries.MYD" ]; then
    cp $MYSQL_PATH/binaries.MYD $SQL_BACKUP/
    mv $MYSQL_PATH/binaries.MYD $PATH_RAMDISK/
    ln -s $PATH_RAMDISK/binaries.MYD $MYSQL_PATH/
fi
if [ ! -h "$MYSQL_PATH/binaries.MYI" ]; then
    cp $MYSQL_PATH/binaries.MYI $SQL_BACKUP/
    mv $MYSQL_PATH/binaries.MYI $PATH_RAMDISK/
    ln -s $PATH_RAMDISK/binaries.MYI $MYSQL_PATH/
fi

chown -R $USER:$USER $SQL_BACKUP/
chown -R mysql:mysql $PATH_RAMDISK
chown -R mysql:mysql $MYSQL_PATH

/etc/init.d/mysql start
exit
