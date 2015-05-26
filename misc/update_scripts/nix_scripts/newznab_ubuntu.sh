#!/bin/sh
#
# Ian - 16/11/2011
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


# Newznab variables 
NN_PATH="/var/www/newznab/misc/update_scripts"
NN_BINUP="update_binaries.php"
NN_RELUP="update_releases.php"
NN_SLEEP_TIME="600" # in seconds . 10sec is good for 100s of groups. 600sec might be a good start for fewer.
NN_PID_PATH="/var/run/" 
PIDFILE="newznab_binup.pid"

test -f /lib/lsb/init-functions || exit 1
. /lib/lsb/init-functions



case "$1" in
  start)
	[ -f ${NN_PID_PATH}${PIDFILE} ] && { echo "$0 is already ruNNing."; false; }
        echo -n "Starting Newznab binaries update"
        cd ${NN_PATH}
        (while (true);do cd ${NN_PATH} && php ${NN_BINUP}  2>&1 > /dev/null && php ${NN_RELUP}  2>&1 > /dev/null ; sleep ${NN_SLEEP_TIME} ;done) &
        PID=`echo $!`
        echo $PID > ${NN_PID_PATH}${PIDFILE}
        ;;
  stop)
        echo -n "Stopping Newznab binaries update"
        kill -9 `cat ${NN_PID_PATH}${PIDFILE}`
        ;;

  *)
        echo "Usage: $0 [start|stop]"
        exit 1
esac

