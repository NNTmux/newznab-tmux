#!/bin/sh
#
# /etc/init.d/newznab: start and stop the newznab update script
#
# run update-rc.d newznab_ubuntu.sh defaults


### BEGIN INIT INFO
# Provides:          Newznab
# Required-Start:    $remote_fs $syslog
# Required-Stop:     $remote_fs $syslog
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: Start newznab at boot time
# Description:       Enable newznab service provided by daemon.
### END INIT INFO

RED=$(tput setaf 1)
GREEN=$(tput setaf 2)
NORMAL=$(tput sgr0)

col=40 

# Newznab variables 

NN_PATH="/var/www/newznab/htdocs/misc/update_scripts"
NN_BINUP="update_binaries.php"
NN_RELUP="update_releases.php"
NN_PREDB="update_predb.php true"
NN_OPT="optimise_db.php"
NN_TV="update_tvschedule.php"
NN_THEATERS="update_theaters.php"
NN_SLEEP_TIME="10" # in seconds . 10sec is good for 100s of groups. 600sec might be a good start for fewer.
NN_PID_PATH="/var/run/" 
PIDFILE="newznab_sh.pid"
LASTOPTIMIZE=`date +%s`

test -f /lib/lsb/init-functions || exit 1
. /lib/lsb/init-functions

case "$1" in
  start)
	if [ -f ${NN_PID_PATH}${PIDFILE} ]
	then
		echo "$0 is already running."
	else
		echo -n "Starting Newznab binaries update..."
		cd ${NN_PATH}
		while :
			do 
				CURRTIME=`date +%s`
				php ${NN_BINUP}  2>&1 > /dev/null && php ${NN_RELUP}  2>&1 > /dev/null && php ${NN_PREDB}  2>&1 > /dev/null
				DIFF=$(($CURRTIME-$LASTOPTIMIZE))
				if [ "$DIFF" -gt 43200 ] || [ "$DIFF" -lt 1 ]
				then
					LASTOPTIMIZE=`date +%s`
					php ${NN_OPT}  2>&1 > /dev/null && php ${NN_TV}  2>&1 > /dev/null && php ${NN_THEATERS}  2>&1 > /dev/null
				fi
				sleep ${NN_SLEEP_TIME}
			done &
		PID=$!
		echo $PID > ${NN_PID_PATH}${PIDFILE}
		sleep 2
		if [ -f ${NN_PID_PATH}${PIDFILE} ]
		then
			printf '%s%*s%s\n' "$GREEN" $col '[OK]' "$NORMAL"
		else
			printf '%s%*s%s\n' "$RED" $col '[FAIL]' "$NORMAL"
		fi
        fi &
	;;
  stop)
        echo -n "Stopping Newznab binaries update..."
        kill -9 `cat ${NN_PID_PATH}${PIDFILE}` && kill -9 `cat ${NN_PID_PATH}${PIDFILE}` && rm ${NN_PID_PATH}${PIDFILE}
	sleep 2
	if [ -f ${NN_PID_PATH}${PIDFILE} ]
	then
		printf '%s%*s%s\n' "$RED" $col '[FAIL]' "$NORMAL"
	else
		printf '%s%*s%s\n' "$GREEN" $col '[OK]' "$NORMAL"
	fi
        ;;

  *)
        echo "Usage: $0 [start|stop]"
        exit 1
esac

