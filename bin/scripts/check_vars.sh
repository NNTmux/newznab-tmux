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

source defaults.sh

echo -e "\033[38;5;160m"
for vars in RUNNING USE_TWO_NNTP USE_TWO_PP NFOS GAMES MOVIES MUSIC TVRAGE EBOOK OTHERS UNWANTED KEEP_KILLED SEQUENTIAL BINARIES BINARIES_THREADS BACKFILL BACKFILL_THREADS KEVIN_SAFER KEVIN_BACKFILL_PARTS KEVIN_THREADED IMPORT NZB_THREADS IMPORT_TRUE MISC_ONLY RELEASES OPTIMIZE OPTIMIZE_KILL INNODB CLEANUP CLEANUP_EDIT PARSING PARSING_MOD FIX_DROID PAST_24_HOURS PREDB SPOTNAB SPOTNAB_ACTIVE TV_SCHEDULE SPHINX KILL_QUIET DELETE_PARTS FIX_POSIX USE_HTOP USE_BWMNG USE_MYTOP USE_ATOP USE_NMON USE_IOTOP USE_VNSTAT USE_TCPTRACK USE_TOP USE_IFTOP USE_CONSOLE POWERLINE EN_IMDB RAMDISK CHOWN_TRUE WRITE_LOGS 
do
	#echo $vars=\"${!vars}\"
	if [[ ${!vars} != "true" ]] && [[ ${!vars} != "false" ]]; then
		clear
		echo -e "$vars=\"${!vars}\" is not valid. Please edit defaults.sh and correct it. Aborting.\n"; exit 1
	fi
done

if ! [[ $NICENESS -le 19 && $NICENESS -ge -20 ]]; then
        clear
	echo -e "NICENESS=$NICENESS is not valid. Please edit defaults.sh and correct it. Aborting.\n"; exit 1
fi

for vars in MAX_LOAD MAX_LOAD_RELEASES MONITOR_UPDATE KILL_UPDATES BINARIES_SEQ_TIMER BINARIES_SLEEP BINARIES_MAX_RELEASES BINARIES_MAX_BINS BINARIES_MAX_ROWS BACKFILL_SLEEP BACKFILL_MAX_RELEASES BACKFILL_MAX_BINS BACKFILL_MAX_ROWS MAXDAYS KEVIN_PARTS NZB_FOLDER_COUNT NZBCOUNT IMPORT_SLEEP IMPORT_MAX_RELEASES IMPORT_MAX_ROWS RELEASES_SLEEP MYISAM_SMALL MYISAM_LARGE INNODB_SMALL INNODB_LARGE CLEANUP_TIMER PARSING_TIMER PREDB_TIMER SPOTNAB_TIMER TVRAGE_TIMER SPHINX_TIMER KILL_PROCESS DELETE_TIMER
do
	if ! [[ ${!vars} =~ ^[0-9]+([.][0-9]+)?$ ]]; then
                clear
		echo -e "$vars=\"${!vars}\" is not valid. Please edit defaults.sh and correct it. Aborting.\n"; exit 1
	fi
done

for vars in POST_TO_RUN_A POST_TO_RUN_B
do
	if ! [[ ${!vars} -le 16 && ${!vars} -ge 0 ]]; then
                clear
        	echo -e "$vars=\"${!vars}\" is not valid. Please edit defaults.sh and correct it. Aborting.\n"; exit 1
	fi
done

for vars in NEWZPATH NEWZNAB_PATH TESTING_PATH ADMIN_PATH NZBS RAMDISK_PATH
do
	if [ ! -d ${!vars} ]; then
                clear
		echo -e "$vars=\"${!vars}\" is not valid. Please edit defaults.sh and correct it. Aborting.\n"; exit 1
	fi
done

for vars in KEVIN_DATE
do
        if ! [[ ${!vars} =~ ^2[0-1][0-9][0-9]-[0-1][0-9]-[0-3][0-9] ]]; then
                clear
                echo -e "$vars=\"${!vars}\" is not valid. Please edit defaults.sh and correct it. Aborting.\n"; exit 1
        fi
done

for vars in SED USER_DEF_ONE USER_DEF_TWO USER_DEF_THREE USER_DEF_FOUR USER_DEF_FIVE
do
        if [ ! -f ${!vars} ]; then
                clear
                echo -e "$vars=\"${!vars}\" is not valid. Please edit defaults.sh and correct it. Aborting.\n"; exit 1
        fi
done

if [[ $NEWZDASH_URL ]]; then
	wget -nv $NEWZDASH_URL > /dev/null 2>&1 &
	pid=$!
	wait $!
	script_exit_value=$?
	if [ "${script_exit_value}" -ne "0" ] ; then
		echo -e "NEWZDASH_URL=\"$NEWZDASH_URL\" is not valid. Please edit defaults.sh and correct it. Aborting.\n"; exit 1
	fi
fi

echo -e "\033[0m"
