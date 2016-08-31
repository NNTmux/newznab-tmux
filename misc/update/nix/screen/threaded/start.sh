#!/bin/sh

if [ -e "NNBase.php" ]
then
	export NNTMUX_ROOT="$(pwd)"
else
	export NNTMUX_ROOT="$(php ../../../../../NNBase.php)"
fi

export NNTMUX_PATH="${NNTMUX_ROOT}/misc/update"
export HELP_PATH="${NNTMUX_ROOT}/misc/update/nix/screen/threaded"
export THREAD_PATH="${NNTMUX_ROOT}/misc/update/nix/multiprocessing"
export TEST_PATH="${NNTMUX_ROOT}/misc/testing"

command -v php5 >/dev/null 2>&1 && export PHP=`command -v php5` || { export PHP=`command -v php`; }
command -v python3 >/dev/null 2>&1 && export PYTHON=`command -v python3` || { export PYTHON=`command -v python`; }

export SCREEN="$(which screen)"
export NNTMUX_SLEEP_TIME="60"
	   LASTOPTIMIZE=`date +%s`
	   LASTOPTIMIZE1=`date +%s`
	   LASTOPTIMIZE2=`date +%s`

while :
do
	sleep 1
	CURRTIME=`date +%s`
	tmux kill-session -t NNTPProxy
	$PHP ${NNTMUX_PATH}/nntpproxy.php

	cd ${NNTMUX_PATH}
	if ! $SCREEN -list | grep -q "POSTP"; then
		cd $NNTMUX_PATH && $SCREEN -dmS POSTP $PHP $NNTMUX_PATH/postprocess.php allinf true
	fi

	cd ${THREAD_PATH}
		echo "Start Multi-Processing binaries.php..."
	$PHP ${THREAD_PATH}/binaries.php 0
		echo "Start Multi-Processing backfill.php..."
	$PHP ${THREAD_PATH}/backfill.php

	cd ${HELP_PATH}
	if ! $SCREEN -list | grep -q "RELEASES"; then
		cd $HELP_PATH && $SCREEN -dmS RELEASES sh $HELP_PATH/helper.sh
	fi

	cd ${TEST_PATH}
	DIFF=$(($CURRTIME-$LASTOPTIMIZE))
	if [ "$DIFF" -gt 900 ] || [ "$DIFF" -lt 1 ]
	then
		LASTOPTIMIZE=`date +%s`
		echo "Cleaning DB..."
		$PHP ${TEST_PATH}/Releases/fixReleaseNames.php 1 true all yes
		$PHP ${TEST_PATH}/Releases/fixReleaseNames.php 3 true other yes
		$PHP ${TEST_PATH}/Releases/fixReleaseNames.php 5 true other yes
	fi

	cd ${NNTMUX_PATH}
	DIFF=$(($CURRTIME-$LASTOPTIMIZE1))
	if [ "$DIFF" -gt 7200 ] || [ "$DIFF" -lt 1 ]
	then
		LASTOPTIMIZE1=`date +%s`
		echo "Optimizing DB..."
		$PHP ${NNTMUX_PATH}/optimise_db.php space
	fi

	DIFF=$(($CURRTIME-$LASTOPTIMIZE2))
	if [ "$DIFF" -gt 43200 ] || [ "$DIFF" -lt 1 ]
	then
		LASTOPTIMIZE2=`date +%s`
	fi

	echo "waiting ${NNTMUX_SLEEP_TIME} seconds..."
	sleep ${NNTMUX_SLEEP_TIME}

done
