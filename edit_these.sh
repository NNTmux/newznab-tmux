#!/usr/bin/env bash

##EDIT THESE##

export NEWZPATH="/var/www/newznab"

export NEWZNAB_PATH=$NEWZPATH"/misc/update_scripts"
export INNODB_PATH=$NEWZPATH"/misc/testing/innodb"
export TESTING_PATH=$NEWZPATH"/misc/testing"
export ADMIN_PATH=$NEWZPATH"/www/admin"
export USERNAME="what is you name" # this is the user name that will run these scripts
export NEWZNAB_IMPORT_SLEEP_TIME="60" # in seconds - this includes import_nzb backfill and current fill
export NEWZNAB_POST_SLEEP_TIME="1" # in seconds - this is for post processing - sleep between loops
export MAXDAYS="200"  #max days for backfill
export NZBS="/path/to/nzbs"  #path to your nzb files to be imported

#Choose to run the threaded or non-threaded newznab scripts true/false
export THREADS="true"

#Choose your database, comment the one true/false
export INNODB="true"

#Choose to run update_cleanup.php true/false
export CLEANUP="true"

#By using this script you understand that the programmer is not responsible for any loss of data, users, or sanity.
#You also agree that you were smart enough to make a backup of your database and files. Do you agree? yes/no

export AGREED="no"

##END OF EDITS##


command -v mysql >/dev/null 2>&1 || { echo >&2 "I require mysql but it's not installed.  Aborting."; exit 1; } && export MYSQL=`command -v mysql`
command -v sed >/dev/null 2>&1 || { echo >&2 "I require sed but it's not installed.  Aborting."; exit 1; } && export SED=`command -v sed`
command -v php5 >/dev/null 2>&1 && export PHP=`command -v php5` || { export PHP=`command -v php`; }
command -v tmux >/dev/null 2>&1 || { echo >&2 "I require tmux but it's not installed.  Aborting."; exit 1; } && export TMUX=`command -v tmux`
command -v nmon >/dev/null 2>&1 || { echo >&2 "I require nmon but it's not installed.  Aborting."; exit 1; } && export NMON=`command -v nmon`
command -v mytop >/dev/null 2>&1|| { echo >&2 "I require mytop but it's not installed.  Aborting."; exit 1; } && export MYTOP=`command -v mytop`

