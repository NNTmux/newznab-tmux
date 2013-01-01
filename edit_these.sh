#!/bin/bash

##EDIT THESE##

export NEWZPATH="/var/www/newznab"

export NEWZNAB_PATH=$NEWZPATH"/misc/update_scripts"
export INNODB_PATH=$NEWZPATH"/misc/testing/innodb"
export TESTING_PATH=$NEWZPATH"/misc/testing"
export ADMIN_PATH=$NEWZPATH"/www/admin"
export USERNAME="jonnyboy" # this is the user name that will run these scripts
export NEWZNAB_IMPORT_SLEEP_TIME="60" # in seconds - this includes import_nzb backfill and current fill
export NEWZNAB_POST_SLEEP_TIME="1" # in seconds - this is for post processing - sleep between loops
export MAXDAYS="200"  #max days for backfill
export NZBS="/path/to/nzbs"  #path to your nzb files to be imported

#Choose to run the threaded or non-threaded newznab scripts true/false
export THREADS="true"

#Choose your database, comment the one true/false
export INNODB="true"

#Choose which app to run in the middle right pane, of course it must be installed. Your choices are mytop and bwm-ng.
#Only have 1 uncommented at a time.

#export CHOICE_APP="bwm-ng"
export CHOICE_APP="mytop"

#By using this script you understand that the programmer is not responsible for any loss of data, users, or sanity.
#You also agree that you were smart enough to make a backup of your database and files. Do you agree? yes/no

export AGREED="no"

##END OF EDITS##


export MYSQL=`which mysql`
export SED=`which sed`
command -v php5 >/dev/null && export PHP=`which php5` || { export PHP=`which php`; }
command -v tmux >/dev/null && export TMUX=`which tmux`
command -v bwm-ng >/dev/null && export BWMNG=`which bwm-ng`
command -v mytop >/dev/null && export MYTOP=`which mytop`
