#!/bin/bash

set -e

##EDIT THESE##


export NEWZNAB_PATH='/var/www/newznab/misc/update_scripts'
export INNODB_PATH='/var/www/newznab/misc/testing/innodb'
export TESTING_PATH='/var/www/newznab/misc/testing'
export ADMIN_PATH='/var/www/newznab/www/admin'
export NEWZPATH='/var/www/newznab'
export USERNAME='whats your name' # this is the user name that will run these scripts
export NEWZNAB_IMPORT_SLEEP_TIME='60' # in seconds - this includes import_nzb backfill and current fill
export NEWZNAB_POST_SLEEP_TIME='1' # in seconds - this is for post processing - sleep between loops
export MAXDAYS='200'  #max days for backfill
export NZBS='/path/to/nzbs'  #path to your nzb files to be imported

#Choose to run the threaded or non-threaded newznab scripts true/false
export THREADS='true'

#Choose your database, comment the one true/false
export INNODB='true'

#By using this script you understand that the programmer is not responsible for any loss of data, users, or sanity.
#You also agree that you were smart enough to make a backup of your database and files. Do you agree? yes/no

export AGREED='no'

##END OF EDITS##


export MYSQL=`which mysql`
export SED=`which sed`
command -v php5 >/dev/null && export PHP=`which php5` || { export PHP=`which php`; }
command -v tmux >/dev/null && export TMUX=`which tmux` || { echo tmux command not found.\n apt-get install tmux\n or if on a real server yum install tmux; exit 1; }
#command -v bwm-ng >/dev/null && export BWMNG=`which bwm-ng` || { echo bwm-ng command not found.\n apt-get install bwm-ng; exit 1; }

