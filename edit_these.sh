#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

#EDIT THESE#

export NEWZPATH="/var/www/newznab"

export NEWZNAB_PATH=$NEWZPATH"/misc/update_scripts"
export TESTING_PATH=$NEWZPATH"/misc/testing"
export ADMIN_PATH=$NEWZPATH"/www/admin"
export INNODB_PATH=$DIR"/bin/innodb"

#Post Processing Additional is the processing that downloads rar and attempts to get info for your site
#you are able to set the number of process to be run from 1-12
#trial and error for this and do to the sorting method 1 runs always, 2 if more than 200, 3 more than 300 and so on.
#not implemented, yet
export POST_TO_RUN="1";

#Enter the session name to be used by tmux
export TMUX_SESSION="Newznab-dev"

#Set, in seconds - how often the monitor.php (left top pane) script should update, 0 may cause errors
export MONITOR_UPDATE="20"

#Set, in seconds - how long the update_binaries should sleep between runs, 0 may cause errors
export NNTP_SLEEP="20"

#Set, in seconds - how long the backfill should sleep between runs, 0 may cause errors
export BACKFILL_SLEEP="20"

#Set, in seconds - how long the update_release should sleep between runs, 0 may cause errors
export RELEASES_SLEEP="20"

#Set, in seconds - how long the nzb-import should sleep between runs, 0 may cause errors
export IMPORT_SLEEP="20"

#Set the maximum days to backfill, you set the nn+ admin/edit backfill to 1
#this will increment your database by 1 after each backfill loop
#once your backfill numbers reach $MAXDAYS, then it will no long increment the database
#backfill will continue to run, and do no work, at that point you should disable backfill, below
export MAXDAYS="210"

#Set the path to the nzb dump you downloaded from torrents, theis is the path to bulk files folder of nzbs
#this does not recurse through subfolders
export NZBS="/path/to/nzbs"

#Choose to run the threaded or non-threaded newznab scripts true/false
#such as update_binaries.php or update_binaries_threaded.php
export THREADS="true"

#Choose your database engine, comment the one true/false
#you should have already converted your database to InnoDB engine, if you select true here
export INNODB="true"

#Choose to run update_cleanup.php true/false
#set to false by default, you will need to edit /misc/testing/update_cleanup.php and /misc/testing/update_parsing.php
#to actually do anything, directions are in the file
export CLEANUP="false"

#Choose to run update_binaries true/false
export BINARIES="true"

#Choose to run backfill script true/false
export BACKFILL="true"

#Choose to run import nzb script true/false
export IMPORT="true"

#Choose to run optimise_db script true/false
#set to false by default, you should test the optimse scripts in bin/innodb first
export OPTIMISE="false"

#Set the max amount of unprocessed releases and still allow nzb-import to run
#set to 0 to disable
export IMPORT_MAX_RELEASES="0"

#Set the max amount of unprocessed releases and still allow backfill to run
#set to 0 to disable
export BACKFILL_MAX_RELEASES="0"

#Set the max amount of unprocessed releases and still allow update_releases to run
#set to 0 to disable
export MAX_RELEASES="0"

#Specify your SED binary
export SED="/bin/sed"
#export SED="/usr/local/bin/gsed"

#Select some monitoring script, if they are not installed, it will not affect the running of the scripts
#these are set to false by default, enable if you want them
export USE_HTOP="false"
export USE_NMON="false"
export USE_BWMNG="false"
export USE_IOTOP="false"
export USE_MYTOP="false"
export USE_VNSTAT="false"

#Each pane may have periods of inactivity, at the time "Pane iis Dead" will be displayed.
#To disable my notes about why this is ok, change to false
export SHOW_WHY="true"

#By using this script you understand that the programmer is not responsible for any loss of data, users, or sanity.
#You also agree that you were smart enough to make a backup of your database and files. Do you agree? yes/no

export AGREED="no"

##END OF EDITS##

command -v mysql >/dev/null 2>&1 || { echo >&2 "I require mysql but it's not installed.  Aborting."; exit 1; } && export MYSQL=`command -v mysql`
command -v php5 >/dev/null 2>&1 && export PHP=`command -v php5` || { export PHP=`command -v php`; }
command -v tmux >/dev/null 2>&1 || { echo >&2 "I require tmux but it's not installed.  Aborting."; exit 1; } && export TMUX=`command -v tmux`


if [[ $USE_HTOP == "true" ]]; then
      command -v htop >/dev/null 2>&1|| { echo >&2 "I require htop but it's not installed.  Aborting."; exit 1; } && export HTOP=`command -v htop`
fi
if [[ $USE_NMON == "true" ]]; then
      command -v nmon >/dev/null 2>&1 || { echo >&2 "I require nmon but it's not installed.  Aborting."; exit 1; } && export NMON=`command -v nmon`
fi
if [[ $USE_BWMNG == "true" ]]; then
      command -v bwm-ng >/dev/null 2>&1|| { echo >&2 "I require bwm-ng but it's not installed.  Aborting."; exit 1; } && export BWMNG=`command -v bwm-ng`
fi
if [[ $USE_IOTOP == "true" ]]; then
      command -v iotop >/dev/null 2>&1|| { echo >&2 "I require iotop but it's not installed.  Aborting."; exit 1; } && export IOTOP=`command -v iotop`
fi
if [[ $USE_MYTOP == "true" ]]; then
      command -v mytop >/dev/null 2>&1|| { echo >&2 "I require mytop but it's not installed.  Aborting."; exit 1; } && export MYTOP=`command -v mytop`
fi
if [[ $USE_VNSTAT == "true" ]]; then
      command -v vnstat >/dev/null 2>&1|| { echo >&2 "I require vnstat but it's not installed. Aborting."; exit 1; } && export VNSTAT=`command -v vnstat`
fi

