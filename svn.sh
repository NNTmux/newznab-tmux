#EDIT_THESE
export NEWZPATH="/var/www/newznab"
export PASSWORD="password"

svn export --force --username svnplus --password $PASSWORD svn://svn.newznab.com/nn/branches/nnplus $NEWZPATH/

cd $NEWZPATH"/misc/update_scripts"
php5 update_database_version.php

cd $NEWZPATH"/misc/update_scripts/nix_scripts/tmux/"
./set_perms.sh

