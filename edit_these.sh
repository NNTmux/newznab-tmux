#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

#EDIT THESE#

export NEWZPATH="/var/www/newznab"

export NEWZNAB_PATH=$NEWZPATH"/misc/update_scripts"
export TESTING_PATH=$NEWZPATH"/misc/testing"
export ADMIN_PATH=$NEWZPATH"/www/admin"
export INNODB_PATH=$DIR"/bin/innodb"

#Select the user name that will run these scripts
export USERNAME="what is your username"

#Enter the session name to be used by tmux
export TMUX_SESSION="Newznab-dev"

#Set, in seconds - how often the monitor.php script should up, 0 may cause errors
export MONITOR_UPDATE="20"

#Set the maximum days tp backfill
export MAXDAYS="210"

#Set the path to the nzb dump you downloaded from torrents
export NZBS="/path/to/nzbs"

#Choose to run the threaded or non-threaded newznab scripts true/false
export THREADS="true"

#Choose your database, comment the one true/false
export INNODB="true"

#Choose to run update_cleanup.php true/false
export CLEANUP="true"

#Choose to run update_binaries true/false
export BINARIES="true"

#Choose to run backfill script true/false
export BACKFILL="true"

#Choose to run import nzb script true/false
export IMPORT="true"

#Set the max amount of unprocessed releases and still allow import or backfill to run
export MAX_RELEASES="30000"

#Specify your SED binary
export SED="/bin/sed"
#export SED="/usr/local/bin/gsed"

#Select some monitoring script, if they are not installed, it will not affect the running of the scripts
export USE_HTOP="false"
export USE_NMON="false"
export USE_BWMNG="false"
export USE_IOTOP="false"
export USE_MYTOP="false"
export USE_VNSTAT="false"

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
