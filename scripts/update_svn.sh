#!/usr/bin/env bash

# Make sure only root can run our script
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

#EDIT_THESE
export NEWZPATH="/var/www/newznab"
source $NEWZPATH"/misc/update_scripts/nix_scripts/tmux/defaults.sh"
export PASSWORD="password"

svn co --force --username svnplus --password $PASSWORD svn://svn.newznab.com/nn/branches/nnplus $NEWZPATH/
sleep 2
svn export --force --username svnplus --password $PASSWORD svn://svn.newznab.com/nn/branches/nnplus $NEWZPATH/

cd $NEWZPATH"/misc/update_scripts"
php5 update_database_version.php

echo " "

#purge smarty cache
cd $NEWZPATH"/www/lib/smarty/templates_c/"
rm -fv *

if [[ $KEVINS_COMP == "true" ]]; then
  cd $NEWZPATH"/misc/update_scripts/nix_scripts/tmux/kevin123"
  cp -frv * $NEWZPATH/www/lib/
fi

cd $NEWZPATH"/misc/update_scripts/nix_scripts/tmux/scripts"

./set_perms.sh

